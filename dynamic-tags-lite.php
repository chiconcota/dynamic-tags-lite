<?php
/**
 * Plugin Name: Dynamic Tags Lite
 * Description: Lightweight dynamic tags for Gutenberg.
 * Version: 1.10.0
 * Author: Antigravity
 * Text Domain: dynamic-tags-lite
 */

namespace Dynamic_Tags_Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DTL_PATH', plugin_dir_path( __FILE__ ) );
define( 'DTL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Plugin {

	private static $instance = null;
	public $manager;
	public $gutenberg;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		spl_autoload_register( [ $this, 'autoload' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	public function autoload( $class ) {
		if ( strpos( $class, __NAMESPACE__ ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( __NAMESPACE__ ) + 1 );
		$file = DTL_PATH . 'includes/class-' . str_replace( '_', '-', strtolower( $relative_class ) ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}

	public function init() {
		$this->manager   = new Manager();
		$this->gutenberg = new Gutenberg_Integration();
	}
}

Plugin::instance();
