<?php
namespace Dynamic_Tags_Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manager {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public function register_rest_routes() {
		register_rest_route( 'dynamic-tags-lite/v1', '/meta-keys', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_meta_keys' ],
			'permission_callback' => '__return_true', // Public endpoint for editors
		] );

		register_rest_route( 'dynamic-tags-lite/v1', '/get-value', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_value_rest' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'dynamic-tags-lite/v1', '/scf-fields', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_scf_fields' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function get_meta_keys() {
		global $wpdb;
		
		// Get all distinct meta keys that don't start with underscore (hidden)
		// We make an exception for some common useful hidden keys if needed, but usually we hide them.
		// For a better UX, we might want to allow all keys but filter commonly useless ones.
		// For now, let's just get non-hidden keys.
		$keys = $wpdb->get_col( "
			SELECT DISTINCT meta_key 
			FROM $wpdb->postmeta 
			WHERE meta_key NOT LIKE '\_%' 
			ORDER BY meta_key ASC 
			LIMIT 500
		" );

		// Return as options for SelectControl/Combobox
		$options = array_map( function( $key ) {
			return [ 'label' => $key, 'value' => $key ];
		}, $keys );

		return rest_ensure_response( $options );
	}

	/**
	 * Get value from source
	 *
	 * @param string $source
	 * @param string $key
	 * @param mixed  $context
	 * @return mixed
	 */
	public function get_value( $source, $key, $context = null ) {
		if ( ! $context ) {
			$context = get_the_ID();
		}

		switch ( $source ) {
			case 'post-meta':
				$value = get_post_meta( $context, $key, true );
				
				if ( empty( $value ) ) {
					error_log( "DTL: Value for key '{$key}' on post {$context} is empty." );
				}

				// If it's a numeric ID, it might be an image/attachment. resolve to URL.
				if ( is_numeric( $value ) && $value > 0 ) {
					$url = wp_get_attachment_url( $value );
					if ( $url ) {
						return $url;
					}
				}
				return $value;
			
			case 'post-data':
				$post = get_post( $context );
				if ( ! $post ) {
					return null;
				}

				if ( 'post_author_name' === $key ) {
					return get_the_author_meta( 'display_name', $post->post_author );
				}

				if ( 'post_categories' === $key ) {
					return get_the_category_list( ', ', '', $post->ID );
				}

				if ( 'post_tags' === $key ) {
					return get_the_tag_list( '', ', ', '', $post->ID );
				}

				if ( 'post_thumbnail_url' === $key ) {
					return get_the_post_thumbnail_url( $post->ID, 'full' );
				}

				if ( 'post_author_avatar_url' === $key ) {
					return get_avatar_url( $post->post_author );
				}

				if ( 'site_logo_url' === $key ) {
					$custom_logo_id = get_theme_mod( 'custom_logo' );
					$logo = wp_get_attachment_image_src( $custom_logo_id , 'full' );
					return $logo ? $logo[0] : '';
				}

				if ( 'post_url' === $key ) {
					return get_permalink( $post->ID );
				}

				if ( 'post_author_url' === $key ) {
					return get_author_posts_url( $post->post_author );
				}

				if ( 'home_url' === $key ) {
					return home_url( '/' );
				}

				if ( 'author_bio' === $key || 'post_author_bio' === $key ) {
					return get_the_author_meta( 'description', $post->post_author );
				}

				if ( strpos( $key, 'author_meta:' ) === 0 ) {
					$meta_key = str_replace( 'author_meta:', '', $key );
					return get_the_author_meta( $meta_key, $post->post_author );
				}

				if ( isset( $post->$key ) ) {
					return $post->$key;
				}
				break;

			case 'scf':
				if ( ! function_exists( 'get_field' ) ) {
					return null;
				}

				$value = get_field( $key, $context );

				// Handle image/file arrays from SCF
				if ( is_array( $value ) && isset( $value['url'] ) ) {
					return $value['url'];
				}

				return $value;

			case 'woocommerce':
				if ( ! class_exists( 'WooCommerce' ) ) {
					return null;
				}

				// Handle Cart/Global data first
				if ( 'cart_contents_count' === $key ) {
					return ( WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
				}

				if ( 'cart_total' === $key ) {
					return ( WC()->cart ) ? strip_tags( WC()->cart->get_cart_total() ) : 0;
				}

				if ( 'cart_url' === $key ) {
					return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
				}

				if ( 'checkout_url' === $key ) {
					return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
				}

				// Product specific data
				$product = wc_get_product( $context );
				if ( ! $product ) {
					return null;
				}

				switch ( $key ) {
					case 'price':
						return $product->get_price();
					case 'regular_price':
						return $product->get_regular_price();
					case 'sale_price':
						return $product->get_sale_price();
					case 'formatted_price':
						return strip_tags( wc_price( $product->get_price() ) );
					case 'sku':
						return $product->get_sku();
					case 'stock_status':
						return $product->get_stock_status();
					case 'stock_quantity':
						return $product->get_stock_quantity();
					case 'average_rating':
						return $product->get_average_rating();
					case 'review_count':
						return $product->get_review_count();
					case 'add_to_cart_url':
						return $product->add_to_cart_url();
					case 'product_url':
						return $product->get_permalink();
				}
				break;

			case 'current-user':
				$user = wp_get_current_user();
				if ( ! $user || 0 === $user->ID ) {
					return null;
				}

				if ( 'ID' === $key ) {
					return $user->ID;
				}

				if ( 'display_name' === $key ) {
					return $user->display_name;
				}

				if ( 'user_email' === $key ) {
					return $user->user_email;
				}

				if ( 'user_login' === $key ) {
					return $user->user_login;
				}

				if ( 'user_nicename' === $key ) {
					return $user->user_nicename;
				}

				// If not core field, try meta
				return get_user_meta( $user->ID, $key, true );
		}

		return null;
	}

	public function get_scf_fields() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return rest_ensure_response( [] );
		}

		$options = [];
		$groups  = acf_get_field_groups();

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group['ID'] );
			if ( ! $fields ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$options[] = [
					'label' => $field['label'] . ' (' . $field['name'] . ')',
					'value' => $field['name'],
					'group' => $group['title'],
				];
			}
		}

