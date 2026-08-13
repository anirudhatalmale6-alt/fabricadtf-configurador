<?php
/**
 * Pop-up de subscrição com desconto automático.
 *
 * Mostra um pop-up (imagem promocional + captura de email) aos visitantes. Ao
 * subscrever, o cliente é inscrito na newsletter (Mailchimp, se ligado), é
 * gerado um cupão WooCommerce único de 10% (uso único), o código é enviado por
 * email (via o SMTP configurado) e é aplicado automaticamente no checkout.
 *
 * O envio é registado (data, código, sucesso/erro) para que a loja possa ver no
 * painel quem recebeu e reenviar o código quando necessário.
 *
 * @package FabricaDTF_Configurador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDTF_Popup {

	const COOKIE_COUPON = 'fdtf_welcome_coupon';
	const LEADS_OPTION  = 'fdtf_popup_leads';
	const LOG_OPTION    = 'fdtf_popup_log';
	const UNSUB_OPTION  = 'fdtf_popup_unsub';
	const LOG_MAX       = 500;

	public function __construct() {
		// Enqueue assets early (wp_footer is too late for enqueuing), render markup in the footer.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 20 );

		// AJAX: subscribe + fresh nonce (works for guests too).
		add_action( 'wp_ajax_fdtf_popup', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_fdtf_popup', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_fdtf_popup_nonce', array( $this, 'ajax_nonce' ) );
		add_action( 'wp_ajax_nopriv_fdtf_popup_nonce', array( $this, 'ajax_nonce' ) );

		// Auto-apply the welcome coupon on cart / checkout, and handle unsubscribes.
		add_action( 'template_redirect', array( $this, 'auto_apply_coupon' ) );
		add_action( 'template_redirect', array( $this, 'maybe_unsubscribe' ), 1 );

		// Admin: subscribers screen + actions.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_fdtf_popup_resend', array( $this, 'admin_resend' ) );
		add_action( 'admin_post_fdtf_popup_export', array( $this, 'admin_export' ) );
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

		$code      = $this->code_for( $email );
		$coupon_id = $this->ensure_coupon( $code, $discount, $days );
		if ( ! $coupon_id ) {
			wp_send_json_error( array( 'code' => 'coupon_fail', 'message' => 'Não foi possível gerar o código. Tente novamente mais tarde.' ) );
		}

		$this->record_lead( $email );
		$this->subscribe_mailchimp( $email );
		$result = $this->send_coupon_email( $email, $code, $discount, $days );
		$this->log_send( $email, $code, $result['sent'], $result['error'] );

		wp_send_json_success( array(
			'code'    => $code,
			'sent'    => (bool) $result['sent'],
			'message' => (string) ( $cfg['success'] ?? 'Obrigado!' ),
			'cookie'  => self::COOKIE_COUPON,
		) );
	}

	/** Deterministic, unique-per-email coupon code (re-subscribing returns the same one). */
	private function code_for( $email ) {
		$cfg    = $this->config();
		$prefix = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) ( $cfg['coupon_prefix'] ?? 'BEMVINDO' ) ) );
		return $prefix . '-' . strtoupper( substr( hash_hmac( 'sha1', strtolower( $email ), wp_salt() ), 0, 6 ) );
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

	/** Record the outcome of an attempted coupon email, newest first. */
	private function log_send( $email, $code, $sent, $error ) {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, array(
			't'     => current_time( 'mysql' ),
			'email' => strtolower( $email ),
			'code'  => $code,
			'sent'  => $sent ? 1 : 0,
			'err'   => $error ? substr( (string) $error, 0, 300 ) : '',
		) );
		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, 0, self::LOG_MAX );
		}
		update_option( self::LOG_OPTION, $log, false );
	}

	/** Latest log entry per email address. */
	private function log_by_email() {
		$log = get_option( self::LOG_OPTION, array() );
		$out = array();
		if ( is_array( $log ) ) {
			foreach ( $log as $row ) {
				$key = isset( $row['email'] ) ? strtolower( $row['email'] ) : '';
				if ( $key && ! isset( $out[ $key ] ) ) {
					$out[ $key ] = $row;
				}
			}
		}
		return $out;
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

	/** One-click unsubscribe link for a given address. */
	private function unsub_url( $email ) {
		return add_query_arg( array(
			'fdtf_unsub' => rawurlencode( strtolower( $email ) ),
			'k'          => substr( hash_hmac( 'sha1', 'unsub|' . strtolower( $email ), wp_salt() ), 0, 16 ),
		), home_url( '/' ) );
	}

	/**
	 * Email the coupon code to the subscriber.
	 *
	 * Deliverability matters here: a large share of subscribers are on Gmail /
	 * Outlook, which are strict with promotional mail. So we send a real
	 * multipart message (HTML + plain text), a recognisable sender name, a
	 * Reply-To that a human reads, and List-Unsubscribe headers.
	 *
	 * @return array{sent:bool,error:string}
	 */
	private function send_coupon_email( $email, $code, $percent, $days ) {
		$shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$brand = get_bloginfo( 'name' );
		$valid = date_i18n( 'd/m/Y', time() + $days * DAY_IN_SECONDS );
		$reply = $this->reply_to_address();
		$unsub = $this->unsub_url( $email );

		$subject = sprintf( 'O seu código de %d%% de desconto — %s', $percent, $brand );

		$msg  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#1a1a1a">';
		$msg .= '<h2 style="color:#0b1a5b;margin:0 0 6px">Bem-vindo à Fábrica DTF!</h2>';
		$msg .= '<p>Obrigado por subscrever. Aqui está o seu código de <b>' . (int) $percent . '% de desconto</b> na sua primeira encomenda:</p>';
		$msg .= '<p style="text-align:center;margin:22px 0">'
			. '<span style="display:inline-block;border:2px dashed #0b1a5b;border-radius:10px;padding:14px 26px;font-size:24px;font-weight:800;letter-spacing:2px;color:#0b1a5b">' . esc_html( $code ) . '</span></p>';
		$msg .= '<p>Use o código no checkout — ou ele será aplicado automaticamente quando voltar à loja neste dispositivo.</p>';
		$msg .= '<p style="color:#555;font-size:14px">Válido até <b>' . esc_html( $valid ) . '</b> · uso único.</p>';
		$msg .= '<p style="margin-top:22px"><a href="' . esc_url( $shop ) . '" style="background:#0b1a5b;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:700;display:inline-block">Ir para a loja</a></p>';
		$msg .= '<p style="color:#888;font-size:12px;margin-top:26px">' . esc_html( $brand ) . ' · A tua imaginação, a nossa impressão.<br>';
		$msg .= 'Recebeu este email porque subscreveu em <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#888">fabricadtf.pt</a>. ';
		$msg .= '<a href="' . esc_url( $unsub ) . '" style="color:#888">Cancelar subscrição</a>.</p>';
		$msg .= '</div>';

		$plain  = "Bem-vindo a Fabrica DTF!\n\n";
		$plain .= "Obrigado por subscrever. O seu codigo de {$percent}% de desconto na primeira encomenda:\n\n";
		$plain .= "    {$code}\n\n";
		$plain .= "Use o codigo no checkout. Valido ate {$valid}, uso unico.\n\n";
		$plain .= "Loja: {$shop}\n\n";
		$plain .= "Recebeu este email porque subscreveu em fabricadtf.pt.\n";
		$plain .= "Cancelar subscricao: {$unsub}\n";

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'Reply-To: ' . $reply,
			'List-Unsubscribe: <' . $unsub . '>, <mailto:' . $reply . '?subject=Unsubscribe>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
		);

		return $this->mail( $email, $subject, $msg, $plain, $headers );
	}

	/** Address a human actually reads (shop admin email by default). */
	private function reply_to_address() {
		$addr = get_option( 'woocommerce_email_from_address' );
		if ( ! $addr || ! is_email( $addr ) || 0 === stripos( $addr, 'no-reply' ) ) {
			$addr = get_option( 'admin_email' );
		}
		return $addr;
	}

	/**
	 * wp_mail wrapper: forces a recognisable From name, attaches the plain-text
	 * alternative, and captures any failure so it can be logged and shown.
	 *
	 * @return array{sent:bool,error:string}
	 */
	private function mail( $to, $subject, $html, $plain, $headers ) {
		$error = '';

		$capture = function ( $wp_error ) use ( &$error ) {
			if ( is_wp_error( $wp_error ) ) {
				$error = $wp_error->get_error_message();
			}
		};
		$from_name = function () {
			$name = get_bloginfo( 'name' );
			return $name ? $name : 'Fábrica DTF';
		};
		$alt_body = function ( $phpmailer ) use ( $plain ) {
			$phpmailer->AltBody = $plain;
		};

		add_action( 'wp_mail_failed', $capture );
		add_filter( 'wp_mail_from_name', $from_name, 99 );
		add_action( 'phpmailer_init', $alt_body, 999 );

		$sent = wp_mail( $to, $subject, $html, $headers );

		remove_action( 'phpmailer_init', $alt_body, 999 );
		remove_filter( 'wp_mail_from_name', $from_name, 99 );
		remove_action( 'wp_mail_failed', $capture );

		if ( ! $sent && ! $error ) {
			$error = 'wp_mail devolveu false (sem detalhe).';
		}
		return array( 'sent' => (bool) $sent, 'error' => $error );
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

	/** Handle the List-Unsubscribe / footer unsubscribe link. */
	public function maybe_unsubscribe() {
		if ( empty( $_GET['fdtf_unsub'] ) || empty( $_GET['k'] ) ) {
			return;
		}
		$email = sanitize_email( wp_unslash( $_GET['fdtf_unsub'] ) );
		$key   = sanitize_text_field( wp_unslash( $_GET['k'] ) );
		$want  = substr( hash_hmac( 'sha1', 'unsub|' . strtolower( $email ), wp_salt() ), 0, 16 );
		if ( ! $email || ! hash_equals( $want, $key ) ) {
			return;
		}

		$unsub = get_option( self::UNSUB_OPTION, array() );
		if ( ! is_array( $unsub ) ) {
			$unsub = array();
		}
		$unsub[ strtolower( $email ) ] = current_time( 'mysql' );
		update_option( self::UNSUB_OPTION, $unsub, false );

		$leads = get_option( self::LEADS_OPTION, array() );
		if ( is_array( $leads ) && isset( $leads[ strtolower( $email ) ] ) ) {
			unset( $leads[ strtolower( $email ) ] );
			update_option( self::LEADS_OPTION, $leads, false );
		}

		wp_die(
			'<h2>Subscrição cancelada</h2><p>O endereço <b>' . esc_html( $email ) . '</b> deixou de receber emails da Fábrica DTF.</p>'
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">Voltar à loja</a></p>',
			'Subscrição cancelada',
			array( 'response' => 200 )
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin: subscribers screen
	 * ------------------------------------------------------------------ */

	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Subscritores do pop-up',
			'Subscritores pop-up',
			'manage_woocommerce',
			'fdtf-subscritores',
			array( $this, 'admin_page' )
		);
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissões.' );
		}
		$leads = get_option( self::LEADS_OPTION, array() );
		if ( ! is_array( $leads ) ) {
			$leads = array();
		}
		$leads = array_reverse( $leads, true );
		$log   = $this->log_by_email();
		$notice = isset( $_GET['fdtf_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['fdtf_msg'] ) ) : '';
		?>
		<div class="wrap">
			<h1>Subscritores do pop-up</h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<p>
				Total de subscritores: <strong><?php echo count( $leads ); ?></strong>.
				A coluna “Email” mostra se o envio do código foi aceite pelo servidor de correio.
				Se um cliente disser que não recebeu, use <em>Reenviar</em> e peça-lhe para verificar também a pasta de Spam/Publicidade.
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fdtf_popup_export' ), 'fdtf_popup_export' ) ); ?>">Exportar CSV</a>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Data</th>
						<th>Email</th>
						<th>Código</th>
						<th>Usado</th>
						<th>Envio</th>
						<th>Ação</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $leads ) : ?>
					<tr><td colspan="6">Ainda não há subscritores.</td></tr>
				<?php endif; ?>
				<?php foreach ( $leads as $email => $when ) :
					$code = $this->code_for( $email );
					$cid  = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $code ) : 0;
					$used = 0;
					if ( $cid ) {
						$c    = new WC_Coupon( $cid );
						$used = (int) $c->get_usage_count();
					}
					$row = isset( $log[ strtolower( $email ) ] ) ? $log[ strtolower( $email ) ] : null;
					?>
					<tr>
						<td><?php echo esc_html( is_string( $when ) ? $when : '' ); ?></td>
						<td><?php echo esc_html( $email ); ?></td>
						<td><code><?php echo esc_html( $code ); ?></code></td>
						<td><?php echo $used ? '<span style="color:#046b1f">sim</span>' : '—'; ?></td>
						<td>
							<?php
							if ( ! $row ) {
								echo '<span style="color:#777">sem registo</span>';
							} elseif ( ! empty( $row['sent'] ) ) {
								echo '<span style="color:#046b1f">enviado</span> <small>' . esc_html( $row['t'] ) . '</small>';
							} else {
								echo '<span style="color:#b32d2e">falhou</span> <small>' . esc_html( $row['err'] ) . '</small>';
							}
							?>
						</td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fdtf_popup_resend&email=' . rawurlencode( $email ) ), 'fdtf_popup_resend' ) ); ?>">Reenviar</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function admin_resend() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissões.' );
		}
		check_admin_referer( 'fdtf_popup_resend' );
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=fdtf-subscritores&fdtf_msg=' . rawurlencode( 'Email inválido.' ) ) );
			exit;
		}
		$cfg      = $this->config();
		$discount = (int) ( $cfg['discount'] ?? 10 );
		$days     = max( 1, (int) ( $cfg['coupon_days'] ?? 30 ) );
		$code     = $this->code_for( $email );
		$this->ensure_coupon( $code, $discount, $days );
		$result = $this->send_coupon_email( $email, $code, $discount, $days );
		$this->log_send( $email, $code, $result['sent'], $result['error'] );

		$msg = $result['sent']
			? sprintf( 'Código %s reenviado para %s.', $code, $email )
			: sprintf( 'Falha ao reenviar para %s: %s', $email, $result['error'] );
		wp_safe_redirect( admin_url( 'admin.php?page=fdtf-subscritores&fdtf_msg=' . rawurlencode( $msg ) ) );
		exit;
	}

	public function admin_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissões.' );
		}
		check_admin_referer( 'fdtf_popup_export' );
		$leads = get_option( self::LEADS_OPTION, array() );
		if ( ! is_array( $leads ) ) {
			$leads = array();
		}
		$log = $this->log_by_email();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=subscritores-fabricadtf.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM so Excel reads the accents.
		fputcsv( $out, array( 'Data', 'Email', 'Codigo', 'Usado', 'Envio', 'Erro' ) );
		foreach ( array_reverse( $leads, true ) as $email => $when ) {
			$code = $this->code_for( $email );
			$cid  = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $code ) : 0;
			$used = 0;
			if ( $cid ) {
				$c    = new WC_Coupon( $cid );
				$used = (int) $c->get_usage_count();
			}
			$row = isset( $log[ strtolower( $email ) ] ) ? $log[ strtolower( $email ) ] : null;
			fputcsv( $out, array(
				is_string( $when ) ? $when : '',
				$email,
				$code,
				$used ? 'sim' : 'nao',
				$row ? ( ! empty( $row['sent'] ) ? 'enviado' : 'falhou' ) : 'sem registo',
				$row && ! empty( $row['err'] ) ? $row['err'] : '',
			) );
		}
		fclose( $out );
		exit;
	}
}
