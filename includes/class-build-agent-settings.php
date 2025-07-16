<?php
/**
 * Build Agent Settings Class
 *
 * Handles all admin settings and configuration for the Build Agent plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Build_Agent_Settings {

    /**
     * Initialize the settings class
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_build_agent_test_connection', array($this, 'handle_test_connection'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('Build Agent Settings', 'build-agent'),
            __('Build Agent', 'build-agent'),
            'manage_options',
            'build-agent-settings',
            array($this, 'admin_page')
        );
    }

    /**
     * Register plugin settings
     */
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

    /**
     * Render admin page
     */
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
                        </td>
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

    /**
     * Handle AJAX connection test
     */
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
}
