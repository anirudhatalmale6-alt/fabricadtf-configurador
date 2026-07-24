<?php
/**
 * Admin settings / configuration panel for the Fábrica DTF Configurador.
 *
 * Provides an easy panel (WooCommerce submenu) to manage t-shirt models,
 * colours, sizes, personalisation price, VAT and upload rules — no code needed.
 *
 * @package FabricaDTF_Configurador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDTF_Settings {

	/**
	 * Default configuration used on first activation.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'currency'    => get_woocommerce_currency_symbol() ? get_woocommerce_currency_symbol() : '€',
			'vat'         => 23,
			'print_price' => 2.50,
			'max_mb'      => 25,
			'accept'      => '.png,.pdf',
			'accept_label'=> 'PNG ou PDF · máx 25 MB',
			'products'    => array(
				array( 'id' => 'classic', 'name' => 'T-shirt Classic 160g', 'price' => 7.00, 'badge' => 'Mais escolhida', 'badge_hot' => 1 ),
				array( 'id' => 'premium', 'name' => 'T-shirt Premium 190g', 'price' => 9.50, 'badge' => 'Qualidade superior', 'badge_hot' => 0 ),
				array( 'id' => 'sport',   'name' => 'T-shirt Sport 135g',   'price' => 8.00, 'badge' => 'Ideal para desporto', 'badge_hot' => 0 ),
			),
			'colors'      => array(
				array( 'name' => 'Branco', 'hex' => '#ffffff' ),
				array( 'name' => 'Preto', 'hex' => '#1a1a1a' ),
				array( 'name' => 'Cinza', 'hex' => '#9aa0aa' ),
				array( 'name' => 'Azul Marinho', 'hex' => '#12235a' ),
				array( 'name' => 'Azul Royal', 'hex' => '#2f6bff' ),
				array( 'name' => 'Vermelho', 'hex' => '#d62828' ),
				array( 'name' => 'Verde', 'hex' => '#1e7d34' ),
				array( 'name' => 'Amarelo', 'hex' => '#f4c20d' ),
				array( 'name' => 'Laranja', 'hex' => '#ef7d1a' ),
				array( 'name' => 'Rosa', 'hex' => '#e85aa0' ),
			),
			'sizes'       => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' ),
		);
	}

	/**
	 * Get merged settings (stored over defaults).
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( FDTF_OPTION );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_fdtf_save_settings', array( $this, 'save' ) );
	}

	public function menu() {
		add_submenu_page(
			'woocommerce',
			'Configurador de T-shirts',
			'Configurador T-shirts',
			'manage_woocommerce',
			'fdtf-configurador',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Persist the submitted settings.
	 */
	public function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissões.' );
		}
		check_admin_referer( 'fdtf_save_settings' );

		$in  = wp_unslash( $_POST );
		$out = self::get();

		$out['currency']     = isset( $in['currency'] ) ? sanitize_text_field( $in['currency'] ) : '€';
		$out['vat']          = isset( $in['vat'] ) ? floatval( $in['vat'] ) : 23;
		$out['print_price']  = isset( $in['print_price'] ) ? round( floatval( $in['print_price'] ), 2 ) : 0;
		$out['max_mb']       = isset( $in['max_mb'] ) ? max( 1, intval( $in['max_mb'] ) ) : 25;
		$out['accept']       = isset( $in['accept'] ) ? sanitize_text_field( $in['accept'] ) : '.png,.pdf';
		$out['accept_label'] = isset( $in['accept_label'] ) ? sanitize_text_field( $in['accept_label'] ) : 'PNG ou PDF';

		// Products.
		$products = array();
		if ( ! empty( $in['product_name'] ) && is_array( $in['product_name'] ) ) {
			foreach ( $in['product_name'] as $i => $name ) {
				$name = sanitize_text_field( $name );
				if ( '' === $name ) {
					continue;
				}
				$id = isset( $in['product_id'][ $i ] ) && '' !== $in['product_id'][ $i ]
					? sanitize_title( $in['product_id'][ $i ] )
					: sanitize_title( $name );
				$products[] = array(
					'id'        => $id,
					'name'      => $name,
					'price'     => isset( $in['product_price'][ $i ] ) ? round( floatval( $in['product_price'][ $i ] ), 2 ) : 0,
					'badge'     => isset( $in['product_badge'][ $i ] ) ? sanitize_text_field( $in['product_badge'][ $i ] ) : '',
					'badge_hot' => ! empty( $in['product_hot'][ $i ] ) ? 1 : 0,
				);
			}
		}
		if ( $products ) {
			$out['products'] = $products;
		}

		// Colours.
		$colors = array();
		if ( ! empty( $in['color_name'] ) && is_array( $in['color_name'] ) ) {
			foreach ( $in['color_name'] as $i => $name ) {
				$name = sanitize_text_field( $name );
				if ( '' === $name ) {
					continue;
				}
				$hex = isset( $in['color_hex'][ $i ] ) ? sanitize_hex_color( $in['color_hex'][ $i ] ) : '';
				$colors[] = array( 'name' => $name, 'hex' => $hex ? $hex : '#ffffff' );
			}
		}
		if ( $colors ) {
			$out['colors'] = $colors;
		}

		// Sizes (comma separated).
		if ( isset( $in['sizes'] ) ) {
			$sizes = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $in['sizes'] ) ) ) );
			if ( $sizes ) {
				$out['sizes'] = array_values( $sizes );
			}
		}

		update_option( FDTF_OPTION, $out );

		wp_safe_redirect( add_query_arg( array( 'page' => 'fdtf-configurador', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		$s        = self::get();
		$page_id  = get_option( 'fdtf_demo_page_id' );
		$saved    = isset( $_GET['saved'] );
		?>
		<div class="wrap">
			<h1>Configurador de T-shirts — Fábrica DTF</h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Definições guardadas.</p></div>
			<?php endif; ?>

			<p>Insere o configurador em qualquer página com o shortcode
				<code>[fabricadtf_configurador]</code>.
				Os pagamentos e envios usam o checkout que já tens configurado no WooCommerce.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fdtf_save_settings">
				<?php wp_nonce_field( 'fdtf_save_settings' ); ?>

				<h2>Geral</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label>Símbolo da moeda</label></th>
						<td><input type="text" name="currency" value="<?php echo esc_attr( $s['currency'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th><label>IVA (%)</label></th>
						<td><input type="number" step="0.01" name="vat" value="<?php echo esc_attr( $s['vat'] ); ?>" class="small-text"> <span class="description">Ex.: 23</span></td>
					</tr>
					<tr>
						<th><label>Preço da personalização / unidade</label></th>
						<td><input type="number" step="0.01" name="print_price" value="<?php echo esc_attr( $s['print_price'] ); ?>" class="small-text"> <span class="description">Aplicado quando o cliente envia arte.</span></td>
					</tr>
					<tr>
						<th><label>Tamanho máximo do ficheiro (MB)</label></th>
						<td><input type="number" name="max_mb" value="<?php echo esc_attr( $s['max_mb'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th><label>Formatos aceites</label></th>
						<td>
							<input type="text" name="accept" value="<?php echo esc_attr( $s['accept'] ); ?>" class="regular-text"><br>
							<input type="text" name="accept_label" value="<?php echo esc_attr( $s['accept_label'] ); ?>" class="regular-text" placeholder="Etiqueta apresentada ao cliente">
							<p class="description">Extensões separadas por vírgula, ex.: <code>.png,.pdf</code></p>
						</td>
					</tr>
				</table>

				<h2>Modelos de t-shirt</h2>
				<table class="widefat striped" id="fdtf-products">
					<thead><tr><th>ID</th><th>Nome</th><th>Preço/un.</th><th>Etiqueta</th><th>Destaque</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['products'] as $p ) : ?>
						<tr>
							<td><input type="text" name="product_id[]" value="<?php echo esc_attr( $p['id'] ); ?>" placeholder="auto"></td>
							<td><input type="text" name="product_name[]" value="<?php echo esc_attr( $p['name'] ); ?>" class="regular-text"></td>
							<td><input type="number" step="0.01" name="product_price[]" value="<?php echo esc_attr( $p['price'] ); ?>" class="small-text"></td>
							<td><input type="text" name="product_badge[]" value="<?php echo esc_attr( $p['badge'] ); ?>"></td>
							<td style="text-align:center"><input type="checkbox" name="product_hot[<?php /* index set via JS-free approach below */ ?>]" <?php checked( ! empty( $p['badge_hot'] ) ); ?>></td>
							<td><button type="button" class="button fdtf-rm">×</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fdtf-add-product">+ Adicionar modelo</button></p>

				<h2>Cores</h2>
				<table class="widefat striped" id="fdtf-colors" style="max-width:520px">
					<thead><tr><th>Nome</th><th>Cor</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['colors'] as $c ) : ?>
						<tr>
							<td><input type="text" name="color_name[]" value="<?php echo esc_attr( $c['name'] ); ?>"></td>
							<td><input type="color" name="color_hex[]" value="<?php echo esc_attr( $c['hex'] ); ?>"></td>
							<td><button type="button" class="button fdtf-rm">×</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fdtf-add-color">+ Adicionar cor</button></p>

				<h2>Tamanhos</h2>
				<p><input type="text" name="sizes" value="<?php echo esc_attr( implode( ', ', $s['sizes'] ) ); ?>" class="regular-text">
					<span class="description">Separados por vírgula, ex.: XS, S, M, L, XL, XXL</span></p>

				<?php submit_button( 'Guardar definições' ); ?>
			</form>
		</div>

		<script>
		( function () {
			// Fix product-hot checkbox indexes (they must match the row order).
			function reindexHot( table ) {
				table.querySelectorAll( 'tbody tr' ).forEach( function ( tr, i ) {
					var cb = tr.querySelector( 'input[type=checkbox]' );
					if ( cb ) { cb.setAttribute( 'name', 'product_hot[' + i + ']' ); }
				} );
			}
			var prodTable = document.getElementById( 'fdtf-products' );
			reindexHot( prodTable );

			document.getElementById( 'fdtf-add-product' ).addEventListener( 'click', function () {
				var tb = prodTable.querySelector( 'tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="product_id[]" placeholder="auto"></td>' +
					'<td><input type="text" name="product_name[]" class="regular-text"></td>' +
					'<td><input type="number" step="0.01" name="product_price[]" class="small-text"></td>' +
					'<td><input type="text" name="product_badge[]"></td>' +
					'<td style="text-align:center"><input type="checkbox"></td>' +
					'<td><button type="button" class="button fdtf-rm">×</button></td>';
				tb.appendChild( tr );
				reindexHot( prodTable );
			} );

			document.getElementById( 'fdtf-add-color' ).addEventListener( 'click', function () {
				var tb = document.querySelector( '#fdtf-colors tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="color_name[]"></td>' +
					'<td><input type="color" name="color_hex[]" value="#ffffff"></td>' +
					'<td><button type="button" class="button fdtf-rm">×</button></td>';
				tb.appendChild( tr );
			} );

			document.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'fdtf-rm' ) ) {
					var tr = e.target.closest( 'tr' );
					var table = tr.closest( 'table' );
					tr.remove();
					if ( table.id === 'fdtf-products' ) { reindexHot( table ); }
				}
			} );
		} )();
		</script>
		<?php
	}
}
