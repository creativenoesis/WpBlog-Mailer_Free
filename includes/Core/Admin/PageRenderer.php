<?php
/**
 * Admin Page Renderer
 * Handles rendering of all admin pages
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Admin;

use WPBlogMailer\Common\Analytics\AnalyticsInterface;

defined('ABSPATH') || exit;

/**
 * PageRenderer Class
 *
 * Responsible for:
 * - Rendering all admin page views
 * - Passing data to view templates
 * - Handling tier-based page access
 */
class PageRenderer {

    /**
     * @var string Plugin path
     */
    private $plugin_path;

    /**
     * @var AnalyticsInterface
     */
    private $analytics_service;

    /**
     * Constructor
     *
     * @param string $plugin_path
     * @param AnalyticsInterface|null $analytics_service
     */
    public function __construct($plugin_path, $analytics_service = null) {
        $this->plugin_path = $plugin_path;
        $this->analytics_service = $analytics_service;
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $view_file = $this->plugin_path . 'includes/Free/Views/dashboard.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Dashboard view file not found.</p></div>';
        }
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        $view_file = $this->plugin_path . 'includes/Free/Views/settings.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Settings view file not found.</p></div>';
        }
    }

    /**
     * Render getting started / help page
     */
    public function render_getting_started_page() {
        $view_file = $this->plugin_path . 'includes/Free/Views/getting-started.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Getting Started view file not found.</p></div>';
        }
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        if (null === $this->analytics_service) {
            echo '<div class="wrap"><h1>Error</h1><p>Analytics service failed to load.</p></div>';
            return;
        }

        $stats = $this->analytics_service->get_dashboard_stats(30);
        $view_file = $this->plugin_path . 'includes/Starter/Views/analytics.php';

        // Use Pro advanced analytics if available
        if (wpbm_is_pro() && file_exists($this->plugin_path . 'includes/Pro/Views/advanced-analytics.php')) {
            $view_file = $this->plugin_path . 'includes/Pro/Views/advanced-analytics.php';
        }

        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Analytics view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render import/export page
     */
    public function render_import_export_page() {
        // Double-check tier access
        if (!wpbm_is_starter()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Starter/Views/import-export.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Import/Export view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render custom email page
     */
    public function render_custom_email_page() {
        // Double-check tier access
        if (!wpbm_is_starter()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Starter/Views/custom-email.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Custom Email view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render custom templates page
     */
    public function render_custom_templates_page() {
        // Double-check tier access
        if (!wpbm_is_pro()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Pro/Views/custom-templates.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Custom Templates view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render account page
     */
    public function render_account() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Account & License', 'blog-mailer') . '</h1>';

        if (!function_exists('wpbm_fs')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Freemius SDK is not available.', 'blog-mailer') . '</p></div>';
            echo '</div>';
            return;
        }

        $fs = wpbm_fs();

        // Check if user is anonymous (not opted in)
        if ($fs->is_anonymous()) {
            // Show custom opt-in page instead of crashing
            $this->render_account_opt_in();
        } else {
            // User is registered, show Freemius account page
            try {
                $fs->_account_page_load();
                $fs->_account_page_render();
            } catch (\Exception $e) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('Error loading account page: ', 'blog-mailer') . esc_html($e->getMessage());
                echo '</p></div>';
            }
        }

        echo '</div>';
    }

    /**
     * Render opt-in page for anonymous users
     */
    private function render_account_opt_in() {
        $fs = wpbm_fs();
        $activation_url = $fs->get_activation_url();
        ?>
        <div class="wpbm-account-opt-in" style="margin: 20px 0;">
            <div style="background: #fff; padding: 40px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <span class="dashicons dashicons-admin-network" style="font-size: 80px; color: #2271b1; margin-bottom: 20px;"></span>

                <h2 style="font-size: 24px; margin: 20px 0;"><?php esc_html_e('Connect Your Account', 'blog-mailer'); ?></h2>

                <p style="font-size: 16px; color: #646970; max-width: 600px; margin: 20px auto;">
                    <?php esc_html_e('To manage your license and access premium features, you need to connect your account with Freemius.', 'blog-mailer'); ?>
                </p>

                <p style="font-size: 14px; color: #646970; max-width: 600px; margin: 20px auto;">
                    <?php esc_html_e('This is a one-time setup that allows you to:', 'blog-mailer'); ?>
                </p>

                <ul style="text-align: left; max-width: 500px; margin: 20px auto; line-height: 2;">
                    <li>✅ <?php esc_html_e('Activate your Pro or Starter license', 'blog-mailer'); ?></li>
                    <li>✅ <?php esc_html_e('Manage your license across sites', 'blog-mailer'); ?></li>
                    <li>✅ <?php esc_html_e('Access premium features', 'blog-mailer'); ?></li>
                    <li>✅ <?php esc_html_e('Get automatic updates', 'blog-mailer'); ?></li>
                    <li>✅ <?php esc_html_e('Contact support', 'blog-mailer'); ?></li>
                </ul>

                <div style="margin: 40px 0;">
                    <a href="<?php echo esc_url($activation_url); ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Connect Account Now', 'blog-mailer'); ?>
                    </a>
                </div>

                <p style="font-size: 13px; color: #646970;">
                    <?php esc_html_e('By connecting, you agree to share basic plugin usage data with Freemius. No sensitive information is collected.', 'blog-mailer'); ?>
                </p>
            </div>

            <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 20px; margin-top: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Why Do I Need This?', 'blog-mailer'); ?></h3>
                <p>
                    <?php esc_html_e('Freemius is our licensing partner that handles secure license activation and management. Without connecting your account, you cannot activate premium licenses or access paid features.', 'blog-mailer'); ?>
                </p>
                <p>
                    <?php esc_html_e('If you only want to use the Free version, you can still connect - you won\'t be charged anything.', 'blog-mailer'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render send log page
     */
    public function render_send_log_page() {
        $view_file = $this->plugin_path . 'includes/Free/Views/send-log.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Send Log view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render cron status page
     */
    public function render_cron_status_page() {
        $view_file = $this->plugin_path . 'includes/Free/Views/cron-status.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Cron Status view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render license status page
     */
    public function render_license_status_page() {
        $view_file = $this->plugin_path . 'includes/Free/Views/license-status.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>License Status view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render subscriber analytics page
     */
    public function render_subscriber_analytics_page() {
        // Double-check tier access
        if (!wpbm_is_pro()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Pro/Views/subscriber-analytics.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Subscriber Analytics view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render Weekly Reports page (Pro only)
     */
    public function render_weekly_reports_page() {
        // Double-check tier access
        if (!wpbm_is_pro()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Pro/Views/weekly-reports.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Weekly Reports view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render A/B Testing page (Pro feature)
     */
    public function render_ab_testing_page() {
        // Double-check tier access
        if (!wpbm_is_pro()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Pro/Views/ab-testing.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>A/B Testing view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render Enhanced Analytics page (Pro feature)
     */
    public function render_analytics_enhanced_page() {
        // Double-check tier access
        if (!wpbm_is_pro()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Pro/Views/analytics-enhanced.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Enhanced Analytics view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }

    /**
     * Render Tags & Segments page (Pro feature)
     */
    public function render_tags_page() {
        // Double-check tier access
        if (!wpbm_is_pro()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blog-mailer'));
        }

        $view_file = $this->plugin_path . 'includes/Pro/Views/tags.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Tags & Segments view file not found.</p><code>' . esc_html($view_file) . '</code></div>';
        }
    }
}
