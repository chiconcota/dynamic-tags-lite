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
				return get_post_meta( $context, $key, true );
			
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

				if ( isset( $post->$key ) ) {
					return $post->$key;
				}
				break;
		}

		return null;
	}
}
