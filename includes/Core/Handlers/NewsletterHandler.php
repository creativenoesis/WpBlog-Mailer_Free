<?php
/**
 * Newsletter Handler
 * Handles newsletter sending operations (manual and automated)
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Handlers;

use WPBlogMailer\Common\Services\NewsletterService;
use WPBlogMailer\Common\Services\EmailQueueService;
use WPBlogMailer\Common\Services\BaseEmailService;
use WPBlogMailer\Free\Services\BasicTemplateService;
use WPBlogMailer\Common\Services\CronStatusService;
use WPBlogMailer\Core\ServiceContainer;
use WPBlogMailer\Common\Constants;

defined('ABSPATH') || exit;

/**
 * NewsletterHandler Class
 *
 * Responsible for:
 * - Handling "Send Now" button clicks
 * - Sending test emails
 * - Processing queue after manual send
 * - Handling automated newsletter cron jobs
 */
class NewsletterHandler {

    /**
     * @var NewsletterService
     */
    private $newsletter_service;

    /**
     * @var EmailQueueService|null
     */
    private $queue_service;

    /**
     * @var BaseEmailService
     */
    private $email_service;

    /**
     * @var ServiceContainer
     */
    private $container;

    /**
     * Constructor
     *
     * @param NewsletterService $newsletter_service
     * @param EmailQueueService|null $queue_service
     * @param BaseEmailService $email_service
     * @param ServiceContainer $container
     */
    public function __construct(
        NewsletterService $newsletter_service,
        $queue_service,
        BaseEmailService $email_service,
        ServiceContainer $container
    ) {
        $this->newsletter_service = $newsletter_service;
        $this->queue_service = $queue_service;
        $this->email_service = $email_service;
        $this->container = $container;
    }

    /**
     * Handle Send Newsletter Now action (manual send via admin button)
     */
    public function handle_send_newsletter_now() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        // Verify nonce
        if (!isset($_POST['wpbm_send_now_nonce']) || !wp_verify_nonce($_POST['wpbm_send_now_nonce'], 'wpbm_send_newsletter_now')) {
            wp_die(esc_html__('Security check failed.', 'blog-mailer'));
        }

        // Check if this is a test email request
        if (isset($_POST['send_test']) && $_POST['send_test'] == '1') {
            $this->send_test_email();
            return;
        }

        // Send newsletter manually (ignores date check)
        if ($this->newsletter_service) {
            // Increase time limit for large batches
            set_time_limit(Constants::NEWSLETTER_MAX_EXECUTION_TIME);

            // CRITICAL: Clear any old pending newsletter emails from queue
            $this->clear_pending_newsletter_queue();

            $result = $this->newsletter_service->send_newsletter(true); // true = manual send

            // Check result and redirect with appropriate message
            if (isset($result['success']) && $result['success'] > 0) {
                // Update last send time only if successful
                update_option('wpbm_last_newsletter_send', current_time('timestamp'));

                // If using queue service (Starter+), process ALL pending emails in multiple batches
                if ($this->queue_service) {
                    $this->process_queue_immediately($result['success']);
                    return;
                }

                wp_safe_redirect(add_query_arg([
                    'page' => 'wpbm-newsletter',
                    'newsletter_sent' => '1',
                    'sent_count' => $result['success'],
                    'failed_count' => $result['failed']
                ], admin_url('admin.php')));
                exit;
            } else {
                // No emails sent - show error
                $error_message = isset($result['message']) ? urlencode($result['message']) : urlencode('No emails were sent');
                wp_safe_redirect(add_query_arg([
                    'page' => 'wpbm-newsletter',
                    'newsletter_error' => '1',
                    'error_message' => $error_message
                ], admin_url('admin.php')));
                exit;
            }
        }

