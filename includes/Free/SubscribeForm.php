<?php
/**
 * Frontend Subscribe Form
 * * Handles frontend subscription form display and processing
 * * @package WP_Blog_Mailer
 * @subpackage Free
 * @since 2.0.0
 */

namespace WPBlogMailer\Free;

// --- START FIX: Import dependencies to be injected ---
use WPBlogMailer\Common\Services\SubscriberService;
use WPBlogMailer\Common\Services\BaseEmailService; // Added this line
// --- END FIX ---

if (!defined('ABSPATH')) exit;

class SubscribeForm {

    /**
     * Subscriber service instance
     *
     * @var SubscriberService
     */
    private $service;

    /**
     * Email service instance
     *
     * @var BaseEmailService
     */
    private $email_service;

    /**
     * Form messages
     *
     * @var array
     */
    private $messages = array();

    /**
     * Constructor
     *
     * --- START FIX: Accept dependencies from Service Container ---
     * @param SubscriberService $subscriber_service
     * @param BaseEmailService $email_service // Added this parameter
     */
    public function __construct(SubscriberService $subscriber_service, BaseEmailService $email_service) {
        // Use the injected service
        $this->service = $subscriber_service;
        $this->email_service = $email_service;
        // --- END FIX ---
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Note: Shortcode is registered in Plugin.php via register_shortcodes()

        // Handle form submission
        add_action('init', array($this, 'handle_submission'));

        // Handle email confirmation
        add_action('init', array($this, 'handle_confirmation'));

        // Handle unsubscribe
        add_action('init', array($this, 'handle_unsubscribe'));

        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Define WPBM_PLUGIN_URL and WPBM_VERSION if not already defined globally
        if (!defined('WPBM_PLUGIN_URL')) {
            define('WPBM_PLUGIN_URL', plugin_dir_url(dirname(dirname(__FILE__)))); // Adjust path if needed
        }
        if (!defined('WPBM_VERSION')) {
             // Get version from composer.json or define statically
             // This is a placeholder, adjust as needed
            $composer_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'composer.json';
            if (file_exists($composer_path)) {
                $composer_data = json_decode(file_get_contents($composer_path), true);
                define('WPBM_VERSION', $composer_data['version'] ?? '2.0.0');
            } else {
                 define('WPBM_VERSION', '2.0.0'); // Fallback version
            }
        }

        if (!is_admin()) {
            wp_enqueue_style(
                'wpbm-subscribe-form',
                WPBM_PLUGIN_URL . 'assets/css/subscribe-form.css',
                array(),
                WPBM_VERSION
            );

            wp_enqueue_script(
                'wpbm-subscribe-form',
                WPBM_PLUGIN_URL . 'assets/js/subscribe-form.js',
                array('jquery'),
                WPBM_VERSION,
                true
            );

            wp_localize_script('wpbm-subscribe-form', 'wpbmForm', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpbm_subscribe'),
                'strings' => array(
                    'processing' => esc_html__('Processing...', 'blog-mailer'),
                    'error' => esc_html__('An error occurred. Please try again.', 'blog-mailer')
                )
            ));
        }
    }

    /**
     * Render subscribe form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Form HTML
     */
    public function render_form($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'title' => esc_html__('Subscribe to Our Newsletter', 'blog-mailer'),
            'description' => esc_html__('Get the latest updates delivered to your inbox.', 'blog-mailer'),
            'button_text' => esc_html__('Subscribe', 'blog-mailer'),
            'show_name' => 'yes',
            'success_message' => esc_html__('Thank you for subscribing!', 'blog-mailer'),
            'class' => ''
        ), $atts);

        // Build form HTML
        ob_start();
        ?>
        <div class="wpbm-subscribe-form-wrapper <?php echo esc_attr($atts['class']); ?>">

            <?php if (!empty($this->messages)): ?>
                <?php foreach ($this->messages as $type => $message): ?>
                    <div class="wpbm-message wpbm-message-<?php echo esc_attr($type); ?>">
                        <?php echo esc_html($message); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!isset($this->messages['success'])): ?>

                <?php if (!empty($atts['title'])): ?>
                    <h3 class="wpbm-form-title"><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>

                <?php if (!empty($atts['description'])): ?>
                    <p class="wpbm-form-description"><?php echo esc_html($atts['description']); ?></p>
                <?php endif; ?>

                <form method="post" action="" class="wpbm-subscribe-form" id="wpbm-subscribe-form">
                    <?php wp_nonce_field('wpbm_subscribe_action', 'wpbm_subscribe_nonce'); ?>
                    <input type="hidden" name="wpbm_subscribe" value="1">

                    <div class="wpbm-form-fields">
                        <?php if ($atts['show_name'] === 'yes'): ?>
                            <div class="wpbm-form-field">
                                <label for="wpbm-first-name" class="wpbm-label">
                                    <?php esc_html_e('First Name', 'blog-mailer'); ?>
                                </label>
                                <input type="text"
                                       id="wpbm-first-name"
                                       name="wpbm_first_name"
                                       class="wpbm-input"
                                       placeholder="<?php esc_attr_e('Your first name', 'blog-mailer'); ?>">
                            </div>
                            <div class="wpbm-form-field">
                                <label for="wpbm-last-name" class="wpbm-label">
                                    <?php esc_html_e('Last Name', 'blog-mailer'); ?>
                                </label>
                                <input type="text"
                                       id="wpbm-last-name"
                                       name="wpbm_last_name"
                                       class="wpbm-input"
                                       placeholder="<?php esc_attr_e('Your last name', 'blog-mailer'); ?>">
                            </div>
                         <?php else: ?>
                             <input type="hidden" name="wpbm_first_name" value="">
                             <input type="hidden" name="wpbm_last_name" value="">
                        <?php endif; ?>

                        <div class="wpbm-form-field">
                            <label for="wpbm-email" class="wpbm-label">
                                <?php esc_html_e('Email', 'blog-mailer'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="email"
                                   id="wpbm-email"
                                   name="wpbm_email"
                                   class="wpbm-input"
                                   placeholder="<?php esc_attr_e('your@email.com', 'blog-mailer'); ?>"
                                   required>
                        </div>

                        <div class="wpbm-form-field wpbm-form-submit">
                            <button type="submit" class="wpbm-button">
                                <?php echo esc_html($atts['button_text']); ?>
                            </button>
                        </div>
                    </div>

                    <p class="wpbm-form-note">
                        <?php esc_html_e('We respect your privacy. Unsubscribe at any time.', 'blog-mailer'); ?>
                    </p>
                </form>

            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Handle form submission
     */
    public function handle_submission() {
        // Check if form was submitted
        if (!isset($_POST['wpbm_subscribe']) || $_POST['wpbm_subscribe'] !== '1') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['wpbm_subscribe_nonce']) ||
            !wp_verify_nonce($_POST['wpbm_subscribe_nonce'], 'wpbm_subscribe_action')) {
            $this->messages['error'] = esc_html__('Security check failed. Please try again.', 'blog-mailer');
            return;
        }

        // Get form data
        $first_name = isset($_POST['wpbm_first_name']) ? sanitize_text_field(wp_unslash($_POST['wpbm_first_name'])) : '';
        $last_name = isset($_POST['wpbm_last_name']) ? sanitize_text_field(wp_unslash($_POST['wpbm_last_name'])) : '';
        $email = isset($_POST['wpbm_email']) ? sanitize_email(wp_unslash($_POST['wpbm_email'])) : '';

        // Validate
        if (empty($email) || !is_email($email)) {
            $this->messages['error'] = esc_html__('Please enter a valid email address.', 'blog-mailer');
            return;
        }

        // Set default first name if empty
        if (empty($first_name) && empty($last_name)) {
             $name_part = substr($email, 0, strpos($email, '@'));
             $first_name = ucfirst(preg_replace('/[^a-zA-Z]/', '', $name_part));
        }

        // Check if email already exists
        if ($this->service->email_exists($email)) {
             $this->messages['error'] = esc_html__('This email address is already subscribed.', 'blog-mailer');
             return;
        }

        // Prepare data for the 'create' method
        $data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'status' => 'confirmed' // Free version: subscribers are immediately confirmed
        );

        // Add subscriber using the 'create' method
        try {
            $result_id = $this->service->create($data);

            if ($result_id) {
                // Free version: No double opt-in, subscribers are immediately confirmed
                $this->messages['success'] = esc_html__('Thank you for subscribing!', 'blog-mailer');

                // Fire action hook
                $full_name = trim($first_name . ' ' . $last_name);
                do_action('wpbm_subscriber_added_frontend', $result_id, $email, $full_name);
            } else {
                $this->messages['error'] = esc_html__('An error occurred while subscribing. Please try again.', 'blog-mailer');
            }
        } catch (\Exception $e) {
            // Handle subscriber limit or other exceptions
            $this->messages['error'] = $e->getMessage();
        }
    }

    /**
     * Handle email confirmation from confirmation link
     */
    public function handle_confirmation() {
        // Check if this is a confirmation request
        if (!isset($_GET['wpbm_action']) || $_GET['wpbm_action'] !== 'confirm') {
            return;
        }

        // Get confirmation parameters
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $email = isset($_GET['email']) ? sanitize_email(urldecode($_GET['email'])) : '';

        if (empty($key) || empty($email)) {
            wp_die(
                esc_html__('Invalid confirmation link.', 'blog-mailer'),
                esc_html__('Invalid Link', 'blog-mailer'),
                array('response' => 400)
            );
        }

        // Find subscriber by email and key
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_subscribers';
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE email = %s AND unsubscribe_key = %s",
            $email,
            $key
        ));

        if (!$subscriber) {
            wp_die(
                esc_html__('Invalid confirmation link or subscriber not found.', 'blog-mailer'),
                esc_html__('Subscriber Not Found', 'blog-mailer'),
                array('response' => 404)
            );
        }

        // Check if already confirmed
        if ($subscriber->status === 'confirmed') {
            wp_die(
                '<h1>' . esc_html__('Already Confirmed', 'blog-mailer') . '</h1><p>' .
                esc_html__('Your email address has already been confirmed. Thank you for subscribing!', 'blog-mailer') . '</p>' .
                '<p><a href="' . esc_url(home_url()) . '">' . esc_html__('Go to Homepage', 'blog-mailer') . '</a></p>',
                esc_html__('Already Confirmed', 'blog-mailer'),
                array('response' => 200)
            );
        }

        // Confirm the subscriber
        $confirmed = $this->service->confirm($subscriber->id);

        if ($confirmed) {
            // Fire action hook
            do_action('wpbm_subscriber_confirmed', $subscriber->id, $subscriber->email);

            // Show success message
            wp_die(
                '<h1>' . esc_html__('Subscription Confirmed!', 'blog-mailer') . '</h1><p>' .
                esc_html__('Thank you for confirming your email address. You will now receive our newsletters.', 'blog-mailer') . '</p>' .
                '<p><a href="' . esc_url(home_url()) . '">' . esc_html__('Go to Homepage', 'blog-mailer') . '</a></p>',
                esc_html__('Subscription Confirmed!', 'blog-mailer'),
                array('response' => 200)
            );
        } else {
            wp_die(
                esc_html__('An error occurred while confirming your subscription. Please try again later.', 'blog-mailer'),
                esc_html__('Confirmation Error', 'blog-mailer'),
                array('response' => 500)
            );
        }
    }

    /**
     * Handle unsubscribe from unsubscribe link
     */
    public function handle_unsubscribe() {
        // Check if this is an unsubscribe request
        if (!isset($_GET['wpbm_action']) || $_GET['wpbm_action'] !== 'unsubscribe') {
            return;
        }

        // Get unsubscribe key
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

        if (empty($key)) {
            wp_die(
                esc_html__('Invalid unsubscribe link.', 'blog-mailer'),
                esc_html__('Invalid Link', 'blog-mailer'),
                array('response' => 400)
            );
        }

        // Find subscriber by unsubscribe key
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_subscribers';
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE unsubscribe_key = %s",
            $key
        ));

        if (!$subscriber) {
            wp_die(
                esc_html__('Invalid unsubscribe link or subscriber not found.', 'blog-mailer'),
                esc_html__('Subscriber Not Found', 'blog-mailer'),
                array('response' => 404)
            );
        }

        // Check if already unsubscribed
        if ($subscriber->status === 'unsubscribed') {
            wp_die(
                '<h1>' . esc_html__('Already Unsubscribed', 'blog-mailer') . '</h1><p>' .
                esc_html__('You have already unsubscribed from our mailing list.', 'blog-mailer') . '</p>' .
                '<p><a href="' . esc_url(home_url()) . '">' . esc_html__('Go to Homepage', 'blog-mailer') . '</a></p>',
                esc_html__('Already Unsubscribed', 'blog-mailer'),
                array('response' => 200)
            );
        }

        // Unsubscribe the subscriber
        $result = $wpdb->update(
            $table_name,
            array('status' => 'unsubscribed'),
            array('id' => $subscriber->id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            // Fire action hook
            do_action('wpbm_subscriber_unsubscribed', $subscriber->id, $subscriber->email);

            // Show success message with proper title
            wp_die(
                '<h1>' . esc_html__('Unsubscribed Successfully', 'blog-mailer') . '</h1><p>' .
                esc_html__('You have been successfully unsubscribed from our mailing list. We\'re sorry to see you go!', 'blog-mailer') . '</p>' .
                '<p>' . esc_html__('If you change your mind, you can always subscribe again.', 'blog-mailer') . '</p>' .
                '<p><a href="' . esc_url(home_url()) . '">' . esc_html__('Go to Homepage', 'blog-mailer') . '</a></p>',
                esc_html__('Unsubscribed Successfully', 'blog-mailer'),
                array('response' => 200)
            );
        } else {
            wp_die(
                esc_html__('An error occurred while unsubscribing. Please try again later or contact support.', 'blog-mailer'),
                esc_html__('Unsubscribe Error', 'blog-mailer'),
                array('response' => 500)
            );
        }
    }
}

// --- FIX: Remove direct initialization. The Service Container handles this. ---
// new SubscribeForm();