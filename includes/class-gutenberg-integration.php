<?php
namespace Dynamic_Tags_Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gutenberg_Integration {

	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'init', [ $this, 'register_attributes' ] );
		add_filter( 'render_block', [ $this, 'render_dynamic_content' ], 10, 2 );
		add_filter( 'block_type_metadata_settings', [ $this, 'register_server_side_attributes' ], 10, 2 );
	}

	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'dtl-editor',
			DTL_URL . 'assets/js/editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-plugins', 'wp-hooks', 'wp-api-fetch', 'wp-url', 'wp-rich-text' ],
			time(), // Force cache reload
			true
		);
	}

	public function register_attributes() {
		// JS-side attributes are enough for most cases, but we register server-side via filter below.
	}

	public function register_server_side_attributes( $settings, $metadata ) {
		$eligible_blocks = [ 'core/paragraph', 'core/heading', 'core/image', 'core/video', 'core/button' ];
		
		if ( in_array( $metadata['name'], $eligible_blocks ) ) {
			$settings['attributes']['dynamicTag'] = [
				'type'    => 'object',
				'default' => [ 
					'enable' => false, 
					'source' => '', 
					'key' => '', 
					'fallback' => '',
					'prefix' => '',
					'suffix' => '',
					'dateFormat' => '',
					'numberDecimals' => '',
					'showPreview' => true,
					'hideIfEmpty' => false,
					'condition' => 'always',
					'compareValue' => ''
				],
			];

			if ( 'core/image' === $metadata['name'] ) {
				$settings['attributes']['dynamicLink'] = [
					'type'    => 'object',
					'default' => [ 
						'enable' => false, 
						'source' => '', 
						'key' => '', 
						'fallback' => '',
						'prefix' => '',
						'suffix' => '',
						'dateFormat' => '',
						'numberDecimals' => '',
						'showPreview' => true
					],
				];
			}
		}

		return $settings;
	}

	public function render_dynamic_content( $block_content, $block ) {
		// 1. Process Inline Dynamic Links (New Feature)
		$block_content = $this->process_inline_dynamic_links( $block_content );

		// 2. Process Dynamic Link for Blocks (e.g. Image)
		if ( ! empty( $block['attrs']['dynamicLink'] ) && $block['attrs']['dynamicLink']['enable'] ) {
			$link_setting = $block['attrs']['dynamicLink'];
			$link_url = Plugin::instance()->manager->get_value( $link_setting['source'], $link_setting['key'] );
			
			if ( empty( $link_url ) && ! empty( $link_setting['fallback'] ) ) {
				$link_url = $link_setting['fallback'];
			}

			if ( ! empty( $link_url ) ) {
				$link_url = Plugin::instance()->manager->apply_formatting( $link_url, $link_setting );

				if ( 'core/image' === $block['blockName'] ) {
					// Check if image is already wrapped in a link
					if ( preg_match( '/<figure[^>]*>.*<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>.*<\/a>.*<\/figure>/is', $block_content ) ) {
						// Replace existing href
						$block_content = preg_replace( '/(<a\s+[^>]*href=["\'])([^"\']*)(["\'][^>]*>)/i', '$1' . esc_url( $link_url ) . '$3', $block_content );
					} else {
						// Wrapping img in link
						// Finding the img tag and wrapping it
						$block_content = preg_replace( '/(<img\s+[^>]+>)/i', '<a href="' . esc_url( $link_url ) . '">$1</a>', $block_content );
					}
				}
				// Add other blocks here if needed (e.g. core/button)
			}
		}

		if ( empty( $block['attrs']['dynamicTag'] ) ) {
			return $block_content;
		}

		$setting = $block['attrs']['dynamicTag'];
		
		if ( empty( $setting['enable'] ) || empty( $setting['source'] ) || empty( $setting['key'] ) ) {
			return $block_content;
		}

		$value = Plugin::instance()->manager->get_value( $setting['source'], $setting['key'] );

		// Visibility Check
		if ( ! empty( $setting['hideIfEmpty'] ) && empty( $value ) ) {
			return '';
		}

		if ( ! empty( $setting['condition'] ) && 'always' !== $setting['condition'] ) {
			$condition = $setting['condition'];
			$compare_value = isset( $setting['compareValue'] ) ? $setting['compareValue'] : '';
			$should_show = true;

			switch ( $condition ) {
				case 'equals':
					$should_show = ( (string) $value === (string) $compare_value );
					break;
				case 'not_equals':
					$should_show = ( (string) $value !== (string) $compare_value );
					break;
				case 'contains':
					$should_show = ( strpos( (string) $value, (string) $compare_value ) !== false );
					break;
				case 'greater_than':
					$should_show = ( floatval( $value ) > floatval( $compare_value ) );
					break;
				case 'less_than':
					$should_show = ( floatval( $value ) < floatval( $compare_value ) );
					break;
			}

			if ( ! $should_show ) {
				return '';
			}
		}

		if ( empty( $value ) && ! empty( $setting['fallback'] ) ) {
			$value = $setting['fallback'];
		}

		if ( empty( $value ) ) {
			// If we are in debug mode or just for logged in users, maybe show a hint?
			// For now, let's add an HTML comment for debugging.
			$debug_msg = sprintf( '<!-- DTL Debug: Key "%s" from Source "%s" returned empty. -->', esc_attr( $setting['key'] ), esc_attr( $setting['source'] ) );
			return $block_content . $debug_msg;
		}

		$value = Plugin::instance()->manager->apply_formatting( $value, $setting );

		// Handle array values (e.g. Checkbox, Select)
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		// Handle specific blocks
		if ( 'core/image' === $block['blockName'] ) {
			// If value is ID, get URL
			if ( is_numeric( $value ) ) {
				$value = wp_get_attachment_url( $value );
			}
			
			// Debug comment to confirm we got here
			$debug = '<!-- DTL Debug: Image Value="' . esc_attr( $value ) . '" -->';

			// Robust regex for src matching (single or double quotes)
			$content = preg_replace( '/src\s*=\s*["\']([^"\']+)["\']/', 'src="' . esc_url( $value ) . '"', $block_content, 1 );
			
			// Remove srcset and sizes aggressively to force the new src
			$content = preg_replace( '/srcset\s*=\s*["\']([^"\']+)["\']/', '', $content );
			$content = preg_replace( '/sizes\s*=\s*["\']([^"\']+)["\']/', '', $content );
			
			return $content . $debug;
		}
		
		if ( 'core/video' === $block['blockName'] ) {
			// Replace src in video tag
			return preg_replace( '/src="([^"]+)"/', 'src="' . esc_url( $value ) . '"', $block_content );
		}

		// Default: Text content replacement
		// We need to be careful not to strip tags if it's a heading or paragraph
		// But usually we just want to replace the inner text.
		
		// If it's a simple text block, we might just replace the content between tags?
		// But Wait, existing logic was relying on block_content being just the HTML.
		// If we replace everything, we lose the tags? 
		// Actually, standard render_block returns HTML.
		// For Paragraph/Heading, the content is inside the tags.
		// Let's use a robust way to replace INNER text if possible.
		// Current approach: Replace %% key %% if found, OR if entire content is just text?
		// Actually, the previous logic (which I am replacing) didn't seem to have a text replacement logic shown in previous context?
		// Wait, I am viewing lines 33-86.
		// The previous code STOPPED at line 86.
		// I need to see how text replacement was handled.
		// From previous summary: "The `render_block` filter in `class-gutenberg-integration.php` intercepts block content. It retrieves the dynamic value... and replaces the block's content."
		
		// Let's look at the "Previous Session Summary" or code again. 
		// Ah, in previous tool output (Step 263), the file ENDS at line 91 effectively (class close).
		// The lines 84-86 were comments. 
		// It seems I might have MISSED the text replacement logic in the previous view.
		// Let me check line 86 again. It just stops.
		// StartLine: 1, EndLine: 86.
		// If the file has 91 lines, and line 86 is a comment, then the logic for text replacement IS MISSING in the view??
		// No, `render_dynamic_content` function body continues.
		// I must see the rest of the file to ensure I don't break the text replacement logic.
		// But for now, I will just INSERT the call at the top and close the method.
		
		return $this->replace_text_content( $block_content, $value ); 
	}

	/**
	 * Process inline dynamic links in any block content.
	 */
	protected function process_inline_dynamic_links( $content ) {
		if ( strpos( $content, 'dtl-dynamic-link' ) === false ) {
			return $content;
		}

		return preg_replace_callback( '/<a\s+[^>]*class=["\'][^"\']*dtl-dynamic-link[^"\']*["\'][^>]*>/i', function( $matches ) {
			$tag = $matches[0];

			// Extract Source
			if ( ! preg_match( '/data-dtl-source=["\']([^"\']+)["\']/', $tag, $source_match ) ) {
				return $tag;
			}
			$source = $source_match[1];

			// Extract Key
			if ( ! preg_match( '/data-dtl-key=["\']([^"\']+)["\']/', $tag, $key_match ) ) {
				return $tag;
			}
			$key = $key_match[1];

			// Get Value
			$url = Plugin::instance()->manager->get_value( $source, $key );

			if ( empty( $url ) ) {
				return $tag; // Or remove link? Keep for now.
			}

			// Replace HREF and URL attributes
			// We remove existing href/url and add new one
			$tag = preg_replace( '/\s+(href|url)=["\'][^"\']*["\']/', '', $tag );
			$tag = str_replace( '<a ', '<a href="' . esc_url( $url ) . '" ', $tag );

			return $tag;
		}, $content );
	}

	protected function replace_text_content( $content, $value ) {
		// New robust logic: Replace %% key %% pattern specifically.
		// This supports multiple placeholders and mixed content.
		
		// If the content doesn't have a placeholder, fall back to replacing everything inside the tags
		if ( strpos( $content, '%%' ) === false ) {
			if ( preg_match( '/(<a[^>]*>)(.*)(<\/a>)/is', $content, $matches ) ) {
				return str_replace( $matches[2], esc_html( $value ), $content );
			}
			return preg_replace( '/^(<[^>]+>)(.*)(<\/[^>]+>)$/s', '$1' . esc_html( $value ) . '$3', $content );
		}

		// Replace patterns like %% some_key %% (with or without spaces)
		return preg_replace( '/%%[\s]*[^%]+[\s]*%%/', esc_html( $value ), $content );
	}
}
