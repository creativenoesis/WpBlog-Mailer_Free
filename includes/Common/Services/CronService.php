<?php
/**
 * Cron Service
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services;

use WPBlogMailer\Common\Services\ActionSchedulerService;

/**
 * CronService Class
 *
 * Handles cron job scheduling and management using Action Scheduler
 *
 * Action Scheduler provides more reliable scheduling than WP-Cron:
 * - Database-backed queue (survives server restarts)
 * - Automatic retry logic for failed jobs
 * - Better handling of concurrent requests
 * - Built-in admin UI for monitoring
 * - No dependency on site traffic
 */
class CronService {

    /**
     * Cron hook names
     */
    const HOOK_SEND_EMAILS = 'wpbm_send_newsletter'; // Fixed: Changed from 'wpbm_send_scheduled_emails' to match Plugin.php
    const HOOK_PROCESS_QUEUE = 'wpbm_process_email_queue';
    const HOOK_CLEANUP = 'wpbm_cleanup_old_data';
    const HOOK_WEEKLY_REPORT = 'wpbm_send_weekly_report';
    const HOOK_UPDATE_ENGAGEMENT_SCORES = 'wpbm_update_engagement_scores';
    const HOOK_CHECK_AB_TESTS = 'wpbm_check_ab_tests';
    const HOOK_CLEANUP_EXPORTS = 'wpbm_cleanup_exports';

    /**
     * Initialize cron service
     * Registers custom intervals - should be called early
     *
     * @return void
     */
    public static function init() {
        self::register_custom_intervals();
    }

    /**
     * Schedule all cron jobs
     *
     * @return void
     */
    public static function schedule() {
        // Ensure Action Scheduler is available
        if (!ActionSchedulerService::is_available()) {
            error_log('WPBM: Action Scheduler not available. Falling back to WP-Cron.');
            self::schedule_with_wp_cron();
            return;
        }

        // Send scheduled emails (when new post published) - Available in all tiers
        ActionSchedulerService::schedule_recurring_action(
            time(),
            HOUR_IN_SECONDS,
            self::HOOK_SEND_EMAILS
        );

        // Process email queue (Starter+ feature ONLY)
        if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
            ActionSchedulerService::schedule_recurring_action(
                time(),
                5 * MINUTE_IN_SECONDS,
                self::HOOK_PROCESS_QUEUE
            );
        }

        // Cleanup old data (weekly) - Available in all tiers
        ActionSchedulerService::schedule_recurring_action(
            time(),
            WEEK_IN_SECONDS,
            self::HOOK_CLEANUP
        );

