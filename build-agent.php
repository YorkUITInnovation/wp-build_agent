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
        register_setting('build_agent_settings', 'build_agent_is_reasoning_model');

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
                    <tr>
                        <th scope="row">
                            <label for="build_agent_is_reasoning_model"><?php _e('Reasoning Model', 'build-agent'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="build_agent_is_reasoning_model" name="build_agent_is_reasoning_model"
                                   value="1" <?php checked(get_option('build_agent_is_reasoning_model'), 1); ?> />
                            <label for="build_agent_is_reasoning_model"><?php _e('Check this if using a reasoning model (o1, o3, etc.)', 'build-agent'); ?></label>
                            <p class="description"><?php _e('Reasoning models like o1 and o3 use different API parameters. Enable this for o1-preview, o1-mini, o3-mini, or other reasoning models.', 'build-agent'); ?></p>
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

                <div class="build-agent-categories" style="margin: 15px 0;">
                    <label for="build-agent-block-categories">
                        <strong><?php _e('Block Categories (select up to 2):', 'build-agent'); ?></strong>
                    </label>
                    <div style="margin-top: 8px;">
                        <label style="margin-right: 20px;">
                            <input type="checkbox" name="build_agent_categories[]" value="text" checked>
                            <?php _e('Text', 'build-agent'); ?> <span style="color: #666;">(headings, paragraphs, lists, quotes, tables)</span>
                        </label>
                        <label style="margin-right: 20px;">
                            <input type="checkbox" name="build_agent_categories[]" value="media">
                            <?php _e('Media', 'build-agent'); ?> <span style="color: #666;">(images, gallery, video, hero sections)</span>
                        </label>
                        <label style="margin-right: 20px;">
                            <input type="checkbox" name="build_agent_categories[]" value="design" checked>
                            <?php _e('Design', 'build-agent'); ?> <span style="color: #666;">(buttons, separators, spacers, groups)</span>
                        </label>
                        <label style="margin-right: 20px;">
                            <input type="checkbox" name="build_agent_categories[]" value="layout">
                            <?php _e('Layout', 'build-agent'); ?> <span style="color: #666;">(columns, media & text, rows)</span>
                        </label>
                        <label style="margin-right: 20px;">
                            <input type="checkbox" name="build_agent_categories[]" value="widgets">
                            <?php _e('Widgets', 'build-agent'); ?> <span style="color: #666;">(shortcodes, HTML, embeds)</span>
                        </label>
                    </div>
                    <p class="description" style="margin-top: 8px;">
                        <?php _e('Select which types of blocks the AI can use. Maximum 2 categories to keep responses focused and optimized.', 'build-agent'); ?>
                    </p>
                </div>

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
            .build-agent-categories label {
                display: inline-block;
                margin-bottom: 8px;
                font-weight: normal;
            }
            .build-agent-categories input[type="checkbox"] {
                margin-right: 5px;
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

        <script>
            jQuery(document).ready(function($) {
                // Limit category selection to 2
                $('input[name="build_agent_categories[]"]').on('change', function() {
                    var checked = $('input[name="build_agent_categories[]"]:checked');
                    if (checked.length > 2) {
                        this.checked = false;
                        alert('<?php _e('You can select a maximum of 2 categories to keep the system prompt optimized.', 'build-agent'); ?>');
                    }
                });
            });
        </script>
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
                'timeout' => (int) get_option('build_agent_timeout', 30), // Pass timeout to JavaScript
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

        $blocks = $this->generate_blocks_from_prompt($prompt, $categories);

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
            'preview' => $this->render_blocks_preview($blocks)
        ));
    }

    public function handle_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'build_agent_test_nonce')) {
            wp_die(__('Security check failed.', 'build-agent'));
        }

        $endpoint = sanitize_url($_POST['endpoint']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $deployment_name = sanitize_text_field($_POST['deployment_name']);
        $api_version = sanitize_text_field($_POST['api_version']);

        if (empty($endpoint) || empty($api_key) || empty($deployment_name)) {
            wp_send_json_error(__('Missing required connection parameters.', 'build-agent'));
        }

        $url = rtrim($endpoint, '/') . "/openai/deployments/{$deployment_name}/chat/completions?api-version={$api_version}";

        // Simple test message
        $body = array(
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hello, this is a connection test.'
                )
            ),
            'max_tokens' => 10
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api-key' => $api_key
            ),
            'body' => json_encode($body),
            'timeout' => 15 // Short timeout for connection test
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(sprintf(
                __('Failed to connect to Azure OpenAI. Error: %s', 'build-agent'),
                $response->get_error_message()
            ));
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            wp_send_json_success(array(
                'message' => __('Connection successful! Your Azure OpenAI configuration is working correctly.', 'build-agent')
            ));
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';

            wp_send_json_error(sprintf(
                __('Connection failed with status %d. Error: %s', 'build-agent'),
                $response_code,
                $error_message
            ));
        }
    }

    private function render_blocks_preview($blocks) {
        if (!is_array($blocks) || empty($blocks)) {
            return '<p>' . __('No blocks generated.', 'build-agent') . '</p>';
        }

        $preview = '<div class="build-agent-blocks-list">';

        foreach ($blocks as $index => $block) {
            $block_name = isset($block['blockName']) ? $block['blockName'] : 'unknown';
            $block_title = $this->get_block_title($block_name);

            $preview .= '<div class="build-agent-block-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
            $preview .= '<h5 style="margin: 0 0 5px 0; color: #2271b1;">' . sprintf(__('Block %d: %s', 'build-agent'), $index + 1, $block_title) . '</h5>';

            // Show a simplified preview of the block content
            if (isset($block['innerHTML']) && !empty($block['innerHTML'])) {
                $content_preview = wp_strip_all_tags($block['innerHTML']);
                $content_preview = substr($content_preview, 0, 100);
                if (strlen($content_preview) > 97) {
                    $content_preview = substr($content_preview, 0, 97) . '...';
                }
                if (!empty($content_preview)) {
                    $preview .= '<p style="margin: 5px 0; color: #666; font-style: italic;">' . esc_html($content_preview) . '</p>';
                }
            }

            // Show block attributes if available
            if (isset($block['attrs']) && !empty($block['attrs'])) {
                $attrs_count = count($block['attrs']);
                $preview .= '<small style="color: #999;">' . sprintf(__('%d attributes configured', 'build-agent'), $attrs_count) . '</small>';
            }

            $preview .= '</div>';
        }

        $preview .= '</div>';
        $preview .= '<p style="margin-top: 15px; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1;">';
        $preview .= '<strong>' . __('Ready to insert!', 'build-agent') . '</strong> ';
        $preview .= sprintf(__('Generated %d blocks. Click "Insert into Page" to add them to your content.', 'build-agent'), count($blocks));
        $preview .= '</p>';

        return $preview;
    }

    private function get_block_title($block_name) {
        $titles = array(
            'core/paragraph' => __('Paragraph', 'build-agent'),
            'core/heading' => __('Heading', 'build-agent'),
            'core/image' => __('Image', 'build-agent'),
            'core/gallery' => __('Gallery', 'build-agent'),
            'core/list' => __('List', 'build-agent'),
            'core/quote' => __('Quote', 'build-agent'),
            'core/button' => __('Button', 'build-agent'),
            'core/buttons' => __('Buttons', 'build-agent'),
            'core/columns' => __('Columns', 'build-agent'),
            'core/column' => __('Column', 'build-agent'),
            'core/group' => __('Group', 'build-agent'),
            'core/cover' => __('Cover', 'build-agent'),
            'core/media-text' => __('Media & Text', 'build-agent'),
            'core/separator' => __('Separator', 'build-agent'),
            'core/spacer' => __('Spacer', 'build-agent'),
            'core/table' => __('Table', 'build-agent'),
            'core/html' => __('Custom HTML', 'build-agent'),
            'core/shortcode' => __('Shortcode', 'build-agent'),
            'core/embed' => __('Embed', 'build-agent'),
        );

        return isset($titles[$block_name]) ? $titles[$block_name] : ucfirst(str_replace(['core/', '-'], ['', ' '], $block_name));
    }

    private function generate_blocks_from_prompt($prompt, $categories = array('text', 'design')) {
        if (empty($this->azure_endpoint) || empty($this->azure_api_key)) {
            return new WP_Error('config_error', __('Azure OpenAI credentials not configured.', 'build-agent'));
        }

        $deployment_name = get_option('build_agent_azure_deployment_name', 'gpt-4');
        $is_reasoning_model = get_option('build_agent_is_reasoning_model', false);

        $system_prompt = $this->get_system_prompt($categories);

        $url = rtrim($this->azure_endpoint, '/') . "/openai/deployments/{$deployment_name}/chat/completions?api-version={$this->azure_api_version}";

        // Build API request body based on model type
        if ($is_reasoning_model) {
            // Reasoning models don't support system messages - combine system prompt with user message
            $combined_prompt = $system_prompt . "\n\nUser Request: " . $prompt;

            $body = array(
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $combined_prompt
                    )
                ),
                'max_completion_tokens' => (int) get_option('build_agent_max_tokens', 4000)
            );
            // Reasoning models don't support temperature, top_p, frequency_penalty, presence_penalty
        } else {
            // Regular models support system messages and all parameters
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
        }

        $timeout = (int) get_option('build_agent_timeout', 30);

        // Log the request for debugging
        error_log('Build Agent Debug - Request body: ' . json_encode($body));
        error_log('Build Agent Debug - Is reasoning model: ' . ($is_reasoning_model ? 'yes' : 'no'));
        error_log('Build Agent Debug - URL: ' . $url);

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api-key' => $this->azure_api_key
            ),
            'body' => json_encode($body),
            'timeout' => $timeout
        ));

        if (is_wp_error($response)) {
            error_log('Build Agent Debug - WordPress HTTP Error: ' . $response->get_error_message());
            return new WP_Error('api_error', sprintf(
                __('Failed to connect to Azure OpenAI. Error: %s', 'build-agent'),
                $response->get_error_message()
            ));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Log response for debugging
        error_log('Build Agent Debug - Response code: ' . $response_code);
        error_log('Build Agent Debug - Response body: ' . substr($response_body, 0, 500) . '...');

        // Enhanced error handling for reasoning models
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';

            // Check for specific reasoning model errors
            if ($response_code === 400) {
                if (strpos($error_message, 'max_tokens') !== false || strpos($error_message, 'max_completion_tokens') !== false) {
                    return new WP_Error('api_error', sprintf(
                        __('Bad request (400). Error: %s. This looks like a parameter compatibility issue. Try %s the "Reasoning Model" setting.', 'build-agent'),
                        $error_message,
                        $is_reasoning_model ? 'disabling' : 'enabling'
                    ));
                }

                if (strpos($error_message, 'system') !== false && $is_reasoning_model) {
                    return new WP_Error('api_error', sprintf(
                        __('Bad request (400). Error: %s. Reasoning models don\'t support system messages. This should be handled automatically.', 'build-agent'),
                        $error_message
                    ));
                }
            }

            return new WP_Error('api_error', sprintf(
                __('API request failed with status %d. Error: %s', 'build-agent'),
                $response_code,
                $error_message
            ));
        }

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

    private function get_system_prompt($categories = array('text', 'design')) {
        $base_prompt = 'You are a WordPress Gutenberg block generator. Create valid WordPress blocks that will work perfectly in the WordPress block editor.

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

AVAILABLE BLOCKS FOR CATEGORIES ' . implode(', ', $categories) . ':

';

        // Add category-specific block information
        $block_info = '';

        if (in_array('text', $categories)) {
            $block_info .= '
TEXT BLOCKS:
- core/paragraph: Basic text paragraphs
- core/heading: H1-H6 headings with level attribute
- core/list: Ordered/unordered lists
- core/quote: Blockquotes with citation
- core/table: Data tables with rows/columns
';
        }

        if (in_array('design', $categories)) {
            $block_info .= '
DESIGN BLOCKS:
- core/buttons: Container for button blocks
- core/button: Individual buttons (must be inside core/buttons)
- core/separator: Horizontal dividers
- core/spacer: Vertical spacing
- core/group: Container for grouping blocks
';
        }

        if (in_array('media', $categories)) {
            $block_info .= '
MEDIA BLOCKS:
- core/image: Single images with alt text
- core/gallery: Multiple images
- core/cover: Hero sections with background images
- core/video: Video embeds
';
        }

        if (in_array('layout', $categories)) {
            $block_info .= '
LAYOUT BLOCKS:
- core/columns: Container for column layout
- core/column: Individual columns (must be inside core/columns)
- core/media-text: Side-by-side media and text
';
        }

        if (in_array('widgets', $categories)) {
            $block_info .= '
WIDGET BLOCKS:
- core/html: Custom HTML code
- core/shortcode: WordPress shortcodes
- core/embed: External content embeds
';
        }

        $closing_prompt = '

CRITICAL RULES:
- Always use proper WordPress CSS classes (wp-block-*, has-text-align-*, etc.)
- For buttons, always wrap individual core/button blocks in core/buttons
- innerHTML must contain valid HTML with proper WordPress comment delimiters
- Attributes in innerHTML comments must match the attrs object exactly
- Use realistic placeholder content
- For hero sections, use core/cover with proper background images
- For layouts, use core/columns with core/column children
- For tabular data, always use core/table, never core/columns

Remember: Follow WordPress block structure EXACTLY. Output ONLY the JSON array, nothing else.';

        return $base_prompt . $block_info . $closing_prompt;
    }
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
