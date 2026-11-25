<?php
/**
 * 301/302 Redirections Engine
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Redirects {

	/**
	 * Redirections table name
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_redirects';
	}

	public function __construct() {
		if ( Seo_Core_Options::get( 'enable_redirects', 1 ) ) {
			add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
			add_action( 'wp_footer', array( $this, 'render_quick_redirect_modal' ) );
			add_action( 'wp_ajax_seo_core_quick_redirect', array( $this, 'ajax_quick_redirect' ) );
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
			url_from varchar(500) NOT NULL,
			url_to text NOT NULL,
			match_type varchar(20) NOT NULL DEFAULT 'exact',
			redirect_code smallint(4) NOT NULL DEFAULT 301,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_accessed datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY url_from (url_from(191))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Intercept incoming request and execute 301/302 redirection if matched
	 */
	public function handle_redirect() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
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

		$clean_path = trim( $parsed_path, '/' );

		global $wpdb;
		$table = self::get_table_name();

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
			return;
		}

		// 1. Exact match check (fastest)
		// Try matching both with and without leading/trailing slashes
		$exact_candidates = array(
			$clean_path,
			$clean_path . '/',
			'/' . $clean_path,
			'/' . $clean_path . '/',
		);
		$exact_candidates = array_unique( $exact_candidates );
		$placeholders     = implode( ',', array_fill( 0, count( $exact_candidates ), '%s' ) );

		$query = $wpdb->prepare(
			"SELECT id, url_to, redirect_code, match_type FROM {$table} 
			 WHERE status = 'active' AND match_type = 'exact' AND url_from IN ($placeholders) 
			 ORDER BY id DESC LIMIT 1",
			$exact_candidates
		);

		$match = $wpdb->get_row( $query, ARRAY_A );

		// 2. Regex match check if exact match not found
		if ( ! $match ) {
			$regex_rules = $wpdb->get_results(
				"SELECT id, url_from, url_to, redirect_code FROM {$table} 
				 WHERE status = 'active' AND match_type = 'regex' 
				 ORDER BY id ASC LIMIT 100",
				ARRAY_A
			);

			if ( ! empty( $regex_rules ) ) {
				foreach ( $regex_rules as $rule ) {
					$pattern = $rule['url_from'];
					// Add delimiters if missing
					if ( substr( $pattern, 0, 1 ) !== '/' && substr( $pattern, 0, 1 ) !== '#' ) {
						$pattern = '#' . $pattern . '#i';
					}

					if ( @preg_match( $pattern, $parsed_path ) || @preg_match( $pattern, $clean_path ) ) {
						$match = $rule;
						break;
					}
				}
			}
		}

		// 3. Contains match check
		if ( ! $match ) {
			$contains_rules = $wpdb->get_results(
				"SELECT id, url_from, url_to, redirect_code FROM {$table} 
				 WHERE status = 'active' AND match_type = 'contains' 
				 ORDER BY id ASC LIMIT 50",
				ARRAY_A
			);

			if ( ! empty( $contains_rules ) ) {
				foreach ( $contains_rules as $rule ) {
					if ( false !== stripos( $parsed_path, $rule['url_from'] ) ) {
						$match = $rule;
						break;
					}
				}
			}
		}

		// Execute redirect
		if ( $match && ! empty( $match['url_to'] ) ) {
			$dest_url = trim( $match['url_to'] );
			$code     = ! empty( $match['redirect_code'] ) ? intval( $match['redirect_code'] ) : 301;

			// If destination is relative, prepend home_url
			if ( 0 !== strpos( $dest_url, 'http://' ) && 0 !== strpos( $dest_url, 'https://' ) ) {
				$dest_url = home_url( '/' . ltrim( $dest_url, '/' ) );
			}

			// Prevent redirect loops
			$dest_path = wp_parse_url( $dest_url, PHP_URL_PATH );
			if ( untrailingslashit( $dest_path ) === untrailingslashit( $parsed_path ) ) {
				return;
			}

			// Record hits
			if ( Seo_Core_Options::get( 'keep_redirect_stats', 1 ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET hits = hits + 1, last_accessed = NOW() WHERE id = %d",
						$match['id']
					)
				);
			}

			wp_redirect( $dest_url, $code );
			exit;
		}

		// 404 Fallback if enabled
		if ( is_404() && Seo_Core_Options::get( 'redirect_fallback_404', 0 ) ) {
			$fallback = Seo_Core_Options::get( 'redirect_fallback_url', '' );
			if ( ! empty( $fallback ) ) {
				wp_redirect( $fallback, 301 );
				exit;
			}
		}
	}

	/**
	 * Add or update single redirection
	 *
	 * @param string $from
	 * @param string $to
	 * @param int    $code
	 * @param string $type
	 * @return int|false
	 */
	public static function add( $from, $to, $code = 301, $type = 'exact' ) {
		global $wpdb;
		$table = self::get_table_name();

		$from = trim( $from );
		$to   = trim( $to );

		if ( empty( $from ) || empty( $to ) ) {
			return false;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'url_from'      => $from,
				'url_to'        => $to,
				'match_type'    => in_array( $type, array( 'exact', 'regex', 'contains' ) ) ? $type : 'exact',
				'redirect_code' => intval( $code ),
				'status'        => 'active',
				'hits'          => 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Delete a redirection by ID
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
	 * Bulk import redirections in batches
	 *
	 * @param array $items Array of arrays ['from', 'to', 'code', 'type', 'hits', 'status']
	 * @return int Number of inserted rows
	 */
	public static function bulk_import( array $items ) {
		global $wpdb;
		$table = self::get_table_name();
		self::install_table();

		if ( empty( $items ) ) {
			return 0;
		}

		$inserted = 0;
		$batch_size = 200;
		$chunks = array_chunk( $items, $batch_size );

		foreach ( $chunks as $chunk ) {
			$values = array();
			$placeholders = array();

			foreach ( $chunk as $row ) {
				$from   = trim( $row['from'] );
				$to     = trim( $row['to'] );
				$code   = isset( $row['code'] ) ? intval( $row['code'] ) : 301;
				$type   = isset( $row['type'] ) ? $row['type'] : 'exact';
				$hits   = isset( $row['hits'] ) ? intval( $row['hits'] ) : 0;
				$status = isset( $row['status'] ) ? $row['status'] : 'active';

				if ( empty( $from ) || empty( $to ) ) {
					continue;
				}

				$placeholders[] = "(%s, %s, %s, %d, %d, %s, NOW())";
				$values[]       = $from;
				$values[]       = $to;
				$values[]       = $type;
				$values[]       = $code;
				$values[]       = $hits;
				$values[]       = $status;
			}

			if ( ! empty( $placeholders ) ) {
				$query = "INSERT INTO {$table} (url_from, url_to, match_type, redirect_code, hits, status, created_at) VALUES " . implode( ', ', $placeholders );
				$wpdb->query( $wpdb->prepare( $query, $values ) );
				$inserted += count( $placeholders );
			}
		}

		return $inserted;
	}

	/**
	 * Get total count
	 *
	 * @return int
	 */
	public static function get_count() {
		global $wpdb;
		$table = self::get_table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
	}

	/**
	 * Add "Redirect this page" button to WordPress Admin Bar on Frontend
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$request_uri = ! empty( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		$clean_path  = ltrim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

		if ( is_404() ) {
			$title = '<span style="color: #fbbf24; font-weight: 700;">⚠️ Редирект 404</span>';
		} else {
			$title = '🔀 Сделать редирект';
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'seo-core-redirect-this',
			'title' => $title,
			'href'  => admin_url( 'admin.php?page=seo-core-best&tab=redirects&from=' . urlencode( $clean_path ) ),
			'meta'  => array(
				'class' => 'seo-core-admin-bar-btn',
				'title' => __( 'Создать 301-редирект для текущего адреса', 'seo-core-best' ),
			),
		) );
	}

	/**
	 * Render Quick Redirect Modal in wp_footer for Admins
	 */
	public function render_quick_redirect_modal() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$request_uri = ! empty( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		$clean_path  = ltrim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
		$is_page_404 = is_404();
		$nonce       = wp_create_nonce( 'seo_core_quick_redirect_nonce' );
		?>
		<!-- SEO Core Best: Quick 404/Page Redirect Modal -->
		<div id="seo-core-modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(4px); z-index: 999998;"></div>
		<div id="seo-core-redirect-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 540px; background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25), 0 8px 10px -6px rgba(0,0,0,0.25); z-index: 999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; overflow: hidden; box-sizing: border-box;">
			<div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
				<h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
					<?php if ( $is_page_404 ) : ?>
						<span>⚠️ Перенаправление с 404 страницы</span>
					<?php else : ?>
						<span>🔀 Создать 301-редирект</span>
					<?php endif; ?>
				</h3>
				<button type="button" id="seo-core-modal-close" style="background: transparent; border: none; color: #94a3b8; font-size: 24px; line-height: 1; cursor: pointer; padding: 0 4px;" title="Закрыть">&times;</button>
			</div>

			<form id="seo-core-quick-form" style="padding: 22px 24px; margin: 0;">
				<input type="hidden" name="action" value="seo_core_quick_redirect" />
				<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />

				<div style="margin-bottom: 16px;">
					<label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Откуда (Исходный адрес / Путь):</label>
					<input type="text" name="redirect_from" id="seo_core_redirect_from" value="<?php echo esc_attr( $clean_path ); ?>" required style="width: 100%; box-sizing: border-box; padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; color: #334155; font-family: monospace;" />
				</div>

				<div style="margin-bottom: 16px;">
					<label style="display: block; font-size: 13px; font-weight: 600; color: #0f172a; margin-bottom: 6px;">Куда (Целевой адрес перенаправления):</label>
					<input type="text" name="redirect_to" id="seo_core_redirect_to" placeholder="https://aliprofi.ru/new-page/ или /new-page/" required style="width: 100%; box-sizing: border-box; padding: 10px 12px; font-size: 14px; border: 2px solid #3b82f6; border-radius: 6px; outline: none; background: #ffffff;" autofocus />
				</div>

				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
					<div>
						<label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Код перенаправления:</label>
						<select name="redirect_code" style="width: 100%; box-sizing: border-box; padding: 8px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
							<option value="301" selected>301 (Постоянный / Permanent)</option>
							<option value="302">302 (Временный / Temporary)</option>
							<option value="307">307 (Временный / 307)</option>
						</select>
					</div>
					<div>
						<label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Тип совпадения:</label>
						<select name="redirect_match_type" style="width: 100%; box-sizing: border-box; padding: 8px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
							<option value="exact" selected>Exact (Точное совпадение)</option>
							<option value="contains">Contains (Частичное)</option>
							<option value="regex">Regex (Регулярное выражение)</option>
						</select>
					</div>
				</div>

				<div id="seo-core-quick-msg" style="display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px;"></div>

				<div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-core-best&tab=redirects&from=' . urlencode( $clean_path ) ) ); ?>" style="font-size: 13px; color: #64748b; text-decoration: none; margin-right: auto;">Открыть в админке &rarr;</a>
					<button type="button" id="seo-core-btn-cancel" style="padding: 9px 16px; font-size: 14px; border: 1px solid #cbd5e1; background: #f8fafc; color: #475569; border-radius: 6px; cursor: pointer;">Отмена</button>
					<button type="submit" id="seo-core-btn-submit" style="padding: 9px 20px; font-size: 14px; font-weight: 600; border: none; background: #2563eb; color: #ffffff; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
						<span>Сохранить и перейти</span>
					</button>
				</div>
			</form>
		</div>

		<script>
		(function() {
			var triggerBtn = document.getElementById('wp-admin-bar-seo-core-redirect-this');
			var modal = document.getElementById('seo-core-redirect-modal');
			var backdrop = document.getElementById('seo-core-modal-backdrop');
			var closeBtn = document.getElementById('seo-core-modal-close');
			var cancelBtn = document.getElementById('seo-core-btn-cancel');
			var form = document.getElementById('seo-core-quick-form');
			var toInput = document.getElementById('seo_core_redirect_to');
			var submitBtn = document.getElementById('seo-core-btn-submit');
			var msgBox = document.getElementById('seo-core-quick-msg');

			function openModal(e) {
				if (e) e.preventDefault();
				modal.style.display = 'block';
				backdrop.style.display = 'block';
				setTimeout(function() {
					if (toInput) toInput.focus();
				}, 100);
			}

			function closeModal() {
				modal.style.display = 'none';
				backdrop.style.display = 'none';
				msgBox.style.display = 'none';
			}

			if (triggerBtn) {
				triggerBtn.addEventListener('click', openModal);
			}
			if (closeBtn) closeBtn.addEventListener('click', closeModal);
			if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
			if (backdrop) backdrop.addEventListener('click', closeModal);

			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && modal.style.display === 'block') {
					closeModal();
				}
			});

			if (form) {
				form.addEventListener('submit', function(e) {
					e.preventDefault();
					var toVal = toInput.value.trim();
					if (!toVal) {
						toInput.focus();
						return;
					}

					submitBtn.disabled = true;
					submitBtn.innerHTML = 'Сохранение...';

					var formData = new FormData(form);

					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: formData
					})
					.then(function(res) { return res.json(); })
					.then(function(data) {
						if (data && data.success) {
							msgBox.style.display = 'block';
							msgBox.style.background = '#dcfce7';
							msgBox.style.color = '#15803d';
							msgBox.style.border = '1px solid #bbf7d0';
							msgBox.innerHTML = '✔ Редирект успешно сохранен! Перенаправление...';

							setTimeout(function() {
								window.location.href = data.data.redirect_to;
							}, 800);
						} else {
							submitBtn.disabled = false;
							submitBtn.innerHTML = 'Сохранить и перейти';
							msgBox.style.display = 'block';
							msgBox.style.background = '#fee2e2';
							msgBox.style.color = '#b91c1c';
							msgBox.style.border = '1px solid #fecaca';
							msgBox.innerHTML = (data && data.data && data.data.message) ? data.data.message : 'Ошибка при сохранении редиректа.';
						}
					})
					.catch(function(err) {
						submitBtn.disabled = false;
						submitBtn.innerHTML = 'Сохранить и перейти';
						msgBox.style.display = 'block';
						msgBox.style.background = '#fee2e2';
						msgBox.style.color = '#b91c1c';
						msgBox.innerHTML = 'Сетевая ошибка при запросе к серверу.';
					});
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * AJAX Handler for Quick Redirect creation
	 */
	public function ajax_quick_redirect() {
		check_ajax_referer( 'seo_core_quick_redirect_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'У вас недостаточно прав.', 'seo-core-best' ) ) );
		}

		$from = isset( $_POST['redirect_from'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_from'] ) ) : '';
		$to   = isset( $_POST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : '';
		$code = isset( $_POST['redirect_code'] ) ? intval( $_POST['redirect_code'] ) : 301;
		$type = isset( $_POST['redirect_match_type'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_match_type'] ) ) : 'exact';

		if ( empty( $from ) || empty( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Заполните оба поля (Откуда и Куда).', 'seo-core-best' ) ) );
		}

		$insert_id = self::add( $from, $to, $code, $type );

		if ( $insert_id ) {
			// Resolve target redirect url
			$target_url = $to;
			if ( 0 !== strpos( $target_url, 'http://' ) && 0 !== strpos( $target_url, 'https://' ) ) {
				$target_url = home_url( '/' . ltrim( $target_url, '/' ) );
			}

			wp_send_json_success( array(
				'id'          => $insert_id,
				'redirect_to' => $target_url,
				'message'     => __( '301-редирект успешно создан!', 'seo-core-best' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Не удалось сохранить редирект в базу данных.', 'seo-core-best' ) ) );
		}
	}
}
