import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('minimal/test-block', {
    title: 'Minimal Test Block',
    category: 'widgets',
    edit: function() {
        return (
            <div {...useBlockProps()}>
                <p>Minimal Test Block - Working!</p>
            </div>
        );
    },
    save: function() {
        return (
            <div {...useBlockProps.save()}>
                <p>Minimal Test Block - Saved!</p>
            </div>
        );
    }
});
