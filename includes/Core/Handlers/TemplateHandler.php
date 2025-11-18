<?php
/**
 * Template Handler
 * Handles template preview AJAX requests
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core\Handlers;

use WPBlogMailer\Core\ServiceContainer;

defined('ABSPATH') || exit;

/**
 * TemplateHandler Class
 *
 * Responsible for:
 * - Handling template preview AJAX requests
 * - Generating preview HTML with sample data
 * - Processing template placeholders
 */
class TemplateHandler {

    /**
     * @var ServiceContainer
     */
    private $container;

    /**
     * Constructor
     *
     * @param ServiceContainer $container
     */
    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    /**
     * Handle template preview AJAX request
     */
    public function handle_template_preview() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpbm_preview_template')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Get preview settings from POST data
        $preview_settings = [
            'template_primary_color' => isset($_POST['template_primary_color']) ? sanitize_hex_color($_POST['template_primary_color']) : '#667eea',
            'template_bg_color' => isset($_POST['template_bg_color']) ? sanitize_hex_color($_POST['template_bg_color']) : '#f7f7f7',
            'template_text_color' => isset($_POST['template_text_color']) ? sanitize_hex_color($_POST['template_text_color']) : '#333333',
            'template_link_color' => isset($_POST['template_link_color']) ? sanitize_hex_color($_POST['template_link_color']) : '#2271b1',
            'template_heading_font' => isset($_POST['template_heading_font']) ? sanitize_text_field(wp_unslash($_POST['template_heading_font'])) : 'Arial, sans-serif',
            'template_body_font' => isset($_POST['template_body_font']) ? sanitize_text_field(wp_unslash($_POST['template_body_font'])) : 'Georgia, serif',
            'post_content_type' => isset($_POST['post_content_type']) ? sanitize_text_field(wp_unslash($_POST['post_content_type'])) : 'excerpt',
            'excerpt_length' => isset($_POST['excerpt_length']) ? absint($_POST['excerpt_length']) : 40,
        ];

