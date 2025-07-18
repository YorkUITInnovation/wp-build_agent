jQuery(document).ready(function($) {
    let generatedBlocks = null;

    // Handle generate button click
    $('#build-agent-generate').on('click', function() {
        const prompt = $('#build-agent-prompt').val().trim();

        if (!prompt) {
            alert(buildAgent.strings.noPrompt);
            return;
        }

        // Get selected categories
        const selectedCategories = [];
        $('input[name="build_agent_categories[]"]:checked').each(function() {
            selectedCategories.push($(this).val());
        });

        generateBlocks(prompt, selectedCategories);
    });

    // Handle insert button click
    $('#build-agent-insert').on('click', function() {
        if (generatedBlocks) {
            insertBlocksIntoEditor(generatedBlocks);
        }
    });

    function generateBlocks(prompt, categories) {
        // Show loading state
        $('#build-agent-loading').show();
        $('#build-agent-preview').hide();
        $('#build-agent-error').hide();
        $('#build-agent-insert').hide();
        $('#build-agent-generate').prop('disabled', true).text(buildAgent.strings.generating);

        // Calculate timeout - use WordPress setting with buffer
        const timeoutMs = (buildAgent.timeout || 30) * 1000 + 10000; // Add 10 second buffer

        $.ajax({
            url: buildAgent.ajaxUrl,
            type: 'POST',
            timeout: timeoutMs, // Set explicit timeout for AJAX request
            data: {
                action: 'build_agent_generate',
                prompt: prompt,
                categories: categories,
                nonce: buildAgent.nonce
            },
            success: function(response) {
                $('#build-agent-loading').hide();
                $('#build-agent-generate').prop('disabled', false).text('Generate Blocks');

                if (response.success) {
                    generatedBlocks = response.data.blocks;
                    $('#build-agent-blocks-preview').html(response.data.preview);
                    $('#build-agent-preview').show();
                    $('#build-agent-insert').show();
                } else {
                    showError(response.data || buildAgent.strings.error);
                }
            },
            error: function(xhr, status, error) {
                $('#build-agent-loading').hide();
                $('#build-agent-generate').prop('disabled', false).text('Generate Blocks');

                let errorMessage = buildAgent.strings.error;

                if (status === 'timeout') {
                    errorMessage = 'Request timed out. The AI is taking longer than expected to generate blocks. Try: 1) Simplifying your request, 2) Increasing the timeout in Build Agent settings, or 3) Using fewer block categories.';
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.data) {
                            errorMessage = response.data;
                        }
                    } catch (e) {
                        errorMessage += ' (' + error + ')';
                    }
                } else {
                    errorMessage += ' (' + error + ')';
                }

                showError(errorMessage);
            }
        });
    }

    function insertBlocksIntoEditor(blocks) {
        // Check if we're in the block editor (Gutenberg)
        if (typeof wp !== 'undefined' && wp.data && wp.blocks) {
            insertBlocksIntoGutenberg(blocks);
        } else {
            // Fallback for classic editor or other scenarios
            insertBlocksAsHTML(blocks);
        }
    }

    function insertBlocksIntoGutenberg(blocks) {
        const { dispatch, select } = wp.data;
        const { createBlock, parse } = wp.blocks;

        try {
            // Convert our blocks to Gutenberg blocks
            const gutenbergBlocks = [];

            blocks.forEach(function(blockData) {
                if (blockData.blockName && blockData.innerHTML) {
                    // Parse the block HTML to create a proper Gutenberg block
                    const parsedBlocks = parse(blockData.innerHTML);
                    if (parsedBlocks && parsedBlocks.length > 0) {
                        gutenbergBlocks.push(...parsedBlocks);
                    }
                }
            });

            if (gutenbergBlocks.length > 0) {
                // Get current blocks and insert new ones at the end
                const currentBlocks = select('core/block-editor').getBlocks();
                const insertIndex = currentBlocks.length;

                // Insert blocks one by one
                gutenbergBlocks.forEach(function(block, index) {
                    dispatch('core/block-editor').insertBlock(block, insertIndex + index);
                });

                // Show success message
                showSuccess('Blocks inserted successfully!');

                // Hide the preview
                $('#build-agent-preview').hide();
                $('#build-agent-insert').hide();
                $('#build-agent-prompt').val('');
            }
        } catch (error) {
            console.error('Error inserting blocks:', error);
            showError('Failed to insert blocks into editor.');
        }
    }

    function insertBlocksAsHTML(blocks) {
        // Fallback: insert as HTML comment blocks
        let htmlContent = '\n\n<!-- Generated by Build Agent -->\n';

        blocks.forEach(function(block) {
            if (block.innerHTML) {
                htmlContent += block.innerHTML + '\n';
            }
        });

        htmlContent += '<!-- End Build Agent Generated Content -->\n\n';

        // Try to insert into TinyMCE (classic editor)
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
            tinyMCE.activeEditor.insertContent(htmlContent);
            showSuccess('Content inserted into editor!');
        } else {
            // Final fallback: show the HTML for manual copy/paste
            const modal = $('<div id="build-agent-html-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border: 1px solid #ccc; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 10000; max-width: 80%; max-height: 80%; overflow-y: auto;">' +
                '<h3>Generated Block HTML</h3>' +
                '<p>Copy this HTML and paste it into your editor:</p>' +
                '<textarea readonly style="width: 100%; height: 300px; font-family: monospace;">' + htmlContent + '</textarea>' +
                '<br><br>' +
                '<button class="button button-primary" onclick="jQuery(this).closest(\'#build-agent-html-modal\').remove();">Close</button>' +
                '</div>');

            $('body').append(modal);
        }

        // Clear the form
        $('#build-agent-preview').hide();
        $('#build-agent-insert').hide();
        $('#build-agent-prompt').val('');
    }

    function showError(message) {
        $('#build-agent-error').html('<p><strong>Error:</strong> ' + message + '</p>').show();
    }

    function showSuccess(message) {
        const notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').prepend(notice);

        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 3000);
    }

    // Allow Enter key to trigger generation (with Ctrl/Cmd)
    $('#build-agent-prompt').on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
            $('#build-agent-generate').click();
        }
    });

    // Auto-resize textarea
    $('#build-agent-prompt').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});
