<?php
/**
 * Plugin Name:       Fábrica DTF – Configurador de T-shirts
 * Plugin URI:        https://fabricadtf.pt
 * Description:        Configurador de t-shirts personalizadas (modelo, cor, tamanhos, upload de arte com pré-visualização em tempo real) integrado com o WooCommerce. Adiciona o produto configurado ao carrinho e usa os pagamentos e envios já existentes. Shortcode: [fabricadtf_configurador]
 * Version:           1.8.2
 * Author:            Anirudha Talmale
 * Text Domain:       fabricadtf-configurador
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 *
 * @package FabricaDTF_Configurador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'FDTF_VERSION', '1.8.2' );
define( 'FDTF_FILE', __FILE__ );
define( 'FDTF_DIR', plugin_dir_path( __FILE__ ) );
define( 'FDTF_URL', plugin_dir_url( __FILE__ ) );
define( 'FDTF_OPTION', 'fdtf_settings' );          // Stored configuration.
define( 'FDTF_BASE_PRODUCT_OPTION', 'fdtf_base_product_id' ); // Hidden WooCommerce container product.

require_once FDTF_DIR . 'includes/class-fdtf-settings.php';
require_once FDTF_DIR . 'includes/class-fdtf-cart.php';
require_once FDTF_DIR . 'includes/class-fdtf-plugin.php';

/**
 * Boot the plugin after all plugins are loaded (so WooCommerce is available).
 */
function fdtf_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Fábrica DTF – Configurador:</strong> requer o WooCommerce ativo.</p></div>';
		} );
		return;
	}
	FDTF_Plugin::instance();
}
add_action( 'plugins_loaded', 'fdtf_bootstrap' );

/**
 * Activation: seed default settings and create the hidden container product.
 */
function fdtf_activate() {
	if ( false === get_option( FDTF_OPTION ) ) {
		add_option( FDTF_OPTION, FDTF_Settings::defaults() );
	}
	// The base product is created lazily on first need (WooCommerce may not be
	// loaded during activation), so nothing else is required here.
	if ( ! wp_next_scheduled( 'fdtf_cleanup_temp_uploads' ) ) {
		wp_schedule_event( time(), 'daily', 'fdtf_cleanup_temp_uploads' );
	}
}
register_activation_hook( __FILE__, 'fdtf_activate' );

function fdtf_deactivate() {
	wp_clear_scheduled_hook( 'fdtf_cleanup_temp_uploads' );
}
register_deactivation_hook( __FILE__, 'fdtf_deactivate' );
