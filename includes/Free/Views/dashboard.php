<?php
/* phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are passed from controller */
/**
 * Dashboard View
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Get services and data
$plugin = \WPBlogMailer\Core\Plugin::instance();

// --- START FIX: Revert to original service fetching ---
$subscriber_service = method_exists($plugin, 'get_subscriber_service') ? $plugin->get_subscriber_service() : null;
$analytics_service = method_exists($plugin, 'get_analytics_service') ? $plugin->get_analytics_service() : null;
// --- END FIX ---

// Get current plan tier
$tier = wpbm_get_plan_name();

// Get stats
$subscriber_stats = $subscriber_service ? $subscriber_service->get_stats() : ['total' => 0, 'active' => 0, 'unconfirmed' => 0];
$analytics_stats = null;

// --- START FIX: Correct Freemius plan check ---
$can_view_analytics = (function_exists('wpbm_fs') && (wpbm_fs()->is_plan_or_trial('starter', true) || wpbm_fs()->is_plan_or_trial('pro', true)));
// --- END FIX ---

if ($analytics_service && $can_view_analytics && method_exists($analytics_service, 'get_dashboard_stats') ) {
    try {
        $analytics_stats = $analytics_service->get_dashboard_stats(30);
    } catch (\Exception $e) {
    }
}

// --- START FIX: Use service and correct column 'created_at' ---
$recent_subscribers_result = $subscriber_service ? $subscriber_service->get_all(['per_page' => 5, 'orderby' => 'created_at', 'order' => 'DESC']) : null;
$recent_subscribers = $recent_subscribers_result ? $recent_subscribers_result['subscribers'] : [];
// --- END FIX ---


// Get subscriber limit info
$limits = [
    'free' => \WPBlogMailer\Common\Constants::FREE_SUBSCRIBER_LIMIT,
    'starter' => 1000,
    'pro' => 10000
];
$current_limit = PHP_INT_MAX; // Assume unlimited for pro
if (function_exists('wpbm_fs')) {
    if (wpbm_fs()->is_plan_or_trial('starter', true)) {
        $current_limit = $limits['starter'];
    } elseif (wpbm_fs()->is_plan_or_trial('free', true)) {
        $current_limit = $limits['free'];
    }
} else {
    $current_limit = $limits['free'];
}

// For limit calculation, count only active subscribers (confirmed + pending, exclude unsubscribed)
$active_subscribers_count = ($subscriber_stats['active'] ?? 0) + ($subscriber_stats['unconfirmed'] ?? 0);
$total_subscribers = $subscriber_stats['total'] ?? 0;
$limit_percentage = ($current_limit > 0 && $current_limit < PHP_INT_MAX) ? round(($active_subscribers_count / $current_limit) * 100) : 0;

// Get admin email for test send placeholder
$admin_email = get_option('admin_email');
?>

<div class="wrap wpbm-dashboard">
    <h1 class="wp-heading-inline"><?php esc_html_e('CN Blog Mailer Dashboard', 'blog-mailer'); ?></h1>

    <span class="wpbm-plan-badge wpbm-plan-<?php echo esc_attr($tier); ?>">
        <?php echo esc_html(ucfirst($tier)); ?> Plan
    </span>

    <?php
    if (isset($_GET['newsletter_sent']) && $_GET['newsletter_sent'] === '1'):
    ?>
    <div class="notice notice-success is-dismissible" style="margin-top: 20px;">
        <p><strong><?php esc_html_e('✉️ Newsletter sent successfully!', 'blog-mailer'); ?></strong></p>
        <?php if (isset($_GET['sent_count'])): ?>
        <p><?php
        /* translators: %s: number of subscribers */
        echo sprintf(esc_html__('Sent to %s subscriber(s).', 'blog-mailer'), intval($_GET['sent_count'])); ?>
        <?php if (isset($_GET['failed_count']) && intval($_GET['failed_count']) > 0): ?>
            <?php
            /* translators: %s: number of failed emails */
            echo sprintf(esc_html__(' %s failed.', 'blog-mailer'), intval($_GET['failed_count'])); ?>
        <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['newsletter_error']) && $_GET['newsletter_error'] === '1'): ?>
    <div class="notice notice-error is-dismissible" style="margin-top: 20px;">
        <p><strong><?php esc_html_e('❌ Newsletter sending failed.', 'blog-mailer'); ?></strong></p>
        <?php if (isset($_GET['error_message'])): ?>
        <p><?php echo esc_html(urldecode($_GET['error_message'])); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    // Check both Action Scheduler and WP-Cron for next scheduled newsletter
    $next_cron = false;

    // First check Action Scheduler
    if (class_exists('\WPBlogMailer\Common\Services\ActionSchedulerService')) {
        $next_cron = \WPBlogMailer\Common\Services\ActionSchedulerService::get_next_scheduled('wpbm_send_newsletter');
    }

    // Fallback to WP-Cron if Action Scheduler not available or not scheduled
    if (!$next_cron) {
        $next_cron = wp_next_scheduled('wpbm_send_newsletter');
    }

    if ($next_cron):
    ?>
    <div class="notice notice-info" style="margin-top: 20px; padding: 15px; border-left: 4px solid #2271b1;">
        <h3 style="margin-top: 0;">
            <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
            <?php esc_html_e('📅 Your Newsletter is Scheduled', 'blog-mailer'); ?>
        </h3>
        <p style="margin: 0 0 10px 0;">
            <strong><?php esc_html_e('Next send:', 'blog-mailer'); ?></strong>
            <?php echo esc_html(wp_date('F j, Y g:i a', $next_cron)); ?>
            (<?php echo esc_html(human_time_diff($next_cron, time())); ?> <?php esc_html_e('from now', 'blog-mailer'); ?>)
        </p>
        <p style="margin: 0; padding: 10px; background: #fff3cd; border-radius: 4px;">
            <strong>💡 <?php esc_html_e('Important:', 'blog-mailer'); ?></strong>
            <?php esc_html_e('WordPress scheduling requires visitors to trigger emails. Low-traffic sites may experience delays.', 'blog-mailer'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-cron-status')); ?>" style="font-weight: 600;">
                <?php esc_html_e('View troubleshooting options →', 'blog-mailer'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['test_email_sent']) && $_GET['test_email_sent'] === '1'): ?>
    <div class="notice notice-success is-dismissible" style="margin-top: 20px;">
        <p><strong><?php esc_html_e('✉️ Test email sent successfully!', 'blog-mailer'); ?></strong></p>
        <?php if (isset($_GET['test_email'])): ?>
        <p><?php
        /* translators: %s: email address */
        echo sprintf(esc_html__('Sent to: %s', 'blog-mailer'), esc_html(urldecode($_GET['test_email']))); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['test_email_failed']) && $_GET['test_email_failed'] === '1'): ?>
    <div class="notice notice-error is-dismissible" style="margin-top: 20px;">
        <p><strong><?php esc_html_e('❌ Test email failed to send.', 'blog-mailer'); ?></strong></p>
        <p><?php esc_html_e('Please check your email settings and try again. You can view detailed error logs in Send Log.', 'blog-mailer'); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['test_email_error']) && $_GET['test_email_error'] === '1'): ?>
    <div class="notice notice-error is-dismissible" style="margin-top: 20px;">
        <p><strong><?php esc_html_e('❌ Invalid email address.', 'blog-mailer'); ?></strong></p>
        <p><?php esc_html_e('Please enter a valid email address for the test.', 'blog-mailer'); ?></p>
    </div>
    <?php endif; ?>


     <?php if ($tier === 'free' && (!function_exists('wpbm_fs') || !wpbm_fs()->has_active_valid_license())): ?>
     <div class="notice notice-info" style="margin-top: 20px;">
         <p>
             <strong><?php esc_html_e('📈 Ready to grow?', 'blog-mailer'); ?></strong>
             <?php esc_html_e('Upgrade to unlock Analytics, Import/Export, Custom Email, and more!', 'blog-mailer'); ?>
             <a href="<?php echo esc_url(wpbm_get_upgrade_url()); ?>" class="button button-primary" style="margin-left: 10px;">
                 <?php esc_html_e('View Plans', 'blog-mailer'); ?>
             </a>
         </p>
     </div>
     <?php endif; ?>

    <?php
    // Show SMTP notice only if user hasn't dismissed it
    $smtp_notice_dismissed = get_user_meta(get_current_user_id(), 'wpbm_smtp_notice_dismissed', true);
    if (!$smtp_notice_dismissed):
    ?>
    <div class="notice notice-info is-dismissible wpbm-smtp-notice" style="margin-top: 20px;" data-notice-id="smtp">
        <p>
            <strong><?php esc_html_e('📧 Improve Email Deliverability', 'blog-mailer'); ?></strong>
        </p>
        <p>
            <?php esc_html_e('For best results, configure SMTP for reliable email delivery. We recommend using an SMTP plugin like WP Mail SMTP, Easy WP SMTP, or Post SMTP Mailer.', 'blog-mailer'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-settings')); ?>" class="button button-secondary">
                <?php esc_html_e('View SMTP Setup Guide', 'blog-mailer'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('plugin-install.php?s=SMTP&tab=search&type=term')); ?>" class="button button-secondary" style="margin-left: 10px;">
                <?php esc_html_e('Browse SMTP Plugins', 'blog-mailer'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <div class="wpbm-stats-grid">

        <div class="wpbm-stat-card">
            <div class="wpbm-stat-icon" style="background: #e7f5ff;">
                <span class="dashicons dashicons-groups" style="color: #2271b1;"></span>
            </div>
            <div class="wpbm-stat-content">
                <div class="wpbm-stat-label"><?php esc_html_e('Total Subscribers', 'blog-mailer'); ?></div>
                <div class="wpbm-stat-value"><?php echo esc_html(number_format_i18n($subscriber_stats['total'] ?? 0)); ?></div>
                <?php if ($current_limit < PHP_INT_MAX): ?>
                <div class="wpbm-stat-meta">
                    <span class="wpbm-limit-text"><?php echo esc_html(number_format_i18n($active_subscribers_count)); ?> / <?php echo esc_html(number_format_i18n($current_limit)); ?> limit</span>
                    <?php if ($limit_percentage > 80): ?>
                        <span class="wpbm-warning-badge"><?php echo esc_html($limit_percentage); ?>% used</span>
                    <?php endif; ?>
                     <?php if ($limit_percentage > 90): ?>
                         <a href="<?php echo esc_url(wpbm_get_upgrade_url()); ?>" style="font-weight: bold;"><?php esc_html_e('Upgrade?', 'blog-mailer'); ?></a>
                     <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="wpbm-stat-card">
            <div class="wpbm-stat-icon" style="background: #e8f5e9;">
                <span class="dashicons dashicons-yes-alt" style="color: #46a049;"></span>
            </div>
            <div class="wpbm-stat-content">
                 <div class="wpbm-stat-label"><?php esc_html_e('Confirmed', 'blog-mailer'); ?></div>
                 <div class="wpbm-stat-value"><?php echo esc_html(number_format_i18n($subscriber_stats['active'] ?? 0)); ?></div>
                <div class="wpbm-stat-meta">
                    <?php
                    $total_for_calc = $subscriber_stats['total'] ?? 0;
                    $active_for_calc = $subscriber_stats['active'] ?? 0;
                    $active_percent = $total_for_calc > 0 ? round(($active_for_calc / $total_for_calc) * 100) : 0;
                    echo esc_html($active_percent . '%');
                    ?>
                    <?php esc_html_e('of total', 'blog-mailer'); ?>
                </div>
            </div>
        </div>

         <?php
// Only show pending status for Starter/Pro (double opt-in feature)
        if (!wpbm_is_free_plan() && function_exists('wpbm_is_double_optin_enabled') && wpbm_is_double_optin_enabled()): ?>
         <div class="wpbm-stat-card">
             <div class="wpbm-stat-icon" style="background: #fff3e0;">
                 <span class="dashicons dashicons-clock" style="color: #f57c00;"></span>
             </div>
             <div class="wpbm-stat-content">
                 <div class="wpbm-stat-label"><?php esc_html_e('Pending Confirmation', 'blog-mailer'); ?></div>
                 <div class="wpbm-stat-value"><?php echo esc_html(number_format_i18n($subscriber_stats['unconfirmed'] ?? 0)); ?></div>
                  <div class="wpbm-stat-meta"><?php esc_html_e('Double opt-in enabled', 'blog-mailer'); ?></div>
             </div>
         </div>
         <?php endif; ?>

        <?php if ($can_view_analytics && $analytics_stats && isset($analytics_stats['total_sends'])): ?>
        <div class="wpbm-stat-card">
            <div class="wpbm-stat-icon" style="background: #f3e5f5;">
                <span class="dashicons dashicons-email-alt" style="color: #7b1fa2;"></span>
            </div>
            <div class="wpbm-stat-content">
                <div class="wpbm-stat-label"><?php esc_html_e('Emails Sent', 'blog-mailer'); ?></div>
                <div class="wpbm-stat-value"><?php echo esc_html(number_format_i18n($analytics_stats['total_sends'])); ?></div>
                <div class="wpbm-stat-meta"><?php esc_html_e('Last 30 days', 'blog-mailer'); ?></div>
            </div>
        </div>
        <?php endif; ?>

         <?php if ($can_view_analytics && $analytics_stats && isset($analytics_stats['open_rate'])): ?>
        <div class="wpbm-stat-card">
            <div class="wpbm-stat-icon" style="background: #e1f5fe;">
                <span class="dashicons dashicons-chart-line" style="color: #0288d1;"></span>
            </div>
            <div class="wpbm-stat-content">
                <div class="wpbm-stat-label"><?php esc_html_e('Avg. Open Rate', 'blog-mailer'); ?></div>
                 <div class="wpbm-stat-value"><?php echo (isset($analytics_stats['open_rate']) && is_numeric($analytics_stats['open_rate'])) ? esc_html(number_format_i18n($analytics_stats['open_rate'], 1)) . '%' : 'N/A'; ?></div>
                <div class="wpbm-stat-meta"><?php esc_html_e('Last 30 days', 'blog-mailer'); ?></div>
            </div>
        </div>
         <?php endif; ?>

         <?php if ($can_view_analytics && $analytics_stats && isset($analytics_stats['click_rate'])): ?>
        <div class="wpbm-stat-card">
            <div class="wpbm-stat-icon" style="background: #fce4ec;">
                <span class="dashicons dashicons-admin-links" style="color: #c2185b;"></span>
            </div>
            <div class="wpbm-stat-content">
                <div class="wpbm-stat-label"><?php esc_html_e('Avg. Click Rate', 'blog-mailer'); ?></div>
                 <div class="wpbm-stat-value"><?php echo (isset($analytics_stats['click_rate']) && is_numeric($analytics_stats['click_rate'])) ? esc_html(number_format_i18n($analytics_stats['click_rate'], 1)) . '%' : 'N/A'; ?></div>
                <div class="wpbm-stat-meta"><?php esc_html_e('Last 30 days', 'blog-mailer'); ?></div>
            </div>
        </div>
        <?php endif; ?>

         <?php if (!$can_view_analytics && $tier === 'free'): ?>
             <div class="wpbm-stat-card wpbm-upgrade-prompt" style="grid-column: span 2;">
                 <div class="wpbm-stat-icon" style="background: #eee;">
                     <span class="dashicons dashicons-lock" style="color: #777;"></span>
                 </div>
                 <div class="wpbm-stat-content">
                     <p><strong><?php esc_html_e('Unlock Email Analytics', 'blog-mailer'); ?></strong></p>
                     <p style="font-size: 13px; color: #50575e;"><?php esc_html_e('Track opens, clicks, and more. Upgrade to Starter or Pro.', 'blog-mailer'); ?></p>
                     <a href="<?php echo esc_url(wpbm_get_upgrade_url()); ?>" class="button button-secondary" style="margin-top: 10px;">
                         <?php esc_html_e('View Plans', 'blog-mailer'); ?>
                     </a>
                 </div>
             </div>
         <?php endif; ?>
    </div>

    <div class="wpbm-dashboard-columns">

        <div class="wpbm-dashboard-main">

            <div class="wpbm-dashboard-card wpbm-send-now-card">
                <div class="wpbm-send-now-content">
                    <div class="wpbm-send-now-info">
                        <h2><?php esc_html_e('Newsletter', 'blog-mailer'); ?></h2>
                        <p><?php esc_html_e('Send your latest blog posts to all confirmed subscribers right now.', 'blog-mailer'); ?></p>
                        <?php
                        // Check both Action Scheduler and WP-Cron
                        $next_scheduled = false;
                        if (class_exists('\WPBlogMailer\Common\Services\ActionSchedulerService')) {
                            $next_scheduled = \WPBlogMailer\Common\Services\ActionSchedulerService::get_next_scheduled('wpbm_send_newsletter');
                        }
                        if (!$next_scheduled) {
                            $next_scheduled = wp_next_scheduled('wpbm_send_newsletter');
                        }
                        if ($next_scheduled):
                        ?>
                        <p class="wpbm-next-send">
                            <span class="dashicons dashicons-clock"></span>
                            <?php
                            /* translators: %s: formatted date and time of next newsletter send */
                            printf(
                                esc_html__('Next scheduled send: %s', 'blog-mailer'),
                                '<strong>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)) . ' (' . esc_html(human_time_diff($next_scheduled, time())) . ')' . '</strong>'
                            );
                            ?>
                        </p>
                        <?php else: ?>
                         <p class="wpbm-next-send">
                             <span class="dashicons dashicons-info"></span>
                             <?php esc_html_e('Automatic sending is not currently scheduled. Check Settings > Newsletter.', 'blog-mailer'); ?>
                         </p>
                        <?php endif; ?>
                    </div>
                    <div class="wpbm-send-now-action">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wpbm-send-now-form">
                            <?php wp_nonce_field('wpbm_send_newsletter_now', 'wpbm_send_now_nonce'); ?>
                            <input type="hidden" name="action" value="wpbm_send_newsletter_now">
                            
                            <button type="submit" class="button button-primary button-hero wpbm-send-now-btn">
                                <span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                                <span style="vertical-align: middle;"><?php esc_html_e('Send Now', 'blog-mailer'); ?></span>
                            </button>
                             <?php // --- FIX: Removed Test Email Button --- ?>
                            <span class="spinner" style="float: none; margin: 10px 0 0 0;"></span>
                        </form>
                        <p class="description" style="margin-top: 10px;">
                            <?php
                            /* translators: %s: number of confirmed subscribers */
                            printf(esc_html__('Sends to %s confirmed subscribers.', 'blog-mailer'), esc_html(number_format_i18n($subscriber_stats['active'] ?? 0))); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="wpbm-dashboard-card">
                <h2><?php esc_html_e('Quick Actions', 'blog-mailer'); ?></h2>
                <div class="wpbm-quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-subscribers')); ?>#add-new" class="wpbm-action-button" id="wpbm-quick-add-subscriber">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Subscriber', 'blog-mailer'); ?>
                    </a>

                     <?php // --- START FIX: Correct Freemius plan checks --- ?>
                     <?php if (function_exists('wpbm_fs') && wpbm_fs()->is_plan_or_trial('pro', true)): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-custom-email')); ?>" class="wpbm-action-button">
                            <span class="dashicons dashicons-email"></span>
                            <?php esc_html_e('Send Custom Email', 'blog-mailer'); ?>
                        </a>
                     <?php else: ?>
                      <a href="<?php echo esc_url(wpbm_get_upgrade_url('pro')); ?>" class="wpbm-action-button wpbm-upsell-action">
                          <span class="dashicons dashicons-lock"></span>
                          <?php esc_html_e('Send Custom Email (Pro)', 'blog-mailer'); ?>
                      </a>
                     <?php endif; ?>

                     <?php if (function_exists('wpbm_fs') && (wpbm_fs()->is_plan_or_trial('starter', true) || wpbm_fs()->is_plan_or_trial('pro', true))): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-import-export')); ?>" class="wpbm-action-button">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e('Import / Export', 'blog-mailer'); ?>
                        </a>
                      <?php else: ?>
                      <a href="<?php echo esc_url(wpbm_get_upgrade_url('starter')); ?>" class="wpbm-action-button wpbm-upsell-action">
                          <span class="dashicons dashicons-lock"></span>
                          <?php esc_html_e('Import / Export (Starter+)', 'blog-mailer'); ?>
                      </a>
                     <?php endif; ?>
                     <?php // --- END FIX --- ?>

                    <?php // --- START FIX: Replace Settings with Send Test Email (as a form) --- ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wpbm-send-test-form-quick-action" style="margin: 0;">
                        <?php wp_nonce_field('wpbm_send_newsletter_now', 'wpbm_send_now_nonce'); ?>
                        <input type="hidden" name="action" value="wpbm_send_newsletter_now">
                        <input type="hidden" name="send_test" value="1">
                        <?php // This hidden input will be populated by JS ?>
                        <input type="hidden" name="test_email_address" id="wpbm-test-email-address" value=""> 
                        
                        <button type="submit" class="wpbm-action-button" id="wpbm-quick-send-test-btn" style="width: 100%; justify-content: center; cursor: pointer;">
                            <span class="dashicons dashicons-email"></span>
                            <?php esc_html_e('Send Test Email', 'blog-mailer'); ?>
                        </button>
                    </form>
                    <?php // --- END FIX --- ?>
                </div>
            </div>

            <div class="wpbm-dashboard-card">
                <h2><?php esc_html_e('Recent Subscribers', 'blog-mailer'); ?></h2>
                <?php if (!empty($recent_subscribers)): ?>
                <div class="wpbm-recent-list">
                    <?php foreach ($recent_subscribers as $subscriber): ?>
                    <div class="wpbm-recent-item">
                        <div class="wpbm-recent-avatar">
                            <?php echo get_avatar($subscriber->email, 40); ?>
                        </div>
                        <div class="wpbm-recent-info">
                            <div class="wpbm-recent-name">
                                 <?php echo esc_html(trim($subscriber->first_name . ' ' . $subscriber->last_name)); ?>
                                <?php if ($subscriber->status === 'unsubscribed'): ?>
                                    <span class="wpbm-badge wpbm-badge-danger"><?php esc_html_e('Unsubscribed', 'blog-mailer'); ?></span>
                                <?php endif; ?>
                            </div>
                             <div class="wpbm-recent-email"><?php echo esc_html($subscriber->email); ?></div>
                        </div>
                        <div class="wpbm-recent-date">
                             <?php
                               $date_string = 'N/A';
                               if (!empty($subscriber->created_at)) {
                                   try {
                                       $timestamp = strtotime($subscriber->created_at);
                                       if ($timestamp) {
                                           $date_string = human_time_diff($timestamp, current_time('timestamp')) . ' ' . esc_html__('ago', 'blog-mailer');
                                       } else { $date_string = $subscriber->created_at; }
                                   } catch (\Exception $e) { $date_string = $subscriber->created_at; }
                               }
                               echo esc_html($date_string);
                             ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-subscribers')); ?>" class="wpbm-view-all">
                    <?php esc_html_e('View All Subscribers →', 'blog-mailer'); ?>
                </a>
                <?php else: ?>
                <div class="wpbm-empty-state">
                    <span class="dashicons dashicons-groups"></span>
                    <p><?php esc_html_e('No subscribers yet. Add your first subscriber to get started!', 'blog-mailer'); ?></p>
                    <button type="button" class="button button-primary" id="wpbm-quick-add-subscriber-empty">
                        <?php esc_html_e('Add Subscriber', 'blog-mailer'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="wpbm-dashboard-sidebar">

            <div class="wpbm-dashboard-card">
                <h3><?php esc_html_e('System Status', 'blog-mailer'); ?></h3>
                <div class="wpbm-status-list">
                    <?php
                    // Check both Action Scheduler and WP-Cron
                    $next_cron_run = false;
                    if (class_exists('\WPBlogMailer\Common\Services\ActionSchedulerService')) {
                        $next_cron_run = \WPBlogMailer\Common\Services\ActionSchedulerService::get_next_scheduled('wpbm_send_newsletter');
                    }
                    if (!$next_cron_run) {
                        $next_cron_run = wp_next_scheduled('wpbm_send_newsletter');
                    }
                    $last_send_timestamp = get_option('wpbm_last_newsletter_send_timestamp', false);
                    ?>
                    <div class="wpbm-status-item">
                        <span class="wpbm-status-label"><?php esc_html_e('Newsletter Cron', 'blog-mailer'); ?></span>
                        <span class="wpbm-status-value <?php echo $next_cron_run ? 'wpbm-status-active' : 'wpbm-status-inactive'; ?>">
                            <?php echo $next_cron_run ? esc_html__('Scheduled', 'blog-mailer') : esc_html__('Not Scheduled', 'blog-mailer'); ?>
                             <?php if ($next_cron_run): ?>
                                 <small>(<?php echo esc_html(human_time_diff($next_cron_run, time())); ?>)</small>
                             <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($last_send_timestamp): ?>
                    <div class="wpbm-status-item">
                        <span class="wpbm-status-label"><?php esc_html_e('Last Newsletter Sent', 'blog-mailer'); ?></span>
                        <span class="wpbm-status-value">
                            <?php echo esc_html(human_time_diff($last_send_timestamp, current_time('timestamp'))); ?> <?php esc_html_e('ago', 'blog-mailer'); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="wpbm-status-item">
                        <span class="wpbm-status-label"><?php esc_html_e('Your Plan', 'blog-mailer'); ?></span>
                        <span class="wpbm-status-value" style="text-transform: capitalize;">
                            <?php echo esc_html($tier); ?>
                        </span>
                    </div>
                     <div class="wpbm-status-item">
                        <span class="wpbm-status-label"><?php esc_html_e('Plugin Version', 'blog-mailer'); ?></span>
                        <span class="wpbm-status-value">
                            <?php echo defined('WPBM_VERSION') ? esc_html(WPBM_VERSION) : 'N/A'; ?>
                        </span>
                    </div>
                </div>
            </div>

             <?php if (function_exists('wpbm_fs') && wpbm_fs()->is_free_plan()): ?>
             <div class="wpbm-dashboard-card wpbm-upgrade-card">
                 <h3><?php esc_html_e('Unlock Premium Features', 'blog-mailer'); ?></h3>
                 <ul class="wpbm-feature-list">
                     <li>✨ <?php esc_html_e('Advanced Analytics', 'blog-mailer'); ?></li>
                     <li>✨ <?php esc_html_e('Custom Email Builder', 'blog-mailer'); ?></li>
                     <li>✨ <?php esc_html_e('Import/Export Subscribers', 'blog-mailer'); ?></li>
                     <li>✨ <?php esc_html_e('Email Templates', 'blog-mailer'); ?></li>
                     <li>✨ <?php esc_html_e('Double Opt-in', 'blog-mailer'); ?></li>
                     <li>✨ <?php esc_html_e('Increased Subscriber Limits', 'blog-mailer'); ?></li>
                     <li>✨ <?php esc_html_e('Priority Support', 'blog-mailer'); ?></li>
                 </ul>
                 <p style="margin-top: 15px; font-size: 12px; color: rgba(255,255,255,0.9); text-align: center;">
                     <?php esc_html_e('Enjoying CN Blog Mailer?', 'blog-mailer'); ?>
                     <a href="https://wordpress.org/support/plugin/cn-blog-mailer/reviews/#new-post" target="_blank" style="color: #fff; text-decoration: underline;">
                         <?php esc_html_e('Rate us in the store!', 'blog-mailer'); ?>
                     </a>
                 </p>
                 <a href="<?php echo esc_url(wpbm_get_upgrade_url()); ?>" class="button button-primary button-large" style="width: 100%; text-align: center; margin-top: 10px;">
                     <?php esc_html_e('Upgrade Now', 'blog-mailer'); ?>
                 </a>
             </div>
             <?php endif; ?>

            <div class="wpbm-dashboard-card wpbm-form-docs-card">
                <h3><?php esc_html_e('Subscribe Form', 'blog-mailer'); ?></h3>
                <p style="font-size: 13px; color: #646970; margin-bottom: 15px;">
                    <?php esc_html_e('Add a subscription form to any page or post using the shortcode below.', 'blog-mailer'); ?>
                </p>

                <div class="wpbm-code-box">
                    <code>[wpbm_subscribe_form]</code>
                    <button type="button" class="wpbm-copy-btn" data-clipboard-text="[wpbm_subscribe_form]" title="<?php esc_attr_e('Copy to clipboard', 'blog-mailer'); ?>">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>

                <details class="wpbm-form-params" style="margin-top: 15px;">
                    <summary style="cursor: pointer; font-weight: 500; color: #2271b1; font-size: 13px; padding: 8px 0;">
                        <?php esc_html_e('Available Parameters', 'blog-mailer'); ?>
                        <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; vertical-align: middle;"></span>
                    </summary>
                    <div style="margin-top: 10px; padding: 10px; background: #f6f7f7; border-radius: 4px; font-size: 12px;">
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><strong>title</strong> - <?php esc_html_e('Form header text', 'blog-mailer'); ?></li>
                            <li><strong>description</strong> - <?php esc_html_e('Subtitle text', 'blog-mailer'); ?></li>
                            <li><strong>button_text</strong> - <?php esc_html_e('Submit button label', 'blog-mailer'); ?></li>
                            <li><strong>show_name</strong> - <?php esc_html_e('Show name fields (yes/no)', 'blog-mailer'); ?></li>
                            <li><strong>success_message</strong> - <?php esc_html_e('Success message text', 'blog-mailer'); ?></li>
                            <li><strong>class</strong> - <?php esc_html_e('Custom CSS class', 'blog-mailer'); ?></li>
                        </ul>

                        <p style="margin: 12px 0 8px 0; font-weight: 500; color: #1d2327;">
                            <?php esc_html_e('Example:', 'blog-mailer'); ?>
                        </p>
                        <div class="wpbm-code-box" style="position: relative;">
                            <code style="display: block; white-space: pre-wrap; word-wrap: break-word; background: #fff; padding: 10px; border-radius: 3px; font-size: 11px;">[wpbm_subscribe_form
    title="Join Our Newsletter"
    button_text="Sign Up"
    show_name="no"]</code>
                            <button type="button" class="wpbm-copy-btn" data-clipboard-text='[wpbm_subscribe_form title="Join Our Newsletter" button_text="Sign Up" show_name="no"]' title="<?php esc_attr_e('Copy to clipboard', 'blog-mailer'); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                    </div>
                </details>
            </div>

            <div class="wpbm-dashboard-card">
                <h3><?php esc_html_e('Help & Support', 'blog-mailer'); ?></h3>
                <ul class="wpbm-help-links">
                    <li>
                        <a href="https://creativenoesis.com/cn-blog-mailer/" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-book"></span>
                            <?php esc_html_e('Documentation / FAQ', 'blog-mailer'); ?>
                        </a>
                    </li>
                     <?php if (function_exists('wpbm_fs') && wpbm_fs()->is_registered() && !wpbm_fs()->is_free_plan()) : ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-contact')); // Use correct slug if different ?>">
                                <span class="dashicons dashicons-email"></span>
                                <?php esc_html_e('Contact Us', 'blog-mailer'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>

    </div>

</div>

<style>
/* Dashboard Styles */
.wpbm-dashboard { margin-right: 20px; }
.wpbm-plan-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 10px; vertical-align: middle; }
.wpbm-plan-free { background: #f0f0f1; color: #646970; }
.wpbm-plan-starter { background: #e7f5ff; color: #0073aa; }
.wpbm-plan-pro { background: #f3e5f5; color: #7b1fa2; }
.wpbm-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
.wpbm-stat-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; display: flex; align-items: flex-start; gap: 15px; transition: box-shadow 0.2s; }
.wpbm-stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.wpbm-stat-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.wpbm-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; }
.wpbm-stat-content { flex: 1; overflow: hidden; }
.wpbm-stat-label { font-size: 13px; color: #646970; margin-bottom: 5px; }
.wpbm-stat-value { font-size: 28px; font-weight: 600; color: #1d2327; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
.wpbm-stat-meta { font-size: 12px; color: #787c82; margin-top: 5px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;}
.wpbm-warning-badge { background: #fcf9e8; color: #996800; padding: 2px 6px; border-radius: 3px; font-weight: 600; }
.wpbm-dashboard-columns { display: grid; grid-template-columns: 1fr 350px; gap: 20px; margin-top: 20px; }
@media (max-width: 1200px) { .wpbm-dashboard-columns { grid-template-columns: 1fr; } }
.wpbm-dashboard-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
.wpbm-dashboard-card h2, .wpbm-dashboard-card h3 { margin: 0 0 15px 0; font-size: 16px; font-weight: 600; padding-bottom: 10px; border-bottom: 1px solid #eee;}
/* Send Now Card */
.wpbm-send-now-card { background: #f0f0f1; color: #1d2327; border-color: #c3c4c7; }
.wpbm-send-now-card h2 { color: #1d2327; }
.wpbm-send-now-card p { color: #3c434a; }
.wpbm-send-now-content { display: flex; align-items: center; justify-content: space-between; gap: 30px; flex-wrap: wrap;}
@media (max-width: 900px) { .wpbm-send-now-content { flex-direction: column; align-items: stretch; text-align: center;} }
.wpbm-send-now-info { flex: 1; min-width: 200px;}
.wpbm-next-send { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #50575e; margin-top: 10px; }
.wpbm-next-send .dashicons { font-size: 16px; width: 16px; height: 16px; }
.wpbm-send-now-action { text-align: center; flex-shrink: 0;}
.wpbm-send-now-btn { min-width: 150px; white-space: nowrap; }
.wpbm-send-now-btn .dashicons { margin-top: 3px; vertical-align: middle; }
#wpbm-send-now-form .spinner { display: none; vertical-align: middle; margin-left: 5px;}
#wpbm-send-now-form.wpbm-sending .spinner { display: inline-block; visibility: visible; }
#wpbm-send-now-form.wpbm-sending button { opacity: 0.6; pointer-events: none; }
/* Quick Actions */
.wpbm-quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.wpbm-action-button { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; text-decoration: none; color: #2c3338; font-weight: 500; transition: all 0.2s; text-align: center; justify-content: center; }
.wpbm-action-button:hover { background: #fff; border-color: #2271b1; color: #2271b1; }
.wpbm-action-button .dashicons { font-size: 18px; width: 18px; height: 18px; }
.wpbm-upsell-action { color: #777 !important; background: #fafafa !important; border-color: #ddd !important; cursor: default !important; opacity: 0.7;}
.wpbm-upsell-action:hover { color: #555 !important; border-color: #ccc !important; }
.wpbm-upsell-action .dashicons { color: #aaa !important; }
/* Recent List */
.wpbm-recent-list { display: flex; flex-direction: column; gap: 0; margin: 0 -20px; }
.wpbm-recent-item { display: flex; align-items: center; gap: 12px; padding: 10px 20px; border-bottom: 1px solid #f0f0f1; }
.wpbm-recent-item:last-child { border-bottom: none; }
.wpbm-recent-avatar img { border-radius: 50%; }
.wpbm-recent-info { flex: 1; overflow: hidden; }
.wpbm-recent-name { font-weight: 500; color: #1d2327; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wpbm-recent-email { font-size: 12px; color: #646970; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wpbm-recent-date { font-size: 12px; color: #787c82; flex-shrink: 0; white-space: nowrap; }
.wpbm-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 6px; vertical-align: middle; }
.wpbm-badge-warning { background: #fff8e1; color: #ff8f00; border: 1px solid #ffecb3;}
.wpbm-badge-danger { background: #fce4ec; color: #ad1457; border: 1px solid #f8bbd0; }
.wpbm-view-all { display: block; text-align: center; margin: 15px 0 0 0; text-decoration: none; color: #2271b1; font-weight: 500; }
.wpbm-view-all:hover { color: #135e96; }
/* Empty State */
.wpbm-empty-state { text-align: center; padding: 40px 20px; }
.wpbm-empty-state .dashicons { font-size: 48px; width: 48px; height: 48px; color: #a7aaad; margin-bottom: 15px; }
.wpbm-empty-state p { color: #646970; margin-bottom: 15px; font-size: 14px; }
/* Status List */
.wpbm-status-list { display: flex; flex-direction: column; gap: 10px; }
.wpbm-status-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f1; }
.wpbm-status-item:last-child { border-bottom: none; }
.wpbm-status-label { font-size: 13px; color: #646970; padding-right: 10px;}
.wpbm-status-value { font-size: 13px; font-weight: 500; color: #1d2327; text-align: right; }
.wpbm-status-value small { font-size: 11px; color: #777; display: block; font-weight: 400;}
.wpbm-status-active { color: #00a32a !important; }
.wpbm-status-inactive { color: #d63638 !important; }
/* Upgrade Card */
.wpbm-upgrade-card { background: linear-gradient(135deg, #4e65d6 0%, #6f42c1 100%); color: #fff; border: none; }
.wpbm-upgrade-card h3 { color: #fff; }
.wpbm-feature-list { list-style: none; margin: 15px 0; padding: 0; }
.wpbm-feature-list li { padding: 6px 0; font-size: 14px; color: rgba(255,255,255,0.9); }
/* Help Links */
.wpbm-help-links { list-style: none; margin: 0; padding: 0; }
.wpbm-help-links li { margin: 0; }
.wpbm-help-links a { display: flex; align-items: center; gap: 8px; padding: 10px; text-decoration: none; color: #2c3338; border-radius: 4px; transition: background 0.2s; }
.wpbm-help-links a:hover { background: #f0f0f1; color: #2271b1; }
.wpbm-help-links .dashicons { font-size: 16px; width: 16px; height: 16px; line-height: 1; }
/* Upgrade Prompt in Stats */
.wpbm-upgrade-prompt { flex-direction: column; align-items: stretch; justify-content: center; text-align: left; gap: 10px; background-color: #f9f9f9; padding: 15px;}
.wpbm-upgrade-prompt p { margin: 0 0 10px 0; font-size: 13px; color: #50575e; }
.wpbm-upgrade-prompt .button { margin-top: 5px; }
/* Subscribe Form Documentation */
.wpbm-code-box { position: relative; background: #f6f7f7; padding: 12px 50px 12px 12px; border-radius: 4px; border: 1px solid #dcdcde; margin: 10px 0; }
.wpbm-code-box code { font-family: Consolas, Monaco, monospace; font-size: 13px; color: #d63638; background: transparent; padding: 0; }
.wpbm-copy-btn { position: absolute; top: 50%; right: 8px; transform: translateY(-50%); background: #2271b1; color: #fff; border: none; border-radius: 3px; padding: 6px 10px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.wpbm-copy-btn:hover { background: #135e96; }
.wpbm-copy-btn:active { transform: translateY(-50%) scale(0.95); }
.wpbm-copy-btn .dashicons { font-size: 16px; width: 16px; height: 16px; color: #fff; }
.wpbm-copy-btn.wpbm-copied { background: #00a32a; }
.wpbm-copy-btn.wpbm-copied::after { content: '✓'; position: absolute; font-size: 14px; }
.wpbm-form-params summary:hover { text-decoration: underline; }
.wpbm-form-params[open] summary .dashicons { transform: rotate(180deg); }
.wpbm-form-params summary .dashicons { transition: transform 0.2s; display: inline-block; }

</style>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#wpbm-quick-add-subscriber-empty, #wpbm-quick-add-subscriber').on('click', function(e) {
            e.preventDefault();
            window.location.href = '<?php echo esc_js(admin_url('admin.php?page=wpbm-subscribers')); ?>#add-new';
        });

        $('#wpbm-send-now-form').on('submit', function(e) {
             var confirmMessage = '<?php esc_attr_e('Are you sure you want to send the newsletter now to all confirmed subscribers?', 'blog-mailer'); ?>';

             if (!confirm(confirmMessage)) {
                 e.preventDefault();
                 return;
             }
            var $form = $(this);
            var $buttons = $form.find('button');
            var $spinner = $form.find('.spinner');
            if ($form.hasClass('wpbm-sending')) {
                e.preventDefault();
                return;
            }
            $form.addClass('wpbm-sending');
            $buttons.prop('disabled', true);
            $spinner.css({'visibility': 'visible', 'display': 'inline-block'});
        });
        
        // --- START NEW JS ---
        // Handler for the new "Send Test Email" button in Quick Actions
        $('#wpbm-quick-send-test-btn').on('click', function(e) {
            e.preventDefault(); // Stop the form from submitting immediately

            var $form = $(this).closest('form');
            var $button = $(this);
            var $emailField = $form.find('#wpbm-test-email-address');

            var email = prompt('<?php esc_attr_e('Enter the email address to send a test to:', 'blog-mailer'); ?>', '<?php echo esc_js($admin_email); ?>');

            if (email === null || email === "") {
                // User cancelled or entered nothing
                return;
            }

            // Simple email validation
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert('<?php esc_attr_e('Please enter a valid email address.', 'blog-mailer'); ?>');
                return;
            }
            
            // Set the hidden email field value
            $emailField.val(email);

            // Now submit the form
            if ($form.hasClass('wpbm-sending')) {
                return;
            }
            $form.addClass('wpbm-sending');
            $button.prop('disabled', true).css('opacity', '0.6');
            
            // Manually submit the form
            $form.submit();
        });
        // --- END NEW JS ---

        // Copy to clipboard functionality for shortcode examples
        $('.wpbm-copy-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var text = $btn.data('clipboard-text');

            // Create temporary textarea to copy from
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                $btn.addClass('wpbm-copied');
                var originalHTML = $btn.html();
                $btn.html('<span class="dashicons dashicons-yes" style="color: #fff;"></span>');

                setTimeout(function() {
                    $btn.removeClass('wpbm-copied');
                    $btn.html(originalHTML);
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }

            $temp.remove();
        });

        // Handle dismissible SMTP notice
        $('.wpbm-smtp-notice').on('click', '.notice-dismiss', function() {
            // Save dismissal preference via AJAX
            $.post(ajaxurl, {
                action: 'wpbm_dismiss_smtp_notice',
                nonce: '<?php echo esc_js(wp_create_nonce('wpbm_dismiss_smtp_notice')); ?>'
            });
        });
    });
</script>