(function () {
    'use strict';

    if (typeof wp === 'undefined' || !wp.blocks || !wp.blocks.registerBlockType) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var createElement = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var __ = wp.i18n.__;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextareaControl = wp.components.TextareaControl;
    var RangeControl = wp.components.RangeControl;
    var Notice = wp.components.Notice;

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
            var blockProps = useBlockProps({ className: 'ai-tldr-block-editor' });

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
                        __('Fallback editor mode is active because compiled block assets were not detected. The block will still render on the front end and in saved content.', 'ai-tldr-block')
                    ),
                    createElement(TextareaControl, {
                        label: __('Summary', 'ai-tldr-block'),
                        help: __('Optional manual summary for preview/editor content.', 'ai-tldr-block'),
                        value: attributes.summary || '',
                        onChange: function (value) { setAttributes({ summary: value });
                        }
                    })
                )
            );
        },
        save: function () {
            return null;
        }
    });
})();
