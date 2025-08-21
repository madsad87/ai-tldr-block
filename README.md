# AI Post Summary (TL;DR) Block

A WordPress Gutenberg block plugin that generates AI-powered post summaries with WP Engine MVDB integration and OpenAI.

## Features

### 🤖 AI-Powered Summarization
- **OpenAI Integration**: Uses GPT-4o-mini for high-quality summaries
- **Multiple Lengths**: Short (1 sentence), Medium (2-3 sentences), Bullets (4-6 points)
- **Tone Control**: Neutral, Executive, or Casual tone options
- **Smart Prompting**: Context-aware prompts optimized for each length and tone

### 🔍 MVDB Integration
- **Vector Database Support**: Integrates with WP Engine's Managed Vector Database
- **Intelligent Fallback**: Falls back to raw content when MVDB unavailable
- **Content Grounding**: Uses relevant chunks from indexed content for better summaries
- **Multi-Schema Support**: Adapts to different MVDB endpoint configurations

### ⚡ Performance & Automation
- **Background Processing**: WP-Cron integration for automatic regeneration
- **Content Change Detection**: SHA256 hashing to detect content changes
- **Rate Limiting**: 3 requests per minute per user to prevent abuse
- **Caching**: Stores summaries in post meta for fast rendering

### 🎨 User Experience
- **Gutenberg Block**: Native WordPress block editor integration
- **Pin/Unpin**: Lock summaries to prevent auto-updates
- **Manual Editing**: Edit summaries with revert-to-AI option
- **Expand/Collapse**: Automatic expand/collapse for long summaries
- **Responsive Design**: Mobile-friendly with accessibility support

### 🔒 Security & Privacy
- **Capability Checks**: Proper WordPress permission handling
- **Nonce Verification**: CSRF protection for all requests
- **Input Sanitization**: Secure handling of user input
- **API Key Protection**: Secure storage of sensitive credentials

## Installation

1. **Download/Clone**: Get the plugin files
2. **Upload**: Place in `/wp-content/plugins/ai-tldr-block/`
3. **Activate**: Enable through WordPress admin
4. **Configure**: Set up OpenAI API key and optional MVDB settings

## Configuration

### OpenAI Setup
1. Get an API key from [OpenAI Platform](https://platform.openai.com/)
2. Go to WordPress Admin → Settings → AI TL;DR Settings
3. Enter your OpenAI API key
4. Configure model settings (defaults to gpt-4o-mini)

### MVDB Setup (Optional)
1. Ensure WP Engine AI Toolkit is installed and configured
2. Enter your MVDB endpoint URL
3. Add API key if required
4. Test connection using the debug panel

## Usage

### Adding the Block
1. Edit any post or page
2. Add the "AI Post Summary (TL;DR)" block
3. Configure length, tone, and appearance settings
4. Click "Generate" to create your first summary

### Block Settings
- **Length**: Choose between Short, Medium, or Bullets format
- **Tone**: Select Neutral, Executive, or Casual tone
- **Auto-regenerate**: Enable/disable automatic updates on content changes
- **Appearance**: Customize background color, border radius, and expand threshold

### Managing Summaries
- **Pin**: Lock summaries to prevent automatic updates
- **Edit**: Manually modify generated summaries
- **Revert**: Return to original AI-generated version
- **Regenerate**: Force creation of new summary

## Technical Details

### File Structure
```
ai-tldr-block/
├── ai-tldr-block.php          # Main plugin file
├── includes/                  # Core PHP classes
│   ├── class-TLDR-Service.php # OpenAI integration & summary logic
│   ├── class-TLDR-Rest.php    # REST API endpoints
│   ├── class-TLDR-Cron.php    # Background processing
│   └── class-TLDR-Content.php # Content processing & MVDB
├── src/block/                 # Block source files
│   ├── block.json            # Block configuration
│   ├── edit.js               # Editor interface
│   ├── save.js               # Frontend save
│   ├── style.scss            # Public styles
│   └── editor.scss           # Editor styles
├── build/                    # Compiled assets
│   ├── *.js, *.css          # Built files
│   ├── render.php           # Server-side rendering
│   └── frontend.js          # Frontend interactions
└── admin/                   # Admin interface
    └── views/               # Admin page templates
```

### Data Storage
All summary data is stored in WordPress post meta:

- `_ai_tldr_summary`: The summary text
- `_ai_tldr_source`: Source type (mvdb/raw)
- `_ai_tldr_is_pinned`: Pin status
- `_ai_tldr_len`: Length setting
- `_ai_tldr_tone`: Tone setting
- `_ai_tldr_content_hash`: Content change detection
- `_ai_tldr_generated_at`: Generation timestamp
- `_ai_tldr_ai_copy`: Original AI version for revert
- `_ai_tldr_tokens`: Token usage count

### REST API Endpoints
- `POST /wp-json/ai-tldr/v1/generate` - Generate summary
- `GET /wp-json/ai-tldr/v1/summary/{post_id}` - Get summary
- `POST /wp-json/ai-tldr/v1/update` - Update summary
- `POST /wp-json/ai-tldr/v1/pin` - Pin/unpin summary
- `POST /wp-json/ai-tldr/v1/revert` - Revert to AI copy
- `DELETE /wp-json/ai-tldr/v1/delete` - Delete summary

### Hooks & Filters
```php
// Customize summarization prompt
add_filter('tldr_summarization_prompt', function($prompt, $content, $options) {
    // Modify prompt
    return $prompt;
}, 10, 3);

// Process after summary generation
add_action('tldr_summary_generated', function($post_id, $summary, $metadata) {
    // Custom processing
}, 10, 3);

// Modify MVDB query
add_filter('tldr_mvdb_query_variables', function($variables, $post_id) {
    // Customize query
    return $variables;
}, 10, 2);
```

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **OpenAI API**: Valid API key required
- **WP Engine AI Toolkit**: Optional, for MVDB features

## Troubleshooting

### Common Issues

**"No summary generated"**
- Check OpenAI API key configuration
- Verify post has sufficient content
- Check error logs for API issues

**"MVDB connection failed"**
- Verify WP Engine AI Toolkit is installed
- Check MVDB endpoint URL and API key
- Use the debug panel to test connection

**"Rate limit exceeded"**
- Wait 1 minute between generation attempts
- Check if multiple users are generating simultaneously

### Debug Mode
Enable WordPress debug logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for detailed error messages.

## Development

### Building Assets
For development, you can modify files in `src/` and copy to `build/`:

```bash
# Copy JavaScript files
cp src/block/edit.js build/
cp src/block/save.js build/

# Copy and rename CSS files
cp src/block/style.scss build/style.css
cp src/block/editor.scss build/editor.css
```

### Testing
1. Create test posts with various content types
2. Test different length and tone combinations
3. Verify MVDB integration with indexed content
4. Test expand/collapse functionality
5. Validate accessibility with screen readers

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

GPL v2 or later. See LICENSE file for details.

## Support

For support and bug reports:
1. Check the troubleshooting section
2. Review WordPress error logs
3. Test API connections
4. Create an issue with detailed information

---

**AI Post Summary (TL;DR) Block** - Intelligent content summarization for WordPress with enterprise-grade MVDB integration.
