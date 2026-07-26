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
		add_action( 'wp_ajax_fdtf_add_dtf', array( $this, 'ajax_add_dtf' ) );
		add_action( 'wp_ajax_nopriv_fdtf_add_dtf', array( $this, 'ajax_add_dtf' ) );

		// Serves a fresh security nonce. The configurator page can be served from a
		// full-page cache (LiteSpeed) whose baked-in nonce has since expired; the
		// front-end fetches a live nonce from this uncached endpoint on load.
		add_action( 'wp_ajax_fdtf_nonce', array( $this, 'ajax_nonce' ) );
		add_action( 'wp_ajax_nopriv_fdtf_nonce', array( $this, 'ajax_nonce' ) );

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
			// Self-heal: the container must stay published & purchasable, otherwise
			// logged-out customers get "este produto não pode ser comprado" and
			// add-to-cart fails (while logged-in admins can still buy a draft,
			// which is why the bug looks intermittent).
			$product = wc_get_product( $id );
			if ( $product ) {
				$changed = false;
				if ( 'publish' !== $product->get_status() ) { $product->set_status( 'publish' ); $changed = true; }
				if ( '' === (string) $product->get_price() ) { $product->set_price( 0 ); $product->set_regular_price( 0 ); $changed = true; }
				if ( 'hidden' !== $product->get_catalog_visibility() ) { $product->set_catalog_visibility( 'hidden' ); $changed = true; }
				if ( ! $product->is_in_stock() ) { $product->set_stock_status( 'instock' ); $changed = true; }
				if ( $changed ) { $product->save(); }
				return $id;
			}
		}

		$product = new WC_Product_Simple();
		$product->set_name( 'T-shirt Personalizada' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->set_stock_status( 'instock' );
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
	/**
	 * AJAX: return a fresh nonce (used to refresh a stale cached-page nonce).
	 */
	public function ajax_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'fdtf_nonce' ) ) );
	}

	/**
	 * AJAX: add a "DTF a Metro" order to the cart. Quantity is in linear metres,
	 * priced per-metre by tier. Price is computed server-side (never trust client).
	 */
	public function ajax_add_dtf() {
		if ( ! check_ajax_referer( 'fdtf_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'bad_nonce', 'message' => 'Sessão expirada. Tente novamente.' ) );
		}

		$s   = FDTF_Settings::get();
		$dtf = isset( $s['dtf'] ) && is_array( $s['dtf'] ) ? $s['dtf'] : array();
		if ( empty( $dtf['tiers'] ) || ! is_array( $dtf['tiers'] ) ) {
			wp_send_json_error( array( 'message' => 'Configuração de preços indisponível.' ) );
		}

		$meters = isset( $_POST['meters'] ) ? intval( $_POST['meters'] ) : 0;
		$min_m  = isset( $dtf['min_m'] ) ? max( 1, intval( $dtf['min_m'] ) ) : 1;
		if ( $meters < $min_m ) {
			wp_send_json_error( array( 'message' => 'Quantidade mínima de ' . $min_m . ' metro(s).' ) );
		}

		// Resolve the tier for this quantity (server-authoritative price).
		$unit = null; $tier_label = '';
		foreach ( $dtf['tiers'] as $t ) {
			$tmin = intval( $t['min'] );
			$tmax = intval( $t['max'] );
			$hi   = ( $tmax > 0 ) ? $tmax : PHP_INT_MAX;
			if ( $meters >= $tmin && $meters <= $hi ) {
				$unit       = floatval( $t['price'] );
				$tier_label = isset( $t['label'] ) ? $t['label'] : '';
				break;
			}
		}
		if ( null === $unit ) {
			// Above the last defined range: use the highest tier.
			$last       = end( $dtf['tiers'] );
			$unit       = floatval( $last['price'] );
			$tier_label = isset( $last['label'] ) ? $last['label'] : '';
		}

		// Art file is required for a DTF order.
		$upload_cfg = array(
			'accept' => isset( $dtf['accept'] ) ? $dtf['accept'] : '.png,.jpg,.jpeg,.pdf',
			'max_mb' => isset( $dtf['max_mb'] ) ? intval( $dtf['max_mb'] ) : 40,
		);
		$art = $this->handle_upload( $upload_cfg, 'art' );
		if ( is_wp_error( $art ) ) {
			wp_send_json_error( array( 'message' => $art->get_error_message() ) );
		}
		if ( ! $art ) {
			wp_send_json_error( array( 'message' => 'Por favor envie o ficheiro do seu design.' ) );
		}

		$line_total = round( $unit * $meters, 2 );
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		$fdtf = array(
			'type'         => 'dtf',
			'product_name' => isset( $dtf['title'] ) ? $dtf['title'] : 'DTF a Metro',
			'meters'       => $meters,
			'tier_label'   => $tier_label,
			'unit_price'   => $unit,
			'line_total'   => $line_total,
			'art_name'     => $art['name'],
			'art_url'      => $art['url'],
			'art_path'     => $art['path'],
			'notes'        => $notes,
			'uid'          => md5( 'dtf' . $art['name'] . $meters . microtime( true ) ),
		);

		$added = WC()->cart->add_to_cart( self::base_product_id(), 1, 0, array(), array( 'fdtf' => $fdtf ) );
		if ( ! $added ) {
			wp_send_json_error( array( 'message' => 'Não foi possível adicionar ao carrinho.' ) );
		}

		wp_send_json_success( array(
			'message'  => 'Adicionado ao carrinho!',
			'redirect' => wc_get_cart_url(),
		) );
	}

	public function ajax_add_to_cart() {
		// Verify without dying, so a stale nonce (expired cached-page nonce)
		// returns a clean JSON error the front-end can detect and auto-retry.
		if ( ! check_ajax_referer( 'fdtf_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'code'    => 'bad_nonce',
					'message' => 'Sessão expirada. Tente novamente.',
				)
			);
		}

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
		$min_qty = isset( $s['min_qty'] ) ? max( 1, intval( $s['min_qty'] ) ) : 5;
		if ( $total_qty < $min_qty ) {
			wp_send_json_error( array( 'message' => 'Quantidade mínima de ' . $min_qty . ' unidades por encomenda.' ) );
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
		$unit_price      = $this->tier_price( $product, $total_qty );
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
	/**
	 * Per-unit base price for the given total quantity, using the product's
	 * quantity tiers (bulk pricing). Falls back to the flat price if no tiers.
	 */
	private function tier_price( $product, $qty ) {
		$base = floatval( $product['price'] );
		if ( empty( $product['tiers'] ) || ! is_array( $product['tiers'] ) ) {
			return $base;
		}
		$q = max( intval( $qty ), 0 );
		$fallback = null;
		foreach ( $product['tiers'] as $t ) {
			$min = intval( $t['min'] );
			$max = intval( $t['max'] );
			if ( null === $fallback ) { $fallback = floatval( $t['price'] ); }
			$hi = ( $max > 0 ) ? $max : PHP_INT_MAX;
			if ( $q >= $min && $q <= $hi ) {
				return floatval( $t['price'] );
			}
		}
		return ( null !== $fallback ) ? $fallback : $base;
	}

	private function handle_upload( $s, $field = 'art' ) {
		if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) {
			return null;
		}

		$file = $_FILES[ $field ];

		// PHP-level upload errors (partial upload, size limits, no tmp dir…).
		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
				return null;
			}
			if ( in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
				return new WP_Error( 'too_big', 'Ficheiro demasiado grande.' );
			}
			return new WP_Error( 'upload_fail', 'Falha no carregamento do ficheiro. Tente novamente.' );
		}

		// Must be a genuine PHP upload (not an injected path).
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload_fail', 'Falha no carregamento do ficheiro. Tente novamente.' );
		}

		$max     = intval( $s['max_mb'] ) * 1024 * 1024;
		$allowed = array_filter( array_map(
			function ( $e ) { return ltrim( strtolower( trim( $e ) ), '.' ); },
			explode( ',', $s['accept'] )
		) );
		// Safety net: if no accept list is configured, fall back to the known-safe set.
		if ( empty( $allowed ) ) {
			$allowed = array( 'png', 'jpg', 'jpeg', 'pdf' );
		}

		if ( $file['size'] > $max ) {
			return new WP_Error( 'too_big', 'Ficheiro demasiado grande (máx ' . intval( $s['max_mb'] ) . ' MB).' );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $ext && ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error( 'bad_type', 'Formato não permitido. Aceites: ' . esc_html( $s['accept'] ) );
		}

		// Verify the real file content matches the extension (defence in depth).
		// We move the file ourselves instead of wp_handle_upload() because the
		// latter requires the 'unfiltered_upload' capability, which logged-out
		// customers never have — that gate would reject every guest upload.
		if ( ! $this->content_matches_ext( $file['tmp_name'], $ext ) ) {
			return new WP_Error( 'bad_type', 'O ficheiro não é um ' . strtoupper( $ext ) . ' válido.' );
		}

		$dir = self::upload_dir();
		if ( empty( $dir['path'] ) || ! wp_is_writable( $dir['path'] ) ) {
			return new WP_Error( 'upload_fail', 'Não foi possível guardar o ficheiro. Tente novamente.' );
		}

		$safe_name = wp_unique_filename( $dir['path'], sanitize_file_name( $file['name'] ) );
		$dest      = trailingslashit( $dir['path'] ) . $safe_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'upload_fail', 'Não foi possível guardar o ficheiro. Tente novamente.' );
		}
		// Match WordPress' default upload permissions.
		$perms = ( defined( 'FS_CHMOD_FILE' ) && FS_CHMOD_FILE ) ? FS_CHMOD_FILE : 0644;
		@chmod( $dest, $perms );

		return array(
			'name' => $safe_name,
			'url'  => trailingslashit( $dir['url'] ) . $safe_name,
			'path' => $dest,
		);
	}

	/**
	 * Confirm the uploaded file's real content matches its extension.
	 * Images must decode as that image type; PDFs must start with %PDF.
	 */
	private function content_matches_ext( $tmp, $ext ) {
		if ( in_array( $ext, array( 'png', 'jpg', 'jpeg' ), true ) ) {
			$info = @getimagesize( $tmp );
			if ( ! $info || empty( $info[2] ) ) {
				return false;
			}
			$want = ( 'png' === $ext ) ? array( IMAGETYPE_PNG ) : array( IMAGETYPE_JPEG );
			return in_array( (int) $info[2], $want, true );
		}
		if ( 'pdf' === $ext ) {
			$fh = @fopen( $tmp, 'rb' );
			if ( ! $fh ) {
				return false;
			}
			$head = fread( $fh, 5 );
			fclose( $fh );
			return ( '%PDF-' === substr( (string) $head, 0, 5 ) );
		}
		// Unknown extension already rejected by the allow-list above.
		return false;
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

		// DTF a Metro line.
		if ( isset( $f['type'] ) && 'dtf' === $f['type'] ) {
			if ( ! empty( $f['tier_label'] ) ) {
				$item_data[] = array( 'key' => 'Tamanho', 'value' => esc_html( $f['tier_label'] ) );
			}
			$item_data[] = array( 'key' => 'Metros', 'value' => intval( $f['meters'] ) . ' m' );
			if ( ! empty( $f['unit_price'] ) ) {
				$item_data[] = array( 'key' => 'Preço', 'value' => wc_price( floatval( $f['unit_price'] ) ) . ' / metro' );
			}
			if ( ! empty( $f['art_url'] ) ) {
				$item_data[] = array(
					'key'   => 'Ficheiro',
					'value' => '<a href="' . esc_url( $f['art_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $f['art_name'] ) . '</a>',
				);
			} elseif ( ! empty( $f['art_name'] ) ) {
				$item_data[] = array( 'key' => 'Ficheiro', 'value' => esc_html( $f['art_name'] ) );
			}
			if ( ! empty( $f['notes'] ) ) {
				$item_data[] = array( 'key' => 'Notas', 'value' => esc_html( $f['notes'] ) );
			}
			return $item_data;
		}

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
			$f     = $cart_item['fdtf'];
			$tag   = ( isset( $f['type'] ) && 'dtf' === $f['type'] ) ? 'por metro' : 'personalizada';
			return esc_html( $f['product_name'] ) . ' <small>(' . $tag . ')</small>';
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

		// DTF a Metro order line.
		if ( isset( $f['type'] ) && 'dtf' === $f['type'] ) {
			$item->add_meta_data( 'Produto', $f['product_name'], true );
			if ( ! empty( $f['tier_label'] ) ) {
				$item->add_meta_data( 'Tamanho', $f['tier_label'], true );
			}
			$item->add_meta_data( 'Metros', intval( $f['meters'] ) . ' m', true );
			$item->add_meta_data( 'Preço/metro', number_format( floatval( $f['unit_price'] ), 2, ',', '' ) . ' €', true );
			if ( ! empty( $f['art_name'] ) ) {
				$item->add_meta_data( 'Ficheiro', $f['art_name'], true );
			}
			if ( ! empty( $f['art_url'] ) ) {
				$item->add_meta_data( 'Ficheiro (link)', $f['art_url'], true );
			}
			if ( ! empty( $f['notes'] ) ) {
				$item->add_meta_data( 'Notas', $f['notes'], true );
			}
			$item->add_meta_data( '_fdtf_data', wp_json_encode( $f ), true );
			return;
		}

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
