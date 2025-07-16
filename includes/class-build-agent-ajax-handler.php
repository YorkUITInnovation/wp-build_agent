<?php
/**
 * Build Agent AJAX Handler Class
 *
 * Handles all AJAX requests for the Build Agent plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Build_Agent_AJAX_Handler {

    private $ai_generator;
    private $block_renderer;

    /**
     * Initialize the AJAX handler
     */
    public function __construct() {
        // Ensure Block Renderer class is loaded
        if (!class_exists('Build_Agent_Block_Renderer')) {
            require_once BUILD_AGENT_PLUGIN_PATH . 'includes/class-build-agent-block-renderer.php';
        }

        $this->ai_generator = new Build_Agent_AI_Generator();
        $this->block_renderer = new Build_Agent_Block_Renderer();

        add_action('wp_ajax_build_agent_generate', array($this, 'handle_generate_request'));
        add_action('wp_ajax_nopriv_build_agent_generate', array($this, 'handle_generate_request'));
    }

    /**
     * Handle block generation AJAX request
     */
    public function handle_generate_request() {
        // Increase PHP execution time and memory limit for AI generation
        $timeout = (int) get_option('build_agent_timeout', 30);
        $extended_timeout = max($timeout + 30, 90); // Add 30 seconds buffer, minimum 90 seconds

        ini_set('max_execution_time', $extended_timeout);
        ini_set('memory_limit', '256M');

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'build_agent_nonce')) {
            wp_die(__('Security check failed.', 'build-agent'));
        }

        $prompt = sanitize_textarea_field($_POST['prompt']);
        $categories = isset($_POST['categories']) ? array_map('sanitize_text_field', $_POST['categories']) : array('text', 'design');

        if (empty($prompt)) {
            wp_send_json_error(__('Prompt is required.', 'build-agent'));
        }

        // Limit to 2 categories
        $categories = array_slice($categories, 0, 2);

        $blocks = $this->ai_generator->generate_blocks_from_prompt($prompt, $categories);

        if (is_wp_error($blocks)) {
            $error_message = $blocks->get_error_message();

            // Check if it's a timeout error and provide helpful guidance
            if (strpos($error_message, 'cURL error 28') !== false || strpos($error_message, 'Operation timed out') !== false) {
                $timeout_setting = get_option('build_agent_timeout', 30);
                $error_message .= sprintf(
                    __(' This appears to be a timeout issue. Current timeout setting: %d seconds. Try: 1) Simplifying your request, 2) Increasing the timeout in Build Agent settings to 60-90 seconds, or 3) Using fewer block categories.', 'build-agent'),
                    $timeout_setting
                );
            }

            wp_send_json_error($error_message);
        }

        wp_send_json_success(array(
            'blocks' => $blocks,
            'preview' => $this->block_renderer->render_blocks_preview($blocks)
        ));
    }
}
