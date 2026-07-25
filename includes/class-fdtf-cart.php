<?php
/**
 * WooCommerce cart & order integration for the Fábrica DTF Configurador.
 *
 * The configured t-shirt is added to the cart as a single line item against a
 * hidden "container" product. The price, colour, sizes and uploaded art are
 * carried as custom cart-item data, the price is overridden server-side, and
 * everything is persisted to the order so it appears in admin / emails.
 *
 * @package FabricaDTF_Configurador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDTF_Cart {

	public function __construct() {
		// AJAX endpoints (logged in + guests).
		add_action( 'wp_ajax_fdtf_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_fdtf_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

		// Carry custom data through the cart.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_custom_price' ), 20, 1 );

		// Keep each configuration a distinct line item.
		add_filter( 'woocommerce_cart_item_name', array( $this, 'cart_item_name' ), 10, 3 );

		// Persist to the order.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

		// Housekeeping.
		add_action( 'fdtf_cleanup_temp_uploads', array( $this, 'cleanup_temp_uploads' ) );
	}

	/**
	 * Get (or lazily create) the hidden container product used for the cart line.
	 *
	 * @return int Product ID.
	 */
	public static function base_product_id() {
		$id = (int) get_option( FDTF_BASE_PRODUCT_OPTION );
		if ( $id && 'product' === get_post_type( $id ) ) {
			return $id;
		}

		$product = new WC_Product_Simple();
		$product->set_name( 'T-shirt Personalizada' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->set_sold_individually( false );
		$product->set_virtual( false );
		$product->set_tax_status( 'taxable' );
		$id = $product->save();

		update_option( FDTF_BASE_PRODUCT_OPTION, $id );
		return $id;
	}

	/**
	 * Directory used for uploaded art (under wp-content/uploads).
	 *
	 * @return array {path, url}
	 */
	private static function upload_dir() {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'fdtf-arte';
		$url = trailingslashit( $up['baseurl'] ) . 'fdtf-arte';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			// Prevent directory listing.
			@file_put_contents( trailingslashit( $dir ) . 'index.html', '' );
		}
		return array( 'path' => $dir, 'url' => $url );
	}

	/**
	 * AJAX: validate the configuration, compute the price server-side, store the
	 * uploaded art, and add the item to the cart.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'fdtf_nonce', 'nonce' );

		$s    = FDTF_Settings::get();
		$data = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : null;

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => 'Pedido inválido.' ) );
		}

		// Resolve the selected product against the trusted server config.
		$product_key = isset( $data['product'] ) ? sanitize_title( $data['product'] ) : '';
		$product     = null;
		foreach ( $s['products'] as $p ) {
			if ( $p['id'] === $product_key ) {
				$product = $p;
				break;
			}
		}
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Modelo inválido.' ) );
		}

		// Colour.
		$color_name = isset( $data['color'] ) ? sanitize_text_field( $data['color'] ) : '';
		$color_hex  = isset( $data['colorHex'] ) ? sanitize_hex_color( $data['colorHex'] ) : '';

		// Validate the colour against the master palette and this product's allowed subset.
		$master_map = array();
		foreach ( (array) $s['colors'] as $mc ) {
			$master_map[ strtolower( trim( $mc['name'] ) ) ] = $mc;
		}
		$allowed_colors = ( ! empty( $product['color_names'] ) && is_array( $product['color_names'] ) )
			? array_map( function ( $n ) { return strtolower( trim( $n ) ); }, $product['color_names'] )
			: array();
		$ckey = strtolower( trim( $color_name ) );
		if ( '' !== $ckey ) {
			if ( ! empty( $allowed_colors ) && ! in_array( $ckey, $allowed_colors, true ) ) {
				wp_send_json_error( array( 'message' => 'Cor não disponível para este modelo.' ) );
			}
			if ( isset( $master_map[ $ckey ] ) ) {
				$color_name = $master_map[ $ckey ]['name'];
				$color_hex  = $master_map[ $ckey ]['hex'];
			}
		}

		// Sizes / quantities.
		$sizes    = array();
		$total_qty = 0;
		if ( ! empty( $data['sizes'] ) && is_array( $data['sizes'] ) ) {
			foreach ( $data['sizes'] as $sz => $q ) {
				$q = max( 0, intval( $q ) );
				if ( $q > 0 && in_array( $sz, $s['sizes'], true ) ) {
					$sizes[ sanitize_text_field( $sz ) ] = $q;
					$total_qty += $q;
				}
			}
		}
		if ( $total_qty < 1 ) {
			wp_send_json_error( array( 'message' => 'Escolhe pelo menos 1 unidade.' ) );
		}

		// Trusted lookups for positions & print sizes.
		$pos_map      = array();
		$pos_allowed  = array();
		foreach ( $s['positions'] as $p ) {
			$pos_map[ $p['code'] ]     = $p['label'];
			$pos_allowed[ $p['code'] ] = ( isset( $p['sizes'] ) && is_array( $p['sizes'] ) ) ? $p['sizes'] : array();
		}
		$size_map = array();
		foreach ( $s['print_sizes'] as $ps ) {
			$size_map[ $ps['code'] ] = floatval( $ps['price'] );
		}

		// Personalisation positions (front / back / sleeves / chest), each with its own art + print size.
		$positions   = array();
		$perso_price = 0.0;
		if ( ! empty( $data['positions'] ) && is_array( $data['positions'] ) ) {
			foreach ( $data['positions'] as $pdata ) {
				$code = isset( $pdata['code'] ) ? sanitize_key( $pdata['code'] ) : '';
				$size = isset( $pdata['size'] ) ? sanitize_text_field( $pdata['size'] ) : '';
				if ( ! isset( $pos_map[ $code ] ) || ! isset( $size_map[ $size ] ) ) {
					continue; // unknown position or size — ignore.
				}
				// Enforce per-position allowed sizes (e.g. sleeves only A7/A6).
				$allowed = $pos_allowed[ $code ];
				if ( ! empty( $allowed ) && ! in_array( $size, $allowed, true ) ) {
					wp_send_json_error( array( 'message' => $pos_map[ $code ] . ': tamanho ' . $size . ' não permitido.' ) );
				}

				// Each declared position must carry its art file.
				$art = $this->handle_upload( $s, 'art_' . $code );
				if ( is_wp_error( $art ) ) {
					wp_send_json_error( array( 'message' => $pos_map[ $code ] . ': ' . $art->get_error_message() ) );
				}
				if ( ! $art ) {
					continue; // no file for this position — skip it.
				}

				$sprice       = $size_map[ $size ];
				$perso_price += $sprice;
				$positions[]  = array(
					'code'       => $code,
					'label'      => $pos_map[ $code ],
					'size'       => $size,
					'size_price' => $sprice,
					'art_name'   => $art['name'],
					'art_url'    => $art['url'],
					'art_path'   => $art['path'],
				);
			}
		}

		// Optional extras (checkboxes) — validate against trusted config.
		$extras       = array();
		$extras_unit  = 0.0;
		$extras_order = 0.0;
		$sel_extras   = ( ! empty( $data['extras'] ) && is_array( $data['extras'] ) ) ? array_map( 'sanitize_key', $data['extras'] ) : array();
		foreach ( (array) $s['extras'] as $e ) {
			if ( in_array( sanitize_key( $e['code'] ), $sel_extras, true ) ) {
				$eprice = floatval( $e['price'] );
				$eper   = ( isset( $e['per'] ) && 'order' === $e['per'] ) ? 'order' : 'unit';
				if ( 'order' === $eper ) {
					$extras_order += $eprice;
				} else {
					$extras_unit += $eprice;
				}
				$extras[] = array( 'code' => $e['code'], 'label' => $e['label'], 'price' => $eprice, 'per' => $eper );
			}
		}

		// Production time — validate and compute surcharge.
		$prod_code    = isset( $data['production'] ) ? sanitize_key( $data['production'] ) : '';
		$production   = null;
		$prod_pct     = 0.0;
		$prod_unit_fx = 0.0;
		foreach ( (array) $s['production'] as $pr ) {
			if ( sanitize_key( $pr['code'] ) === $prod_code ) {
				$production = $pr;
				break;
			}
		}
		if ( ! $production ) {
			// fall back to default / first.
			foreach ( (array) $s['production'] as $pr ) {
				if ( ! empty( $pr['default'] ) ) { $production = $pr; break; }
			}
			if ( ! $production && ! empty( $s['production'] ) ) {
				$production = $s['production'][0];
			}
		}
		if ( $production ) {
			$prod_pct     = floatval( $production['pct'] );
			$prod_unit_fx = floatval( $production['unit'] );
		}

		// Server-side price (never trust the client value).
		$unit_price      = floatval( $product['price'] );
		$prod_surcharge  = round( ( $unit_price + $perso_price + $extras_unit ) * $prod_pct / 100 + $prod_unit_fx, 2 );
		$net_per_unit    = $unit_price + $perso_price + $extras_unit + $prod_surcharge;
		$net_total       = round( $net_per_unit * $total_qty + $extras_order, 2 );

		$notes = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';

		$fdtf = array(
			'product_id'   => $product['id'],
			'product_name' => $product['name'],
			'color'        => $color_name,
			'color_hex'    => $color_hex,
			'sizes'        => $sizes,
			'total_qty'    => $total_qty,
			'unit_price'   => $unit_price,
			'perso_price'  => $perso_price,
			'positions'    => $positions,
			'extras'       => $extras,
			'production'   => $production ? array( 'code' => $production['code'], 'label' => $production['label'], 'days' => intval( $production['days'] ) ) : null,
			'prod_surcharge' => $prod_surcharge,
			'notes'        => $notes,
			'line_total'   => $net_total,
			'uid'          => md5( wp_json_encode( $data ) . microtime( true ) ),
		);

		$added = WC()->cart->add_to_cart(
			self::base_product_id(),
			1,
			0,
			array(),
			array( 'fdtf' => $fdtf )
		);

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => 'Não foi possível adicionar ao carrinho.' ) );
		}

		wp_send_json_success( array(
			'message'  => 'Adicionado ao carrinho!',
			'redirect' => wc_get_cart_url(),
		) );
	}

	/**
	 * Validate and move the uploaded art file into the uploads folder.
	 *
	 * @param array  $s     Settings.
	 * @param string $field The $_FILES field name (e.g. art_frente).
	 * @return array|WP_Error|null  File info, error, or null if no file.
	 */
	private function handle_upload( $s, $field = 'art' ) {
		if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) {
			return null;
		}

		$file    = $_FILES[ $field ];
		$max     = intval( $s['max_mb'] ) * 1024 * 1024;
		$allowed = array_filter( array_map(
			function ( $e ) { return ltrim( strtolower( trim( $e ) ), '.' ); },
			explode( ',', $s['accept'] )
		) );

		if ( $file['size'] > $max ) {
			return new WP_Error( 'too_big', 'Ficheiro demasiado grande (máx ' . intval( $s['max_mb'] ) . ' MB).' );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $allowed && ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error( 'bad_type', 'Formato não permitido. Aceites: ' . esc_html( $s['accept'] ) );
		}

		$mimes = array(
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg'=> 'image/jpeg',
			'pdf' => 'application/pdf',
			'svg' => 'image/svg+xml',
		);

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$dir = self::upload_dir();
		add_filter( 'upload_dir', $cb = function ( $u ) use ( $dir ) {
			$u['path']   = $dir['path'];
			$u['url']    = $dir['url'];
			$u['subdir'] = '';
			return $u;
		} );

		$safe_name = wp_unique_filename( $dir['path'], sanitize_file_name( $file['name'] ) );
		$file['name'] = $safe_name;

		$moved = wp_handle_upload( $file, array(
			'test_form' => false,
			'mimes'     => $mimes,
		) );

		remove_filter( 'upload_dir', $cb );

		if ( ! $moved || isset( $moved['error'] ) ) {
			return new WP_Error( 'upload_fail', isset( $moved['error'] ) ? $moved['error'] : 'Falha no upload.' );
		}

		return array(
			'name' => sanitize_file_name( $file['name'] ),
			'url'  => $moved['url'],
			'path' => $moved['file'],
		);
	}

	/**
	 * Attach our custom data to the cart item and force uniqueness so each
	 * configuration stays a separate line.
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( (int) $product_id === self::base_product_id() && empty( $cart_item_data['fdtf'] ) ) {
			// Direct add without config data — ignore.
		}
		if ( ! empty( $cart_item_data['fdtf']['uid'] ) ) {
			$cart_item_data['unique_key'] = $cart_item_data['fdtf']['uid'];
		}
		return $cart_item_data;
	}

	/**
	 * Show the configuration nicely in the cart & checkout.
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['fdtf'] ) ) {
			return $item_data;
		}
		$f = $cart_item['fdtf'];

		if ( ! empty( $f['color'] ) ) {
			$item_data[] = array( 'key' => 'Cor', 'value' => esc_html( $f['color'] ) );
		}
		if ( ! empty( $f['sizes'] ) ) {
			$parts = array();
			foreach ( $f['sizes'] as $sz => $q ) {
				$parts[] = intval( $q ) . '× ' . esc_html( $sz );
			}
			$item_data[] = array( 'key' => 'Tamanhos', 'value' => implode( ', ', $parts ) . ' (' . intval( $f['total_qty'] ) . ' un.)' );
		}
		if ( ! empty( $f['positions'] ) && is_array( $f['positions'] ) ) {
			foreach ( $f['positions'] as $pos ) {
				$art = ! empty( $pos['art_url'] )
					? ' — <a href="' . esc_url( $pos['art_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $pos['art_name'] ) . '</a>'
					: ( ! empty( $pos['art_name'] ) ? ' — ' . esc_html( $pos['art_name'] ) : '' );
				$item_data[] = array(
					'key'   => esc_html( $pos['label'] ),
					'value' => esc_html( $pos['size'] ) . $art,
				);
			}
		}
		if ( ! empty( $f['extras'] ) && is_array( $f['extras'] ) ) {
			$names = array();
			foreach ( $f['extras'] as $e ) {
				$names[] = esc_html( $e['label'] );
			}
			if ( $names ) {
				$item_data[] = array( 'key' => 'Extras', 'value' => implode( ', ', $names ) );
			}
		}
		if ( ! empty( $f['production']['label'] ) ) {
			$item_data[] = array( 'key' => 'Produção', 'value' => esc_html( $f['production']['label'] ) );
		}
		if ( ! empty( $f['notes'] ) ) {
			$item_data[] = array( 'key' => 'Notas', 'value' => esc_html( $f['notes'] ) );
		}
		return $item_data;
	}

	/**
	 * Override the line price with the server-computed total.
	 */
	public function set_custom_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['fdtf']['line_total'] ) ) {
				$cart_item['data']->set_price( floatval( $cart_item['fdtf']['line_total'] ) );
			}
		}
	}

	/**
	 * Friendlier cart line name.
	 */
	public function cart_item_name( $name, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item['fdtf']['product_name'] ) ) {
			return esc_html( $cart_item['fdtf']['product_name'] ) . ' <small>(personalizada)</small>';
		}
		return $name;
	}

	/**
	 * Save the configuration onto the order line item so it shows in admin/emails.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['fdtf'] ) ) {
			return;
		}
		$f = $values['fdtf'];

		$item->add_meta_data( 'Modelo', $f['product_name'], true );
		if ( ! empty( $f['color'] ) ) {
			$item->add_meta_data( 'Cor', $f['color'], true );
		}
		if ( ! empty( $f['sizes'] ) ) {
			$parts = array();
			foreach ( $f['sizes'] as $sz => $q ) {
				$parts[] = intval( $q ) . '× ' . $sz;
			}
			$item->add_meta_data( 'Tamanhos', implode( ', ', $parts ), true );
			$item->add_meta_data( 'Unidades', intval( $f['total_qty'] ), true );
		}
		if ( ! empty( $f['positions'] ) && is_array( $f['positions'] ) ) {
			foreach ( $f['positions'] as $pos ) {
				$val = $pos['size'];
				if ( ! empty( $pos['art_name'] ) ) {
					$val .= ' · ' . $pos['art_name'];
				}
				$item->add_meta_data( $pos['label'], $val, true );
				if ( ! empty( $pos['art_url'] ) ) {
					$item->add_meta_data( $pos['label'] . ' (link)', $pos['art_url'], true );
				}
			}
		}
		if ( ! empty( $f['extras'] ) && is_array( $f['extras'] ) ) {
			$names = array();
			foreach ( $f['extras'] as $e ) {
				$names[] = $e['label'];
			}
			if ( $names ) {
				$item->add_meta_data( 'Extras', implode( ', ', $names ), true );
			}
		}
		if ( ! empty( $f['production']['label'] ) ) {
			$item->add_meta_data( 'Tempo de produção', $f['production']['label'], true );
		}
		if ( ! empty( $f['notes'] ) ) {
			$item->add_meta_data( 'Notas', $f['notes'], true );
		}
		// Hidden internal reference for fulfilment.
		$item->add_meta_data( '_fdtf_data', wp_json_encode( $f ), true );
	}

	/**
	 * Remove orphaned uploads older than 7 days that never reached an order.
	 * (Files referenced by orders live in order meta and are not touched here —
	 * this only trims abandoned cart uploads by age.)
	 */
	public function cleanup_temp_uploads() {
		$dir = self::upload_dir();
		$now = time();
		foreach ( (array) glob( trailingslashit( $dir['path'] ) . '*' ) as $file ) {
			if ( is_file( $file ) && basename( $file ) !== 'index.html' ) {
				if ( ( $now - filemtime( $file ) ) > 30 * DAY_IN_SECONDS ) {
					@unlink( $file );
				}
			}
		}
	}
}
