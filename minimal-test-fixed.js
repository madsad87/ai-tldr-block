// Self-contained block registration without ES6 imports
console.log('MINIMAL TEST JS: Script loaded and executing');

// Check if WordPress objects are available
if (typeof wp === 'undefined') {
    console.error('MINIMAL TEST JS: WordPress wp object not available');
} else {
    console.log('MINIMAL TEST JS: WordPress wp object available');
}

if (typeof wp.blocks === 'undefined') {
    console.error('MINIMAL TEST JS: wp.blocks not available');
} else {
    console.log('MINIMAL TEST JS: wp.blocks available');
}

// Use the global wp object instead of imports
(function() {
    'use strict';
    
    console.log('MINIMAL TEST JS: Starting block registration');
    
    // Check if required functions exist
    if (!wp.blocks || !wp.blocks.registerBlockType) {
        console.error('MINIMAL TEST JS: registerBlockType not available');
        return;
    }
    
    if (!wp.blockEditor || !wp.blockEditor.useBlockProps) {
        console.error('MINIMAL TEST JS: useBlockProps not available');
        return;
    }
    
    if (!wp.element || !wp.element.createElement) {
        console.error('MINIMAL TEST JS: React createElement not available');
        return;
    }
    
    var registerBlockType = wp.blocks.registerBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var createElement = wp.element.createElement;
    
    console.log('MINIMAL TEST JS: All required functions available, registering block');
    
    registerBlockType('minimal/test-block-fixed', {
        title: 'Minimal Test Block Fixed',
        category: 'widgets',
        icon: 'admin-tools',
        edit: function() {
            console.log('MINIMAL TEST JS: Edit function called');
            return createElement(
                'div',
                useBlockProps(),
                createElement('p', {}, 'Minimal Test Block Fixed - Working!')
            );
        },
        save: function() {
            console.log('MINIMAL TEST JS: Save function called');
            return createElement(
                'div',
                useBlockProps.save(),
                createElement('p', {}, 'Minimal Test Block Fixed - Saved!')
            );
        }
    });
    
    console.log('MINIMAL TEST JS: Block registration completed');
})();
