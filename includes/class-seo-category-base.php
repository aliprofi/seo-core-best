<?php
/**
 * Strip /category/ Base from Category URLs & 301 Redirect old URLs
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Category_Base {

	public function __construct() {
		if ( ! Seo_Core_Options::get( 'strip_category_base', 0 ) ) {
			return;
		}

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'request', array( $this, 'handle_request' ), 1 );
		add_filter( 'category_rewrite_rules', array( $this, 'category_rewrite_rules' ) );
		add_filter( 'category_link', array( $this, 'strip_category_base' ), 999 );
		add_filter( 'term_link', array( $this, 'no_category_base' ), 999, 3 );

		add_action( 'created_category', array( $this, 'schedule_flush_rewrites' ) );
		add_action( 'delete_category', array( $this, 'schedule_flush_rewrites' ) );
		add_action( 'edited_category', array( $this, 'schedule_flush_rewrites' ) );
	}

	/**
	 * Schedule flush of rewrite rules
	 */
	public function schedule_flush_rewrites() {
		flush_rewrite_rules();
	}

	/**
	 * Add custom redirect query var
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public function query_vars( $query_vars ) {
		$query_vars[] = 'seo_core_category_redirect';
		return $query_vars;
	}

	/**
	 * Handle request:
	 * 1. 301 Redirect old /category/slug/ URLs to new clean /slug/
	 * 2. Fallback category matching if rewrite rules haven't flushed yet
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public function handle_request( $query_vars ) {
		// 1. 301 Redirect old /category/ URL to new clean URL
		if ( isset( $query_vars['seo_core_category_redirect'] ) ) {
			$cat_slug = sanitize_text_field( $query_vars['seo_core_category_redirect'] );
			$cat_link = trailingslashit( home_url() ) . user_trailingslashit( $cat_slug, 'category' );
			wp_safe_redirect( $cat_link, 301 );
			exit;
		}

		// Also catch direct /category/ URLs before WP processes them
		if ( ! is_admin() && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$category_base = get_option( 'category_base' );
			if ( empty( $category_base ) ) {
				$category_base = 'category';
			}
			$category_base = trim( $category_base, '/' );

			$site_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			$site_path = rtrim( $site_path, '/' );
			$base_prefix = $site_path . '/' . $category_base . '/';

			$req_path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
			if ( 0 === strpos( $req_path, $base_prefix ) ) {
				$clean_subpath = substr( $req_path, strlen( $base_prefix ) );
				$new_url = home_url( '/' . ltrim( $clean_subpath, '/' ) );
				if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
					$new_url .= '?' . $_SERVER['QUERY_STRING'];
				}
				wp_safe_redirect( $new_url, 301 );
				exit;
			}
		}

		// 2. Fallback category match if rewrite rules haven't been flushed yet
		if ( empty( $query_vars['category_name'] ) && ! empty( $query_vars['name'] ) ) {
			$slug = $query_vars['name'];
			$cat  = get_category_by_slug( $slug );
			if ( $cat && ! is_wp_error( $cat ) ) {
				// Verify it is not an actual post with this name
				global $wpdb;
				$post_exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish' LIMIT 1",
					$slug
				) );

				if ( ! $post_exists ) {
					unset( $query_vars['name'] );
					$query_vars['category_name'] = $slug;
				}
			}
		}

		return $query_vars;
	}

	/**
	 * Build clean category rewrite rules without category base
	 *
	 * @return array
	 */
	public function category_rewrite_rules() {
		global $wp_rewrite;

		$category_rewrite = $this->get_category_rules();

		// Add 301 redirect rule for old category URLs
		$old_base = str_replace( '%category%', '(.+)', $wp_rewrite->get_category_permastruct() );
		$old_base = trim( $old_base, '/' );
		$category_rewrite[ $old_base . '$' ] = 'index.php?seo_core_category_redirect=$matches[1]';

		return $category_rewrite;
	}

	/**
	 * Remove category base from category links
	 *
	 * @param string $link
	 * @param object $term
	 * @param string $taxonomy
	 * @return string
	 */
	public function no_category_base( $link, $term = null, $taxonomy = '' ) {
		if ( ! empty( $taxonomy ) && 'category' !== $taxonomy ) {
			return $link;
		}

		return $this->strip_category_base( $link );
	}

	/**
	 * Strip category base string from URL
	 *
	 * @param string $link
	 * @return string
	 */
	public function strip_category_base( $link ) {
		$category_base = get_option( 'category_base' );
		if ( empty( $category_base ) ) {
			global $wp_rewrite;
			if ( isset( $wp_rewrite ) ) {
				$category_base = trim( str_replace( '%category%', '', $wp_rewrite->get_category_permastruct() ), '/' );
			}
		}

		if ( empty( $category_base ) ) {
			$category_base = 'category';
		}

		$category_base = trim( $category_base, '/' );

		// Remove /category/ (or custom base) from the URL
		$pattern = '#/' . preg_quote( $category_base, '#' ) . '/#';
		return preg_replace( $pattern, '/', $link, 1 );
	}

	/**
	 * Get category rewrite rules for all categories including children
	 *
	 * @return array
	 */
	private function get_category_rules() {
		global $wp_rewrite;

		$category_rewrite = array();
		$categories       = get_categories( array( 'hide_empty' => false ) );
		$blog_prefix      = $this->get_blog_prefix();
		$pagination_base  = isset( $wp_rewrite->pagination_base ) ? $wp_rewrite->pagination_base : 'page';

		if ( empty( $categories ) ) {
			return $category_rewrite;
		}

		foreach ( $categories as $category ) {
			$category_nicename = $this->get_category_parents( $category ) . $category->slug;
			$category_rewrite  = $this->add_category_rewrites( $category_rewrite, $category_nicename, $blog_prefix, $pagination_base );
		}

		return $category_rewrite;
	}

	/**
	 * Add rewrite rules for a single category hierarchy
	 *
	 * @param array  $category_rewrite
	 * @param string $category_nicename
	 * @param string $blog_prefix
	 * @param string $pagination_base
	 * @return array
	 */
	private function add_category_rewrites( $category_rewrite, $category_nicename, $blog_prefix, $pagination_base ) {
		$category_rewrite[ $blog_prefix . '(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ]    = 'index.php?category_name=$matches[1]&feed=$matches[2]';
		$category_rewrite[ $blog_prefix . '(' . $category_nicename . ')/' . $pagination_base . '/?([0-9]{1,})/?$' ] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$category_rewrite[ $blog_prefix . '(' . $category_nicename . ')/?$' ]                                       = 'index.php?category_name=$matches[1]';

		return $category_rewrite;
	}

	/**
	 * Retrieve category parents path
	 *
	 * @param WP_Term $category
	 * @return string
	 */
	private function get_category_parents( $category ) {
		if ( $category->parent === $category->cat_ID || absint( $category->parent ) < 1 ) {
			return '';
		}

		$parents = get_category_parents( $category->parent, false, '/', true );
		return is_wp_error( $parents ) ? '' : $parents;
	}

	/**
	 * Multisite blog prefix support
	 *
	 * @return string
	 */
	private function get_blog_prefix() {
		$permalink_structure = get_option( 'permalink_structure' );
		if ( is_multisite() && ! is_subdomain_install() && is_main_site() && 0 === strpos( $permalink_structure, '/blog/' ) ) {
			return 'blog/';
		}

		return '';
	}
}
