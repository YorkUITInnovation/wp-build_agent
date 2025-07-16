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

/**
 * Main Build Agent Plugin Class
 *
 * This class only handles plugin initialization and autoloading.
 * All functionality is separated into logical classes in the includes directory.
 */
class BuildAgent {

    private $settings;
    private $meta_box;
    private $ajax_handler;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_classes'));
    }

    /**
     * Initialize plugin basics
     */
    public function init() {
        // Load plugin text domain for translations
        load_plugin_textdomain('build-agent', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Load and initialize all plugin classes
     */
    public function load_classes() {
        // Require all class files
        require_once BUILD_AGENT_PLUGIN_PATH . 'includes/class-build-agent-settings.php';
        require_once BUILD_AGENT_PLUGIN_PATH . 'includes/class-build-agent-meta-box.php';
        require_once BUILD_AGENT_PLUGIN_PATH . 'includes/class-build-agent-ai-generator.php';
        require_once BUILD_AGENT_PLUGIN_PATH . 'includes/class-build-agent-block-renderer.php';
        require_once BUILD_AGENT_PLUGIN_PATH . 'includes/class-build-agent-ajax-handler.php';

        // Initialize classes
        $this->settings = new Build_Agent_Settings();
        $this->meta_box = new Build_Agent_Meta_Box();
        $this->ajax_handler = new Build_Agent_AJAX_Handler();
    }
}

// Initialize the plugin
new BuildAgent();