        // Fallback if newsletter service not available
        wp_safe_redirect(add_query_arg([
            'page' => 'wpbm-newsletter',
            'newsletter_error' => '1',
            'error_message' => urlencode('Newsletter service not available')
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Send test email to specified address
     */
    private function send_test_email() {
        $test_email = isset($_POST['test_email_address']) ? sanitize_email(wp_unslash($_POST['test_email_address'])) : '';

        if (empty($test_email) || !is_email($test_email)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'wpbm-newsletter',
                'test_email_error' => '1'
            ], admin_url('admin.php')));
            exit;
        }

        // Send test email
        if ($this->newsletter_service && $this->email_service) {
            // Get settings
            $settings = get_option('wpbm_settings', []);
            $subject = isset($settings['subject_line']) ? $settings['subject_line'] : 'Test Newsletter';
            $subject = str_replace('{site_name}', get_bloginfo('name'), $subject);
            $subject = str_replace('{date}', wp_date('F j, Y'), $subject);

            // Get template service
            $template_service = $this->container->get(\WPBlogMailer\Free\Services\BasicTemplateService::class);

            // Get recent posts for test
            $posts = get_posts([
                'numberposts' => isset($settings['posts_per_email']) ? intval($settings['posts_per_email']) : 5,
                'post_status' => 'publish',
                'post_type' => isset($settings['post_types']) ? $settings['post_types'] : ['post'],
                'orderby' => 'date',
                'order' => 'DESC',
            ]);

            // Create test subscriber object
            $test_subscriber = (object) [
                'id' => 0,
                'email' => $test_email,
                'first_name' => 'Test',
                'last_name' => 'User',
                'unsubscribe_key' => 'test_key_' . time(),
            ];

            // Render template
            $template_data = [
                'posts' => $posts,
                'heading' => $subject,
                'subscriber' => $test_subscriber,
            ];

            $html_content = $template_service->render($template_data);

            // Send test email
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . (isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name')) . ' <' . (isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email')) . '>',
            ];

            $sent = $this->email_service->send(
                $test_email,
                $subject,
                $html_content,
                $headers,
                ['campaign_type' => 'test']
            );

