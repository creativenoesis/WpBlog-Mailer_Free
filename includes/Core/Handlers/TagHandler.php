<?php
/**
 * Tag Handler
 * Handles tag management AJAX requests (Pro feature)
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Handlers;

use WPBlogMailer\Common\Models\Tag;
// Note: SegmentService loaded conditionally to avoid errors in free version

defined('ABSPATH') || exit;

/**
 * TagHandler Class
 *
 * Responsible for:
 * - Handling tag CRUD operations via AJAX
 * - Managing tag-to-subscriber relationships
 * - Pro feature only
 */
class TagHandler {

    /**
     * AJAX handler to get tag data
     */
    public function handle_get_tag() {
        check_ajax_referer('wpbm_tag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'blog-mailer'));
        }

        $tag_id = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;

        if (!$tag_id) {
            wp_send_json_error(esc_html__('Invalid tag ID', 'blog-mailer'));
        }

        $tag_model = new Tag();
        $tag = $tag_model->find($tag_id);

        if (!$tag) {
            wp_send_json_error(esc_html__('Tag not found', 'blog-mailer'));
        }

        wp_send_json_success($tag);
    }

    /**
     * AJAX handler to save (create or update) tag
     */
    public function handle_save_tag() {
        check_ajax_referer('wpbm_tag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'blog-mailer'));
        }

        $tag_id = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
        $tag_name = isset($_POST['tag_name']) ? sanitize_text_field(wp_unslash($_POST['tag_name'])) : '';
        $tag_slug = isset($_POST['tag_slug']) ? sanitize_title(wp_unslash($_POST['tag_slug'])) : '';
        $tag_description = isset($_POST['tag_description']) ? sanitize_textarea_field($_POST['tag_description']) : '';
        $tag_color = isset($_POST['tag_color']) ? sanitize_hex_color($_POST['tag_color']) : '#0073aa';

        if (empty($tag_name)) {
            wp_send_json_error(esc_html__('Tag name is required', 'blog-mailer'));
        }

        $segment_service = new SegmentService();

        $tag_data = array(
            'name' => $tag_name,
            'slug' => $tag_slug,
            'description' => $tag_description,
            'color' => $tag_color,
        );

        if ($tag_id) {
            // Update existing tag
            $result = $segment_service->update_tag($tag_id, $tag_data);
        } else {
            // Create new tag
            $result = $segment_service->create_tag($tag_data);
        }

        if ($result) {
            wp_send_json_success(array('tag_id' => $tag_id ? $tag_id : $result));
        } else {
            wp_send_json_error(esc_html__('Failed to save tag', 'blog-mailer'));
        }
    }

    /**
     * AJAX handler to delete tag
     */
    public function handle_delete_tag() {
        check_ajax_referer('wpbm_tag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'blog-mailer'));
        }

        $tag_id = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;

        if (!$tag_id) {
            wp_send_json_error(esc_html__('Invalid tag ID', 'blog-mailer'));
        }

        $segment_service = new SegmentService();
        $result = $segment_service->delete_tag($tag_id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(esc_html__('Failed to delete tag', 'blog-mailer'));
        }
    }
}
