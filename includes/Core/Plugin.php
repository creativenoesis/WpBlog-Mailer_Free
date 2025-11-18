<?php
/**
 * Main Plugin Class (Refactored)
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core;

// Import Admin Classes
use WPBlogMailer\Core\Admin\MenuManager;
use WPBlogMailer\Core\Admin\AssetManager;
use WPBlogMailer\Core\Admin\PageRenderer;

// Import Handlers
use WPBlogMailer\Core\Handlers\NewsletterHandler;
use WPBlogMailer\Core\Handlers\CustomEmailHandler;
use WPBlogMailer\Core\Handlers\TemplateHandler;
use WPBlogMailer\Core\Handlers\TagHandler;
use WPBlogMailer\Core\Handlers\CronHandler;

// Import Controllers
use WPBlogMailer\Free\Controllers\SubscribersController;
use WPBlogMailer\Free\SubscribeForm;

// Import Services
use WPBlogMailer\Common\Services\SubscriberService;
use WPBlogMailer\Common\Services\CronService;
// Note: TrackingService loaded conditionally to avoid errors in free version

defined('ABSPATH') || exit;

/**
 * Plugin Class
 *
 * The core plugin class that orchestrates all plugin functionality.
 * Delegates responsibilities to specialized classes for better maintainability.
 */
class Plugin {

    /**
     * Plugin version
     * @var string
     */
    const VERSION = '2.0.0';

    /**
     * Plugin instance (Singleton)
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin base directory
     * @var string
     */
    private $plugin_path;

    /**
     * Plugin base URL
     * @var string
     */
    private $plugin_url;

    /**
     * The Dependency Injection container
     * @var ServiceContainer
     */
    public $container;

    // --- Admin & Handler Classes ---
    /** @var MenuManager */
    private $menu_manager;

    /** @var AssetManager */
    private $asset_manager;

    /** @var PageRenderer */
    private $page_renderer;

    /** @var NewsletterHandler */
    private $newsletter_handler;

    /** @var CustomEmailHandler */
    private $custom_email_handler;

    /** @var TemplateHandler */
    private $template_handler;

    /** @var TagHandler */
    private $tag_handler;

    /** @var CronHandler */
    private $cron_handler;

    // --- Core Services ---
    /** @var SubscribersController */
    private $subscribers_controller;

    /** @var SubscribeForm */
    private $form_controller;

    /** @var TrackingService|null */
    private $tracking_service;

    /** @var SubscriberService */
    private $subscriber_service;

    /** @var AnalyticsInterface|null */
    private $analytics_service;

