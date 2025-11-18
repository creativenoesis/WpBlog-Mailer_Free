<?php
/**
 * Plugin Constants
 * Central location for all magic numbers and configuration values
 *
 * @package WPBlogMailer
 * @since 2.0.1
 */

namespace WPBlogMailer\Common;

defined('ABSPATH') || exit;

/**
 * Constants Class
 *
 * Defines all plugin-wide constants to eliminate magic numbers
 * and improve code maintainability.
 */
final class Constants {

    // ==========================================
    // SUBSCRIBER LIMITS
    // ==========================================

    /**
     * Maximum number of subscribers allowed on free tier
     */
    const FREE_SUBSCRIBER_LIMIT = 250;

    /**
     * Unlimited subscribers constant (for Starter and Pro)
     */
    const UNLIMITED_SUBSCRIBERS = -1;

    // ==========================================
    // EMAIL QUEUE & BATCH PROCESSING
    // ==========================================

    /**
     * Rate limit: Emails per batch for free tier
     */
    const RATE_LIMIT_FREE = 50;

    /**
     * Rate limit: Emails per batch for starter tier
     */
    const RATE_LIMIT_STARTER = 100;

    /**
     * Rate limit: Emails per batch for pro tier
     */
    const RATE_LIMIT_PRO = 500;

    /**
     * Delay between individual email sends (microseconds)
     * 100,000 microseconds = 0.1 seconds
     */
    const EMAIL_SEND_DELAY_MICROSECONDS = 100000;

    /**
     * Maximum number of queue processing iterations for "Send Now"
     * Prevents infinite loops in case of issues
     */
    const MAX_QUEUE_PROCESSING_ITERATIONS = 20;

    /**
     * Maximum execution time for newsletter sending (seconds)
     * 600 seconds = 10 minutes
     */
    const NEWSLETTER_MAX_EXECUTION_TIME = 600;

    // ==========================================
    // RETRY & BACKOFF CONFIGURATION
    // ==========================================

    /**
     * Maximum number of retry attempts for failed email sends
     */
    const MAX_EMAIL_RETRY_ATTEMPTS = 3;

    /**
     * Retry delay intervals (minutes) for exponential backoff
     * Array index corresponds to attempt number (0-indexed)
     */
    const RETRY_DELAY_MINUTES = [5, 15, 30];

    // ==========================================
    // CLEANUP & MAINTENANCE
    // ==========================================

    /**
     * Number of days to keep completed/failed emails in queue before cleanup
     */
    const QUEUE_CLEANUP_DAYS = 30;

    /**
     * Number of days to keep old export files before deletion
     */
    const EXPORT_CLEANUP_DAYS = 7;

    /**
     * Number of days to keep old send log entries
     */
    const SEND_LOG_RETENTION_DAYS = 90;

    // ==========================================
    // ENGAGEMENT SCORING (Pro)
    // ==========================================

    /**
     * Batch size for engagement score updates
     */
    const ENGAGEMENT_UPDATE_BATCH_SIZE = 100;

    /**
     * Points awarded for email open
     */
    const ENGAGEMENT_POINTS_OPEN = 1;

    /**
     * Points awarded for email click
     */
    const ENGAGEMENT_POINTS_CLICK = 3;

    /**
     * Points deducted for unsubscribe
     */
    const ENGAGEMENT_POINTS_UNSUBSCRIBE = -10;

    // ==========================================
    // TEMPLATE & CONTENT
    // ==========================================

    /**
     * Default excerpt length (words) for newsletter posts
     */
    const DEFAULT_EXCERPT_LENGTH = 40;

    /**
     * Default number of posts to include in newsletter
     */
    const DEFAULT_POSTS_PER_EMAIL = 5;

    /**
     * Maximum length for unsubscribe key generation
     */
    const UNSUBSCRIBE_KEY_LENGTH = 32;

    // ==========================================
    // PAGINATION & DISPLAY
    // ==========================================

    /**
     * Default number of subscribers per page in admin
     */
    const SUBSCRIBERS_PER_PAGE = 20;

    /**
     * Default number of send log entries per page
     */
    const SEND_LOG_PER_PAGE = 50;

