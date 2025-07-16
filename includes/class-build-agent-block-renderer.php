<?php
/**
 * Build Agent Block Renderer Class
 *
 * Handles block preview rendering and formatting.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Build_Agent_Block_Renderer {

    /**
     * Render blocks preview for admin interface
     */
    public function render_blocks_preview($blocks) {
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

    /**
     * Get user-friendly block titles
     */
    public function get_block_title($block_name) {
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

    /**
     * Format blocks for insertion into WordPress editor
     */
    public function format_blocks_for_editor($blocks) {
        if (!is_array($blocks) || empty($blocks)) {
            return '';
        }

        $formatted_blocks = array();
        foreach ($blocks as $block) {
            if (isset($block['blockName']) && isset($block['innerHTML'])) {
                $formatted_blocks[] = serialize_block($block);
            }
        }

        return implode("\n\n", $formatted_blocks);
    }
}
