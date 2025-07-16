<?php
/**
 * Build Agent Meta Box Class
 *
 * Handles the post/page editor interface for the Build Agent plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Build_Agent_Meta_Box {

    /**
     * Initialize the meta box class
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add meta boxes to post and page edit screens
     */
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

    /**
     * Render the meta box content
     */
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

        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }

    /**
     * Render inline styles for the meta box
     */
    private function render_styles() {
        ?>
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
        <?php
    }

    /**
     * Render inline scripts for the meta box
     */
    private function render_scripts() {
        ?>
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

    /**
     * Enqueue scripts and styles for the meta box
     */
    public function enqueue_scripts($hook) {
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
                'timeout' => (int) get_option('build_agent_timeout', 30),
                'strings' => array(
                    'error' => __('An error occurred while generating blocks.', 'build-agent'),
                    'noPrompt' => __('Please enter a description first.', 'build-agent'),
                    'generating' => __('Generating blocks...', 'build-agent'),
                    'inserting' => __('Inserting blocks...', 'build-agent'),
                )
            ));
        }
    }
}
