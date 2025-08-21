import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

// Simple edit component
function Edit() {
    return (
        <div {...useBlockProps()}>
            <p>{__('AI Post Summary (TL;DR) - Block is working!', 'ai-tldr-block')}</p>
        </div>
    );
}

// Simple save component
function save() {
    return (
        <div {...useBlockProps.save()}>
            <p>AI Post Summary (TL;DR) - Saved content</p>
        </div>
    );
}

// Custom icon for the block
const tldrIcon = (
    <svg
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
        focusable="false"
    >
        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm.5 16c0 .3-.2.5-.5.5H5c-.3 0-.5-.2-.5-.5V7h15v12zM9 10H7v2h2v-2zm0 4H7v2h2v-2zm4-4h-2v2h2v-2zm4 0h-2v2h2v-2zm-4 4h-2v2h2v-2zm4 0h-2v2h2v-2z"></path>
    </svg>
);

// Register the block
registerBlockType('ai-tldr/summary-block', {
    title: __('AI Post Summary (TL;DR)', 'ai-tldr-block'),
    description: __('Generate AI-powered summaries of your post content with customizable length and tone.', 'ai-tldr-block'),
    category: 'widgets',
    icon: tldrIcon,
    keywords: ['ai', 'summary', 'tldr', 'openai', 'content'],
    supports: {
        html: false,
        multiple: false,
        reusable: false,
        inserter: true
    },
    edit: Edit,
    save: save
});
