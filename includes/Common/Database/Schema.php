<?php
// FILE: includes/Common/Database/Schema.php

namespace WPBlogMailer\Common\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Plugin Database Schema Creation and Updates.
 */
class Schema {

    // Define table names as constants
    const TABLE_SUBSCRIBERS = 'wpbm_subscribers';
    const TABLE_SEND_HISTORY = 'wpbm_send_history'; // Added based on BasicAnalytics
    const TABLE_ANALYTICS_LOG = 'wpbm_analytics_log'; // Added based on AdvancedAnalytics
    const TABLE_ANALYTICS_LINKS = 'wpbm_analytics_links'; // Added based on AdvancedAnalytics
    const TABLE_TEMPLATES = 'wpbm_templates'; // Added for custom templates storage
    const TABLE_EMAIL_QUEUE = 'wpbm_email_queue'; // Email queue for background processing
    const TABLE_SEND_LOG = 'wpbm_send_log'; // Detailed send log for error tracking
    const TABLE_CRON_LOG = 'wpbm_cron_log'; // Cron execution log for monitoring
    const TABLE_TAGS = 'wpbm_tags'; // Tags for subscriber segmentation
    const TABLE_SUBSCRIBER_TAGS = 'wpbm_subscriber_tags'; // Many-to-many relationship between subscribers and tags
    const TABLE_AB_TESTS = 'wpbm_ab_tests'; // A/B testing campaigns
    const TABLE_AB_VARIANTS = 'wpbm_ab_variants'; // A/B test variants
    const TABLE_AB_RESULTS = 'wpbm_ab_results'; // A/B test results per subscriber

    /**
     * Create or update the necessary database tables.
     *
     * This method should be called during plugin activation.
     */
    public function create_tables() {
        // **FIX 1: Make WordPress DB object available**
        global $wpdb;

        // **FIX 2: Ensure dbDelta function is available**
        // This file is often not loaded by default during activation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // **FIX 3: Get charset/collate AFTER $wpdb is defined**
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix; // Use $wpdb->prefix

        // --- Subscribers Table ---
        $table_name_subscribers = $table_prefix . self::TABLE_SUBSCRIBERS;
        $sql_subscribers = "CREATE TABLE {$table_name_subscribers} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            first_name varchar(50) DEFAULT '' NOT NULL,
            last_name varchar(50) DEFAULT '' NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            unsubscribe_key varchar(64) DEFAULT '' NOT NULL,
            timezone varchar(50) DEFAULT 'UTC' NOT NULL,
            engagement_score int(11) DEFAULT 0 NOT NULL,
            lifetime_value decimal(10,2) DEFAULT 0.00 NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY unsubscribe_key (unsubscribe_key),
            KEY engagement_score (engagement_score),
            KEY timezone (timezone)
        ) {$charset_collate};";
        // **FIX 4: Call dbDelta() correctly**
        dbDelta( $sql_subscribers );

        // --- Send History Table (For Starter Tier Analytics) ---
        $table_name_history = $table_prefix . self::TABLE_SEND_HISTORY;
        $sql_history = "CREATE TABLE {$table_name_history} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email_subject varchar(255) DEFAULT '' NOT NULL,
            recipient_count int(11) DEFAULT 0 NOT NULL,
            sent_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status varchar(20) DEFAULT 'completed' NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";
        dbDelta( $sql_history );