            if ($sent) {
                wp_safe_redirect(add_query_arg([
                    'page' => 'wpbm-newsletter',
                    'test_email_sent' => '1',
                    'test_email' => urlencode($test_email)
                ], admin_url('admin.php')));
                exit;
            } else {
                wp_safe_redirect(add_query_arg([
                    'page' => 'wpbm-newsletter',
                    'test_email_failed' => '1'
                ], admin_url('admin.php')));
                exit;
            }
        }
    }

    /**
     * Clear old pending newsletter emails from queue
     * Prevents reprocessing emails from incomplete previous sends
     */
    private function clear_pending_newsletter_queue() {
        if (!$this->queue_service) {
            return;
        }

        global $wpdb;
        $queue_table = $wpdb->prefix . 'wpbm_email_queue';

        // Delete ALL pending newsletter emails before Send Now
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$queue_table}
            WHERE status = 'pending'
            AND campaign_type = 'newsletter'",
            []
        ));

        if ($deleted > 0) {
        }
    }

    /**
     * Process queue immediately after Send Now in multiple batches
     *
     * @param int $emails_expected Number of emails we expect to send
     */
    private function process_queue_immediately($emails_expected) {

        // Verify queue count
        global $wpdb;
        $queue_table = $wpdb->prefix . 'wpbm_email_queue';
        $queue_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$queue_table}
            WHERE status = 'pending'",
            []
        ));


        if ($queue_count != $emails_expected) {
        }

        // Process queue in batches until we've sent to all subscribers
        $total_sent = 0;
        $total_failed = 0;
        $max_iterations = Constants::MAX_QUEUE_PROCESSING_ITERATIONS;
        $iteration = 0;

        do {
            $iteration++;
            $queue_result = $this->queue_service->process_queue();

            $batch_sent = isset($queue_result['sent']) ? $queue_result['sent'] : 0;
            $batch_failed = isset($queue_result['failed']) ? $queue_result['failed'] : 0;
            $batch_processed = isset($queue_result['processed']) ? $queue_result['processed'] : 0;

            $total_sent += $batch_sent;
            $total_failed += $batch_failed;

            // Stop conditions
            $total_processed = $total_sent + $total_failed;
            $should_stop = (
                $batch_processed == 0 ||
                $total_processed >= $emails_expected ||
                $iteration >= $max_iterations
            );

            if ($should_stop) {
                if ($batch_processed == 0) {
                } elseif ($total_processed >= $emails_expected) {
                } else {
                }
            }

        } while (!$should_stop);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpbm-newsletter',
            'newsletter_sent' => '1',
            'sent_count' => $total_sent,
            'failed_count' => $total_failed
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle newsletter sending via cron (automated send)
     */
    public function handle_newsletter_cron() {
        $cron_status_service = new CronStatusService();

        // Log start of execution
        $log_id = $cron_status_service->log_execution('wpbm_send_newsletter', 'started', 'Checking for new posts');

        if (!$this->newsletter_service) {
            $error_msg = 'NewsletterService not initialized';
            $cron_status_service->log_execution('wpbm_send_newsletter', 'failed', $error_msg);
            return;
        }

        try {
            // Send the newsletter (automated - checks date)
            $result = $this->newsletter_service->send_newsletter(false); // false = automated send

            // Update last send time if successful
            if (isset($result['success']) && $result['success'] > 0) {
                update_option('wpbm_last_newsletter_send', current_time('timestamp'));
            }

            // Log the result
            $message = isset($result['message']) ? $result['message'] : 'Newsletter sent';

            // Determine status based on result
            $status = 'success';

            // If no emails were sent (even successfully), mark as info/warning
            if (isset($result['total']) && $result['total'] === 0) {
                // No subscribers or no posts - this is informational, not a failure
                $status = 'success'; // Still success, but message will indicate why nothing sent
            } elseif (isset($result['error']) && $result['error'] > 0) {
                $status = isset($result['success']) && $result['success'] > 0 ? 'success' : 'failed';
            } elseif (isset($result['success']) && $result['success'] === 0 && isset($result['failed']) && $result['failed'] > 0) {
                // All emails failed to send
                $status = 'failed';
            }

            $cron_status_service->log_execution('wpbm_send_newsletter', $status, $message, $result);
        } catch (\Exception $e) {
            $error_msg = 'Exception during newsletter send: ' . $e->getMessage();
            $cron_status_service->log_execution('wpbm_send_newsletter', 'failed', $error_msg);
        }
    }

    /**
     * Handle email queue processing via cron (Starter+ feature)
     */
    public function handle_queue_processing_cron() {
        $cron_status_service = new CronStatusService();

        // Log start of execution
        $log_id = $cron_status_service->log_execution('wpbm_process_email_queue', 'started', 'Queue processing started');

        if (!$this->queue_service) {
            $error_msg = 'EmailQueueService not initialized';
            $cron_status_service->log_execution('wpbm_process_email_queue', 'failed', $error_msg);
            return;
        }

        try {
            // Process the queue
            $result = $this->queue_service->process_queue();

            // Log the result
            $message = '';
            if (isset($result['processed'], $result['sent'], $result['failed'])) {
                $message = sprintf(
                    'Processed %d emails - Sent: %d, Failed: %d',
                    $result['processed'],
                    $result['sent'],
                    $result['failed']
                );
            }

            // Determine status
            $status = 'success';
            if (isset($result['failed']) && $result['failed'] > 0 && (!isset($result['sent']) || $result['sent'] === 0)) {
                $status = 'failed';
            }

            $cron_status_service->log_execution('wpbm_process_email_queue', $status, $message, $result);
        } catch (\Exception $e) {
            $error_msg = 'Exception during queue processing: ' . $e->getMessage();
            $cron_status_service->log_execution('wpbm_process_email_queue', 'failed', $error_msg);
        }
    }
}
