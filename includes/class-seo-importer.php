<?php
/**
 * Rank Math Importer (Database & JSON File)
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Importer {

	/**
	 * Check if Rank Math data exists in current WordPress Database
	 *
	 * @return array
	 */
	public static function detect_rank_math_in_db() {
		global $wpdb;

		$results = array(
			'has_data'        => false,
			'has_options'     => false,
			'has_redirects'   => false,
			'redirect_count'  => 0,
			'titles_found'    => false,
			'sitemap_found'   => false,
		);

		// Check options
		$titles_opt  = get_option( 'rank-math-options-titles' );
		$sitemap_opt = get_option( 'rank-math-options-sitemap' );
		$general_opt = get_option( 'rank-math-options-general' );

		if ( ! empty( $titles_opt ) || ! empty( $sitemap_opt ) || ! empty( $general_opt ) ) {
			$results['has_options']  = true;
			$results['titles_found'] = ! empty( $titles_opt );
			$results['sitemap_found']= ! empty( $sitemap_opt );
		}

		// Check redirections table
		$rm_redir_table = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $rm_redir_table ) ) === $rm_redir_table ) {
			$count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$rm_redir_table}" );
			if ( $count > 0 ) {
				$results['has_redirects']  = true;
				$results['redirect_count'] = $count;
			}
		}

		$results['has_data'] = $results['has_options'] || $results['has_redirects'];

		return $results;
	}

	/**
	 * Import settings and redirections directly from DB
	 *
	 * @return array Status and report
	 */
	public static function import_from_db() {
		global $wpdb;
		$imported_settings = 0;
		$imported_redirects = 0;

		$general_opt = get_option( 'rank-math-options-general', array() );
		$titles_opt  = get_option( 'rank-math-options-titles', array() );
		$sitemap_opt = get_option( 'rank-math-options-sitemap', array() );

		// Process settings
		$new_settings = self::map_rank_math_settings( $general_opt, $titles_opt, $sitemap_opt );
		if ( ! empty( $new_settings ) ) {
			Seo_Core_Options::update_all( $new_settings );
			$imported_settings = count( $new_settings );
			if ( isset( $new_settings['strip_category_base'] ) ) {
				flush_rewrite_rules();
			}
		}

		// Process redirections
		$rm_redir_table = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $rm_redir_table ) ) === $rm_redir_table ) {
			$rows = $wpdb->get_results( "SELECT * FROM {$rm_redir_table}", ARRAY_A );
			if ( ! empty( $rows ) ) {
				$redirs_to_insert = array();
				foreach ( $rows as $row ) {
					$parsed = self::parse_rank_math_redirect_row( $row );
					if ( ! empty( $parsed ) ) {
						foreach ( $parsed as $p ) {
							$redirs_to_insert[] = $p;
						}
					}
				}

				if ( ! empty( $redirs_to_insert ) ) {
					$imported_redirects = Seo_Core_Redirects::bulk_import( $redirs_to_insert );
				}
			}
		}

		return array(
			'success'            => true,
			'imported_settings'  => $imported_settings,
			'imported_redirects' => $imported_redirects,
			'message'            => sprintf(
				__( 'Успешно импортировано настроек: %d, 301-редиректов: %d.', 'seo-core-best' ),
				$imported_settings,
				$imported_redirects
			),
		);
	}

	/**
	 * Import from uploaded Rank Math JSON export file or string
	 *
	 * @param string|array $json_data
	 * @return array
	 */
	public static function import_from_json( $json_data ) {
		if ( is_string( $json_data ) ) {
			$data = json_decode( $json_data, true );
		} else {
			$data = $json_data;
		}

		if ( empty( $data ) || ! is_array( $data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Ошибка: Некорректный формат JSON файла.', 'seo-core-best' ),
			);
		}

		$general_opt = isset( $data['general'] ) ? $data['general'] : array();
		$titles_opt  = isset( $data['titles'] ) ? $data['titles'] : array();
		$sitemap_opt = isset( $data['sitemap'] ) ? $data['sitemap'] : array();
		$redirections= isset( $data['redirections'] ) ? $data['redirections'] : array();

		// Map and save settings
		$new_settings = self::map_rank_math_settings( $general_opt, $titles_opt, $sitemap_opt );
		$imported_settings = 0;
		if ( ! empty( $new_settings ) ) {
			Seo_Core_Options::update_all( $new_settings );
			$imported_settings = count( $new_settings );
		}

		// Import redirections
		$imported_redirects = 0;
		if ( ! empty( $redirections ) && is_array( $redirections ) ) {
			$redirs_to_insert = array();
			foreach ( $redirections as $row ) {
				$parsed = self::parse_rank_math_redirect_row( $row );
				if ( ! empty( $parsed ) ) {
					foreach ( $parsed as $p ) {
						$redirs_to_insert[] = $p;
					}
				}
			}

			if ( ! empty( $redirs_to_insert ) ) {
				$imported_redirects = Seo_Core_Redirects::bulk_import( $redirs_to_insert );
			}
		}

		return array(
			'success'            => true,
			'imported_settings'  => $imported_settings,
			'imported_redirects' => $imported_redirects,
			'message'            => sprintf(
				__( 'Файл JSON успешно обработан! Импортировано настроек: %d, 301-редиректов: %d.', 'seo-core-best' ),
				$imported_settings,
				$imported_redirects
			),
		);
	}

	/**
	 * Map Rank Math option structures to SeoCoreBest settings
	 *
	 * @param array $general
	 * @param array $titles
	 * @param array $sitemap
	 * @return array
	 */
	public static function map_rank_math_settings( $general, $titles, $sitemap ) {
		$settings = array();

		// General
		if ( isset( $general['strip_category_base'] ) ) {
			$settings['strip_category_base'] = ( 'on' === $general['strip_category_base'] ) ? 1 : 0;
		}
		if ( isset( $general['nofollow_external_links'] ) ) {
			$settings['nofollow_external_links'] = ( 'on' === $general['nofollow_external_links'] ) ? 1 : 0;
		}
		if ( isset( $general['new_window_external_links'] ) ) {
			$settings['open_external_blank'] = ( 'on' === $general['new_window_external_links'] ) ? 1 : 0;
		}
		if ( isset( $general['breadcrumbs'] ) ) {
			$settings['enable_breadcrumbs'] = ( 'on' === $general['breadcrumbs'] ) ? 1 : 0;
		}
		if ( ! empty( $general['breadcrumbs_separator'] ) ) {
			$settings['breadcrumbs_separator'] = sanitize_text_field( $general['breadcrumbs_separator'] );
		}

		// Organization / Knowledge Graph
		if ( ! empty( $titles['knowledgegraph_name'] ) ) {
			$settings['org_name'] = sanitize_text_field( $titles['knowledgegraph_name'] );
		}
		if ( ! empty( $titles['knowledgegraph_logo'] ) ) {
			$settings['org_logo'] = esc_url_raw( $titles['knowledgegraph_logo'] );
		}
		if ( ! empty( $titles['url'] ) ) {
			$settings['org_url'] = esc_url_raw( $titles['url'] );
		}

		// Titles
		if ( ! empty( $titles['title_separator'] ) ) {
			$settings['title_separator'] = sanitize_text_field( $titles['title_separator'] );
		}
		if ( ! empty( $titles['homepage_title'] ) ) {
			$settings['homepage_title'] = $titles['homepage_title'];
		}
		if ( isset( $titles['homepage_description'] ) ) {
			$settings['homepage_description'] = $titles['homepage_description'];
		}
		if ( ! empty( $titles['pt_post_title'] ) ) {
			$settings['post_title_template'] = $titles['pt_post_title'];
		}
		if ( ! empty( $titles['pt_post_description'] ) ) {
			$settings['post_desc_template'] = $titles['pt_post_description'];
		}
		if ( ! empty( $titles['pt_page_title'] ) ) {
			$settings['page_title_template'] = $titles['pt_page_title'];
		}
		if ( ! empty( $titles['pt_page_description'] ) ) {
			$settings['page_desc_template'] = $titles['pt_page_description'];
		}
		if ( ! empty( $titles['tax_category_title'] ) ) {
			$settings['category_title_template'] = $titles['tax_category_title'];
		}
		if ( ! empty( $titles['tax_category_description'] ) ) {
			$settings['category_desc_template'] = $titles['tax_category_description'];
		}
		if ( ! empty( $titles['tax_post_tag_title'] ) ) {
			$settings['tag_title_template'] = $titles['tax_post_tag_title'];
		}
		if ( ! empty( $titles['tax_post_tag_description'] ) ) {
			$settings['tag_desc_template'] = $titles['tax_post_tag_description'];
		}
		if ( ! empty( $titles['author_archive_title'] ) ) {
			$settings['author_title_template'] = $titles['author_archive_title'];
		}

		// Custom post types templates in Rank Math
		$cpt_templates = array();
		foreach ( $titles as $key => $val ) {
			if ( preg_match( '/^pt_([a-zA-Z0-9_-]+)_title$/', $key, $m ) ) {
				$pt = $m[1];
				if ( ! in_array( $pt, array( 'post', 'page', 'attachment' ) ) ) {
					$cpt_templates[ $pt ]['title'] = $val;
				}
			} elseif ( preg_match( '/^pt_([a-zA-Z0-9_-]+)_description$/', $key, $m ) ) {
				$pt = $m[1];
				if ( ! in_array( $pt, array( 'post', 'page', 'attachment' ) ) ) {
					$cpt_templates[ $pt ]['desc'] = $val;
				}
			}
		}
		if ( ! empty( $cpt_templates ) ) {
			$settings['cpt_templates'] = $cpt_templates;
		}

		// Custom taxonomies templates in Rank Math
		$tax_templates = array();
		foreach ( $titles as $key => $val ) {
			if ( preg_match( '/^tax_([a-zA-Z0-9_-]+)_title$/', $key, $m ) ) {
				$tx = $m[1];
				if ( ! in_array( $tx, array( 'category', 'post_tag', 'post_format' ) ) ) {
					$tax_templates[ $tx ]['title'] = $val;
				}
			} elseif ( preg_match( '/^tax_([a-zA-Z0-9_-]+)_description$/', $key, $m ) ) {
				$tx = $m[1];
				if ( ! in_array( $tx, array( 'category', 'post_tag', 'post_format' ) ) ) {
					$tax_templates[ $tx ]['desc'] = $val;
				}
			}
		}
		if ( ! empty( $tax_templates ) ) {
			$settings['tax_templates'] = $tax_templates;
		}

		// Sitemap
		if ( isset( $sitemap['items_per_page'] ) && intval( $sitemap['items_per_page'] ) > 0 ) {
			$settings['sitemap_items_per_page'] = intval( $sitemap['items_per_page'] );
		}
		if ( isset( $sitemap['include_images'] ) ) {
			$settings['sitemap_include_images'] = ( 'on' === $sitemap['include_images'] ) ? 1 : 0;
		}

		$sitemap_pts = array();
		foreach ( $sitemap as $k => $v ) {
			if ( 'on' === $v && preg_match( '/^pt_([a-zA-Z0-9_-]+)_sitemap$/', $k, $m ) ) {
				if ( 'attachment' !== $m[1] ) {
					$sitemap_pts[] = $m[1];
				}
			}
		}
		if ( ! empty( $sitemap_pts ) ) {
			$settings['sitemap_post_types'] = array_unique( $sitemap_pts );
		}

		$sitemap_taxes = array();
		foreach ( $sitemap as $k => $v ) {
			if ( 'on' === $v && preg_match( '/^tax_([a-zA-Z0-9_-]+)_sitemap$/', $k, $m ) ) {
				$sitemap_taxes[] = $m[1];
			}
		}
		if ( ! empty( $sitemap_taxes ) ) {
			$settings['sitemap_taxonomies'] = array_unique( $sitemap_taxes );
		}

		return $settings;
	}

	/**
	 * Parse a single Rank Math redirection row (which can have serialized sources)
	 *
	 * @param array $row
	 * @return array Array of normalized redirect rules
	 */
	public static function parse_rank_math_redirect_row( array $row ) {
		$results = array();

		$url_to      = isset( $row['url_to'] ) ? trim( $row['url_to'] ) : '';
		$header_code = isset( $row['header_code'] ) ? intval( $row['header_code'] ) : 301;
		$hits        = isset( $row['hits'] ) ? intval( $row['hits'] ) : 0;
		$status      = isset( $row['status'] ) ? $row['status'] : 'active';

		if ( empty( $url_to ) ) {
			return $results;
		}

		$sources_raw = isset( $row['sources'] ) ? $row['sources'] : '';
		$sources     = maybe_unserialize( $sources_raw );

		if ( is_array( $sources ) ) {
			foreach ( $sources as $src ) {
				if ( ! empty( $src['pattern'] ) ) {
					$comp = isset( $src['comparison'] ) ? $src['comparison'] : 'exact';
					$type = 'exact';
					if ( 'regex' === $comp ) {
						$type = 'regex';
					} elseif ( 'contains' === $comp ) {
						$type = 'contains';
					}

					$results[] = array(
						'from'   => trim( $src['pattern'] ),
						'to'     => $url_to,
						'code'   => $header_code,
						'type'   => $type,
						'hits'   => $hits,
						'status' => $status,
					);
				}
			}
		} elseif ( is_string( $sources_raw ) && ! empty( $sources_raw ) ) {
			// Fallback regex pattern match if serialize fails
			if ( preg_match( '/s:7:"pattern";s:\d+:"([^"]+)"/', $sources_raw, $m ) ) {
				$pattern = $m[1];
				$comp = 'exact';
				if ( preg_match( '/s:10:"comparison";s:\d+:"([^"]+)"/', $sources_raw, $cm ) ) {
					$comp = $cm[1];
				}
				$results[] = array(
					'from'   => trim( $pattern ),
					'to'     => $url_to,
					'code'   => $header_code,
					'type'   => ( 'regex' === $comp ) ? 'regex' : 'exact',
					'hits'   => $hits,
					'status' => $status,
				);
			}
		}

		return $results;
	}
}