    /**
     * Get plugin instance (Singleton pattern)
     *
     * @return Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private for Singleton)
     */
    private function __construct() {
        // Use constants defined in wp-blog-mailer.php
        $this->plugin_path = defined('WPBM_PLUGIN_PATH') ? WPBM_PLUGIN_PATH : plugin_dir_path(dirname(dirname(__FILE__)));
        $this->plugin_url = defined('WPBM_PLUGIN_URL') ? WPBM_PLUGIN_URL : plugin_dir_url(dirname(dirname(__FILE__)));

        // Create the container
        $this->container = new ServiceContainer();

        // Initialize everything
        $this->init();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    private function init() {
        // Initialize cron service (registers custom intervals)
        CronService::init();

        // Load admin classes, handlers, and core services from container
        $this->init_admin_classes();
        $this->init_handlers();
        $this->init_core_services();

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Initialize admin classes using the container
     */
    private function init_admin_classes() {
        $this->page_renderer = $this->container->get(PageRenderer::class);
        $this->menu_manager = $this->container->get(MenuManager::class);
        $this->asset_manager = $this->container->get(AssetManager::class);
    }

    /**
     * Initialize handlers using the container
     */
    private function init_handlers() {
        $this->newsletter_handler = $this->container->get(NewsletterHandler::class);
        $this->custom_email_handler = $this->container->get(CustomEmailHandler::class);
        $this->template_handler = $this->container->get(TemplateHandler::class);
        $this->tag_handler = $this->container->get(TagHandler::class);
        $this->cron_handler = $this->container->get(CronHandler::class);
    }

    /**
     * Initialize core services using the container
     */
    private function init_core_services() {
        $this->subscribers_controller = $this->container->get(SubscribersController::class);
        $this->form_controller = $this->container->get(SubscribeForm::class);

        // TrackingService only available in Pro
        $this->tracking_service = null;
        if ( class_exists( '\WPBlogMailer\Pro\Services\TrackingService' ) ) {
            try {
                $this->tracking_service = $this->container->get('\WPBlogMailer\Pro\Services\TrackingService');
            } catch (\Exception $e) {
                $this->tracking_service = null;
            }
        }

        $this->subscriber_service = $this->container->get(SubscriberService::class);

        // Analytics service - available in Starter and Pro
        $this->analytics_service = null;
        if (wpbm_is_starter() || wpbm_is_pro()) {
            try {
                $this->analytics_service = $this->container->get('\WPBlogMailer\Common\Analytics\AnalyticsInterface');
            } catch (\Exception $e) {
                $this->analytics_service = null;
            }
        }
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks() {
        // Admin area hooks
        if (is_admin()) {
            add_action('admin_menu', array($this->menu_manager, 'register_menus'), 5);
            add_action('admin_enqueue_scripts', array($this->asset_manager, 'enqueue_admin_assets'));
            add_action('admin_init', array($this, 'maybe_migrate_subscriber_keys'));
            add_action('admin_init', array($this, 'check_database_updates'));
        }

        // Register shortcodes
        add_action('init', array($this, 'register_shortcodes'));

        // Subscriber controller hooks (AJAX, admin_post, admin_notices)
        if ($this->subscribers_controller) {
            $this->subscribers_controller->init_hooks();

            // Import/Export hooks (Starter+)
            if (wpbm_is_starter()) {
                add_action('admin_post_wpbm_import_subscribers', array($this->subscribers_controller, 'handle_import_subscribers'));
                add_action('admin_post_wpbm_export_subscribers', array($this->subscribers_controller, 'handle_export_subscribers'));
            }
        }

        // Public Tracking Hooks (Pro only)
        if ($this->tracking_service) {
            add_action('admin_post_nopriv_wpbm_track_open', [$this->tracking_service, 'handle_open_tracking']);
            add_action('admin_post_nopriv_wpbm_track_click', [$this->tracking_service, 'handle_click_tracking']);
            add_action('admin_post_wpbm_track_open', [$this->tracking_service, 'handle_open_tracking']);
            add_action('admin_post_wpbm_track_click', [$this->tracking_service, 'handle_click_tracking']);
        }

        // Newsletter sending hooks
        add_action('admin_post_wpbm_send_newsletter_now', array($this->newsletter_handler, 'handle_send_newsletter_now'));
        add_action('wpbm_send_newsletter', array($this->newsletter_handler, 'handle_newsletter_cron'));

        // Email queue processor (Starter+ feature)
        if (wpbm_is_starter()) {
            add_action('wpbm_process_email_queue', array($this->newsletter_handler, 'handle_queue_processing_cron'));
        }

        // Custom Email sender (Starter+)
        if (wpbm_is_starter()) {
            add_action('admin_post_wpbm_send_custom_email', array($this->custom_email_handler, 'handle_send_custom_email'));
        }

        // Template preview AJAX handler
        add_action('wp_ajax_wpbm_preview_template', array($this->template_handler, 'handle_template_preview'));

        // Send test email AJAX handler
        add_action('wp_ajax_wpbm_send_test_email', array($this, 'handle_send_test_email'));

        // Dismiss SMTP notice AJAX handler
        add_action('wp_ajax_wpbm_dismiss_smtp_notice', array($this, 'handle_dismiss_smtp_notice'));

        // Tag management AJAX handlers (Pro only)
        if (wpbm_is_pro()) {
            add_action('wp_ajax_wpbm_get_tag', array($this->tag_handler, 'handle_get_tag'));
            add_action('wp_ajax_wpbm_save_tag', array($this->tag_handler, 'handle_save_tag'));
            add_action('wp_ajax_wpbm_delete_tag', array($this->tag_handler, 'handle_delete_tag'));
        }

        // Pro feature cron jobs
        if (wpbm_is_pro()) {
            add_action('wpbm_send_weekly_report', array($this->cron_handler, 'handle_weekly_report_send'));
            add_action('wpbm_update_engagement_scores', array($this->cron_handler, 'handle_update_engagement_scores'));
            add_action('wpbm_check_ab_tests', array($this->cron_handler, 'handle_check_ab_tests'));
            add_action('wpbm_cleanup_exports', array($this->cron_handler, 'handle_cleanup_exports'));
        }
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        if ($this->form_controller) {
            add_shortcode('wpbm_subscribe_form', array($this->form_controller, 'render_form'));
        }
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return self::VERSION;
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public function get_plugin_path() {
        return $this->plugin_path;
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function get_plugin_url() {
        return $this->plugin_url;
    }

    /**
     * Handle send test email AJAX request
     */
    public function handle_send_test_email() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpbm_send_test_email')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'blog-mailer')));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to send test emails.', 'blog-mailer')));
            return;
        }

        // Get test email address
        $test_email = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';

        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error(array('message' => esc_html__('Invalid email address.', 'blog-mailer')));
            return;
        }

