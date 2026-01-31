# Dynamic Tags Lite

Lightweight and powerful dynamic tags for the WordPress Block Editor (Gutenberg).

## Table of Contents

- [Why Dynamic Tags Lite?](#why-dynamic-tags-lite)
- [Features](#features)
- [Requirements](#requirements)
- [Supported Blocks](#supported-blocks)
- [How to Use](#how-to-use)
- [Security & Performance](#security--performance)
- [Installation](#installation)
- [Version History](#version-history)
- [License](#license)

## Why Dynamic Tags Lite?

- **Lightweight**: No bloat. No heavy external libraries. Just pure WordPress-native logic.
- **Fast**: All rendering happens server-side with zero-dependency CSS or JS on the frontend.
- **Modern**: Fully integrated with the Gutenberg Block Editor using standard WordPress components.
- **Developer-Ready**: Built with PHP 8.2+ standards and PSR-4 namespacing.

## Features

- **Gutenberg Integration**: Add dynamic content to Paragraph, Heading, Image, Video, and Button blocks directly from the editor toolbar.
- **Live Preview**: See real formatted dynamic values in the editor while you type. Switch back to placeholders anytime with the "Show Original" toggle.
- **Conditional Display (v1.9.0)**: Hide blocks if the dynamic value is empty or based on comparison rules (Equals, Not Equals, Contains, Greater/Less Than).
- **Data Sources**: 
  - **WooCommerce (v1.10.0)**: Fetch product prices, SKU, stock, ratings, and cart information.
  - **Secure Custom Fields (SCF/ACF)**: Pre-loaded dropdown for easy field selection.
  - **Current User**: Display info about the logged-in user (Display Name, Email, ID, User Meta).
  - **Post Data**: Core post fields PLUS Author Bio and Author Social Meta.
  - **Post Meta**: Fetch any custom field value by its key.
- **Advanced Formatting**:
  - **Prefix & Suffix**: Add text before or after dynamic values.
  - **Date Formatting**: Support for local and common formats (e.g., d/m/Y, July 30, 2025).
  - **Number Formatting**: Decimals control for prices and counts.
- **Inline Dynamic Links**: Convert any text selection into a dynamic link.
- **Performance**: Zero-dependency frontend rendering for maximum speed.

## Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher (8.2+ recommended)
- **Optional**: [Secure Custom Fields (SCF)](https://wordpress.org/plugins/advanced-custom-fields/) or ACF for enhanced data source capabilities.
- **Optional**: [WooCommerce](https://wordpress.org/plugins/woocommerce/) for product and shop dynamic data.

## Supported Blocks

Currently, the following core blocks are supported with the dynamic tags icon in the toolbar:
- **Paragraph** (`core/paragraph`)
- **Heading** (`core/heading`)
- **Image** (`core/image`) - Supports dynamic src and dynamic links.
- **Video** (`core/video`) - Supports dynamic src.
- **Button** (`core/button`) - Supports dynamic text.
- **Inline Text**: Any block that supports the RichText toolbar (using Dynamic Link format).

## How to Use

### 1. Basic Dynamic Tags
1. Select a paragraph, heading, button, or image block.
2. Click the **Database Icon** in the block toolbar.
3. Choose a **Source** (Post Meta, Post Data, etc.).
4. Select the specific field.
5. (Optional) Configure **Advanced Settings** (Prefix, Suffix, Formatting).

### 2. Live Preview
- Once configured, the editor will display the actual value from the database instead of a placeholder.
- Use the **Show Original** toggle to switch back to the technical placeholder (e.g., `%% meta_key %%`) if you need to edit the surrounding text.

### 3. Visibility Settings (NEW)
- Open the visibility section in the popover.
- Toggle **Hide if Empty** to prevent the block from rendering if no data is found.
- Use **Display Conditions** to show blocks only when specific criteria are met (e.g., "Price > 100").

### 4. Inline Dynamic Links
- Highlight a piece of text within a block.
- Click the **Dynamic Link icon** (link with a sparkle) in the formatting bar.
- Configure the source and key for the link destination.

## Security & Performance

- **Security First**: All data is sanitized on input and escaped on output using standard WordPress functions (`esc_html`, `esc_url`, `esc_attr`).
- **REST API Security**: All data fetching restricted to authorized editors.
- **HPOS Ready**: Fully compatible with WooCommerce High-Performance Order Storage.
- **Caching Friendly**: Works seamlessly with Object Cache and Page Cache plugins.

## Installation

1. Upload the `dynamic-tags-lite` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Edit any post/page and look for the **Database icon** in the block toolbar.

## Version History

### v1.10.0
- Added: **Phase 5: WooCommerce Integration**. Support for Product prices, SKU, Stock, Ratings, and Cart data.
- Added: WooCommerce field selection dropdown in block settings and link popover.

### v1.9.0
- Added: **Phase 4: Conditional Display**. Hide blocks if empty or based on comparison logic.
- Added: **Phase 3: Context Expansion** (Current User & Post Author fields).
- Added: **Phase 3.5: Secure Custom Fields (SCF) Integration**.
- Added: **Phase 2.5: Live Preview** in Gutenberg editor.
- Improved: Centralized formatting logic in `Manager` class.

### v1.7.1
- Fix: Critical dashboard crash and editor syntax errors.
- Added: Enhanced date parsing for European/Vietnamese formats (d/m/Y).

### v1.7.0
- Added: Phase 2 features (Prefix, Suffix, Date & Number formatting).

## Support

If you encounter any issues or have feature requests, please open an issue on the GitHub repository or contact the developer.

## License

GPL-2.0+