        // Send weekly analytics report (Pro feature ONLY)
        if (function_exists('wpbm_is_pro') && wpbm_is_pro()) {
            // Schedule for Monday at 9:00 AM by default
            $settings = get_option('wpbm_weekly_report_settings', []);
            $send_day = isset($settings['send_day']) ? $settings['send_day'] : 'monday';
            $send_time = isset($settings['send_time']) ? $settings['send_time'] : '09:00';

            // Calculate next occurrence
            $next_run = strtotime("next {$send_day} {$send_time}");
            if ($next_run === false) {
                $next_run = strtotime("next monday 09:00");
            }

            ActionSchedulerService::schedule_recurring_action(
                $next_run,
                WEEK_IN_SECONDS,
                self::HOOK_WEEKLY_REPORT
            );

            // Update engagement scores daily (Pro feature)
            ActionSchedulerService::schedule_recurring_action(
                time(),
                DAY_IN_SECONDS,
                self::HOOK_UPDATE_ENGAGEMENT_SCORES
            );

            // Check A/B test completion hourly (Pro feature)
            ActionSchedulerService::schedule_recurring_action(
                time(),
                HOUR_IN_SECONDS,
                self::HOOK_CHECK_AB_TESTS
            );

            // Cleanup old export files daily (Pro feature)
            ActionSchedulerService::schedule_recurring_action(
                time(),
                DAY_IN_SECONDS,
                self::HOOK_CLEANUP_EXPORTS
            );
        }
    }
    
    /**
     * Clear all cron jobs
     *
     * @return void
     */
    public static function clear() {
        // Clear Action Scheduler actions
        if (ActionSchedulerService::is_available()) {
            ActionSchedulerService::unschedule_action(self::HOOK_SEND_EMAILS);
            ActionSchedulerService::unschedule_action(self::HOOK_PROCESS_QUEUE);
            ActionSchedulerService::unschedule_action(self::HOOK_CLEANUP);
            ActionSchedulerService::unschedule_action(self::HOOK_WEEKLY_REPORT);
            ActionSchedulerService::unschedule_action(self::HOOK_UPDATE_ENGAGEMENT_SCORES);
            ActionSchedulerService::unschedule_action(self::HOOK_CHECK_AB_TESTS);
            ActionSchedulerService::unschedule_action(self::HOOK_CLEANUP_EXPORTS);
        }

        // Also clear WP-Cron jobs (in case of migration from old version)
        wp_clear_scheduled_hook(self::HOOK_SEND_EMAILS);
        wp_clear_scheduled_hook(self::HOOK_PROCESS_QUEUE);
        wp_clear_scheduled_hook(self::HOOK_CLEANUP);
        wp_clear_scheduled_hook(self::HOOK_WEEKLY_REPORT);
        wp_clear_scheduled_hook(self::HOOK_UPDATE_ENGAGEMENT_SCORES);
        wp_clear_scheduled_hook(self::HOOK_CHECK_AB_TESTS);
        wp_clear_scheduled_hook(self::HOOK_CLEANUP_EXPORTS);
    }

    /**
     * Schedule all cron jobs (alias for schedule)
     * Used by plugin activation hook
     *
     * @return void
     */
    public static function schedule_events() {
        self::schedule();
    }

    /**
     * Clear all cron jobs (alias for clear)
     * Used by plugin deactivation hook
     *
     * @return void
     */
    public static function unschedule_events() {
        self::clear();
    }

    /**
     * Fallback: Schedule using WP-Cron
     * Used only if Action Scheduler is not available
     *
     * @return void
     */
    private static function schedule_with_wp_cron() {
        // Ensure custom intervals are registered
        self::register_custom_intervals();

        // Send scheduled emails (when new post published) - Available in all tiers
        if (!wp_next_scheduled(self::HOOK_SEND_EMAILS)) {
            wp_schedule_event(time(), 'hourly', self::HOOK_SEND_EMAILS);
        }

        // Process email queue (Starter+ feature ONLY)
        if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
            if (!wp_next_scheduled(self::HOOK_PROCESS_QUEUE)) {
                wp_schedule_event(time(), 'every_five_minutes', self::HOOK_PROCESS_QUEUE);
            }
        }

        // Cleanup old data (weekly) - Available in all tiers
        if (!wp_next_scheduled(self::HOOK_CLEANUP)) {
            wp_schedule_event(time(), 'weekly', self::HOOK_CLEANUP);
        }

        // Send weekly analytics report (Pro feature ONLY)
        if (function_exists('wpbm_is_pro') && wpbm_is_pro()) {
            if (!wp_next_scheduled(self::HOOK_WEEKLY_REPORT)) {
                // Schedule for Monday at 9:00 AM by default
                $settings = get_option('wpbm_weekly_report_settings', []);
                $send_day = isset($settings['send_day']) ? $settings['send_day'] : 'monday';
                $send_time = isset($settings['send_time']) ? $settings['send_time'] : '09:00';

                // Calculate next occurrence
                $next_run = strtotime("next {$send_day} {$send_time}");
                if ($next_run === false) {
                    $next_run = strtotime("next monday 09:00");
                }

                wp_schedule_event($next_run, 'weekly', self::HOOK_WEEKLY_REPORT);
            }

            // Update engagement scores daily (Pro feature)
            if (!wp_next_scheduled(self::HOOK_UPDATE_ENGAGEMENT_SCORES)) {
                wp_schedule_event(time(), 'daily', self::HOOK_UPDATE_ENGAGEMENT_SCORES);
            }

            // Check A/B test completion hourly (Pro feature)
            if (!wp_next_scheduled(self::HOOK_CHECK_AB_TESTS)) {
                wp_schedule_event(time(), 'hourly', self::HOOK_CHECK_AB_TESTS);
            }

            // Cleanup old export files daily (Pro feature)
            if (!wp_next_scheduled(self::HOOK_CLEANUP_EXPORTS)) {
                wp_schedule_event(time(), 'daily', self::HOOK_CLEANUP_EXPORTS);
            }
        }
    }

    /**
     * Register custom cron intervals
     * Only needed for WP-Cron fallback
     *
     * @return void
     */
    private static function register_custom_intervals() {
        add_filter('cron_schedules', function($schedules) {
            // Every 5 minutes
            $schedules['every_five_minutes'] = array(
                'interval' => 300,
                'display'  => esc_html__('Every 5 Minutes', 'blog-mailer')
            );

            // Every 15 minutes
            $schedules['every_fifteen_minutes'] = array(
                'interval' => 900,
                'display'  => esc_html__('Every 15 Minutes', 'blog-mailer')
            );

            // Bi-weekly (every 2 weeks)
            $schedules['biweekly'] = array(
                'interval' => 1209600, // 14 days in seconds
                'display'  => esc_html__('Every 2 Weeks', 'blog-mailer')
            );

            return $schedules;
        });
    }
}