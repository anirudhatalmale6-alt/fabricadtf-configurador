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
					array( 'id' => 'classic', 'name' => 'T-shirt 150gr', 'price' => 6.95, 'badge' => 'Mais escolhida', 'badge_hot' => 1,
						'desc' => 'T-shirt de adulto de manga curta, 100% algodão em malha lisa 150 g/m²',
						'features' => array( '100% algodão', 'Malha lisa 150 g/m²', 'Single Jersey' ),
						'color_names' => array( 'Branco', 'Preto', 'Cinza', 'Vermelho', 'Laranja', 'Amarelo', 'Azul Navy', 'Azul Royal', 'Azul claro', 'Verde', 'Verde escuro' ),
						'tiers' => array(
							array( 'min' => 1, 'max' => 9, 'price' => 6.95 ),
							array( 'min' => 10, 'max' => 49, 'price' => 2.55 ),
							array( 'min' => 50, 'max' => 99, 'price' => 2.45 ),
							array( 'min' => 100, 'max' => 249, 'price' => 2.40 ),
							array( 'min' => 250, 'max' => 499, 'price' => 2.15 ),
							array( 'min' => 500, 'max' => 999, 'price' => 1.95 ),
							array( 'min' => 1000, 'max' => 0, 'price' => 1.85 ),
						) ),
					array( 'id' => 'premium', 'name' => 'T-shirt 190gr', 'price' => 8.95, 'badge' => 'Qualidade superior', 'badge_hot' => 0,
						'desc' => 'T-shirt de adulto de manga curta, 100% algodão penteado 190 g/m²',
						'features' => array( '100% algodão penteado', 'Malha 190 g/m²', 'Toque mais encorpado' ),
						'color_names' => array( 'Branco', 'Preto', 'Cinza', 'Vermelho', 'Laranja', 'Amarelo', 'Azul Navy', 'Azul Royal', 'Azul claro', 'Verde', 'Verde escuro' ),
						'tiers' => array(
							array( 'min' => 1, 'max' => 9, 'price' => 8.95 ),
							array( 'min' => 10, 'max' => 49, 'price' => 3.85 ),
							array( 'min' => 50, 'max' => 99, 'price' => 3.40 ),
							array( 'min' => 100, 'max' => 249, 'price' => 3.05 ),
							array( 'min' => 250, 'max' => 499, 'price' => 2.85 ),
							array( 'min' => 500, 'max' => 999, 'price' => 2.75 ),
							array( 'min' => 1000, 'max' => 0, 'price' => 2.65 ),
						) ),
					array( 'id' => 'sport',   'name' => 'T-shirt Técnica 135gr', 'price' => 6.95, 'badge' => 'Ideal para desporto', 'badge_hot' => 0,
						'desc' => 'T-shirt técnica de manga curta, poliéster respirável 135 g/m²',
						'features' => array( '100% poliéster', 'Respirável / dry-fit', 'Leve 135 g/m²' ),
						'color_names' => array( 'Branco', 'Preto', 'Vermelho', 'Laranja florescente', 'Amarelo florescente', 'Azul Navy', 'Azul Royal', 'Verde florescente', 'Verde kelly' ),
						'tiers' => array(
							array( 'min' => 1, 'max' => 9, 'price' => 6.95 ),
							array( 'min' => 10, 'max' => 49, 'price' => 2.55 ),
							array( 'min' => 50, 'max' => 99, 'price' => 2.45 ),
							array( 'min' => 100, 'max' => 249, 'price' => 2.40 ),
							array( 'min' => 250, 'max' => 499, 'price' => 2.13 ),
							array( 'min' => 500, 'max' => 999, 'price' => 1.95 ),
							array( 'min' => 1000, 'max' => 0, 'price' => 1.85 ),
						) ),
				),
				// Master colour palette (name + hex). Each product may use a subset via 'color_names' (empty = all).
				'colors'      => array(
					array( 'name' => 'Branco', 'hex' => '#ffffff' ),
					array( 'name' => 'Preto', 'hex' => '#1a1a1a' ),
					array( 'name' => 'Cinza', 'hex' => '#9aa0aa' ),
					array( 'name' => 'Vermelho', 'hex' => '#d62828' ),
					array( 'name' => 'Laranja', 'hex' => '#ef7d1a' ),
					array( 'name' => 'Amarelo', 'hex' => '#f4c20d' ),
					array( 'name' => 'Azul Navy', 'hex' => '#12235a' ),
					array( 'name' => 'Azul Royal', 'hex' => '#2f6bff' ),
					array( 'name' => 'Azul claro', 'hex' => '#4bb3e6' ),
					array( 'name' => 'Verde', 'hex' => '#1e7d34' ),
					array( 'name' => 'Verde escuro', 'hex' => '#14532d' ),
					array( 'name' => 'Laranja florescente', 'hex' => '#ff6a13' ),
					array( 'name' => 'Amarelo florescente', 'hex' => '#e2f321' ),
					array( 'name' => 'Verde florescente', 'hex' => '#4dff3f' ),
					array( 'name' => 'Verde kelly', 'hex' => '#3cae4a' ),
				),
				'sizes'       => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' ),
			// Print positions the customer can personalise (front, back, sleeves, chest).
			// 'sizes' limits which print sizes are allowed for that position (empty = all).
			'positions'   => array(
				array( 'code' => 'frente',    'label' => 'Frente',              'default_size' => 'A4', 'sizes' => array() ),
				array( 'code' => 'costas',    'label' => 'Costas',              'default_size' => 'A3', 'sizes' => array() ),
				array( 'code' => 'peito_esq', 'label' => 'Peito (lado coração)', 'default_size' => 'A7', 'sizes' => array( 'A7', 'A6' ) ),
				array( 'code' => 'manga_esq', 'label' => 'Manga esquerda',      'default_size' => 'A7', 'sizes' => array( 'A7', 'A6' ) ),
				array( 'code' => 'manga_dta', 'label' => 'Manga direita',       'default_size' => 'A7', 'sizes' => array( 'A7', 'A6' ) ),
			),
			// Print sizes (A-series) and their price per unit. Placeholder prices — edit to your real tariff.
			'print_sizes' => array(
				array( 'code' => 'A7', 'label' => 'A7', 'price' => 0.30 ),
				array( 'code' => 'A6', 'label' => 'A6', 'price' => 0.45 ),
				array( 'code' => 'A5', 'label' => 'A5', 'price' => 0.70 ),
				array( 'code' => 'A4', 'label' => 'A4', 'price' => 0.75 ),
				array( 'code' => 'A3', 'label' => 'A3', 'price' => 1.15 ),
			),
			// Size measurements (informative table shown on the sizes step).
			'measurements' => array(
				array( 'size' => 'XS',  'width' => 47, 'height' => 67 ),
				array( 'size' => 'S',   'width' => 50, 'height' => 69 ),
				array( 'size' => 'M',   'width' => 53, 'height' => 72 ),
				array( 'size' => 'L',   'width' => 56, 'height' => 74 ),
				array( 'size' => 'XL',  'width' => 59, 'height' => 76 ),
				array( 'size' => 'XXL', 'width' => 62, 'height' => 79 ),
			),
			// Optional extras (checkboxes). per = unit (por produto) or order (por encomenda).
			'extras'      => array(
				array( 'code' => 'embalamento', 'label' => 'Embalamento individual', 'desc' => 'Aplicado por produto', 'price' => 0.25, 'per' => 'unit' ),
			),
			// Production time options (radio). pct = % surcharge on unit price; unit = fixed €/un surcharge.
			'production'  => array(
				array( 'code' => 'normal',      'label' => 'Normal até 9 dias',      'days' => 9, 'pct' => 0,  'unit' => 0, 'default' => 1 ),
				array( 'code' => 'prioritaria', 'label' => 'Prioritária até 5 dias', 'days' => 5, 'pct' => 10, 'unit' => 0, 'default' => 0 ),
			),
		);
	}

	/**
	 * Get merged settings (stored over defaults).
	 *
	 * @return array
	 */
	/**
	 * Format tiers as editable text lines (min-max:price, or min+:price for open-ended).
	 */
	public static function tiers_to_text( $tiers ) {
		$out = array();
		if ( ! empty( $tiers ) && is_array( $tiers ) ) {
			foreach ( $tiers as $t ) {
				$mx = empty( $t['max'] ) ? '+' : '-' . intval( $t['max'] );
				$out[] = intval( $t['min'] ) . $mx . ':' . number_format( floatval( $t['price'] ), 2, '.', '' );
			}
		}
		return implode( "\n", $out );
	}

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
				$features = array();
				if ( isset( $in['product_features'][ $i ] ) ) {
					foreach ( preg_split( '/\r\n|\r|\n/', $in['product_features'][ $i ] ) as $line ) {
						$line = sanitize_text_field( trim( $line ) );
						if ( '' !== $line ) {
							$features[] = $line;
						}
					}
				}
				$color_names = array();
					if ( isset( $in['product_colors'][ $i ] ) ) {
						foreach ( preg_split( '/\r\n|\r|\n|,/', $in['product_colors'][ $i ] ) as $cn ) {
							$cn = sanitize_text_field( trim( $cn ) );
							if ( '' !== $cn ) { $color_names[] = $cn; }
						}
					}
					$tiers = array();
					if ( isset( $in['product_tiers'][ $i ] ) ) {
						foreach ( preg_split( '/\r\n|\r|\n/', $in['product_tiers'][ $i ] ) as $ln ) {
							$ln = trim( $ln );
							if ( '' === $ln ) { continue; }
							if ( preg_match( '/^\s*(\d+)\s*[-\x{2013}]\s*(\d*)\+?\s*:\s*([0-9]+(?:[.,][0-9]+)?)/u', $ln, $m ) ) {
								$tiers[] = array( 'min' => intval( $m[1] ), 'max' => ( '' === $m[2] ? 0 : intval( $m[2] ) ), 'price' => round( floatval( str_replace( ',', '.', $m[3] ) ), 2 ) );
							}
						}
					}
					$products[] = array(
					'id'        => $id,
					'name'      => $name,
					'price'     => isset( $in['product_price'][ $i ] ) ? round( floatval( $in['product_price'][ $i ] ), 2 ) : 0,
					'badge'     => isset( $in['product_badge'][ $i ] ) ? sanitize_text_field( $in['product_badge'][ $i ] ) : '',
					'badge_hot' => ! empty( $in['product_hot'][ $i ] ) ? 1 : 0,
					'desc'      => isset( $in['product_desc'][ $i ] ) ? sanitize_text_field( $in['product_desc'][ $i ] ) : '',
					'features'  => $features,
						'color_names' => $color_names,
						'tiers'       => $tiers,
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

		// Print sizes (A-series) with prices.
		$print_sizes = array();
		if ( ! empty( $in['psize_code'] ) && is_array( $in['psize_code'] ) ) {
			foreach ( $in['psize_code'] as $i => $code ) {
				$code = sanitize_text_field( $code );
				if ( '' === $code ) {
					continue;
				}
				$label = isset( $in['psize_label'][ $i ] ) && '' !== $in['psize_label'][ $i ]
					? sanitize_text_field( $in['psize_label'][ $i ] )
					: $code;
				$print_sizes[] = array(
					'code'  => $code,
					'label' => $label,
					'price' => isset( $in['psize_price'][ $i ] ) ? round( floatval( $in['psize_price'][ $i ] ), 2 ) : 0,
				);
			}
		}
		if ( $print_sizes ) {
			$out['print_sizes'] = $print_sizes;
		}

		// Print positions (front / back / sleeves).
		$positions = array();
		if ( ! empty( $in['pos_label'] ) && is_array( $in['pos_label'] ) ) {
			foreach ( $in['pos_label'] as $i => $label ) {
				$label = sanitize_text_field( $label );
				if ( '' === $label ) {
					continue;
				}
				$code = isset( $in['pos_code'][ $i ] ) && '' !== $in['pos_code'][ $i ]
					? sanitize_key( $in['pos_code'][ $i ] )
					: sanitize_key( $label );
				$allowed = array();
				if ( isset( $in['pos_sizes'][ $i ] ) && '' !== trim( $in['pos_sizes'][ $i ] ) ) {
					foreach ( explode( ',', $in['pos_sizes'][ $i ] ) as $code_s ) {
						$code_s = sanitize_text_field( trim( $code_s ) );
						if ( '' !== $code_s ) {
							$allowed[] = $code_s;
						}
					}
				}
				$positions[] = array(
					'code'         => $code,
					'label'        => $label,
					'default_size' => isset( $in['pos_default'][ $i ] ) ? sanitize_text_field( $in['pos_default'][ $i ] ) : '',
					'sizes'        => $allowed,
				);
			}
		}
		if ( $positions ) {
			$out['positions'] = $positions;
		}

		// Size measurements.
		$measures = array();
		if ( ! empty( $in['meas_size'] ) && is_array( $in['meas_size'] ) ) {
			foreach ( $in['meas_size'] as $i => $sz ) {
				$sz = sanitize_text_field( $sz );
				if ( '' === $sz ) {
					continue;
				}
				$measures[] = array(
					'size'   => $sz,
					'width'  => isset( $in['meas_width'][ $i ] ) ? floatval( $in['meas_width'][ $i ] ) : 0,
					'height' => isset( $in['meas_height'][ $i ] ) ? floatval( $in['meas_height'][ $i ] ) : 0,
				);
			}
		}
		$out['measurements'] = $measures;

		// Optional extras.
		$extras = array();
		if ( ! empty( $in['extra_label'] ) && is_array( $in['extra_label'] ) ) {
			foreach ( $in['extra_label'] as $i => $label ) {
				$label = sanitize_text_field( $label );
				if ( '' === $label ) {
					continue;
				}
				$code = isset( $in['extra_code'][ $i ] ) && '' !== $in['extra_code'][ $i ]
					? sanitize_key( $in['extra_code'][ $i ] )
					: sanitize_key( $label );
				$extras[] = array(
					'code'  => $code,
					'label' => $label,
					'desc'  => isset( $in['extra_desc'][ $i ] ) ? sanitize_text_field( $in['extra_desc'][ $i ] ) : '',
					'price' => isset( $in['extra_price'][ $i ] ) ? round( floatval( $in['extra_price'][ $i ] ), 2 ) : 0,
					'per'   => ( isset( $in['extra_per'][ $i ] ) && 'order' === $in['extra_per'][ $i ] ) ? 'order' : 'unit',
				);
			}
		}
		$out['extras'] = $extras;

		// Production time options.
		$production = array();
		if ( ! empty( $in['prod_label'] ) && is_array( $in['prod_label'] ) ) {
			$def = isset( $in['prod_default'] ) ? intval( $in['prod_default'] ) : 0;
			foreach ( $in['prod_label'] as $i => $label ) {
				$label = sanitize_text_field( $label );
				if ( '' === $label ) {
					continue;
				}
				$code = isset( $in['prod_code'][ $i ] ) && '' !== $in['prod_code'][ $i ]
					? sanitize_key( $in['prod_code'][ $i ] )
					: sanitize_key( $label );
				$production[] = array(
					'code'    => $code,
					'label'   => $label,
					'days'    => isset( $in['prod_days'][ $i ] ) ? intval( $in['prod_days'][ $i ] ) : 0,
					'pct'     => isset( $in['prod_pct'][ $i ] ) ? floatval( $in['prod_pct'][ $i ] ) : 0,
					'unit'    => isset( $in['prod_unit'][ $i ] ) ? round( floatval( $in['prod_unit'][ $i ] ), 2 ) : 0,
					'default' => ( (int) $i === $def ) ? 1 : 0,
				);
			}
		}
		if ( $production ) {
			$out['production'] = $production;
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
				<p class="description">A descrição e as características (uma por linha) aparecem no cartão de cada modelo, como no exemplo (100% algodão, gramagem, Single Jersey, etc.).</p>
				<table class="widefat striped" id="fdtf-products">
					<thead><tr><th>ID</th><th>Nome</th><th>Preço/un.</th><th>Etiqueta</th><th style="text-align:center">Destaque</th><th>Descrição</th><th>Características (1 por linha)</th><th>Cores (uma por linha; vazio = todas)</th><th>Escalões (min-max:preço, 1 por linha)</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['products'] as $p ) : ?>
						<tr>
							<td><input type="text" name="product_id[]" value="<?php echo esc_attr( $p['id'] ); ?>" placeholder="auto" style="width:80px"></td>
							<td><input type="text" name="product_name[]" value="<?php echo esc_attr( $p['name'] ); ?>"></td>
							<td><input type="number" step="0.01" name="product_price[]" value="<?php echo esc_attr( $p['price'] ); ?>" class="small-text"></td>
							<td><input type="text" name="product_badge[]" value="<?php echo esc_attr( $p['badge'] ); ?>" style="width:110px"></td>
							<td style="text-align:center"><input type="checkbox" name="product_hot[<?php /* index set via JS below */ ?>]" <?php checked( ! empty( $p['badge_hot'] ) ); ?>></td>
							<td><textarea name="product_desc[]" rows="2" style="width:200px"><?php echo esc_textarea( isset( $p['desc'] ) ? $p['desc'] : '' ); ?></textarea></td>
							<td><textarea name="product_features[]" rows="3" style="width:200px" placeholder="100% algodão&#10;Malha 160 g/m²&#10;Single Jersey"><?php echo esc_textarea( isset( $p['features'] ) && is_array( $p['features'] ) ? implode( "\n", $p['features'] ) : '' ); ?></textarea></td>
							<td><textarea name="product_colors[]" rows="3" style="width:150px" placeholder="Branco&#10;Preto&#10;Cinza&#10;(vazio = todas)"><?php echo esc_textarea( isset( $p['color_names'] ) && is_array( $p['color_names'] ) ? implode( "\n", $p['color_names'] ) : '' ); ?></textarea></td>
							<td><textarea name="product_tiers[]" rows="4" style="width:150px" placeholder="1-9:6.95&#10;10-49:2.55&#10;1000+:1.85"><?php echo esc_textarea( self::tiers_to_text( isset( $p['tiers'] ) ? $p['tiers'] : array() ) ); ?></textarea></td>
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

				<h2>Tamanhos (t-shirt)</h2>
				<p><input type="text" name="sizes" value="<?php echo esc_attr( implode( ', ', $s['sizes'] ) ); ?>" class="regular-text">
					<span class="description">Separados por vírgula, ex.: XS, S, M, L, XL, XXL</span></p>

				<h2>Tamanhos de impressão (A7–A3)</h2>
				<p class="description">Preço por unidade de cada tamanho de impressão. O cliente escolhe o tamanho em cada posição (frente, costas, mangas).</p>
				<table class="widefat striped" id="fdtf-psizes" style="max-width:520px">
					<thead><tr><th>Código</th><th>Etiqueta</th><th>Preço/un.</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['print_sizes'] as $ps ) : ?>
						<tr>
							<td><input type="text" name="psize_code[]" value="<?php echo esc_attr( $ps['code'] ); ?>" class="small-text"></td>
							<td><input type="text" name="psize_label[]" value="<?php echo esc_attr( isset( $ps['label'] ) ? $ps['label'] : $ps['code'] ); ?>" class="small-text"></td>
							<td><input type="number" step="0.01" name="psize_price[]" value="<?php echo esc_attr( $ps['price'] ); ?>" class="small-text"></td>
							<td><button type="button" class="button fdtf-rm">×</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fdtf-add-psize">+ Adicionar tamanho de impressão</button></p>

				<h2>Posições de impressão</h2>
				<p class="description">Onde o cliente pode imprimir. Códigos reconhecidos para a pré-visualização: <code>frente</code>, <code>costas</code>, <code>peito_esq</code> (lado do coração), <code>manga_esq</code>, <code>manga_dta</code>. Em "Tamanhos permitidos" limita quais os tamanhos de impressão disponíveis nessa posição (vazio = todos). Ex.: nas mangas usar <code>A7,A6</code>.</p>
				<table class="widefat striped" id="fdtf-positions" style="max-width:820px">
					<thead><tr><th>Código</th><th>Nome apresentado</th><th>Tamanho por defeito</th><th>Tamanhos permitidos</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['positions'] as $pos ) : ?>
						<tr>
							<td><input type="text" name="pos_code[]" value="<?php echo esc_attr( $pos['code'] ); ?>" class="small-text"></td>
							<td><input type="text" name="pos_label[]" value="<?php echo esc_attr( $pos['label'] ); ?>" class="regular-text"></td>
							<td><input type="text" name="pos_default[]" value="<?php echo esc_attr( isset( $pos['default_size'] ) ? $pos['default_size'] : '' ); ?>" class="small-text" placeholder="A4"></td>
							<td><input type="text" name="pos_sizes[]" value="<?php echo esc_attr( isset( $pos['sizes'] ) && is_array( $pos['sizes'] ) ? implode( ',', $pos['sizes'] ) : '' ); ?>" placeholder="todos" style="width:150px"></td>
							<td><button type="button" class="button fdtf-rm">×</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fdtf-add-position">+ Adicionar posição</button></p>

				<h2>Medidas por tamanho (informativo)</h2>
				<p class="description">Tabela de medidas mostrada ao cliente no passo dos tamanhos (Largura / Altura em cm).</p>
				<table class="widefat striped" id="fdtf-measures" style="max-width:520px">
					<thead><tr><th>Tamanho</th><th>Largura (cm)</th><th>Altura (cm)</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['measurements'] as $m ) : ?>
						<tr>
							<td><input type="text" name="meas_size[]" value="<?php echo esc_attr( $m['size'] ); ?>" class="small-text"></td>
							<td><input type="number" step="0.1" name="meas_width[]" value="<?php echo esc_attr( $m['width'] ); ?>" class="small-text"></td>
							<td><input type="number" step="0.1" name="meas_height[]" value="<?php echo esc_attr( $m['height'] ); ?>" class="small-text"></td>
							<td><button type="button" class="button fdtf-rm">×</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fdtf-add-measure">+ Adicionar medida</button></p>

				<h2>Extras opcionais</h2>
				<p class="description">Opções adicionais (checkbox). "Por" = <code>unit</code> (por produto/unidade) ou <code>order</code> (por encomenda).</p>
				<table class="widefat striped" id="fdtf-extras" style="max-width:760px">
					<thead><tr><th>Código</th><th>Nome</th><th>Descrição</th><th>Preço</th><th>Por</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['extras'] as $e ) : ?>
						<tr>
							<td><input type="text" name="extra_code[]" value="<?php echo esc_attr( $e['code'] ); ?>" class="small-text"></td>
							<td><input type="text" name="extra_label[]" value="<?php echo esc_attr( $e['label'] ); ?>"></td>
							<td><input type="text" name="extra_desc[]" value="<?php echo esc_attr( isset( $e['desc'] ) ? $e['desc'] : '' ); ?>"></td>
							<td><input type="number" step="0.01" name="extra_price[]" value="<?php echo esc_attr( $e['price'] ); ?>" class="small-text"></td>
							<td>
								<select name="extra_per[]">
									<option value="unit" <?php selected( ! isset( $e['per'] ) || 'order' !== $e['per'] ); ?>>unit</option>
									<option value="order" <?php selected( isset( $e['per'] ) && 'order' === $e['per'] ); ?>>order</option>
								</select>
							</td>
							<td><button type="button" class="button fdtf-rm">×</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fdtf-add-extra">+ Adicionar extra</button></p>

				<h2>Tempo de produção</h2>
				<p class="description">Opções de prazo (radio). "%": acréscimo em percentagem sobre o preço unitário. "€/un.": acréscimo fixo por unidade. Marca uma como "Por defeito".</p>
				<table class="widefat striped" id="fdtf-production" style="max-width:820px">
					<thead><tr><th>Código</th><th>Nome</th><th>Dias</th><th>% acréscimo</th><th>€/un. acréscimo</th><th>Por defeito</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $s['production'] as $i => $pr ) : ?>
						<tr>
							<td><input type="text" name="prod_code[]" value="<?php echo esc_attr( $pr['code'] ); ?>" class="small-text"></td>
							<td><input type="text" name="prod_label[]" value="<?php echo esc_attr( $pr['label'] ); ?>"></td>
							<td><input type="number" name="prod_days[]" value="<?php echo esc_attr( $pr['days'] ); ?>" class="small-text"></td>
							<td><input type="number" step="0.01" name="prod_pct[]" value="<?php echo esc_attr( $pr['pct'] ); ?>" class="small-text"></td>
							<td><input type="number" step="0.01" name="prod_unit[]" value="<?php echo esc_attr( $pr['unit'] ); ?>" class="small-text"></td>
							<td style="text-align:center"><input type="radio" name="prod_default" value="<?php echo esc_attr( $i ); ?>" <?php checked( ! empty( $pr['default'] ) ); ?>></td>
							<td><button type="button" class="button fdtf-rm">×</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fdtf-add-production">+ Adicionar opção de produção</button></p>

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

			// Keep the "por defeito" radio values in sync with row order.
			function reindexProdDefault() {
				document.querySelectorAll( '#fdtf-production tbody tr' ).forEach( function ( tr, i ) {
					var rb = tr.querySelector( 'input[type=radio]' );
					if ( rb ) { rb.value = i; }
				} );
			}
			reindexProdDefault();

			document.getElementById( 'fdtf-add-product' ).addEventListener( 'click', function () {
				var tb = prodTable.querySelector( 'tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="product_id[]" placeholder="auto" style="width:80px"></td>' +
					'<td><input type="text" name="product_name[]"></td>' +
					'<td><input type="number" step="0.01" name="product_price[]" class="small-text"></td>' +
					'<td><input type="text" name="product_badge[]" style="width:110px"></td>' +
					'<td style="text-align:center"><input type="checkbox"></td>' +
					'<td><textarea name="product_desc[]" rows="2" style="width:200px"></textarea></td>' +
					'<td><textarea name="product_features[]" rows="3" style="width:200px"></textarea></td>' +
						'<td><textarea name="product_colors[]" rows="3" style="width:150px" placeholder="vazio = todas"></textarea></td>' +
						'<td><textarea name="product_tiers[]" rows="4" style="width:150px" placeholder="1-9:6.95&#10;1000+:1.85"></textarea></td>' +
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

			document.getElementById( 'fdtf-add-psize' ).addEventListener( 'click', function () {
				var tb = document.querySelector( '#fdtf-psizes tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="psize_code[]" class="small-text"></td>' +
					'<td><input type="text" name="psize_label[]" class="small-text"></td>' +
					'<td><input type="number" step="0.01" name="psize_price[]" class="small-text"></td>' +
					'<td><button type="button" class="button fdtf-rm">×</button></td>';
				tb.appendChild( tr );
			} );

			document.getElementById( 'fdtf-add-position' ).addEventListener( 'click', function () {
				var tb = document.querySelector( '#fdtf-positions tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="pos_code[]" class="small-text"></td>' +
					'<td><input type="text" name="pos_label[]" class="regular-text"></td>' +
					'<td><input type="text" name="pos_default[]" class="small-text" placeholder="A4"></td>' +
					'<td><input type="text" name="pos_sizes[]" placeholder="todos" style="width:150px"></td>' +
					'<td><button type="button" class="button fdtf-rm">×</button></td>';
				tb.appendChild( tr );
			} );

			document.getElementById( 'fdtf-add-measure' ).addEventListener( 'click', function () {
				var tb = document.querySelector( '#fdtf-measures tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="meas_size[]" class="small-text"></td>' +
					'<td><input type="number" step="0.1" name="meas_width[]" class="small-text"></td>' +
					'<td><input type="number" step="0.1" name="meas_height[]" class="small-text"></td>' +
					'<td><button type="button" class="button fdtf-rm">×</button></td>';
				tb.appendChild( tr );
			} );

			document.getElementById( 'fdtf-add-extra' ).addEventListener( 'click', function () {
				var tb = document.querySelector( '#fdtf-extras tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="extra_code[]" class="small-text"></td>' +
					'<td><input type="text" name="extra_label[]"></td>' +
					'<td><input type="text" name="extra_desc[]"></td>' +
					'<td><input type="number" step="0.01" name="extra_price[]" class="small-text"></td>' +
					'<td><select name="extra_per[]"><option value="unit">unit</option><option value="order">order</option></select></td>' +
					'<td><button type="button" class="button fdtf-rm">×</button></td>';
				tb.appendChild( tr );
			} );

			document.getElementById( 'fdtf-add-production' ).addEventListener( 'click', function () {
				var tb = document.querySelector( '#fdtf-production tbody' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="text" name="prod_code[]" class="small-text"></td>' +
					'<td><input type="text" name="prod_label[]"></td>' +
					'<td><input type="number" name="prod_days[]" class="small-text"></td>' +
					'<td><input type="number" step="0.01" name="prod_pct[]" class="small-text"></td>' +
					'<td><input type="number" step="0.01" name="prod_unit[]" class="small-text"></td>' +
					'<td style="text-align:center"><input type="radio" name="prod_default"></td>' +
					'<td><button type="button" class="button fdtf-rm">×</button></td>';
				tb.appendChild( tr );
				reindexProdDefault();
			} );

			document.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'fdtf-rm' ) ) {
					var tr = e.target.closest( 'tr' );
					var table = tr.closest( 'table' );
					tr.remove();
					if ( table.id === 'fdtf-products' ) { reindexHot( table ); }
					if ( table.id === 'fdtf-production' ) { reindexProdDefault(); }
				}
			} );
		} )();
		</script>
		<?php
	}
}
