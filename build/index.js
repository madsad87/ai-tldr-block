// AI TL;DR Block Registration - Full functionality version
console.log('AI TL;DR: Script loaded and executing');

// Check if WordPress objects are available
if (typeof wp === 'undefined') {
    console.error('AI TL;DR: WordPress wp object not available');
} else {
    console.log('AI TL;DR: WordPress wp object available');
}

// Use the global wp object instead of imports
(function() {
    'use strict';
    
    console.log('AI TL;DR: Starting block registration');
    
    // Check if required functions exist
    if (!wp.blocks || !wp.blocks.registerBlockType) {
        console.error('AI TL;DR: registerBlockType not available');
        return;
    }
    
    if (!wp.blockEditor || !wp.blockEditor.useBlockProps) {
        console.error('AI TL;DR: useBlockProps not available');
        return;
    }
    
    if (!wp.element || !wp.element.createElement) {
        console.error('AI TL;DR: React createElement not available');
        return;
    }
    
    if (!wp.i18n || !wp.i18n.__) {
        console.error('AI TL;DR: i18n not available');
        return;
    }
    
    var registerBlockType = wp.blocks.registerBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var createElement = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var __ = wp.i18n.__;
    var Button = wp.components.Button;
    var SelectControl = wp.components.SelectControl;
    var TextareaControl = wp.components.TextareaControl;
    var ToggleControl = wp.components.ToggleControl;
    var PanelBody = wp.components.PanelBody;
    var PanelRow = wp.components.PanelRow;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useSelect = wp.data.useSelect;
    var apiFetch = wp.apiFetch;
    
    console.log('AI TL;DR: All required functions available, registering block');
    
    // Custom icon for the block
    var tldrIcon = createElement(
        'svg',
        {
            viewBox: "0 0 24 24",
            xmlns: "http://www.w3.org/2000/svg",
            'aria-hidden': "true",
            focusable: "false"
        },
        createElement('path', {
            d: "M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm.5 16c0 .3-.2.5-.5.5H5c-.3 0-.5-.2-.5-.5V7h15v12zM9 10H7v2h2v-2zm0 4H7v2h2v-2zm4-4h-2v2h2v-2zm4 0h-2v2h2v-2zm-4 4h-2v2h2v-2zm4 0h-2v2h2v-2z"
        })
    );
    
    // Main edit component with full functionality
    function Edit(props) {
        console.log('AI TL;DR: Edit function called');
        
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var clientId = props.clientId;
        
        // State management
        var summaryState = useState(attributes.summary || '');
        var summary = summaryState[0];
        var setSummary = summaryState[1];
        
        var loadingState = useState(false);
        var isLoading = loadingState[0];
        var setIsLoading = loadingState[1];
        
        var errorState = useState('');
        var error = errorState[0];
        var setError = errorState[1];
        
        var lastGeneratedState = useState(attributes.lastGenerated || '');
        var lastGenerated = lastGeneratedState[0];
        var setLastGenerated = lastGeneratedState[1];
        
        // Get current post data
        var postData = useSelect(function(select) {
            var editor = select('core/editor');
            if (!editor) return {};
            
            return {
                postId: editor.getCurrentPostId(),
                postContent: editor.getEditedPostContent(),
                postTitle: editor.getEditedPostAttribute('title')
            };
        }, []);
        
        // Settings with defaults
        var length = attributes.length || 'medium';
        var tone = attributes.tone || 'neutral';
        var isPinned = attributes.isPinned || false;
        var autoRegenerate = attributes.autoRegenerate || false;
        
        // Generate summary function
        function generateSummary() {
            if (!postData.postId) {
                setError(__('Please save the post first before generating a summary.', 'ai-tldr-block'));
                return;
            }
            
            setIsLoading(true);
            setError('');
            
            var requestData = {
                post_id: postData.postId,
                length: length,
                tone: tone,
                force_regenerate: true
            };
            
            apiFetch({
                path: '/ai-tldr/v1/generate',
                method: 'POST',
                data: requestData
            }).then(function(response) {
                if (response.success) {
                    setSummary(response.data.summary);
                    setLastGenerated(response.data.generated_at);
                    setAttributes({
                        summary: response.data.summary,
                        lastGenerated: response.data.generated_at,
                        source: response.data.source,
                        tokenCount: response.data.token_count
                    });
                    setError('');
                } else {
                    setError(response.data.message || __('Failed to generate summary', 'ai-tldr-block'));
                }
            }).catch(function(err) {
                console.error('AI TL;DR: Generation error:', err);
                setError(__('Error generating summary. Please check your API settings.', 'ai-tldr-block'));
            }).finally(function() {
                setIsLoading(false);
            });
        }
        
        // Pin/Unpin summary
        function togglePin() {
            var newPinned = !isPinned;
            setAttributes({ isPinned: newPinned });
            
            if (postData.postId) {
                apiFetch({
                    path: '/ai-tldr/v1/pin',
                    method: 'POST',
                    data: {
                        post_id: postData.postId,
                        pinned: newPinned
                    }
                }).catch(function(err) {
                    console.error('AI TL;DR: Pin error:', err);
                });
            }
        }
        
        // Update summary text
        function updateSummary(newSummary) {
            setSummary(newSummary);
            setAttributes({ summary: newSummary });
        }
        
        // Update settings
        function updateLength(newLength) {
            setAttributes({ length: newLength });
        }
        
        function updateTone(newTone) {
            setAttributes({ tone: newTone });
        }
        
        function updateAutoRegenerate(newAuto) {
            setAttributes({ autoRegenerate: newAuto });
        }
        
        // Format timestamp
        function formatTimestamp(timestamp) {
            if (!timestamp) return __('Never', 'ai-tldr-block');
            try {
                return new Date(timestamp).toLocaleString();
            } catch (e) {
                return timestamp;
            }
        }
        
        // Main editor interface
        var blockProps = useBlockProps({
            className: 'ai-tldr-block-editor'
        });
        
        return createElement(
            'div',
            blockProps,
            [
                // Inspector Controls (Sidebar)
                createElement(
                    InspectorControls,
                    { key: 'inspector' },
                    [
                        createElement(
                            PanelBody,
                            {
                                key: 'settings',
                                title: __('Summary Settings', 'ai-tldr-block'),
                                initialOpen: true
                            },
                            [
                                createElement(SelectControl, {
                                    key: 'length',
                                    label: __('Length', 'ai-tldr-block'),
                                    value: length,
                                    options: [
                                        { label: __('Short (1-liner)', 'ai-tldr-block'), value: 'short' },
                                        { label: __('Medium (3 sentences)', 'ai-tldr-block'), value: 'medium' },
                                        { label: __('Bullets (4-6 points)', 'ai-tldr-block'), value: 'bullets' }
                                    ],
                                    onChange: updateLength
                                }),
                                createElement(SelectControl, {
                                    key: 'tone',
                                    label: __('Tone', 'ai-tldr-block'),
                                    value: tone,
                                    options: [
                                        { label: __('Neutral', 'ai-tldr-block'), value: 'neutral' },
                                        { label: __('Executive', 'ai-tldr-block'), value: 'executive' },
                                        { label: __('Casual', 'ai-tldr-block'), value: 'casual' }
                                    ],
                                    onChange: updateTone
                                }),
                                createElement(ToggleControl, {
                                    key: 'pinned',
                                    label: __('Pin Summary', 'ai-tldr-block'),
                                    help: isPinned ? 
                                        __('Summary is pinned and won\'t auto-update', 'ai-tldr-block') : 
                                        __('Summary will auto-update when content changes', 'ai-tldr-block'),
                                    checked: isPinned,
                                    onChange: togglePin
                                }),
                                createElement(ToggleControl, {
                                    key: 'auto',
                                    label: __('Auto-regenerate', 'ai-tldr-block'),
                                    help: __('Automatically regenerate summary when post is updated', 'ai-tldr-block'),
                                    checked: autoRegenerate,
                                    onChange: updateAutoRegenerate
                                })
                            ]
                        ),
                        createElement(
                            PanelBody,
                            {
                                key: 'info',
                                title: __('Summary Info', 'ai-tldr-block'),
                                initialOpen: false
                            },
                            [
                                createElement(PanelRow, { key: 'generated' },
                                    createElement('span', {}, 
                                        __('Last Generated: ', 'ai-tldr-block') + formatTimestamp(lastGenerated)
                                    )
                                ),
                                createElement(PanelRow, { key: 'source' },
                                    createElement('span', {}, 
                                        __('Source: ', 'ai-tldr-block') + (attributes.source === 'mvdb' ? 'Vector Database' : 'Raw Content')
                                    )
                                ),
                                attributes.tokenCount && createElement(PanelRow, { key: 'tokens' },
                                    createElement('span', {}, 
                                        __('Tokens: ', 'ai-tldr-block') + attributes.tokenCount
                                    )
                                )
                            ]
                        )
                    ]
                ),
                
                // Main block content
                createElement(
                    'div',
                    { key: 'content', className: 'ai-tldr-block-content' },
                    [
                        createElement('h3', { key: 'title' }, __('AI Post Summary (TL;DR)', 'ai-tldr-block')),
                        
                        error && createElement(Notice, {
                            key: 'error',
                            status: 'error',
                            isDismissible: true,
                            onRemove: function() { setError(''); }
                        }, error),
                        
                        createElement(
                            'div',
                            { key: 'controls', className: 'ai-tldr-controls' },
                            [
                                createElement(Button, {
                                    key: 'generate',
                                    isPrimary: true,
                                    isBusy: isLoading,
                                    disabled: isLoading || !postData.postId,
                                    onClick: generateSummary
                                }, isLoading ? __('Generating...', 'ai-tldr-block') : 
                                   (summary ? __('Regenerate', 'ai-tldr-block') : __('Generate', 'ai-tldr-block'))),
                                
                                isLoading && createElement(Spinner, { key: 'spinner' })
                            ]
                        ),
                        
                        createElement(TextareaControl, {
                            key: 'summary',
                            label: __('Summary', 'ai-tldr-block'),
                            value: summary,
                            onChange: updateSummary,
                            placeholder: __('Click "Generate" to create an AI-powered summary of your post content...', 'ai-tldr-block'),
                            rows: 6,
                            className: 'ai-tldr-summary-textarea'
                        }),
                        
                        summary && createElement(
                            'div',
                            { key: 'preview', className: 'ai-tldr-preview' },
                            [
                                createElement('h4', { key: 'preview-title' }, __('Preview', 'ai-tldr-block')),
                                createElement('div', { 
                                    key: 'preview-content',
                                    className: 'ai-tldr-preview-content',
                                    dangerouslySetInnerHTML: { __html: summary.replace(/\n/g, '<br>') }
                                })
                            ]
                        )
                    ]
                )
            ]
        );
    }
    
    // Save component - returns null for dynamic rendering
    function save() {
        console.log('AI TL;DR: Save function called');
        return null; // Dynamic block - rendered server-side
    }
    
    // Register the block
    registerBlockType('ai-tldr/summary-block', {
        title: __('AI Post Summary (TL;DR)', 'ai-tldr-block'),
        description: __('Generate AI-powered summaries of your post content with customizable length and tone.', 'ai-tldr-block'),
        category: 'widgets',
        icon: tldrIcon,
        keywords: ['ai', 'summary', 'tldr', 'openai', 'content'],
        attributes: {
            summary: {
                type: 'string',
                default: ''
            },
            length: {
                type: 'string',
                default: 'medium'
            },
            tone: {
                type: 'string',
                default: 'neutral'
            },
            isPinned: {
                type: 'boolean',
                default: false
            },
            autoRegenerate: {
                type: 'boolean',
                default: false
            },
            lastGenerated: {
                type: 'string',
                default: ''
            },
            source: {
                type: 'string',
                default: ''
            },
            tokenCount: {
                type: 'number',
                default: 0
            }
        },
        supports: {
            html: false,
            multiple: false,
            reusable: false,
            inserter: true
        },
        edit: Edit,
        save: save
    });
    
    console.log('AI TL;DR: Block registration completed');
})();
