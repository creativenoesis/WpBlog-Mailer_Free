<?php
/* phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are passed from controller */
/**
 * Getting Started / Help Page View
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap wpbm-getting-started">
    <h1><?php esc_html_e('Getting Started with CN Blog Mailer', 'blog-mailer'); ?></h1>
    <p class="wpbm-intro"><?php esc_html_e('Welcome! Follow this guide to set up your newsletter and start engaging with your subscribers.', 'blog-mailer'); ?></p>

    <div class="wpbm-help-grid">
        <!-- Quick Setup Checklist -->
        <div class="wpbm-help-card wpbm-checklist-card">
            <div class="wpbm-help-card-header">
                <h2>
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Quick Setup Checklist', 'blog-mailer'); ?>
                </h2>
            </div>
            <div class="wpbm-help-card-content">
                <p><?php esc_html_e('Complete these steps to get your newsletter up and running:', 'blog-mailer'); ?></p>
                <ol class="wpbm-checklist">
                    <li>
                        <strong><?php esc_html_e('Configure Sender Information', 'blog-mailer'); ?></strong>
                        <p><?php esc_html_e('Go to Settings and set your "From Name" and "From Email".', 'blog-mailer'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-settings')); ?>" class="button button-secondary button-small">
                            <?php esc_html_e('Go to Settings', 'blog-mailer'); ?>
                        </a>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Set Up SMTP (Recommended)', 'blog-mailer'); ?></strong>
                        <p><?php esc_html_e('Install an SMTP plugin for reliable email delivery.', 'blog-mailer'); ?></p>
                        <a href="#smtp-setup" class="button button-secondary button-small">
                            <?php esc_html_e('View SMTP Guide', 'blog-mailer'); ?>
                        </a>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Add Your First Subscriber', 'blog-mailer'); ?></strong>
                        <p><?php esc_html_e('Add yourself as a test subscriber to receive test emails.', 'blog-mailer'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-subscribers')); ?>" class="button button-secondary button-small">
                            <?php esc_html_e('Manage Subscribers', 'blog-mailer'); ?>
                        </a>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Send a Test Email', 'blog-mailer'); ?></strong>
                        <p><?php esc_html_e('Test your configuration by sending a newsletter to yourself.', 'blog-mailer'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-dashboard')); ?>" class="button button-secondary button-small">
                            <?php esc_html_e('Go to Dashboard', 'blog-mailer'); ?>
                        </a>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Add Subscription Form to Your Site', 'blog-mailer'); ?></strong>
                        <p><?php esc_html_e('Let visitors subscribe using a shortcode or widget.', 'blog-mailer'); ?></p>
                        <a href="#subscription-form" class="button button-secondary button-small">
                            <?php esc_html_e('View Instructions', 'blog-mailer'); ?>
                        </a>
                    </li>
                </ol>
            </div>
        </div>

        <!-- SMTP Setup Guide -->
        <div class="wpbm-help-card" id="smtp-setup">
            <div class="wpbm-help-card-header">
                <h2>
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e('SMTP Setup Guide', 'blog-mailer'); ?>
                </h2>
            </div>
            <div class="wpbm-help-card-content">
                <div class="wpbm-info-box">
                    <p><strong><?php esc_html_e('Why SMTP?', 'blog-mailer'); ?></strong></p>
                    <p><?php esc_html_e('WordPress uses PHP mail() by default, which often gets flagged as spam. SMTP (Simple Mail Transfer Protocol) uses proper email servers with authentication, dramatically improving deliverability.', 'blog-mailer'); ?></p>
                </div>

                <h3><?php esc_html_e('Step 1: Choose an SMTP Service', 'blog-mailer'); ?></h3>
                <ul class="wpbm-service-list">
                    <li>
                        <strong>Gmail</strong> - <?php esc_html_e('Free for up to 500 emails/day. Good for testing.', 'blog-mailer'); ?>
                    </li>
                    <li>
                        <strong>SendGrid</strong> - <?php esc_html_e('Free tier: 100 emails/day. Professional option.', 'blog-mailer'); ?>
                    </li>
                    <li>
                        <strong>Mailgun</strong> - <?php esc_html_e('Free tier: 5,000 emails/month. Developer-friendly.', 'blog-mailer'); ?>
                    </li>
                    <li>
                        <strong>Amazon SES</strong> - <?php esc_html_e('Pay-as-you-go: $0.10 per 1,000 emails. Highly scalable.', 'blog-mailer'); ?>
                    </li>
                </ul>

                <h3><?php esc_html_e('Step 2: Install an SMTP Plugin', 'blog-mailer'); ?></h3>
                <p><?php esc_html_e('Choose one of these popular, free plugins:', 'blog-mailer'); ?></p>
                <div class="wpbm-plugin-grid">
                    <div class="wpbm-plugin-card">
                        <h4>WP Mail SMTP</h4>
                        <p><?php esc_html_e('Most popular, easy to configure', 'blog-mailer'); ?></p>
                        <a href="<?php echo esc_url(admin_url('plugin-install.php?s=WP+Mail+SMTP&tab=search&type=term')); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Install', 'blog-mailer'); ?>
                        </a>
                    </div>
                    <div class="wpbm-plugin-card">
                        <h4>Easy WP SMTP</h4>
                        <p><?php esc_html_e('Lightweight and simple', 'blog-mailer'); ?></p>
                        <a href="<?php echo esc_url(admin_url('plugin-install.php?s=Easy+WP+SMTP&tab=search&type=term')); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Install', 'blog-mailer'); ?>
                        </a>
                    </div>
                    <div class="wpbm-plugin-card">
                        <h4>Post SMTP Mailer</h4>
                        <p><?php esc_html_e('Advanced features, logging', 'blog-mailer'); ?></p>
                        <a href="<?php echo esc_url(admin_url('plugin-install.php?s=Post+SMTP&tab=search&type=term')); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Install', 'blog-mailer'); ?>
                        </a>
                    </div>
                </div>

                <h3><?php esc_html_e('Step 3: Configure the SMTP Plugin', 'blog-mailer'); ?></h3>
                <ol>
                    <li><?php esc_html_e('After installing, go to the plugin\'s settings page', 'blog-mailer'); ?></li>
                    <li><?php esc_html_e('Enter your SMTP server details (host, port, username, password)', 'blog-mailer'); ?></li>
                    <li><?php esc_html_e('Choose encryption type (usually TLS or SSL)', 'blog-mailer'); ?></li>
                    <li><?php esc_html_e('Send a test email from the SMTP plugin to verify', 'blog-mailer'); ?></li>
                </ol>

                <div class="wpbm-success-box">
                    <p><strong>✓ <?php esc_html_e('Done!', 'blog-mailer'); ?></strong> <?php esc_html_e('CN Blog Mailer will now use your SMTP configuration automatically.', 'blog-mailer'); ?></p>
                </div>
            </div>
        </div>

        <!-- Common Tasks -->
        <div class="wpbm-help-card">
            <div class="wpbm-help-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e('Common Tasks', 'blog-mailer'); ?>
                </h2>
            </div>
            <div class="wpbm-help-card-content">

                <div class="wpbm-task-section" id="subscription-form">
                    <h3><?php esc_html_e('Add Subscription Form to Your Site', 'blog-mailer'); ?></h3>
                    <p><?php esc_html_e('Let visitors subscribe to your newsletter. You can add the form in three ways:', 'blog-mailer'); ?></p>

                    <h4><?php esc_html_e('Option 1: Using Shortcode', 'blog-mailer'); ?></h4>
                    <p><?php esc_html_e('Add this shortcode to any post, page, or text widget:', 'blog-mailer'); ?></p>
                    <div class="wpbm-code-block">
                        <code>[wpbm_subscribe_form]</code>
                        <button class="button button-small wpbm-copy-btn" data-clipboard-text="[wpbm_subscribe_form]">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>

                    <h4><?php esc_html_e('Option 2: Using Block Editor', 'blog-mailer'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Edit a page or post in the Block Editor', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Click the + button to add a block', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Search for "Shortcode" and add the Shortcode block', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Paste the shortcode:', 'blog-mailer'); ?> <code>[wpbm_subscribe_form]</code></li>
                    </ol>

                    <h4><?php esc_html_e('Option 3: In Theme Template', 'blog-mailer'); ?></h4>
                    <p><?php esc_html_e('Add this PHP code to your theme files:', 'blog-mailer'); ?></p>
                    <div class="wpbm-code-block">
                        <code>&lt;?php echo do_shortcode('[wpbm_subscribe_form]'); ?&gt;</code>
                        <button class="button button-small wpbm-copy-btn" data-clipboard-text="<?php echo esc_attr('<?php echo do_shortcode(\'[wpbm_subscribe_form]\'); ?>'); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                </div>

                <hr>

                <div class="wpbm-task-section">
                    <h3><?php esc_html_e('Import Subscribers', 'blog-mailer'); ?></h3>
                    <p><?php esc_html_e('Already have subscribers? Import them from a CSV file:', 'blog-mailer'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Go to Subscribers page', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Look for the Import button at the top', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('CSV should have columns: email, first_name, last_name', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Upload and import', 'blog-mailer'); ?></li>
                    </ol>
                    <p class="description">
                        <strong><?php esc_html_e('Note:', 'blog-mailer'); ?></strong>
                        <?php esc_html_e('Free version limit: 250 subscribers. Upgrade for more.', 'blog-mailer'); ?>
                    </p>
                </div>

                <hr>

                <div class="wpbm-task-section">
                    <h3><?php esc_html_e('Customize Email Templates', 'blog-mailer'); ?></h3>
                    <p><?php esc_html_e('Personalize your newsletter appearance:', 'blog-mailer'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Go to Settings → Newsletter tab', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Scroll to "Template Customization"', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Change colors, fonts, greeting text, and intro text', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Use Send Test Email to preview changes', 'blog-mailer'); ?></li>
                    </ol>
                </div>

                <hr>

                <div class="wpbm-task-section">
                    <h3><?php esc_html_e('Schedule Automatic Newsletters', 'blog-mailer'); ?></h3>
                    <p><?php esc_html_e('Send newsletters automatically on a schedule:', 'blog-mailer'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Go to Settings → Newsletter tab', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Find "Schedule Settings"', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Choose frequency: Weekly, Bi-weekly, or Monthly', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Select day and time for sending', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Save settings', 'blog-mailer'); ?></li>
                    </ol>
                    <p class="description">
                        <?php esc_html_e('Newsletters will include your most recent posts published since the last send.', 'blog-mailer'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="wpbm-help-card">
            <div class="wpbm-help-card-header">
                <h2>
                    <span class="dashicons dashicons-sos"></span>
                    <?php esc_html_e('Troubleshooting', 'blog-mailer'); ?>
                </h2>
            </div>
            <div class="wpbm-help-card-content">

                <div class="wpbm-faq-item">
                    <h3><?php esc_html_e('❌ Emails are not sending', 'blog-mailer'); ?></h3>
                    <p><strong><?php esc_html_e('Solutions:', 'blog-mailer'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('Verify SMTP plugin is installed and configured', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Check Send Log page for error messages', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Send a test email from your SMTP plugin to verify configuration', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Check your SMTP service dashboard for quota limits', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Enable WordPress debug log (WP_DEBUG_LOG) to see detailed errors', 'blog-mailer'); ?></li>
                    </ul>
                </div>

                <hr>

                <div class="wpbm-faq-item">
                    <h3><?php esc_html_e('📬 Subscribers not receiving emails', 'blog-mailer'); ?></h3>
                    <p><strong><?php esc_html_e('Check:', 'blog-mailer'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('Subscriber status is "Confirmed" (not "Pending")', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Emails are not in spam folder', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('From Email matches your SMTP authenticated domain', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Check Send Log to confirm emails were sent', 'blog-mailer'); ?></li>
                    </ul>
                </div>

                <hr>

                <div class="wpbm-faq-item">
                    <h3><?php esc_html_e('🔗 Unsubscribe link not working', 'blog-mailer'); ?></h3>
                    <p><strong><?php esc_html_e('Solutions:', 'blog-mailer'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('Ensure permalinks are enabled (Settings → Permalinks)', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Try flushing permalinks: Go to Settings → Permalinks and click Save', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Check if theme or other plugins are interfering with query parameters', 'blog-mailer'); ?></li>
                    </ul>
                </div>

                <hr>

                <div class="wpbm-faq-item">
                    <h3><?php esc_html_e('📊 Reached subscriber limit', 'blog-mailer'); ?></h3>
                    <p><strong><?php esc_html_e('Free version:', 'blog-mailer'); ?></strong> <?php esc_html_e('Limited to 250 active subscribers', 'blog-mailer'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Unsubscribed users don\'t count toward limit', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Remove invalid/bounced emails to free up space', 'blog-mailer'); ?></li>
                        <li>
                            <a href="<?php echo esc_url(wpbm_get_upgrade_url()); ?>">
                                <?php esc_html_e('Upgrade to Starter (1,000) or Pro (10,000)', 'blog-mailer'); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <hr>

                <div class="wpbm-faq-item">
                    <h3><?php esc_html_e('📅 Scheduled newsletters not sending', 'blog-mailer'); ?></h3>
                    <p><strong><?php esc_html_e('Check:', 'blog-mailer'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('WordPress Cron is working (requires site visits or external cron)', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Schedule settings are saved correctly', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('There are new posts to send since last newsletter', 'blog-mailer'); ?></li>
                        <li><?php esc_html_e('Check Send Log for scheduled send attempts', 'blog-mailer'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Need More Help -->
        <div class="wpbm-help-card wpbm-support-card">
            <div class="wpbm-help-card-header">
                <h2>
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Need More Help?', 'blog-mailer'); ?>
                </h2>
            </div>
            <div class="wpbm-help-card-content">
                <p><?php esc_html_e('Still stuck? Here\'s how to get support:', 'blog-mailer'); ?></p>
                <div class="wpbm-support-options">
                    <div class="wpbm-support-option">
                        <span class="dashicons dashicons-format-chat"></span>
                        <h4><?php esc_html_e('WordPress.org Support Forum', 'blog-mailer'); ?></h4>
                        <p><?php esc_html_e('Free community support for all users', 'blog-mailer'); ?></p>
                        <a href="https://wordpress.org/support/plugin/cn-blog-mailer/" target="_blank" class="button button-secondary">
                            <?php esc_html_e('Visit Forum', 'blog-mailer'); ?>
                        </a>
                    </div>
                    <div class="wpbm-support-option">
                        <span class="dashicons dashicons-star-filled"></span>
                        <h4><?php esc_html_e('Upgrade for Priority Support', 'blog-mailer'); ?></h4>
                        <p><?php esc_html_e('Get direct email support with Starter or Pro plans', 'blog-mailer'); ?></p>
                        <a href="<?php echo esc_url(wpbm_get_upgrade_url()); ?>" class="button button-primary">
                            <?php esc_html_e('View Plans', 'blog-mailer'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wpbm-getting-started {
    max-width: 1200px;
}

.wpbm-intro {
    font-size: 16px;
    margin-bottom: 30px;
    color: #50575e;
}

.wpbm-help-grid {
    display: grid;
    gap: 20px;
}

.wpbm-help-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wpbm-help-card-header {
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    padding: 15px 20px;
}

.wpbm-help-card-header h2 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.wpbm-help-card-header .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.wpbm-help-card-content {
    padding: 20px;
}

.wpbm-checklist {
    list-style: none;
    counter-reset: checklist-counter;
    padding: 0;
}

.wpbm-checklist li {
    counter-increment: checklist-counter;
    position: relative;
    padding: 15px 15px 15px 50px;
    margin-bottom: 15px;
    background: #f6f7f7;
    border-left: 4px solid #2271b1;
    border-radius: 4px;
}

.wpbm-checklist li:before {
    content: counter(checklist-counter);
    position: absolute;
    left: 15px;
    top: 15px;
    background: #2271b1;
    color: #fff;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.wpbm-checklist li strong {
    display: block;
    margin-bottom: 5px;
    color: #1d2327;
}

.wpbm-checklist li p {
    margin: 5px 0 10px;
    color: #50575e;
    font-size: 14px;
}

.wpbm-info-box {
    background: #e7f5ff;
    border-left: 4px solid #2271b1;
    padding: 15px;
    margin-bottom: 20px;
}

.wpbm-success-box {
    background: #e8f5e9;
    border-left: 4px solid #46a049;
    padding: 15px;
    margin-top: 20px;
}

.wpbm-service-list {
    list-style: none;
    padding: 0;
}

.wpbm-service-list li {
    padding: 10px 0;
    border-bottom: 1px solid #dcdcde;
}

.wpbm-service-list li:last-child {
    border-bottom: none;
}

.wpbm-plugin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.wpbm-plugin-card {
    background: #f6f7f7;
    padding: 15px;
    border-radius: 4px;
    text-align: center;
    border: 1px solid #dcdcde;
}

.wpbm-plugin-card h4 {
    margin-top: 0;
    color: #1d2327;
}

.wpbm-plugin-card p {
    font-size: 13px;
    color: #50575e;
    margin-bottom: 15px;
}

.wpbm-code-block {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 15px 0;
    font-family: 'Courier New', monospace;
}

.wpbm-code-block code {
    background: transparent;
    color: #d4d4d4;
    padding: 0;
}

.wpbm-copy-btn {
    background: #2271b1 !important;
    border-color: #2271b1 !important;
    color: #fff !important;
    cursor: pointer;
}

.wpbm-copy-btn:hover {
    background: #135e96 !important;
}

.wpbm-copy-btn .dashicons {
    color: #fff;
}

.wpbm-task-section h3 {
    margin-top: 0;
    color: #1d2327;
}

.wpbm-task-section h4 {
    color: #2271b1;
    margin-top: 20px;
}

.wpbm-faq-item h3 {
    color: #1d2327;
    margin-top: 0;
}

.wpbm-support-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.wpbm-support-card .wpbm-help-card-header {
    background: rgba(255,255,255,0.1);
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.wpbm-support-card .wpbm-help-card-header h2 {
    color: #fff;
}

.wpbm-support-card .wpbm-help-card-content {
    color: #fff;
}

.wpbm-support-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.wpbm-support-option {
    background: rgba(255,255,255,0.1);
    padding: 20px;
    border-radius: 4px;
    text-align: center;
}

.wpbm-support-option .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #fff;
}

.wpbm-support-option h4 {
    color: #fff;
    margin: 15px 0 10px;
}

.wpbm-support-option p {
    color: rgba(255,255,255,0.9);
    margin-bottom: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Copy to clipboard functionality
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
            var originalHTML = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span>');

            setTimeout(function() {
                $btn.html(originalHTML);
            }, 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }

        $temp.remove();
    });

    // Smooth scroll to sections
    $('a[href^="#"]').on('click', function(e) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 50
            }, 500);
        }
    });
});
</script>
