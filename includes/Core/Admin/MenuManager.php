<?php
/**
 * Admin Menu Manager
 * Handles WordPress admin menu registration for the plugin
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Admin;

use WPBlogMailer\Free\Controllers\SubscribersController;
use WPBlogMailer\Core\Admin\PageRenderer;

defined('ABSPATH') || exit;

/**
 * MenuManager Class
 *
 * Responsible for:
 * - Registering admin menu items
 * - Handling tier-based menu visibility
 * - Delegating page rendering to PageRenderer
 */
class MenuManager {

    /**
     * @var PageRenderer
     */
    private $page_renderer;

    /**
     * @var SubscribersController
     */
    private $subscribers_controller;

    /**
     * Constructor
     *
     * @param PageRenderer $page_renderer
     * @param SubscribersController $subscribers_controller
     */
    public function __construct(PageRenderer $page_renderer, SubscribersController $subscribers_controller) {
        $this->page_renderer = $page_renderer;
        $this->subscribers_controller = $subscribers_controller;
    }

    /**
     * Register admin menu items dynamically based on license tier
     *
     * @return void
     */
    public function register_menus() {
        // Main menu
        add_menu_page(
            esc_html__('CN Blog Mailer', 'blog-mailer'),
            esc_html__('CN Blog Mailer', 'blog-mailer'),
            'manage_options',
            'wpbm-newsletter',
            array($this->page_renderer, 'render_dashboard'),
            'dashicons-email-alt',
            30
        );

        // Dashboard
        add_submenu_page(
            'wpbm-newsletter',
            esc_html__('Dashboard', 'blog-mailer'),
            esc_html__('Dashboard', 'blog-mailer'),
            'manage_options',
            'wpbm-newsletter',
            array($this->page_renderer, 'render_dashboard')
        );

        // Subscribers (Always available)
        add_submenu_page(
            'wpbm-newsletter',
            esc_html__('Subscribers', 'blog-mailer'),
            esc_html__('Subscribers', 'blog-mailer'),
            'manage_options',
            'wpbm-subscribers',
            array($this->subscribers_controller, 'render_page')
        );

        // Subscriber Analytics Detail (Pro - hidden from menu, accessed via link)
        if (wpbm_is_pro()) {
            add_submenu_page(
                null, // Hidden from menu
                esc_html__('Subscriber Analytics', 'blog-mailer'),
                esc_html__('Subscriber Analytics', 'blog-mailer'),
                'manage_options',
                'wpbm-subscriber-analytics',
                array($this->page_renderer, 'render_subscriber_analytics_page')
            );
        }

        // Send Log (Always available)
        add_submenu_page(
            'wpbm-newsletter',
            esc_html__('Send Log', 'blog-mailer'),
            esc_html__('Send Log', 'blog-mailer'),
            'manage_options',
            'wpbm-send-log',
            array($this->page_renderer, 'render_send_log_page')
        );

        // Cron Status (Always available)
        add_submenu_page(
            'wpbm-newsletter',
            esc_html__('Cron Status', 'blog-mailer'),
            esc_html__('Cron Status', 'blog-mailer'),
            'manage_options',
            'wpbm-cron-status',
            array($this->page_renderer, 'render_cron_status_page')
        );

        // Analytics (Starter+)
        if (wpbm_is_starter()) {
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Analytics', 'blog-mailer'),
                esc_html__('Analytics', 'blog-mailer'),
                'manage_options',
                'wpbm-analytics',
                array($this->page_renderer, 'render_analytics_page')
            );
        }

        // Import / Export - Removed from free version
        // This functionality is available in Pro version only
        // Users can access import/export through upgrade path
        /*
        if (wpbm_is_starter()) {
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Import / Export', 'blog-mailer'),
                esc_html__('Import / Export', 'blog-mailer'),
                'manage_options',
                'wpbm-import-export',
                array($this->page_renderer, 'render_import_export_page')
            );
        }
        */

        // Custom Email (Starter+)
        if (wpbm_is_starter()) {
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Custom Email', 'blog-mailer'),
                esc_html__('Custom Email', 'blog-mailer'),
                'manage_options',
                'wpbm-custom-email',
                array($this->page_renderer, 'render_custom_email_page')
            );
        }

        // Pro Features
        if (wpbm_is_pro()) {
            // Tags & Segments (Pro only)
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Tags & Segments', 'blog-mailer'),
                esc_html__('Tags & Segments', 'blog-mailer'),
                'manage_options',
                'wpbm-tags',
                array($this->page_renderer, 'render_tags_page')
            );

            // Custom Templates (Pro only)
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Templates', 'blog-mailer'),
                esc_html__('Templates', 'blog-mailer'),
                'manage_options',
                'wpbm-custom-templates',
                array($this->page_renderer, 'render_custom_templates_page')
            );

            // Weekly Reports (Pro only)
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Weekly Reports', 'blog-mailer'),
                esc_html__('Weekly Reports', 'blog-mailer'),
                'manage_options',
                'wpbm-weekly-reports',
                array($this->page_renderer, 'render_weekly_reports_page')
            );

            // A/B Testing (Pro only)
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('A/B Testing', 'blog-mailer'),
                esc_html__('A/B Testing', 'blog-mailer'),
                'manage_options',
                'wpbm-ab-testing',
                array($this->page_renderer, 'render_ab_testing_page')
            );

            // Enhanced Analytics (Pro only)
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Enhanced Analytics', 'blog-mailer'),
                esc_html__('Enhanced Analytics', 'blog-mailer'),
                'manage_options',
                'wpbm-analytics-enhanced',
                array($this->page_renderer, 'render_analytics_enhanced_page')
            );
        }

        // Settings (Always available)
        add_submenu_page(
            'wpbm-newsletter',
            esc_html__('Settings', 'blog-mailer'),
            esc_html__('Settings', 'blog-mailer'),
            'manage_options',
            'wpbm-settings',
            array($this->page_renderer, 'render_settings')
        );

        // Getting Started / Help (Always available - Last menu item)
        add_submenu_page(
            'wpbm-newsletter',
            esc_html__('Getting Started', 'blog-mailer'),
            esc_html__('Getting Started', 'blog-mailer'),
            'manage_options',
            'wpbm-getting-started',
            array($this->page_renderer, 'render_getting_started_page')
        );

        // License Status - Hidden in free version (for Pro debugging only)
        // Removed for WordPress.org submission to avoid confusion for free users
        // This page will be available in Pro version for license management
        /*
        add_submenu_page(
            'wpbm-newsletter',
            esc_html__('License Status', 'blog-mailer'),
            esc_html__('License Status', 'blog-mailer'),
            'manage_options',
            'wpbm-license-status',
            array($this->page_renderer, 'render_license_status_page')
        );
        */

        // Account (Use Freemius slug if defined) - Hide in free version
        if (!wpbm_is_free_plan()) {
            add_submenu_page(
                'wpbm-newsletter',
                esc_html__('Account', 'blog-mailer'),
                esc_html__('Account', 'blog-mailer'),
                'manage_options',
                'wpblog-mailer-account',
                array($this->page_renderer, 'render_account')
            );
        }
    }
}
