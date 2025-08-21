// AI TL;DR Block Registration - Fixed version without ES6 imports
console.log('AI TL;DR: Script loaded and executing');

// Check if WordPress objects are available
if (typeof wp === 'undefined') {
    console.error('AI TL;DR: WordPress wp object not available');
} else {
    console.log('AI TL;DR: WordPress wp object available');
}

if (typeof wp.blocks === 'undefined') {
    console.error('AI TL;DR: wp.blocks not available');
} else {
    console.log('AI TL;DR: wp.blocks available');
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
    var __ = wp.i18n.__;
    
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
    
    // Simple edit component
    function Edit() {
        console.log('AI TL;DR: Edit function called');
        return createElement(
            'div',
            useBlockProps(),
            createElement('p', {}, __('AI Post Summary (TL;DR) - Block is working!', 'ai-tldr-block'))
        );
    }
    
    // Simple save component
    function save() {
        console.log('AI TL;DR: Save function called');
        return createElement(
            'div',
            useBlockProps.save(),
            createElement('p', {}, 'AI Post Summary (TL;DR) - Saved content')
        );
    }
    
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
    
    console.log('AI TL;DR: Block registration completed');
})();
