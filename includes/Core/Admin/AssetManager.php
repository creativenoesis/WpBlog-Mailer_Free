<?php
/**
 * Asset Manager
 * Handles enqueuing of CSS and JavaScript files for admin pages
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Admin;

defined('ABSPATH') || exit;

/**
 * AssetManager Class
 *
 * Responsible for:
 * - Enqueuing admin CSS/JS based on current page
 * - Localizing scripts with necessary data
 * - Loading WordPress core assets (color picker, etc.)
 */
class AssetManager {

    /**
     * Plugin version
     * @var string
     */
    private $version;

    /**
     * Plugin URL
     * @var string
     */
    private $plugin_url;

    /**
     * Constructor
     *
     * @param string $version Plugin version for cache busting
     * @param string $plugin_url Base plugin URL
     */
    public function __construct($version, $plugin_url) {
        $this->version = $version;
        $this->plugin_url = $plugin_url;
    }

    /**
     * Enqueue admin assets based on current page
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $base_screen_id = 'toplevel_page_wpbm-newsletter';
        $is_plugin_page = (
            strpos($screen->id, 'wpbm-') !== false ||
            strpos($screen->id, $base_screen_id) !== false ||
            strpos($screen->id, 'wpblog-mailer') !== false
        );

        if (!$is_plugin_page) {
            return;
        }

        // Common Assets - Load on all plugin pages
        wp_enqueue_style(
            'wpbm-admin-common',
            $this->plugin_url . 'assets/css/admin/common.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'wpbm-admin-common',
            $this->plugin_url . 'assets/js/admin/common.js',
            ['jquery'],
            $this->version,
            true
        );

        // Localize common script with base data
        wp_localize_script('wpbm-admin-common', 'wpbm', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbm_admin_nonce'),
        ]);

        // Page-specific assets
        $this->enqueue_subscribers_assets($hook);
        $this->enqueue_analytics_assets($hook);
        $this->enqueue_custom_email_assets($hook);
        $this->enqueue_import_export_assets($hook);
        $this->enqueue_settings_assets($hook);
        $this->enqueue_templates_assets($hook);
    }

    /**
     * Enqueue subscribers page assets
     *
     * @param string $hook
     */
    private function enqueue_subscribers_assets($hook) {
        if (strpos($hook, 'wpbm-subscribers') === false) {
            return;
        }

        wp_enqueue_style(
            'wpbm-admin-subscribers',
            $this->plugin_url . 'assets/css/admin/subscribers.css',
            ['wpbm-admin-common'],
            $this->version
        );

        wp_enqueue_script(
            'wpbm-admin-subscribers',
            $this->plugin_url . 'assets/js/admin/subscribers.js',
            ['jquery', 'wpbm-admin-common'],
            $this->version,
            true
        );

        wp_localize_script('wpbm-admin-subscribers', 'wpbm_subscribers', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbm_subscriber_nonce'),
            'confirm_delete' => esc_html__('Are you sure you want to delete this subscriber?', 'blog-mailer'),
            'confirm_bulk_delete' => esc_html__('Are you sure you want to delete the selected subscribers?', 'blog-mailer'),
        ]);
    }

    /**
     * Enqueue analytics page assets
     *
     * @param string $hook
     */
    private function enqueue_analytics_assets($hook) {
        if (strpos($hook, 'wpbm-analytics') === false || !wpbm_is_starter()) {
            return;
        }

        wp_enqueue_style(
            'wpbm-admin-analytics',
            $this->plugin_url . 'assets/css/admin/analytics.css',
            ['wpbm-admin-common'],
            $this->version
        );

        wp_enqueue_script(
            'wpbm-admin-analytics',
            $this->plugin_url . 'assets/js/admin/analytics.js',
            ['jquery', 'wpbm-admin-common'],
            $this->version,
            true
        );

        wp_localize_script('wpbm-admin-analytics', 'wpbm_analytics', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbm_analytics_nonce'),
        ]);
    }

    /**
     * Enqueue custom email page assets
     *
     * @param string $hook
     */
    private function enqueue_custom_email_assets($hook) {
        if (strpos($hook, 'wpbm-custom-email') === false || !wpbm_is_starter()) {
            return;
        }

        // Enqueue WordPress editor for custom email content
        wp_enqueue_editor();

        wp_enqueue_style(
            'wpbm-admin-custom-email',
            $this->plugin_url . 'assets/css/admin/custom-email.css',
            ['wpbm-admin-common'],
            $this->version
        );

        wp_enqueue_script(
            'wpbm-admin-custom-email',
            $this->plugin_url . 'assets/js/admin/custom-email.js',
            ['jquery', 'wpbm-admin-common'],
            $this->version,
            true
        );

        wp_localize_script('wpbm-admin-custom-email', 'wpbm_custom_email', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbm_custom_email_nonce'),
        ]);
    }

    /**
     * Enqueue import/export page assets
     *
     * @param string $hook
     */
    private function enqueue_import_export_assets($hook) {
        if (strpos($hook, 'wpbm-import-export') === false || !wpbm_is_starter()) {
            return;
        }

        wp_enqueue_style(
            'wpbm-admin-import-export',
            $this->plugin_url . 'assets/css/admin/import-export.css',
            ['wpbm-admin-common'],
            $this->version
        );

        wp_enqueue_script(
            'wpbm-admin-import-export',
            $this->plugin_url . 'assets/js/admin/import-export.js',
            ['jquery', 'wpbm-admin-common'],
            $this->version,
            true
        );

        wp_localize_script('wpbm-admin-import-export', 'wpbm_import_export', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbm_import_export_nonce'),
        ]);
    }

    /**
     * Enqueue settings page assets
     *
     * @param string $hook
     */
    private function enqueue_settings_assets($hook) {
        if (strpos($hook, 'wpbm-settings') === false) {
            return;
        }

        // WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Settings page uses inline styles and scripts in the view file
        // No separate CSS/JS files needed
    }

    /**
     * Enqueue templates page assets
     *
     * @param string $hook
     */
    private function enqueue_templates_assets($hook) {
        if (strpos($hook, 'wpbm-custom-templates') === false || !wpbm_is_pro()) {
            return;
        }

        // WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_style(
            'wpbm-admin-templates',
            $this->plugin_url . 'assets/css/admin/templates.css',
            ['wpbm-admin-common', 'wp-color-picker'],
            $this->version
        );

        wp_enqueue_script(
            'wpbm-admin-templates',
            $this->plugin_url . 'assets/js/admin/templates.js',
            ['jquery', 'wpbm-admin-common', 'wp-color-picker'],
            $this->version,
            true
        );

        wp_localize_script('wpbm-admin-templates', 'wpbm_templates', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbm_template_nonce'),
        ]);
    }
}