		return rest_ensure_response( $options );
	}

	public function get_value_rest( $request ) {
		$source  = $request->get_param( 'source' );
		$key     = $request->get_param( 'key' );
		$post_id = $request->get_param( 'post_id' );

		if ( ! $source || ! $key ) {
			return new \WP_Error( 'missing_params', 'Missing source or key', [ 'status' => 400 ] );
		}

		$value = $this->get_value( $source, $key, $post_id );

		// Apply formatting if requested
		$settings = [
			'prefix'         => $request->get_param( 'prefix' ),
			'suffix'         => $request->get_param( 'suffix' ),
			'dateFormat'     => $request->get_param( 'dateFormat' ),
			'numberDecimals' => $request->get_param( 'numberDecimals' ),
		];

		if ( $value !== null ) {
			$value = $this->apply_formatting( $value, $settings );
		}

		return rest_ensure_response( [
			'value' => $value,
		] );
	}

	/**
	 * Apply prefix, suffix, date and number formatting to a dynamic value.
	 */
	public function apply_formatting( $value, $settings ) {
		if ( empty( $value ) && empty( $settings['prefix'] ) && empty( $settings['suffix'] ) ) {
			return $value;
		}

		// 1. Array Value Handling
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		// 2. Date Formatting
		if ( ! empty( $settings['dateFormat'] ) ) {
			$timestamp = false;
			if ( is_numeric( $value ) ) {
				$timestamp = $value;
			} else {
				// Try parsing European/Vietnamese formats (d/m/Y) by replacing / with - for strtotime
				$normalized_value = str_replace( '/', '-', (string) $value );
				$timestamp = strtotime( $normalized_value );
				
				// Fallback if still false
				if ( ! $timestamp ) {
					$timestamp = strtotime( (string) $value );
				}
			}
			
			if ( $timestamp ) {
				$value = wp_date( $settings['dateFormat'], $timestamp );
			}
		}

		// 3. Number Formatting
		if ( isset( $settings['numberDecimals'] ) && $settings['numberDecimals'] !== '' ) {
			$decimals = intval( $settings['numberDecimals'] );
			$value = number_format_i18n( floatval( $value ), $decimals );
		}

		// 4. Prefix & Suffix
		$prefix = isset( $settings['prefix'] ) ? $settings['prefix'] : '';
		$suffix = isset( $settings['suffix'] ) ? $settings['suffix'] : '';
		
		return $prefix . $value . $suffix;
	}
}