        // --- Analytics Log Table (For Pro Tier) ---
        $table_name_analytics_log = $table_prefix . self::TABLE_ANALYTICS_LOG;
        $sql_analytics_log = "CREATE TABLE {$table_name_analytics_log} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id bigint(20) UNSIGNED NOT NULL,
            subscriber_id mediumint(9) NOT NULL,
            event_type varchar(10) NOT NULL,
            link_id bigint(20) UNSIGNED DEFAULT NULL,
            event_timestamp datetime NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            country varchar(2) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            device_type varchar(20) DEFAULT NULL,
            browser varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY email_id (email_id),
            KEY subscriber_id (subscriber_id),
            KEY event_type (event_type),
            KEY link_id (link_id),
            KEY country (country),
            KEY device_type (device_type)
        ) {$charset_collate};";
        dbDelta( $sql_analytics_log );


        // --- Analytics Links Table (For Pro Tier Click Tracking) ---
        $table_name_analytics_links = $table_prefix . self::TABLE_ANALYTICS_LINKS;
        $sql_analytics_links = "CREATE TABLE {$table_name_analytics_links} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            original_url text NOT NULL,
            url_hash char(32) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash)
        ) {$charset_collate};";
        dbDelta( $sql_analytics_links );

        // --- Templates Table (For Pro Tier Custom Templates) ---
        $table_name_templates = $table_prefix . self::TABLE_TEMPLATES;
        $sql_templates = "CREATE TABLE {$table_name_templates} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            content longtext NOT NULL,
            template_json longtext DEFAULT NULL,
            template_type varchar(50) DEFAULT 'custom' NOT NULL,
            category varchar(50) DEFAULT 'newsletter' NOT NULL,
            thumbnail_url varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY template_type (template_type),
            KEY category (category)
        ) {$charset_collate};";
        dbDelta( $sql_templates );

        // --- Email Queue Table (For Background Email Processing) ---
        $table_name_queue = $table_prefix . self::TABLE_EMAIL_QUEUE;
        $sql_queue = "CREATE TABLE {$table_name_queue} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient_email varchar(100) NOT NULL,
            subscriber_id mediumint(9) DEFAULT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            headers text DEFAULT NULL,
            template_type varchar(50) DEFAULT 'basic' NOT NULL,
            campaign_type varchar(50) DEFAULT 'newsletter' NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            priority int(11) DEFAULT 5 NOT NULL,
            attempts int(11) DEFAULT 0 NOT NULL,
            max_attempts int(11) DEFAULT 3 NOT NULL,
            error_message text DEFAULT NULL,
            scheduled_for datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_for (scheduled_for),
            KEY priority (priority),
            KEY subscriber_id (subscriber_id),
            KEY campaign_type (campaign_type)
        ) {$charset_collate};";
        dbDelta( $sql_queue );

        // --- Send Log Table (Detailed email send tracking with errors) ---
        $table_name_send_log = $table_prefix . self::TABLE_SEND_LOG;
        $sql_send_log = "CREATE TABLE {$table_name_send_log} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient_email varchar(100) NOT NULL,
            recipient_name varchar(100) DEFAULT '' NOT NULL,
            subscriber_id mediumint(9) DEFAULT NULL,
            subject varchar(255) NOT NULL,
            template_type varchar(50) DEFAULT 'basic' NOT NULL,
            campaign_type varchar(50) DEFAULT 'newsletter' NOT NULL,
            status varchar(20) DEFAULT 'success' NOT NULL,
            error_message text DEFAULT NULL,
            sent_at datetime NOT NULL,
            queue_id bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY recipient_email (recipient_email),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY sent_at (sent_at),
            KEY campaign_type (campaign_type),
            KEY queue_id (queue_id)
        ) {$charset_collate};";
        dbDelta( $sql_send_log );

        // --- Cron Log Table (For cron execution monitoring and health checks) ---
        $table_name_cron_log = $table_prefix . self::TABLE_CRON_LOG;
        $sql_cron_log = "CREATE TABLE {$table_name_cron_log} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            hook varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            message text DEFAULT NULL,
            details longtext DEFAULT NULL,
            executed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY hook (hook),
            KEY status (status),
            KEY executed_at (executed_at)
        ) {$charset_collate};";
        dbDelta( $sql_cron_log );

        // --- Tags Table (For subscriber segmentation - Pro feature) ---
        $table_name_tags = $table_prefix . self::TABLE_TAGS;
        $sql_tags = "CREATE TABLE {$table_name_tags} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text DEFAULT NULL,
            color varchar(7) DEFAULT '#0073aa' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY name (name)
        ) {$charset_collate};";
        dbDelta( $sql_tags );

        // --- Subscriber Tags Table (Many-to-many relationship - Pro feature) ---
        $table_name_subscriber_tags = $table_prefix . self::TABLE_SUBSCRIBER_TAGS;
        $sql_subscriber_tags = "CREATE TABLE {$table_name_subscriber_tags} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id mediumint(9) NOT NULL,
            tag_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY subscriber_tag (subscriber_id, tag_id),
            KEY subscriber_id (subscriber_id),
            KEY tag_id (tag_id)
        ) {$charset_collate};";
        dbDelta( $sql_subscriber_tags );

        // --- A/B Tests Table (For Pro Tier A/B Testing) ---
        $table_name_ab_tests = $table_prefix . self::TABLE_AB_TESTS;
        $sql_ab_tests = "CREATE TABLE {$table_name_ab_tests} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            status varchar(20) DEFAULT 'draft' NOT NULL,
            test_type varchar(50) DEFAULT 'subject' NOT NULL,
            winner_variant_id bigint(20) UNSIGNED DEFAULT NULL,
            winner_criteria varchar(50) DEFAULT 'open_rate' NOT NULL,
            sample_size_percentage int(11) DEFAULT 50 NOT NULL,
            auto_select_winner tinyint(1) DEFAULT 1 NOT NULL,
            test_duration_hours int(11) DEFAULT 24 NOT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY test_type (test_type),
            KEY started_at (started_at)
        ) {$charset_collate};";
        dbDelta( $sql_ab_tests );

        // --- A/B Variants Table (For Pro Tier A/B Testing) ---
        $table_name_ab_variants = $table_prefix . self::TABLE_AB_VARIANTS;
        $sql_ab_variants = "CREATE TABLE {$table_name_ab_variants} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id bigint(20) UNSIGNED NOT NULL,
            variant_name varchar(50) NOT NULL,
            subject varchar(255) DEFAULT NULL,
            content longtext DEFAULT NULL,
            template_id bigint(20) UNSIGNED DEFAULT NULL,
            from_name varchar(100) DEFAULT NULL,
            weight_percentage int(11) DEFAULT 50 NOT NULL,
            sent_count int(11) DEFAULT 0 NOT NULL,
            open_count int(11) DEFAULT 0 NOT NULL,
            click_count int(11) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY test_id (test_id),
            KEY variant_name (variant_name)
        ) {$charset_collate};";
        dbDelta( $sql_ab_variants );

        // --- A/B Results Table (For Pro Tier A/B Testing) ---
        $table_name_ab_results = $table_prefix . self::TABLE_AB_RESULTS;
        $sql_ab_results = "CREATE TABLE {$table_name_ab_results} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id bigint(20) UNSIGNED NOT NULL,
            variant_id bigint(20) UNSIGNED NOT NULL,
            subscriber_id mediumint(9) NOT NULL,
            email_id bigint(20) UNSIGNED DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY test_subscriber (test_id, subscriber_id),
            KEY variant_id (variant_id),
            KEY subscriber_id (subscriber_id),
            KEY email_id (email_id)
        ) {$charset_collate};";
        dbDelta( $sql_ab_results );

        // Store a version number for future migrations
        update_option('wpbm_db_version', '3.0'); // Update this when schema changes
    }

    /**
     * Optional: Method to handle database schema updates/migrations.
     * This could compare the stored version with the current plugin version.
     */
    public function check_updates() {
        $current_db_version = get_option('wpbm_db_version', '1.0'); // Default to 1.0 if not set

        // If DB version is less than 3.0, run create_tables to add new tables/columns
        if (version_compare($current_db_version, '3.0', '<')) {
            $this->create_tables();
        }

        // Handle incremental updates for existing installations
        if (version_compare($current_db_version, '2.6', '<')) {
            global $wpdb;
            $table_name = $wpdb->prefix . self::TABLE_SUBSCRIBERS;

            // Add new fields to subscribers table if they don't exist
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'UTC' NOT NULL;");
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN IF NOT EXISTS engagement_score INT(11) DEFAULT 0 NOT NULL;");
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN IF NOT EXISTS lifetime_value DECIMAL(10,2) DEFAULT 0.00 NOT NULL;");
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX IF NOT EXISTS engagement_score (engagement_score);");
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX IF NOT EXISTS timezone (timezone);");
        }

        if (version_compare($current_db_version, '2.7', '<')) {
            global $wpdb;
            $analytics_table = $wpdb->prefix . self::TABLE_ANALYTICS_LOG;

            // Add geographic and device tracking to analytics
            $wpdb->query("ALTER TABLE {$analytics_table} ADD COLUMN IF NOT EXISTS country VARCHAR(2) DEFAULT NULL;");
            $wpdb->query("ALTER TABLE {$analytics_table} ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL;");
            $wpdb->query("ALTER TABLE {$analytics_table} ADD COLUMN IF NOT EXISTS device_type VARCHAR(20) DEFAULT NULL;");
            $wpdb->query("ALTER TABLE {$analytics_table} ADD COLUMN IF NOT EXISTS browser VARCHAR(50) DEFAULT NULL;");
            $wpdb->query("ALTER TABLE {$analytics_table} ADD INDEX IF NOT EXISTS country (country);");
            $wpdb->query("ALTER TABLE {$analytics_table} ADD INDEX IF NOT EXISTS device_type (device_type);");
        }

        if (version_compare($current_db_version, '2.8', '<')) {
            global $wpdb;
            $templates_table = $wpdb->prefix . self::TABLE_TEMPLATES;

            // Add visual builder fields to templates
            $wpdb->query("ALTER TABLE {$templates_table} ADD COLUMN IF NOT EXISTS template_json LONGTEXT DEFAULT NULL;");
            $wpdb->query("ALTER TABLE {$templates_table} ADD COLUMN IF NOT EXISTS thumbnail_url VARCHAR(255) DEFAULT NULL;");
        }
    }

} // End Class