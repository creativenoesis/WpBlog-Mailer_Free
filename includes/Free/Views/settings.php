<?php
/* phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are passed from controller */
/**
 * Settings Page View
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Get current settings
$settings = get_option('wpbm_settings', []);
$defaults = [
    'from_name' => get_bloginfo('name'),
    'from_email' => get_bloginfo('admin_email'),
    'subject_line' => '[{site_name}] New Posts: {date}',
    'posts_per_email' => 5,
    'post_types' => ['post'],
    'post_content_type' => 'excerpt',
    'excerpt_length' => 40,
    'schedule_frequency' => '', // Empty by default - user must choose to enable scheduling
    'schedule_day' => 'monday',
    'schedule_time' => '09:00',
    'double_optin' => false,
    'unsubscribe_text' => 'If you no longer wish to receive these emails, you can {unsubscribe_link}.',
    'enable_greeting' => true,
    'greeting_text' => 'Hi {first_name},',
    'intro_text' => 'Here are the latest posts from {site_name}:',
    'enable_site_link' => true,
    'template_primary_color' => '#667eea',
    'template_bg_color' => '#f7f7f7',
    'template_text_color' => '#333333',
    'template_link_color' => '#2271b1',
    'template_heading_font' => 'Arial, sans-serif',
    'template_body_font' => 'Georgia, serif',
];
$settings = wp_parse_args($settings, $defaults);

// Get current template selection
$selected_template = get_option('wpbm_template_type', 'basic');

// Handle form submission
if (isset($_POST['wpbm_save_settings']) && check_admin_referer('wpbm_settings_nonce', 'wpbm_settings_nonce')) {
    $new_settings = [
        'from_name' => isset($_POST['from_name']) ? sanitize_text_field(wp_unslash($_POST['from_name'])) : '',
        'from_email' => isset($_POST['from_email']) ? sanitize_email(wp_unslash($_POST['from_email'])) : '',
        'subject_line' => isset($_POST['subject_line']) ? sanitize_text_field(wp_unslash($_POST['subject_line'])) : '',
        'posts_per_email' => isset($_POST['posts_per_email']) ? absint($_POST['posts_per_email']) : 5,
        'post_types' => ['post'], // Fixed: Always use 'post' only - removed Post Types selector
        'post_content_type' => isset($_POST['post_content_type']) ? sanitize_text_field(wp_unslash($_POST['post_content_type'])) : 'excerpt',
        'excerpt_length' => isset($_POST['excerpt_length']) ? absint($_POST['excerpt_length']) : 40,
        'schedule_frequency' => isset($_POST['schedule_frequency']) ? sanitize_text_field(wp_unslash($_POST['schedule_frequency'])) : 'weekly',
        'schedule_day' => isset($_POST['schedule_day']) ? sanitize_text_field(wp_unslash($_POST['schedule_day'])) : 'Monday',
        'schedule_time' => isset($_POST['schedule_time']) ? sanitize_text_field(wp_unslash($_POST['schedule_time'])) : '09:00',
        'double_optin' => isset($_POST['double_optin']) ? 1 : 0,
        'unsubscribe_text' => isset($_POST['unsubscribe_text']) ? wp_kses_post(wp_unslash($_POST['unsubscribe_text'])) : '',
        'enable_greeting' => isset($_POST['enable_greeting']) ? 1 : 0,
        'greeting_text' => isset($_POST['greeting_text']) ? sanitize_text_field(wp_unslash($_POST['greeting_text'])) : '',
        'intro_text' => isset($_POST['intro_text']) ? sanitize_text_field(wp_unslash($_POST['intro_text'])) : '',
        'enable_site_link' => isset($_POST['enable_site_link']) ? 1 : 0,
    ];

    // Add custom frequency days for Starter/Pro
    if (isset($_POST['custom_frequency_days']) && (wpbm_is_starter() || wpbm_is_pro())) {
        $new_settings['custom_frequency_days'] = max(1, min(365, absint($_POST['custom_frequency_days'])));
    }

    // Add template customization settings
    if (isset($_POST['template_primary_color'])) {
        $new_settings['template_primary_color'] = sanitize_hex_color(wp_unslash($_POST['template_primary_color']));
    }
    if (isset($_POST['template_bg_color'])) {
        $new_settings['template_bg_color'] = sanitize_hex_color(wp_unslash($_POST['template_bg_color']));
    }
    if (isset($_POST['template_text_color'])) {
        $new_settings['template_text_color'] = sanitize_hex_color(wp_unslash($_POST['template_text_color']));
    }
    if (isset($_POST['template_link_color'])) {
        $new_settings['template_link_color'] = sanitize_hex_color(wp_unslash($_POST['template_link_color']));
    }
    if (isset($_POST['template_heading_font'])) {
        $new_settings['template_heading_font'] = sanitize_text_field(wp_unslash($_POST['template_heading_font']));
    }
    if (isset($_POST['template_body_font'])) {
        $new_settings['template_body_font'] = sanitize_text_field(wp_unslash($_POST['template_body_font']));
    }

    update_option('wpbm_settings', $new_settings);
    $settings = $new_settings;

    // Save template selection
    if (isset($_POST['template_type'])) {
        update_option('wpbm_template_type', sanitize_text_field(wp_unslash($_POST['template_type'])));
        $selected_template = sanitize_text_field(wp_unslash($_POST['template_type']));
    }

    // Only schedule cron if user has selected a frequency
    if (!empty($settings['schedule_frequency'])) {
        // Clear any existing scheduled events (both WP-Cron and Action Scheduler)
        $timestamp = wp_next_scheduled('wpbm_send_newsletter');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wpbm_send_newsletter');
        }
        wp_clear_scheduled_hook('wpbm_send_newsletter');

        if (class_exists('\WPBlogMailer\Common\Services\ActionSchedulerService')) {
            \WPBlogMailer\Common\Services\ActionSchedulerService::unschedule_action('wpbm_send_newsletter');
        }

        // Schedule new cron - Use WordPress timezone for accurate scheduling
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);

        // Parse the schedule time (HH:MM format)
        list($hour, $minute) = explode(':', $settings['schedule_time']);

        // Calculate next scheduled time based on frequency
        if ($settings['schedule_frequency'] === 'daily') {
            // For daily: schedule for today at the specified time, or tomorrow if time has passed
            $schedule_date = new DateTime('now', $timezone);
            $schedule_date->setTime((int)$hour, (int)$minute, 0);

            // If time has already passed today, schedule for tomorrow
            if ($schedule_date <= $now) {
                $schedule_date->modify('+1 day');
            }
            $interval_seconds = DAY_IN_SECONDS;
        } elseif ($settings['schedule_frequency'] === 'weekly' || $settings['schedule_frequency'] === 'biweekly') {
            // For weekly/biweekly: schedule for next occurrence of the specified day
            $schedule_date = new DateTime('next ' . $settings['schedule_day'], $timezone);
            $schedule_date->setTime((int)$hour, (int)$minute, 0);

            // If that day+time is today but hasn't passed yet, use today
            $test_today = new DateTime('now', $timezone);
            $test_today->setTime((int)$hour, (int)$minute, 0);
            if ($test_today->format('l') === ucfirst($settings['schedule_day']) && $test_today > $now) {
                $schedule_date = $test_today;
            }
            $interval_seconds = ($settings['schedule_frequency'] === 'weekly') ? WEEK_IN_SECONDS : (2 * WEEK_IN_SECONDS);
        } elseif ($settings['schedule_frequency'] === 'custom' && (wpbm_is_starter() || wpbm_is_pro())) {
            // For custom frequency (Starter/Pro only)
            $custom_days = isset($settings['custom_frequency_days']) ? (int)$settings['custom_frequency_days'] : 7;
            $schedule_date = new DateTime('now', $timezone);
            $schedule_date->setTime((int)$hour, (int)$minute, 0);

            // If time has already passed today, start from tomorrow
            if ($schedule_date <= $now) {
                $schedule_date->modify('+1 day');
            }
            $interval_seconds = $custom_days * DAY_IN_SECONDS;
        } else {
            // For monthly: schedule for first day of next month
            $schedule_date = new DateTime('first day of next month', $timezone);
            $schedule_date->setTime((int)$hour, (int)$minute, 0);
            $interval_seconds = 30 * DAY_IN_SECONDS; // Approximate month
        }

        $schedule_timestamp = $schedule_date->getTimestamp();

        // Use Action Scheduler if available, otherwise fallback to WP-Cron
        if (class_exists('\WPBlogMailer\Common\Services\ActionSchedulerService') &&
            \WPBlogMailer\Common\Services\ActionSchedulerService::is_available()) {
            // Schedule with Action Scheduler (more reliable)
            \WPBlogMailer\Common\Services\ActionSchedulerService::schedule_recurring_action(
                $schedule_timestamp,
                $interval_seconds,
                'wpbm_send_newsletter'
            );
        } else {
            // Fallback to WP-Cron
            if ($settings['schedule_frequency'] === 'custom' && (wpbm_is_starter() || wpbm_is_pro())) {
                $custom_days = isset($settings['custom_frequency_days']) ? (int)$settings['custom_frequency_days'] : 7;

                // Register custom interval dynamically
                add_filter('cron_schedules', function($schedules) use ($custom_days) {
                    $schedules['wpbm_custom'] = array(
                        'interval' => $custom_days * DAY_IN_SECONDS,
                    /* translators: %d: maximum number of posts to include */
                        'display'  => sprintf(esc_html__('Every %d Days', 'blog-mailer'), $custom_days)
                    );
                    return $schedules;
                });

                wp_schedule_event($schedule_timestamp, 'wpbm_custom', 'wpbm_send_newsletter');
            } else {
                wp_schedule_event($schedule_timestamp, $settings['schedule_frequency'], 'wpbm_send_newsletter');
            }

            // Trigger wp-cron to ensure it runs even on low-traffic sites
            spawn_cron();
        }
    } else {
        // If no schedule is set, clear any existing cron jobs
        $timestamp = wp_next_scheduled('wpbm_send_newsletter');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wpbm_send_newsletter');
        }
        wp_clear_scheduled_hook('wpbm_send_newsletter');

        if (class_exists('\WPBlogMailer\Common\Services\ActionSchedulerService')) {
            \WPBlogMailer\Common\Services\ActionSchedulerService::unschedule_action('wpbm_send_newsletter');
        }
    }

    // Show success message
    ?>
    <div class="notice notice-success" style="padding: 15px; margin: 20px 0;">
        <p style="font-size: 14px; margin: 0 0 10px 0;">
            <strong style="font-size: 16px;">✅ <?php esc_html_e('Settings saved successfully!', 'blog-mailer'); ?></strong>
        </p>
        <?php if (!empty($settings['schedule_frequency'])): ?>
        <p style="margin: 0 0 10px 0;">
            <strong><?php esc_html_e('Next scheduled send:', 'blog-mailer'); ?></strong>
            <?php echo esc_html(wp_date('F j, Y g:i a', $schedule_timestamp)); ?>
            (<?php echo esc_html(human_time_diff($schedule_timestamp, time())); ?> <?php esc_html_e('from now', 'blog-mailer'); ?>)
        </p>
        <p style="margin: 0 0 10px 0; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">
            <strong>⚠️ <?php esc_html_e('Important:', 'blog-mailer'); ?></strong>
            <?php esc_html_e('WordPress cron requires site traffic to run. If nobody visits at the scheduled time, the email will be delayed.', 'blog-mailer'); ?>
        </p>
        <p style="margin: 0;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-cron-status')); ?>" class="button button-primary">
                <?php esc_html_e('⚡ Test Cron Now', 'blog-mailer'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-cron-status')); ?>" class="button">
                <?php esc_html_e('View Cron Status', 'blog-mailer'); ?>
            </a>
        </p>
        <?php else: ?>
        <p style="margin: 0;">
            <?php esc_html_e('Automatic sending is currently disabled. To enable scheduled newsletters, go to the Schedule tab and select a frequency.', 'blog-mailer'); ?>
        </p>
        <?php endif; ?>
    </div>
    <?php
}

