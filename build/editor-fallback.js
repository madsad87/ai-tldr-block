(function () {
    'use strict';

    if (typeof wp === 'undefined' || !wp.blocks || !wp.blocks.registerBlockType) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var createElement = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useEffect = wp.element.useEffect;
    var useState = wp.element.useState;
    var __ = wp.i18n.__;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextareaControl = wp.components.TextareaControl;
    var RangeControl = wp.components.RangeControl;
    var Notice = wp.components.Notice;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var apiFetch = wp.apiFetch;

    registerBlockType('ai-tldr/summary-block', {
        title: __('AI Post Summary (TL;DR)', 'ai-tldr-block'),
        description: __('Generate AI-powered summaries of your post content with customizable length and tone.', 'ai-tldr-block'),
        category: 'widgets',
        icon: 'admin-comments',
        keywords: ['ai', 'summary', 'tldr', 'openai', 'content'],
        supports: {
            html: false,
            multiple: false,
            reusable: false,
            inserter: true
        },
        attributes: {
            postId: { type: 'number', default: 0 },
            summary: { type: 'string', default: '' },
            length: { type: 'string', default: 'medium' },
            tone: { type: 'string', default: 'neutral' },
            isPinned: { type: 'boolean', default: false },
            autoRegen: { type: 'boolean', default: true },
            showMetadata: { type: 'boolean', default: true },
            expandThreshold: { type: 'number', default: 300 },
            backgroundColor: { type: 'string', default: '#f8f9fa' },
            borderRadius: { type: 'number', default: 8 }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var postId = (wp.data && wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId)
                ? wp.data.select('core/editor').getCurrentPostId()
                : 0;

            var blockProps = useBlockProps({ className: 'ai-tldr-block-editor' });
            var _a = useState(false), isLoading = _a[0], setIsLoading = _a[1];
            var _b = useState(false), isGenerating = _b[0], setIsGenerating = _b[1];
            var _c = useState(''), error = _c[0], setError = _c[1];
            var _d = useState(attributes.summary || ''), editedSummary = _d[0], setEditedSummary = _d[1];

            useEffect(function () {
                setEditedSummary(attributes.summary || '');
            }, [attributes.summary]);

            useEffect(function () {
                if (postId && postId !== attributes.postId) {
                    setAttributes({ postId: postId });
                }
            }, [postId]);

            useEffect(function () {
                if (!postId || !apiFetch) {
                    return;
                }

                setIsLoading(true);
                setError('');

                apiFetch({ path: '/ai-tldr/v1/summary/' + postId, method: 'GET' })
                    .then(function (response) {
                        if (!response) return;

                        if (response.exists) {
                            setAttributes({
                                summary: response.summary || '',
                                length: response.metadata && response.metadata.length ? response.metadata.length : attributes.length,
                                tone: response.metadata && response.metadata.tone ? response.metadata.tone : attributes.tone,
                                autoRegen: !(response.metadata && response.metadata.auto_regen === 'false')
                            });
                        } else if (response.metadata && response.metadata.auto_regen !== undefined) {
                            setAttributes({ autoRegen: response.metadata.auto_regen !== 'false' });
                        }
                    })
                    .catch(function () {
                        setError(__('Could not load existing summary.', 'ai-tldr-block'));
                    })
                    .finally(function () {
                        setIsLoading(false);
                    });
            }, [postId]);

            function generateSummary() {
                if (!postId || !apiFetch) {
                    setError(__('No post ID available yet. Save draft and try again.', 'ai-tldr-block'));
                    return;
                }

                setIsGenerating(true);
                setError('');

                apiFetch({
                    path: '/ai-tldr/v1/generate',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        length: attributes.length,
                        tone: attributes.tone,
                        force_regenerate: !!attributes.summary
                    }
                }).then(function (response) {
                    if (response && response.success) {
                        setAttributes({ summary: response.summary || '' });
                        setEditedSummary(response.summary || '');
                        return;
                    }

                    setError((response && response.error) ? response.error : __('Failed to generate summary.', 'ai-tldr-block'));
                }).catch(function (err) {
                    setError((err && err.message) ? err.message : __('Failed to generate summary.', 'ai-tldr-block'));
                }).finally(function () {
                    setIsGenerating(false);
                });
            }

            function saveSummary() {
                if (!postId || !apiFetch) {
                    return;
                }

                setIsLoading(true);
                setError('');

                apiFetch({
                    path: '/ai-tldr/v1/update',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        summary: editedSummary,
                        auto_regen: !!attributes.autoRegen
                    }
                }).then(function (response) {
                    if (response && response.success) {
                        setAttributes({ summary: editedSummary });
                        return;
                    }

                    setError((response && response.error) ? response.error : __('Failed to save summary.', 'ai-tldr-block'));
                }).catch(function (err) {
                    setError((err && err.message) ? err.message : __('Failed to save summary.', 'ai-tldr-block'));
                }).finally(function () {
                    setIsLoading(false);
                });
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('TL;DR Settings', 'ai-tldr-block'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Length', 'ai-tldr-block'),
                            value: attributes.length,
                            options: [
                                { label: __('Short', 'ai-tldr-block'), value: 'short' },
                                { label: __('Medium', 'ai-tldr-block'), value: 'medium' },
                                { label: __('Bullets', 'ai-tldr-block'), value: 'bullets' }
                            ],
                            onChange: function (value) { setAttributes({ length: value }); }
                        }),
                        createElement(SelectControl, {
                            label: __('Tone', 'ai-tldr-block'),
                            value: attributes.tone,
                            options: [
                                { label: __('Neutral', 'ai-tldr-block'), value: 'neutral' },
                                { label: __('Executive', 'ai-tldr-block'), value: 'executive' },
                                { label: __('Casual', 'ai-tldr-block'), value: 'casual' }
                            ],
                            onChange: function (value) { setAttributes({ tone: value }); }
                        }),
                        createElement(ToggleControl, {
                            label: __('Show metadata', 'ai-tldr-block'),
                            checked: !!attributes.showMetadata,
                            onChange: function (value) { setAttributes({ showMetadata: value }); }
                        }),
                        createElement(ToggleControl, {
                            label: __('Auto-regenerate', 'ai-tldr-block'),
                            checked: !!attributes.autoRegen,
                            onChange: function (value) { setAttributes({ autoRegen: value }); }
                        }),
                        createElement(RangeControl, {
                            label: __('Expand threshold (characters)', 'ai-tldr-block'),
                            value: attributes.expandThreshold || 300,
                            min: 100,
                            max: 1000,
                            step: 10,
                            onChange: function (value) { setAttributes({ expandThreshold: value }); }
                        })
                    )
                ),
                createElement(
                    'div',
                    blockProps,
                    createElement('h4', null, __('TL;DR', 'ai-tldr-block')),
                    createElement(
                        Notice,
                        { status: 'warning', isDismissible: false },
                        __('Fallback editor mode is active because compiled block assets were not detected.', 'ai-tldr-block')
                    ),
                    error ? createElement(Notice, { status: 'error', isDismissible: false }, error) : null,
                    createElement(
                        'div',
                        { style: { display: 'flex', gap: '8px', marginBottom: '12px' } },
                        createElement(
                            Button,
                            { variant: 'primary', onClick: generateSummary, disabled: isGenerating || isLoading },
                            isGenerating ? createElement(Spinner, null) : null,
                            isGenerating
                                ? __('Generating…', 'ai-tldr-block')
                                : (attributes.summary ? __('Regenerate AI Summary', 'ai-tldr-block') : __('Generate AI Summary', 'ai-tldr-block'))
                        ),
                        createElement(
                            Button,
                            { variant: 'secondary', onClick: saveSummary, disabled: isLoading || !editedSummary },
                            __('Save Summary', 'ai-tldr-block')
                        )
                    ),
                    createElement(TextareaControl, {
                        label: __('Summary', 'ai-tldr-block'),
                        help: __('You can generate with AI, then edit and save.', 'ai-tldr-block'),
                        value: editedSummary,
                        onChange: function (value) { setEditedSummary(value); }
                    })
                )
            );
        },
        save: function () {
            return null;
        }
    });
})();
