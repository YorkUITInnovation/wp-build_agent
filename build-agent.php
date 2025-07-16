<?php
/*
Plugin Name: Build Agent - WordPress Page Design AI
Plugin URI: https://github.com/patrickthibaudeau/build-agent
Description: AI-powered WordPress page design agent that uses Azure OpenAI to generate WordPress blocks based on user prompts.
Version: 1.0.0
Author: Patrick Thibaudeau
Author URI: https://patrickthibaudeau.com
License: GPL2
Text Domain: build-agent
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BUILD_AGENT_VERSION', '1.0.0');
define('BUILD_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BUILD_AGENT_PLUGIN_PATH', plugin_dir_path(__FILE__));

class BuildAgent {

    private $azure_endpoint;
    private $azure_api_key;
    private $azure_api_version;

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_build_agent_generate', array($this, 'handle_generate_request'));
        add_action('wp_ajax_nopriv_build_agent_generate', array($this, 'handle_generate_request'));
        add_action('wp_ajax_build_agent_test_connection', array($this, 'handle_test_connection'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('admin_init', array($this, 'register_settings'));

        // Load Azure credentials
        $this->azure_endpoint = get_option('build_agent_azure_endpoint', '');
        $this->azure_api_key = get_option('build_agent_azure_api_key', '');
        $this->azure_api_version = get_option('build_agent_azure_api_version', '2024-02-15-preview');
    }

    public function init() {
        // Register custom post type if needed
        load_plugin_textdomain('build-agent', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_admin_menu() {
        add_options_page(
            __('Build Agent Settings', 'build-agent'),
            __('Build Agent', 'build-agent'),
            'manage_options',
            'build-agent-settings',
            array($this, 'admin_page')
        );
    }

    public function register_settings() {
        register_setting('build_agent_settings', 'build_agent_azure_endpoint');
        register_setting('build_agent_settings', 'build_agent_azure_api_key');
        register_setting('build_agent_settings', 'build_agent_azure_deployment_name');
        register_setting('build_agent_settings', 'build_agent_azure_api_version');

        // AI Generation Parameters
        register_setting('build_agent_settings', 'build_agent_max_tokens');
        register_setting('build_agent_settings', 'build_agent_temperature');
        register_setting('build_agent_settings', 'build_agent_top_p');
        register_setting('build_agent_settings', 'build_agent_frequency_penalty');
        register_setting('build_agent_settings', 'build_agent_presence_penalty');
        register_setting('build_agent_settings', 'build_agent_timeout');
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('build_agent_settings');
                do_settings_sections('build_agent_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="build_agent_azure_endpoint"><?php _e('Azure OpenAI Endpoint', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="build_agent_azure_endpoint" name="build_agent_azure_endpoint"
                                   value="<?php echo esc_attr(get_option('build_agent_azure_endpoint')); ?>"
                                   class="regular-text" placeholder="https://your-resource.openai.azure.com/" />
                            <p class="description"><?php _e('Your Azure OpenAI resource endpoint URL.', 'build-agent'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_azure_api_key"><?php _e('Azure OpenAI API Key', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="build_agent_azure_api_key" name="build_agent_azure_api_key"
                                   value="<?php echo esc_attr(get_option('build_agent_azure_api_key')); ?>"
                                   class="regular-text" />
                            <p class="description"><?php _e('Your Azure OpenAI API key.', 'build-agent'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_azure_deployment_name"><?php _e('Deployment Name', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="build_agent_azure_deployment_name" name="build_agent_azure_deployment_name"
                                   value="<?php echo esc_attr(get_option('build_agent_azure_deployment_name')); ?>"
                                   class="regular-text" placeholder="gpt-4" />
                            <p class="description"><?php _e('Your Azure OpenAI deployment name (e.g., gpt-4, gpt-35-turbo).', 'build-agent'); ?></p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_azure_api_version"><?php _e('API Version', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="build_agent_azure_api_version" name="build_agent_azure_api_version"
                                   value="<?php echo esc_attr(get_option('build_agent_azure_api_version', '2024-02-15-preview')); ?>"
                                   class="regular-text" placeholder="2024-02-15-preview" />
                            <p class="description"><?php _e('The Azure OpenAI API version to use.', 'build-agent'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e('AI Generation Parameters', 'build-agent'); ?></h2>
                <p><?php _e('Fine-tune the AI behavior for block generation. These settings control how the AI responds to your prompts.', 'build-agent'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="build_agent_max_tokens"><?php _e('Max Tokens', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="build_agent_max_tokens" name="build_agent_max_tokens"
                                   value="<?php echo esc_attr(get_option('build_agent_max_tokens', '4000')); ?>"
                                   class="small-text" min="100" max="8000" step="100" />
                            <p class="description">
                                <?php _e('Maximum number of tokens (words/parts of words) the AI can generate. Higher values allow longer responses but cost more. Recommended: 2000-4000 for complex layouts.', 'build-agent'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_temperature"><?php _e('Temperature', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="build_agent_temperature" name="build_agent_temperature"
                                   value="<?php echo esc_attr(get_option('build_agent_temperature', '0.3')); ?>"
                                   class="small-text" min="0" max="2" step="0.1" />
                            <p class="description">
                                <?php _e('Controls creativity vs consistency. Lower values (0.1-0.3) = more predictable, structured responses. Higher values (0.7-1.0) = more creative, varied responses. Recommended: 0.3 for reliable block generation.', 'build-agent'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_top_p"><?php _e('Top P (Nucleus Sampling)', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="build_agent_top_p" name="build_agent_top_p"
                                   value="<?php echo esc_attr(get_option('build_agent_top_p', '0.95')); ?>"
                                   class="small-text" min="0.1" max="1" step="0.05" />
                            <p class="description">
                                <?php _e('Alternative to temperature. Controls diversity by considering only the top P% of probable next words. Lower values = more focused responses. Default: 0.95. Use either temperature OR top_p, not both.', 'build-agent'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_frequency_penalty"><?php _e('Frequency Penalty', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="build_agent_frequency_penalty" name="build_agent_frequency_penalty"
                                   value="<?php echo esc_attr(get_option('build_agent_frequency_penalty', '0')); ?>"
                                   class="small-text" min="-2" max="2" step="0.1" />
                            <p class="description">
                                <?php _e('Reduces repetition based on how often words appear. Positive values (0.1-1.0) discourage repetition. Negative values encourage it. Default: 0 (no penalty). Recommended: 0-0.3 for varied content.', 'build-agent'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_presence_penalty"><?php _e('Presence Penalty', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="build_agent_presence_penalty" name="build_agent_presence_penalty"
                                   value="<?php echo esc_attr(get_option('build_agent_presence_penalty', '0')); ?>"
                                   class="small-text" min="-2" max="2" step="0.1" />
                            <p class="description">
                                <?php _e('Encourages discussing new topics by penalizing words that have already appeared. Positive values (0.1-1.0) encourage variety. Default: 0. Recommended: 0-0.5 for diverse block types.', 'build-agent'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="build_agent_timeout"><?php _e('Request Timeout (seconds)', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="build_agent_timeout" name="build_agent_timeout"
                                   value="<?php echo esc_attr(get_option('build_agent_timeout', '30')); ?>"
                                   class="small-text" min="10" max="120" step="5" />
                            <p class="description">
                                <?php _e('How long to wait for the AI to respond before timing out. Higher values allow for more complex responses but may make the interface feel slower. Recommended: 30-60 seconds.', 'build-agent'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div class="notice notice-info inline">
                    <p><strong><?php _e('Quick Settings Guide:', 'build-agent'); ?></strong></p>
                    <ul>
                        <li><strong><?php _e('For consistent, reliable blocks:', 'build-agent'); ?></strong> <?php _e('Temperature: 0.1-0.3, Frequency Penalty: 0', 'build-agent'); ?></li>
                        <li><strong><?php _e('For creative, varied designs:', 'build-agent'); ?></strong> <?php _e('Temperature: 0.7-1.0, Presence Penalty: 0.3-0.5', 'build-agent'); ?></li>
                        <li><strong><?php _e('For simple layouts:', 'build-agent'); ?></strong> <?php _e('Max Tokens: 1000-2000', 'build-agent'); ?></li>
                        <li><strong><?php _e('For complex multi-section pages:', 'build-agent'); ?></strong> <?php _e('Max Tokens: 3000-4000', 'build-agent'); ?></li>
                    </ul>
                </div>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php _e('Connection Test', 'build-agent'); ?></h2>
            <p><?php _e('Test your Azure OpenAI connection to ensure all settings are correct.', 'build-agent'); ?></p>

            <div id="build-agent-test-section">
                <button type="button" id="build-agent-test-connection" class="button button-secondary">
                    <?php _e('Test Connection', 'build-agent'); ?>
                </button>

                <div id="build-agent-test-loading" style="display: none; margin-top: 10px;">
                    <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                    <?php _e('Testing connection...', 'build-agent'); ?>
                </div>

                <div id="build-agent-test-result" style="margin-top: 15px; display: none;">
                </div>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    $('#build-agent-test-connection').on('click', function() {
                        var button = $(this);
                        var loading = $('#build-agent-test-loading');
                        var result = $('#build-agent-test-result');

                        // Get current form values
                        var endpoint = $('#build_agent_azure_endpoint').val();
                        var apiKey = $('#build_agent_azure_api_key').val();
                        var deploymentName = $('#build_agent_azure_deployment_name').val();
                        var apiVersion = $('#build_agent_azure_api_version').val();

                        button.prop('disabled', true);
                        loading.show();
                        result.hide();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'build_agent_test_connection',
                                endpoint: endpoint,
                                api_key: apiKey,
                                deployment_name: deploymentName,
                                api_version: apiVersion,
                                nonce: '<?php echo wp_create_nonce('build_agent_test_nonce'); ?>'
                            },
                            success: function(response) {
                                loading.hide();
                                button.prop('disabled', false);

                                if (response.success) {
                                    result.html('<div class="notice notice-success"><p><strong><?php _e('Success!', 'build-agent'); ?></strong> ' + response.data.message + '</p></div>');
                                } else {
                                    result.html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'build-agent'); ?></strong> ' + response.data + '</p></div>');
                                }
                                result.show();
                            },
                            error: function() {
                                loading.hide();
                                button.prop('disabled', false);
                                result.html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'build-agent'); ?></strong> <?php _e('Failed to test connection.', 'build-agent'); ?></p></div>');
                                result.show();
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

    public function add_meta_boxes() {
        add_meta_box(
            'build-agent-generator',
            __('AI Page Builder', 'build-agent'),
            array($this, 'meta_box_callback'),
            'page',
            'normal',
            'high'
        );

        add_meta_box(
            'build-agent-generator',
            __('AI Page Builder', 'build-agent'),
            array($this, 'meta_box_callback'),
            'post',
            'normal',
            'high'
        );
    }

    public function meta_box_callback($post) {
        wp_nonce_field('build_agent_meta_box', 'build_agent_meta_box_nonce');
        ?>
        <div id="build-agent-container">
            <div class="build-agent-input-section">
                <label for="build-agent-prompt">
                    <strong><?php _e('Describe what you want to build:', 'build-agent'); ?></strong>
                </label>
                <textarea id="build-agent-prompt" rows="4" style="width: 100%; margin: 10px 0;"
                          placeholder="<?php _e('e.g., Create a hero section with a call-to-action button, followed by a three-column feature grid...', 'build-agent'); ?>"></textarea>

                <div class="build-agent-buttons">
                    <button type="button" id="build-agent-generate" class="button button-primary">
                        <?php _e('Generate Blocks', 'build-agent'); ?>
                    </button>
                    <button type="button" id="build-agent-insert" class="button button-secondary" style="display: none;">
                        <?php _e('Insert into Page', 'build-agent'); ?>
                    </button>
                </div>
            </div>

            <div id="build-agent-loading" style="display: none;">
                <p><?php _e('Generating blocks...', 'build-agent'); ?></p>
                <div class="spinner is-active"></div>
            </div>

            <div id="build-agent-preview" style="display: none;">
                <h4><?php _e('Generated Blocks Preview:', 'build-agent'); ?></h4>
                <div id="build-agent-blocks-preview"></div>
            </div>

            <div id="build-agent-error" style="display: none; color: #d63638;">
            </div>
        </div>

        <style>
            #build-agent-container {
                padding: 15px;
            }
            .build-agent-input-section {
                margin-bottom: 20px;
            }
            .build-agent-buttons {
                margin-top: 10px;
            }
            .build-agent-buttons button {
                margin-right: 10px;
            }
            #build-agent-blocks-preview {
                border: 1px solid #ddd;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
                max-height: 400px;
                overflow-y: auto;
            }
        </style>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            wp_enqueue_style(
                'build-agent-admin',
                BUILD_AGENT_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                BUILD_AGENT_VERSION
            );

            wp_enqueue_script(
                'build-agent-admin',
                BUILD_AGENT_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                BUILD_AGENT_VERSION,
                true
            );

            wp_localize_script('build-agent-admin', 'buildAgent', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('build_agent_nonce'),
                'strings' => array(
                    'error' => __('An error occurred while generating blocks.', 'build-agent'),
                    'noPrompt' => __('Please enter a description first.', 'build-agent'),
                    'generating' => __('Generating blocks...', 'build-agent'),
                    'inserting' => __('Inserting blocks...', 'build-agent'),
                )
            ));
        }
    }

    public function enqueue_frontend_scripts() {
        // Frontend scripts if needed
    }

    public function handle_generate_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'build_agent_nonce')) {
            wp_die(__('Security check failed.', 'build-agent'));
        }

        $prompt = sanitize_textarea_field($_POST['prompt']);

        if (empty($prompt)) {
            wp_send_json_error(__('Prompt is required.', 'build-agent'));
        }

        $blocks = $this->generate_blocks_from_prompt($prompt);

        if (is_wp_error($blocks)) {
            wp_send_json_error($blocks->get_error_message());
        }

        wp_send_json_success(array(
            'blocks' => $blocks,
            'preview' => $this->render_blocks_preview($blocks)
        ));
    }

    private function generate_blocks_from_prompt($prompt) {
        if (empty($this->azure_endpoint) || empty($this->azure_api_key)) {
            return new WP_Error('config_error', __('Azure OpenAI credentials not configured.', 'build-agent'));
        }

        $deployment_name = get_option('build_agent_azure_deployment_name', 'gpt-4');

        $system_prompt = $this->get_system_prompt();

        $url = rtrim($this->azure_endpoint, '/') . "/openai/deployments/{$deployment_name}/chat/completions?api-version={$this->azure_api_version}";

        $body = array(
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => (int) get_option('build_agent_max_tokens', 4000),
            'temperature' => (float) get_option('build_agent_temperature', 0.3),
            'top_p' => (float) get_option('build_agent_top_p', 0.95),
            'frequency_penalty' => (float) get_option('build_agent_frequency_penalty', 0),
            'presence_penalty' => (float) get_option('build_agent_presence_penalty', 0)
        );

        $timeout = (int) get_option('build_agent_timeout', 30);

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api-key' => $this->azure_api_key
            ),
            'body' => json_encode($body),
            'timeout' => $timeout
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_error', __('Failed to connect to Azure OpenAI.', 'build-agent'));
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            // Log the full response for debugging
            error_log('Build Agent Debug - Invalid API response: ' . $response_body);
            return new WP_Error('api_error', __('Invalid response from Azure OpenAI.', 'build-agent'));
        }

        $content = $data['choices'][0]['message']['content'];

        // Log the raw AI response for debugging
        error_log('Build Agent Debug - Raw AI response: ' . $content);

        // Try multiple methods to extract JSON from the response
        $json_content = $this->extract_json_from_response($content);

        if ($json_content === false) {
            error_log('Build Agent Debug - Failed to extract JSON from response');
            return new WP_Error('parse_error', sprintf(
                __('Failed to extract JSON from AI response. Raw response: %s', 'build-agent'),
                substr($content, 0, 200) . '...'
            ));
        }

        // Log the extracted JSON for debugging
        error_log('Build Agent Debug - Extracted JSON: ' . $json_content);

        $blocks = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('Build Agent Debug - JSON decode error: ' . $json_error);
            error_log('Build Agent Debug - JSON content that failed: ' . $json_content);

            return new WP_Error('parse_error', sprintf(
                __('Failed to parse AI response as JSON. Error: %s. Content: %s', 'build-agent'),
                $json_error,
                substr($json_content, 0, 200) . '...'
            ));
        }

        // Validate that we have an array of blocks
        if (!is_array($blocks)) {
            error_log('Build Agent Debug - Response is not an array: ' . gettype($blocks));
            return new WP_Error('format_error', __('AI response is not a valid array of blocks.', 'build-agent'));
        }

        return $blocks;
    }

    /**
     * Extract JSON from AI response using multiple methods
     */
    private function extract_json_from_response($content) {
        // Method 1: Look for JSON wrapped in code blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $json = trim($matches[1]);
            return $this->validate_and_repair_json($json);
        }

        // Method 2: Look for any code block that might contain JSON
        if (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $potential_json = trim($matches[1]);
            // Check if it looks like JSON (starts with [ or {)
            if (preg_match('/^\s*[\[\{]/', $potential_json)) {
                return $this->validate_and_repair_json($potential_json);
            }
        }

        // Method 3: Look for JSON starting with [ and ending with ]
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            return $this->validate_and_repair_json(trim($matches[0]));
        }

        // Method 4: Look for JSON starting with { and ending with }
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            return $this->validate_and_repair_json(trim($matches[0]));
        }

        // Method 5: Try to find the start of a JSON array and extract everything to the end
        if (preg_match('/\[.*$/s', $content, $matches)) {
            $json = trim($matches[0]);
            return $this->validate_and_repair_json($json);
        }

        // Method 6: Try the content as-is after trimming
        $trimmed = trim($content);
        if (preg_match('/^\s*[\[\{]/', $trimmed)) {
            return $this->validate_and_repair_json($trimmed);
        }

        return false;
    }

    /**
     * Validate and attempt to repair incomplete JSON
     */
    private function validate_and_repair_json($json) {
        // First, try to parse as-is
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        // If it's a syntax error, try to repair common issues
        if (json_last_error() === JSON_ERROR_SYNTAX) {
            error_log('Build Agent Debug - Attempting to repair JSON syntax error');

            // Try to fix truncated JSON by completing the structure
            $repaired = $this->attempt_json_repair($json);
            if ($repaired !== false) {
                $decoded = json_decode($repaired, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    error_log('Build Agent Debug - Successfully repaired JSON');
                    return $repaired;
                }
            }
        }

        return false;
    }

    /**
     * Attempt to repair common JSON syntax errors
     */
    private function attempt_json_repair($json) {
        $original = $json;

        // Remove any trailing commas
        $json = preg_replace('/,(\s*[\]\}])/', '$1', $json);

        // If JSON starts with [ but doesn't end with ], try to close it
        if (preg_match('/^\s*\[/', $json) && !preg_match('/\]\s*$/', $json)) {
            // Count open and close brackets to see if we need to close objects/arrays
            $open_braces = substr_count($json, '{');
            $close_braces = substr_count($json, '}');
            $open_brackets = substr_count($json, '[');
            $close_brackets = substr_count($json, ']');

            // Add missing closing braces and brackets
            $missing_braces = $open_braces - $close_braces;
            $missing_brackets = $open_brackets - $close_brackets;

            for ($i = 0; $i < $missing_braces; $i++) {
                $json .= '}';
            }
            for ($i = 0; $i < $missing_brackets; $i++) {
                $json .= ']';
            }
        }

        // Test if the repair worked
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        // If still broken, try a more aggressive approach - extract only complete objects
        if (preg_match('/^\s*\[/', $original)) {
            $complete_objects = $this->extract_complete_json_objects($original);
            if (!empty($complete_objects)) {
                $json = '[' . implode(',', $complete_objects) . ']';
                $decoded = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    error_log('Build Agent Debug - Extracted complete objects from partial JSON');
                    return $json;
                }
            }
        }

        return false;
    }

    /**
     * Extract complete JSON objects from a potentially truncated JSON array
     */
    private function extract_complete_json_objects($json) {
        $objects = array();
        $current_object = '';
        $brace_count = 0;
        $in_string = false;
        $escape_next = false;
        $chars = str_split($json);

        foreach ($chars as $char) {
            if ($escape_next) {
                $current_object .= $char;
                $escape_next = false;
                continue;
            }

            if ($char === '\\') {
                $escape_next = true;
                $current_object .= $char;
                continue;
            }

            if ($char === '"' && !$escape_next) {
                $in_string = !$in_string;
            }

            if (!$in_string) {
                if ($char === '{') {
                    $brace_count++;
                } elseif ($char === '}') {
                    $brace_count--;
                }
            }

            $current_object .= $char;

            // If we've closed a complete object
            if (!$in_string && $brace_count === 0 && $char === '}') {
                // Clean up the object (remove array brackets and commas)
                $clean_object = trim($current_object);
                $clean_object = preg_replace('/^[\[\s,]+/', '', $clean_object);
                $clean_object = preg_replace('/[\s,]+$/', '', $clean_object);

                // Validate this object
                if (json_decode($clean_object, true) !== null) {
                    $objects[] = $clean_object;
                }

                $current_object = '';
            }
        }

        return $objects;
    }

    private function get_system_prompt() {
        return 'You are a WordPress Gutenberg block generator. Create valid WordPress blocks that will work perfectly in the WordPress block editor.

CRITICAL INSTRUCTIONS:
1. You MUST respond with ONLY a valid JSON array
2. NO explanatory text before or after the JSON
3. NO markdown code blocks or backticks
4. Start your response with [ and end with ]

WORDPRESS BLOCK FORMAT:
Each block must follow WordPress\'s exact structure:
{
    "blockName": "core/blocktype",
    "attrs": {
        // WordPress block attributes only
    },
    "innerBlocks": [
        // nested blocks if needed
    ],
    "innerHTML": "<!-- wp:blocktype {\"attrs\":\"here\"} -->HTML content here<!-- /wp:blocktype -->"
}

AVAILABLE BLOCKS & PROPER USAGE:

1. HEADINGS (core/heading):
{
    "blockName": "core/heading",
    "attrs": {
        "level": 1,
        "textAlign": "center"
    },
    "innerBlocks": [],
    "innerHTML": "<!-- wp:heading {\"level\":1,\"textAlign\":\"center\"} --><h1 class=\"wp-block-heading has-text-align-center\">Your Heading Text</h1><!-- /wp:heading -->"
}

2. PARAGRAPHS (core/paragraph):
{
    "blockName": "core/paragraph",
    "attrs": {
        "textAlign": "center"
    },
    "innerBlocks": [],
    "innerHTML": "<!-- wp:paragraph {\"textAlign\":\"center\"} --><p class=\"has-text-align-center\">Your paragraph text here.</p><!-- /wp:paragraph -->"
}

3. BUTTONS (core/buttons with core/button):
{
    "blockName": "core/buttons",
    "attrs": {
        "layout": {
            "type": "flex",
            "justifyContent": "center"
        }
    },
    "innerBlocks": [
        {
            "blockName": "core/button",
            "attrs": {
                "text": "Click Me",
                "url": "#",
                "className": "is-style-fill"
            },
            "innerBlocks": [],
            "innerHTML": "<!-- wp:button {\"className\":\"is-style-fill\"} --><div class=\"wp-block-button is-style-fill\"><a class=\"wp-block-button__link wp-element-button\" href=\"#\">Click Me</a></div><!-- /wp:button -->"
        }
    ],
    "innerHTML": "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} --><div class=\"wp-block-buttons\"><!-- wp:button {\"className\":\"is-style-fill\"} --><div class=\"wp-block-button is-style-fill\"><a class=\"wp-block-button__link wp-element-button\" href=\"#\">Click Me</a></div><!-- /wp:button --></div><!-- /wp:buttons -->"
}

4. COLUMNS (core/columns with core/column):
{
    "blockName": "core/columns",
    "attrs": {
        "isStackedOnMobile": true
    },
    "innerBlocks": [
        {
            "blockName": "core/column",
            "attrs": {},
            "innerBlocks": [
                {
                    "blockName": "core/paragraph",
                    "attrs": {},
                    "innerBlocks": [],
                    "innerHTML": "<!-- wp:paragraph --><p>Column 1 content</p><!-- /wp:paragraph -->"
                }
            ],
            "innerHTML": "<!-- wp:column --><div class=\"wp-block-column\"><!-- wp:paragraph --><p>Column 1 content</p><!-- /wp:paragraph --></div><!-- /wp:column -->"
        },
        {
            "blockName": "core/column",
            "attrs": {},
            "innerBlocks": [
                {
                    "blockName": "core/paragraph",
                    "attrs": {},
                    "innerBlocks": [],
                    "innerHTML": "<!-- wp:paragraph --><p>Column 2 content</p><!-- /wp:paragraph -->"
                }
            ],
            "innerHTML": "<!-- wp:column --><div class=\"wp-block-column\"><!-- wp:paragraph --><p>Column 2 content</p><!-- /wp:paragraph --></div><!-- /wp:column -->"
        }
    ],
    "innerHTML": "<!-- wp:columns {\"isStackedOnMobile\":true} --><div class=\"wp-block-columns is-stacked-on-mobile\"><!-- wp:column --><div class=\"wp-block-column\"><!-- wp:paragraph --><p>Column 1 content</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class=\"wp-block-column\"><!-- wp:paragraph --><p>Column 2 content</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->"
}

5. COVER/HERO SECTIONS (core/cover):
{
    "blockName": "core/cover",
    "attrs": {
        "url": "https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80",
        "hasParallax": false,
        "dimRatio": 50,
        "minHeight": 400,
        "contentPosition": "center center"
    },
    "innerBlocks": [
        {
            "blockName": "core/heading",
            "attrs": {
                "level": 1,
                "textAlign": "center",
                "style": {
                    "color": {
                        "text": "#ffffff"
                    }
                }
            },
            "innerBlocks": [],
            "innerHTML": "<!-- wp:heading {\"level\":1,\"textAlign\":\"center\",\"style\":{\"color\":{\"text\":\"#ffffff\"}}} --><h1 class=\"wp-block-heading has-text-align-center\" style=\"color:#ffffff\">Hero Title</h1><!-- /wp:heading -->"
        }
    ],
    "innerHTML": "<!-- wp:cover {\"url\":\"https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80\",\"hasParallax\":false,\"dimRatio\":50,\"minHeight\":400,\"contentPosition\":\"center center\"} --><div class=\"wp-block-cover\" style=\"min-height:400px\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-background-dim\"></span><img class=\"wp-block-cover__image-background\" alt=\"\" src=\"https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80\" data-object-fit=\"cover\"/><div class=\"wp-block-cover__inner-container\"><!-- wp:heading {\"level\":1,\"textAlign\":\"center\",\"style\":{\"color\":{\"text\":\"#ffffff\"}}} --><h1 class=\"wp-block-heading has-text-align-center\" style=\"color:#ffffff\">Hero Title</h1><!-- /wp:heading --></div></div><!-- /wp:cover -->"
}

6. SPACER (core/spacer):
{
    "blockName": "core/spacer",
    "attrs": {
        "height": "50px"
    },
    "innerBlocks": [],
    "innerHTML": "<!-- wp:spacer {\"height\":\"50px\"} --><div style=\"height:50px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div><!-- /wp:spacer -->"
}

CRITICAL RULES:
- Always use proper WordPress CSS classes (wp-block-*, has-text-align-*, etc.)
- For buttons, always wrap individual core/button blocks in core/buttons
- innerHTML must contain valid HTML with proper WordPress comment delimiters
- Attributes in innerHTML comments must match the attrs object exactly
- Use realistic placeholder content
- For hero sections, use core/cover with proper background images
- For layouts, use core/columns with core/column children

EXAMPLE FOR "hero section with button":
[
    {
        "blockName": "core/cover",
        "attrs": {
            "url": "https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80",
            "hasParallax": false,
            "dimRatio": 30,
            "minHeight": 500,
            "contentPosition": "center center"
        },
        "innerBlocks": [
            {
                "blockName": "core/heading",
                "attrs": {
                    "level": 1,
                    "textAlign": "center",
                    "style": {
                        "color": {
                            "text": "#ffffff"
                        }
                    }
                },
                "innerBlocks": [],
                "innerHTML": "<!-- wp:heading {\"level\":1,\"textAlign\":\"center\",\"style\":{\"color\":{\"text\":\"#ffffff\"}}} --><h1 class=\"wp-block-heading has-text-align-center\" style=\"color:#ffffff\">Welcome to Our Amazing Service</h1><!-- /wp:heading -->"
            },
            {
                "blockName": "core/paragraph",
                "attrs": {
                    "textAlign": "center",
                    "style": {
                        "color": {
                            "text": "#ffffff"
                        }
                    }
                },
                "innerBlocks": [],
                "innerHTML": "<!-- wp:paragraph {\"textAlign\":\"center\",\"style\":{\"color\":{\"text\":\"#ffffff\"}}} --><p class=\"has-text-align-center\" style=\"color:#ffffff\">Transform your business with our innovative solutions</p><!-- /wp:paragraph -->"
            },
            {
                "blockName": "core/buttons",
                "attrs": {
                    "layout": {
                        "type": "flex",
                        "justifyContent": "center"
                    }
                },
                "innerBlocks": [
                    {
                        "blockName": "core/button",
                        "attrs": {
                            "text": "Get Started",
                            "url": "#",
                            "className": "is-style-fill"
                        },
                        "innerBlocks": [],
                        "innerHTML": "<!-- wp:button {\"className\":\"is-style-fill\"} --><div class=\"wp-block-button is-style-fill\"><a class=\"wp-block-button__link wp-element-button\" href=\"#\">Get Started</a></div><!-- /wp:button -->"
                    }
                ],
                "innerHTML": "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} --><div class=\"wp-block-buttons\"><!-- wp:button {\"className\":\"is-style-fill\"} --><div class=\"wp-block-button is-style-fill\"><a class=\"wp-block-button__link wp-element-button\" href=\"#\">Get Started</a></div><!-- /wp:button --></div><!-- /wp:buttons -->"
            }
        ],
        "innerHTML": "<!-- wp:cover {\"url\":\"https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80\",\"hasParallax\":false,\"dimRatio\":30,\"minHeight\":500,\"contentPosition\":\"center center\"} --><div class=\"wp-block-cover\" style=\"min-height:500px\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-background-dim-30 has-background-dim\"></span><img class=\"wp-block-cover__image-background\" alt=\"\" src=\"https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80\" data-object-fit=\"cover\"/><div class=\"wp-block-cover__inner-container\"><!-- wp:heading {\"level\":1,\"textAlign\":\"center\",\"style\":{\"color\":{\"text\":\"#ffffff\"}}} --><h1 class=\"wp-block-heading has-text-align-center\" style=\"color:#ffffff\">Welcome to Our Amazing Service</h1><!-- /wp:heading --><!-- wp:paragraph {\"textAlign\":\"center\",\"style\":{\"color\":{\"text\":\"#ffffff\"}}} --><p class=\"has-text-align-center\" style=\"color:#ffffff\">Transform your business with our innovative solutions</p><!-- /wp:paragraph --><!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} --><div class=\"wp-block-buttons\"><!-- wp:button {\"className\":\"is-style-fill\"} --><div class=\"wp-block-button is-style-fill\"><a class=\"wp-block-button__link wp-element-button\" href=\"#\">Get Started</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div></div><!-- /wp:cover -->"
    }
]

Remember: Follow WordPress block structure EXACTLY. Output ONLY the JSON array, nothing else.';
    }

    private function render_blocks_preview($blocks) {
        if (!is_array($blocks)) {
            return '';
        }

        $preview = '<div class="blocks-preview">';

        foreach ($blocks as $block) {
            $preview .= '<div class="block-preview">';
            $preview .= '<strong>Block:</strong> ' . esc_html($block['blockName'] ?? 'unknown') . '<br>';

            if (isset($block['innerHTML'])) {
                // Show a simplified preview of the block content
                $content = wp_strip_all_tags($block['innerHTML']);
                $content = wp_trim_words($content, 20);
                $preview .= '<em>Content:</em> ' . esc_html($content) . '<br>';
            }

            $preview .= '</div><hr>';
        }

        $preview .= '</div>';

        return $preview;
    }

    public function handle_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'build_agent_test_nonce')) {
            wp_die(__('Security check failed.', 'build-agent'));
        }

        // Get test parameters from the form
        $endpoint = sanitize_url($_POST['endpoint']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $deployment_name = sanitize_text_field($_POST['deployment_name']);
        $api_version = sanitize_text_field($_POST['api_version']);

        // Validate inputs
        $errors = array();

        if (empty($endpoint)) {
            $errors[] = __('Azure OpenAI Endpoint is required.', 'build-agent');
        } elseif (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $errors[] = __('Azure OpenAI Endpoint must be a valid URL.', 'build-agent');
        }

        if (empty($api_key)) {
            $errors[] = __('Azure OpenAI API Key is required.', 'build-agent');
        }

        if (empty($deployment_name)) {
            $errors[] = __('Deployment Name is required.', 'build-agent');
        }

        if (empty($api_version)) {
            $errors[] = __('API Version is required.', 'build-agent');
        }

        if (!empty($errors)) {
            wp_send_json_error(implode(' ', $errors));
        }

        // Test the connection
        $test_result = $this->test_azure_openai_connection($endpoint, $api_key, $deployment_name, $api_version);

        if (is_wp_error($test_result)) {
            wp_send_json_error($test_result->get_error_message());
        } else {
            wp_send_json_success($test_result);
        }
    }

    private function test_azure_openai_connection($endpoint, $api_key, $deployment_name, $api_version) {
        // Build the URL
        $url = rtrim($endpoint, '/') . "/openai/deployments/{$deployment_name}/chat/completions?api-version={$api_version}";

        // Add debugging information
        error_log('Build Agent Debug - Testing Azure OpenAI Connection:');
        error_log('Endpoint: ' . $endpoint);
        error_log('Deployment: ' . $deployment_name);
        error_log('API Version: ' . $api_version);
        error_log('Full URL: ' . $url);

        // Simple test message
        $body = array(
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hello'
                )
            ),
            'max_tokens' => 10,
            'temperature' => 0.1
        );

        // Make the request
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api-key' => $api_key
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            error_log('Build Agent Debug - WordPress HTTP Error: ' . $response->get_error_message());
            return new WP_Error('connection_failed', sprintf(
                __('Failed to connect to Azure OpenAI. Network error: %s', 'build-agent'),
                $response->get_error_message()
            ));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Log response details
        error_log('Build Agent Debug - Response Code: ' . $response_code);
        error_log('Build Agent Debug - Response Body: ' . $response_body);

        // Parse response for detailed error diagnosis
        switch ($response_code) {
            case 200:
                $data = json_decode($response_body, true);
                if (isset($data['choices'][0]['message']['content'])) {
                    return array(
                        'message' => __('Connection successful! Azure OpenAI is responding correctly.', 'build-agent'),
                        'details' => array(
                            'endpoint' => __('✓ Endpoint is reachable', 'build-agent'),
                            'api_key' => __('✓ API Key is valid', 'build-agent'),
                            'deployment' => __('✓ Deployment is accessible', 'build-agent'),
                            'api_version' => __('✓ API Version is supported', 'build-agent')
                        )
                    );
                } else {
                    return new WP_Error('invalid_response', __('Connected but received unexpected response format.', 'build-agent'));
                }
                break;

            case 401:
                return new WP_Error('authentication_failed',
                    __('Authentication failed. Please check your API Key. Make sure it\'s correct and has not expired.', 'build-agent')
                );
                break;

            case 404:
                $error_data = json_decode($response_body, true);

                // Provide more detailed debugging for 404 errors
                $debug_info = sprintf(
                    __('URL attempted: %s | Endpoint format should be: https://your-resource-name.openai.azure.com/', 'build-agent'),
                    $url
                );

                if (isset($error_data['error']['code'])) {
                    switch ($error_data['error']['code']) {
                        case 'DeploymentNotFound':
                            return new WP_Error('deployment_not_found',
                                sprintf(__('Deployment "%s" not found. Please check your deployment name in the Azure portal. %s', 'build-agent'),
                                $deployment_name, $debug_info)
                            );
                        case 'ResourceNotFound':
                            return new WP_Error('resource_not_found',
                                sprintf(__('Azure OpenAI resource not found. Please check your endpoint URL. %s', 'build-agent'), $debug_info)
                            );
                        default:
                            return new WP_Error('not_found',
                                sprintf(__('Resource not found (404). Error: %s | %s', 'build-agent'),
                                $error_data['error']['message'] ?? 'Unknown error', $debug_info)
                            );
                    }
                } else {
                    return new WP_Error('not_found',
                        sprintf(__('Resource not found. Please check your endpoint URL and deployment name. %s', 'build-agent'), $debug_info)
                    );
                }
                break;

            case 400:
                $error_data = json_decode($response_body, true);
                if (isset($error_data['error']['code'])) {
                    switch ($error_data['error']['code']) {
                        case 'InvalidApiVersionParameter':
                            return new WP_Error('invalid_api_version',
                                sprintf(__('Invalid API version "%s". Please check the supported versions in Azure documentation.', 'build-agent'),
                                $api_version)
                            );
                        case 'InvalidRequestParameter':
                            return new WP_Error('invalid_request',
                                sprintf(__('Invalid request parameter. Error: %s', 'build-agent'),
                                $error_data['error']['message'] ?? 'Unknown parameter issue')
                            );
                        default:
                            return new WP_Error('bad_request',
                                sprintf(__('Bad request (400). Error: %s', 'build-agent'),
                                $error_data['error']['message'] ?? 'Unknown error')
                            );
                    }
                } else {
                    return new WP_Error('bad_request', __('Bad request. Please check all your settings.', 'build-agent'));
                }
                break;

            case 403:
                return new WP_Error('forbidden',
                    __('Access forbidden. Your API key may not have permission to access this deployment or the resource may be in a different region.', 'build-agent')
                );
                break;

            case 429:
                return new WP_Error('rate_limited',
                    __('Rate limit exceeded. Please wait a moment and try again.', 'build-agent')
                );
                break;

            case 500:
            case 502:
            case 503:
            case 504:
                return new WP_Error('server_error',
                    sprintf(__('Azure OpenAI server error (%d). Please try again later.', 'build-agent'), $response_code)
                );
                break;

            default:
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : $response_body;
                return new WP_Error('unexpected_error',
                    sprintf(__('Unexpected response (HTTP %d): %s', 'build-agent'), $response_code, $error_message)
                );
        }
    }

    // ...existing code...
}

// Initialize the plugin
new BuildAgent();

// Activation hook
register_activation_hook(__FILE__, 'build_agent_activate');
function build_agent_activate() {
    // Create any necessary database tables or options
    add_option('build_agent_azure_endpoint', '');
    add_option('build_agent_azure_api_key', '');
    add_option('build_agent_azure_deployment_name', 'gpt-4');
    add_option('build_agent_azure_api_version', '2024-02-15-preview');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'build_agent_deactivate');
function build_agent_deactivate() {
    // Clean up if necessary
}