$tier = wpbm_get_plan_name();
?>

<div class="wrap wpbm-settings-page">
    <h1><?php esc_html_e('CN Blog Mailer Settings', 'blog-mailer'); ?></h1>

    <form method="post" action="" class="wpbm-settings-form">
        <?php wp_nonce_field('wpbm_settings_nonce', 'wpbm_settings_nonce'); ?>

        <div class="wpbm-settings-tabs">
            <button type="button" class="wpbm-tab-btn active" data-tab="general"><?php esc_html_e('General', 'blog-mailer'); ?></button>
            <button type="button" class="wpbm-tab-btn" data-tab="email"><?php esc_html_e('Email Settings', 'blog-mailer'); ?></button>
            <button type="button" class="wpbm-tab-btn" data-tab="schedule"><?php esc_html_e('Schedule', 'blog-mailer'); ?></button>
            <?php if (wpbm_is_starter() || wpbm_is_pro()): ?>
            <button type="button" class="wpbm-tab-btn" data-tab="advanced"><?php esc_html_e('Advanced', 'blog-mailer'); ?></button>
            <?php endif; ?>
        </div>

        <!-- General Tab -->
        <div class="wpbm-tab-content active" id="tab-general">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="from_name"><?php esc_html_e('From Name', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="from_name"
                                   id="from_name"
                                   value="<?php echo esc_attr($settings['from_name']); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description"><?php esc_html_e('The name subscribers will see emails from', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="from_email"><?php esc_html_e('From Email', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   name="from_email"
                                   id="from_email"
                                   value="<?php echo esc_attr($settings['from_email']); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description"><?php esc_html_e('The email address emails will be sent from', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"></th>
                        <td>
                            <div class="notice notice-info inline" style="margin: 0; padding: 12px;">
                                <p style="margin: 0.5em 0;">
                                    <strong><?php esc_html_e('📧 Email Delivery Tips:', 'blog-mailer'); ?></strong>
                                </p>
                                <p style="margin: 0.5em 0;">
                                    <?php esc_html_e('For reliable email delivery, we recommend configuring SMTP instead of using PHP mail().', 'blog-mailer'); ?>
                                </p>
                                <p style="margin: 0.5em 0;">
                                    <?php esc_html_e('Popular SMTP plugins:', 'blog-mailer'); ?>
                                </p>
                                <ul style="margin: 0.5em 0 0.5em 20px;">
                                    <li>
                                        <strong>WP Mail SMTP</strong> -
                                        <a href="<?php echo esc_url(admin_url('plugin-install.php?s=WP+Mail+SMTP&tab=search&type=term')); ?>" target="_blank">
                                            <?php esc_html_e('Install from WordPress', 'blog-mailer'); ?>
                                        </a>
                                    </li>
                                    <li>
                                        <strong>Easy WP SMTP</strong> -
                                        <a href="<?php echo esc_url(admin_url('plugin-install.php?s=Easy+WP+SMTP&tab=search&type=term')); ?>" target="_blank">
                                            <?php esc_html_e('Install from WordPress', 'blog-mailer'); ?>
                                        </a>
                                    </li>
                                    <li>
                                        <strong>Post SMTP Mailer</strong> -
                                        <a href="<?php echo esc_url(admin_url('plugin-install.php?s=Post+SMTP&tab=search&type=term')); ?>" target="_blank">
                                            <?php esc_html_e('Install from WordPress', 'blog-mailer'); ?>
                                        </a>
                                    </li>
                                </ul>
                                <p style="margin: 0.5em 0;">
                                    <?php esc_html_e('SMTP services like Gmail, SendGrid, Mailgun, or Amazon SES will significantly improve deliverability.', 'blog-mailer'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="posts_per_email"><?php esc_html_e('Posts Per Email', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   name="posts_per_email"
                                   id="posts_per_email"
                                   value="<?php echo esc_attr($settings['posts_per_email']); ?>"
                                   min="1"
                                   max="20"
                                   class="small-text">
                            <p class="description"><?php esc_html_e('Number of recent posts to include in each newsletter', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="post_content_type"><?php esc_html_e('Post Content', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <select name="post_content_type" id="post_content_type">
                                <option value="excerpt" <?php selected($settings['post_content_type'], 'excerpt'); ?>>
                                    <?php esc_html_e('Excerpt Only', 'blog-mailer'); ?>
                                </option>
                                <option value="full" <?php selected($settings['post_content_type'], 'full'); ?>>
                                    <?php esc_html_e('Full Post Content', 'blog-mailer'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose whether to send excerpt or full post content in emails', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr id="excerpt_length_row" style="<?php echo ($settings['post_content_type'] === 'full') ? 'display:none;' : ''; ?>">
                        <th scope="row">
                            <label for="excerpt_length"><?php esc_html_e('Excerpt Length', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   name="excerpt_length"
                                   id="excerpt_length"
                                   value="<?php echo esc_attr($settings['excerpt_length']); ?>"
                                   min="10"
                                   max="200"
                                   class="small-text">
                            <span><?php esc_html_e('words', 'blog-mailer'); ?></span>
                            <p class="description"><?php esc_html_e('Number of words to show in excerpt', 'blog-mailer'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Email Settings Tab -->
        <div class="wpbm-tab-content" id="tab-email">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="template_type"><?php esc_html_e('Email Template', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <select name="template_type" id="template_type" class="regular-text">
                                <option value="basic" <?php selected($selected_template, 'basic'); ?>>
                                    <?php esc_html_e('Basic Newsletter Template', 'blog-mailer'); ?>
                                </option>
                                <?php if (wpbm_is_pro()): ?>
                                    <optgroup label="<?php esc_attr_e('Professional Templates', 'blog-mailer'); ?>">
                                        <option value="library-modern" <?php selected($selected_template, 'library-modern'); ?>>
                                            <?php esc_html_e('Modern - Clean & Contemporary', 'blog-mailer'); ?>
                                        </option>
                                        <option value="library-classic" <?php selected($selected_template, 'library-classic'); ?>>
                                            <?php esc_html_e('Classic - Traditional Blog Style', 'blog-mailer'); ?>
                                        </option>
                                        <option value="library-minimal" <?php selected($selected_template, 'library-minimal'); ?>>
                                            <?php esc_html_e('Minimal - Simple & Elegant', 'blog-mailer'); ?>
                                        </option>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose the template design for your newsletter emails.', 'blog-mailer'); ?>
                                <?php if (!wpbm_is_pro()): ?>
                                    <br>
                                    <span class="wpbm-feature-badge"><?php esc_html_e('Upgrade to Pro for more templates', 'blog-mailer'); ?></span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="subject_line"><?php esc_html_e('Subject Line', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="subject_line"
                                   id="subject_line"
                                   value="<?php echo esc_attr($settings['subject_line']); ?>"
                                   class="large-text"
                                   required>
                            <p class="description">
                                <?php esc_html_e('Available tags:', 'blog-mailer'); ?>
                                <code>{site_name}</code>, <code>{date}</code>, <code>{post_count}</code>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="unsubscribe_text"><?php esc_html_e('Unsubscribe Text', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <textarea name="unsubscribe_text"
                                      id="unsubscribe_text"
                                      rows="3"
                                      class="large-text"><?php echo esc_textarea($settings['unsubscribe_text']); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Text shown at bottom of emails. Use', 'blog-mailer'); ?> <code>{unsubscribe_link}</code>
                                <?php esc_html_e('for the unsubscribe link.', 'blog-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enable_greeting"><?php esc_html_e('Subscriber Greeting', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="enable_greeting"
                                       id="enable_greeting"
                                       value="1"
                                       <?php checked($settings['enable_greeting'], 1); ?>>
                                <?php esc_html_e('Enable personalized greeting in newsletters', 'blog-mailer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Show a greeting at the beginning of each newsletter', 'blog-mailer'); ?>
                            </p>

                            <div style="margin-top: 15px;">
                                <label for="greeting_text">
                                    <?php esc_html_e('Greeting Text:', 'blog-mailer'); ?>
                                </label>
                                <input type="text"
                                       name="greeting_text"
                                       id="greeting_text"
                                       class="regular-text"
                                       value="<?php echo esc_attr($settings['greeting_text']); ?>"
                                       placeholder="Hi {first_name},">
                                <p class="description">
                                    <?php esc_html_e('Use', 'blog-mailer'); ?> <code>{first_name}</code> <?php esc_html_e('to include the subscriber\'s first name. If no name is available, it will show "there" instead.', 'blog-mailer'); ?>
                                    <br>
                                    <?php esc_html_e('Examples: "Hi {first_name}," or "Hello {first_name}!" or "Dear {first_name}," - customize in any language!', 'blog-mailer'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="intro_text"><?php esc_html_e('Introduction Text', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="intro_text"
                                   id="intro_text"
                                   class="large-text"
                                   value="<?php echo esc_attr($settings['intro_text']); ?>"
                                   placeholder="Here are the latest posts from {site_name}:">
                            <p class="description">
                                <?php esc_html_e('Text shown before the list of posts. Use', 'blog-mailer'); ?> <code>{site_name}</code> <?php esc_html_e('to include your site name.', 'blog-mailer'); ?>
                                <br>
                                <?php esc_html_e('Examples: "Here are the latest posts from {site_name}:" or "Check out what\'s new at {site_name}:" - customize in any language!', 'blog-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enable_site_link"><?php esc_html_e('Site Name Link', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="enable_site_link"
                                       id="enable_site_link"
                                       value="1"
                                       <?php checked($settings['enable_site_link'], 1); ?>>
                                <?php esc_html_e('Make site name heading clickable', 'blog-mailer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, the site name in the email header will link to your homepage', 'blog-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin-top: 30px; margin-bottom: 10px;">
                                <?php esc_html_e('Template Customization', 'blog-mailer'); ?>
                            </h3>
                            <p class="description" style="font-weight: normal;">
                                <?php esc_html_e('Customize the colors and fonts used in your email templates', 'blog-mailer'); ?>
                            </p>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_primary_color"><?php esc_html_e('Primary Color', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_primary_color"
                                   id="template_primary_color"
                                   value="<?php echo esc_attr($settings['template_primary_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#667eea">
                            <p class="description"><?php esc_html_e('Used for header, buttons, and accents', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_bg_color"><?php esc_html_e('Background Color', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_bg_color"
                                   id="template_bg_color"
                                   value="<?php echo esc_attr($settings['template_bg_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#f7f7f7">
                            <p class="description"><?php esc_html_e('Background color for the email', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_text_color"><?php esc_html_e('Text Color', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_text_color"
                                   id="template_text_color"
                                   value="<?php echo esc_attr($settings['template_text_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#333333">
                            <p class="description"><?php esc_html_e('Main text color', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_link_color"><?php esc_html_e('Link Color', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_link_color"
                                   id="template_link_color"
                                   value="<?php echo esc_attr($settings['template_link_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#2271b1">
                            <p class="description"><?php esc_html_e('Color for links and buttons', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_heading_font"><?php esc_html_e('Heading Font', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <select name="template_heading_font" id="template_heading_font" class="regular-text">
                                <option value="Arial, sans-serif" <?php selected($settings['template_heading_font'], 'Arial, sans-serif'); ?>>Arial</option>
                                <option value="Helvetica, sans-serif" <?php selected($settings['template_heading_font'], 'Helvetica, sans-serif'); ?>>Helvetica</option>
                                <option value="'Trebuchet MS', sans-serif" <?php selected($settings['template_heading_font'], "'Trebuchet MS', sans-serif"); ?>>Trebuchet MS</option>
                                <option value="'Courier New', monospace" <?php selected($settings['template_heading_font'], "'Courier New', monospace"); ?>>Courier New</option>
                                <option value="Georgia, serif" <?php selected($settings['template_heading_font'], 'Georgia, serif'); ?>>Georgia</option>
                                <option value="'Times New Roman', serif" <?php selected($settings['template_heading_font'], "'Times New Roman', serif"); ?>>Times New Roman</option>
                            </select>
                            <p class="description"><?php esc_html_e('Font family for headings', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_body_font"><?php esc_html_e('Body Font', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <select name="template_body_font" id="template_body_font" class="regular-text">
                                <option value="Georgia, serif" <?php selected($settings['template_body_font'], 'Georgia, serif'); ?>>Georgia</option>
                                <option value="'Times New Roman', serif" <?php selected($settings['template_body_font'], "'Times New Roman', serif"); ?>>Times New Roman</option>
                                <option value="Arial, sans-serif" <?php selected($settings['template_body_font'], 'Arial, sans-serif'); ?>>Arial</option>
                                <option value="Helvetica, sans-serif" <?php selected($settings['template_body_font'], 'Helvetica, sans-serif'); ?>>Helvetica</option>
                                <option value="'Trebuchet MS', sans-serif" <?php selected($settings['template_body_font'], "'Trebuchet MS', sans-serif"); ?>>Trebuchet MS</option>
                                <option value="Verdana, sans-serif" <?php selected($settings['template_body_font'], 'Verdana, sans-serif'); ?>>Verdana</option>
                            </select>
                            <p class="description"><?php esc_html_e('Font family for body text', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Test Email', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="wpbm-test-email" class="regular-text" placeholder="<?php esc_attr_e('your@email.com', 'blog-mailer'); ?>" value="<?php echo esc_attr(get_option('admin_email')); ?>">
                            <button type="button" id="wpbm-send-test-email" class="button button-secondary">
                                <?php esc_html_e('Send Test Email', 'blog-mailer'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('Send a test newsletter to see how it looks in your inbox', 'blog-mailer'); ?></p>
                            <div id="wpbm-test-email-result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Schedule Tab -->
        <div class="wpbm-tab-content" id="tab-schedule">

            <!-- IMPORTANT: WordPress Cron Warning -->
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #856404;">
                    <span class="dashicons dashicons-warning" style="color: #ffc107;"></span>
                    <?php esc_html_e('⚠️ Important: How WordPress Scheduling Works', 'blog-mailer'); ?>
                </h3>
                <p style="margin: 10px 0;">
                    <strong><?php esc_html_e('WordPress cron only runs when someone visits your site.', 'blog-mailer'); ?></strong>
                    <?php esc_html_e('If you schedule a newsletter for 9:00 AM and nobody visits your site at that time, the email will be sent when the next visitor arrives (could be minutes or hours later).', 'blog-mailer'); ?>
                </p>
                <p style="margin: 10px 0;">
                    <strong><?php esc_html_e('For low-traffic sites:', 'blog-mailer'); ?></strong>
                    <?php esc_html_e('You may experience delays or missed sends. We recommend using a real server cron job for reliable, time-accurate delivery.', 'blog-mailer'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-cron-status')); ?>" style="font-weight: 600;">
                        <?php esc_html_e('See setup instructions →', 'blog-mailer'); ?>
                    </a>
                </p>
                <p style="margin: 10px 0 0 0;">
                    <strong><?php esc_html_e('After saving:', 'blog-mailer'); ?></strong>
                    <?php esc_html_e('Go to Cron Status and click "Trigger Cron Now" to test your schedule.', 'blog-mailer'); ?>
                </p>
            </div>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="schedule_frequency"><?php esc_html_e('Send Frequency', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <select name="schedule_frequency" id="schedule_frequency">
                                <option value="" <?php selected($settings['schedule_frequency'], ''); ?>><?php esc_html_e('Disabled (No Auto-Sending)', 'blog-mailer'); ?></option>
                                <option value="daily" <?php selected($settings['schedule_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'blog-mailer'); ?></option>
                                <option value="weekly" <?php selected($settings['schedule_frequency'], 'weekly'); ?>><?php esc_html_e('Weekly', 'blog-mailer'); ?></option>
                                <option value="biweekly" <?php selected($settings['schedule_frequency'], 'biweekly'); ?>><?php esc_html_e('Bi-weekly (Every 2 Weeks)', 'blog-mailer'); ?></option>
                                <option value="monthly" <?php selected($settings['schedule_frequency'], 'monthly'); ?>><?php esc_html_e('Monthly', 'blog-mailer'); ?></option>
                                <?php if (wpbm_is_starter() || wpbm_is_pro()): ?>
                                <option value="custom" <?php selected($settings['schedule_frequency'], 'custom'); ?>>
                                    <?php esc_html_e('Custom Frequency', 'blog-mailer'); ?>
                                    <span class="wpbm-feature-badge"><?php esc_html_e('Starter+', 'blog-mailer'); ?></span>
                                </option>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('How often to send newsletters automatically', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <?php if (wpbm_is_starter() || wpbm_is_pro()): ?>
                    <tr class="wpbm-custom-frequency-row" style="display: none;">
                        <th scope="row">
                            <label for="custom_frequency_days"><?php esc_html_e('Custom Frequency (Days)', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   name="custom_frequency_days"
                                   id="custom_frequency_days"
                                   value="<?php echo esc_attr(isset($settings['custom_frequency_days']) ? $settings['custom_frequency_days'] : 7); ?>"
                                   min="1"
                                   max="365"
                                   class="small-text">
                            <span><?php esc_html_e('days', 'blog-mailer'); ?></span>
                            <p class="description">
                                <?php esc_html_e('Send newsletters every X days (1-365). Example: 3 = every 3 days, 10 = every 10 days', 'blog-mailer'); ?>
                            </p>
                            <span class="wpbm-feature-badge"><?php esc_html_e('Starter+ Feature', 'blog-mailer'); ?></span>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <tr class="wpbm-schedule-day-row">
                        <th scope="row">
                            <label for="schedule_day"><?php esc_html_e('Send Day', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <select name="schedule_day" id="schedule_day">
                                <option value="monday" <?php selected($settings['schedule_day'], 'monday'); ?>><?php esc_html_e('Monday', 'blog-mailer'); ?></option>
                                <option value="tuesday" <?php selected($settings['schedule_day'], 'tuesday'); ?>><?php esc_html_e('Tuesday', 'blog-mailer'); ?></option>
                                <option value="wednesday" <?php selected($settings['schedule_day'], 'wednesday'); ?>><?php esc_html_e('Wednesday', 'blog-mailer'); ?></option>
                                <option value="thursday" <?php selected($settings['schedule_day'], 'thursday'); ?>><?php esc_html_e('Thursday', 'blog-mailer'); ?></option>
                                <option value="friday" <?php selected($settings['schedule_day'], 'friday'); ?>><?php esc_html_e('Friday', 'blog-mailer'); ?></option>
                                <option value="saturday" <?php selected($settings['schedule_day'], 'saturday'); ?>><?php esc_html_e('Saturday', 'blog-mailer'); ?></option>
                                <option value="sunday" <?php selected($settings['schedule_day'], 'sunday'); ?>><?php esc_html_e('Sunday', 'blog-mailer'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Day of the week to send (for weekly schedule)', 'blog-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="schedule_time"><?php esc_html_e('Send Time', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="time"
                                   name="schedule_time"
                                   id="schedule_time"
                                   value="<?php echo esc_attr($settings['schedule_time']); ?>">
                            <p class="description">
                                <?php esc_html_e('Time of day to send (server timezone:', 'blog-mailer'); ?>
                                <strong><?php echo esc_html(wp_timezone_string()); ?></strong>)
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Next Scheduled Send', 'blog-mailer'); ?></th>
                        <td>
                            <?php
                            // Check Action Scheduler first (more reliable)
                            $next_scheduled = null;
                            if (class_exists('\WPBlogMailer\Common\Services\ActionSchedulerService') &&
                                \WPBlogMailer\Common\Services\ActionSchedulerService::is_available()) {
                                $next_scheduled = \WPBlogMailer\Common\Services\ActionSchedulerService::get_next_scheduled_time('wpbm_send_newsletter');
                            }

                            // Fallback to WP-Cron if Action Scheduler doesn't have it
                            if (!$next_scheduled) {
                                $next_scheduled = wp_next_scheduled('wpbm_send_newsletter');
                            }

                            if ($next_scheduled):
                            ?>
                                <p><strong><?php echo esc_html(wp_date('F j, Y g:i a', $next_scheduled)); ?></strong></p>
                                <p class="description">
                                    <?php
                                    /* translators: %s: human readable time difference (e.g., "2 hours", "3 days") */
                                    printf(
                                        esc_html__('In %s', 'blog-mailer'),
                                        esc_html(human_time_diff($next_scheduled, time()))
                                    );
                                    ?>
                                </p>
                            <?php else: ?>
                                <p><?php esc_html_e('No newsletter scheduled', 'blog-mailer'); ?></p>
                                <p class="description"><?php esc_html_e('Save your schedule settings above to schedule a newsletter', 'blog-mailer'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Advanced Tab (Starter+) -->
        <?php if (wpbm_is_starter() || wpbm_is_pro()): ?>
        <div class="wpbm-tab-content" id="tab-advanced">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="double_optin"><?php esc_html_e('Double Opt-in', 'blog-mailer'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="double_optin"
                                       id="double_optin"
                                       value="1"
                                       <?php checked($settings['double_optin'], 1); ?>>
                                <?php esc_html_e('Enable double opt-in', 'blog-mailer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Require subscribers to confirm their email address before being added to the list (GDPR compliant)', 'blog-mailer'); ?>
                            </p>
                            <span class="wpbm-feature-badge"><?php esc_html_e('Starter+ Feature', 'blog-mailer'); ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" name="wpbm_save_settings" class="button button-primary button-large">
                <?php esc_html_e('Save Settings', 'blog-mailer'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.wpbm-settings-page {
    max-width: 1000px;
}

.wpbm-settings-tabs {
    background: #fff;
    border-bottom: 1px solid #c3c4c7;
    margin: 20px 0 0 0;
    padding: 0;
}

.wpbm-tab-btn {
    background: none;
    border: none;
    padding: 15px 20px;
    cursor: pointer;
    font-size: 14px;
    color: #646970;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.wpbm-tab-btn:hover {
    color: #2271b1;
}

.wpbm-tab-btn.active {
    color: #2271b1;
    border-bottom-color: #2271b1;
    font-weight: 600;
}

.wpbm-tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #c3c4c7;
    border-top: none;
}

.wpbm-tab-content.active {
    display: block;
}

.wpbm-feature-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 10px;
}

.form-table th {
    width: 220px;
}

/* Preview Modal Styles */
.wpbm-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
}

.wpbm-modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 90%;
    max-width: 900px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}

.wpbm-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    line-height: 20px;
    cursor: pointer;
    transition: color 0.2s;
}

.wpbm-modal-close:hover,
.wpbm-modal-close:focus {
    color: #000;
}

.wpbm-preview-container {
    margin-top: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}

#wpbm-preview-iframe {
    width: 100%;
    height: 600px;
    border: none;
    background: #fff;
}

.wpbm-modal-content h2 {
    margin-top: 0;
    padding-right: 30px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize WordPress color pickers
    $('.wpbm-color-picker').wpColorPicker();

    // Tab switching
    $('.wpbm-tab-btn').on('click', function() {
        var tab = $(this).data('tab');

        $('.wpbm-tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.wpbm-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Show/hide schedule day and custom frequency based on frequency
    function toggleScheduleOptions() {
        var frequency = $('#schedule_frequency').val();

        // Show schedule day for weekly and biweekly
        if (frequency === 'weekly' || frequency === 'biweekly') {
            $('.wpbm-schedule-day-row').show();
        } else {
            $('.wpbm-schedule-day-row').hide();
        }

        // Show custom frequency fields for custom option (Starter/Pro only)
        if (frequency === 'custom') {
            $('.wpbm-custom-frequency-row').show();
        } else {
            $('.wpbm-custom-frequency-row').hide();
        }
    }

    $('#schedule_frequency').on('change', toggleScheduleOptions);
    toggleScheduleOptions();

    // Show/hide excerpt length based on content type
    function toggleExcerptLength() {
        var contentType = $('#post_content_type').val();
        if (contentType === 'excerpt') {
            $('#excerpt_length_row').show();
        } else {
            $('#excerpt_length_row').hide();
        }
    }

    $('#post_content_type').on('change', toggleExcerptLength);
    toggleExcerptLength();

    // Send test email functionality
    $('#wpbm-send-test-email').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        var testEmail = $('#wpbm-test-email').val().trim();
        var resultDiv = $('#wpbm-test-email-result');

        // Validate email
        if (!testEmail || !testEmail.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            resultDiv.html('<div class="notice notice-error inline"><p><?php esc_html_e('Please enter a valid email address.', 'blog-mailer'); ?></p></div>');
            return;
        }

        button.prop('disabled', true).text('<?php esc_html_e('Sending...', 'blog-mailer'); ?>');
        resultDiv.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpbm_send_test_email',
                nonce: '<?php echo esc_js(wp_create_nonce('wpbm_send_test_email')); ?>',
                test_email: testEmail
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error inline"><p>' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Error sending test email. Please try again.', 'blog-mailer'); ?>') + '</p></div>');
                }
            },
            error: function() {
                resultDiv.html('<div class="notice notice-error inline"><p><?php esc_html_e('Error sending test email. Please try again.', 'blog-mailer'); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
                setTimeout(function() {
                    resultDiv.fadeOut(function() {
                        resultDiv.html('').show();
                    });
                }, 5000);
            }
        });
    });
});
</script>
