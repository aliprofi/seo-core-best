<?php
/**
 * Comprehensive Dynamic Variables and Shortcodes Replacer
 *
 * Full compatibility with Rank Math variable formats (%org_name%, %tag%, %date(format)%, %customfield(name)%, etc.)
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Variables {

	/**
	 * Replace SEO snippet variables (%title%, %org_name%, %tag%, %sep%, etc.) and shortcodes
	 *
	 * @param string       $string String with variables
	 * @param WP_Post|null $post   Post object if applicable
	 * @param mixed|null   $term   Term object if taxonomy
	 * @return string
	 */
	public static function replace( $string, $post = null, $term = null ) {
		if ( empty( $string ) || ! is_string( $string ) ) {
			return '';
		}

		// Bail early if no % variables and no [ shortcodes
		if ( false === strpos( $string, '%' ) && false === strpos( $string, '[' ) ) {
			return $string;
		}

		// Resolve post object if not explicitly provided but in singular query
		if ( empty( $post ) && is_singular() ) {
			global $post;
		}

		// Resolve term object if not explicitly provided but in taxonomy query
		if ( empty( $term ) && ( is_category() || is_tag() || is_tax() ) ) {
			$term = get_queried_object();
		}

		$sep      = Seo_Core_Options::get( 'title_separator', '—' );
		$sitename = get_bloginfo( 'name' );
		$sitedesc = get_bloginfo( 'description' );
		$org_name = Seo_Core_Options::get( 'org_name', $sitename );
		$org_logo = Seo_Core_Options::get( 'org_logo', '' );
		$org_url  = Seo_Core_Options::get( 'org_url', home_url() );

		// Pagination
		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' );
		$paged = $paged ? max( 1, intval( $paged ) ) : 1;
		global $wp_query;
		$max_num_pages = ( isset( $wp_query->max_num_pages ) && $wp_query->max_num_pages > 0 ) ? intval( $wp_query->max_num_pages ) : 1;

		$page_str = ( $paged > 1 ) ? ' ' . $sep . ' ' . sprintf( __( 'Страница %d', 'seo-core-best' ), $paged ) : '';

		// Base static replacements
		$replacements = array(
			'%sep%'           => $sep,
			'%sitename%'      => $sitename,
			'%sitedesc%'      => $sitedesc,
			'%org_name%'      => $org_name,
			'%org_logo%'      => $org_logo,
			'%org_url%'       => $org_url,
			'%page%'          => $page_str,
			'%pagenumber%'    => (string) $paged,
			'%pagetotal%'     => (string) $max_num_pages,
			'%currentdate%'   => date_i18n( get_option( 'date_format' ) ),
			'%currenttime%'   => date_i18n( get_option( 'time_format' ) ),
			'%currentday%'    => date_i18n( 'j' ),
			'%currentmonth%'  => date_i18n( 'F' ),
			'%currentyear%'   => date_i18n( 'Y' ),
		);

		// Post Context
		if ( ! empty( $post ) && is_object( $post ) ) {
			$post_id    = $post->ID;
			$post_title = get_the_title( $post_id );
			$excerpt    = self::get_post_excerpt( $post );

			$replacements['%title%']       = $post_title;
			$replacements['%id%']          = (string) $post_id;
			$replacements['%excerpt%']     = $excerpt;
			$replacements['%excerpt_only%']= ! empty( $post->post_excerpt ) ? wp_strip_all_tags( $post->post_excerpt ) : '';
			$replacements['%url%']          = get_permalink( $post_id );

			// Dates
			$replacements['%date%']        = get_the_date( '', $post );
			$replacements['%modified%']    = get_the_modified_date( '', $post );

			// Focus Keyword
			$focus_kw = get_post_meta( $post_id, '_seo_focus_kw', true );
			if ( empty( $focus_kw ) ) {
				$focus_kw = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			}
			$replacements['%focuskw%']     = $focus_kw;
			$replacements['%keywords%']    = $focus_kw;

			// Post SEO Title & Description
			$seo_title = get_post_meta( $post_id, '_seo_title', true );
			if ( empty( $seo_title ) ) {
				$seo_title = get_post_meta( $post_id, 'rank_math_title', true );
			}
			$replacements['%seo_title%']   = $seo_title ? $seo_title : $post_title;

			$seo_desc = get_post_meta( $post_id, '_seo_description', true );
			if ( empty( $seo_desc ) ) {
				$seo_desc = get_post_meta( $post_id, 'rank_math_description', true );
			}
			$replacements['%seo_description%'] = $seo_desc ? $seo_desc : $excerpt;

			// Parent Title
			if ( ! empty( $post->post_parent ) ) {
				$replacements['%parent_title%'] = get_the_title( $post->post_parent );
			} else {
				$replacements['%parent_title%'] = '';
			}

			// Post Type labels
			$pt_obj = get_post_type_object( $post->post_type );
			if ( $pt_obj ) {
				$replacements['%pt_single%'] = $pt_obj->labels->singular_name;
				$replacements['%pt_plural%'] = $pt_obj->labels->name;
			}

			// Author
			$author_id = $post->post_author;
			$replacements['%author%']           = get_the_author_meta( 'display_name', $author_id );
			$replacements['%name%']             = get_the_author_meta( 'display_name', $author_id );
			$replacements['%post_author%']      = get_the_author_meta( 'display_name', $author_id );
			$replacements['%userid%']           = (string) $author_id;
			$replacements['%user_description%'] = get_the_author_meta( 'description', $author_id );

			// Categories
			$categories = get_the_category( $post_id );
			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				$replacements['%category%']   = $categories[0]->name;
				$cat_names                    = wp_list_pluck( $categories, 'name' );
				$replacements['%categories%'] = implode( ', ', $cat_names );
				$replacements['%primary_taxonomy_terms%'] = implode( ', ', $cat_names );
			} else {
				$replacements['%category%']   = '';
				$replacements['%categories%'] = '';
				$replacements['%primary_taxonomy_terms%'] = '';
			}

			// Tags
			$tags = get_the_tags( $post_id );
			if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
				$first_tag = reset( $tags );
				$replacements['%tag%']  = $first_tag->name;
				$tag_names              = wp_list_pluck( $tags, 'name' );
				$replacements['%tags%'] = implode( ', ', $tag_names );
			} else {
				$replacements['%tag%']  = '';
				$replacements['%tags%'] = '';
			}
		}

		// Term Context (Taxonomy / Category / Tag archive)
		if ( ! empty( $term ) && is_object( $term ) ) {
			$term_name = isset( $term->name ) ? $term->name : '';
			$term_desc = isset( $term->description ) ? wp_strip_all_tags( $term->description ) : '';
			$term_link = ( ! is_wp_error( $term ) ) ? get_term_link( $term ) : '';

			$replacements['%term%']             = $term_name;
			$replacements['%title%']            = $term_name;
			$replacements['%term_description%'] = $term_desc;
			$replacements['%url%']              = ! is_wp_error( $term_link ) ? $term_link : '';

			if ( isset( $term->taxonomy ) ) {
				if ( 'category' === $term->taxonomy ) {
					$replacements['%category%']   = $term_name;
					$replacements['%categories%'] = $term_name;
				} elseif ( 'post_tag' === $term->taxonomy ) {
					$replacements['%tag%']  = $term_name;
					$replacements['%tags%'] = $term_name;
				}
			}
		}

		// Search Query
		if ( is_search() ) {
			$sq = get_search_query();
			$replacements['%searchquery%']  = $sq;
			$replacements['%search_query%'] = $sq;
		}

		// Author Archive Context
		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author && isset( $author->display_name ) ) {
				$replacements['%name%']             = $author->display_name;
				$replacements['%author%']           = $author->display_name;
				$replacements['%userid%']           = (string) $author->ID;
				$replacements['%user_description%'] = get_the_author_meta( 'description', $author->ID );
			}
		}

		// 1. Process Parameterized Variables with regex:
		// e.g. %date(format)%, %modified(format)%, %currentdate(format)%, %currenttime(format)%, %customfield(name)%, %customterm(taxonomy)%
		if ( false !== strpos( $string, '(' ) ) {
			$string = preg_replace_callback( '/%([a-zA-Z0-9_-]+)\(([^)]*)\)%/u', function( $matches ) use ( $post, $term ) {
				$var  = strtolower( $matches[1] );
				$param = trim( $matches[2] );

				// %date(Y-m-d)%
				if ( 'date' === $var ) {
					if ( ! empty( $post ) ) {
						return get_the_date( $param ? $param : '', $post );
					}
					return date_i18n( $param ? $param : get_option( 'date_format' ) );
				}

				// %modified(Y-m-d)%
				if ( 'modified' === $var ) {
					if ( ! empty( $post ) ) {
						return get_the_modified_date( $param ? $param : '', $post );
					}
					return '';
				}

				// %currentdate(format)%
				if ( 'currentdate' === $var ) {
					return date_i18n( $param ? $param : get_option( 'date_format' ) );
				}

				// %currenttime(format)%
				if ( 'currenttime' === $var ) {
					return date_i18n( $param ? $param : get_option( 'time_format' ) );
				}

				// %customfield(meta_key)%
				if ( 'customfield' === $var && ! empty( $param ) ) {
					if ( ! empty( $post ) ) {
						$meta = get_post_meta( $post->ID, $param, true );
						if ( ! is_array( $meta ) ) {
							return (string) $meta;
						}
					} elseif ( ! empty( $term ) ) {
						$meta = get_term_meta( $term->term_id, $param, true );
						if ( ! is_array( $meta ) ) {
							return (string) $meta;
						}
					} elseif ( is_author() ) {
						$author = get_queried_object();
						if ( $author ) {
							$meta = get_user_meta( $author->ID, $param, true );
							if ( ! is_array( $meta ) ) {
								return (string) $meta;
							}
						}
					}
					return '';
				}

				// %customterm(taxonomy)%
				if ( 'customterm' === $var && ! empty( $param ) && ! empty( $post ) ) {
					$terms = get_the_terms( $post->ID, $param );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$names = wp_list_pluck( $terms, 'name' );
						return implode( ', ', $names );
					}
					return '';
				}

				// %customterm_desc(taxonomy)%
				if ( 'customterm_desc' === $var && ! empty( $param ) && ! empty( $post ) ) {
					$terms = get_the_terms( $post->ID, $param );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$first = reset( $terms );
						return wp_strip_all_tags( $first->description );
					}
					return '';
				}

				return '';
			}, $string );
		}

		// 2. Perform Simple Variables Replacement
		$output = str_replace( array_keys( $replacements ), array_values( $replacements ), $string );

		// 3. Clean any remaining unrecognized %...% tags that resolved to empty
		$output = preg_replace( '/%[a-zA-Z0-9_-]+%/u', '', $output );

		// 4. Execute dynamic shortcodes (e.g. [currentyear], [upcomingmonth], [currentmonth])
		if ( false !== strpos( $output, '[' ) ) {
			$output = do_shortcode( $output );
		}

		// 5. Clean up multiple spaces, empty parentheses, dangling separators
		$output = preg_replace( '/\(\s*\)/', '', $output );
		$output = preg_replace( '/\s+/', ' ', $output );
		$output = trim( $output );

		$escaped_sep = preg_quote( $sep, '/' );
		// Collapse duplicate separators: e.g. "— —" -> "—"
		$output = preg_replace( '/(' . $escaped_sep . '\s*)+/u', $sep . ' ', $output );
		// Strip leading and trailing separator
		$output = preg_replace( '/^' . $escaped_sep . '\s*/u', '', $output );
		$output = preg_replace( '/\s*' . $escaped_sep . '$/u', '', $output );

		return trim( $output );
	}

	/**
	 * Get clean excerpt for post
	 *
	 * @param WP_Post $post
	 * @return string
	 */
	public static function get_post_excerpt( $post ) {
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		if ( empty( $post->post_content ) ) {
			return '';
		}

		$content = strip_shortcodes( $post->post_content );
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/\s+/', ' ', $content );
		return wp_trim_words( $content, 35, '...' );
	}
}
