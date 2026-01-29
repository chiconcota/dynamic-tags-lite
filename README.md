# Dynamic Tags Lite

Lightweight and powerful dynamic tags for the WordPress Block Editor (Gutenberg).

## Features

- **Gutenberg Integration**: Add dynamic content to Paragraph, Heading, Image, Video, and Button blocks directly from the editor toolbar.
- **Data Sources**: 
  - **Post Meta**: Fetch any custom field value by its key.
  - **Post Data**: Fetch core post fields like Date, Author, Categories, Permalink, etc.
- **Advanced Formatting (v1.7.1)**:
  - **Prefix & Suffix**: Add text before or after dynamic values.
  - **Date Formatting**: Choose from common date formats or let the plugin handle local date styles.
  - **Number Formatting**: Set decimal places for numeric values (prices, counts, IDs).
- **Inline Dynamic Links**: Turn any text selection into a dynamic link that updates automatically.
- **Frontend Performance**: Zero-dependency frontend rendering.

## Version History

### v1.7.1
- Fix: Critical dashboard crash and editor syntax errors.
- Improved: Robust text replacement logic for placeholders like `%% key %%`.
- Added: Enhanced date parsing for European/Vietnamese formats (d/m/Y).

### v1.7.0
- Added: Phase 2 features (Prefix, Suffix, Date & Number formatting).
- Added: Advanced Settings UI in block popover.

## Installation

1. Upload the `dynamic-tags-lite` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Look for the **Database icon** in the Gutenberg block toolbar.

## License

GPL-2.0+