    /**
     * Maximum number of items to export at once
     */
    const EXPORT_BATCH_SIZE = 1000;

    // ==========================================
    // ANALYTICS & REPORTING
    // ==========================================

    /**
     * Default number of days for analytics dashboard stats
     */
    const ANALYTICS_DEFAULT_DAYS = 30;

    /**
     * Number of top links to show in analytics
     */
    const ANALYTICS_TOP_LINKS_COUNT = 10;

    /**
     * Number of top subscribers to show in analytics
     */
    const ANALYTICS_TOP_SUBSCRIBERS_COUNT = 10;

    // ==========================================
    // A/B TESTING (Pro)
    // ==========================================

    /**
     * Minimum sample size for A/B test variant
     */
    const AB_TEST_MIN_SAMPLE_SIZE = 100;

    /**
     * Default A/B test duration (hours)
     */
    const AB_TEST_DEFAULT_DURATION_HOURS = 24;

    /**
     * Confidence level for A/B test winner selection (percentage)
     */
    const AB_TEST_CONFIDENCE_LEVEL = 95;

    // ==========================================
    // TRACKING & SECURITY
    // ==========================================

    /**
     * Tracking pixel width (pixels)
     */
    const TRACKING_PIXEL_WIDTH = 1;

    /**
     * Tracking pixel height (pixels)
     */
    const TRACKING_PIXEL_HEIGHT = 1;

    /**
     * Rate limit for tracking requests per IP (per minute)
     */
    const TRACKING_RATE_LIMIT_PER_MINUTE = 60;

    /**
     * Tracking signature algorithm
     */
    const TRACKING_SIGNATURE_ALGORITHM = 'sha256';

    // ==========================================
    // CRON & SCHEDULING
    // ==========================================

    /**
     * Weekly report send day (1 = Monday, 7 = Sunday)
     */
    const WEEKLY_REPORT_DAY = 1; // Monday

    /**
     * Weekly report send hour (0-23)
     */
    const WEEKLY_REPORT_HOUR = 9; // 9 AM

    /**
     * Engagement score update interval (hours)
     */
    const ENGAGEMENT_UPDATE_INTERVAL_HOURS = 24;

    /**
     * A/B test check interval (hours)
     */
    const AB_TEST_CHECK_INTERVAL_HOURS = 1;

    // ==========================================
    // FILE UPLOAD & VALIDATION
    // ==========================================

    /**
     * Maximum CSV file size for subscriber import (bytes)
     * 2 MB = 2 * 1024 * 1024
     */
    const MAX_IMPORT_FILE_SIZE = 2097152;

    /**
     * Allowed MIME types for CSV import
     */
    const ALLOWED_IMPORT_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
    ];

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get retry delay for a given attempt number
     *
     * @param int $attempt_number Attempt number (1-indexed)
     * @return int Delay in minutes
     */
    public static function get_retry_delay($attempt_number) {
        $index = $attempt_number - 1;
        return isset(self::RETRY_DELAY_MINUTES[$index])
            ? self::RETRY_DELAY_MINUTES[$index]
            : 30; // Default to 30 minutes for attempts beyond defined array
    }

    /**
     * Get subscriber limit for current tier
     *
     * @return int Number of subscribers allowed (-1 for unlimited)
     */
    public static function get_subscriber_limit() {
        if (function_exists('wpbm_is_pro') && wpbm_is_pro()) {
            return self::UNLIMITED_SUBSCRIBERS;
        }

        if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
            return self::UNLIMITED_SUBSCRIBERS;
        }

        return self::FREE_SUBSCRIBER_LIMIT;
    }

    /**
     * Get rate limit for current tier
     *
     * @return int Number of emails per batch
     */
    public static function get_rate_limit() {
        if (function_exists('wpbm_is_pro') && wpbm_is_pro()) {
            return self::RATE_LIMIT_PRO;
        }

        if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
            return self::RATE_LIMIT_STARTER;
        }

        return self::RATE_LIMIT_FREE;
    }

    /**
     * Prevent instantiation
     */
    private function __construct() {}

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
