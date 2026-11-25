<?php
/**
 * Lightweight Breadcrumbs Generator & Compatibility Bridge
 *
 * Supports native [seo_breadcrumbs] shortcode, seo_core_breadcrumbs() template tag,
 * and backward compatibility with rank_math_the_breadcrumbs() & [rank_math_breadcrumb].
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Breadcrumbs {

	public function __construct() {
		add_shortcode( 'seo_breadcrumbs', array( $this, 'render_shortcode' ) );

		// Rank Math shortcode compatibility
		if ( ! shortcode_exists( 'rank_math_breadcrumb' ) ) {
			add_shortcode( 'rank_math_breadcrumb', array( $this, 'render_shortcode' ) );
		}
	}

	/**
	 * Render breadcrumbs as HTML string
	 *
	 * @param array $args
	 * @return string
	 */
	public static function get_breadcrumb( $args = array() ) {
		if ( is_front_page() || is_home() ) {
			return '';
		}

		$sep = Seo_Core_Options::get( 'breadcrumbs_separator', '»' );
		$home_title = Seo_Core_Options::get( 'org_name', get_bloginfo( 'name' ) );

		$crumbs = array();

		// 1. Home
		$crumbs[] = array(
			'title' => $home_title,
			'url'   => home_url( '/' ),
		);

		// 2. Singular posts, pages & CPTs
		if ( is_singular() ) {
			global $post;
			$pt = get_post_type( $post );

			// Post Type Archive link if available
			if ( 'post' !== $pt && 'page' !== $pt ) {
				$pt_obj = get_post_type_object( $pt );
				if ( $pt_obj && $pt_obj->has_archive ) {
					$archive_url = get_post_type_archive_link( $pt );
					if ( $archive_url ) {
						$crumbs[] = array(
							'title' => $pt_obj->labels->name,
							'url'   => $archive_url,
						);
					}
				}
			}

			// Category
			$cats = get_the_category( $post->ID );
			if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
				$main_cat = $cats[0];
				$crumbs[] = array(
					'title' => $main_cat->name,
					'url'   => get_category_link( $main_cat->term_id ),
				);
			}

			// Current Page
			$crumbs[] = array(
				'title' => get_the_title( $post->ID ),
				'url'   => '',
			);
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				$crumbs[] = array(
					'title' => $term->name,
					'url'   => '',
				);
			}
		} elseif ( is_search() ) {
			$crumbs[] = array(
				'title' => sprintf( __( 'Поиск: «%s»', 'seo-core-best' ), get_search_query() ),
				'url'   => '',
			);
		} elseif ( is_404() ) {
			$crumbs[] = array(
				'title' => __( 'Ошибка 404: Страница не найдена', 'seo-core-best' ),
				'url'   => '',
			);
		}

		if ( count( $crumbs ) <= 1 ) {
			return '';
		}

		// Build Schema.org HTML
		$html  = '<nav class="breadcrumbs-container seo-core-breadcrumbs" aria-label="' . esc_attr__( 'Навигация', 'seo-core-best' ) . '">';
		$html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList" class="breadcrumbs" style="list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; gap:6px; font-size:14px; align-items:center;">';

		$total = count( $crumbs );
		$pos   = 1;

		foreach ( $crumbs as $i => $crumb ) {
			$is_last = ( $pos === $total );

			$html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" ' . ( $is_last ? 'class="current" aria-current="page"' : '' ) . '>';
			if ( ! empty( $crumb['url'] ) && ! $is_last ) {
				$html .= '<a itemprop="item" href="' . esc_url( $crumb['url'] ) . '" style="text-decoration:none; color:inherit;">';
				$html .= '<span itemprop="name">' . esc_html( $crumb['title'] ) . '</span>';
				$html .= '</a>';
			} else {
				$html .= '<span itemprop="name">' . esc_html( $crumb['title'] ) . '</span>';
			}
			$html .= '<meta itemprop="position" content="' . $pos . '" />';
			$html .= '</li>';

			if ( ! $is_last ) {
				$html .= '<li aria-hidden="true" class="separator" style="color:#94a3b8; user-select:none;">' . esc_html( $sep ) . '</li>';
			}

			$pos++;
		}

		$html .= '</ol>';
		$html .= '</nav>';

		return $html;
	}

	/**
	 * Shortcode callback
	 */
	public function render_shortcode() {
		return self::get_breadcrumb();
	}
}

/**
 * Global helper functions
 */
if ( ! function_exists( 'seo_core_breadcrumbs' ) ) {
	function seo_core_breadcrumbs( $args = array() ) {
		echo Seo_Core_Breadcrumbs::get_breadcrumb( $args );
	}
}

if ( ! function_exists( 'seo_core_get_breadcrumbs' ) ) {
	function seo_core_get_breadcrumbs( $args = array() ) {
		return Seo_Core_Breadcrumbs::get_breadcrumb( $args );
	}
}

/**
 * Rank Math Compatibility Bridge Functions
 */
if ( ! function_exists( 'rank_math_the_breadcrumbs' ) ) {
	function rank_math_the_breadcrumbs( $args = array() ) {
		echo Seo_Core_Breadcrumbs::get_breadcrumb( $args );
	}
}

if ( ! function_exists( 'rank_math_get_breadcrumbs' ) ) {
	function rank_math_get_breadcrumbs( $args = array() ) {
		return Seo_Core_Breadcrumbs::get_breadcrumb( $args );
	}
}
