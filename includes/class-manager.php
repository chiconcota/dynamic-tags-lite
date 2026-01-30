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

				if ( isset( $post->$key ) ) {
					return $post->$key;
				}
				break;
		}

		return null;
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
			'value'           => $value,
			'formatted_value' => $formatted_value,
		] );
	}
}
