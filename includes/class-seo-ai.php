<?php
/**
 * SEO Core AI Engine (DeepSeek & Mistral AI)
 *
 * Provides native AI integration for automatic image alt generation,
 * high-CTR SEO Title & Description writing, and 1-click issue fixing in SEO Analyzer.
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_AI {

	public function __construct() {
		// AJAX endpoints for admin
		add_action( 'wp_ajax_seo_core_ai_generate_alts', array( $this, 'ajax_generate_alts' ) );
		add_action( 'wp_ajax_seo_core_ai_generate_meta', array( $this, 'ajax_generate_meta' ) );
		add_action( 'wp_ajax_seo_core_ai_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_seo_core_ai_fix_issue', array( $this, 'ajax_fix_issue' ) );
	}

	/**
	 * Send Chat Completion request to configured AI Provider (DeepSeek or Mistral)
	 *
	 * @param array $messages Array of messages with role and content
	 * @param array $args Optional parameters (temperature, max_tokens, json_mode)
	 * @return array|WP_Error
	 */
	public static function chat_completion( array $messages, array $args = array() ) {
		$provider = Seo_Core_Options::get( 'ai_provider', 'deepseek' );

		if ( 'mistral' === $provider ) {
			$api_url = 'https://api.mistral.ai/v1/chat/completions';
			$api_key = trim( Seo_Core_Options::get( 'mistral_api_key', '' ) );
			$model   = Seo_Core_Options::get( 'mistral_model', 'mistral-small-latest' );
		} else {
			// Default: DeepSeek
			$api_url = 'https://api.deepseek.com/chat/completions';
			$api_key = trim( Seo_Core_Options::get( 'deepseek_api_key', '' ) );
			$model   = Seo_Core_Options::get( 'deepseek_model', 'deepseek-chat' );
		}

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', sprintf( __( 'API-ключ для %s не настроен. Укажите его во вкладке «Настройки AI».', 'seo-core-best' ), ucfirst( $provider ) ) );
		}

		$payload = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => isset( $args['temperature'] ) ? floatval( $args['temperature'] ) : 0.7,
			'max_tokens'  => isset( $args['max_tokens'] ) ? intval( $args['max_tokens'] ) : 1500,
		);

		if ( ! empty( $args['json_mode'] ) ) {
			$payload['response_format'] = array( 'type' => 'json_object' );
		}

		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 45,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$err_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : ( 'HTTP ' . $code . ': ' . substr( $body, 0, 200 ) );
			return new WP_Error( 'api_error', sprintf( __( 'Ошибка %s API: %s', 'seo-core-best' ), ucfirst( $provider ), $err_msg ) );
		}

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'empty_response', __( 'Пустой ответ от AI модели.', 'seo-core-best' ) );
		}

		return array(
			'content'  => $data['choices'][0]['message']['content'],
			'provider' => $provider,
			'model'    => $model,
			'usage'    => isset( $data['usage'] ) ? $data['usage'] : array(),
		);
	}

	/**
	 * Test AI API connection
	 *
	 * @param string $provider
	 * @param string $key
	 * @return array
	 */
	public static function test_connection( $provider = '', $key = '' ) {
		if ( empty( $provider ) ) {
			$provider = Seo_Core_Options::get( 'ai_provider', 'deepseek' );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an AI test probe. Return JSON: {"status":"connected","provider":"' . $provider . '"}',
			),
			array(
				'role'    => 'user',
				'content' => 'Ping',
			),
		);

		// Temporarily set key if provided
		$orig_key = '';
		if ( ! empty( $key ) ) {
			if ( 'mistral' === $provider ) {
				$orig_key = Seo_Core_Options::get( 'mistral_api_key' );
				$opts = Seo_Core_Options::get_all();
				$opts['mistral_api_key'] = $key;
				Seo_Core_Options::update_all( $opts );
			} else {
				$orig_key = Seo_Core_Options::get( 'deepseek_api_key' );
				$opts = Seo_Core_Options::get_all();
				$opts['deepseek_api_key'] = $key;
				Seo_Core_Options::update_all( $opts );
			}
		}

		$res = self::chat_completion( $messages, array( 'json_mode' => true, 'max_tokens' => 100 ) );

		// Restore key if needed
		if ( ! empty( $key ) ) {
			$opts = Seo_Core_Options::get_all();
			if ( 'mistral' === $provider ) {
				$opts['mistral_api_key'] = $orig_key;
			} else {
				$opts['deepseek_api_key'] = $orig_key;
			}
			Seo_Core_Options::update_all( $opts );
		}

		if ( is_wp_error( $res ) ) {
			return array(
				'success' => false,
				'message' => $res->get_error_message(),
			);
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Соединение с %s успешно установлено! Модель готова к работе.', 'seo-core-best' ), ucfirst( $provider ) ),
			'provider' => $provider,
		);
	}

	/**
	 * Generate and save Alt descriptions for all images in a post
	 *
	 * @param int $post_id
	 * @return array
	 */
	public static function generate_image_alts_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'Пост не найден.', 'seo-core-best' ),
			);
		}

		$content = $post->post_content;
		if ( empty( $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Контент статьи пуст.', 'seo-core-best' ),
			);
		}

		// Find all <img> tags
		preg_match_all( '/<img\b([^>]*)>/i', $content, $img_matches, PREG_OFFSET_CAPTURE );
		if ( empty( $img_matches[0] ) ) {
			return array(
				'success' => false,
				'message' => __( 'В тексте статьи не найдено тегов &lt;img&gt;.', 'seo-core-best' ),
			);
		}

		$images_to_process = array();
		foreach ( $img_matches[0] as $idx => $match_data ) {
			$tag_html = $match_data[0];
			$offset   = $match_data[1];

			// Extract src
			if ( ! preg_match( '/src=[\'"]([^\'"]+)[\'"]/i', $tag_html, $src_match ) ) {
				continue;
			}
			$src = $src_match[1];
			$path_info = pathinfo( wp_parse_url( $src, PHP_URL_PATH ) );
			$filename = ! empty( $path_info['filename'] ) ? $path_info['filename'] : 'image';

			// Check current alt
			$current_alt = '';
			if ( preg_match( '/\balt=[\'"]([^\'"]*)[\'"]/i', $tag_html, $alt_match ) ) {
				$current_alt = trim( $alt_match[1] );
			}

			// Extract surrounding text context (up to 300 chars before/after)
			$start_ctx = max( 0, $offset - 250 );
			$ctx_chunk = strip_tags( substr( $content, $start_ctx, 500 ) );
			$ctx_clean = trim( preg_replace( '/\s+/u', ' ', $ctx_chunk ) );

			$images_to_process[] = array(
				'index'       => $idx + 1,
				'tag_html'    => $tag_html,
				'src'         => $src,
				'filename'    => $filename,
				'current_alt' => $current_alt,
				'context'     => mb_substr( $ctx_clean, 0, 220, 'UTF-8' ),
			);
		}

		if ( empty( $images_to_process ) ) {
			return array(
				'success' => false,
				'message' => __( 'Не удалось извлечь изображения для обработки.', 'seo-core-best' ),
			);
		}

		// Build prompt for AI
		$prompt_list = array();
		foreach ( $images_to_process as $item ) {
			$prompt_list[] = sprintf(
				"Картинка #%d: Файл \"%s\", Контекст абзаца: \"%s\"",
				$item['index'],
				$item['filename'],
				$item['context']
			);
		}

		$system_prompt = "Ты ведущий SEO-специалист и эксперт по оптимизации изображений (Image SEO) для поисковых систем Google и Яндекс. Твоя задача — создать естественные, релевантные и емкие alt-описания на русском языке для картинок в статье. Длина каждого alt: от 3 до 8 слов. Описание должно точно отражать смысл иллюстрации в контексте темы статьи и не спамить ключами. Ответ должен быть строго в формате JSON.";

		$user_prompt = "Название статьи: \"" . get_the_title( $post ) . "\"\n\n";
		$user_prompt .= "Список изображений:\n" . implode( "\n", $prompt_list ) . "\n\n";
		$user_prompt .= "Верни JSON объект со списком alts:\n";
		$user_prompt .= "{\n  \"items\": [\n    {\"index\": 1, \"alt\": \"Описание первой картинки\"},\n    {\"index\": 2, \"alt\": \"Описание второй картинки\"}\n  ]\n}";

		$messages = array(
			array( 'role' => 'system', 'content' => $system_prompt ),
			array( 'role' => 'user', 'content' => $user_prompt ),
		);

		$ai_res = self::chat_completion( $messages, array( 'json_mode' => true, 'temperature' => 0.6 ) );
		if ( is_wp_error( $ai_res ) ) {
			return array(
				'success' => false,
				'message' => $ai_res->get_error_message(),
			);
		}

		$data = json_decode( $ai_res['content'], true );
		if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'AI вернул некорректный формат ответа.', 'seo-core-best' ),
			);
		}

		// Map index to alt
		$alt_map = array();
		foreach ( $data['items'] as $item ) {
			if ( isset( $item['index'] ) && ! empty( $item['alt'] ) ) {
				$alt_map[ intval( $item['index'] ) ] = sanitize_text_field( trim( $item['alt'] ) );
			}
		}

		// Replace in post content
		$updated_count = 0;
		$new_content = $content;

		foreach ( $images_to_process as $item ) {
			$idx = $item['index'];
			if ( empty( $alt_map[ $idx ] ) ) {
				continue;
			}

			$new_alt = esc_attr( $alt_map[ $idx ] );
			$old_tag = $item['tag_html'];

			if ( preg_match( '/\balt=[\'"][^\'"]*[\'"]/i', $old_tag ) ) {
				$new_tag = preg_replace( '/\balt=[\'"][^\'"]*[\'"]/i', 'alt="' . $new_alt . '"', $old_tag );
			} else {
				$new_tag = preg_replace( '/<img\b/i', '<img alt="' . $new_alt . '"', $old_tag );
			}

			if ( $new_tag !== $old_tag ) {
				$new_content = str_replace( $old_tag, $new_tag, $new_content );
				$updated_count++;
			}

			// Also update media library attachment meta if exists
			$attach_id = attachment_url_to_postid( $item['src'] );
			if ( $attach_id > 0 ) {
				update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_map[ $idx ] ) );
			}
		}

		// Save updated post
		if ( $updated_count > 0 && $new_content !== $content ) {
			wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $new_content,
			) );

			// Clear analysis cache for this URL
			$cache_key = 'seo_audit_' . md5( get_permalink( $post->ID ) );
			delete_transient( $cache_key );
		}

		return array(
			'success'       => true,
			'message'       => sprintf( __( 'AI успешно заполнил Alt-описания для %d изображений!', 'seo-core-best' ), $updated_count ),
			'updated_count' => $updated_count,
			'items'         => $alt_map,
		);
	}

	/**
	 * Generate high-CTR Title and Meta Description for a post
	 *
	 * @param int $post_id
	 * @return array
	 */
	public static function generate_seo_metadata( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'Пост не найден.', 'seo-core-best' ),
			);
		}

		$post_title = get_the_title( $post );
		$body_clean = strip_tags( $post->post_content );
		$body_short = mb_substr( trim( preg_replace( '/\s+/u', ' ', $body_clean ) ), 0, 1800, 'UTF-8' );

		$system_prompt = "Ты топовый On-Page SEO специалист с огромным опытом создания заголовков с высоким CTR для поисковиков Google и Яндекс. На основе предоставленного текста статьи напиши идеальный SEO Title и Meta Description на русском языке.\nТребования к Title:\n- Длина: строго от 45 до 65 символов с пробелами\n- Привлекательный, кликабельный, точный интент\nТребования к Description:\n- Длина: строго от 135 до 160 символов с пробелами\n- Цепляющий анонс статьи с конкретной пользой и призывом к прочтению\nОтвет верни строго в формате JSON: {\"title\": \"...\", \"description\": \"...\"}";

		$user_prompt = "Заголовок статьи: \"" . $post_title . "\"\n\nФрагмент текста статьи:\n\"" . $body_short . "\"\n\nСгенерируй SEO Title и Meta Description в формате JSON.";

		$messages = array(
			array( 'role' => 'system', 'content' => $system_prompt ),
			array( 'role' => 'user', 'content' => $user_prompt ),
		);

		$ai_res = self::chat_completion( $messages, array( 'json_mode' => true, 'temperature' => 0.7 ) );
		if ( is_wp_error( $ai_res ) ) {
			return array(
				'success' => false,
				'message' => $ai_res->get_error_message(),
			);
		}

		$data = json_decode( $ai_res['content'], true );
		if ( empty( $data['title'] ) || empty( $data['description'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Не удалось разобрать JSON ответ AI.', 'seo-core-best' ),
			);
		}

		$title = sanitize_text_field( trim( $data['title'] ) );
		$desc  = sanitize_textarea_field( trim( $data['description'] ) );

		return array(
			'success'     => true,
			'title'       => $title,
			'description' => $desc,
			'message'     => __( 'SEO мета-теги успешно сгенерированы AI!', 'seo-core-best' ),
		);
	}

	/**
	 * AJAX: Generate Image Alts
	 */
	public function ajax_generate_alts() {
		check_ajax_referer( 'seo_core_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'seo-core-best' ) ) );
		}

		$post_id = ! empty( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$url     = ! empty( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';

		// If post_id is not passed, try to detect from URL
		if ( ! $post_id && ! empty( $url ) ) {
			$post_id = url_to_postid( $url );
		}

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Не удалось определить ID записи для генерации alt-тегов.', 'seo-core-best' ) ) );
		}

		$result = self::generate_image_alts_for_post( $post_id );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Generate Title & Description
	 */
	public function ajax_generate_meta() {
		check_ajax_referer( 'seo_core_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'seo-core-best' ) ) );
		}

		$post_id = ! empty( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'ID записи не указан.', 'seo-core-best' ) ) );
		}

		$result = self::generate_seo_metadata( $post_id );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Test AI Connection
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'seo_core_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'seo-core-best' ) ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : 'deepseek';
		$key      = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';

		$result = self::test_connection( $provider, $key );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Robust multi-tier URL to Post ID resolver
	 *
	 * @param string $url
	 * @return int Post ID or 0
	 */
	public static function resolve_post_id_from_url( $url ) {
		$url = esc_url_raw( trim( $url ) );
		if ( empty( $url ) ) {
			return 0;
		}

		// 1. Standard WP function
		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		// 2. Try with and without trailing slash
		$alt_url = ( substr( $url, -1 ) === '/' ) ? rtrim( $url, '/' ) : $url . '/';
		$post_id = url_to_postid( $alt_url );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		// 3. Extract path and resolve via get_page_by_path
		$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( ! empty( $path ) ) {
			// Check standard pages
			$page = get_page_by_path( $path );
			if ( $page && isset( $page->ID ) ) {
				return intval( $page->ID );
			}

			// Check all public post types (posts, pages, products, dwqa, ali_sale, etc.)
			$post_types = get_post_types( array( 'public' => true ) );
			foreach ( $post_types as $pt ) {
				$p = get_page_by_path( $path, OBJECT, $pt );
				if ( $p && isset( $p->ID ) ) {
					return intval( $p->ID );
				}
			}

			// 4. Try matching slug from the last segment in wp_posts
			$segments = explode( '/', $path );
			$slug = end( $segments );
			if ( ! empty( $slug ) ) {
				global $wpdb;
				$found_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status IN ('publish', 'draft', 'private', 'future') ORDER BY ID DESC LIMIT 1",
					$slug
				) );
				if ( $found_id ) {
					return intval( $found_id );
				}
			}
		}

		return 0;
	}

	/**
	 * Purge all caches for a specific post and URL
	 *
	 * @param int $post_id
	 * @param string $url
	 */
	public static function purge_caches( $post_id, $url = '' ) {
		if ( $post_id > 0 ) {
			clean_post_cache( $post_id );

			// 3rd party cache plugins purge
			if ( function_exists( 'wp_cache_post_change' ) ) {
				wp_cache_post_change( $post_id );
			}
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $post_id );
			}
			if ( has_action( 'litespeed_purge_post' ) ) {
				do_action( 'litespeed_purge_post', $post_id );
			}
			if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
				w3tc_pgcache_flush_post( $post_id );
			}
		}

		// Clear Analyzer transients
		$urls_to_clear = array();
		if ( ! empty( $url ) ) {
			$urls_to_clear[] = $url;
			$urls_to_clear[] = rtrim( $url, '/' );
			$urls_to_clear[] = rtrim( $url, '/' ) . '/';
		}
		if ( $post_id > 0 ) {
			$perm = get_permalink( $post_id );
			if ( $perm ) {
				$urls_to_clear[] = $perm;
				$urls_to_clear[] = rtrim( $perm, '/' );
				$urls_to_clear[] = rtrim( $perm, '/' ) . '/';
			}
		}

		foreach ( array_unique( $urls_to_clear ) as $u ) {
			delete_transient( 'seo_audit_' . md5( $u ) );
		}
	}

	/**
	 * AJAX: Fix specific issue from SEO Analyzer (title, description, alts)
	 */
	public function ajax_fix_issue() {
		check_ajax_referer( 'seo_core_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'seo-core-best' ) ) );
		}

		$fix_type = ! empty( $_POST['fix_type'] ) ? sanitize_text_field( $_POST['fix_type'] ) : '';
		$url      = ! empty( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
		$post_id  = ! empty( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id && ! empty( $url ) ) {
			$post_id = self::resolve_post_id_from_url( $url );
		}

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Не удалось определить запись WordPress по данному URL (возможно это главная страница сайта или страница архива). Авто-исправление доступно для статей и страниц.', 'seo-core-best' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Запись не найдена в базе данных.', 'seo-core-best' ) ) );
		}

		if ( 'alts' === $fix_type ) {
			$res = self::generate_image_alts_for_post( $post_id );
			if ( $res['success'] ) {
				self::purge_caches( $post_id, $url );
				wp_send_json_success( $res );
			} else {
				wp_send_json_error( $res );
			}
		}

		if ( 'description' === $fix_type ) {
			$post_title = get_the_title( $post );
			$body_clean = strip_tags( $post->post_content );
			$body_short = mb_substr( trim( preg_replace( '/\s+/u', ' ', $body_clean ) ), 0, 1800, 'UTF-8' );

			$system_prompt = "Ты лучший On-Page SEO специалист. Напиши привлекательное Meta Description для статьи на русском языке.\nТребования:\n- Длина: строго от 135 до 160 символов с пробелами\n- Цепляющее, раскрывающее пользу статьи\nОтвет верни строго в формате JSON: {\"description\": \"...\"}";
			$user_prompt   = "Название статьи: \"" . $post_title . "\"\nТекст:\n\"" . $body_short . "\"\nСгенерируй Description.";

			$messages = array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			);

			$ai_res = self::chat_completion( $messages, array( 'json_mode' => true, 'temperature' => 0.7 ) );
			if ( is_wp_error( $ai_res ) ) {
				wp_send_json_error( array( 'message' => $ai_res->get_error_message() ) );
			}

			$data = json_decode( $ai_res['content'], true );
			if ( empty( $data['description'] ) ) {
				wp_send_json_error( array( 'message' => __( 'AI не вернул поле description.', 'seo-core-best' ) ) );
			}

			$new_desc = sanitize_textarea_field( trim( $data['description'] ) );

			// Save to both native and Rank Math meta keys to guarantee persistence
			update_post_meta( $post_id, '_seo_description', $new_desc );
			update_post_meta( $post_id, 'rank_math_description', $new_desc );

			// Also update post excerpt if post had manual excerpt to prevent template override
			if ( ! empty( $post->post_excerpt ) ) {
				wp_update_post( array(
					'ID'           => $post_id,
					'post_excerpt' => $new_desc,
				) );
			}

			self::purge_caches( $post_id, $url );

			wp_send_json_success( array(
				'message'     => sprintf( __( 'Мета-описание успешно оптимизировано (%d знаков) для «%s» и сохранено!', 'seo-core-best' ), mb_strlen( $new_desc, 'UTF-8' ), esc_html( $post_title ) ),
				'description' => $new_desc,
				'post_id'     => $post_id,
			) );
		}

		if ( 'title' === $fix_type ) {
			$post_title = get_the_title( $post );
			$body_clean = strip_tags( $post->post_content );
			$body_short = mb_substr( trim( preg_replace( '/\s+/u', ' ', $body_clean ) ), 0, 1800, 'UTF-8' );

			$system_prompt = "Ты ведущий On-Page SEO специалист. Напиши привлекательный кликабельный SEO Title для статьи на русском языке.\nТребования:\n- Длина: строго от 45 до 65 символов с пробелами\n- Релевантный теме и высокий CTR\nОтвет верни строго в формате JSON: {\"title\": \"...\"}";
			$user_prompt   = "Название статьи: \"" . $post_title . "\"\nТекст:\n\"" . $body_short . "\"\nСгенерируй Title.";

			$messages = array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			);

			$ai_res = self::chat_completion( $messages, array( 'json_mode' => true, 'temperature' => 0.7 ) );
			if ( is_wp_error( $ai_res ) ) {
				wp_send_json_error( array( 'message' => $ai_res->get_error_message() ) );
			}

			$data = json_decode( $ai_res['content'], true );
			if ( empty( $data['title'] ) ) {
				wp_send_json_error( array( 'message' => __( 'AI не вернул поле title.', 'seo-core-best' ) ) );
			}

			$new_title = sanitize_text_field( trim( $data['title'] ) );

			// Save to both native and Rank Math meta keys
			update_post_meta( $post_id, '_seo_title', $new_title );
			update_post_meta( $post_id, 'rank_math_title', $new_title );

			self::purge_caches( $post_id, $url );

			wp_send_json_success( array(
				'message' => sprintf( __( 'SEO Title успешно оптимизирован (%d знаков): «%s»', 'seo-core-best' ), mb_strlen( $new_title, 'UTF-8' ), $new_title ),
				'title'   => $new_title,
				'post_id' => $post_id,
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Неизвестный тип исправления.', 'seo-core-best' ) ) );
	}
}
