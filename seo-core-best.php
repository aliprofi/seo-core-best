<?php
/**
 * Plugin Name: SEO Core Best
 * Plugin URI: https://github.com/aliprofi/seo-core-best
 * Description: Сверхлегкий, быстрый и автономный On-Page SEO плагин с 100% совместимостью мета-полей Rank Math, 301-редиректами, XML-картой сайта, OpenGraph, монитором 404, мгновенной индексацией и AI-ассистентом.
 * Version: 1.0.0
 * Author: AliProfi
 * Author URI: https://aliprofi.ru
 * Text Domain: seo-core-best
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEO_CORE_BEST_VERSION', '1.0.0' );
define( 'SEO_CORE_BEST_FILE', __FILE__ );
define( 'SEO_CORE_BEST_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEO_CORE_BEST_URL', plugin_dir_url( __FILE__ ) );

// Load Classes
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-options.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-variables.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-frontend.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-redirects.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-breadcrumbs.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-404-monitor.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-instant-indexing.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-analyzer.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-ai.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-category-base.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-sitemap.php';
require_once SEO_CORE_BEST_DIR . 'includes/class-seo-importer.php';

if ( is_admin() ) {
	require_once SEO_CORE_BEST_DIR . 'includes/class-seo-metabox.php';
	require_once SEO_CORE_BEST_DIR . 'includes/class-seo-admin.php';
}

/**
 * Main Plugin Class
 */
class Seo_Core_Best {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Initialize Components
		new Seo_Core_Frontend();
		new Seo_Core_Redirects();
		new Seo_Core_Breadcrumbs();
		new Seo_Core_Category_Base();
		new Seo_Core_404_Monitor();
		new Seo_Core_Instant_Indexing();
		new Seo_Core_Analyzer();
		new Seo_Core_AI();
		new Seo_Core_Sitemap();

		if ( is_admin() ) {
			new Seo_Core_Metabox();
			new Seo_Core_Admin();
		}

		// Register core shortcodes if not already provided by another plugin
		$this->register_shortcodes();
	}

	/**
	 * Register [currentyear], [currentmonth], [upcomingmonth] shortcodes
	 */
	protected function register_shortcodes() {
		if ( ! shortcode_exists( 'currentyear' ) ) {
			add_shortcode( 'currentyear', function() {
				return date( 'Y' );
			} );
		}

		if ( ! shortcode_exists( 'currentmonth' ) ) {
			add_shortcode( 'currentmonth', function() {
				return date_i18n( 'F' );
			} );
		}

		if ( ! shortcode_exists( 'upcomingmonth' ) ) {
			add_shortcode( 'upcomingmonth', function( $atts ) {
				$atts = shortcode_atts( array(
					'days'      => 7,
					'separator' => ' — ',
					'format'    => 'pair',
				), $atts, 'upcomingmonth' );

				$days_in_month = (int) date( 't' );
				$current_day   = (int) date( 'j' );
				$current_month = date_i18n( 'F' );

				if ( $current_day > ( $days_in_month - intval( $atts['days'] ) ) ) {
					$next_month = date_i18n( 'F', strtotime( '+1 month' ) );
					if ( 'next' === $atts['format'] ) {
						return $next_month;
					}
					return $current_month . $atts['separator'] . $next_month;
				}

				return $current_month;
			} );
		}
	}
}

/**
 * Activation & Deactivation Hooks
 */
register_activation_hook( __FILE__, function() {
	Seo_Core_Redirects::install_table();
	Seo_Core_404_Monitor::install_table();
	Seo_Core_Instant_Indexing::install_table();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );

// Bootstrap
add_action( 'plugins_loaded', array( 'Seo_Core_Best', 'get_instance' ) );