        try {
            // Get newsletter service
            $newsletter_service = $this->container->get(\WPBlogMailer\Common\Services\NewsletterService::class);

            // Get recent posts
            $settings = get_option('wpbm_settings', array());
            $posts_per_email = isset($settings['posts_per_email']) ? absint($settings['posts_per_email']) : 5;
            $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post');

            $args = array(
                'posts_per_page' => $posts_per_email,
                'post_type' => $post_types,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC'
            );

            $posts = get_posts($args);

            if (empty($posts)) {
                wp_send_json_error(array('message' => esc_html__('No posts available to send. Please publish some posts first.', 'blog-mailer')));
                return;
            }

            // Create a test subscriber object
            $test_subscriber = (object) array(
                'id' => 0,
                'email' => $test_email,
                'first_name' => 'Test',
                'last_name' => 'User',
                'status' => 'confirmed',
                'unsubscribe_key' => 'test_key_' . wp_generate_password(20, false)
            );

            // Get email service
            $email_service_factory = $this->container->get(\WPBlogMailer\Core\EmailServiceFactory::class);
            $email_service = $email_service_factory->create();

            // Get template service (use BasicTemplateService for newsletter templates)
            $template_service = $this->container->get(\WPBlogMailer\Free\Services\BasicTemplateService::class);

            // Get settings
            $subject_template = isset($settings['subject_template']) ? $settings['subject_template'] : '[{site_name}] New Posts: {date}';
            $heading = isset($settings['newsletter_heading']) ? $settings['newsletter_heading'] : esc_html__('Latest Posts from {site_name}', 'blog-mailer');

            // Replace placeholders in heading
            $heading = str_replace(
                array('{site_name}', '{date}'),
                array(get_bloginfo('name'), date_i18n(get_option('date_format'))),
                $heading
            );

            // Prepare subject
            $subject = str_replace(
                array('{site_name}', '{date}', '{post_count}'),
                array(get_bloginfo('name'), date_i18n(get_option('date_format')), count($posts)),
                $subject_template
            );

            // Render email content using BasicTemplateService
            $email_content = $template_service->render(array(
                'posts' => $posts,
                'subscriber' => $test_subscriber,
                'heading' => $heading
            ));

            // Send email (pass empty headers array and tracking data)
            $result = $email_service->send(
                $test_email,
                $subject,
                $email_content,
                [], // headers
                ['template' => 'basic', 'campaign_type' => 'test'] // tracking_data
            );

            if ($result) {
        /* translators: %s: name of the missing capability */
                wp_send_json_success(array('message' => sprintf(esc_html__('Test email sent successfully to %s!', 'blog-mailer'), $test_email)));
            } else {
                wp_send_json_error(array('message' => esc_html__('Failed to send test email. Please check your email settings and try again.', 'blog-mailer')));
            }

        } catch (\Exception $e) {
            /* translators: %s: error message from the exception */
            wp_send_json_error(array('message' => sprintf(esc_html__('Error: %s', 'blog-mailer'), $e->getMessage())));
        }
    }

    /**
     * Handle AJAX request to dismiss SMTP notice
     */
    public function handle_dismiss_smtp_notice() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpbm_dismiss_smtp_notice')) {
            wp_send_json_error(array('message' => esc_html__('Invalid nonce', 'blog-mailer')));
            return;
        }

        // Save user meta to remember dismissal
        update_user_meta(get_current_user_id(), 'wpbm_smtp_notice_dismissed', true);

        wp_send_json_success();
    }

    /**
     * Get the current license tier slug (dynamically checks Freemius)
     *
     * @return string (free|starter|pro)
     */
    public function get_license_tier_slug() {
        if (wpbm_is_pro()) return 'pro';
        if (wpbm_is_starter()) return 'starter';
        return 'free';
    }

    /**
     * Check if a specific tier (or higher) is active
     *
     * @param string $tier The tier to check (free|starter|pro)
     * @return bool
     */
    public function is_tier($tier) {
        switch($tier) {
            case 'pro':
                return wpbm_is_pro();
            case 'starter':
                return wpbm_is_starter();
            case 'free':
            default:
                return true; // Free tier is always accessible
        }
    }

    /**
     * Get service from container
     *
     * @param string $service_class Service class name
     * @return mixed Service instance
     */
    public function get_service($service_class) {
        return $this->container->get($service_class);
    }

    /**
     * Get subscriber service
     *
     * @return \WPBlogMailer\Common\Services\SubscriberService|null
     */
    public function get_subscriber_service() {
        return $this->subscriber_service;
    }

    /**
     * Get analytics service
     *
     * @return \WPBlogMailer\Common\Analytics\AnalyticsInterface|null
     */
    public function get_analytics_service() {
        return $this->analytics_service;
    }

    /**
     * One-time migration to generate keys for existing subscribers
     * This runs once after updating to version with double opt-in support
     */
    public function maybe_migrate_subscriber_keys() {
        // Check if migration has already run
        $migration_done = get_option('wpbm_subscriber_keys_migrated', false);

        if ($migration_done) {
            return;
        }

        // Run the migration
        if ($this->subscriber_service) {
            $updated = $this->subscriber_service->generate_missing_keys();

            // Mark migration as complete
            update_option('wpbm_subscriber_keys_migrated', true);

            // Log the result
            if ($updated > 0) {
            }
        }
    }

    /**
     * Check for database schema updates
     */
    public function check_database_updates() {
        if (!class_exists('\WPBlogMailer\Common\Database\Schema')) {
            return;
        }

        $schema = new \WPBlogMailer\Common\Database\Schema();
        $schema->check_updates();
    }

} // End Class
