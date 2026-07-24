<?php
/**
 * Main orchestrator: shortcode, asset loading and front-end config injection.
 *
 * @package FabricaDTF_Configurador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDTF_Plugin {

	private static $instance = null;
	private $enqueue_needed  = false;

	/** @var FDTF_Settings */
	public $settings;
	/** @var FDTF_Cart */
	public $cart;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = new FDTF_Settings();
		$this->cart     = new FDTF_Cart();

		add_shortcode( 'fabricadtf_configurador', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		// Load a translations file if present.
		add_action( 'init', function () {
			load_plugin_textdomain( 'fabricadtf-configurador', false, dirname( plugin_basename( FDTF_FILE ) ) . '/languages' );
		} );
	}

	/**
	 * Register (but don't yet enqueue) the front-end assets.
	 */
	public function register_assets() {
		wp_register_style( 'fdtf-configurador', FDTF_URL . 'assets/configurator.css', array(), FDTF_VERSION );
		wp_register_script( 'fdtf-configurador', FDTF_URL . 'assets/configurator.js', array(), FDTF_VERSION, true );
	}

	/**
	 * Build the front-end config object from the stored settings.
	 *
	 * @return array
	 */
	private function build_config() {
		$s = FDTF_Settings::get();

		$products = array();
		foreach ( $s['products'] as $p ) {
			$products[] = array(
				'id'       => $p['id'],
				'name'     => $p['name'],
				'price'    => floatval( $p['price'] ),
				'badge'    => isset( $p['badge'] ) ? $p['badge'] : '',
				'badgeHot' => ! empty( $p['badge_hot'] ),
			);
		}

		return array(
			'currency'    => $s['currency'],
			'vat'         => floatval( $s['vat'] ),
			'printPrice'  => floatval( $s['print_price'] ),
			'maxMB'       => intval( $s['max_mb'] ),
			'accept'      => $s['accept'],
			'acceptLabel' => $s['accept_label'],
			'products'    => $products,
			'colors'      => array_values( $s['colors'] ),
			'sizes'       => array_values( $s['sizes'] ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'fdtf_nonce' ),
		);
	}

	/**
	 * Shortcode handler: [fabricadtf_configurador]
	 */
	public function shortcode( $atts ) {
		$this->enqueue_needed = true;

		wp_enqueue_style( 'fdtf-configurador' );
		wp_enqueue_script( 'fdtf-configurador' );
		wp_add_inline_script(
			'fdtf-configurador',
			'window.FDTF_CONFIG = ' . wp_json_encode( $this->build_config() ) . ';',
			'before'
		);

		return '<div class="fdtf-config"></div>';
	}
}
