/**
 * Frontend JavaScript for AI TL;DR Block
 * Handles expand/collapse functionality
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTLDRBlocks);
    } else {
        initTLDRBlocks();
    }

    function initTLDRBlocks() {
        const tldrBlocks = document.querySelectorAll('.ai-tldr-block-frontend');
        
        tldrBlocks.forEach(function(block) {
            initExpandCollapse(block);
        });
    }

    function initExpandCollapse(block) {
        const expandToggle = block.querySelector('.ai-tldr-expand-toggle');
        const summary = block.querySelector('.ai-tldr-summary');
        
        if (!expandToggle || !summary) {
            return;
        }

        const expandText = expandToggle.getAttribute('data-expand-text') || 'Read more';
        const collapseText = expandToggle.getAttribute('data-collapse-text') || 'Read less';
        
        expandToggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const isExpanded = expandToggle.getAttribute('aria-expanded') === 'true';
            
            if (isExpanded) {
                // Collapse
                summary.classList.remove('expanded');
                summary.classList.add('collapsing');
                expandToggle.setAttribute('aria-expanded', 'false');
                expandToggle.textContent = expandText;
                
                // Remove collapsing class after animation
                setTimeout(function() {
                    summary.classList.remove('collapsing');
                }, 300);
                
            } else {
                // Expand
                summary.classList.remove('collapsing');
                summary.classList.add('expanded', 'expanding');
                expandToggle.setAttribute('aria-expanded', 'true');
                expandToggle.textContent = collapseText;
                
                // Remove expanding class after animation
                setTimeout(function() {
                    summary.classList.remove('expanding');
                }, 300);
            }
        });

        // Handle keyboard navigation
        expandToggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                expandToggle.click();
            }
        });
    }

    // Handle dynamic content (for AJAX-loaded content)
    function handleDynamicContent() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if the added node is a TLDR block
                        if (node.classList && node.classList.contains('ai-tldr-block-frontend')) {
                            initExpandCollapse(node);
                        }
                        
                        // Check for TLDR blocks within the added node
                        const tldrBlocks = node.querySelectorAll('.ai-tldr-block-frontend');
                        tldrBlocks.forEach(function(block) {
                            initExpandCollapse(block);
                        });
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Initialize dynamic content handling if MutationObserver is supported
    if (window.MutationObserver) {
        handleDynamicContent();
    }

    // Expose initialization function globally for manual initialization
    window.initAITLDRBlocks = initTLDRBlocks;

})();
