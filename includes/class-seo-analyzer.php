<?php
/**
 * SEO Analyzer Engine
 *
 * Lightweight, zero-dependency, local On-Page SEO analyzer.
 * Inspects any page URL (homepage, post, custom post type, product)
 * for 18+ critical SEO, content, media, performance, and schema factors.
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Analyzer {

	public function __construct() {
		// Add top admin bar unified dropdown menu
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 90 );
	}

	/**
	 * Build unified Admin Bar dropdown menu (matches Rank Math UX)
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_frontend = ! is_admin();
		$current_url = '';
		$clean_path  = '';
		if ( $is_frontend && ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$clean_path  = ltrim( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
		}

		// 1. Parent Node: "📊 SEO Core"
		$wp_admin_bar->add_node( array(
			'id'    => 'seo_core_top_menu',
			'title' => '<span class="ab-icon dashicons dashicons-chart-bar" style="margin-top:2px;"></span> <span class="ab-label">SEO Core</span>',
			'href'  => admin_url( 'admin.php?page=seo-core-best' ),
			'meta'  => array(
				'title' => __( 'SEO Core Best: Панель управления SEO', 'seo-core-best' ),
			),
		) );

		// 2. Child: "🎯 SEO Анализ этой страницы" (frontend only)
		if ( $is_frontend && ! empty( $current_url ) && ! is_404() ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'seo_core_top_menu',
				'id'     => 'seo_core_menu_analyze_page',
				'title'  => '🎯 <strong>' . __( 'SEO Анализ этой страницы', 'seo-core-best' ) . '</strong>',
				'href'   => admin_url( 'admin.php?page=seo-core-best&tab=analyzer&analyze_url=' . rawurlencode( $current_url ) ),
				'meta'   => array(
					'title' => __( 'Провести мгновенный SEO аудит текущей страницы', 'seo-core-best' ),
				),
			) );
		}

		// 3. Child: "📊 SEO Анализатор (Сайт)"
		$wp_admin_bar->add_node( array(
			'parent' => 'seo_core_top_menu',
			'id'     => 'seo_core_menu_analyzer',
			'title'  => '📊 ' . __( 'SEO Анализатор сайта', 'seo-core-best' ),
			'href'   => admin_url( 'admin.php?page=seo-core-best&tab=analyzer' ),
		) );

		// 4. Child: "⚙️ Настройки и шаблоны"
		$wp_admin_bar->add_node( array(
			'parent' => 'seo_core_top_menu',
			'id'     => 'seo_core_menu_settings',
			'title'  => '⚙️ ' . __( 'Настройки и шаблоны Titles', 'seo-core-best' ),
			'href'   => admin_url( 'admin.php?page=seo-core-best&tab=titles' ),
		) );

		// 5. Child: "🔍 Мониторинг 404"
		$stat404 = class_exists( 'Seo_Core_404_Monitor' ) ? Seo_Core_404_Monitor::get_stats() : array( 'total_urls' => 0 );
		$badge_404 = ( ! empty( $stat404['total_urls'] ) && $stat404['total_urls'] > 0 ) ? ' <span style="background:#f59e0b;color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;font-weight:700;">' . $stat404['total_urls'] . '</span>' : '';
		$wp_admin_bar->add_node( array(
			'parent' => 'seo_core_top_menu',
			'id'     => 'seo_core_menu_404',
			'title'  => '🔍 ' . __( 'Мониторинг 404', 'seo-core-best' ) . $badge_404,
			'href'   => admin_url( 'admin.php?page=seo-core-best&tab=monitor404' ),
		) );

		// 6. Child: "🔀 301 Редиректы"
		$wp_admin_bar->add_node( array(
			'parent' => 'seo_core_top_menu',
			'id'     => 'seo_core_menu_redirects',
			'title'  => '🔀 ' . __( '301 Редиректы', 'seo-core-best' ),
			'href'   => admin_url( 'admin.php?page=seo-core-best&tab=redirects' ),
		) );

		// 7. Child: "Сделать редирект для этой страницы" (frontend only, triggers modal)
		if ( $is_frontend && ! empty( $clean_path ) ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'seo_core_top_menu',
				'id'     => 'seo_core_menu_quick_redirect',
				'title'  => '↳ ' . __( 'Сделать редирект для страницы', 'seo-core-best' ),
				'href'   => '#',
				'meta'   => array(
					'class' => 'seo-core-admin-bar-btn',
					'title' => __( 'Открыть окно быстрого создания редиректа', 'seo-core-best' ),
				),
			) );
		}

		// 8. Child: "🚀 Мгновенная индексация API"
		$wp_admin_bar->add_node( array(
			'parent' => 'seo_core_top_menu',
			'id'     => 'seo_core_menu_indexing',
			'title'  => '🚀 ' . __( 'Мгновенная индексация API', 'seo-core-best' ),
			'href'   => admin_url( 'admin.php?page=seo-core-best&tab=indexing' ),
		) );

		// 9. Child: "🗺️ XML Карта сайта"
		$wp_admin_bar->add_node( array(
			'parent' => 'seo_core_top_menu',
			'id'     => 'seo_core_menu_sitemap',
			'title'  => '🗺️ ' . __( 'XML Карта сайта', 'seo-core-best' ) . ' ↗',
			'href'   => home_url( '/sitemap_index.xml' ),
			'meta'   => array(
				'target' => '_blank',
			),
		) );

		// Special: If on 404 page, ALSO show prominent direct button on top bar
		if ( $is_frontend && is_404() ) {
			$wp_admin_bar->add_node( array(
				'id'    => 'seo-core-redirect-this',
				'title' => '<span style="color: #fbbf24; font-weight: 700;">⚠️ Редирект 404</span>',
				'href'  => '#',
				'meta'  => array(
					'class' => 'seo-core-admin-bar-btn',
					'title' => __( 'Создать 301-редирект прямо сейчас', 'seo-core-best' ),
				),
			) );
		}
	}

	/**
	 * Run comprehensive SEO audit on a given URL
	 *
	 * @param string $url URL to analyze.
	 * @param bool   $force Refresh cache if true.
	 * @return array
	 */
	public static function run_audit( $url, $force = false ) {
		$url = esc_url_raw( trim( $url ) );
		if ( empty( $url ) ) {
			$url = home_url( '/' );
		}

		$cache_key = 'seo_audit_' . md5( $url );
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$start_time = microtime( true );

		// Fetch the page content with cache-busting to bypass server-side caching (LiteSpeed, WP Rocket, Nginx FastCGI)
		$fetch_url = add_query_arg( 'seo_audit_nocache', time(), $url );

		$response = wp_remote_get( $fetch_url, array(
			'timeout'     => 15,
			'sslverify'   => false,
			'user-agent'  => 'Mozilla/5.0 (compatible; SeoCoreBestAnalyzer/1.0; +https://aliprofi.ru)',
			'headers'     => array(
				'Accept'        => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Pragma'        => 'no-cache',
			),
		) );

		$load_time = round( ( microtime( true ) - $start_time ), 3 );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => sprintf( __( 'Не удалось загрузить страницу: %s', 'seo-core-best' ), $response->get_error_message() ),
				'url'     => $url,
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$html          = wp_remote_retrieve_body( $response );
		$html_size_kb  = round( strlen( $html ) / 1024, 1 );

		// Run tests
		$tests = array();

		// -------------------------------------------------------------
		// 1. Basic SEO Tests
		// -------------------------------------------------------------

		// Test: Title tag
		preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $title_match );
		$title = isset( $title_match[1] ) ? trim( html_entity_decode( $title_match[1], ENT_QUOTES, 'UTF-8' ) ) : '';
		$title_len = mb_strlen( $title, 'UTF-8' );

		if ( empty( $title ) ) {
			$tests['title'] = array(
				'category' => 'basic',
				'status'   => 'fail',
				'title'    => __( 'Тег Title (SEO Заголовок)', 'seo-core-best' ),
				'message'  => __( 'Тег &lt;title&gt; отсутствует на странице или пуст. Это критический фактор ранжирования!', 'seo-core-best' ),
				'how_to'   => __( 'Укажите SEO-заголовок в метабоксе статьи или проверьте шаблоны заголовков.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 0,
			);
		} elseif ( $title_len < 30 ) {
			$tests['title'] = array(
				'category' => 'basic',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Тег Title (%d символов)', 'seo-core-best' ), $title_len ),
				'message'  => sprintf( __( 'Заголовок слишком короткий (%d символов: "%s"). Рекомендуемая длина — от 35 до 65 символов.', 'seo-core-best' ), $title_len, esc_html( $title ) ),
				'how_to'   => __( 'Добавьте ключевые слова или название сайта, чтобы повысить кликабельность в выдаче.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 5,
			);
		} elseif ( $title_len > 68 ) {
			$tests['title'] = array(
				'category' => 'basic',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Тег Title (%d символов)', 'seo-core-best' ), $title_len ),
				'message'  => sprintf( __( 'Заголовок длиннее 68 символов (%d симв.: "%s"). Поисковики могут обрезать его в сниппете.', 'seo-core-best' ), $title_len, esc_html( $title ) ),
				'how_to'   => __( 'Сократите заголовок до 60-65 знаков, оставив самое важное в начале.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 7,
			);
		} else {
			$tests['title'] = array(
				'category' => 'basic',
				'status'   => 'pass',
				'title'    => sprintf( __( 'Тег Title (%d символов) — Отлично', 'seo-core-best' ), $title_len ),
				'message'  => sprintf( __( 'Заголовок имеет идеальную длину (%d симв.): "%s"', 'seo-core-best' ), $title_len, esc_html( $title ) ),
				'how_to'   => '',
				'weight'   => 10,
				'earned'   => 10,
			);
		}

		// Test: Meta Description
		preg_match( '/<meta[^>]+name=[\'"]description[\'"][^>]+content=[\'"](.*?)[\'"][^>]*>/is', $html, $desc_match );
		if ( empty( $desc_match[1] ) ) {
			preg_match( '/<meta[^>]+content=[\'"](.*?)[\'"][^>]+name=[\'"]description[\'"][^>]*>/is', $html, $desc_match );
		}
		$desc = isset( $desc_match[1] ) ? trim( html_entity_decode( $desc_match[1], ENT_QUOTES, 'UTF-8' ) ) : '';
		$desc_len = mb_strlen( $desc, 'UTF-8' );

		if ( empty( $desc ) ) {
			$tests['description'] = array(
				'category' => 'basic',
				'status'   => 'fail',
				'title'    => __( 'Мета-описание (Meta Description)', 'seo-core-best' ),
				'message'  => __( 'Мета-описание отсутствует на странице. Поисковики будут выдергивать случайный текст из контента.', 'seo-core-best' ),
				'how_to'   => __( 'Заполните поле «Мета-описание» в метабоксе статьи.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 0,
			);
		} elseif ( $desc_len < 80 ) {
			$tests['description'] = array(
				'category' => 'basic',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Мета-описание (%d символов)', 'seo-core-best' ), $desc_len ),
				'message'  => sprintf( __( 'Описание коротковато (%d символов). Оптимальная длина — 120–160 знаков.', 'seo-core-best' ), $desc_len ),
				'how_to'   => __( 'Расширьте описание, добавив тезисы и призыв к действию.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 6,
			);
		} elseif ( $desc_len > 175 ) {
			$tests['description'] = array(
				'category' => 'basic',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Мета-описание (%d символов)', 'seo-core-best' ), $desc_len ),
				'message'  => sprintf( __( 'Описание длиннее 175 символов (%d симв.). Хвост описания скроется в сниппете выдачи.', 'seo-core-best' ), $desc_len ),
				'how_to'   => __( 'Сократите описание до 150-160 знаков.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 7,
			);
		} else {
			$tests['description'] = array(
				'category' => 'basic',
				'status'   => 'pass',
				'title'    => sprintf( __( 'Мета-описание (%d символов) — Отлично', 'seo-core-best' ), $desc_len ),
				'message'  => sprintf( __( 'Описание заполнено оптимально (%d симв.): "%s"', 'seo-core-best' ), $desc_len, esc_html( $desc ) ),
				'how_to'   => '',
				'weight'   => 10,
				'earned'   => 10,
			);
		}

		// Test: H1 Headings
		preg_match_all( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1_matches );
		$h1_count = isset( $h1_matches[1] ) ? count( $h1_matches[1] ) : 0;

		if ( 0 === $h1_count ) {
			$tests['h1'] = array(
				'category' => 'basic',
				'status'   => 'fail',
				'title'    => __( 'Главный заголовок H1', 'seo-core-best' ),
				'message'  => __( 'На странице не обнаружен тег &lt;h1&gt;. Главный заголовок обязателен для поисковой оптимизации.', 'seo-core-best' ),
				'how_to'   => __( 'Убедитесь, что заголовок статьи выводится вашей темой в теге &lt;h1&gt;.', 'seo-core-best' ),
				'weight'   => 8,
				'earned'   => 0,
			);
		} elseif ( $h1_count > 1 ) {
			$tests['h1'] = array(
				'category' => 'basic',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Заголовки H1 (Найдено %d)', 'seo-core-best' ), $h1_count ),
				'message'  => sprintf( __( 'Найдено %d тегов &lt;h1&gt;. По стандартам SEO рекомендуется использовать строго один H1 на страницу.', 'seo-core-best' ), $h1_count ),
				'how_to'   => __( 'Замените второстепенные заголовки H1 на H2 или H3.', 'seo-core-best' ),
				'weight'   => 8,
				'earned'   => 4,
			);
		} else {
			$h1_text = trim( strip_tags( $h1_matches[1][0] ) );
			$tests['h1'] = array(
				'category' => 'basic',
				'status'   => 'pass',
				'title'    => __( 'Заголовок H1 — Отлично (1 тег)', 'seo-core-best' ),
				'message'  => sprintf( __( 'На странице ровно один H1: "%s"', 'seo-core-best' ), esc_html( $h1_text ) ),
				'how_to'   => '',
				'weight'   => 8,
				'earned'   => 8,
			);
		}

		// Test: H2 Subheadings
		preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $html, $h2_matches );
		$h2_count = isset( $h2_matches[1] ) ? count( $h2_matches[1] ) : 0;

		if ( 0 === $h2_count ) {
			$tests['h2'] = array(
				'category' => 'basic',
				'status'   => 'warning',
				'title'    => __( 'Подзаголовки H2', 'seo-core-best' ),
				'message'  => __( 'На странице нет подзаголовков &lt;h2&gt;. Текст без подзаголовков тяжело воспринимается читателями.', 'seo-core-best' ),
				'how_to'   => __( 'Разбейте материал на логические блоки с помощью заголовков H2.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 2,
			);
		} else {
			$tests['h2'] = array(
				'category' => 'basic',
				'status'   => 'pass',
				'title'    => sprintf( __( 'Подзаголовки H2 (%d шт.) — Отлично', 'seo-core-best' ), $h2_count ),
				'message'  => sprintf( __( 'Материал отлично структурирован с помощью %d подзаголовков H2.', 'seo-core-best' ), $h2_count ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// Test: Site Tagline
		$blog_desc = get_bloginfo( 'description' );
		$is_default_tagline = ( empty( $blog_desc ) || false !== stripos( $blog_desc, 'Just another WordPress site' ) || false !== stripos( $blog_desc, 'Ещё один сайт на WordPress' ) );
		if ( $is_default_tagline ) {
			$tests['tagline'] = array(
				'category' => 'basic',
				'status'   => 'warning',
				'title'    => __( 'Слоган сайта (Tagline)', 'seo-core-best' ),
				'message'  => __( 'Слоган сайта не задан или остался стандартным («Ещё один сайт на WordPress»).', 'seo-core-best' ),
				'how_to'   => __( 'Укажите уникальное описание в Настройки &rarr; Общие.', 'seo-core-best' ),
				'weight'   => 4,
				'earned'   => 0,
			);
		} else {
			$tests['tagline'] = array(
				'category' => 'basic',
				'status'   => 'pass',
				'title'    => __( 'Слоган сайта — Задан', 'seo-core-best' ),
				'message'  => sprintf( __( 'Слоган сайта настроен: "%s"', 'seo-core-best' ), esc_html( $blog_desc ) ),
				'how_to'   => '',
				'weight'   => 4,
				'earned'   => 4,
			);
		}

		// -------------------------------------------------------------
		// 2. Content & Quality Tests
		// -------------------------------------------------------------

		// Test: Content word count
		$body_text = strip_tags( preg_replace( '/<(script|style|svg|noscript|header|footer|nav)[^>]*>.*?<\/\1>/is', '', $html ) );
		$words = preg_split( '/\s+/u', trim( $body_text ), -1, PREG_SPLIT_NO_EMPTY );
		$word_count = count( $words );

		if ( $word_count < 200 ) {
			$tests['word_count'] = array(
				'category' => 'content',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Объем контента (%d слов)', 'seo-core-best' ), $word_count ),
				'message'  => sprintf( __( 'На странице мало текста (%d слов). Поисковики могут посчитать страницу «тонким контентом» (thin content).', 'seo-core-best' ), $word_count ),
				'how_to'   => __( 'Добавьте полезного текстового контента, чтобы лучше отвечать на запрос пользователя.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 2,
			);
		} else {
			$tests['word_count'] = array(
				'category' => 'content',
				'status'   => 'pass',
				'title'    => sprintf( __( 'Объем контента (%d слов) — Достаточно', 'seo-core-best' ), $word_count ),
				'message'  => sprintf( __( 'Страница содержит хороший объем текста (%d слов).', 'seo-core-best' ), $word_count ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// Test: Unparsed raw shortcodes
		preg_match_all( '/\[(currentyear|currentmonth|upcomingmonth|rank_math_[a-z0-9_]+|contact-form-[0-9]+|wpforms|gallery|caption)[^\]]*\]/i', $html, $raw_shortcodes );
		$unparsed_count = isset( $raw_shortcodes[0] ) ? count( array_unique( $raw_shortcodes[0] ) ) : 0;

		if ( $unparsed_count > 0 ) {
			$tests['shortcodes'] = array(
				'category' => 'content',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Нераскрытые шорткоды (%d шт.)', 'seo-core-best' ), $unparsed_count ),
				'message'  => sprintf( __( 'В исходном HTML страницы найдены сырые шорткоды: %s. Посетители и роботы видят их как текст в скобках!', 'seo-core-best' ), esc_html( implode( ', ', array_slice( $raw_shortcodes[0], 0, 3 ) ) ) ),
				'how_to'   => __( 'Убедитесь, что плагины для этих шорткодов активны или обработаны фильтром do_shortcode().', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 1,
			);
		} else {
			$tests['shortcodes'] = array(
				'category' => 'content',
				'status'   => 'pass',
				'title'    => __( 'Шорткоды и переменные — Раскрыты', 'seo-core-best' ),
				'message'  => __( 'Сырых необработанных шорткодов на странице не обнаружено. Все динамические теги обработаны корректно.', 'seo-core-best' ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// -------------------------------------------------------------
		// 3. Media & Links
		// -------------------------------------------------------------

		// Test: Image Alt Attributes
		preg_match_all( '/<img[^>]+>/is', $html, $img_matches );
		$total_imgs = isset( $img_matches[0] ) ? count( $img_matches[0] ) : 0;
		$missing_alt = 0;

		if ( $total_imgs > 0 ) {
			foreach ( $img_matches[0] as $img_tag ) {
				if ( ! preg_match( '/alt=[\'"][^\'"]+[\'"]/i', $img_tag ) ) {
					$missing_alt++;
				}
			}
		}

		if ( 0 === $total_imgs ) {
			$tests['images'] = array(
				'category' => 'media',
				'status'   => 'pass',
				'title'    => __( 'Изображения (Alt-текст)', 'seo-core-best' ),
				'message'  => __( 'На странице нет изображений или они подключены через CSS фоны.', 'seo-core-best' ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		} elseif ( $missing_alt > 0 ) {
			$tests['images'] = array(
				'category' => 'media',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Атрибуты Alt у картинок (%d из %d без alt)', 'seo-core-best' ), $missing_alt, $total_imgs ),
				'message'  => sprintf( __( 'Найдено %d изображений, из них %d без описания в атрибуте alt. Поисковики картинок Яндекс и Google не смогут их проиндексировать.', 'seo-core-best' ), $total_imgs, $missing_alt ),
				'how_to'   => __( 'Добавьте содержательный замещающий текст (alt) в медиабиблиотеке или в редакторе.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => max( 1, 6 - ( $missing_alt * 2 ) ),
			);
		} else {
			$tests['images'] = array(
				'category' => 'media',
				'status'   => 'pass',
				'title'    => sprintf( __( 'Атрибуты Alt у картинок (Все %d с alt) — Отлично', 'seo-core-best' ), $total_imgs ),
				'message'  => sprintf( __( 'Все %d изображений на странице имеют заполненный атрибут alt.', 'seo-core-best' ), $total_imgs ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// Test: Internal & External Links
		preg_match_all( '/<a[^>]+href=[\'"](.*?)[\'"][^>]*>/is', $html, $link_matches );
		$all_links = isset( $link_matches[1] ) ? $link_matches[1] : array();
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		$internal_count = 0;
		$external_count = 0;

		foreach ( $all_links as $l ) {
			if ( 0 === strpos( $l, '#' ) || 0 === strpos( $l, 'javascript:' ) || 0 === strpos( $l, 'mailto:' ) || 0 === strpos( $l, 'tel:' ) ) {
				continue;
			}
			$l_host = wp_parse_url( $l, PHP_URL_HOST );
			if ( empty( $l_host ) || $l_host === $site_host ) {
				$internal_count++;
			} else {
				$external_count++;
			}
		}

		if ( 0 === $internal_count ) {
			$tests['links'] = array(
				'category' => 'media',
				'status'   => 'warning',
				'title'    => __( 'Внутренняя перелинковка', 'seo-core-best' ),
				'message'  => __( 'На странице не найдено внутренних ссылок. Перелинковка важна для распределения веса и навигации.', 'seo-core-best' ),
				'how_to'   => __( 'Добавьте ссылки на смежные статьи или разделы сайта.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 1,
			);
		} else {
			$tests['links'] = array(
				'category' => 'media',
				'status'   => 'pass',
				'title'    => sprintf( __( 'Ссылки и перелинковка (Внутренних: %d, Внешних: %d)', 'seo-core-best' ), $internal_count, $external_count ),
				'message'  => sprintf( __( 'Найдено %d внутренних ссылок и %d внешних ссылок. Баланс ссылочной массы соблюден.', 'seo-core-best' ), $internal_count, $external_count ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// -------------------------------------------------------------
		// 4. Indexing & Technical SEO
		// -------------------------------------------------------------

		// Test: Robots meta & Indexability
		$is_noindex = false;
		preg_match( '/<meta[^>]+name=[\'"]robots[\'"][^>]+content=[\'"](.*?)[\'"][^>]*>/is', $html, $robots_match );
		if ( ! empty( $robots_match[1] ) && false !== stripos( $robots_match[1], 'noindex' ) ) {
			$is_noindex = true;
		}

		// Check blog_public setting
		$is_public = ( '1' === (string) get_option( 'blog_public' ) );

		if ( ! $is_public ) {
			$tests['indexing'] = array(
				'category' => 'technical',
				'status'   => 'fail',
				'title'    => __( 'Индексируемость сайта (Критично)', 'seo-core-best' ),
				'message'  => __( 'В настройках WordPress включена опция «Попросить поисковые системы не индексировать сайт». Сайт полностью заблокирован от Google и Яндекс!', 'seo-core-best' ),
				'how_to'   => __( 'Снимите галочку в Настройки &rarr; Чтение &rarr; Видимость для поисковых систем.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 0,
			);
		} elseif ( $is_noindex ) {
			$tests['indexing'] = array(
				'category' => 'technical',
				'status'   => 'fail',
				'title'    => __( 'Директива Robots Noindex', 'seo-core-best' ),
				'message'  => __( 'На странице найден мета-тег robots="noindex". Страница не будет попадать в результаты поиска.', 'seo-core-best' ),
				'how_to'   => __( 'Если это целевая страница, снимите отметку noindex в SEO-настройках.', 'seo-core-best' ),
				'weight'   => 10,
				'earned'   => 0,
			);
		} else {
			$tests['indexing'] = array(
				'category' => 'technical',
				'status'   => 'pass',
				'title'    => __( 'Индексируемость — Открыто для роботов', 'seo-core-best' ),
				'message'  => __( 'Страница полностью открыта для индексации поисковыми роботами Google и Яндекс.', 'seo-core-best' ),
				'how_to'   => '',
				'weight'   => 10,
				'earned'   => 10,
			);
		}

		// Test: Canonical URL
		preg_match( '/<link[^>]+rel=[\'"]canonical[\'"][^>]+href=[\'"](.*?)[\'"][^>]*>/is', $html, $canon_match );
		$canonical_url = isset( $canon_match[1] ) ? trim( $canon_match[1] ) : '';

		if ( empty( $canonical_url ) ) {
			$tests['canonical'] = array(
				'category' => 'technical',
				'status'   => 'warning',
				'title'    => __( 'Канонический URL (Canonical)', 'seo-core-best' ),
				'message'  => __( 'Тег &lt;link rel="canonical"&gt; не найден. Это может приводить к дублям страниц с параметрами.', 'seo-core-best' ),
				'how_to'   => __( 'SEO Core Best автоматически добавляет канонический тег на все страницы.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 2,
			);
		} else {
			$tests['canonical'] = array(
				'category' => 'technical',
				'status'   => 'pass',
				'title'    => __( 'Канонический URL — Настроен', 'seo-core-best' ),
				'message'  => sprintf( __( 'Канонический тег указан верно: <code>%s</code>', 'seo-core-best' ), esc_html( $canonical_url ) ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// Test: HTTPS / SSL
		$is_https = ( 0 === strpos( $url, 'https://' ) );
		if ( ! $is_https ) {
			$tests['ssl'] = array(
				'category' => 'technical',
				'status'   => 'fail',
				'title'    => __( 'Безопасность соединения (SSL/HTTPS)', 'seo-core-best' ),
				'message'  => __( 'Страница открывается по незащищенному протоколу HTTP. Google пессимизирует сайты без SSL.', 'seo-core-best' ),
				'how_to'   => __( 'Установите бесплатный SSL-сертификат (Let\'s Encrypt) и настройте редирект на HTTPS.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 0,
			);
		} else {
			$tests['ssl'] = array(
				'category' => 'technical',
				'status'   => 'pass',
				'title'    => __( 'SSL Сертификат (HTTPS) — Активен', 'seo-core-best' ),
				'message'  => __( 'Страница использует защищенное шифрованное соединение HTTPS.', 'seo-core-best' ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// Test: Server Response Time
		if ( $load_time > 2.0 ) {
			$tests['speed'] = array(
				'category' => 'technical',
				'status'   => 'warning',
				'title'    => sprintf( __( 'Время ответа сервера (%.2f сек)', 'seo-core-best' ), $load_time ),
				'message'  => sprintf( __( 'Сервер отвечал %.2f сек. Рекомендуемое время генерации HTML — менее 0.8 сек.', 'seo-core-best' ), $load_time ),
				'how_to'   => __( 'Включите страницу кэширования (WP Super Cache / Redis) и оптимизируйте запросы к базе данных.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 2,
			);
		} else {
			$tests['speed'] = array(
				'category' => 'technical',
				'status'   => 'pass',
				'title'    => sprintf( __( 'Скорость ответа (%.2f сек, %.1f КБ) — Отлично', 'seo-core-best' ), $load_time, $html_size_kb ),
				'message'  => sprintf( __( 'Сервер отдал страницу быстро за %.2f сек (размер HTML: %.1f КБ).', 'seo-core-best' ), $load_time, $html_size_kb ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// -------------------------------------------------------------
		// 5. Social & Schema
		// -------------------------------------------------------------

		// Test: OpenGraph tags
		$has_og_title = preg_match( '/<meta[^>]+(?:property|name)=[\'"]og:title[\'"][^>]*>/is', $html );
		$has_og_image = preg_match( '/<meta[^>]+(?:property|name)=[\'"]og:image[\'"][^>]*>/is', $html );

		if ( ! $has_og_title ) {
			$tests['opengraph'] = array(
				'category' => 'social',
				'status'   => 'warning',
				'title'    => __( 'OpenGraph разметка (Соцсети)', 'seo-core-best' ),
				'message'  => __( 'Теги OpenGraph (og:title, og:image) не обнаружены. Ссылки в Telegram и соцсетях будут без красивого превью.', 'seo-core-best' ),
				'how_to'   => __( 'Включите OpenGraph и настройте соцсети во вкладке «Соцсети и OpenGraph» плагина SEO Core Best.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 1,
			);
		} else {
			$tests['opengraph'] = array(
				'category' => 'social',
				'status'   => 'pass',
				'title'    => __( 'OpenGraph разметка — Настроена', 'seo-core-best' ),
				'message'  => __( 'Теги OpenGraph присутствуют. Поделившись ссылкой в соцсетях и мессенджерах, сформируется красивое превью.', 'seo-core-best' ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// Test: Schema.org Structured Data
		$has_schema = ( false !== stripos( $html, 'application/ld+json' ) || false !== stripos( $html, 'itemscope' ) );

		if ( ! $has_schema ) {
			$tests['schema'] = array(
				'category' => 'social',
				'status'   => 'warning',
				'title'    => __( 'Микроразметка Schema.org', 'seo-core-best' ),
				'message'  => __( 'На странице не обнаружена структурированная разметка Schema.org (JSON-LD или Microdata).', 'seo-core-best' ),
				'how_to'   => __( 'Используйте хлебные крошки или разметку статьи для получения расширенных сниппетов в Google.', 'seo-core-best' ),
				'weight'   => 6,
				'earned'   => 2,
			);
		} else {
			$tests['schema'] = array(
				'category' => 'social',
				'status'   => 'pass',
				'title'    => __( 'Микроразметка Schema.org — Присутствует', 'seo-core-best' ),
				'message'  => __( 'На странице найдена разметка Schema.org (JSON-LD / Microdata). Поисковики лучше поймут структуру контента.', 'seo-core-best' ),
				'how_to'   => '',
				'weight'   => 6,
				'earned'   => 6,
			);
		}

		// Calculate total score
		$total_weight = 0;
		$earned_weight = 0;
		$passed_count = 0;
		$warning_count = 0;
		$failed_count = 0;

		foreach ( $tests as $t ) {
			$total_weight += $t['weight'];
			$earned_weight += $t['earned'];

			if ( 'pass' === $t['status'] ) {
				$passed_count++;
			} elseif ( 'warning' === $t['status'] ) {
				$warning_count++;
			} else {
				$failed_count++;
			}
		}

		$score = ( $total_weight > 0 ) ? round( ( $earned_weight / $total_weight ) * 100 ) : 0;

		$post_id = class_exists( 'Seo_Core_AI' ) ? Seo_Core_AI::resolve_post_id_from_url( $url ) : 0;

		$result = array(
			'success'       => true,
			'url'           => $url,
			'post_id'       => $post_id,
			'score'         => $score,
			'passed_count'  => $passed_count,
			'warning_count' => $warning_count,
			'failed_count'  => $failed_count,
			'total_tests'   => count( $tests ),
			'tests'         => $tests,
			'serp_preview'  => array(
				'title' => ! empty( $title ) ? $title : __( '(нет заголовка)', 'seo-core-best' ),
				'desc'  => ! empty( $desc ) ? $desc : __( '(нет описания)', 'seo-core-best' ),
				'url'   => $url,
			),
			'checked_at'    => current_time( 'mysql' ),
			'load_time'     => $load_time,
			'html_size_kb'  => $html_size_kb,
		);

		// Cache for 1 hour
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}
}
