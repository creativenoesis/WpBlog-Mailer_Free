<?php
/**
 * Cron Handler
 * Handles Pro feature cron jobs (engagement scores, A/B tests, exports cleanup, weekly reports)
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Handlers;

use WPBlogMailer\Common\Services\CronStatusService;
use WPBlogMailer\Core\ServiceContainer;

defined('ABSPATH') || exit;

/**
 * CronHandler Class
 *
 * Responsible for:
 * - Handling Pro feature cron jobs
 * - Engagement scoring updates
 * - A/B test completion checking
 * - Export file cleanup
 * - Weekly report generation
 */
class CronHandler {

    /**
     * @var ServiceContainer
     */
    private $container;

    /**
     * Constructor
     *
     * @param ServiceContainer $container
     */
    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    /**
     * Handle weekly report cron job (Pro feature)
     */
    public function handle_weekly_report_send() {
        $cron_status_service = new CronStatusService();

        // Log start of execution
        $log_id = $cron_status_service->log_execution('wpbm_send_weekly_report', 'started', 'Weekly report sending started');

        $weekly_report_service = $this->container->get(\WPBlogMailer\Pro\Services\WeeklyReportService::class);

        if (!$weekly_report_service) {
            $error_msg = 'WeeklyReportService not initialized';
            $cron_status_service->log_execution('wpbm_send_weekly_report', 'failed', $error_msg);
            return;
        }

        try {
            // Generate and send the weekly report
            $result = $weekly_report_service->generate_and_send_report();

            // Log the result
            $message = isset($result['message']) ? $result['message'] : 'Weekly report sent';
            if (isset($result['message'])) {
            }

            // Determine status based on result
            $status = isset($result['success']) && $result['success'] ? 'success' : 'failed';

            $cron_status_service->log_execution('wpbm_send_weekly_report', $status, $message, $result);
        } catch (\Exception $e) {
            $error_msg = 'Exception during weekly report send: ' . $e->getMessage();
            $cron_status_service->log_execution('wpbm_send_weekly_report', 'failed', $error_msg);
        }
    }

    /**
     * Handle engagement score update cron job (Pro only)
     * Updates engagement scores for all active subscribers
     */
    public function handle_update_engagement_scores() {
        $cron_status_service = new CronStatusService();

        // Log start of execution
        $log_id = $cron_status_service->log_execution('wpbm_update_engagement_scores', 'started', 'Engagement score update started');

        try {
            $engagement_service = $this->container->get(\WPBlogMailer\Pro\Services\EngagementScoringService::class);

            if (!$engagement_service) {
                $error_msg = 'EngagementScoringService not initialized';
                $cron_status_service->log_execution('wpbm_update_engagement_scores', 'failed', $error_msg);
                return;
            }

            // Update scores in batches of 100 subscribers
            $result = $engagement_service->update_all_scores(100);

            $message = sprintf('Updated engagement scores for %d out of %d subscribers',
                $result['updated_count'],
                $result['total_processed']
            );

            $cron_status_service->log_execution('wpbm_update_engagement_scores', 'success', $message, $result);

        } catch (\Exception $e) {
            $error_msg = 'Exception during engagement score update: ' . $e->getMessage();
            $cron_status_service->log_execution('wpbm_update_engagement_scores', 'failed', $error_msg);
        }
    }

    /**
     * Handle A/B test completion checking cron job (Pro only)
     * Checks if any running A/B tests have completed and selects winners
     */
    public function handle_check_ab_tests() {
        $cron_status_service = new CronStatusService();

        // Log start of execution
        $log_id = $cron_status_service->log_execution('wpbm_check_ab_tests', 'started', 'A/B test check started');

        try {
            $ab_test_service = $this->container->get(\WPBlogMailer\Pro\Services\ABTestService::class);

            if (!$ab_test_service) {
                $error_msg = 'ABTestService not initialized';
                $cron_status_service->log_execution('wpbm_check_ab_tests', 'failed', $error_msg);
                return;
            }

            // Check and complete tests
            $ab_test_service->check_and_complete_tests();

            $message = 'A/B test completion check completed';
            $cron_status_service->log_execution('wpbm_check_ab_tests', 'success', $message);

        } catch (\Exception $e) {
            $error_msg = 'Exception during A/B test check: ' . $e->getMessage();
            $cron_status_service->log_execution('wpbm_check_ab_tests', 'failed', $error_msg);
        }
    }

    /**
     * Handle export cleanup cron job (Pro only)
     * Cleans up old export files older than 7 days
     */
    public function handle_cleanup_exports() {
        $cron_status_service = new CronStatusService();

        // Log start of execution
        $log_id = $cron_status_service->log_execution('wpbm_cleanup_exports', 'started', 'Export cleanup started');

        try {
            $export_service = $this->container->get(\WPBlogMailer\Pro\Services\AnalyticsExportService::class);

            if (!$export_service) {
                $error_msg = 'AnalyticsExportService not initialized';
                $cron_status_service->log_execution('wpbm_cleanup_exports', 'failed', $error_msg);
                return;
            }

            // Cleanup exports older than 7 days
            $export_service->cleanup_old_exports(7);

            $message = 'Export files cleanup completed';
            $cron_status_service->log_execution('wpbm_cleanup_exports', 'success', $message);

        } catch (\Exception $e) {
            $error_msg = 'Exception during export cleanup: ' . $e->getMessage();
            $cron_status_service->log_execution('wpbm_cleanup_exports', 'failed', $error_msg);
        }
    }
}
