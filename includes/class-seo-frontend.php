<?php
/**
 * Frontend Head Tags & Metadata Output
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Frontend {

	public function __construct() {
		// Document Title
		add_filter( 'pre_get_document_title', array( $this, 'get_document_title' ), 15 );
		add_filter( 'wp_title', array( $this, 'get_document_title' ), 15 );

		// Head Tags
		add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );

		// Remove default WP canonical & robots if active
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_robots', 1 );

		// Optional filters
		if ( Seo_Core_Options::get( 'nofollow_external_links' ) || Seo_Core_Options::get( 'open_external_blank' ) ) {
			add_filter( 'the_content', array( $this, 'filter_content_links' ), 99 );
		}

		// Auto Image SEO (Alt and Title)
		if ( Seo_Core_Options::get( 'enable_auto_image_alt', 1 ) || Seo_Core_Options::get( 'enable_auto_image_title', 0 ) ) {
			add_filter( 'the_content', array( $this, 'filter_image_seo' ), 85 );
			add_filter( 'post_thumbnail_html', array( $this, 'filter_thumbnail_seo' ), 85, 5 );
		}
	}

	/**
	 * Compute current document title
	 *
	 * @param string $default_title
	 * @return string
	 */
	public function get_document_title( $default_title = '' ) {
		if ( is_front_page() || is_home() ) {
			$tpl = Seo_Core_Options::get( 'homepage_title', '%sitename% %sep% %sitedesc%' );
			return Seo_Core_Variables::replace( $tpl );
		}

		if ( is_singular() ) {
			global $post;
			if ( ! $post ) {
				return $default_title;
			}

			// Check custom title
			$title = get_post_meta( $post->ID, '_seo_title', true );
			if ( empty( $title ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
				$title = get_post_meta( $post->ID, 'rank_math_title', true );
			}

			if ( ! empty( $title ) ) {
				return Seo_Core_Variables::replace( $title, $post );
			}

			// Fallback to template
			$pt = get_post_type( $post );
			$tpl = '';
			if ( 'post' === $pt ) {
				$tpl = Seo_Core_Options::get( 'post_title_template', '%title% %sep% %sitename%' );
			} elseif ( 'page' === $pt ) {
				$tpl = Seo_Core_Options::get( 'page_title_template', '%title% %sep% %sitename%' );
			} else {
				$cpt_tpls = Seo_Core_Options::get( 'cpt_templates', array() );
				if ( isset( $cpt_tpls[ $pt ]['title'] ) && ! empty( $cpt_tpls[ $pt ]['title'] ) ) {
					$tpl = $cpt_tpls[ $pt ]['title'];
				} else {
					$tpl = '%title% %sep% %sitename%';
				}
			}

			return Seo_Core_Variables::replace( $tpl, $post );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term || is_wp_error( $term ) ) {
				return $default_title;
			}

			$term_id = $term->term_id;
			$title   = get_term_meta( $term_id, '_seo_title', true );
			if ( empty( $title ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
				$title = get_term_meta( $term_id, 'rank_math_title', true );
			}

			if ( ! empty( $title ) ) {
				return Seo_Core_Variables::replace( $title, null, $term );
			}

			$tax = $term->taxonomy;
			if ( 'category' === $tax ) {
				$tpl = Seo_Core_Options::get( 'category_title_template', '%term% %sep% %sitename%' );
			} elseif ( 'post_tag' === $tax ) {
				$tpl = Seo_Core_Options::get( 'tag_title_template', '%term% %sep% %sitename%' );
			} else {
				$tax_tpls = Seo_Core_Options::get( 'tax_templates', array() );
				if ( isset( $tax_tpls[ $tax ]['title'] ) && ! empty( $tax_tpls[ $tax ]['title'] ) ) {
					$tpl = $tax_tpls[ $tax ]['title'];
				} else {
					$tpl = '%term% %sep% %sitename%';
				}
			}

			return Seo_Core_Variables::replace( $tpl, null, $term );
		}

		if ( is_post_type_archive() ) {
			$pt = get_query_var( 'post_type' );
			if ( is_array( $pt ) ) {
				$pt = reset( $pt );
			}
			$cpt_tpls = Seo_Core_Options::get( 'cpt_templates', array() );
			if ( isset( $cpt_tpls[ $pt ]['archive_title'] ) && ! empty( $cpt_tpls[ $pt ]['archive_title'] ) ) {
				$tpl = $cpt_tpls[ $pt ]['archive_title'];
			} else {
				$pt_obj = get_post_type_object( $pt );
				$name   = $pt_obj ? $pt_obj->labels->name : $pt;
				$tpl    = $name . ' %sep% %sitename%';
			}
			return Seo_Core_Variables::replace( $tpl );
		}

		if ( is_author() ) {
			$tpl = Seo_Core_Options::get( 'author_title_template', '%name% %sep% %sitename%' );
			return Seo_Core_Variables::replace( $tpl );
		}

		if ( is_search() ) {
			$tpl = Seo_Core_Options::get( 'search_title_template', 'Результаты поиска по запросу «%searchquery%» %sep% %sitename%' );
			return Seo_Core_Variables::replace( $tpl );
		}

		if ( is_404() ) {
			$tpl = Seo_Core_Options::get( '404_title_template', 'Страница не найдена %sep% %sitename%' );
			return Seo_Core_Variables::replace( $tpl );
		}

		return $default_title;
	}

	/**
	 * Compute current meta description
	 *
	 * @return string
	 */
	public function get_meta_description() {
		if ( is_front_page() || is_home() ) {
			$desc = Seo_Core_Options::get( 'homepage_description', '' );
			if ( ! empty( $desc ) ) {
				return Seo_Core_Variables::replace( $desc );
			}
			return get_bloginfo( 'description' );
		}

		if ( is_singular() ) {
			global $post;
			if ( ! $post ) {
				return '';
			}

			$desc = get_post_meta( $post->ID, '_seo_description', true );
			if ( empty( $desc ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
				$desc = get_post_meta( $post->ID, 'rank_math_description', true );
			}

			if ( ! empty( $desc ) ) {
				return Seo_Core_Variables::replace( $desc, $post );
			}

			// Template fallback
			$pt = get_post_type( $post );
			$tpl = '';
			if ( 'post' === $pt ) {
				$tpl = Seo_Core_Options::get( 'post_desc_template', '%excerpt%' );
			} elseif ( 'page' === $pt ) {
				$tpl = Seo_Core_Options::get( 'page_desc_template', '%excerpt%' );
			} else {
				$cpt_tpls = Seo_Core_Options::get( 'cpt_templates', array() );
				if ( isset( $cpt_tpls[ $pt ]['desc'] ) && ! empty( $cpt_tpls[ $pt ]['desc'] ) ) {
					$tpl = $cpt_tpls[ $pt ]['desc'];
				}
			}

			if ( ! empty( $tpl ) ) {
				return Seo_Core_Variables::replace( $tpl, $post );
			}

			return Seo_Core_Variables::get_post_excerpt( $post );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term || is_wp_error( $term ) ) {
				return '';
			}

			$term_id = $term->term_id;
			$desc    = get_term_meta( $term_id, '_seo_description', true );
			if ( empty( $desc ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
				$desc = get_term_meta( $term_id, 'rank_math_description', true );
			}

			if ( ! empty( $desc ) ) {
				return Seo_Core_Variables::replace( $desc, null, $term );
			}

			$tax = $term->taxonomy;
			if ( 'category' === $tax ) {
				$tpl = Seo_Core_Options::get( 'category_desc_template', '%term_description%' );
			} elseif ( 'post_tag' === $tax ) {
				$tpl = Seo_Core_Options::get( 'tag_desc_template', '%term_description%' );
			} else {
				$tax_tpls = Seo_Core_Options::get( 'tax_templates', array() );
				if ( isset( $tax_tpls[ $tax ]['desc'] ) && ! empty( $tax_tpls[ $tax ]['desc'] ) ) {
					$tpl = $tax_tpls[ $tax ]['desc'];
				} else {
					$tpl = '%term_description%';
				}
			}

			return Seo_Core_Variables::replace( $tpl, null, $term );
		}

		if ( is_post_type_archive() ) {
			$pt = get_query_var( 'post_type' );
			if ( is_array( $pt ) ) {
				$pt = reset( $pt );
			}
			$cpt_tpls = Seo_Core_Options::get( 'cpt_templates', array() );
			if ( isset( $cpt_tpls[ $pt ]['archive_desc'] ) && ! empty( $cpt_tpls[ $pt ]['archive_desc'] ) ) {
				return Seo_Core_Variables::replace( $cpt_tpls[ $pt ]['archive_desc'] );
			}
			return '';
		}

		return '';
	}

	/**
	 * Compute Canonical URL
	 *
	 * @return string
	 */
	public function get_canonical_url() {
		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_singular() ) {
			global $post;
			if ( $post ) {
				$custom = get_post_meta( $post->ID, '_seo_canonical', true );
				if ( empty( $custom ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
					$custom = get_post_meta( $post->ID, 'rank_math_canonical_url', true );
				}
				if ( ! empty( $custom ) ) {
					return esc_url( $custom );
				}
				$url = get_permalink( $post->ID );
				$paged = get_query_var( 'page' );
				if ( $paged > 1 ) {
					$url = trailingslashit( $url ) . user_trailingslashit( $paged, 'single_paged' );
				}
				return $url;
			}
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				$url = get_term_link( $term );
				$paged = get_query_var( 'paged' );
				if ( $paged > 1 ) {
					$url = trailingslashit( $url ) . 'page/' . $paged . '/';
				}
				return $url;
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author ) {
				return get_author_posts_url( $author->ID );
			}
		}

		return '';
	}

	/**
	 * Compute Robots string
	 *
	 * @return string
	 */
	public function get_robots() {
		$robots = array( 'index', 'follow' );

		// Check global noindex options
		if ( is_search() && Seo_Core_Options::get( 'noindex_search', 1 ) ) {
			return 'noindex, follow';
		}

		if ( is_404() ) {
			return 'noindex, nofollow';
		}

		if ( is_date() && Seo_Core_Options::get( 'noindex_archives_date', 1 ) ) {
			return 'noindex, follow';
		}

		if ( is_author() && Seo_Core_Options::get( 'noindex_archives_author', 0 ) ) {
			return 'noindex, follow';
		}

		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' );
		if ( $paged > 1 && Seo_Core_Options::get( 'noindex_paginated_pages', 0 ) ) {
			return 'noindex, follow';
		}

		if ( is_singular() ) {
			global $post;
			if ( $post ) {
				$meta_robots = get_post_meta( $post->ID, '_seo_robots', true );
				if ( empty( $meta_robots ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
					$rm_robots = get_post_meta( $post->ID, 'rank_math_robots', true );
					if ( is_array( $rm_robots ) ) {
						$meta_robots = implode( ', ', $rm_robots );
					} elseif ( is_string( $rm_robots ) ) {
						$meta_robots = $rm_robots;
					}
				}

				if ( ! empty( $meta_robots ) ) {
					return is_array( $meta_robots ) ? implode( ', ', $meta_robots ) : $meta_robots;
				}
			}
		}

		return implode( ', ', $robots );
	}

	/**
	 * Output SEO Tags in <head>
	 */
	public function output_meta_tags() {
		echo "\n<!-- SEO Core Best Plugin -->\n";

		// Description
		$desc = $this->get_meta_description();
		if ( ! empty( $desc ) ) {
			echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '" />' . "\n";
		}

		// Robots
		$robots = $this->get_robots();
		if ( ! empty( $robots ) ) {
			echo '<meta name="robots" content="' . esc_attr( $robots ) . '" />' . "\n";
		}

		// Canonical
		$canonical = $this->get_canonical_url();
		if ( ! empty( $canonical ) ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		// OpenGraph & Twitter
		if ( Seo_Core_Options::get( 'enable_opengraph', 1 ) ) {
			$this->output_opengraph( $canonical, $desc );
		}

		// Schema.org Structured Data (JSON-LD)
		$this->output_schema_json_ld( $canonical, $desc );

		echo "<!-- /SEO Core Best Plugin -->\n\n";
	}

	/**
	 * Output OpenGraph and Twitter Meta Tags
	 *
	 * @param string $canonical
	 * @param string $desc
	 */
	protected function output_opengraph( $canonical, $desc ) {
		$title = $this->get_document_title();
		$site_name = get_bloginfo( 'name' );
		$og_type = is_singular() ? 'article' : 'website';

		$image = '';
		if ( is_singular() ) {
			global $post;
			if ( $post && has_post_thumbnail( $post->ID ) ) {
				$image = get_the_post_thumbnail_url( $post->ID, 'large' );
			}
			// Fallback to first image in post body
			if ( empty( $image ) && $post && ! empty( $post->post_content ) ) {
				if ( preg_match( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $post->post_content, $first_img ) ) {
					$image = $first_img[1];
				}
			}
		}
		if ( empty( $image ) ) {
			$image = Seo_Core_Options::get( 'default_og_image', '' );
		}
		// Fallback to custom logo or site icon
		if ( empty( $image ) ) {
			$custom_logo_id = get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) {
				$image = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			} elseif ( function_exists( 'get_site_icon_url' ) ) {
				$image = get_site_icon_url( 512 );
			}
		}

		echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( ! empty( $desc ) ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		if ( ! empty( $canonical ) ) {
			echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
		}
		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
		if ( ! empty( $image ) ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}

		if ( Seo_Core_Options::get( 'enable_twitter_cards', 1 ) ) {
			$card_type = Seo_Core_Options::get( 'twitter_card_type', 'summary_large_image' );
			echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
			if ( ! empty( $desc ) ) {
				echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
			}
			if ( ! empty( $image ) ) {
				echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
			}
			$tw_handle = Seo_Core_Options::get( 'social_twitter', '' );
			if ( ! empty( $tw_handle ) ) {
				$tw_handle = '@' . ltrim( preg_replace( '#^https?://(www\.)?twitter\.com/#i', '', $tw_handle ), '@' );
				echo '<meta name="twitter:site" content="' . esc_attr( $tw_handle ) . '" />' . "\n";
				echo '<meta name="twitter:creator" content="' . esc_attr( $tw_handle ) . '" />' . "\n";
			}
		}
	}

	/**
	 * Output Schema.org Structured Data (JSON-LD)
	 *
	 * @param string $canonical
	 * @param string $desc
	 */
	protected function output_schema_json_ld( $canonical, $desc ) {
		$site_url  = home_url( '/' );
		$site_name = get_bloginfo( 'name' );
		$org_name  = Seo_Core_Options::get( 'org_name', $site_name );
		$org_logo  = Seo_Core_Options::get( 'org_logo', '' );

		if ( empty( $org_logo ) ) {
			$custom_logo_id = get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) {
				$org_logo = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			} elseif ( function_exists( 'get_site_icon_url' ) ) {
				$org_logo = get_site_icon_url( 512 );
			}
		}

		$graphs = array();

		// 1. WebSite & Organization
		$graphs[] = array(
			'@type' => 'WebSite',
			'@id'   => $site_url . '#website',
			'url'   => $site_url,
			'name'  => $site_name,
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => $site_url . '?s={search_term_string}',
				'query-input' => 'required name=search_term_string',
			),
		);

		$org_data = array(
			'@type' => 'Organization',
			'@id'   => $site_url . '#organization',
			'name'  => $org_name,
			'url'   => $site_url,
		);
		if ( ! empty( $org_logo ) ) {
			$org_data['logo'] = $org_logo;
		}

		// Social profiles sameAs for Google Knowledge Graph
		$same_as = array();
		$social_tg = Seo_Core_Options::get( 'social_telegram', '' );
		if ( ! empty( $social_tg ) ) {
			$same_as[] = ( strpos( $social_tg, 'http' ) === 0 ) ? $social_tg : 'https://t.me/' . ltrim( $social_tg, '@' );
		}
		$social_vk = Seo_Core_Options::get( 'social_vk', '' );
		if ( ! empty( $social_vk ) ) {
			$same_as[] = $social_vk;
		}
		$social_yt = Seo_Core_Options::get( 'social_youtube', '' );
		if ( ! empty( $social_yt ) ) {
			$same_as[] = $social_yt;
		}
		$social_tw = Seo_Core_Options::get( 'social_twitter', '' );
		if ( ! empty( $social_tw ) ) {
			$same_as[] = ( strpos( $social_tw, 'http' ) === 0 ) ? $social_tw : 'https://twitter.com/' . ltrim( $social_tw, '@' );
		}
		$social_fb = Seo_Core_Options::get( 'social_facebook', '' );
		if ( ! empty( $social_fb ) ) {
			$same_as[] = $social_fb;
		}
		if ( ! empty( $same_as ) ) {
			$org_data['sameAs'] = array_values( array_unique( $same_as ) );
		}

		$graphs[] = $org_data;

		// 2. Article Schema for singular posts
		if ( is_singular() ) {
			global $post;
			if ( $post ) {
				$post_title = $this->get_document_title();
				$article_data = array(
					'@type'            => 'Article',
					'@id'              => ( $canonical ? $canonical : get_permalink( $post->ID ) ) . '#article',
					'isPartOf'         => array( '@id' => ( $canonical ? $canonical : get_permalink( $post->ID ) ) ),
					'headline'         => $post_title,
					'datePublished'    => get_the_date( 'c', $post ),
					'dateModified'     => get_the_modified_date( 'c', $post ),
					'mainEntityOfPage' => ( $canonical ? $canonical : get_permalink( $post->ID ) ),
					'publisher'        => array( '@id' => $site_url . '#organization' ),
				);

				if ( ! empty( $desc ) ) {
					$article_data['description'] = $desc;
				}

				// Find image for Schema
				$post_img = '';
				if ( has_post_thumbnail( $post->ID ) ) {
					$post_img = get_the_post_thumbnail_url( $post->ID, 'large' );
				} elseif ( ! empty( $post->post_content ) && preg_match( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $post->post_content, $first_img ) ) {
					$post_img = $first_img[1];
				}
				if ( ! empty( $post_img ) ) {
					$article_data['image'] = $post_img;
				}

				$author_id = $post->post_author;
				if ( $author_id ) {
					$article_data['author'] = array(
						'@type' => 'Person',
						'name'  => get_the_author_meta( 'display_name', $author_id ),
						'url'   => get_author_posts_url( $author_id ),
					);
				}

				$graphs[] = $article_data;
			}
		}

		if ( ! empty( $graphs ) ) {
			$json_ld = array(
				'@context' => 'https://schema.org',
				'@graph'   => $graphs,
			);
			echo '<script type="application/ld+json" class="seo-core-schema">' . "\n";
			echo wp_json_encode( $json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
			echo '</script>' . "\n";
		}
	}

	/**
	 * Filter links in post content to add nofollow or target="_blank" to external links
	 *
	 * @param string $content
	 * @return string
	 */
	public function filter_content_links( $content ) {
		if ( empty( $content ) || is_admin() || is_feed() ) {
			return $content;
		}

		$nofollow = Seo_Core_Options::get( 'nofollow_external_links', 0 );
		$blank    = Seo_Core_Options::get( 'open_external_blank', 0 );

		if ( ! $nofollow && ! $blank ) {
			return $content;
		}

		$site_url = home_url();
		$site_host = wp_parse_url( $site_url, PHP_URL_HOST );

		return preg_replace_callback( '/<a\s+([^>]+)>/i', function( $matches ) use ( $site_host, $nofollow, $blank ) {
			$tag_attr = $matches[1];

			if ( ! preg_match( '/href=[\'"]([^\'"]+)[\'"]/i', $tag_attr, $href_match ) ) {
				return $matches[0];
			}

			$href = $href_match[1];
			$host = wp_parse_url( $href, PHP_URL_HOST );

			// Internal link or relative
			if ( empty( $host ) || strcasecmp( $host, $site_host ) === 0 ) {
				return $matches[0];
			}

			// External link
			if ( $blank && false === stripos( $tag_attr, 'target=' ) ) {
				$tag_attr .= ' target="_blank"';
			}

			if ( $nofollow ) {
				if ( preg_match( '/rel=[\'"]([^\'"]+)[\'"]/i', $tag_attr, $rel_match ) ) {
					$rel = $rel_match[1];
					if ( false === stripos( $rel, 'nofollow' ) ) {
						$new_rel = trim( $rel . ' nofollow noopener' );
						$tag_attr = str_replace( $rel_match[0], 'rel="' . esc_attr( $new_rel ) . '"', $tag_attr );
					}
				} else {
					$tag_attr .= ' rel="nofollow noopener"';
				}
			}

			return '<a ' . trim( $tag_attr ) . '>';
		}, $content );
	}

	/**
	 * Automatically add or fill Alt and Title attributes for images in content
	 *
	 * @param string $content
	 * @return string
	 */
	public function filter_image_seo( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		$auto_alt   = Seo_Core_Options::get( 'enable_auto_image_alt', 1 );
		$auto_title = Seo_Core_Options::get( 'enable_auto_image_title', 0 );

		if ( ! $auto_alt && ! $auto_title ) {
			return $content;
		}

		$alt_tpl   = Seo_Core_Options::get( 'auto_image_alt_template', '%title%' );
		$title_tpl = Seo_Core_Options::get( 'auto_image_title_template', '%title%' );

		global $post;
		$post_obj   = $post instanceof WP_Post ? $post : null;
		$post_title = $post_obj ? get_the_title( $post_obj ) : '';

		$img_counter = 0;

		return preg_replace_callback( '/<img\b([^>]*)>/i', function( $matches ) use ( &$img_counter, $auto_alt, $auto_title, $alt_tpl, $title_tpl, $post_obj, $post_title ) {
			$img_counter++;
			$attrs = $matches[1];

			// Extract filename from src
			$filename = '';
			if ( preg_match( '/src=[\'"]([^\'"]+)[\'"]/i', $attrs, $src_match ) ) {
				$path_info = pathinfo( wp_parse_url( $src_match[1], PHP_URL_PATH ) );
				if ( ! empty( $path_info['filename'] ) ) {
					// Clean up filename (replace dashes/underscores with spaces)
					$filename = trim( preg_replace( '/[-_+]+/', ' ', $path_info['filename'] ) );
				}
			}

			// 1. Process ALT attribute
			if ( $auto_alt ) {
				$has_alt = preg_match( '/\balt=[\'"]([^\'"]*)[\'"]/i', $attrs, $alt_match );
				$alt_val = $has_alt ? trim( $alt_match[1] ) : '';

				if ( ! $has_alt || empty( $alt_val ) ) {
					$gen_alt = str_replace( array( '%title%', '%filename%', '%image_name%', '%count%' ), array( $post_title, $filename, $filename, $img_counter ), $alt_tpl );
					if ( $post_obj ) {
						$gen_alt = Seo_Core_Variables::replace( $gen_alt, $post_obj );
					}
					$gen_alt = esc_attr( trim( $gen_alt ) );

					if ( $has_alt ) {
						$attrs = preg_replace( '/\balt=[\'"][^\'"]*[\'"]/i', 'alt="' . $gen_alt . '"', $attrs );
					} else {
						$attrs .= ' alt="' . $gen_alt . '"';
					}
				}
			}

			// 2. Process TITLE attribute
			if ( $auto_title ) {
				$has_title = preg_match( '/\btitle=[\'"]([^\'"]*)[\'"]/i', $attrs, $title_match );
				$title_val = $has_title ? trim( $title_match[1] ) : '';

				if ( ! $has_title || empty( $title_val ) ) {
					$gen_title = str_replace( array( '%title%', '%filename%', '%image_name%', '%count%' ), array( $post_title, $filename, $filename, $img_counter ), $title_tpl );
					if ( $post_obj ) {
						$gen_title = Seo_Core_Variables::replace( $gen_title, $post_obj );
					}
					$gen_title = esc_attr( trim( $gen_title ) );

					if ( $has_title ) {
						$attrs = preg_replace( '/\btitle=[\'"][^\'"]*[\'"]/i', 'title="' . $gen_title . '"', $attrs );
					} else {
						$attrs .= ' title="' . $gen_title . '"';
					}
				}
			}

			return '<img ' . trim( $attrs ) . '>';
		}, $content );
	}

	/**
	 * Automatically add or fill Alt for post featured thumbnail
	 */
	public function filter_thumbnail_seo( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		if ( empty( $html ) ) {
			return $html;
		}

		if ( ! Seo_Core_Options::get( 'enable_auto_image_alt', 1 ) ) {
			return $html;
		}

		if ( preg_match( '/\balt=[\'"]\s*[\'"]/i', $html ) || ! preg_match( '/\balt=/i', $html ) ) {
			$title = get_the_title( $post_id );
			$alt_tpl = Seo_Core_Options::get( 'auto_image_alt_template', '%title%' );
			$gen_alt = esc_attr( trim( str_replace( '%title%', $title, $alt_tpl ) ) );

			if ( preg_match( '/\balt=[\'"]\s*[\'"]/i', $html ) ) {
				$html = preg_replace( '/\balt=[\'"]\s*[\'"]/i', 'alt="' . $gen_alt . '"', $html );
			} else {
				$html = str_replace( '<img ', '<img alt="' . $gen_alt . '" ', $html );
			}
		}

		return $html;
	}
}
