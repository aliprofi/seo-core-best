<?php
/**
 * Advanced 404 Error Monitor
 *
 * Tracks 404 requests with IP detection (Cloudflare-ready), Referer, User-Agent,
 * File-Type categorization (Pages vs Images vs Assets), Hit counting and One-Click 301 Redirection.
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_404_Monitor {

	/**
	 * Table name
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_404_logs';
	}

	public function __construct() {
		if ( Seo_Core_Options::get( 'enable_404_monitor', 1 ) ) {
			add_action( 'template_redirect', array( $this, 'log_404' ), 99 );
		}
	}

	/**
	 * Create database table if not exists
	 */
	public static function install_table() {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uri varchar(500) NOT NULL,
			file_type varchar(20) NOT NULL DEFAULT 'page',
			ip_address varchar(45) NOT NULL DEFAULT '',
			referer varchar(500) NOT NULL DEFAULT '',
			user_agent varchar(300) NOT NULL DEFAULT '',
			hits bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_accessed datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uri (uri(191)),
			KEY file_type (file_type),
			KEY last_accessed (last_accessed),
			KEY hits (hits)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Intercept 404 request and log details
	 */
	public function log_404() {
		if ( ! is_404() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request_uri = $_SERVER['REQUEST_URI'];
		$parsed_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( empty( $parsed_path ) ) {
			$parsed_path = '/';
		}

		$file_type = self::detect_file_type( $parsed_path );

		// Skip images if disabled in settings
		if ( 'image' === $file_type && ! Seo_Core_Options::get( 'log_404_images', 1 ) ) {
			return;
		}

		// Skip common favicon / robots / sitemap noise if desired or keep clean
		$clean_uri = '/' . ltrim( $parsed_path, '/' );

		$ip         = self::get_client_ip();
		$referer    = ! empty( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$user_agent = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Trim lengths to fit schema
		$referer    = mb_substr( $referer, 0, 500 );
		$user_agent = mb_substr( $user_agent, 0, 300 );

		global $wpdb;
		$table = self::get_table_name();

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
			self::install_table();
		}

		// Insert or increment hits on duplicate URI
		$query = "INSERT INTO {$table} (uri, file_type, ip_address, referer, user_agent, hits, created_at, last_accessed)
				  VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
				  ON DUPLICATE KEY UPDATE 
				      hits = hits + 1, 
				      last_accessed = NOW(), 
				      ip_address = VALUES(ip_address), 
				      referer = VALUES(referer), 
				      user_agent = VALUES(user_agent),
				      file_type = VALUES(file_type)";

		$wpdb->query( $wpdb->prepare( $query, $clean_uri, $file_type, $ip, $referer, $user_agent ) );
	}

	/**
	 * Detect file type based on extension
	 *
	 * @param string $path
	 * @return string 'page'|'image'|'asset'|'other'
	 */
	public static function detect_file_type( $path ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( empty( $ext ) || in_array( $ext, array( 'html', 'htm', 'php' ), true ) ) {
			return 'page';
		}

		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif', 'ico', 'bmp', 'tiff' ), true ) ) {
			return 'image';
		}

		if ( in_array( $ext, array( 'css', 'js', 'map', 'woff', 'woff2', 'ttf', 'eot', 'otf' ), true ) ) {
			return 'asset';
		}

		return 'other';
	}

	/**
	 * Get real client IP address (Cloudflare, Proxy and direct support)
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$raw_ip = $_SERVER[ $key ];
				// If multiple IPs (X-Forwarded-For: client, proxy1, proxy2), take first
				if ( false !== strpos( $raw_ip, ',' ) ) {
					$parts  = explode( ',', $raw_ip );
					$raw_ip = trim( $parts[0] );
				}

				$filtered = filter_var( $raw_ip, FILTER_VALIDATE_IP );
				if ( false !== $filtered ) {
					return $filtered;
				}
			}
		}

		return '127.0.0.1';
	}

	/**
	 * Get logs with pagination and filters
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = array(
			'paged'     => 1,
			'per_page'  => 50,
			'file_type' => '',
			'search'    => '',
			'orderby'   => 'last_accessed',
			'order'     => 'DESC',
		);
		$r = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $r['file_type'] ) && 'all' !== $r['file_type'] ) {
			$where[] = 'file_type = %s';
			$values[] = sanitize_text_field( $r['file_type'] );
		}

		if ( ! empty( $r['search'] ) ) {
			$search_like = '%' . $wpdb->esc_like( trim( $r['search'] ) ) . '%';
			$where[]     = '(uri LIKE %s OR ip_address LIKE %s OR referer LIKE %s OR user_agent LIKE %s)';
			$values[]    = $search_like;
			$values[]    = $search_like;
			$values[]    = $search_like;
			$values[]    = $search_like;
		}

		$where_sql = implode( ' AND ', $where );

		// Ordering
		$valid_orderby = array( 'last_accessed', 'hits', 'created_at', 'uri' );
		$orderby = in_array( $r['orderby'], $valid_orderby, true ) ? $r['orderby'] : 'last_accessed';
		$order   = ( 'ASC' === strtoupper( $r['order'] ) ) ? 'ASC' : 'DESC';

		$offset = ( max( 1, intval( $r['paged'] ) ) - 1 ) * intval( $r['per_page'] );
		$limit  = intval( $r['per_page'] );

		// Count total
		$count_query = "SELECT COUNT(id) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $values ) );
		} else {
			$total = (int) $wpdb->get_var( $count_query );
		}

		// Items query
		$items_query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
		if ( ! empty( $values ) ) {
			$items = $wpdb->get_results( $wpdb->prepare( $items_query, $values ), ARRAY_A );
		} else {
			$items = $wpdb->get_results( $items_query, ARRAY_A );
		}

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => ceil( $total / $limit ),
		);
	}

	/**
	 * Get total stats (total hits, unique URLs, images count, pages count)
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;
		$table = self::get_table_name();

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
			return array(
				'total_urls'  => 0,
				'total_hits'  => 0,
				'pages_count' => 0,
				'images_count'=> 0,
				'assets_count'=> 0,
			);
		}

		$stats = $wpdb->get_row( "SELECT 
			COUNT(id) as total_urls, 
			COALESCE(SUM(hits), 0) as total_hits,
			SUM(CASE WHEN file_type = 'page' THEN 1 ELSE 0 END) as pages_count,
			SUM(CASE WHEN file_type = 'image' THEN 1 ELSE 0 END) as images_count,
			SUM(CASE WHEN file_type = 'asset' THEN 1 ELSE 0 END) as assets_count
		FROM {$table}", ARRAY_A );

		return $stats ? $stats : array(
			'total_urls'  => 0,
			'total_hits'  => 0,
			'pages_count' => 0,
			'images_count'=> 0,
			'assets_count'=> 0,
		);
	}

	/**
	 * Delete a single log entry
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return (bool) $wpdb->delete( $table, array( 'id' => intval( $id ) ), array( '%d' ) );
	}

	/**
	 * Clear all 404 logs
	 *
	 * @return bool
	 */
	public static function clear_all() {
		global $wpdb;
		$table = self::get_table_name();
		return (bool) $wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
