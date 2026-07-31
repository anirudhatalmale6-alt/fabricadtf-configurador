<?php
/**
 * Pop-up de subscrição com desconto automático.
 *
 * Mostra um pop-up (imagem promocional + captura de email) aos visitantes. Ao
 * subscrever, o cliente é inscrito na newsletter (Mailchimp, se ligado), é
 * gerado um cupão WooCommerce único de 10% (uso único), o código é enviado por
 * email (via o SMTP configurado) e é aplicado automaticamente no checkout.
 *
 * @package FabricaDTF_Configurador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDTF_Popup {

	const COOKIE_COUPON = 'fdtf_welcome_coupon';
	const LEADS_OPTION  = 'fdtf_popup_leads';

	public function __construct() {
		// Enqueue assets early (wp_footer is too late for enqueuing), render markup in the footer.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 20 );

		// AJAX: subscribe + fresh nonce (works for guests too).
		add_action( 'wp_ajax_fdtf_popup', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_fdtf_popup', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_fdtf_popup_nonce', array( $this, 'ajax_nonce' ) );
		add_action( 'wp_ajax_nopriv_fdtf_popup_nonce', array( $this, 'ajax_nonce' ) );

		// Auto-apply the welcome coupon on cart / checkout.
		add_action( 'template_redirect', array( $this, 'auto_apply_coupon' ) );
	}

	/**
	 * Read the pop-up configuration (from stored settings, falling back to the
	 * PHP defaults — the stored option has no 'popup' key, so defaults win).
	 *
	 * @return array
	 */
	private function config() {
		$all = FDTF_Settings::get();
		return isset( $all['popup'] ) && is_array( $all['popup'] ) ? $all['popup'] : array();
	}

	/**
	 * Should the pop-up be rendered on this request?
	 * - Never in admin / feeds / REST.
	 * - In "preview" mode (?fdtf_popup=1) it always renders (for testing).
	 * - Otherwise only when enabled in settings.
	 * The cookie / delay gating is handled client-side so it stays cache-safe.
	 */
	private function should_render() {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return false;
		}
		if ( isset( $_GET['fdtf_popup'] ) ) {
			return true; // preview
		}
		$cfg = $this->config();
		if ( empty( $cfg['enabled'] ) ) {
			return false;
		}
		// Don't interrupt the cart / checkout / account flow.
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Enqueue the pop-up assets (on wp_enqueue_scripts, so they print reliably).
	 */
	public function enqueue() {
		if ( ! $this->should_render() ) {
			return;
		}
		$cfg     = $this->config();
		$preview = isset( $_GET['fdtf_popup'] ) ? 1 : 0;

		wp_enqueue_style( 'fdtf-popup', FDTF_URL . 'assets/popup.css', array(), FDTF_VERSION );
		wp_enqueue_script( 'fdtf-popup', FDTF_URL . 'assets/popup.js', array(), FDTF_VERSION, true );
		wp_localize_script( 'fdtf-popup', 'FDTF_POPUP', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'fdtf_popup' ),
			'delay'      => max( 0, (int) ( $cfg['delay'] ?? 6 ) ),
			'cookieDays' => max( 1, (int) ( $cfg['cookie_days'] ?? 30 ) ),
			'preview'    => $preview,
			'success'    => (string) ( $cfg['success'] ?? 'Obrigado! Verifique o seu email.' ),
		) );
	}

	/**
	 * Output the pop-up markup in the footer.
	 */
	public function render() {
		if ( ! $this->should_render() ) {
			return;
		}
		$cfg     = $this->config();
		$preview = isset( $_GET['fdtf_popup'] ) ? 1 : 0;

		$img      = esc_url( $cfg['image'] ?? '' );
		$title    = esc_html( $cfg['title'] ?? '' );
		$subtitle = esc_html( $cfg['subtitle'] ?? '' );
		$ph       = esc_attr( $cfg['placeholder'] ?? 'O seu email' );
		$button   = esc_html( $cfg['button'] ?? 'Subscrever' );
		$dismiss  = esc_html( $cfg['dismiss'] ?? 'Não, obrigado' );
		$consent  = esc_html( $cfg['consent'] ?? '' );
		?>
<div id="fdtfPopup" class="fdtf-pop" role="dialog" aria-modal="true" aria-labelledby="fdtfPopTitle" aria-hidden="true" data-preview="<?php echo (int) $preview; ?>">
	<div class="fdtf-pop-backdrop" data-close="1"></div>
	<div class="fdtf-pop-card">
		<button type="button" class="fdtf-pop-x" data-close="1" aria-label="Fechar">&times;</button>
		<?php if ( $img ) : ?>
		<div class="fdtf-pop-media"><img src="<?php echo $img; ?>" alt="Fábrica DTF — 10% de desconto na 1ª encomenda" loading="lazy"></div>
		<?php endif; ?>
		<div class="fdtf-pop-body">
			<h2 id="fdtfPopTitle" class="fdtf-pop-title"><?php echo $title; ?></h2>
			<p class="fdtf-pop-sub"><?php echo $subtitle; ?></p>
			<form class="fdtf-pop-form" novalidate>
				<input type="email" class="fdtf-pop-email" name="email" placeholder="<?php echo $ph; ?>" autocomplete="email" required>
				<button type="submit" class="fdtf-pop-btn"><?php echo $button; ?></button>
			</form>
			<div class="fdtf-pop-msg" aria-live="polite"></div>
			<button type="button" class="fdtf-pop-no" data-close="1"><?php echo $dismiss; ?></button>
			<?php if ( $consent ) : ?><p class="fdtf-pop-consent"><?php echo $consent; ?></p><?php endif; ?>
		</div>
	</div>
</div>
		<?php
	}

	/** Serve a fresh nonce (uncached admin-ajax) so cached pages can still submit. */
	public function ajax_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'fdtf_popup' ) ) );
	}

	/**
	 * Handle a subscription: validate, subscribe to Mailchimp (best effort),
	 * create/reuse a unique coupon, email it, and return the code.
	 */
	public function ajax_subscribe() {
		if ( ! check_ajax_referer( 'fdtf_popup', 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'bad_nonce', 'message' => 'Sessão expirada. Atualize a página e tente novamente.' ) );
		}
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'code' => 'bad_email', 'message' => 'Por favor introduza um email válido.' ) );
		}

		$cfg      = $this->config();
		$discount = (int) ( $cfg['discount'] ?? 10 );
		$days     = max( 1, (int) ( $cfg['coupon_days'] ?? 30 ) );
		$prefix   = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) ( $cfg['coupon_prefix'] ?? 'BEMVINDO' ) ) );

		// Deterministic, unique-per-email code so re-subscribing resends the same one.
		$code = $prefix . '-' . strtoupper( substr( hash_hmac( 'sha1', strtolower( $email ), wp_salt() ), 0, 6 ) );

		$coupon_id = $this->ensure_coupon( $code, $discount, $days );
		if ( ! $coupon_id ) {
			wp_send_json_error( array( 'code' => 'coupon_fail', 'message' => 'Não foi possível gerar o código. Tente novamente mais tarde.' ) );
		}

		$this->record_lead( $email );
		$this->subscribe_mailchimp( $email );
		$this->send_coupon_email( $email, $code, $discount, $days );

		wp_send_json_success( array(
			'code'    => $code,
			'message' => (string) ( $cfg['success'] ?? 'Obrigado!' ),
			'cookie'  => self::COOKIE_COUPON,
		) );
	}

	/**
	 * Create (or fetch) a WooCommerce coupon: percent discount, single use,
	 * individual use, expiring in $days. Reused if it already exists.
	 *
	 * @return int Coupon post ID or 0 on failure.
	 */
	private function ensure_coupon( $code, $percent, $days ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return 0;
		}
		$existing = wc_get_coupon_id_by_code( $code );
		if ( $existing ) {
			return (int) $existing;
		}
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $percent );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_exclude_sale_items( false );
		$coupon->set_date_expires( time() + $days * DAY_IN_SECONDS );
		$coupon->set_description( 'Cupão de boas-vindas gerado pelo pop-up de subscrição.' );
		$id = $coupon->save();
		return $id ? (int) $id : 0;
	}

	/** Store the lead email locally (deduped) so the shop always has the list. */
	private function record_lead( $email ) {
		$leads = get_option( self::LEADS_OPTION, array() );
		if ( ! is_array( $leads ) ) {
			$leads = array();
		}
		$key = strtolower( $email );
		if ( ! isset( $leads[ $key ] ) ) {
			$leads[ $key ] = current_time( 'mysql' );
			// Keep the option from growing without bound.
			if ( count( $leads ) > 5000 ) {
				$leads = array_slice( $leads, -5000, null, true );
			}
			update_option( self::LEADS_OPTION, $leads, false );
		}
	}

	/** Best-effort subscribe to Mailchimp via the MC4WP plugin, if configured. */
	private function subscribe_mailchimp( $email ) {
		try {
			if ( ! function_exists( 'mc4wp' ) ) {
				return;
			}
			$mc  = mc4wp();
			$api = method_exists( $mc, 'get_api_v3' ) ? $mc->get_api_v3() : null;
			if ( ! $api ) {
				return;
			}
			// Use the plugin's default configured list, if any.
			$lists = function_exists( 'mc4wp_get_settings' ) ? mc4wp_get_settings() : array();
			$list_id = '';
			if ( ! empty( $lists['lists'] ) && is_array( $lists['lists'] ) ) {
				$list_id = (string) reset( $lists['lists'] );
			}
			if ( ! $list_id ) {
				return;
			}
			$api->add_list_member( $list_id, array(
				'email_address' => $email,
				'status'        => 'subscribed',
			) );
		} catch ( \Throwable $e ) {
			// Silent — the coupon + email are the core deliverable.
		}
	}

	/** Email the coupon code to the subscriber (uses the site's mailer / SMTP). */
	private function send_coupon_email( $email, $code, $percent, $days ) {
		$shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$brand = get_bloginfo( 'name' );
		$valid = date_i18n( 'd/m/Y', time() + $days * DAY_IN_SECONDS );
		$subject = sprintf( 'O seu código de %d%% de desconto — %s', $percent, $brand );

		$msg  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#1a1a1a">';
		$msg .= '<h2 style="color:#0b1a5b;margin:0 0 6px">Bem-vindo à Fábrica DTF!</h2>';
		$msg .= '<p>Obrigado por subscrever. Aqui está o seu código de <b>' . (int) $percent . '% de desconto</b> na sua primeira encomenda:</p>';
		$msg .= '<p style="text-align:center;margin:22px 0">'
			. '<span style="display:inline-block;border:2px dashed #0b1a5b;border-radius:10px;padding:14px 26px;font-size:24px;font-weight:800;letter-spacing:2px;color:#0b1a5b">' . esc_html( $code ) . '</span></p>';
		$msg .= '<p>Use o código no checkout — ou ele será aplicado automaticamente quando voltar à loja neste dispositivo.</p>';
		$msg .= '<p style="color:#555;font-size:14px">Válido até <b>' . esc_html( $valid ) . '</b> · uso único.</p>';
		$msg .= '<p style="margin-top:22px"><a href="' . esc_url( $shop ) . '" style="background:#0b1a5b;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:700;display:inline-block">Ir para a loja</a></p>';
		$msg .= '<p style="color:#888;font-size:12px;margin-top:26px">' . esc_html( $brand ) . ' · A tua imaginação, a nossa impressão.</p>';
		$msg .= '</div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $email, $subject, $msg, $headers );
	}

	/**
	 * Apply the welcome coupon automatically on cart / checkout when the visitor
	 * has a stored code cookie and it isn't already applied.
	 */
	public function auto_apply_coupon() {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}
		if ( ! ( is_cart() || is_checkout() ) ) {
			return;
		}
		if ( empty( $_COOKIE[ self::COOKIE_COUPON ] ) ) {
			return;
		}
		$code = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_COUPON ] ) );
		if ( ! $code || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		if ( WC()->cart->has_discount( $code ) ) {
			return;
		}
		// Only apply if the coupon still exists and is valid.
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) || ! wc_get_coupon_id_by_code( $code ) ) {
			return;
		}
		WC()->cart->apply_coupon( $code );
	}
}
