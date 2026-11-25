<?php
/**
 * Instant Indexing Engine (Google Indexing API & IndexNow for Yandex/Bing)
 *
 * Lightweight, zero-dependency implementation of Google Indexing API (via native OpenSSL JWT)
 * and IndexNow protocol for instant indexing in Yandex and Bing.
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Instant_Indexing {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_indexing_logs';
	}

	public function __construct() {
		if ( ! Seo_Core_Options::get( 'enable_instant_indexing', 1 ) ) {
			return;
		}

		// Handle IndexNow key verification file request: /{key}.txt
		add_action( 'init', array( $this, 'serve_indexnow_key' ) );

		// Auto-submit on post publish or update
		add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'handle_post_deletion' ) );
		add_action( 'before_delete_post', array( $this, 'handle_post_deletion' ) );
	}

	/**
	 * Install database table for logging
	 */
	public static function install_table() {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(500) NOT NULL,
			engine varchar(20) NOT NULL DEFAULT 'google',
			action_type varchar(20) NOT NULL DEFAULT 'update',
			response_code smallint(4) NOT NULL DEFAULT 200,
			response_body text NULL,
			status varchar(20) NOT NULL DEFAULT 'success',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY engine (engine),
			KEY created_at (created_at),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get or generate IndexNow API Key
	 *
	 * @return string
	 */
	public static function get_indexnow_key() {
		$key = Seo_Core_Options::get( 'indexnow_api_key', '' );
		if ( empty( $key ) ) {
			$key = md5( get_site_url() . wp_salt() );
			$options = Seo_Core_Options::get_all();
			$options['indexnow_api_key'] = $key;
			Seo_Core_Options::update_all( $options );
		}
		return $key;
	}

	/**
	 * Serve IndexNow verification key file (e.g. /<key>.txt)
	 */
	public function serve_indexnow_key() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$key = self::get_indexnow_key();
		if ( empty( $key ) ) {
			return;
		}

		$request_uri = $_SERVER['REQUEST_URI'];
		$path = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

		if ( $path === $key . '.txt' ) {
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( $key );
			exit;
		}
	}

	/**
	 * Handle post publish or update
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function handle_post_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post ) ) {
			return;
		}

		// Prevent duplicate requests during the same save cycle
		static $processed = array();
		if ( isset( $processed[ $post->ID ] ) ) {
			return;
		}
		$processed[ $post->ID ] = true;

		$pt = get_post_type( $post );
		$permalink = get_permalink( $post->ID );
		if ( empty( $permalink ) ) {
			return;
		}

		// Google Indexing API auto-submit
		$google_pts = Seo_Core_Options::get( 'google_index_post_types', array( 'post', 'page', 'ali_sale', 'dwqa-question' ) );
		if ( in_array( $pt, (array) $google_pts, true ) ) {
			self::submit_to_google( array( $permalink ), 'URL_UPDATED' );
		}

		// IndexNow (Yandex/Bing) auto-submit
		$indexnow_pts = Seo_Core_Options::get( 'indexnow_post_types', array( 'post', 'page', 'ali_sale', 'dwqa-question' ) );
		if ( in_array( $pt, (array) $indexnow_pts, true ) ) {
			self::submit_to_indexnow( array( $permalink ) );
		}
	}

	/**
	 * Handle post trash / deletion
	 *
	 * @param int $post_id
	 */
	public function handle_post_deletion( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$permalink = get_permalink( $post_id );
		if ( empty( $permalink ) ) {
			return;
		}

		$google_pts = Seo_Core_Options::get( 'google_index_post_types', array( 'post', 'page' ) );
		if ( in_array( $post->post_type, (array) $google_pts, true ) ) {
			self::submit_to_google( array( $permalink ), 'URL_DELETED' );
		}
	}

	/**
	 * Submit URLs to Google Indexing API
	 *
	 * @param array  $urls Array of URLs
	 * @param string $action 'URL_UPDATED' or 'URL_DELETED'
	 * @return array
	 */
	public static function submit_to_google( array $urls, $action = 'URL_UPDATED' ) {
		$key_json = Seo_Core_Options::get( 'google_json_key', '' );
		if ( empty( $key_json ) ) {
			return array(
				'success' => false,
				'message' => __( 'Ключ Google Service Account JSON не настроен.', 'seo-core-best' ),
			);
		}

		$key_data = json_decode( $key_json, true );
		if ( empty( $key_data ) || empty( $key_data['client_email'] ) || empty( $key_data['private_key'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Некорректный формат Google JSON ключа. Проверьте поля client_email и private_key.', 'seo-core-best' ),
			);
		}

		// Get Access Token
		$access_token = self::get_google_access_token( $key_data );
		if ( is_wp_error( $access_token ) ) {
			return array(
				'success' => false,
				'message' => $access_token->get_error_message(),
			);
		}

		$results = array();
		$success_count = 0;

		foreach ( $urls as $url ) {
			$url = trim( $url );
			if ( empty( $url ) ) {
				continue;
			}

			$response = wp_remote_post( 'https://indexing.googleapis.com/v3/urlNotifications:publish', array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
				),
				'body'    => wp_json_encode( array(
					'url'  => $url,
					'type' => $action,
				) ),
				'timeout' => 15,
			) );

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$is_ok = ( 200 === $code );

			if ( $is_ok ) {
				$success_count++;
			}

			self::log_submission( $url, 'google', ( 'URL_DELETED' === $action ? 'delete' : 'update' ), $code, $body, $is_ok ? 'success' : 'error' );

			$results[] = array(
				'url'     => $url,
				'code'    => $code,
				'success' => $is_ok,
				'body'    => $body,
			);
		}

		return array(
			'success' => ( $success_count > 0 ),
			'message' => sprintf( __( 'Google API: Успешно отправлено %d из %d URL.', 'seo-core-best' ), $success_count, count( $urls ) ),
			'results' => $results,
		);
	}

	/**
	 * Generate JWT and exchange for Google OAuth2 Bearer Access Token
	 *
	 * @param array $key_data
	 * @return string|WP_Error
	 */
	protected static function get_google_access_token( array $key_data ) {
		// Check transient cache (55 mins)
		$cache_key = 'seo_core_google_token_' . md5( $key_data['client_email'] );
		$cached = get_transient( $cache_key );
		if ( ! empty( $cached ) ) {
			return $cached;
		}

		$header = self::base64_url_encode( wp_json_encode( array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		) ) );

		$now = time();
		$claim = self::base64_url_encode( wp_json_encode( array(
			'iss'   => $key_data['client_email'],
			'scope' => 'https://www.googleapis.com/auth/indexing',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'exp'   => $now + 3600,
			'iat'   => $now,
		) ) );

		$to_sign = $header . '.' . $claim;
		$signature = '';

		$private_key = openssl_pkey_get_private( $key_data['private_key'] );
		if ( ! $private_key ) {
			return new WP_Error( 'invalid_key', __( 'Не удалось загрузить приватный ключ RSA из JSON.', 'seo-core-best' ) );
		}

		$signed = openssl_sign( $to_sign, $signature, $private_key, OPENSSL_ALGO_SHA256 );
		openssl_free_key( $private_key );

		if ( ! $signed ) {
			return new WP_Error( 'sign_failed', __( 'Ошибка подписи JWT токена через OpenSSL.', 'seo-core-best' ) );
		}

		$jwt = $to_sign . '.' . self::base64_url_encode( $signature );

		// Exchange JWT for access token
		$token_response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $token_response ) ) {
			return $token_response;
		}

		$token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
		if ( empty( $token_body['access_token'] ) ) {
			$err = isset( $token_body['error_description'] ) ? $token_body['error_description'] : wp_remote_retrieve_body( $token_response );
			return new WP_Error( 'token_error', sprintf( __( 'Google Auth Error: %s', 'seo-core-best' ), $err ) );
		}

		$access_token = $token_body['access_token'];
		set_transient( $cache_key, $access_token, 3300 ); // Cache for 55 mins

		return $access_token;
	}

	/**
	 * Submit URLs to IndexNow (Yandex and Bing)
	 *
	 * @param array $urls
	 * @return array
	 */
	public static function submit_to_indexnow( array $urls ) {
		$key = self::get_indexnow_key();
		$site_url = home_url();
		$host = wp_parse_url( $site_url, PHP_URL_HOST );
		$key_location = trailingslashit( $site_url ) . $key . '.txt';

		$clean_urls = array();
		foreach ( $urls as $u ) {
			$u = trim( $u );
			if ( ! empty( $u ) ) {
				$clean_urls[] = $u;
			}
		}

		if ( empty( $clean_urls ) ) {
			return array(
				'success' => false,
				'message' => __( 'Список URL для IndexNow пуст.', 'seo-core-best' ),
			);
		}

		$payload = wp_json_encode( array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => $key_location,
			'urlList'     => $clean_urls,
		) );

		// Endpoints: Yandex and IndexNow Central (Bing)
		$endpoints = array(
			'yandex'   => 'https://yandex.com/indexnow',
			'indexnow' => 'https://api.indexnow.org/indexnow',
		);

		$all_ok = true;
		$msg = '';

		foreach ( $endpoints as $engine_name => $endpoint ) {
			$response = wp_remote_post( $endpoint, array(
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => $payload,
				'timeout' => 15,
			) );

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$is_ok = in_array( $code, array( 200, 202 ), true );

			if ( ! $is_ok ) {
				$all_ok = false;
			}

			foreach ( $clean_urls as $single_url ) {
				self::log_submission( $single_url, $engine_name, 'update', $code, $body, $is_ok ? 'success' : 'error' );
			}
		}

		return array(
			'success' => $all_ok,
			'message' => sprintf( __( 'IndexNow (Яндекс и Bing): Отправлено %d URL. Статус: %s', 'seo-core-best' ), count( $clean_urls ), $all_ok ? 'Успешно' : 'Проверьте ответ сервера' ),
		);
	}

	/**
	 * Log submission to database
	 *
	 * @param string $url
	 * @param string $engine
	 * @param string $action
	 * @param int    $code
	 * @param string $body
	 * @param string $status
	 */
	protected static function log_submission( $url, $engine, $action, $code, $body, $status ) {
		global $wpdb;
		$table = self::get_table_name();
		self::install_table();

		$wpdb->insert(
			$table,
			array(
				'url'           => $url,
				'engine'        => $engine,
				'action_type'   => $action,
				'response_code' => intval( $code ),
				'response_body' => mb_substr( (string) $body, 0, 500 ),
				'status'        => $status,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get submission history
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_history( $limit = 50 ) {
		global $wpdb;
		$table = self::get_table_name();
		self::install_table();

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	/**
	 * Clear submission history
	 *
	 * @return bool
	 */
	public static function clear_history() {
		global $wpdb;
		$table = self::get_table_name();
		return (bool) $wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Base64 URL safe encoder
	 *
	 * @param string $data
	 * @return string
	 */
	protected static function base64_url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
