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

		// Master palette + name->colour lookup so each product can use a subset.
		$master_colors = array_values( (array) $s['colors'] );
		$color_by_name = array();
		foreach ( $master_colors as $mc ) {
			$color_by_name[ strtolower( trim( $mc['name'] ) ) ] = array( 'name' => $mc['name'], 'hex' => $mc['hex'] );
		}

		// Mockup photo sets: colour-name -> file code, per product mockup set.
		$mock_map = array(
			'cotton' => array( 'branco' => 'wh', 'preto' => 'bk', 'cinza' => 'as', 'vermelho' => 'rd', 'laranja' => 'or', 'amarelo' => 'sy', 'azul navy' => 'ny', 'azul royal' => 'rb', 'azul claro' => 'sk', 'verde' => 'lm', 'verde escuro' => 'bg' ),
			'sport'  => array( 'branco' => 'wh', 'preto' => 'bk', 'vermelho' => 'rd', 'laranja florescente' => 'orf', 'amarelo florescente' => 'syf', 'azul navy' => 'ny', 'azul royal' => 'rb', 'verde florescente' => 'gf', 'verde kelly' => 'lmf' ),
		);
		$mock_url = FDTF_URL . 'assets/mockups/';

		$products = array();
		foreach ( $s['products'] as $p ) {
			$set  = isset( $p['mockup'] ) ? $p['mockup'] : '';
			$cmap = ( $set && isset( $mock_map[ $set ] ) ) ? $mock_map[ $set ] : array();
			$pcolors = array();
			if ( ! empty( $p['color_names'] ) && is_array( $p['color_names'] ) ) {
				foreach ( $p['color_names'] as $cn ) {
					$k = strtolower( trim( $cn ) );
					$entry = isset( $color_by_name[ $k ] ) ? $color_by_name[ $k ] : array( 'name' => $cn, 'hex' => '#cccccc' );
					if ( isset( $cmap[ $k ] ) ) {
						$entry['front'] = $mock_url . $set . '/' . $cmap[ $k ] . '_front.jpg';
						$entry['back']  = $mock_url . $set . '/' . $cmap[ $k ] . '_back.jpg';
					}
					$pcolors[] = $entry;
				}
			}
			if ( empty( $pcolors ) ) {
				$pcolors = $master_colors;
			}
			$ptiers = array();
			if ( ! empty( $p['tiers'] ) && is_array( $p['tiers'] ) ) {
				foreach ( $p['tiers'] as $t ) {
					$ptiers[] = array(
						'min'   => intval( $t['min'] ),
						'max'   => intval( $t['max'] ),
						'price' => floatval( $t['price'] ),
					);
				}
			}
			$products[] = array(
				'id'       => $p['id'],
				'name'     => $p['name'],
				'price'    => floatval( $p['price'] ),
				'badge'    => isset( $p['badge'] ) ? $p['badge'] : '',
				'badgeHot' => ! empty( $p['badge_hot'] ),
				'desc'     => isset( $p['desc'] ) ? $p['desc'] : '',
				'features' => isset( $p['features'] ) && is_array( $p['features'] ) ? array_values( $p['features'] ) : array(),
				'colors'   => $pcolors,
				'tiers'    => $ptiers,
				'mockupSet' => $set,
			);
		}

		$print_sizes = array();
		foreach ( (array) $s['print_sizes'] as $ps ) {
			$print_sizes[] = array(
				'code'  => $ps['code'],
				'label' => isset( $ps['label'] ) ? $ps['label'] : $ps['code'],
				'price' => floatval( $ps['price'] ),
			);
		}

		$positions = array();
		foreach ( (array) $s['positions'] as $pos ) {
			$positions[] = array(
				'code'        => $pos['code'],
				'label'       => $pos['label'],
				'defaultSize' => isset( $pos['default_size'] ) ? $pos['default_size'] : '',
				'sizes'       => isset( $pos['sizes'] ) && is_array( $pos['sizes'] ) ? array_values( $pos['sizes'] ) : array(),
			);
		}

		$measurements = array();
		foreach ( (array) $s['measurements'] as $m ) {
			$measurements[] = array(
				'size'   => $m['size'],
				'width'  => floatval( $m['width'] ),
				'height' => floatval( $m['height'] ),
			);
		}

		$extras = array();
		foreach ( (array) $s['extras'] as $e ) {
			$extras[] = array(
				'code'  => $e['code'],
				'label' => $e['label'],
				'desc'  => isset( $e['desc'] ) ? $e['desc'] : '',
				'price' => floatval( $e['price'] ),
				'per'   => ( isset( $e['per'] ) && 'order' === $e['per'] ) ? 'order' : 'unit',
			);
		}

		$production = array();
		foreach ( (array) $s['production'] as $pr ) {
			$production[] = array(
				'code'    => $pr['code'],
				'label'   => $pr['label'],
				'days'    => intval( $pr['days'] ),
				'pct'     => floatval( $pr['pct'] ),
				'unit'    => floatval( $pr['unit'] ),
				'default' => ! empty( $pr['default'] ),
			);
		}

		return array(
			'currency'     => $s['currency'],
			'vat'          => floatval( $s['vat'] ),
			'printPrice'   => floatval( $s['print_price'] ),
			'maxMB'        => intval( $s['max_mb'] ),
			'accept'       => $s['accept'],
			'acceptLabel'  => $s['accept_label'],
			'products'     => $products,
			'colors'       => array_values( $s['colors'] ),
			'sizes'        => array_values( $s['sizes'] ),
			'printSizes'   => $print_sizes,
			'positions'    => $positions,
			'measurements' => $measurements,
			'extras'       => $extras,
			'production'   => $production,
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'fdtf_nonce' ),
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
