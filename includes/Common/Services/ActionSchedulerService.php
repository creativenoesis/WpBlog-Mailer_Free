<?php
/**
 * Action Scheduler Service
 * Provides a clean interface for Action Scheduler functionality
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services;

/**
 * ActionSchedulerService Class
 *
 * Wraps Action Scheduler functionality for reliable cron job scheduling.
 * Action Scheduler is more reliable than WP-Cron because it:
 * - Uses database-backed queue (persistent)
 * - Automatic retry logic for failed jobs
 * - Smart async processing (doesn't block page loads)
 * - Batch processing to prevent timeouts
 * - Built-in admin UI for monitoring
 */
class ActionSchedulerService {

    /**
     * Action group for all WP Blog Mailer actions
     */
    const ACTION_GROUP = 'wpbm';

    /**
     * Schedule a recurring action
     *
     * @param int $timestamp Unix timestamp for first run
     * @param int $interval_in_seconds Interval between runs in seconds
     * @param string $hook Hook name to execute
     * @param array $args Arguments to pass to the hook (optional)
     * @param bool $unique If true, prevent duplicate scheduling (default: true)
     * @return int|bool Action ID on success, false on failure
     */
    public static function schedule_recurring_action($timestamp, $interval_in_seconds, $hook, $args = [], $unique = true) {
        if (!function_exists('as_schedule_recurring_action')) {
            error_log('WPBM: Action Scheduler not available');
            return false;
        }

        try {
            // If unique, check if already scheduled
            if ($unique && as_next_scheduled_action($hook, $args, self::ACTION_GROUP)) {
                return true; // Already scheduled
            }

            // Schedule the recurring action
            $action_id = as_schedule_recurring_action(
                $timestamp,
                $interval_in_seconds,
                $hook,
                $args,
                self::ACTION_GROUP
            );

            return $action_id;
        } catch (\Exception $e) {
            error_log('WPBM: Error scheduling recurring action: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule a single action
     *
     * @param int $timestamp Unix timestamp for when to run
     * @param string $hook Hook name to execute
     * @param array $args Arguments to pass to the hook (optional)
     * @param bool $unique If true, prevent duplicate scheduling (default: true)
     * @return int|bool Action ID on success, false on failure
     */
    public static function schedule_single_action($timestamp, $hook, $args = [], $unique = true) {
        if (!function_exists('as_schedule_single_action')) {
            error_log('WPBM: Action Scheduler not available');
            return false;
        }

        try {
            // If unique, check if already scheduled
            if ($unique && as_next_scheduled_action($hook, $args, self::ACTION_GROUP)) {
                return true; // Already scheduled
            }

            // Schedule the single action
            $action_id = as_schedule_single_action(
                $timestamp,
                $hook,
                $args,
                self::ACTION_GROUP
            );

            return $action_id;
        } catch (\Exception $e) {
            error_log('WPBM: Error scheduling single action: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unschedule a specific action
     *
     * @param string $hook Hook name to unschedule
     * @param array $args Arguments to match (optional)
     * @return void
     */
    public static function unschedule_action($hook, $args = []) {
        if (!function_exists('as_unschedule_all_actions')) {
            error_log('WPBM: Action Scheduler not available');
            return;
        }

        try {
            // Unschedule all matching actions
            as_unschedule_all_actions($hook, $args, self::ACTION_GROUP);
        } catch (\Exception $e) {
            error_log('WPBM: Error unscheduling action: ' . $e->getMessage());
        }
    }

    /**
     * Get next scheduled time for an action
     *
     * @param string $hook Hook name
     * @param array $args Arguments to match (optional)
     * @return int|false Unix timestamp of next run, or false if not scheduled
     */
    public static function get_next_scheduled($hook, $args = []) {
        if (!function_exists('as_next_scheduled_action')) {
            return false;
        }

        try {
            return as_next_scheduled_action($hook, $args, self::ACTION_GROUP);
        } catch (\Exception $e) {
            error_log('WPBM: Error getting next scheduled action: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an action is scheduled
     *
     * @param string $hook Hook name
     * @param array $args Arguments to match (optional)
     * @return bool True if scheduled, false otherwise
     */
    public static function is_scheduled($hook, $args = []) {
        return self::get_next_scheduled($hook, $args) !== false;
    }

    /**
     * Unschedule all WPBM actions
     *
     * @return void
     */
    public static function unschedule_all_wpbm_actions() {
        if (!function_exists('as_unschedule_all_actions')) {
            error_log('WPBM: Action Scheduler not available');
            return;
        }

        try {
            // Unschedule all actions in our group
            as_unschedule_all_actions(null, [], self::ACTION_GROUP);
        } catch (\Exception $e) {
            error_log('WPBM: Error unscheduling all actions: ' . $e->getMessage());
        }
    }

    /**
     * Get Action Scheduler admin URL
     *
     * @return string URL to Action Scheduler admin page
     */
    public static function get_admin_url() {
        return admin_url('tools.php?page=action-scheduler&s=wpbm&group=' . self::ACTION_GROUP);
    }

    /**
     * Check if Action Scheduler is available
     *
     * @return bool True if Action Scheduler is loaded and available
     */
    public static function is_available() {
        return function_exists('as_schedule_recurring_action');
    }
}
