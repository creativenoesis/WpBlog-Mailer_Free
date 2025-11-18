<?php
/**
 * Custom Email Handler
 * Handles custom email sending operations (Starter+ feature)
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Handlers;

use WPBlogMailer\Common\Services\SubscriberService;
use WPBlogMailer\Common\Services\BaseEmailService;
// Note: SegmentService loaded conditionally to avoid errors in free version

defined('ABSPATH') || exit;

/**
 * CustomEmailHandler Class
 *
 * Responsible for:
 * - Handling custom email form submissions
 * - Sending custom emails to subscribers
 * - Handling recipient selection (all, tags, segments)
 */
class CustomEmailHandler {

    /**
     * @var SubscriberService
     */
    private $subscriber_service;

    /**
     * @var BaseEmailService
     */
    private $email_service;

    /**
     * Constructor
     *
     * @param SubscriberService $subscriber_service
     * @param BaseEmailService $email_service
     */
    public function __construct(SubscriberService $subscriber_service, BaseEmailService $email_service) {
        $this->subscriber_service = $subscriber_service;
        $this->email_service = $email_service;
    }

    /**
     * Handle custom email sending (Starter+ feature)
     */
    public function handle_send_custom_email() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        // Verify nonce
        if (!isset($_POST['wpbm_custom_email_nonce']) || !wp_verify_nonce($_POST['wpbm_custom_email_nonce'], 'wpbm_send_custom_email')) {
            wp_die(esc_html__('Security check failed.', 'blog-mailer'));
        }

        // Check tier
        if (!wpbm_is_starter()) {
            wp_die(esc_html__('This feature requires Starter or Pro plan.', 'blog-mailer'));
        }

        // Get form data
        $subject = isset($_POST['email_subject']) ? sanitize_text_field(wp_unslash($_POST['email_subject'])) : '';
        $content = isset($_POST['email_content']) ? wp_kses_post($_POST['email_content']) : '';
        $recipient_type = isset($_POST['recipient_type']) ? sanitize_text_field(wp_unslash($_POST['recipient_type'])) : 'all';

        if (empty($subject) || empty($content)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'wpbm-custom-email',
                'error' => 'empty_fields'
            ], admin_url('admin.php')));
            exit;
        }

        // Get recipients based on type
        $recipients = $this->get_recipients($recipient_type);

        if (empty($recipients)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'wpbm-custom-email',
                'error' => 'no_recipients'
            ], admin_url('admin.php')));
            exit;
        }

        // Get plugin settings
        $settings = get_option('wpbm_settings', []);
        $from_name = isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
        $from_email = isset($settings['from_email']) ? $settings['from_email'] : get_bloginfo('admin_email');

        // Prepare headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        // Send emails
        $success_count = 0;
        $failed_count = 0;

        foreach ($recipients as $recipient) {
            try {
                $sent = $this->email_service->send(
                    $recipient->email,
                    $subject,
                    $content,
                    $headers,
                    [
                        'subscriber_id' => isset($recipient->id) ? $recipient->id : 0,
                        'template' => 'custom',
                    ]
                );

                if ($sent) {
                    $success_count++;
                } else {
                    $failed_count++;
                }
            } catch (\Exception $e) {
                $failed_count++;
            }
        }

        // Redirect with success message
        wp_safe_redirect(add_query_arg([
            'page' => 'wpbm-custom-email',
            'sent' => '1',
            'success' => $success_count,
            'failed' => $failed_count
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Get recipients based on recipient type
     *
     * @param string $recipient_type Type of recipients (all, test, tag, segment)
     * @return array Array of recipient objects
     */
    private function get_recipients($recipient_type) {
        if ($recipient_type === 'test') {
            // Send test email to admin
            $admin_email = get_option('admin_email');
            return [
                (object) [
                    'email' => $admin_email,
                    'first_name' => 'Admin',
                    'last_name' => '',
                ]
            ];
        }

        if ($recipient_type === 'tag' && wpbm_is_pro()) {
            // Send to subscribers with specific tags (Pro feature)
            return $this->get_recipients_by_tags();
        }

        if ($recipient_type === 'segment' && wpbm_is_pro()) {
            // Send to predefined segment (Pro feature)
            return $this->get_recipients_by_segment();
        }

        // Send to all confirmed subscribers (default)
        return $this->subscriber_service->get_confirmed();
    }

    /**
     * Get recipients by selected tags (Pro feature)
     *
     * @return array Array of subscriber objects
     */
    private function get_recipients_by_tags() {
        $selected_tags = isset($_POST['selected_tags']) ? array_map('intval', (array)$_POST['selected_tags']) : array();

        if (empty($selected_tags)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'wpbm-custom-email',
                'error' => 'no_tags_selected'
            ], admin_url('admin.php')));
            exit;
        }

        $segment_service = new SegmentService();
        return $segment_service->get_subscribers_by_segment([
            'tags' => $selected_tags,
            'status' => 'confirmed',
            'tag_operator' => 'any'
        ]);
    }

    /**
     * Get recipients by predefined segment (Pro feature)
     *
     * @return array Array of subscriber objects
     */
    private function get_recipients_by_segment() {
        $selected_segment = isset($_POST['selected_segment']) ? sanitize_text_field(wp_unslash($_POST['selected_segment'])) : '';

        if (empty($selected_segment)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'wpbm-custom-email',
                'error' => 'no_segment_selected'
            ], admin_url('admin.php')));
            exit;
        }

        $segment_service = new SegmentService();
        $predefined_segments = $segment_service->get_predefined_segments();

        if (!isset($predefined_segments[$selected_segment])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'wpbm-custom-email',
                'error' => 'invalid_segment'
            ], admin_url('admin.php')));
            exit;
        }

        return $segment_service->get_subscribers_by_segment($predefined_segments[$selected_segment]['criteria']);
    }
}
