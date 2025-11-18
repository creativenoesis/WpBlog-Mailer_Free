<?php
/**
 * Checkout URL Generator
 *
 * Generates Freemius checkout URLs for pricing pages and upgrade buttons.
 * Can be used via shortcode or programmatically.
 *
 * @package WP_Blog_Mailer
 * @version 2.0.0
 */

namespace WP_Blog_Mailer\Common\Utilities;

if (!defined('ABSPATH')) exit;

class CheckoutUrlGenerator {

    /**
     * Plan ID mapping
     *
     * @var array
     */
    private static $plan_ids = array(
        'starter' => 35576,  // Starter Plan ID from Freemius Dashboard
        'pro'     => 35577,  // Pro Plan ID from Freemius Dashboard
    );

    /**
     * Initialize the generator
     */
    public static function init() {
        // Register shortcode
        add_shortcode('wpbm_checkout_url', array(__CLASS__, 'shortcode'));

        // Register REST API endpoint for external website use
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));

        // Register AJAX endpoint for admin use
        add_action('wp_ajax_wpbm_get_checkout_url', array(__CLASS__, 'ajax_get_checkout_url'));
    }

    /**
     * Get checkout URL for a specific plan
     *
     * @param string $plan Plan name: 'starter' or 'pro'
     * @param string $period Billing period: 'monthly' or 'annual'
     * @param array $extra Extra parameters to pass to checkout
     * @return string|false Checkout URL or false if Freemius not available
     */
    public static function get_checkout_url($plan = 'starter', $period = 'annual', $extra = array()) {
        // Check if Freemius is available
        if (!function_exists('wpbm_fs')) {
            return false;
        }

        $fs = wpbm_fs();

        // Map period to Freemius constants
        $billing_cycle = ($period === 'monthly') ? WP_FS__PERIOD_MONTHLY : WP_FS__PERIOD_ANNUALLY;

        // Get plan ID if set
        $plan_id = self::get_plan_id($plan);

        // Build extra params
        if ($plan_id) {
            $extra['plan_id'] = $plan_id;
        }

        // Generate checkout URL
        $url = $fs->checkout_url($billing_cycle, false, $extra);

        /**
         * Filter the checkout URL
         *
         * @param string $url The generated checkout URL
         * @param string $plan The plan name
         * @param string $period The billing period
         */
        return apply_filters('wpbm_checkout_url', $url, $plan, $period);
    }

    /**
     * Get trial checkout URL
     *
     * @param string $plan Plan name: 'starter' or 'pro'
     * @return string|false
     */
    public static function get_trial_url($plan = 'pro') {
        if (!function_exists('wpbm_fs')) {
            return false;
        }

        $fs = wpbm_fs();
        $plan_id = self::get_plan_id($plan);

        $extra = array();
        if ($plan_id) {
            $extra['plan_id'] = $plan_id;
        }

        return $fs->checkout_url(WP_FS__PERIOD_ANNUALLY, true, $extra);
    }

    /**
     * Get plan ID from config
     *
     * @param string $plan Plan name
     * @return int|null
     */
    private static function get_plan_id($plan) {
        // Allow override via WordPress options
        $option_key = 'wpbm_freemius_' . $plan . '_plan_id';
        $plan_id = get_option($option_key);

        if ($plan_id) {
            return (int) $plan_id;
        }

        // Fallback to hardcoded values
        return isset(self::$plan_ids[$plan]) ? self::$plan_ids[$plan] : null;
    }

    /**
     * Shortcode: [wpbm_checkout_url plan="starter" period="annual" text="Buy Now"]
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function shortcode($atts) {
        $atts = shortcode_atts(array(
            'plan'    => 'starter',
            'period'  => 'annual',
            'trial'   => 'false',
            'text'    => 'Upgrade Now',
            'class'   => 'button button-primary',
            'target'  => '_blank',
        ), $atts);

        // Get URL
        if ($atts['trial'] === 'true') {
            $url = self::get_trial_url($atts['plan']);
        } else {
            $url = self::get_checkout_url($atts['plan'], $atts['period']);
        }

        // Fallback if Freemius not available
        if (!$url) {
            $url = 'https://creativenoesis.com/cn-blog-mailer/';
        }

        // Build output
        return sprintf(
            '<a href="%s" class="%s" target="%s">%s</a>',
            esc_url($url),
            esc_attr($atts['class']),
            esc_attr($atts['target']),
            esc_html($atts['text'])
        );
    }

    /**
     * Register REST API routes for external website use
     */
    public static function register_rest_routes() {
        register_rest_route('wpbm/v1', '/checkout-url', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'rest_get_checkout_url'),
            'permission_callback' => '__return_true', // Public endpoint
            'args' => array(
                'plan' => array(
                    'required' => false,
                    'default'  => 'starter',
                    'enum'     => array('starter', 'pro'),
                ),
                'period' => array(
                    'required' => false,
                    'default'  => 'annual',
                    'enum'     => array('monthly', 'annual'),
                ),
                'trial' => array(
                    'required' => false,
                    'default'  => false,
                    'type'     => 'boolean',
                ),
            ),
        ));
    }

    /**
     * REST API callback
     *
     * Usage: https://yoursite.com/wp-json/wpbm/v1/checkout-url?plan=pro&period=annual
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function rest_get_checkout_url($request) {
        $plan   = $request->get_param('plan');
        $period = $request->get_param('period');
        $trial  = $request->get_param('trial');

        if ($trial) {
            $url = self::get_trial_url($plan);
        } else {
            $url = self::get_checkout_url($plan, $period);
        }

        if (!$url) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Freemius not available',
                'url'     => 'https://creativenoesis.com/cn-blog-mailer/',
            ), 200);
        }

        return new \WP_REST_Response(array(
            'success' => true,
            'url'     => $url,
            'plan'    => $plan,
            'period'  => $period,
            'trial'   => $trial,
        ), 200);
    }

    /**
     * AJAX callback for admin use
     */
    public static function ajax_get_checkout_url() {
        // Verify nonce for CSRF protection
        check_ajax_referer('wpbm_checkout_url', 'nonce');

        // Verify user capability (admin only)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
            return;
        }

        // Use POST instead of GET for AJAX
        $plan   = isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : 'starter';
        $period = isset($_POST['period']) ? sanitize_text_field(wp_unslash($_POST['period'])) : 'annual';
        $trial  = isset($_POST['trial']) && $_POST['trial'] === 'true';

        // Validate plan
        if (!in_array($plan, ['starter', 'pro'], true)) {
            wp_send_json_error(['message' => 'Invalid plan'], 400);
            return;
        }

        // Validate period
        if (!in_array($period, ['monthly', 'annual'], true)) {
            wp_send_json_error(['message' => 'Invalid period'], 400);
            return;
        }

        if ($trial) {
            $url = self::get_trial_url($plan);
        } else {
            $url = self::get_checkout_url($plan, $period);
        }

        wp_send_json_success(array(
            'url'    => $url,
            'plan'   => $plan,
            'period' => $period,
        ));
    }

    /**
     * Get all checkout URLs at once (useful for pricing pages)
     *
     * @return array
     */
    public static function get_all_urls() {
        return array(
            'starter' => array(
                'monthly' => self::get_checkout_url('starter', 'monthly'),
                'annual'  => self::get_checkout_url('starter', 'annual'),
            ),
            'pro' => array(
                'monthly' => self::get_checkout_url('pro', 'monthly'),
                'annual'  => self::get_checkout_url('pro', 'annual'),
                'trial'   => self::get_trial_url('pro'),
            ),
        );
    }

    /**
     * Output JavaScript snippet for easy integration on external website
     *
     * Usage in external website:
     * <script src="https://yourwordpresssite.com/?wpbm_checkout_js=1"></script>
     * <a href="#" data-wpbm-checkout="pro" data-wpbm-period="annual">Buy Pro</a>
     */
    public static function output_js_snippet() {
        // Check if requested
        if (!isset($_GET['wpbm_checkout_js'])) {
            return;
        }

        header('Content-Type: application/javascript');

        $rest_url = rest_url('wpbm/v1/checkout-url');
        ?>
(function() {
    'use strict';

    // Find all checkout buttons
    var buttons = document.querySelectorAll('[data-wpbm-checkout]');

    buttons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            var plan = this.getAttribute('data-wpbm-checkout') || 'starter';
            var period = this.getAttribute('data-wpbm-period') || 'annual';
            var trial = this.getAttribute('data-wpbm-trial') === 'true';

            // Fetch checkout URL (properly escaped to prevent XSS)
            var url = <?php echo json_encode($rest_url); ?> + '?plan=' + plan + '&period=' + period + '&trial=' + trial;

            fetch(url)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.url) {
                        window.open(data.url, '_blank');
                    }
                })
                .catch(function(error) {
                    console.error('WPBM Checkout Error:', error);
                    // Fallback
                    window.open('https://creativenoesis.com/cn-blog-mailer/', '_blank');
                });
        });
    });
})();
        <?php
        exit;
    }
}

// Initialize
add_action('init', array('WP_Blog_Mailer\Common\Utilities\CheckoutUrlGenerator', 'init'));
add_action('template_redirect', array('WP_Blog_Mailer\Common\Utilities\CheckoutUrlGenerator', 'output_js_snippet'));
