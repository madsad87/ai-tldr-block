import { useBlockProps } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const {
        summary,
        backgroundColor,
        borderRadius,
        expandThreshold,
        showMetadata
    } = attributes;

    // Don't render anything if no summary
    if (!summary) {
        return null;
    }

    const blockProps = useBlockProps.save({
        className: 'ai-tldr-block-frontend',
        style: {
            backgroundColor,
            borderRadius: `${borderRadius}px`
        }
    });

    const shouldShowExpand = summary.length > expandThreshold;

    return (
        <div {...blockProps}>
            <div className="ai-tldr-summary-container">
                <div className="ai-tldr-header">
                    <h4 className="ai-tldr-title">TL;DR</h4>
                </div>
                <div className="ai-tldr-content">
                    <div 
                        className={`ai-tldr-summary ${shouldShowExpand ? 'ai-tldr-expandable' : ''}`}
                        data-expand-threshold={expandThreshold}
                    >
                        {summary}
                    </div>
                    {shouldShowExpand && (
                        <button 
                            className="ai-tldr-expand-toggle"
                            aria-expanded="false"
                            data-expand-text="Read more"
                            data-collapse-text="Read less"
                        >
                            Read more
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
