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
		add_shortcode( 'fabricadtf_dtf', array( $this, 'shortcode_dtf' ) );
		add_shortcode( 'fabricadtf_home', array( $this, 'shortcode_home' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		// Countdown announcement bar at the very top of the homepage.
		add_action( 'wp_body_open', array( $this, 'render_top_bar' ) );
		// Send the old "DTF a Metro" product page to the new redesigned page.
		add_action( 'template_redirect', array( $this, 'maybe_redirect_old_dtf' ) );
		// Load a translations file if present.
		add_action( 'init', function () {
			load_plugin_textdomain( 'fabricadtf-configurador', false, dirname( plugin_basename( FDTF_FILE ) ) . '/languages' );
		} );
	}

	/**
	 * Print the dark-blue countdown announcement bar at the very top of the page
	 * (above the theme header) on the homepage, and hide the theme's own static
	 * free-shipping strip so the message is not duplicated. Self-contained inline
	 * style + script so it renders correctly even before the enqueued assets load.
	 */
	public function render_top_bar() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! has_shortcode( $post->post_content, 'fabricadtf_home' ) ) {
			return;
		}
		?>
<style id="fh-topbar-css">
.elementor-element-75e2ade{display:none !important}
#fhTopbar{background:#0b1a5b;color:#fff;font-size:14px;font-weight:700;line-height:1.3}
#fhTopbar .fh-tb-in{max-width:1200px;margin:0 auto;padding:9px 18px;display:flex;flex-wrap:wrap;gap:6px 22px;align-items:center;justify-content:center;text-align:center}
#fhTopbar .fh-cd{display:inline-flex;gap:5px;margin-left:6px;vertical-align:middle}
#fhTopbar .fh-cd b{background:rgba(255,255,255,.16);border-radius:6px;padding:3px 7px;font-size:13.5px;font-variant-numeric:tabular-nums;min-width:26px;text-align:center}
@media(max-width:640px){#fhTopbar{font-size:12.5px}#fhTopbar .fh-tb-in{gap:4px 14px}}
</style>
<div id="fhTopbar" role="complementary" aria-label="Aviso de envio">
  <div class="fh-tb-in">
    <div>Envia HOJE se encomendar em: <span class="fh-cd" id="fhCdTop" aria-hidden="true"><b>00</b>:<b>00</b>:<b>00</b></span></div>
    <div>Envio grátis em encomendas acima de 150€</div>
  </div>
</div>
<script>
(function(){var el=document.getElementById("fhCdTop");if(!el)return;var b=el.querySelectorAll("b");function p(n){return(n<10?"0":"")+n;}function t(){var n=new Date(),e=new Date(n.getFullYear(),n.getMonth(),n.getDate(),23,59,59),s=Math.max(0,Math.floor((e-n)/1000));b[0].textContent=p(Math.floor(s/3600));b[1].textContent=p(Math.floor(s%3600/60));b[2].textContent=p(s%60);}t();setInterval(t,1000);})();
</script>
		<?php
	}

	/**
	 * Redirect the legacy "DTF a Metro" WooCommerce product to the new redesigned
	 * page, so every existing link (homepage, mega-menu, SEO, bookmarks) lands on
	 * the new page. The old product stays in the database as a reversible backup —
	 * remove the 'fdtf_dtf_prod_page_id' option (or the old product slug) to undo.
	 */
	public function maybe_redirect_old_dtf() {
		if ( is_admin() ) {
			return;
		}
		$target = intval( get_option( 'fdtf_dtf_prod_page_id' ) );
		if ( ! $target ) {
			return;
		}
		if ( is_singular( 'product' ) ) {
			$obj = get_queried_object();
			if ( $obj && isset( $obj->post_name, $obj->ID )
				&& 'dtf-a-metro' === $obj->post_name && (int) $obj->ID !== $target ) {
				wp_safe_redirect( get_permalink( $target ), 302 );
				exit;
			}
		}
	}

	/**
	 * Register (but don't yet enqueue) the front-end assets.
	 */
	public function register_assets() {
		wp_register_style( 'fdtf-configurador', FDTF_URL . 'assets/configurator.css', array(), FDTF_VERSION );
		wp_register_script( 'fdtf-configurador', FDTF_URL . 'assets/configurator.js', array(), FDTF_VERSION, true );
		wp_register_style( 'fdtf-dtf', FDTF_URL . 'assets/dtf.css', array(), FDTF_VERSION );
		wp_register_script( 'fdtf-dtf', FDTF_URL . 'assets/dtf.js', array(), FDTF_VERSION, true );
		wp_register_style( 'fdtf-home', FDTF_URL . 'assets/home.css', array(), FDTF_VERSION );
		wp_register_script( 'fdtf-home', FDTF_URL . 'assets/home.js', array(), FDTF_VERSION, true );
	}

	/**
	 * Build the front-end config for the DTF-a-metro ordering page.
	 *
	 * @return array
	 */
	private function build_dtf_config() {
		$s   = FDTF_Settings::get();
		$dtf = isset( $s['dtf'] ) && is_array( $s['dtf'] ) ? $s['dtf'] : array();

		$tiers = array();
		foreach ( (array) ( isset( $dtf['tiers'] ) ? $dtf['tiers'] : array() ) as $t ) {
			$tiers[] = array(
				'label' => isset( $t['label'] ) ? $t['label'] : '',
				'min'   => intval( isset( $t['min'] ) ? $t['min'] : 1 ),
				'max'   => intval( isset( $t['max'] ) ? $t['max'] : 0 ),
				'price' => floatval( isset( $t['price'] ) ? $t['price'] : 0 ),
			);
		}

		return array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'fdtf_nonce' ),
			'currency'     => isset( $s['currency'] ) ? $s['currency'] : '€',
			'title'        => isset( $dtf['title'] ) ? $dtf['title'] : 'DTF a Metro',
			'badge'        => isset( $dtf['badge'] ) ? $dtf['badge'] : '',
			'rating'       => isset( $dtf['rating'] ) ? floatval( $dtf['rating'] ) : 5,
			'reviews'      => isset( $dtf['reviews'] ) ? intval( $dtf['reviews'] ) : 0,
			'desc'         => isset( $dtf['desc'] ) ? $dtf['desc'] : '',
			'unitLabel'    => isset( $dtf['unit_label'] ) ? $dtf['unit_label'] : '/metro',
			'qtyLabel'     => isset( $dtf['qty_label'] ) ? $dtf['qty_label'] : 'Quantidade (metros lineares)',
			'uploadNote'   => isset( $dtf['upload_note'] ) ? $dtf['upload_note'] : '',
			'notesLabel'   => isset( $dtf['notes_label'] ) ? $dtf['notes_label'] : 'Notas (opcional)',
			'notesPh'      => isset( $dtf['notes_ph'] ) ? $dtf['notes_ph'] : '',
			'minM'         => isset( $dtf['min_m'] ) ? max( 1, intval( $dtf['min_m'] ) ) : 1,
			'accept'       => isset( $dtf['accept'] ) ? $dtf['accept'] : '.png,.jpg,.jpeg,.pdf',
			'acceptLabel'  => isset( $dtf['accept_label'] ) ? $dtf['accept_label'] : 'PNG, JPG, PDF',
			'maxMB'        => isset( $dtf['max_mb'] ) ? intval( $dtf['max_mb'] ) : 40,
			'tiers'        => $tiers,
			'features'     => array_values( (array) ( isset( $dtf['features'] ) ? $dtf['features'] : array() ) ),
			'guidelines'   => array_values( (array) ( isset( $dtf['guidelines'] ) ? $dtf['guidelines'] : array() ) ),
			'detailsHtml'  => isset( $dtf['details_html'] ) ? $dtf['details_html'] : '',
			'shippingHtml' => isset( $dtf['shipping_html'] ) ? $dtf['shipping_html'] : '',
			'images'       => array_values( array_filter( (array) ( isset( $dtf['images'] ) ? $dtf['images'] : array() ) ) ),
		);
	}

	/**
	 * Shortcode handler: [fabricadtf_dtf]
	 */
	public function shortcode_dtf( $atts ) {
		wp_enqueue_style( 'fdtf-dtf' );
		wp_enqueue_script( 'fdtf-dtf' );
		wp_add_inline_script(
			'fdtf-dtf',
			'window.FDTF_DTF_CONFIG = ' . wp_json_encode( $this->build_dtf_config() ) . ';',
			'before'
		);

		// Carries a per-request nonce -> must not be served from a stale full-page cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'FabricaDTF DTF a metro (nonce por pedido)' );
		add_filter( 'litespeed_control_cacheable', '__return_false', 99 );

		return '<div class="fdtf-dtf"></div>';
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
			'minQty'       => isset( $s['min_qty'] ) ? intval( $s['min_qty'] ) : 5,
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
			'logoUrl'      => FDTF_URL . 'assets/brand/logo.png',
			'siteLabel'    => preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) ),
		);
	}

	/**
	 * Shortcode handler: [fabricadtf_configurador]
	 */
	public function shortcode( $atts ) {
		$this->enqueue_needed = true;

		// The configurator carries a per-request security nonce, so this page must
		// not be served from a full-page cache with a stale nonce. Ask page caches
		// (LiteSpeed, WP Super Cache, W3TC…) to skip it.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		// LiteSpeed Cache uses its own control API (ignores DONOTCACHEPAGE / raw headers).
		do_action( 'litespeed_control_set_nocache', 'FabricaDTF configurador (nonce por pedido)' );
		add_filter( 'litespeed_control_cacheable', '__return_false', 99 );

		wp_enqueue_style( 'fdtf-configurador' );
		wp_enqueue_script( 'fdtf-configurador' );
		wp_add_inline_script(
			'fdtf-configurador',
			'window.FDTF_CONFIG = ' . wp_json_encode( $this->build_config() ) . ';',
			'before'
		);

		return '<div class="fdtf-config"></div>';
	}

	/**
	 * Shortcode handler: [fabricadtf_home] — full redesigned homepage.
	 * Server-rendered HTML (SEO-friendly: single H1, section H2s, FAQPage schema).
	 */
	public function shortcode_home( $atts ) {
		wp_enqueue_style( 'fdtf-home' );
		wp_enqueue_script( 'fdtf-home' );
		return $this->build_home_html();
	}

	/**
	 * Build the redesigned homepage markup.
	 *
	 * @return string
	 */
	private function build_home_html() {
		$dtf_page = intval( get_option( 'fdtf_dtf_prod_page_id' ) );
		$dtf_url  = $dtf_page ? get_permalink( $dtf_page ) : home_url( '/dtf-a-metro/' );
		$conf_url = home_url( '/personalizar-t-shirt/' );
		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
		$contact  = home_url( '/contato/' );
		$logo     = FDTF_URL . 'assets/brand/logo.png';
		$roll     = FDTF_URL . 'assets/dtf-images/dtf-roll-1.jpg';
		$img      = FDTF_URL . 'assets/home/';
		$phone    = '+351 937 661 849';
		$editor   = home_url( '/editor/' );
		$blog_pid = intval( get_option( 'page_for_posts' ) );
		$blog_url = $blog_pid ? get_permalink( $blog_pid ) : home_url( '/blog/' );
		$posts    = get_posts( array( 'numberposts' => 4, 'post_status' => 'publish' ) );

		// --- data ---------------------------------------------------------
		$cats = array(
			array( 'TRANSFERÊNCIAS DTF', $roll, '', 'Ver DTF', $dtf_url ),
			array( 'AUTOCOLANTES UV', $img . 'cat-uv.jpg', '', 'Ver Autocolantes', $shop_url ),
			array( 'PACOTES DE DTF', $img . 'cat-pacotes.jpg', '', 'Ver Pacotes', $shop_url ),
			array( 'T-SHIRT PERSONALIZADA', $img . 'cat-tshirt.jpg', '', 'Ver Personalizadas', $conf_url ),
		);
		$banners = array(
			array( $img . 'banner-1.jpg', 'Portes grátis em encomendas superiores a 150€', $shop_url ),
			array( $img . 'banner-2.jpg', 'Dê força à sua marca com impressão DTF premium', $conf_url ),
			array( $img . 'banner-3.jpg', 'Imprima mais, pague menos — DTF a metro', $dtf_url ),
			array( $img . 'banner-4.jpg', 'Cores vibrantes, detalhes nítidos — transferências DTF premium', $dtf_url ),
		);
		$reviews = array(
			array( 'Ana Ferreira', 'há 3 meses', 'A FabricaDTF é rápida e de excelente qualidade. Enviei ao final do dia e no dia seguinte já estava pronto para levantar. Atendimento incrível.' ),
			array( 'Michelle Bastos', 'há 3 meses', 'Quando temos um grande fornecedor, para quê mudar? Os melhores em Lisboa! Qualidade e serviço rápido — sempre entregam. Qualidade INIGUALÁVEL!' ),
			array( 'Miguel Fontes', 'há 12 dias', 'Acabei de receber os meus DTF personalizados, muito grato por vos ter encontrado. As impressões estão lindas, obrigado! Vou voltar a encomendar.' ),
			array( 'Melissa Gouveia', 'há 2 meses', 'Já é o meu sítio favorito para DTF. Melhor preço da cidade e melhor qualidade. Equipa simpática. Recomendo sem hesitar!' ),
		);
		$steps = array(
			array( '<path d="M12 15V3m0 0L8 7m4-4 4 4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>', 'Faça Upload e Encomende', 'Carregue a sua arte ou gang sheet e faça a encomenda em segundos, poupando tempo.' ),
			array( '<path d="M12 2 2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>', 'Transferências DTF Premium', 'Eleve os seus projetos com transferências DTF premium e cores vibrantes.' ),
			array( '<path d="M12 2s5 4 5 9a5 5 0 01-10 0c0-2 1-3 1-3s0 2 2 2c0-3 2-5 2-8z"/>', 'Prensa Térmica e Descole', 'Aplique pressão média durante 10 a 15 segundos. Descole e está pronto.' ),
		);
		$faq = array(
			array( 'O que é uma transferência DTF?', 'DTF (Direct to Film) é uma técnica em que o desenho é impresso numa película especial e depois transferido para o tecido com calor e pressão. Adere a praticamente qualquer tecido, com cores vivas e grande durabilidade.' ),
			array( 'Qual é a encomenda mínima?', 'Nas transferências DTF a metro não há mínimos: pode encomendar a partir de 1 metro linear. Nas t-shirts personalizadas a encomenda mínima é de 5 unidades.' ),
			array( 'Que tipo de prensa preciso?', 'Uma prensa térmica plana. Aplique a cerca de 150–160 °C, com pressão média, durante 10 a 15 segundos. Também funciona com prensa de caneca ou boné para superfícies adequadas.' ),
			array( 'Que tamanhos estão disponíveis?', 'As transferências DTF são vendidas a metro (largura de rolo fixa), por isso escolhe o comprimento que precisar. Para impressões em peças, temos tamanhos de A7 até A3.' ),
			array( 'Como resistem à lavagem?', 'Com a aplicação correta, as transferências DTF aguentam mais de 50 lavagens sem estalar nem desbotar. Lave do avesso, a 30 °C, e evite secador muito quente.' ),
			array( 'Com que rapidez recebo a encomenda?', 'Produzimos no próprio dia sempre que possível. O envio para Portugal Continental demora normalmente 1 a 2 dias úteis. Portes grátis em encomendas acima de 150€.' ),
			array( 'Que formato de ficheiro devo enviar?', 'De preferência PNG com fundo transparente em alta resolução (300 dpi), ou PDF. Também aceitamos JPG. Se o ficheiro for grande, pode enviar o link do WeTransfer nas notas da encomenda.' ),
			array( 'Qual é a política de reembolso?', 'Como cada transferência é personalizada, não aceitamos devoluções por desistência. Se houver algum defeito de impressão da nossa parte, reimprimimos ou reembolsamos sem custos.' ),
			array( 'É possível cancelar uma encomenda?', 'Sim, desde que a produção ainda não tenha começado. Contacte-nos o quanto antes pelo telefone ou pelo formulário de contacto.' ),
		);

		$stars = '★★★★★';
		ob_start();
		?>
<div class="fdtf-home">

  <div class="fh-wrap">

    <!-- HERO -->
    <h1 class="fh-seo-h1">Transferências DTF premium e t-shirts personalizadas, impressas no próprio dia</h1>
    <section class="fh-hero" aria-label="Destaques">
      <div class="fh-slides">
        <?php foreach ( $banners as $bi => $bn ) : ?>
        <a class="fh-slide<?php echo 0 === $bi ? ' on' : ''; ?>" href="<?php echo esc_url( $bn[2] ); ?>">
          <img src="<?php echo esc_url( $bn[0] ); ?>" alt="<?php echo esc_attr( $bn[1] ); ?>"<?php echo 0 === $bi ? '' : ' loading="lazy"'; ?>>
        </a>
        <?php endforeach; ?>
      </div>
      <button class="fh-arrow prev" type="button" aria-label="Anterior">‹</button>
      <button class="fh-arrow next" type="button" aria-label="Seguinte">›</button>
      <div class="fh-dots">
        <?php foreach ( $banners as $bi => $bn ) : ?>
        <button<?php echo 0 === $bi ? ' class="on"' : ''; ?> type="button" aria-label="Slide <?php echo (int) $bi + 1; ?>"></button>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <!-- CATEGORIES -->
  <section class="fh-sec fh-cats-sec">
    <div class="fh-wrap">
      <div class="fh-cats">
        <?php foreach ( $cats as $c ) : ?>
        <div class="fh-cat">
          <h3><?php echo esc_html( $c[0] ); ?></h3>
          <div class="fh-catimg">
            <?php if ( $c[1] ) : ?>
              <img src="<?php echo esc_url( $c[1] ); ?>" alt="<?php echo esc_attr( $c[0] ); ?>" loading="lazy">
            <?php else : ?>
              <span class="fh-emoji"><?php echo esc_html( $c[2] ); ?></span>
            <?php endif; ?>
          </div>
          <a class="fh-catbtn" href="<?php echo esc_url( $c[4] ); ?>"><?php echo esc_html( $c[3] ); ?></a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- REVIEWS -->
  <section class="fh-sec tint">
    <div class="fh-wrap" style="text-align:center">
      <div class="fh-gbadge">
        <span class="fh-gicon"><img src="<?php echo esc_url( $logo ); ?>" alt="Logótipo Fábrica DTF"></span>
        <span><b>4,9</b> <span class="fh-stars"><?php echo $stars; ?></span><small>216 avaliações no Google</small></span>
      </div>
      <div class="fh-reviews">
        <?php foreach ( $reviews as $r ) : ?>
        <div class="fh-rev">
          <div class="fh-revhead">
            <span class="fh-av"><?php echo esc_html( mb_substr( $r[0], 0, 1 ) ); ?></span>
            <span><span class="fh-nm"><?php echo esc_html( $r[0] ); ?></span><br><span class="fh-when"><?php echo esc_html( $r[1] ); ?></span></span>
          </div>
          <div class="fh-stars"><?php echo $stars; ?></div>
          <p><?php echo esc_html( $r[2] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- GANG SHEETS -->
  <section class="fh-sec">
    <div class="fh-wrap">
      <div class="fh-gang">
        <div class="fh-gimg"><img src="<?php echo esc_url( $img . 'gang.jpg' ); ?>" alt="Crie a sua folha DTF (gang sheet) no editor" loading="lazy"></div>
        <div>
          <h2>Maximize o seu lucro com Gang Sheets personalizadas</h2>
          <p class="fh-glead">Deixe de pagar por design. Coloque os logos, gráficos e etiquetas que quiser numa única folha e pague um preço baixo por metro.</p>
          <div class="fh-gfeat">
            <b>Cores ilimitadas</b><span>Gradientes e fotos a cores completas, sem custos de setup.</span>
            <b>Encomenda simples</b><span>Escolha os metros, envie a arte e nós tratamos do resto.</span>
            <b>Preços por escalão</b><span>Quanto maior a folha, mais barato fica ao metro.</span>
          </div>
          <a class="fh-btn navy" href="<?php echo esc_url( $editor ); ?>">Abrir editor e crie a sua folha</a>
        </div>
      </div>
    </div>
  </section>

  <!-- PROCESS -->
  <section class="fh-sec navy">
    <div class="fh-wrap">
      <h2 class="fh-title">Processo de encomenda e aplicação DTF</h2>
      <div class="fh-steps">
        <?php foreach ( $steps as $s ) : ?>
        <div class="fh-step">
          <div class="fh-sic"><svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $s[0]; ?></svg></div>
          <h3><?php echo esc_html( $s[1] ); ?></h3>
          <p><?php echo esc_html( $s[2] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="fh-sec tint">
    <div class="fh-wrap">
      <h2 class="fh-title">Perguntas frequentes</h2>
      <div class="fh-faq">
        <?php foreach ( $faq as $q ) : ?>
        <details>
          <summary><?php echo esc_html( $q[0] ); ?></summary>
          <div class="fh-a"><?php echo esc_html( $q[1] ); ?></div>
        </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ( ! empty( $posts ) ) : ?>
  <!-- BLOG -->
  <section class="fh-sec tint">
    <div class="fh-wrap">
      <div class="fh-bloghead">
        <h2 class="fh-title fh-blogtitle">Blog</h2>
        <a class="fh-blogall" href="<?php echo esc_url( $blog_url ); ?>">Ver todos →</a>
      </div>
      <div class="fh-blog">
        <?php foreach ( $posts as $p ) :
          $thumb = get_the_post_thumbnail_url( $p->ID, 'medium_large' );
          $purl  = get_permalink( $p->ID );
        ?>
        <a class="fh-post" href="<?php echo esc_url( $purl ); ?>">
          <div class="fh-postimg">
            <?php if ( $thumb ) : ?>
              <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $p->ID ) ); ?>" loading="lazy">
            <?php else : ?>
              <span class="fh-emoji">📝</span>
            <?php endif; ?>
          </div>
          <div class="fh-postbody">
            <span class="fh-postdate"><?php echo esc_html( get_the_date( 'j M Y', $p->ID ) ); ?></span>
            <h3><?php echo esc_html( get_the_title( $p->ID ) ); ?></h3>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- CTA -->
  <section class="fh-sec">
    <div class="fh-wrap">
      <div class="fh-cta">
        <h2>Pronto para imprimir as suas ideias?</h2>
        <p>Transferências DTF a metro e t-shirts personalizadas, com produção no próprio dia.</p>
        <a class="fh-btn" href="<?php echo esc_url( $dtf_url ); ?>">Começar agora</a>
        &nbsp;
        <a class="fh-btn navy" href="<?php echo esc_url( $contact ); ?>">Falar connosco</a>
      </div>
    </div>
  </section>

</div>
<?php
		// FAQPage structured data for Google rich results.
		$faq_items = array();
		foreach ( $faq as $q ) {
			$faq_items[] = array(
				'@type'          => 'Question',
				'name'           => $q[0],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $q[1] ),
			);
		}
		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $faq_items,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
		return ob_get_clean();
	}
}
