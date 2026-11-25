<?php
/**
 * XML Sitemap Generator
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Sitemap {

	public function __construct() {
		if ( ! Seo_Core_Options::get( 'enable_sitemap', 1 ) ) {
			return;
		}

		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render_sitemap' ) );

		// Clear transient on post/term update
		add_action( 'save_post', array( $this, 'clear_cache' ) );
		add_action( 'edited_term', array( $this, 'clear_cache' ) );
	}

	/**
	 * Add rewrite rules for XML sitemaps
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^sitemap_index\.xml$', 'index.php?seo_sitemap=index', 'top' );
		add_rewrite_rule( '^([a-zA-Z0-9_-]+)-sitemap([0-9]*)\.xml$', 'index.php?seo_sitemap=$matches[1]&seo_sitemap_page=$matches[2]', 'top' );
	}

	/**
	 * Register query variables
	 *
	 * @param array $vars
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'seo_sitemap';
		$vars[] = 'seo_sitemap_page';
		return $vars;
	}

	/**
	 * Render sitemap XML if requested
	 */
	public function render_sitemap() {
		$sitemap = get_query_var( 'seo_sitemap' );
		if ( empty( $sitemap ) ) {
			return;
		}

		$page = get_query_var( 'seo_sitemap_page' );
		$page = ! empty( $page ) ? max( 1, intval( $page ) ) : 1;

		if ( 'index' === $sitemap ) {
			$this->render_index();
			exit;
		}

		$this->render_type_sitemap( $sitemap, $page );
		exit;
	}

	/**
	 * Render Sitemap Index (/sitemap_index.xml)
	 */
	protected function render_index() {
		$this->send_headers();

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// Post Types
		$pts = Seo_Core_Options::get( 'sitemap_post_types', array( 'post', 'page' ) );
		foreach ( $pts as $pt ) {
			if ( ! post_type_exists( $pt ) ) {
				continue;
			}
			$count = $this->count_indexable_posts( $pt );
			if ( $count <= 0 ) {
				continue;
			}

			$limit = Seo_Core_Options::get( 'sitemap_items_per_page', 1000 );
			$total_pages = ceil( $count / $limit );

			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$page_suffix = $i > 1 ? $i : '';
				$loc = home_url( "/{$pt}-sitemap{$page_suffix}.xml" );
				$lastmod = $this->get_last_modified_date( $pt );

				echo "  <sitemap>\n";
				echo "    <loc>" . esc_url( $loc ) . "</loc>\n";
				if ( ! empty( $lastmod ) ) {
					echo "    <lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
				}
				echo "  </sitemap>\n";
			}
		}

		// Taxonomies
		$taxes = Seo_Core_Options::get( 'sitemap_taxonomies', array( 'category' ) );
		foreach ( $taxes as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$loc = home_url( "/{$tax}-sitemap.xml" );
			echo "  <sitemap>\n";
			echo "    <loc>" . esc_url( $loc ) . "</loc>\n";
			echo "    <lastmod>" . date( 'c' ) . "</lastmod>\n";
			echo "  </sitemap>\n";
		}

		echo '</sitemapindex>';
	}

	/**
	 * Render individual type sitemap
	 *
	 * @param string $type
	 * @param int    $page
	 */
	protected function render_type_sitemap( $type, $page = 1 ) {
		$this->send_headers();

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		$limit = Seo_Core_Options::get( 'sitemap_items_per_page', 1000 );
		$offset = ( $page - 1 ) * $limit;

		if ( post_type_exists( $type ) ) {
			$posts = get_posts( array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
			) );

			foreach ( $posts as $post_id ) {
				// Check noindex
				if ( $this->is_post_noindexed( $post_id ) ) {
					continue;
				}

				$loc     = get_permalink( $post_id );
				$lastmod = get_the_modified_date( 'c', $post_id );

				echo "  <url>\n";
				echo "    <loc>" . esc_url( $loc ) . "</loc>\n";
				echo "    <lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";

				// Images
				if ( Seo_Core_Options::get( 'sitemap_include_images', 1 ) && has_post_thumbnail( $post_id ) ) {
					$img_url = get_the_post_thumbnail_url( $post_id, 'full' );
					if ( ! empty( $img_url ) ) {
						echo "    <image:image>\n";
						echo "      <image:loc>" . esc_url( $img_url ) . "</image:loc>\n";
						echo "    </image:image>\n";
					}
				}

				echo "  </url>\n";
			}
		} elseif ( taxonomy_exists( $type ) ) {
			$terms = get_terms( array(
				'taxonomy'   => $type,
				'hide_empty' => true,
				'number'     => $limit,
				'offset'     => $offset,
			) );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$loc = get_term_link( $term );
					echo "  <url>\n";
					echo "    <loc>" . esc_url( $loc ) . "</loc>\n";
					echo "    <lastmod>" . date( 'c' ) . "</lastmod>\n";
					echo "  </url>\n";
				}
			}
		}

		echo '</urlset>';
	}

	/**
	 * Send XML Headers
	 */
	protected function send_headers() {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );
	}

	/**
	 * Check if post is marked noindex
	 *
	 * @param int $post_id
	 * @return bool
	 */
	protected function is_post_noindexed( $post_id ) {
		$robots = get_post_meta( $post_id, '_seo_robots', true );
		if ( empty( $robots ) ) {
			$robots = get_post_meta( $post_id, 'rank_math_robots', true );
		}

		if ( is_array( $robots ) ) {
			return in_array( 'noindex', $robots, true );
		}

		if ( is_string( $robots ) ) {
			return false !== stripos( $robots, 'noindex' );
		}

		return false;
	}

	/**
	 * Count indexable posts for post type
	 *
	 * @param string $post_type
	 * @return int
	 */
	protected function count_indexable_posts( $post_type ) {
		$counts = wp_count_posts( $post_type );
		return isset( $counts->publish ) ? intval( $counts->publish ) : 0;
	}

	/**
	 * Get last modified date for post type
	 *
	 * @param string $post_type
	 * @return string
	 */
	protected function get_last_modified_date( $post_type ) {
		global $wpdb;
		$date = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_modified FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_modified DESC LIMIT 1",
			$post_type
		) );

		return ! empty( $date ) ? date( 'c', strtotime( $date ) ) : date( 'c' );
	}

	/**
	 * Clear sitemap cache
	 */
	public function clear_cache() {
		delete_transient( 'seo_core_sitemap_index' );
	}
}
