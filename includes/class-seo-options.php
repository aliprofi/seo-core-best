<?php
/**
 * Options Manager for SEO Core Best
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Options {

	const OPTION_NAME = 'seo_core_best_settings';

	/**
	 * Get all plugin options merged with defaults
	 *
	 * @return array
	 */
	public static function get_all() {
		$defaults = self::get_defaults();
		$saved    = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Get a specific option value
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$options = self::get_all();
		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		$defaults = self::get_defaults();
		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Update an option or multiple options
	 *
	 * @param array $new_options
	 * @return bool
	 */
	public static function update_all( array $new_options ) {
		$current = self::get_all();
		$merged  = array_merge( $current, $new_options );
		return update_option( self::OPTION_NAME, $merged );
	}

	/**
	 * Default settings
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			// General
			'title_separator'            => '—',
			'org_name'                   => get_bloginfo( 'name' ),
			'org_logo'                   => '',
			'org_url'                    => home_url(),
			'strip_category_base'        => 0,
			'nofollow_external_links'    => 0,
			'open_external_blank'        => 0,
			'enable_breadcrumbs'         => 1,
			'breadcrumbs_separator'      => '—',

			// Titles & Meta Templates
			'homepage_title'             => '%sitename% %sep% %sitedesc%',
			'homepage_description'       => '',
			'post_title_template'        => '%title% %sep% %sitename%',
			'post_desc_template'         => '%excerpt%',
			'page_title_template'        => '%title% %sep% %sitename%',
			'page_desc_template'         => '%excerpt%',
			'category_title_template'    => '%term% %sep% %sitename%',
			'category_desc_template'     => '%term_description%',
			'tag_title_template'         => '%term% %sep% %sitename%',
			'tag_desc_template'          => '%term_description%',
			'author_title_template'      => '%name% %sep% %sitename%',
			'search_title_template'      => 'Результаты поиска по запросу «%searchquery%» %sep% %sitename%',
			'404_title_template'         => 'Страница не найдена %sep% %sitename%',

			// Custom Post Types & Taxonomies templates (auto-extended)
			'cpt_templates'              => array(
				'ali_sale'      => array(
					'title' => '%title% %sep% %sitename%',
					'desc'  => '%excerpt%',
				),
				'dwqa-question' => array(
					'title' => '%title% %sep% %sitename%',
					'desc'  => '%excerpt%',
				),
			),
			'tax_templates'              => array(),

			// Social / OpenGraph
			'enable_opengraph'           => 1,
			'enable_twitter_cards'       => 1,
			'default_og_image'           => '',
			'twitter_card_type'          => 'summary_large_image',
			'social_telegram'            => '',
			'social_vk'                  => '',
			'social_youtube'             => '',
			'social_twitter'             => '',
			'social_facebook'            => '',

			// Robots / Indexing
			'noindex_empty_taxonomies'   => 1,
			'noindex_search'             => 1,
			'noindex_archives_date'      => 1,
			'noindex_archives_author'    => 0,
			'noindex_paginated_pages'    => 0,

			// XML Sitemap
			'enable_sitemap'             => 1,
			'sitemap_items_per_page'     => 1000,
			'sitemap_post_types'         => array( 'post', 'page', 'ali_sale', 'dwqa-question' ),
			'sitemap_taxonomies'         => array( 'category' ),
			'sitemap_include_images'     => 1,

			// Redirections & 404 Monitor
			'enable_redirects'           => 1,
			'redirect_fallback_404'      => 0,
			'redirect_fallback_url'      => '',
			'keep_redirect_stats'        => 1,
			'enable_404_monitor'         => 1,
			'log_404_images'             => 1,

			// Instant Indexing (Google & IndexNow API)
			'enable_instant_indexing'    => 1,
			'google_json_key'            => '',
			'google_index_post_types'    => array( 'post', 'page', 'ali_sale', 'dwqa-question' ),
			'indexnow_api_key'           => '',
			'indexnow_post_types'        => array( 'post', 'page', 'ali_sale', 'dwqa-question' ),

			// Image SEO (Auto Alt & Title)
			'enable_auto_image_alt'      => 1,
			'auto_image_alt_template'    => '%title%',
			'enable_auto_image_title'    => 0,
			'auto_image_title_template'  => '%title%',

			// AI Assistant (DeepSeek & Mistral)
			'ai_provider'                => 'deepseek', // 'deepseek' or 'mistral'
			'deepseek_api_key'           => '',
			'deepseek_model'             => 'deepseek-chat',
			'mistral_api_key'            => '',
			'mistral_model'              => 'mistral-small-latest',

			// Rank Math Compatibility Mode
			'rank_math_compat_read'      => 1, // Fallback to rank_math_* meta if _seo_* is empty
			'rank_math_compat_write'     => 1, // Also update rank_math_* meta when saving _seo_*
		);
	}
}