        // Get sample posts for preview
        $sample_posts = get_posts([
            'numberposts' => 2,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        // If no posts exist, create a sample post object
        if (empty($sample_posts)) {
            $sample_post = new \stdClass();
            $sample_post->ID = 0;
            $sample_post->post_title = 'Sample Blog Post Title';
            $sample_post->post_content = 'This is a sample blog post excerpt. Your actual newsletter will contain real posts from your blog with images, formatting, and full content.';
            $sample_post->post_excerpt = 'This is a sample blog post excerpt.';
            $sample_post->post_author = get_current_user_id();
            $sample_post->post_date = current_time('mysql');
            $sample_posts = [$sample_post];
        }

        // Create a sample subscriber
        $sample_subscriber = (object) [
            'id' => 0,
            'email' => 'preview@example.com',
            'first_name' => 'Preview',
            'last_name' => 'User',
            'unsubscribe_key' => 'preview_key_123',
        ];

        // Get template type from POST
        $template_type = isset($_POST['template_type']) ? sanitize_text_field(wp_unslash($_POST['template_type'])) : 'basic';

        // Generate preview HTML
        try {
            $html = $this->generate_preview_html($template_type, $sample_posts, $sample_subscriber, $preview_settings);
            wp_send_json_success(['html' => $html]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Error generating preview: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate preview HTML based on template type
     *
     * @param string $template_type Template type identifier
     * @param array $posts Sample posts
     * @param object $subscriber Sample subscriber
     * @param array $settings Preview settings
     * @return string Generated HTML
     * @throws \Exception
     */
    private function generate_preview_html($template_type, $posts, $subscriber, $settings) {
        $template_data = [
            'posts' => $posts,
            'heading' => 'Latest Posts from ' . get_bloginfo('name'),
            'subscriber' => $subscriber,
        ];

        $html = '';

        // Determine which template service to use based on template type
        if ($template_type === 'basic' || empty($template_type)) {
            // Use basic template
            $template_service = $this->container->get(\WPBlogMailer\Free\Services\BasicTemplateService::class);
            $html = $template_service->render($template_data);
        } elseif (strpos($template_type, 'library-') === 0) {
            // Use template library (Pro feature)
            if (!wpbm_is_pro()) {
                throw new \Exception('Template library requires Pro plan');
            }

            $library_service = $this->container->get(\WPBlogMailer\Pro\Services\TemplateLibraryService::class);
            $template_id = str_replace('library-', '', $template_type);

            // Get template HTML with customization applied
            $template_html = $library_service->get_template_content($template_id, $settings);

            // Process placeholders
            $html = $this->process_template_placeholders($template_html, $template_data, $settings);
        } elseif (strpos($template_type, 'custom-') === 0) {
            // Use custom template (Pro feature)
            if (!wpbm_is_pro()) {
                throw new \Exception('Custom templates require Pro plan');
            }

            $custom_service = $this->container->get(\WPBlogMailer\Pro\Services\CustomTemplateService::class);
            $template_id = str_replace('custom-', '', $template_type);
            $custom_template = $custom_service->get_template($template_id);

            if ($custom_template) {
                $html = $this->process_template_placeholders($custom_template->content, $template_data, $settings);
            } else {
                throw new \Exception('Custom template not found');
            }
        } else {
            // Fallback to basic
            $template_service = $this->container->get(\WPBlogMailer\Free\Services\BasicTemplateService::class);
            $html = $template_service->render($template_data);
        }

        return $html;
    }

    /**
     * Process template placeholders for preview
     *
     * @param string $content Template HTML content
     * @param array $data Template data
     * @param array $settings Preview settings
     * @return string Processed HTML
     */
    private function process_template_placeholders($content, $data, $settings) {
        // Basic placeholder replacement
        $replacements = [
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url(),
            '{{heading}}' => isset($data['heading']) ? $data['heading'] : '',
            '{{subscriber_name}}' => isset($data['subscriber']->first_name) ? $data['subscriber']->first_name : 'Preview',
            '{{subscriber_email}}' => isset($data['subscriber']->email) ? $data['subscriber']->email : 'preview@example.com',
            '{{unsubscribe_url}}' => '#unsubscribe',
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        // Process posts loop if present
        if (isset($data['posts']) && is_array($data['posts']) && !empty($data['posts'])) {
            $posts_html = $this->generate_posts_html($data['posts'], $settings);
            $content = str_replace('{{posts_loop}}', $posts_html, $content);
        }

        return $content;
    }

    /**
     * Generate posts HTML for preview
     *
     * @param array $posts Array of post objects
     * @param array $settings Preview settings
     * @return string Posts HTML
     */
    private function generate_posts_html($posts, $settings) {
        $posts_html = '';
        $excerpt_length = isset($settings['excerpt_length']) ? intval($settings['excerpt_length']) : 40;
        $post_content_type = isset($settings['post_content_type']) ? $settings['post_content_type'] : 'excerpt';

        foreach ($posts as $post) {
            if ($post_content_type === 'full') {
                // Show FULL post content with HTML
                $content = $post->post_content;

                // Strip WordPress block comments but keep HTML
                $content = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $content);

                // Apply the content (keeps safe HTML)
                $post_text = wp_kses_post($content);

                // Make images responsive with inline styles for email compatibility
                $post_text = preg_replace_callback(
                    '/<img([^>]*)>/i',
                    function($matches) {
                        $attributes = $matches[1];
                        // Remove width/height attributes
                        $attributes = preg_replace('/\s*width=(["\'])[^"\']*\1/i', '', $attributes);
                        $attributes = preg_replace('/\s*height=(["\'])[^"\']*\1/i', '', $attributes);
                        // Add inline responsive styles
                        if (!preg_match('/style=/i', $attributes)) {
                            $attributes .= ' style="max-width: 100%; height: auto; display: block;"';
                        }
                        return '<img' . $attributes . '>';
                    },
                    $post_text
                );
            } else {
                // Show excerpt only
                $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;

                // Strip WordPress block comments
                $excerpt = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $excerpt);

                // Strip all HTML tags for clean excerpt
                $excerpt = wp_strip_all_tags($excerpt);

                // Trim to desired length
                $post_text = wp_trim_words($excerpt, $excerpt_length, '...');
            }

            $posts_html .= '<div class="post-item" style="margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #e0e0e0;">';
            $posts_html .= '<h2 style="margin: 0 0 10px 0; font-size: 22px;"><a href="' . esc_url(get_permalink($post->ID)) . '" style="color: #2c3e50; text-decoration: none;">' . esc_html($post->post_title) . '</a></h2>';
            $posts_html .= '<p style="color: #666; font-size: 14px; margin-bottom: 10px;">By ' . esc_html(get_the_author_meta('display_name', $post->post_author)) . ' on ' . esc_html(get_the_date('F j, Y', $post)) . '</p>';
            $posts_html .= '<div style="color: #444; line-height: 1.6; margin-bottom: 15px;">' . $post_text . '</div>';
            $posts_html .= '<a href="' . esc_url(get_permalink($post->ID)) . '" style="display: inline-block; padding: 10px 20px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px;">Read More</a>';
            $posts_html .= '</div>';
        }

        return $posts_html;
    }
}
