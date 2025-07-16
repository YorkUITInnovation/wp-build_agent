<?php
/**
 * Build Agent AI Generator Class
 *
 * Handles Azure OpenAI API communication and block generation.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Build_Agent_AI_Generator {

    private $azure_endpoint;
    private $azure_api_key;
    private $azure_api_version;

    /**
     * Initialize the AI generator
     */
    public function __construct() {
        $this->azure_endpoint = get_option('build_agent_azure_endpoint', '');
        $this->azure_api_key = get_option('build_agent_azure_api_key', '');
        $this->azure_api_version = get_option('build_agent_azure_api_version', '2024-02-15-preview');
    }

    /**
     * Generate blocks from prompt
     */
    public function generate_blocks_from_prompt($prompt, $categories = array('text', 'design')) {
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
                __('Failed to parse AI response as JSON. Error: %s', 'build-agent'),
                $json_error
            ));
        }

        return $blocks;
    }

    /**
     * Extract JSON from AI response
     */
    private function extract_json_from_response($content) {
        // Method 1: Try to find JSON between ```json and ``` markers
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            return trim($matches[1]);
        }

        // Method 2: Try to find JSON between ``` markers (without json specification)
        if (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $potential_json = trim($matches[1]);
            // Check if it looks like JSON (starts with [ or {)
            if (preg_match('/^\s*[\[\{]/', $potential_json)) {
                return $potential_json;
            }
        }

        // Method 3: Try to find a JSON array or object in the content
        if (preg_match('/(\[.*\]|\{.*\})/s', $content, $matches)) {
            return $matches[1];
        }

        // Method 4: If the entire content looks like JSON, use it
        $trimmed_content = trim($content);
        if (preg_match('/^\s*[\[\{]/', $trimmed_content) && preg_match('/[\]\}]\s*$/', $trimmed_content)) {
            return $trimmed_content;
        }

        return false;
    }

    /**
     * Get system prompt based on categories
     */
    private function get_system_prompt($categories) {
        $base_prompt = "You are a WordPress block generation AI. Generate WordPress blocks in valid JSON format that can be parsed by WordPress's block parser.

CRITICAL REQUIREMENTS:
1. Return ONLY a valid JSON array of block objects
2. Each block must have: blockName, attrs (object), innerHTML (string)
3. Use ONLY WordPress core blocks
4. Generate semantic, accessible HTML
5. Include proper CSS classes and attributes

";

        // Add category-specific instructions
        $category_prompts = array(
            'text' => "TEXT BLOCKS - Available blocks:
- core/heading (h1-h6 with level attribute)
- core/paragraph (with HTML content)
- core/list (ordered/unordered with HTML items)
- core/quote (with cite support)
- core/table (with tbody/thead structure)

",
            'media' => "MEDIA BLOCKS - Available blocks:
- core/image (with src, alt, caption)
- core/gallery (with images array)
- core/cover (hero sections with background)
- core/video (with src attribute)

",
            'design' => "DESIGN BLOCKS - Available blocks:
- core/button (with text, URL, className)
- core/buttons (container for multiple buttons)
- core/separator (with style options)
- core/spacer (with height attribute)
- core/group (container with backgroundColor)

",
            'layout' => "LAYOUT BLOCKS - Available blocks:
- core/columns (with numberOfColumns)
- core/column (with width percentage)
- core/media-text (side-by-side content)
- core/row (horizontal layout)

",
            'widgets' => "WIDGET BLOCKS - Available blocks:
- core/shortcode (with shortcode content)
- core/html (with custom HTML)
- core/embed (with URL and provider)

"
        );

        $selected_prompts = '';
        foreach ($categories as $category) {
            if (isset($category_prompts[$category])) {
                $selected_prompts .= $category_prompts[$category];
            }
        }

        $example_prompt = "
EXAMPLE OUTPUT FORMAT:
[
  {
    \"blockName\": \"core/heading\",
    \"attrs\": {\"level\": 2, \"className\": \"wp-block-heading\"},
    \"innerHTML\": \"<h2 class=\\\"wp-block-heading\\\">Welcome to Our Service</h2>\"
  },
  {
    \"blockName\": \"core/paragraph\",
    \"attrs\": {},
    \"innerHTML\": \"<p>This is a paragraph with some <strong>bold text</strong> and <em>italic text</em>.</p>\"
  }
]

IMPORTANT:
- Escape quotes properly in innerHTML
- Use realistic, engaging content
- Include proper HTML structure
- Add CSS classes for styling
- Make content accessible and semantic";

        return $base_prompt . $selected_prompts . $example_prompt;
    }
}
