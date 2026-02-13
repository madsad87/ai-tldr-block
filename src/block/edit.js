import { __ } from '@wordpress/i18n';
import { 
    useBlockProps, 
    InspectorControls,
    PanelColorSettings 
} from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    ToggleControl,
    Button,
    ButtonGroup,
    TextareaControl,
    Spinner,
    Notice,
    RangeControl,
    Flex,
    FlexItem,
    Card,
    CardBody,
    CardHeader
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes, context }) {
    const {
        summary,
        length,
        tone,
        isPinned,
        autoRegen,
        showMetadata,
        expandThreshold,
        backgroundColor,
        borderRadius
    } = attributes;

    const [isGenerating, setIsGenerating] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const [metadata, setMetadata] = useState({});
    const [editedSummary, setEditedSummary] = useState(summary);
    const [isEditing, setIsEditing] = useState(false);

    // Get current post ID
    const postId = useSelect((select) => {
        return select('core/editor').getCurrentPostId();
    }, []);

    // Update postId attribute when it changes
    useEffect(() => {
        if (postId && postId !== attributes.postId) {
            setAttributes({ postId });
        }
    }, [postId, attributes.postId, setAttributes]);

    // Load existing summary on mount
    useEffect(() => {
        if (postId) {
            loadSummary();
        }
    }, [postId]);

    // Update edited summary when summary attribute changes
    useEffect(() => {
        setEditedSummary(summary);
    }, [summary]);

    const blockProps = useBlockProps({
        className: 'ai-tldr-block-editor',
        style: {
            backgroundColor,
            borderRadius: `${borderRadius}px`,
            padding: '20px',
            border: '1px solid #ddd'
        }
    });

    const loadSummary = async () => {
        if (!postId) return;

        setIsLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: `/ai-tldr/v1/summary/${postId}`,
                method: 'GET'
            });

            if (response.exists) {
                setAttributes({ 
                    summary: response.summary,
                    length: response.metadata.length || 'medium',
                    tone: response.metadata.tone || 'neutral',
                    isPinned: response.metadata.is_pinned === 'true',
                    autoRegen: response.metadata.auto_regen !== 'false'
                });
                setMetadata(response.metadata);
            } else if (response.metadata && response.metadata.auto_regen !== undefined) {
                setAttributes({ autoRegen: response.metadata.auto_regen !== 'false' });
                setMetadata(response.metadata);
            }
        } catch (err) {
            setError(__('Failed to load existing summary', 'ai-tldr-block'));
        } finally {
            setIsLoading(false);
        }
    };

    const generateSummary = async (forceRegenerate = false) => {
        if (!postId) {
            setError(__('No post ID available', 'ai-tldr-block'));
            return;
        }

        setIsGenerating(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: '/ai-tldr/v1/generate',
                method: 'POST',
                data: {
                    post_id: postId,
                    length,
                    tone,
                    force_regenerate: forceRegenerate
                }
            });

            if (response.success) {
                setAttributes({ summary: response.summary });
                setMetadata({
                    source: response.source,
                    tokens: response.tokens,
                    generated_at: response.generated_at,
                    processing_time: response.processing_time
                });
            } else {
                setError(response.error || __('Failed to generate summary', 'ai-tldr-block'));
            }
        } catch (err) {
            setError(err.message || __('Failed to generate summary', 'ai-tldr-block'));
        } finally {
            setIsGenerating(false);
        }
    };

    const updateSummary = async () => {
        if (!postId || !editedSummary.trim()) return;

        setIsLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: '/ai-tldr/v1/update',
                method: 'POST',
                data: {
                    post_id: postId,
                    summary: editedSummary
                }
            });

            if (response.success) {
                setAttributes({ summary: editedSummary });
                setIsEditing(false);
            } else {
                setError(response.error || __('Failed to update summary', 'ai-tldr-block'));
            }
        } catch (err) {
            setError(err.message || __('Failed to update summary', 'ai-tldr-block'));
        } finally {
            setIsLoading(false);
        }
    };

    const togglePin = async () => {
        if (!postId) return;

        const newPinnedState = !isPinned;
        setIsLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: '/ai-tldr/v1/pin',
                method: 'POST',
                data: {
                    post_id: postId,
                    pinned: newPinnedState
                }
            });

            if (response.success) {
                setAttributes({ isPinned: newPinnedState });
            } else {
                setError(response.error || __('Failed to update pin status', 'ai-tldr-block'));
            }
        } catch (err) {
            setError(err.message || __('Failed to update pin status', 'ai-tldr-block'));
        } finally {
            setIsLoading(false);
        }
    };

    const revertToAI = async () => {
        if (!postId) return;

        setIsLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: '/ai-tldr/v1/revert',
                method: 'POST',
                data: {
                    post_id: postId
                }
            });

            if (response.success) {
                setAttributes({ summary: response.summary });
                setEditedSummary(response.summary);
                setIsEditing(false);
            } else {
                setError(response.error || __('Failed to revert to AI copy', 'ai-tldr-block'));
            }
        } catch (err) {
            setError(err.message || __('Failed to revert to AI copy', 'ai-tldr-block'));
        } finally {
            setIsLoading(false);
        }
    };


    const updateAutoRegen = async (value) => {
        if (!postId) {
            setAttributes({ autoRegen: value });
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: '/ai-tldr/v1/update',
                method: 'POST',
                data: {
                    post_id: postId,
                    auto_regen: value
                }
            });

            if (response.success) {
                const canonicalValue = response.auto_regen !== 'false';
                setAttributes({ autoRegen: canonicalValue });
                setMetadata((current) => ({
                    ...current,
                    auto_regen: response.auto_regen
                }));
            } else {
                setError(response.error || __('Failed to update auto-regeneration setting', 'ai-tldr-block'));
            }
        } catch (err) {
            setError(err.message || __('Failed to update auto-regeneration setting', 'ai-tldr-block'));
        } finally {
            setIsLoading(false);
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    const getSourceLabel = (source) => {
        switch (source) {
            case 'mvdb':
                return __('MVDB (Vector Database)', 'ai-tldr-block');
            case 'raw':
                return __('Raw Content', 'ai-tldr-block');
            default:
                return __('Unknown', 'ai-tldr-block');
        }
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Summary Settings', 'ai-tldr-block')} initialOpen={true}>
                    <SelectControl
                        label={__('Length', 'ai-tldr-block')}
                        value={length}
                        options={[
                            { label: __('Short (1 sentence)', 'ai-tldr-block'), value: 'short' },
                            { label: __('Medium (2-3 sentences)', 'ai-tldr-block'), value: 'medium' },
                            { label: __('Bullets (4-6 points)', 'ai-tldr-block'), value: 'bullets' }
                        ]}
                        onChange={(value) => setAttributes({ length: value })}
                    />

                    <SelectControl
                        label={__('Tone', 'ai-tldr-block')}
                        value={tone}
                        options={[
                            { label: __('Neutral', 'ai-tldr-block'), value: 'neutral' },
                            { label: __('Executive', 'ai-tldr-block'), value: 'executive' },
                            { label: __('Casual', 'ai-tldr-block'), value: 'casual' }
                        ]}
                        onChange={(value) => setAttributes({ tone: value })}
                    />

                    <ToggleControl
                        label={__('Auto-regenerate on post update', 'ai-tldr-block')}
                        checked={autoRegen}
                        onChange={updateAutoRegen}
                        help={__('Automatically update summary when post content changes', 'ai-tldr-block')}
                    />

                    <ToggleControl
                        label={__('Show metadata', 'ai-tldr-block')}
                        checked={showMetadata}
                        onChange={(value) => setAttributes({ showMetadata: value })}
                        help={__('Display generation timestamp, source, and token count', 'ai-tldr-block')}
                    />
                </PanelBody>

                <PanelBody title={__('Appearance', 'ai-tldr-block')} initialOpen={false}>
                    <PanelColorSettings
                        title={__('Background Color', 'ai-tldr-block')}
                        colorSettings={[
                            {
                                value: backgroundColor,
                                onChange: (value) => setAttributes({ backgroundColor: value || '#f8f9fa' }),
                                label: __('Background Color', 'ai-tldr-block')
                            }
                        ]}
                    />

                    <RangeControl
                        label={__('Border Radius', 'ai-tldr-block')}
                        value={borderRadius}
                        onChange={(value) => setAttributes({ borderRadius: value })}
                        min={0}
                        max={20}
                    />

                    <RangeControl
                        label={__('Expand threshold (characters)', 'ai-tldr-block')}
                        value={expandThreshold}
                        onChange={(value) => setAttributes({ expandThreshold: value })}
                        min={100}
                        max={1000}
                        help={__('Show expand/collapse for summaries longer than this', 'ai-tldr-block')}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <Card>
                    <CardHeader>
                        <Flex justify="space-between" align="center">
                            <h3>{__('AI Post Summary (TL;DR)', 'ai-tldr-block')}</h3>
                            <Flex gap={2}>
                                {summary && (
                                    <Button
                                        icon={isPinned ? 'unlock' : 'lock'}
                                        label={isPinned ? __('Unpin summary', 'ai-tldr-block') : __('Pin summary', 'ai-tldr-block')}
                                        onClick={togglePin}
                                        disabled={isLoading}
                                        variant={isPinned ? 'primary' : 'secondary'}
                                        size="small"
                                    />
                                )}
                                <ButtonGroup>
                                    <Button
                                        variant="primary"
                                        onClick={() => generateSummary(false)}
                                        disabled={isGenerating || isLoading}
                                        size="small"
                                    >
                                        {isGenerating ? <Spinner /> : null}
                                        {summary ? __('Regenerate', 'ai-tldr-block') : __('Generate', 'ai-tldr-block')}
                                    </Button>
                                    {summary && (
                                        <Button
                                            variant="secondary"
                                            onClick={() => generateSummary(true)}
                                            disabled={isGenerating || isLoading}
                                            size="small"
                                        >
                                            {__('Force Regenerate', 'ai-tldr-block')}
                                        </Button>
                                    )}
                                </ButtonGroup>
                            </Flex>
                        </Flex>
                    </CardHeader>

                    <CardBody>
                        {error && (
                            <Notice status="error" isDismissible onRemove={() => setError(null)}>
                                {error}
                            </Notice>
                        )}

                        {isPinned && (
                            <Notice status="info" isDismissible={false}>
                                {__('This summary is pinned and will not auto-update', 'ai-tldr-block')}
                            </Notice>
                        )}

                        {isLoading && !isGenerating && (
                            <div style={{ textAlign: 'center', padding: '20px' }}>
                                <Spinner />
                            </div>
                        )}

                        {summary ? (
                            <div>
                                {isEditing ? (
                                    <div>
                                        <TextareaControl
                                            value={editedSummary}
                                            onChange={setEditedSummary}
                                            rows={6}
                                            style={{ marginBottom: '10px' }}
                                        />
                                        <Flex gap={2}>
                                            <Button
                                                variant="primary"
                                                onClick={updateSummary}
                                                disabled={isLoading || !editedSummary.trim()}
                                                size="small"
                                            >
                                                {__('Save', 'ai-tldr-block')}
                                            </Button>
                                            <Button
                                                variant="secondary"
                                                onClick={() => {
                                                    setEditedSummary(summary);
                                                    setIsEditing(false);
                                                }}
                                                size="small"
                                            >
                                                {__('Cancel', 'ai-tldr-block')}
                                            </Button>
                                            {metadata.ai_copy && (
                                                <Button
                                                    variant="tertiary"
                                                    onClick={revertToAI}
                                                    disabled={isLoading}
                                                    size="small"
                                                >
                                                    {__('Revert to AI', 'ai-tldr-block')}
                                                </Button>
                                            )}
                                        </Flex>
                                    </div>
                                ) : (
                                    <div>
                                        <div 
                                            style={{ 
                                                marginBottom: '15px',
                                                lineHeight: '1.6',
                                                fontSize: '16px'
                                            }}
                                            dangerouslySetInnerHTML={{ 
                                                __html: summary.replace(/\n/g, '<br>') 
                                            }}
                                        />
                                        <Flex gap={2}>
                                            <Button
                                                variant="secondary"
                                                onClick={() => setIsEditing(true)}
                                                size="small"
                                            >
                                                {__('Edit', 'ai-tldr-block')}
                                            </Button>
                                        </Flex>
                                    </div>
                                )}

                                {showMetadata && metadata && (
                                    <div style={{ 
                                        marginTop: '15px', 
                                        padding: '10px', 
                                        backgroundColor: '#f0f0f0', 
                                        borderRadius: '4px',
                                        fontSize: '12px',
                                        color: '#666'
                                    }}>
                                        <strong>{__('Metadata:', 'ai-tldr-block')}</strong>
                                        <br />
                                        {metadata.generated_at && (
                                            <>
                                                {__('Generated:', 'ai-tldr-block')} {formatDate(metadata.generated_at)}
                                                <br />
                                            </>
                                        )}
                                        {metadata.source && (
                                            <>
                                                {__('Source:', 'ai-tldr-block')} {getSourceLabel(metadata.source)}
                                                <br />
                                            </>
                                        )}
                                        {metadata.tokens && (
                                            <>
                                                {__('Tokens:', 'ai-tldr-block')} {metadata.tokens}
                                                <br />
                                            </>
                                        )}
                                        {metadata.processing_time && (
                                            <>
                                                {__('Processing time:', 'ai-tldr-block')} {Math.round(metadata.processing_time * 1000)}ms
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
                                <p>{__('No summary generated yet.', 'ai-tldr-block')}</p>
                                <p style={{ fontSize: '14px' }}>
                                    {__('Click "Generate" to create an AI-powered summary of your post content.', 'ai-tldr-block')}
                                </p>
                            </div>
                        )}
                    </CardBody>
                </Card>
            </div>
        </>
    );
}
