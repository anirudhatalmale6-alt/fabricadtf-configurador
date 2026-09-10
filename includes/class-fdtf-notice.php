<?php
/**
 * Aviso de encerramento / mensagem temporária.
 *
 * Mostra uma barra de aviso no topo de todas as páginas (e uma caixa no carrinho
 * e no checkout) durante um período definido. As datas e os textos são geridos
 * no painel, e o aviso liga-se e desliga-se sozinho — não é preciso lembrar-se
 * de o remover no fim.
 *
 * Guarda a configuração numa opção própria (fdtf_notice) para não depender do
 * array de definições do configurador.
 *
 * @package FabricaDTF_Configurador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDTF_Notice {

	const OPTION = 'fdtf_notice';

	public function __construct() {
		// The theme calls wp_body_open() right after <body>, so the bar renders
		// in-flow at the very top — no fixed positioning, no overlap with the header.
		add_action( 'wp_body_open', array( $this, 'render_bar' ) );

		// Reinforce it where it matters most: cart and checkout.
		add_action( 'woocommerce_before_cart', array( $this, 'render_box' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_box' ), 5 );

		// Admin.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_fdtf_notice_save', array( $this, 'admin_save' ) );
	}

	public static function defaults() {
		return array(
			'enabled'      => 0,
			'show_from'    => '',   // Y-m-d — when the warning starts appearing.
			'closed_from'  => '',   // Y-m-d — first closed day.
			'closed_until' => '',   // Y-m-d — last closed day (bar hides the day after).
			'text_before'  => '',
			'text_during'  => '',
			'bg'           => '#0b1a5b',
			'fg'           => '#ffffff',
			'dismissible'  => 0,
			'on_cart'      => 1,
		);
	}

	public static function get() {
		$stored = get_option( self::OPTION );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/** Today in the site's own timezone (not the server's). */
	private function today() {
		return current_time( 'Y-m-d' );
	}

	private function valid_date( $d ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d );
	}

	/**
	 * The message to show right now, or '' when the notice shouldn't appear.
	 *
	 * Preview: ?fdtf_notice=1 forces the "before" text, ?fdtf_notice=during
	 * forces the closed-period text, ignoring the dates.
	 */
	public function current_message() {
		$cfg = self::get();

		$preview = isset( $_GET['fdtf_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['fdtf_notice'] ) ) : '';
		if ( $preview ) {
			return 'during' === $preview ? (string) $cfg['text_during'] : (string) $cfg['text_before'];
		}

		if ( empty( $cfg['enabled'] ) ) {
			return '';
		}
		if ( ! $this->valid_date( $cfg['closed_from'] ) || ! $this->valid_date( $cfg['closed_until'] ) ) {
			return '';
		}

		$today = $this->today();

		// Not started announcing yet.
		if ( $this->valid_date( $cfg['show_from'] ) && $today < $cfg['show_from'] ) {
			return '';
		}
		// Reopened — the notice switches itself off.
		if ( $today > $cfg['closed_until'] ) {
			return '';
		}

		$text = ( $today < $cfg['closed_from'] ) ? $cfg['text_before'] : $cfg['text_during'];
		return (string) $text;
	}

	/** Print the CSS at most once per page (the bar and the cart box share it). */
	private function styles() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;

		$cfg = self::get();
		$bg  = sanitize_hex_color( $cfg['bg'] ) ? $cfg['bg'] : '#0b1a5b';
		$fg  = sanitize_hex_color( $cfg['fg'] ) ? $cfg['fg'] : '#ffffff';
		return '<style id="fdtf-notice-css">'
			. '.fdtf-notice{background:' . $bg . ';color:' . $fg . ';font-family:inherit;font-size:15px;line-height:1.45;padding:12px 18px;text-align:center;position:relative;z-index:100}'
			. '.fdtf-notice-in{max-width:1100px;margin:0 auto;display:block}'
			. '.fdtf-notice b{font-weight:800}'
			. '.fdtf-notice-x{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:0;color:inherit;font-size:22px;line-height:1;cursor:pointer;opacity:.75;padding:0 6px}'
			. '.fdtf-notice-x:hover{opacity:1}'
			. '.fdtf-notice-box{background:' . $bg . ';color:' . $fg . ';border-radius:10px;padding:16px 20px;margin:0 0 22px;font-size:15px;line-height:1.5}'
			. '@media(max-width:640px){.fdtf-notice{font-size:13.5px;padding:11px 30px 11px 14px}.fdtf-notice-box{font-size:13.5px}}'
			. '</style>';
	}

	/** The site-wide bar, printed immediately after <body>. */
	public function render_bar() {
		if ( is_admin() ) {
			return;
		}
		$msg = $this->current_message();
		if ( '' === $msg ) {
			return;
		}
		$cfg = self::get();

		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="fdtf-notice" role="status">';
		echo '<span class="fdtf-notice-in">' . wp_kses_post( $msg ) . '</span>';
		if ( ! empty( $cfg['dismissible'] ) ) {
			echo '<button type="button" class="fdtf-notice-x" aria-label="Fechar aviso" onclick="this.parentNode.style.display=\'none\'">&times;</button>';
		}
		echo '</div>';
	}

	/** The reinforced box on cart / checkout. */
	public function render_box() {
		$cfg = self::get();
		if ( empty( $cfg['on_cart'] ) ) {
			return;
		}
		$msg = $this->current_message();
		if ( '' === $msg ) {
			return;
		}
		// styles() is a no-op if the bar already printed the CSS on this page.
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="fdtf-notice-box" role="status">' . wp_kses_post( $msg ) . '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Aviso no site',
			'Aviso no site',
			'manage_woocommerce',
			'fdtf-aviso',
			array( $this, 'admin_page' )
		);
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissões.' );
		}
		$cfg    = self::get();
		$saved  = isset( $_GET['fdtf_saved'] );
		$today  = $this->today();
		$active = '' !== $this->current_message_ignoring_preview();
		?>
		<div class="wrap">
			<h1>Aviso no site</h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Aviso guardado.</p></div>
			<?php endif; ?>

			<p>
				Mostra uma barra de aviso no topo de todas as páginas durante o período indicado,
				e repete o aviso no carrinho e no checkout. <strong>Liga-se e desliga-se sozinho nas datas
				que definir</strong> — não precisa de se lembrar de o remover.
			</p>
			<p>
				Hoje é <strong><?php echo esc_html( $today ); ?></strong> —
				estado atual:
				<?php if ( $active ) : ?>
					<strong style="color:#046b1f">o aviso está a aparecer no site</strong>.
				<?php else : ?>
					<strong style="color:#777">o aviso não está a aparecer</strong>.
				<?php endif; ?>
			</p>
			<p>
				Pré-visualizar sem publicar:
				<a href="<?php echo esc_url( home_url( '/?fdtf_notice=1' ) ); ?>" target="_blank">texto de aviso prévio</a> ·
				<a href="<?php echo esc_url( home_url( '/?fdtf_notice=during' ) ); ?>" target="_blank">texto do período encerrado</a>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fdtf_notice_save">
				<?php wp_nonce_field( 'fdtf_notice_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Ativo</th>
						<td>
							<label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>> Mostrar o aviso (dentro das datas abaixo)</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="show_from">Começar a avisar em</label></th>
						<td>
							<input type="date" id="show_from" name="show_from" value="<?php echo esc_attr( $cfg['show_from'] ); ?>">
							<p class="description">A partir desta data aparece o aviso prévio. Deixe vazio para começar já.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="closed_from">Primeiro dia encerrado</label></th>
						<td><input type="date" id="closed_from" name="closed_from" value="<?php echo esc_attr( $cfg['closed_from'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="closed_until">Último dia encerrado</label></th>
						<td>
							<input type="date" id="closed_until" name="closed_until" value="<?php echo esc_attr( $cfg['closed_until'] ); ?>">
							<p class="description">O aviso desaparece sozinho no dia seguinte a esta data.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="text_before">Texto antes de encerrar</label></th>
						<td>
							<textarea id="text_before" name="text_before" rows="4" class="large-text"><?php echo esc_textarea( $cfg['text_before'] ); ?></textarea>
							<p class="description">Mostrado desde a data de início até ao dia anterior ao encerramento.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="text_during">Texto durante o encerramento</label></th>
						<td>
							<textarea id="text_during" name="text_during" rows="4" class="large-text"><?php echo esc_textarea( $cfg['text_during'] ); ?></textarea>
							<p class="description">Substitui o texto acima automaticamente no primeiro dia encerrado.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Cores</th>
						<td>
							Fundo <input type="color" name="bg" value="<?php echo esc_attr( $cfg['bg'] ); ?>">
							&nbsp; Texto <input type="color" name="fg" value="<?php echo esc_attr( $cfg['fg'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">Opções</th>
						<td>
							<label><input type="checkbox" name="on_cart" value="1" <?php checked( ! empty( $cfg['on_cart'] ) ); ?>> Repetir o aviso no carrinho e no checkout</label><br>
							<label><input type="checkbox" name="dismissible" value="1" <?php checked( ! empty( $cfg['dismissible'] ) ); ?>> Permitir que o cliente feche a barra</label>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Guardar aviso' ); ?>
			</form>
		</div>
		<?php
	}

	/** Same as current_message() but ignoring the ?fdtf_notice preview parameter. */
	private function current_message_ignoring_preview() {
		$saved = isset( $_GET['fdtf_notice'] ) ? $_GET['fdtf_notice'] : null;
		unset( $_GET['fdtf_notice'] );
		$msg = $this->current_message();
		if ( null !== $saved ) {
			$_GET['fdtf_notice'] = $saved;
		}
		return $msg;
	}

	public function admin_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissões.' );
		}
		check_admin_referer( 'fdtf_notice_save' );

		$in  = wp_unslash( $_POST );
		$out = self::defaults();

		$out['enabled']     = empty( $in['enabled'] ) ? 0 : 1;
		$out['dismissible'] = empty( $in['dismissible'] ) ? 0 : 1;
		$out['on_cart']     = empty( $in['on_cart'] ) ? 0 : 1;

		foreach ( array( 'show_from', 'closed_from', 'closed_until' ) as $k ) {
			$v = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
			$out[ $k ] = $this->valid_date( $v ) ? $v : '';
		}
		foreach ( array( 'text_before', 'text_during' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? wp_kses_post( trim( $in[ $k ] ) ) : '';
		}
		foreach ( array( 'bg', 'fg' ) as $k ) {
			$v = isset( $in[ $k ] ) ? sanitize_hex_color( $in[ $k ] ) : '';
			$out[ $k ] = $v ? $v : self::defaults()[ $k ];
		}

		update_option( self::OPTION, $out, false );

		// The bar is part of the cached HTML, so the cache must go.
		do_action( 'litespeed_purge_all' );

		wp_safe_redirect( admin_url( 'admin.php?page=fdtf-aviso&fdtf_saved=1' ) );
		exit;
	}
}
