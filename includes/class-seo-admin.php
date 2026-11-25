<?php
/**
 * Admin Dashboard & Settings Page
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register menu item
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'SEO Core Best', 'seo-core-best' ),
			__( 'SEO Core', 'seo-core-best' ),
			'manage_options',
			'seo-core-best',
			array( $this, 'render_admin_page' ),
			'dashicons-chart-line',
			99
		);
	}

	/**
	 * Enqueue assets for admin page
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_seo-core-best' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'seo-core-admin',
			plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'seo-core-admin',
			plugins_url( 'assets/js/admin.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);
	}

	/**
	 * Handle admin post actions (Save settings, Run Migration, Import JSON, Add/Delete Redirect)
	 */
	public function handle_actions() {
		if ( ! isset( $_POST['seo_core_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'seo_core_admin_action', 'seo_core_admin_nonce' );

		$action = sanitize_text_field( $_POST['seo_core_action'] );

		// 1. Save Settings
		if ( 'save_settings' === $action ) {
			$options = Seo_Core_Options::get_all();
			$tab     = ! empty( $_POST['seo_core_tab'] ) ? sanitize_key( $_POST['seo_core_tab'] ) : '';

			// General Settings Tab
			if ( 'general' === $tab || ( empty( $tab ) && isset( $_POST['title_separator'] ) ) ) {
				$old_strip                          = ! empty( $options['strip_category_base'] ) ? 1 : 0;
				$new_strip                          = ! empty( $_POST['strip_category_base'] ) ? 1 : 0;
				$options['strip_category_base']     = $new_strip;

				$options['title_separator']         = sanitize_text_field( $_POST['title_separator'] );
				$options['org_name']                = sanitize_text_field( $_POST['org_name'] );
				$options['org_logo']                = esc_url_raw( $_POST['org_logo'] );
				$options['org_url']                 = esc_url_raw( $_POST['org_url'] );
				$options['nofollow_external_links'] = ! empty( $_POST['nofollow_external_links'] ) ? 1 : 0;
				$options['open_external_blank']     = ! empty( $_POST['open_external_blank'] ) ? 1 : 0;
				$options['enable_breadcrumbs']      = ! empty( $_POST['enable_breadcrumbs'] ) ? 1 : 0;
				$options['breadcrumbs_separator']   = sanitize_text_field( $_POST['breadcrumbs_separator'] );

				if ( $old_strip !== $new_strip ) {
					flush_rewrite_rules();
				}
			}

			// Titles & Meta Tab
			if ( 'titles' === $tab || ( empty( $tab ) && isset( $_POST['homepage_title'] ) ) ) {
				$options['homepage_title']          = sanitize_text_field( $_POST['homepage_title'] );
				$options['homepage_description']    = sanitize_textarea_field( $_POST['homepage_description'] );
				$options['post_title_template']     = sanitize_text_field( $_POST['post_title_template'] );
				$options['post_desc_template']      = sanitize_textarea_field( $_POST['post_desc_template'] );
				$options['page_title_template']     = sanitize_text_field( $_POST['page_title_template'] );
				$options['page_desc_template']      = sanitize_textarea_field( $_POST['page_desc_template'] );
				$options['category_title_template'] = sanitize_text_field( $_POST['category_title_template'] );
				$options['category_desc_template']  = sanitize_textarea_field( $_POST['category_desc_template'] );
				$options['tag_title_template']      = sanitize_text_field( $_POST['tag_title_template'] );
				$options['tag_desc_template']       = sanitize_textarea_field( $_POST['tag_desc_template'] );

				// Custom Post Types Templates
				if ( ! empty( $_POST['cpt_templates'] ) && is_array( $_POST['cpt_templates'] ) ) {
					foreach ( $_POST['cpt_templates'] as $cpt_key => $tpl_data ) {
						$cpt_key = sanitize_key( $cpt_key );
						if ( isset( $tpl_data['title'] ) ) {
							$options['cpt_templates'][ $cpt_key ]['title'] = sanitize_text_field( $tpl_data['title'] );
						}
						if ( isset( $tpl_data['desc'] ) ) {
							$options['cpt_templates'][ $cpt_key ]['desc'] = sanitize_textarea_field( $tpl_data['desc'] );
						}
					}
				}

				// Custom Taxonomies Templates
				if ( ! empty( $_POST['tax_templates'] ) && is_array( $_POST['tax_templates'] ) ) {
					foreach ( $_POST['tax_templates'] as $tax_key => $tpl_data ) {
						$tax_key = sanitize_key( $tax_key );
						if ( isset( $tpl_data['title'] ) ) {
							$options['tax_templates'][ $tax_key ]['title'] = sanitize_text_field( $tpl_data['title'] );
						}
						if ( isset( $tpl_data['desc'] ) ) {
							$options['tax_templates'][ $tax_key ]['desc'] = sanitize_textarea_field( $tpl_data['desc'] );
						}
					}
				}
			}

			// Social & OpenGraph Tab
			if ( 'social' === $tab || isset( $_POST['enable_opengraph'] ) || isset( $_POST['social_telegram'] ) ) {
				$options['enable_opengraph']     = ! empty( $_POST['enable_opengraph'] ) ? 1 : 0;
				$options['enable_twitter_cards'] = ! empty( $_POST['enable_twitter_cards'] ) ? 1 : 0;
				$options['twitter_card_type']    = ! empty( $_POST['twitter_card_type'] ) ? sanitize_text_field( $_POST['twitter_card_type'] ) : 'summary_large_image';
				$options['default_og_image']     = ! empty( $_POST['default_og_image'] ) ? esc_url_raw( $_POST['default_og_image'] ) : '';
				$options['social_telegram']      = ! empty( $_POST['social_telegram'] ) ? sanitize_text_field( trim( $_POST['social_telegram'] ) ) : '';
				$options['social_vk']            = ! empty( $_POST['social_vk'] ) ? esc_url_raw( trim( $_POST['social_vk'] ) ) : '';
				$options['social_youtube']       = ! empty( $_POST['social_youtube'] ) ? esc_url_raw( trim( $_POST['social_youtube'] ) ) : '';
				$options['social_twitter']       = ! empty( $_POST['social_twitter'] ) ? sanitize_text_field( trim( $_POST['social_twitter'] ) ) : '';
				$options['social_facebook']      = ! empty( $_POST['social_facebook'] ) ? esc_url_raw( trim( $_POST['social_facebook'] ) ) : '';
			}

			// Sitemap Tab
			if ( 'sitemap' === $tab || ( empty( $tab ) && isset( $_POST['enable_sitemap'] ) ) ) {
				$options['enable_sitemap']          = ! empty( $_POST['enable_sitemap'] ) ? 1 : 0;
				$options['sitemap_items_per_page']  = max( 50, intval( $_POST['sitemap_items_per_page'] ) );
				$options['sitemap_include_images']  = ! empty( $_POST['sitemap_include_images'] ) ? 1 : 0;
				$options['sitemap_post_types']      = ! empty( $_POST['sitemap_post_types'] ) && is_array( $_POST['sitemap_post_types'] ) ? array_map( 'sanitize_text_field', $_POST['sitemap_post_types'] ) : array( 'post', 'page' );
				$options['sitemap_taxonomies']      = ! empty( $_POST['sitemap_taxonomies'] ) && is_array( $_POST['sitemap_taxonomies'] ) ? array_map( 'sanitize_text_field', $_POST['sitemap_taxonomies'] ) : array( 'category' );
			}

			// 404 Monitor Settings Tab
			if ( 'monitor404' === $tab || isset( $_POST['enable_404_monitor'] ) ) {
				$options['enable_404_monitor']      = ! empty( $_POST['enable_404_monitor'] ) ? 1 : 0;
				$options['log_404_images']          = ! empty( $_POST['log_404_images'] ) ? 1 : 0;
			}

			// Image SEO (Auto Alt & Title) Tab
			if ( 'images' === $tab || isset( $_POST['enable_auto_image_alt'] ) || isset( $_POST['auto_image_alt_template'] ) ) {
				$options['enable_auto_image_alt']     = ! empty( $_POST['enable_auto_image_alt'] ) ? 1 : 0;
				$options['auto_image_alt_template']   = ! empty( $_POST['auto_image_alt_template'] ) ? sanitize_text_field( $_POST['auto_image_alt_template'] ) : '%title%';
				$options['enable_auto_image_title']   = ! empty( $_POST['enable_auto_image_title'] ) ? 1 : 0;
				$options['auto_image_title_template'] = ! empty( $_POST['auto_image_title_template'] ) ? sanitize_text_field( $_POST['auto_image_title_template'] ) : '%title%';
			}

			// AI Assistant (DeepSeek & Mistral) Tab
			if ( 'ai' === $tab || isset( $_POST['ai_provider'] ) ) {
				if ( isset( $_POST['ai_provider'] ) ) {
					$options['ai_provider'] = sanitize_text_field( $_POST['ai_provider'] );
				}
				if ( isset( $_POST['deepseek_api_key'] ) ) {
					$options['deepseek_api_key'] = sanitize_text_field( trim( $_POST['deepseek_api_key'] ) );
				}
				if ( isset( $_POST['mistral_api_key'] ) ) {
					$options['mistral_api_key'] = sanitize_text_field( trim( $_POST['mistral_api_key'] ) );
				}
			}

			// Compat / Migration Tab
			if ( 'migration' === $tab || isset( $_POST['rank_math_compat_read'] ) || isset( $_POST['rank_math_compat_write'] ) ) {
				$options['rank_math_compat_read']   = ! empty( $_POST['rank_math_compat_read'] ) ? 1 : 0;
				$options['rank_math_compat_write']  = ! empty( $_POST['rank_math_compat_write'] ) ? 1 : 0;
			}

			Seo_Core_Options::update_all( $options );
			add_settings_error( 'seo_core_messages', 'seo_core_saved', __( 'Настройки успешно сохранены!', 'seo-core-best' ), 'updated' );
		}

		// 2. Import from DB
		if ( 'import_rank_math_db' === $action ) {
			$res = Seo_Core_Importer::import_from_db();
			add_settings_error( 'seo_core_messages', 'seo_core_imported', $res['message'], 'updated' );
		}

		// 3. Import from JSON File
		if ( 'import_rank_math_json' === $action ) {
			if ( ! empty( $_FILES['rank_math_json_file']['tmp_name'] ) ) {
				$json_content = file_get_contents( $_FILES['rank_math_json_file']['tmp_name'] );
				$res = Seo_Core_Importer::import_from_json( $json_content );
				if ( $res['success'] ) {
					add_settings_error( 'seo_core_messages', 'seo_core_imported', $res['message'], 'updated' );
				} else {
					add_settings_error( 'seo_core_messages', 'seo_core_error', $res['message'], 'error' );
				}
			} else {
				add_settings_error( 'seo_core_messages', 'seo_core_error', __( 'Пожалуйста, выберите файл .json для загрузки.', 'seo-core-best' ), 'error' );
			}
		}

		// 4. Add Redirection
		if ( 'add_redirect' === $action ) {
			$from = sanitize_text_field( $_POST['redirect_from'] );
			$to   = sanitize_text_field( $_POST['redirect_to'] );
			$code = intval( $_POST['redirect_code'] );
			$type = sanitize_text_field( $_POST['redirect_match_type'] );

			if ( Seo_Core_Redirects::add( $from, $to, $code, $type ) ) {
				add_settings_error( 'seo_core_messages', 'seo_core_redirect_added', __( '301-редирект успешно добавлен!', 'seo-core-best' ), 'updated' );
			} else {
				add_settings_error( 'seo_core_messages', 'seo_core_redirect_err', __( 'Ошибка при добавлении редиректа. Проверьте поля.', 'seo-core-best' ), 'error' );
			}
		}

		// 5. Delete Redirection
		if ( 'delete_redirect' === $action && ! empty( $_POST['redirect_id'] ) ) {
			$del_id = intval( $_POST['redirect_id'] );
			Seo_Core_Redirects::delete( $del_id );
			add_settings_error( 'seo_core_messages', 'seo_core_redirect_deleted', __( 'Редирект удален.', 'seo-core-best' ), 'updated' );
		}

		// 6. Delete 404 Log Item
		if ( 'delete_404_log' === $action && ! empty( $_POST['log_id'] ) ) {
			$log_id = intval( $_POST['log_id'] );
			Seo_Core_404_Monitor::delete( $log_id );
			add_settings_error( 'seo_core_messages', 'seo_core_404_deleted', __( 'Запись из лога 404 удалена.', 'seo-core-best' ), 'updated' );
		}

		// 7. Clear All 404 Logs
		if ( 'clear_all_404_logs' === $action ) {
			Seo_Core_404_Monitor::clear_all();
			add_settings_error( 'seo_core_messages', 'seo_core_404_cleared', __( 'Все логи 404 успешно очищены!', 'seo-core-best' ), 'updated' );
		}

		// 8. Save Google Indexing Settings
		if ( 'save_google_indexing' === $action ) {
			$options = Seo_Core_Options::get_all();

			// Key from uploaded file or textarea
			if ( ! empty( $_FILES['google_json_file']['tmp_name'] ) ) {
				$file_contents = file_get_contents( $_FILES['google_json_file']['tmp_name'] );
				$options['google_json_key'] = trim( $file_contents );
			} elseif ( isset( $_POST['google_json_key'] ) ) {
				$options['google_json_key'] = trim( wp_unslash( $_POST['google_json_key'] ) );
			}

			// Post types
			$options['google_index_post_types'] = ! empty( $_POST['google_index_post_types'] ) && is_array( $_POST['google_index_post_types'] ) 
				? array_map( 'sanitize_text_field', $_POST['google_index_post_types'] ) 
				: array();

			Seo_Core_Options::update_all( $options );
			add_settings_error( 'seo_core_messages', 'seo_core_google_saved', __( 'Настройки Google Indexing API успешно сохранены!', 'seo-core-best' ), 'updated' );
		}

		// 9. Save IndexNow Settings
		if ( 'save_indexnow_settings' === $action ) {
			$options = Seo_Core_Options::get_all();

			if ( ! empty( $_POST['indexnow_api_key'] ) ) {
				$options['indexnow_api_key'] = sanitize_text_field( trim( $_POST['indexnow_api_key'] ) );
			}

			$options['indexnow_post_types'] = ! empty( $_POST['indexnow_post_types'] ) && is_array( $_POST['indexnow_post_types'] ) 
				? array_map( 'sanitize_text_field', $_POST['indexnow_post_types'] ) 
				: array();

			Seo_Core_Options::update_all( $options );
			add_settings_error( 'seo_core_messages', 'seo_core_indexnow_saved', __( 'Настройки IndexNow API успешно сохранены!', 'seo-core-best' ), 'updated' );
		}

		// 10. Manual URL submission from Console
		if ( 'submit_manual_indexing' === $action ) {
			$raw_urls = isset( $_POST['manual_urls'] ) ? wp_unslash( $_POST['manual_urls'] ) : '';
			$target   = isset( $_POST['indexing_target'] ) ? sanitize_text_field( $_POST['indexing_target'] ) : 'both';

			$urls = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $raw_urls ) ) ) );

			if ( empty( $urls ) ) {
				add_settings_error( 'seo_core_messages', 'seo_core_idx_err', __( 'Пожалуйста, введите хотя бы один URL.', 'seo-core-best' ), 'error' );
			} else {
				$msgs = array();

				if ( 'google' === $target || 'both' === $target ) {
					$g_res = Seo_Core_Instant_Indexing::submit_to_google( $urls, 'URL_UPDATED' );
					$msgs[] = $g_res['message'];
				}

				if ( 'indexnow' === $target || 'both' === $target ) {
					$in_res = Seo_Core_Instant_Indexing::submit_to_indexnow( $urls );
					$msgs[] = $in_res['message'];
				}

				add_settings_error( 'seo_core_messages', 'seo_core_idx_res', implode( ' | ', $msgs ), 'updated' );
			}
		}

		// 11. Clear Indexing History
		if ( 'clear_indexing_history' === $action ) {
			Seo_Core_Instant_Indexing::clear_history();
			add_settings_error( 'seo_core_messages', 'seo_core_hist_cleared', __( 'История отправки в поисковые системы очищена.', 'seo-core-best' ), 'updated' );
		}
	}

	/**
	 * Render settings page with tabs
	 */
	public function render_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		$options    = Seo_Core_Options::get_all();
		$db_detect  = Seo_Core_Importer::detect_rank_math_in_db();
		$redir_count = Seo_Core_Redirects::get_count();
		$stat404     = Seo_Core_404_Monitor::get_stats();
		?>
		<div class="wrap seo-core-wrap">
			<div class="seo-core-header-banner">
				<div class="seo-core-header-title">
					<h1>🚀 SEO Core Best <span class="seo-core-version">v1.0.0</span></h1>
					<p class="seo-core-tagline">Сверхлегкий, быстрый On-Page SEO плагин — полноценная альтернатива громоздкому Rank Math.</p>
				</div>
				<div class="seo-core-header-stats">
					<div class="seo-stat-pill">
						<span class="stat-num"><?php echo esc_html( $redir_count ); ?></span>
						<span class="stat-label">301 Редиректов</span>
					</div>
					<div class="seo-stat-pill">
						<span class="stat-num" style="<?php echo ( $stat404['total_urls'] > 0 ? 'color: #f59e0b;' : '' ); ?>"><?php echo esc_html( $stat404['total_urls'] ); ?></span>
						<span class="stat-label">Ошибок 404</span>
					</div>
					<div class="seo-stat-pill">
						<span class="stat-num"><?php echo ( $options['enable_sitemap'] ? 'XML OK' : 'Откл' ); ?></span>
						<span class="stat-label">Карта сайта</span>
					</div>
				</div>
			</div>

			<?php settings_errors( 'seo_core_messages' ); ?>

			<!-- Navigation Tabs -->
			<h2 class="nav-tab-wrapper seo-core-tabs">
				<a href="?page=seo-core-best&tab=general" class="nav-tab <?php echo ( 'general' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					⚙️ <?php esc_html_e( 'Общие настройки', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=titles" class="nav-tab <?php echo ( 'titles' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🏷️ <?php esc_html_e( 'Заголовки и Мета (Titles)', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=social" class="nav-tab <?php echo ( 'social' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🌐 <?php esc_html_e( 'Соцсети и OpenGraph', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=sitemap" class="nav-tab <?php echo ( 'sitemap' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🗺️ <?php esc_html_e( 'Карта сайта (Sitemap)', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=redirects" class="nav-tab <?php echo ( 'redirects' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🔀 <?php esc_html_e( '301 Редиректы', 'seo-core-best' ); ?> (<?php echo esc_html( $redir_count ); ?>)
				</a>
				<a href="?page=seo-core-best&tab=monitor404" class="nav-tab <?php echo ( 'monitor404' === $active_tab ? 'nav-tab-active' : '' ); ?>" style="<?php echo ( $stat404['total_urls'] > 0 ? 'border-color: #f59e0b; color: #b45309;' : '' ); ?>">
					🔍 <?php esc_html_e( 'Монитор 404', 'seo-core-best' ); ?> (<?php echo esc_html( $stat404['total_urls'] ); ?>)
				</a>
				<a href="?page=seo-core-best&tab=indexing" class="nav-tab <?php echo ( 'indexing' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🚀 <?php esc_html_e( 'Мгновенная индексация API', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=analyzer" class="nav-tab <?php echo ( 'analyzer' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🎯 <?php esc_html_e( 'SEO Анализатор', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=images" class="nav-tab <?php echo ( 'images' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🖼️ <?php esc_html_e( 'SEO Картинок (Image SEO)', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=ai" class="nav-tab <?php echo ( 'ai' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					🤖 <?php esc_html_e( 'Настройки AI (DeepSeek / Mistral)', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=migration" class="nav-tab <?php echo ( 'migration' === $active_tab ? 'nav-tab-active' : '' ); ?> seo-tab-highlight">
					📦 <?php esc_html_e( 'Миграция из Rank Math', 'seo-core-best' ); ?>
				</a>
			</h2>

			<div class="seo-core-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'titles':
						$this->render_tab_titles( $options );
						break;
					case 'social':
						$this->render_tab_social( $options );
						break;
					case 'sitemap':
						$this->render_tab_sitemap( $options );
						break;
					case 'redirects':
						$this->render_tab_redirects();
						break;
					case 'monitor404':
						$this->render_tab_404_monitor();
						break;
					case 'indexing':
						$this->render_tab_indexing( $options );
						break;
					case 'analyzer':
						$this->render_tab_analyzer();
						break;
					case 'images':
						$this->render_tab_images( $options );
						break;
					case 'ai':
						$this->render_tab_ai( $options );
						break;
					case 'migration':
						$this->render_tab_migration( $db_detect );
						break;
					case 'general':
					default:
						$this->render_tab_general( $options );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab 1: General
	 */
	protected function render_tab_general( $options ) {
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
			<input type="hidden" name="seo_core_action" value="save_settings" />
			<input type="hidden" name="seo_core_tab" value="general" />

			<table class="form-table seo-table">
				<tr>
					<th scope="row"><label for="title_separator">Разделитель заголовка (Separator)</label></th>
					<td>
						<input type="text" name="title_separator" id="title_separator" value="<?php echo esc_attr( $options['title_separator'] ); ?>" class="regular-text" style="width: 80px; text-align: center; font-size: 18px;" />
						<p class="description">Символ между названием статьи и названием сайта (например: <code>—</code>, <code>|</code>, <code>-</code>). Переменная <code>%sep%</code>.</p>
					</td>
				</tr>
				<tr>
					<th colspan="2" class="seo-section-title">🏢 Данные организации и бренда (%org_name%)</th>
				</tr>
				<tr>
					<th scope="row"><label for="org_name">Название бренда / Организации</label></th>
					<td>
						<input type="text" name="org_name" id="org_name" value="<?php echo esc_attr( isset( $options['org_name'] ) ? $options['org_name'] : get_bloginfo( 'name' ) ); ?>" class="regular-text" />
						<p class="description">Доступно в шаблонах через переменную <code>%org_name%</code> (как в Rank Math Knowledge Graph).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="org_url">URL организации</label></th>
					<td>
						<input type="url" name="org_url" id="org_url" value="<?php echo esc_attr( isset( $options['org_url'] ) ? $options['org_url'] : home_url() ); ?>" class="regular-text" />
						<p class="description">Доступно в шаблонах через переменную <code>%org_url%</code>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="org_logo">URL логотипа</label></th>
					<td>
						<input type="url" name="org_logo" id="org_logo" value="<?php echo esc_attr( isset( $options['org_logo'] ) ? $options['org_logo'] : '' ); ?>" class="large-text" placeholder="https://site.ru/wp-content/uploads/logo.png" />
						<p class="description">Доступно в шаблонах через переменную <code>%org_logo%</code>.</p>
					</td>
				</tr>
				<tr>
					<th colspan="2" class="seo-section-title">⚙️ Дополнительные опции</th>
				</tr>
				<tr>
					<th scope="row">Хлебные крошки (Breadcrumbs)</th>
					<td>
						<label>
							<input type="checkbox" name="enable_breadcrumbs" value="1" <?php checked( $options['enable_breadcrumbs'], 1 ); ?> />
							Включить поддержку хлебных крошек
						</label>
						<br /><br />
						<label for="breadcrumbs_separator">Разделитель в крошках:</label>
						<input type="text" name="breadcrumbs_separator" id="breadcrumbs_separator" value="<?php echo esc_attr( $options['breadcrumbs_separator'] ); ?>" style="width: 50px; text-align: center;" />
					</td>
				</tr>
				<tr>
					<th scope="row">Оптимизация внешних ссылок</th>
					<td>
						<label>
							<input type="checkbox" name="nofollow_external_links" value="1" <?php checked( $options['nofollow_external_links'], 1 ); ?> />
							Автоматически добавлять <code>rel="nofollow noopener"</code> ко всем внешним ссылкам в контенте
						</label>
						<br /><br />
						<label>
							<input type="checkbox" name="open_external_blank" value="1" <?php checked( $options['open_external_blank'], 1 ); ?> />
							Открывать внешние ссылки в новом окне (<code>target="_blank"</code>)
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Удаление базы категорий</th>
					<td>
						<label>
							<input type="checkbox" name="strip_category_base" value="1" <?php checked( $options['strip_category_base'], 1 ); ?> />
							Удалить <code>/category/</code> из ссылок рубрик (Strip category base)
						</label>
					</td>
				</tr>
				<tr>
					<th colspan="2" class="seo-section-title">🔍 Монитор 404 ошибок</th>
				</tr>
				<tr>
					<th scope="row">Логирование 404</th>
					<td>
						<label>
							<input type="checkbox" name="enable_404_monitor" value="1" <?php checked( isset( $options['enable_404_monitor'] ) ? $options['enable_404_monitor'] : 1, 1 ); ?> />
							<strong>Включить отслеживание 404 ошибок</strong> (логировать битые URL, IP посетителей, источники-Referer и User-Agent)
						</label>
						<br /><br />
						<label>
							<input type="checkbox" name="log_404_images" value="1" <?php checked( isset( $options['log_404_images'] ) ? $options['log_404_images'] : 1, 1 ); ?> />
							Отслеживать 404 для изображений (<code>.jpg, .png, .webp, .svg</code> и др.)
						</label>
						<p class="description">Позволяет находить битые картинки в статьях или отключить их сбор, если они не нужны.</p>
					</td>
				</tr>
				<tr>
					<th colspan="2" class="seo-section-title">🛡️ Совместимость</th>
				</tr>
				<tr>
					<th scope="row">Совместимость с Rank Math</th>
					<td>
						<label>
							<input type="checkbox" name="rank_math_compat_read" value="1" <?php checked( $options['rank_math_compat_read'], 1 ); ?> />
							<strong>Авто-чтение метаданных Rank Math</strong> (если <code>_seo_title</code> пуст, брать из <code>rank_math_title</code>)
						</label>
						<p class="description">Позволяет мгновенно отключить Rank Math без единого изменения в 10 000+ существующих постов!</p>
						<br />
						<label>
							<input type="checkbox" name="rank_math_compat_write" value="1" <?php checked( $options['rank_math_compat_write'], 1 ); ?> />
							<strong>Двойная запись</strong> (при сохранении статьи обновлять как <code>_seo_*</code>, так и <code>rank_math_*</code>)
						</label>
						<p class="description">Гарантирует, что любые внешние Python скрипты и плагины будут продолжать работать без правок кода.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Сохранить общие настройки', 'seo-core-best' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tab 2: Titles & Meta
	 */
	protected function render_tab_titles( $options ) {
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
			<input type="hidden" name="seo_core_action" value="save_settings" />
			<input type="hidden" name="seo_core_tab" value="titles" />

			<div class="seo-callout">
				<h4>💡 Справочник поддерживаемых переменных Rank Math:</h4>
				<ul style="margin: 8px 0 0 18px; line-height: 1.8; font-size: 13px;">
					<li><strong>Основные:</strong> <code>%title%</code> (заголовок), <code>%sitename%</code> (сайт), <code>%sep%</code> (разделитель), <code>%org_name%</code> (бренд/организация), <code>%sitedesc%</code> (описание сайта), <code>%excerpt%</code> (отрывок), <code>%url%</code></li>
					<li><strong>Рубрики и Метки:</strong> <code>%category%</code> (рубрика статьи или архива), <code>%categories%</code> (список рубрик), <code>%tag%</code> (метка статьи или архива), <code>%tags%</code> (список меток), <code>%term%</code>, <code>%term_description%</code></li>
					<li><strong>Даты и Время:</strong> <code>%currentyear%</code>, <code>%currentmonth%</code>, <code>%currentday%</code>, <code>%currentdate%</code>, <code>%date%</code> (дата статьи), <code>%modified%</code> (дата обновления), форматы: <code>%date(Y)%</code>, <code>%currentdate(d.m.Y)%</code></li>
					<li><strong>Умные шорткоды:</strong> <code>[currentyear]</code> (динамический текущий год), <code>[upcomingmonth]</code> (умный месяц для акций), <code>[currentmonth]</code> (текущий месяц прописью)</li>
					<li><strong>Продвинутые:</strong> <code>%author%</code>, <code>%name%</code>, <code>%id%</code>, <code>%focuskw%</code> (фокусное слово), <code>%page%</code> (номер страницы), <code>%customfield(meta_key)%</code> (произвольное поле)</li>
				</ul>
			</div>

			<table class="form-table seo-table">
				<!-- Homepage -->
				<tr>
					<th colspan="2" class="seo-section-title">🏠 Главная страница</th>
				</tr>
				<tr>
					<th scope="row">Title главной</th>
					<td>
						<input type="text" name="homepage_title" value="<?php echo esc_attr( $options['homepage_title'] ); ?>" class="large-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">Description главной</th>
					<td>
						<textarea name="homepage_description" rows="2" class="large-text"><?php echo esc_textarea( $options['homepage_description'] ); ?></textarea>
					</td>
				</tr>

				<!-- Posts -->
				<tr>
					<th colspan="2" class="seo-section-title">📝 Записи (Посты)</th>
				</tr>
				<tr>
					<th scope="row">Шаблон Title</th>
					<td>
						<input type="text" name="post_title_template" value="<?php echo esc_attr( $options['post_title_template'] ); ?>" class="large-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">Шаблон Description</th>
					<td>
						<textarea name="post_desc_template" rows="2" class="large-text"><?php echo esc_textarea( $options['post_desc_template'] ); ?></textarea>
					</td>
				</tr>

				<!-- Pages -->
				<tr>
					<th colspan="2" class="seo-section-title">📄 Страницы</th>
				</tr>
				<tr>
					<th scope="row">Шаблон Title</th>
					<td>
						<input type="text" name="page_title_template" value="<?php echo esc_attr( $options['page_title_template'] ); ?>" class="large-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">Шаблон Description</th>
					<td>
						<textarea name="page_desc_template" rows="2" class="large-text"><?php echo esc_textarea( $options['page_desc_template'] ); ?></textarea>
					</td>
				</tr>

				<!-- Categories -->
				<tr>
					<th colspan="2" class="seo-section-title">📁 Рубрики (Категории)</th>
				</tr>
				<tr>
					<th scope="row">Шаблон Title</th>
					<td>
						<input type="text" name="category_title_template" value="<?php echo esc_attr( $options['category_title_template'] ); ?>" class="large-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">Шаблон Description</th>
					<td>
						<textarea name="category_desc_template" rows="2" class="large-text"><?php echo esc_textarea( $options['category_desc_template'] ); ?></textarea>
					</td>
				</tr>

				<!-- Tags -->
				<tr>
					<th colspan="2" class="seo-section-title">🏷️ Метки (Теги)</th>
				</tr>
				<tr>
					<th scope="row">Шаблон Title</th>
					<td>
						<input type="text" name="tag_title_template" value="<?php echo esc_attr( $options['tag_title_template'] ); ?>" class="large-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">Шаблон Description</th>
					<td>
						<textarea name="tag_desc_template" rows="2" class="large-text"><?php echo esc_textarea( $options['tag_desc_template'] ); ?></textarea>
					</td>
				</tr>

				<!-- Dynamic Custom Post Types registered by any plugin -->
				<?php
				$all_public_cpts = get_post_types( array( 'public' => true ), 'objects' );
				$cpt_tpls        = isset( $options['cpt_templates'] ) && is_array( $options['cpt_templates'] ) ? $options['cpt_templates'] : array();
				$has_custom_cpts = false;

				foreach ( $all_public_cpts as $cpt_slug => $cpt_obj ) {
					if ( in_array( $cpt_slug, array( 'post', 'page', 'attachment' ), true ) ) {
						continue;
					}
					$has_custom_cpts = true;
					$saved_title = isset( $cpt_tpls[ $cpt_slug ]['title'] ) ? $cpt_tpls[ $cpt_slug ]['title'] : '%title% %sep% %sitename%';
					$saved_desc  = isset( $cpt_tpls[ $cpt_slug ]['desc'] ) ? $cpt_tpls[ $cpt_slug ]['desc'] : '%excerpt%';
					?>
					<tr>
						<th colspan="2" class="seo-section-title" style="background: #f1f5f9; color: #1e293b;">
							📦 Тип записи: <strong><?php echo esc_html( $cpt_obj->labels->name ); ?></strong> (код: <code><?php echo esc_html( $cpt_slug ); ?></code>)
						</th>
					</tr>
					<tr>
						<th scope="row">Шаблон Title</th>
						<td>
							<input type="text" name="cpt_templates[<?php echo esc_attr( $cpt_slug ); ?>][title]" value="<?php echo esc_attr( $saved_title ); ?>" class="large-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">Шаблон Description</th>
						<td>
							<textarea name="cpt_templates[<?php echo esc_attr( $cpt_slug ); ?>][desc]" rows="2" class="large-text"><?php echo esc_textarea( $saved_desc ); ?></textarea>
						</td>
					</tr>
					<?php
				}

				// Dynamic Custom Taxonomies registered by any plugin
				$all_public_taxes = get_taxonomies( array( 'public' => true ), 'objects' );
				$tax_tpls         = isset( $options['tax_templates'] ) && is_array( $options['tax_templates'] ) ? $options['tax_templates'] : array();

				foreach ( $all_public_taxes as $tax_slug => $tax_obj ) {
					if ( in_array( $tax_slug, array( 'category', 'post_tag', 'post_format' ), true ) ) {
						continue;
					}
					$saved_tax_title = isset( $tax_tpls[ $tax_slug ]['title'] ) ? $tax_tpls[ $tax_slug ]['title'] : '%term% %sep% %sitename%';
					$saved_tax_desc  = isset( $tax_tpls[ $tax_slug ]['desc'] ) ? $tax_tpls[ $tax_slug ]['desc'] : '%term_description%';
					?>
					<tr>
						<th colspan="2" class="seo-section-title" style="background: #f8fafc; color: #334155;">
							🏷️ Таксономия: <strong><?php echo esc_html( $tax_obj->labels->name ); ?></strong> (код: <code><?php echo esc_html( $tax_slug ); ?></code>)
						</th>
					</tr>
					<tr>
						<th scope="row">Шаблон Title</th>
						<td>
							<input type="text" name="tax_templates[<?php echo esc_attr( $tax_slug ); ?>][title]" value="<?php echo esc_attr( $saved_tax_title ); ?>" class="large-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">Шаблон Description</th>
						<td>
							<textarea name="tax_templates[<?php echo esc_attr( $tax_slug ); ?>][desc]" rows="2" class="large-text"><?php echo esc_textarea( $saved_tax_desc ); ?></textarea>
						</td>
					</tr>
					<?php
				}
				?>
			</table>

			<?php submit_button( __( 'Сохранить шаблоны Title и Meta', 'seo-core-best' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Social Networks & OpenGraph
	 */
	protected function render_tab_social( $options ) {
		$enable_og      = isset( $options['enable_opengraph'] ) ? $options['enable_opengraph'] : 1;
		$enable_tw      = isset( $options['enable_twitter_cards'] ) ? $options['enable_twitter_cards'] : 1;
		$tw_type        = isset( $options['twitter_card_type'] ) ? $options['twitter_card_type'] : 'summary_large_image';
		$default_og_img = isset( $options['default_og_image'] ) ? $options['default_og_image'] : '';
		$social_tg      = isset( $options['social_telegram'] ) ? $options['social_telegram'] : '';
		$social_vk      = isset( $options['social_vk'] ) ? $options['social_vk'] : '';
		$social_yt      = isset( $options['social_youtube'] ) ? $options['social_youtube'] : '';
		$social_tw      = isset( $options['social_twitter'] ) ? $options['social_twitter'] : '';
		$social_fb      = isset( $options['social_facebook'] ) ? $options['social_facebook'] : '';
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
			<input type="hidden" name="seo_core_action" value="save_settings" />
			<input type="hidden" name="seo_core_tab" value="social" />

			<!-- Card 1: OpenGraph & Facebook / VK / Telegram -->
			<div class="seo-migration-card">
				<div class="migration-card-header">
					<h3>🌐 Разметка OpenGraph (Facebook, ВКонтакте, Telegram, WhatsApp)</h3>
					<span class="seo-status-tag tag-ready">Стандарт соцсетей</span>
				</div>
				<p>Теги OpenGraph (<code>og:title</code>, <code>og:description</code>, <code>og:image</code>, <code>og:url</code>, <code>og:site_name</code>) обеспечивают привлекательный и кликабельный вид ссылок вашего сайта при отправке в мессенджеры (Telegram, WhatsApp) и публикации в социальных сетях.</p>

				<table class="form-table seo-table">
					<tr>
						<th scope="row">Включить OpenGraph</th>
						<td>
							<label>
								<input type="checkbox" name="enable_opengraph" value="1" <?php checked( $enable_og, 1 ); ?> />
								<strong>Генерировать метатеги OpenGraph в шапке сайта (&lt;head&gt;)</strong>
							</label>
							<p class="description">Критически важно для правильного отображения превью страниц в Telegram, VK и WhatsApp.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Card 2: Twitter / X Cards -->
			<div class="seo-migration-card">
				<div class="migration-card-header">
					<h3>🐦 Карточки Twitter / X Cards</h3>
					<span class="seo-status-tag tag-ready">Twitter / X</span>
				</div>
				<p>Специальная разметка для платформы X (бывший Twitter), формирующая интерактивную карточку со ссылкой и иллюстрацией.</p>

				<table class="form-table seo-table">
					<tr>
						<th scope="row">Включить Twitter Cards</th>
						<td>
							<label>
								<input type="checkbox" name="enable_twitter_cards" value="1" <?php checked( $enable_tw, 1 ); ?> />
								<strong>Выводить теги twitter:card, twitter:title, twitter:image, twitter:site</strong>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitter_card_type">Формат карточки Twitter</label></th>
						<td>
							<select name="twitter_card_type" id="twitter_card_type">
								<option value="summary_large_image" <?php selected( $tw_type, 'summary_large_image' ); ?>>Большое изображение (summary_large_image) — Рекомендуется</option>
								<option value="summary" <?php selected( $tw_type, 'summary' ); ?>>Компактная карточка (summary)</option>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<!-- Card 3: Default Share Image -->
			<div class="seo-migration-card">
				<div class="migration-card-header">
					<h3>🖼️ Изображение для соцсетей по умолчанию (Default Share Image)</h3>
					<span class="seo-status-tag tag-info">Запасное изображение</span>
				</div>
				<p>Это изображение автоматически используется при публикации главной страницы, рубрик или записей, у которых <strong>не установлена собственная миниатюра (Featured Image)</strong>.</p>

				<table class="form-table seo-table">
					<tr>
						<th scope="row"><label for="default_og_image">URL изображения</label></th>
						<td>
							<div style="display: flex; gap: 10px; align-items: center; max-width: 650px;">
								<input type="url" name="default_og_image" id="default_og_image" value="<?php echo esc_url( $default_og_img ); ?>" class="regular-text" style="flex: 1;" placeholder="https://aliprofi.ru/wp-content/uploads/og-image.jpg" />
								<button type="button" class="button button-secondary seo-media-upload-btn" data-target="#default_og_image" data-preview="#og_image_preview" data-title="Выберите изображение для соцсетей">
									📁 Выбрать / Загрузить
								</button>
								<button type="button" class="button button-link-delete seo-media-clear-btn" data-target="#default_og_image" data-preview="#og_image_preview">
									✕
								</button>
							</div>
							<p class="description">Рекомендуемый размер: <strong>1200 × 630 пикселей</strong> (соотношение 1.91:1, форматы JPG или PNG).</p>
							<div id="og_image_preview">
								<?php if ( ! empty( $default_og_img ) ) : ?>
									<img src="<?php echo esc_url( $default_og_img ); ?>" style="max-width: 280px; max-height: 160px; object-fit: cover; border-radius: 6px; margin-top: 10px; border: 1px solid #cbd5e1; display: block;" />
								<?php endif; ?>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<!-- Card 4: Social Accounts / Profiles -->
			<div class="seo-migration-card">
				<div class="migration-card-header">
					<h3>📱 Профили и каналы сайта в социальных сетях</h3>
					<span class="seo-status-tag tag-success">Schema.org Knowledge Graph</span>
				</div>
				<p>Укажите ссылки на ваши официальные сообщества и каналы. Плагин свяжет их с сайтом в микроразметке <strong>Schema.org Organization (sameAs)</strong> для поисковиков Google и Яндекс, а также для атрибуции авторства карточек.</p>

				<table class="form-table seo-table">
					<tr>
						<th scope="row"><label for="social_telegram">✈️ Telegram (Канал или Чат)</label></th>
						<td>
							<input type="text" name="social_telegram" id="social_telegram" value="<?php echo esc_attr( $social_tg ); ?>" class="regular-text" placeholder="https://t.me/aliprofi или @aliprofi" />
							<p class="description">Ссылка на ваш Telegram-канал или группу проекта.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="social_vk">🔵 ВКонтакте (Сообщество или Профиль)</label></th>
						<td>
							<input type="url" name="social_vk" id="social_vk" value="<?php echo esc_url( $social_vk ); ?>" class="regular-text" placeholder="https://vk.com/aliprofi" />
							<p class="description">Ссылка на группу или паблик ВКонтакте.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="social_youtube">▶️ YouTube (Канал)</label></th>
						<td>
							<input type="url" name="social_youtube" id="social_youtube" value="<?php echo esc_url( $social_yt ); ?>" class="regular-text" placeholder="https://youtube.com/@aliprofi" />
							<p class="description">Ссылка на YouTube-канал проекта.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="social_twitter">🐦 Twitter / X (Имя пользователя)</label></th>
						<td>
							<input type="text" name="social_twitter" id="social_twitter" value="<?php echo esc_attr( $social_tw ); ?>" class="regular-text" placeholder="@aliprofi" />
							<p class="description">Имя пользователя Twitter/X (например, <code>@aliprofi</code>). Используется в тегах <code>twitter:site</code> и <code>twitter:creator</code>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="social_facebook">👥 Facebook (Страница)</label></th>
						<td>
							<input type="url" name="social_facebook" id="social_facebook" value="<?php echo esc_url( $social_fb ); ?>" class="regular-text" placeholder="https://facebook.com/aliprofi" />
							<p class="description">Ссылка на страницу Facebook.</p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary seo-btn-red">
					<?php esc_html_e( 'Сохранить настройки соцсетей', 'seo-core-best' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Tab 3: Sitemap
	 */
	protected function render_tab_sitemap( $options ) {
		$pts = get_post_types( array( 'public' => true ), 'objects' );
		$taxes = get_taxonomies( array( 'public' => true ), 'objects' );
		$sitemap_url = home_url( '/sitemap_index.xml' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
			<input type="hidden" name="seo_core_action" value="save_settings" />
			<input type="hidden" name="seo_core_tab" value="sitemap" />

			<div class="seo-callout seo-callout-info">
				<h4>🗺️ Ссылка на вашу карту сайта:</h4>
				<p><a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" class="seo-link-sitemap"><strong><?php echo esc_html( $sitemap_url ); ?> ↗</strong></a></p>
			</div>

			<table class="form-table seo-table">
				<tr>
					<th scope="row">Включить XML Карту сайта</th>
					<td>
						<label>
							<input type="checkbox" name="enable_sitemap" value="1" <?php checked( $options['enable_sitemap'], 1 ); ?> />
							Генерировать чистую, быструю XML Карту сайта без тормозов
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Количество ссылок на 1 карту</th>
					<td>
						<input type="number" name="sitemap_items_per_page" value="<?php echo esc_attr( $options['sitemap_items_per_page'] ); ?>" min="50" max="50000" step="50" style="width: 120px;" />
						<p class="description">По умолчанию в Rank Math: <code>1000</code>. При превышении автоматически создаются страницы <code>post-sitemap2.xml</code> и т.д.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Включать изображения</th>
					<td>
						<label>
							<input type="checkbox" name="sitemap_include_images" value="1" <?php checked( $options['sitemap_include_images'], 1 ); ?> />
							Добавлять тег <code>&lt;image:image&gt;</code> для главных изображений постов
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Типы записей в карте сайта</th>
					<td>
						<?php foreach ( $pts as $pt ) : ?>
							<?php if ( 'attachment' === $pt->name ) continue; ?>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="sitemap_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $options['sitemap_post_types'], true ) ); ?> />
								<strong><?php echo esc_html( $pt->labels->name ); ?></strong> (<code><?php echo esc_html( $pt->name ); ?></code>)
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">Таксономии в карте сайта</th>
					<td>
						<?php foreach ( $taxes as $tax ) : ?>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="sitemap_taxonomies[]" value="<?php echo esc_attr( $tax->name ); ?>" <?php checked( in_array( $tax->name, (array) $options['sitemap_taxonomies'], true ) ); ?> />
								<strong><?php echo esc_html( $tax->labels->name ); ?></strong> (<code><?php echo esc_html( $tax->name ); ?></code>)
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Сохранить настройки карты сайта', 'seo-core-best' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tab 4: Redirections
	 */
	protected function render_tab_redirects() {
		global $wpdb;
		$table = Seo_Core_Redirects::get_table_name();
		Seo_Core_Redirects::install_table();

		// Add new redirect form
		$prefill_from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		?>
		<div class="seo-box" <?php echo ( $prefill_from ? 'style="border-color: #3b82f6; background: #eff6ff;"' : '' ); ?>>
			<h3>➕ <?php echo ( $prefill_from ? 'Создать редирект для ' . esc_html( $prefill_from ) : 'Добавить новый 301/302 Редирект' ); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
				<input type="hidden" name="seo_core_action" value="add_redirect" />

				<div class="seo-redirect-add-grid">
					<div>
						<label><strong>Откуда (Исходный URL / Путь):</strong></label>
						<input type="text" name="redirect_from" value="<?php echo esc_attr( $prefill_from ); ?>" placeholder="old-page/ или /help/kak-zakazat/" required class="widefat" />
					</div>
					<div>
						<label><strong>Куда (Целевой URL):</strong></label>
						<input type="text" name="redirect_to" placeholder="https://aliprofi.ru/new-page/ или /new-page/" required class="widefat" <?php echo ( $prefill_from ? 'autofocus' : '' ); ?> />
					</div>
					<div style="width: 120px;">
						<label><strong>Код:</strong></label>
						<select name="redirect_code" class="widefat">
							<option value="301">301 (Permanent)</option>
							<option value="302">302 (Temporary)</option>
							<option value="307">307 (Temporary)</option>
						</select>
					</div>
					<div style="width: 140px;">
						<label><strong>Тип совпадения:</strong></label>
						<select name="redirect_match_type" class="widefat">
							<option value="exact">Exact (Точное)</option>
							<option value="contains">Contains (Часть)</option>
							<option value="regex">Regex (Регулярка)</option>
						</select>
					</div>
					<div style="align-self: flex-end;">
						<button type="submit" class="button button-primary">Добавить редирект</button>
					</div>
				</div>
			</form>
		</div>

		<?php
		// Fetch recent redirects
		$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		$offset = ( $paged - 1 ) * $per_page;
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
		$total_pages = ceil( $total_items / $per_page );

		$redirects = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
		?>

		<div class="seo-box" style="margin-top: 20px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
				<h3>📋 Активные правила перенаправлений (Всего: <?php echo esc_html( $total_items ); ?>)</h3>
				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( 'Стр. %d из %d', $paged, $total_pages ); ?></span>
						<?php for ( $i = 1; $i <= min( 10, $total_pages ); $i++ ) : ?>
							<a href="?page=seo-core-best&tab=redirects&paged=<?php echo $i; ?>" class="button button-small <?php echo ( $paged === $i ? 'button-primary' : '' ); ?>"><?php echo $i; ?></a>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 30%;">Исходный URL (Откуда)</th>
						<th style="width: 35%;">Целевой URL (Куда)</th>
						<th style="width: 80px;">Код</th>
						<th style="width: 80px;">Тип</th>
						<th style="width: 90px;">Хиты</th>
						<th style="width: 80px;">Действие</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $redirects ) ) : ?>
						<tr>
							<td colspan="6" style="text-align: center; padding: 20px;">Редиректы еще не добавлены. Вы можете импортировать их из Rank Math во вкладке «Миграция»!</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $redirects as $r ) : ?>
							<tr>
								<td><code><?php echo esc_html( $r['url_from'] ); ?></code></td>
								<td><a href="<?php echo esc_url( $r['url_to'] ); ?>" target="_blank"><?php echo esc_html( $r['url_to'] ); ?> ↗</a></td>
								<td><span class="badge-code"><?php echo esc_html( $r['redirect_code'] ); ?></span></td>
								<td><?php echo esc_html( $r['match_type'] ); ?></td>
								<td><strong><?php echo esc_html( $r['hits'] ); ?></strong></td>
								<td>
									<form method="post" action="" style="display:inline;" onsubmit="return confirm('Удалить этот редирект?');">
										<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
										<input type="hidden" name="seo_core_action" value="delete_redirect" />
										<input type="hidden" name="redirect_id" value="<?php echo esc_attr( $r['id'] ); ?>" />
										<button type="submit" class="button button-small button-link-delete">Удалить</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Tab: 404 Error Monitor
	 */
	protected function render_tab_404_monitor() {
		Seo_Core_404_Monitor::install_table();

		$stats = Seo_Core_404_Monitor::get_stats();

		$current_filter = isset( $_GET['file_type'] ) ? sanitize_text_field( $_GET['file_type'] ) : 'all';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby        = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'last_accessed';
		$order          = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
		$paged          = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page       = 50;

		$log_data = Seo_Core_404_Monitor::get_logs( array(
			'paged'     => $paged,
			'per_page'  => $per_page,
			'file_type' => $current_filter,
			'search'    => $search_query,
			'orderby'   => $orderby,
			'order'     => $order,
		) );

		$items       = $log_data['items'];
		$total_items = $log_data['total'];
		$total_pages = $log_data['total_pages'];
		?>
		<!-- 404 Stats Banner -->
		<div class="seo-404-stats-grid">
			<div class="seo-404-stat-card card-danger">
				<span class="stat-title">Всего 404 переходов</span>
				<span class="stat-value"><?php echo number_format_i18n( $stats['total_hits'] ); ?></span>
				<span class="stat-sub">суммарно запросов</span>
			</div>
			<div class="seo-404-stat-card card-warning">
				<span class="stat-title">Уникальных битых URL</span>
				<span class="stat-value"><?php echo number_format_i18n( $stats['total_urls'] ); ?></span>
				<span class="stat-sub">адресов в базе</span>
			</div>
			<div class="seo-404-stat-card card-blue">
				<span class="stat-title">📄 Страницы (HTML)</span>
				<span class="stat-value"><?php echo number_format_i18n( $stats['pages_count'] ); ?></span>
				<span class="stat-sub">битых страниц сайта</span>
			</div>
			<div class="seo-404-stat-card card-orange">
				<span class="stat-title">🖼️ Картинки (Images)</span>
				<span class="stat-value"><?php echo number_format_i18n( $stats['images_count'] ); ?></span>
				<span class="stat-sub">ненайденных картинок</span>
			</div>
			<div class="seo-404-stat-card card-gray">
				<span class="stat-title">⚙️ Скрипты и Ассеты</span>
				<span class="stat-value"><?php echo number_format_i18n( $stats['assets_count'] ); ?></span>
				<span class="stat-sub">.css, .js, шрифты</span>
			</div>
		</div>

		<!-- Filter & Search Toolbar -->
		<div class="seo-404-toolbar">
			<div class="seo-404-filter-pills">
				<a href="?page=seo-core-best&tab=monitor404&file_type=all<?php echo ( $search_query ? '&s=' . urlencode( $search_query ) : '' ); ?>" class="seo-pill <?php echo ( 'all' === $current_filter ? 'pill-active' : '' ); ?>">
					Все типы (<?php echo esc_html( $stats['total_urls'] ); ?>)
				</a>
				<a href="?page=seo-core-best&tab=monitor404&file_type=page<?php echo ( $search_query ? '&s=' . urlencode( $search_query ) : '' ); ?>" class="seo-pill <?php echo ( 'page' === $current_filter ? 'pill-active' : '' ); ?>">
					📄 Страницы (<?php echo esc_html( $stats['pages_count'] ); ?>)
				</a>
				<a href="?page=seo-core-best&tab=monitor404&file_type=image<?php echo ( $search_query ? '&s=' . urlencode( $search_query ) : '' ); ?>" class="seo-pill <?php echo ( 'image' === $current_filter ? 'pill-active' : '' ); ?>">
					🖼️ Картинки (<?php echo esc_html( $stats['images_count'] ); ?>)
				</a>
				<a href="?page=seo-core-best&tab=monitor404&file_type=asset<?php echo ( $search_query ? '&s=' . urlencode( $search_query ) : '' ); ?>" class="seo-pill <?php echo ( 'asset' === $current_filter ? 'pill-active' : '' ); ?>">
					⚙️ Ассеты (<?php echo esc_html( $stats['assets_count'] ); ?>)
				</a>
			</div>

			<div class="seo-404-actions-bar">
				<!-- Search -->
				<form method="get" action="" class="seo-404-search-form">
					<input type="hidden" name="page" value="seo-core-best" />
					<input type="hidden" name="tab" value="monitor404" />
					<input type="hidden" name="file_type" value="<?php echo esc_attr( $current_filter ); ?>" />
					<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Поиск по URL, IP или источнику..." class="seo-search-input" />
					<button type="submit" class="button">Найти</button>
					<?php if ( ! empty( $search_query ) ) : ?>
						<a href="?page=seo-core-best&tab=monitor404&file_type=<?php echo esc_attr( $current_filter ); ?>" class="button button-link">Сбросить</a>
					<?php endif; ?>
				</form>

				<!-- Clear All Button -->
				<?php if ( $stats['total_urls'] > 0 ) : ?>
					<form method="post" action="" onsubmit="return confirm('Вы уверены, что хотите полностью очистить весь лог 404 ошибок?');" style="margin: 0;">
						<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
						<input type="hidden" name="seo_core_action" value="clear_all_404_logs" />
						<button type="submit" class="button button-secondary" style="color: #b91c1c; border-color: #fca5a5;">
							🗑️ Очистить весь лог 404
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<!-- 404 Table -->
		<div class="seo-box" style="margin-top: 15px; padding: 0; overflow: hidden;">
			<div style="padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
				<span style="font-weight: 600; font-size: 14px;">
					Найдено записей: <?php echo esc_html( $total_items ); ?>
					<?php if ( 'last_accessed' === $orderby ) : ?>
						<span style="font-size: 12px; font-weight: normal; color: #64748b;">(Сортировка: по свежести | <a href="?page=seo-core-best&tab=monitor404&file_type=<?php echo esc_attr( $current_filter ); ?>&orderby=hits&order=DESC">Сортировать по частым хитам</a>)</span>
					<?php else : ?>
						<span style="font-size: 12px; font-weight: normal; color: #64748b;">(Сортировка: по хитам | <a href="?page=seo-core-best&tab=monitor404&file_type=<?php echo esc_attr( $current_filter ); ?>&orderby=last_accessed&order=DESC">Сортировать по свежести</a>)</span>
					<?php endif; ?>
				</span>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( 'Стр. %d из %d', $paged, $total_pages ); ?></span>
						<?php for ( $i = 1; $i <= min( 10, $total_pages ); $i++ ) : ?>
							<a href="?page=seo-core-best&tab=monitor404&file_type=<?php echo esc_attr( $current_filter ); ?>&orderby=<?php echo esc_attr( $orderby ); ?>&paged=<?php echo $i; ?><?php echo ( $search_query ? '&s=' . urlencode( $search_query ) : '' ); ?>" class="button button-small <?php echo ( $paged === $i ? 'button-primary' : '' ); ?>"><?php echo $i; ?></a>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="seo-404-table-responsive">
				<table class="wp-list-table widefat striped seo-404-table">
					<thead>
						<tr>
							<th class="col-uri">Битый URL (Куда стучались)</th>
							<th class="col-type">Тип</th>
							<th class="col-hits">Хиты</th>
							<th class="col-ip">IP-адрес</th>
							<th class="col-referer">Источник (Referer)</th>
							<th class="col-ua">Бот / Браузер</th>
							<th class="col-date">Дата</th>
							<th class="col-actions">Действия</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $items ) ) : ?>
							<tr>
								<td colspan="8" style="text-align: center; padding: 30px; color: #64748b;">
									<?php if ( ! empty( $search_query ) ) : ?>
										По запросу «<?php echo esc_html( $search_query ); ?>» 404 ошибок не найдено.
									<?php else : ?>
										Лог 404 ошибок пуст! На вашем сайте пока не зафиксировано битых ссылок. 🎉
									<?php endif; ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $items as $row ) : 
								$clean_uri = ltrim( $row['uri'], '/' );
								$redirect_link = admin_url( 'admin.php?page=seo-core-best&tab=redirects&from=' . urlencode( $clean_uri ) );
								$ip = $row['ip_address'];
								$abuse_link = ! empty( $ip ) && '127.0.0.1' !== $ip ? 'https://www.abuseipdb.com/check/' . urlencode( $ip ) : '';
							?>
								<tr>
									<!-- URI -->
									<td class="col-uri">
										<div class="seo-404-uri-wrap" title="<?php echo esc_attr( $row['uri'] ); ?>">
											<span class="seo-404-uri-text"><?php echo esc_html( $row['uri'] ); ?></span>
										</div>
									</td>

									<!-- File Type Badge -->
									<td class="col-type">
										<?php if ( 'page' === $row['file_type'] ) : ?>
											<span class="seo-badge-type badge-page">📄 Страница</span>
										<?php elseif ( 'image' === $row['file_type'] ) : ?>
											<span class="seo-badge-type badge-image">🖼️ Картинка</span>
										<?php elseif ( 'asset' === $row['file_type'] ) : ?>
											<span class="seo-badge-type badge-asset">⚙️ Ассет</span>
										<?php else : ?>
											<span class="seo-badge-type badge-other">📦 Файл</span>
										<?php endif; ?>
									</td>

									<!-- Hits -->
									<td class="col-hits">
										<span class="seo-hits-pill <?php echo ( $row['hits'] > 5 ? 'hits-high' : '' ); ?>">
											<?php echo esc_html( $row['hits'] ); ?>
										</span>
									</td>

									<!-- IP Address -->
									<td class="col-ip">
										<div class="seo-ip-tag">
											<span class="seo-ip-code"><?php echo esc_html( $ip ? $ip : '—' ); ?></span>
											<?php if ( $abuse_link ) : ?>
												<a href="<?php echo esc_url( $abuse_link ); ?>" target="_blank" title="Проверить репутацию IP на AbuseIPDB (бот/спам)" class="seo-ip-check-link">🔍</a>
											<?php endif; ?>
										</div>
									</td>

									<!-- Referer -->
									<td class="col-referer">
										<?php if ( ! empty( $row['referer'] ) ) : 
											$ref_host = wp_parse_url( $row['referer'], PHP_URL_HOST );
											$is_internal = ( false !== stripos( $row['referer'], home_url() ) );
											$ref_path = wp_parse_url( $row['referer'], PHP_URL_PATH );
										?>
											<div class="seo-ref-block">
												<span class="seo-ref-badge <?php echo ( $is_internal ? 'ref-badge-internal' : 'ref-badge-external' ); ?>">
													<?php echo ( $is_internal ? 'Внутренняя' : 'Внешняя' ); ?>
												</span>
												<a href="<?php echo esc_url( $row['referer'] ); ?>" target="_blank" class="seo-ref-url" title="<?php echo esc_attr( $row['referer'] ); ?>">
													<?php echo esc_html( ( $ref_host ? $ref_host : '' ) . ( $ref_path ? $ref_path : '' ) ); ?> ↗
												</a>
											</div>
										<?php else : ?>
											<span class="seo-ref-direct">Прямой заход / бот</span>
										<?php endif; ?>
									</td>

									<!-- User Agent -->
									<td class="col-ua">
										<span class="seo-ua-box" title="<?php echo esc_attr( $row['user_agent'] ); ?>">
											<?php
											$ua = $row['user_agent'];
											if ( false !== stripos( $ua, 'Googlebot' ) ) {
												echo '🤖 Googlebot';
											} elseif ( false !== stripos( $ua, 'Yandex' ) ) {
												echo '🤖 YandexBot';
											} elseif ( false !== stripos( $ua, 'bingbot' ) ) {
												echo '🤖 Bingbot';
											} elseif ( false !== stripos( $ua, 'Ahrefs' ) ) {
												echo '🤖 AhrefsBot';
											} elseif ( false !== stripos( $ua, 'Mobile' ) ) {
												echo '📱 Мобильный';
											} elseif ( ! empty( $ua ) ) {
												echo '💻 Десктоп';
											} else {
												echo '—';
											}
											?>
										</span>
									</td>

									<!-- Date -->
									<td class="col-date">
										<span class="seo-date-day"><?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $row['last_accessed'] ) ) ); ?></span>
										<span class="seo-date-time"><?php echo esc_html( date_i18n( 'H:i', strtotime( $row['last_accessed'] ) ) ); ?></span>
									</td>

									<!-- Actions -->
									<td class="col-actions">
										<div class="seo-actions-group">
											<a href="<?php echo esc_url( $redirect_link ); ?>" class="button button-small button-primary" title="Создать 301-редирект для этого битого адреса">
												🔀 Редирект
											</a>

											<form method="post" action="" style="display: inline;" onsubmit="return confirm('Удалить эту запись из лога?');">
												<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
												<input type="hidden" name="seo_core_action" value="delete_404_log" />
												<input type="hidden" name="log_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
												<button type="submit" class="button button-small button-link-delete" title="Удалить">✕</button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab 5: Migration from Rank Math
	 */
	protected function render_tab_migration( $db_detect ) {
		?>
		<div class="seo-migration-container">
			<!-- Option 1: Direct Database Migration -->
			<div class="seo-migration-card <?php echo ( $db_detect['has_data'] ? 'card-detected' : '' ); ?>">
				<div class="migration-card-header">
					<h3>1️⃣ Быстрый импорт из базы данных (1 клик)</h3>
					<?php if ( $db_detect['has_data'] ) : ?>
						<span class="seo-status-tag tag-success">✔ Данные Rank Math найдены в MySQL!</span>
					<?php else : ?>
						<span class="seo-status-tag tag-neutral">Таблицы Rank Math в БД не обнаружены</span>
					<?php endif; ?>
				</div>

				<p>Если на вашем сайте был установлен плагин Rank Math, все его шаблоны заголовков, настройки карты сайта и таблица 301-редиректов всё ещё находятся в базе данных сайта.</p>

				<?php if ( $db_detect['has_data'] ) : ?>
					<div class="seo-detection-list">
						<ul>
							<li>Шаблоны Titles & Meta: <strong><?php echo ( $db_detect['titles_found'] ? 'Обнаружены ✔' : 'Нет' ); ?></strong></li>
							<li>Настройки Sitemap: <strong><?php echo ( $db_detect['sitemap_found'] ? 'Обнаружены ✔' : 'Нет' ); ?></strong></li>
							<li>Таблица 301-редиректов: <strong><?php echo esc_html( $db_detect['redirect_count'] ); ?> правил найдено ✔</strong></li>
						</ul>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
						<input type="hidden" name="seo_core_action" value="import_rank_math_db" />
						<button type="submit" class="button button-primary button-hero" onclick="return confirm('Запустить импорт всех настроек и редиректов из Rank Math прямо из базы данных?');">
							⚡ Импортировать всё из БД в 1 клик
						</button>
					</form>
				<?php else : ?>
					<p class="description">Если таблицы уже очищены, воспользуйтесь вариантом №2 ниже (импорт через JSON файл).</p>
				<?php endif; ?>
			</div>

			<!-- Option 2: JSON File Import -->
			<div class="seo-migration-card">
				<div class="migration-card-header">
					<h3>2️⃣ Импорт из файла экспорта JSON</h3>
					<span class="seo-status-tag tag-ready">Файл <code>rank-math-settings-*.json</code></span>
				</div>

				<p>Если вы экспортировали настройки из Rank Math в файл (например, <code>rank-math-settings-2026-11-24...json</code>), вы можете загрузить его сюда. Плагин мгновенно прочитает все глобальные шаблоны и импортирует все 999+ правил 301 редиректов!</p>

				<form method="post" action="" enctype="multipart/form-data" class="seo-upload-form">
					<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
					<input type="hidden" name="seo_core_action" value="import_rank_math_json" />

					<div class="seo-file-picker">
						<input type="file" name="rank_math_json_file" accept=".json" required />
					</div>

					<button type="submit" class="button button-secondary button-hero">
						📂 Загрузить и импортировать JSON
					</button>
				</form>
			</div>

			<!-- Explanation on Post Meta (Why posts are already 100% safe) -->
			<div class="seo-migration-card card-info">
				<div class="migration-card-header">
					<h3>🛡️ Что происходит со статьями и товарами при отключении Rank Math?</h3>
				</div>
				<p><strong>Важнейшее преимущество архитектуры SEO Core Best:</strong></p>
				<p>В WordPress при деактивации Rank Math таблица метаданных <code>wp_postmeta</code> <strong>НЕ удаляется</strong>. Все ваши SEO заголовки, описания и ключевые слова (поля <code>rank_math_title</code>, <code>rank_math_description</code>) продолжают бережно храниться в базе.</p>
				<p>Наш плагин <strong>нативно читает эти поля</strong>, поэтому вам <strong>НЕ нужно</strong> пересохранять или экспортировать десятки тысяч постов! Никакого риска потери позиций в Google и Яндекс.</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab: Instant Indexing (Google Indexing API + IndexNow)
	 */
	protected function render_tab_indexing( $options ) {
		$subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( $_GET['subtab'] ) : 'google';
		$has_google_key = ! empty( $options['google_json_key'] );
		$has_indexnow_key = ! empty( $options['indexnow_api_key'] );

		// Fetch all post types with human-readable labels
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		if ( ! isset( $post_types['attachment'] ) ) {
			$post_types['attachment'] = get_post_type_object( 'attachment' );
		}

		$google_pts = isset( $options['google_index_post_types'] ) && is_array( $options['google_index_post_types'] )
			? $options['google_index_post_types']
			: array( 'post', 'page' );

		$indexnow_pts = isset( $options['indexnow_post_types'] ) && is_array( $options['indexnow_post_types'] )
			? $options['indexnow_post_types']
			: array( 'post', 'page' );

		$indexnow_key = Seo_Core_Instant_Indexing::get_indexnow_key();
		$verify_url   = home_url( '/' . $indexnow_key . '.txt' );
		?>
		<div class="seo-indexing-wrapper">
			<!-- Subtabs Navigation -->
			<div class="seo-subnav-bar">
				<a href="?page=seo-core-best&tab=indexing&subtab=console" class="seo-subnav-item <?php echo ( 'console' === $subtab ? 'is-active' : '' ); ?>">
					<?php esc_html_e( 'Консоль', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=indexing&subtab=google" class="seo-subnav-item <?php echo ( 'google' === $subtab ? 'is-active' : '' ); ?>">
					<?php if ( $has_google_key ) : ?><span class="seo-subnav-badge">✔</span><?php endif; ?>
					<?php esc_html_e( 'Настройки API Google', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=indexing&subtab=indexnow" class="seo-subnav-item <?php echo ( 'indexnow' === $subtab ? 'is-active' : '' ); ?>">
					<?php if ( $has_indexnow_key ) : ?><span class="seo-subnav-badge">✔</span><?php endif; ?>
					<?php esc_html_e( 'Настройки IndexNow API', 'seo-core-best' ); ?>
				</a>
				<a href="?page=seo-core-best&tab=indexing&subtab=history" class="seo-subnav-item <?php echo ( 'history' === $subtab ? 'is-active' : '' ); ?>">
					<?php esc_html_e( 'История IndexNow', 'seo-core-best' ); ?>
				</a>
			</div>

			<div class="seo-subtab-pane">
				<?php if ( 'console' === $subtab ) : ?>
					<!-- SUBTAB 1: CONSOLE -->
					<div class="seo-subtab-header">
						<h2><?php esc_html_e( 'Консоль мгновенной отправки URL', 'seo-core-best' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Введите один или несколько URL вашего сайта (по одному на строку), чтобы отправить их на обход в поисковые системы прямо сейчас.', 'seo-core-best' ); ?></p>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
						<input type="hidden" name="seo_core_action" value="submit_manual_indexing" />

						<div class="seo-setting-row">
							<div class="seo-setting-col-label">
								<strong><?php esc_html_e( 'Список URL адресов:', 'seo-core-best' ); ?></strong>
								<p class="description"><?php esc_html_e( 'Каждый адрес с новой строки (до 100 URL за раз).', 'seo-core-best' ); ?></p>
							</div>
							<div class="seo-setting-col-field">
								<textarea name="manual_urls" rows="6" class="seo-code-textarea" placeholder="https://example.com/page-1&#10;https://example.com/page-2" required></textarea>
							</div>
						</div>

						<div class="seo-setting-row">
							<div class="seo-setting-col-label">
								<strong><?php esc_html_e( 'Куда отправить:', 'seo-core-best' ); ?></strong>
							</div>
							<div class="seo-setting-col-field">
								<div class="seo-radio-group">
									<label class="seo-radio-item">
										<input type="radio" name="indexing_target" value="both" checked />
										<span class="seo-radio-label">🌐 <strong><?php esc_html_e( 'Везде (Google + IndexNow Яндекс/Bing)', 'seo-core-best' ); ?></strong></span>
									</label>
									<label class="seo-radio-item">
										<input type="radio" name="indexing_target" value="google" />
										<span class="seo-radio-label">🔴 <strong><?php esc_html_e( 'Только Google', 'seo-core-best' ); ?></strong> (Google Indexing API)</span>
									</label>
									<label class="seo-radio-item">
										<input type="radio" name="indexing_target" value="indexnow" />
										<span class="seo-radio-label">🟡 <strong><?php esc_html_e( 'Только IndexNow', 'seo-core-best' ); ?></strong> (Яндекс, Bing)</span>
									</label>
								</div>
							</div>
						</div>

						<div class="seo-setting-row-submit">
							<button type="submit" class="seo-btn-red">
								⚡ <?php esc_html_e( 'Отправить в поисковые системы', 'seo-core-best' ); ?>
							</button>
						</div>
					</form>

					<div class="seo-callout-grid" style="margin-top: 30px;">
						<div class="seo-callout">
							<h4>ℹ️ Google Indexing API</h4>
							<p>Позволяет вебмастерам напрямую уведомлять Google об обновлении или удалении страниц. Лимит по умолчанию: 200 URL в день на проект Google Cloud.</p>
						</div>
						<div class="seo-callout seo-callout-info">
							<h4>ℹ️ IndexNow API (Яндекс и Bing)</h4>
							<p>Протокол совместной быстрой индексации от Яндекс и Microsoft Bing. Лимит: до 10 000 URL в день. Робот посещает страницу в течение нескольких минут.</p>
						</div>
					</div>

				<?php elseif ( 'google' === $subtab ) : ?>
					<!-- SUBTAB 2: GOOGLE SETTINGS -->
					<div class="seo-subtab-header">
						<h2><?php esc_html_e( 'Настройки API мгновенной индексации Google', 'seo-core-best' ); ?></h2>
					</div>

					<form method="post" action="" enctype="multipart/form-data">
						<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
						<input type="hidden" name="seo_core_action" value="save_google_indexing" />

						<!-- Setting 1: Google JSON Key -->
						<div class="seo-setting-row">
							<div class="seo-setting-col-label">
								<label for="google_json_key"><strong>Google JSON Key:</strong></label>
								<p class="description">Загрузите файл ключа JSON служебного аккаунта, полученный из консоли Google API, или вставьте его содержимое в это поле.</p>
								<div class="seo-doc-link-box">
									<a href="https://developers.google.com/search/apis/indexing-api/v3/prereqs" target="_blank" class="seo-doc-link">
										<span class="dashicons dashicons-editor-help"></span> Прочитайте наше руководство по установке
									</a>
								</div>
							</div>
							<div class="seo-setting-col-field">
								<textarea name="google_json_key" id="google_json_key" rows="9" class="seo-code-textarea" placeholder="{\n  &quot;type&quot;: &quot;service_account&quot;,\n  &quot;project_id&quot;: &quot;...&quot;,\n  &quot;private_key_id&quot;: &quot;...&quot;,\n  &quot;private_key&quot;: &quot;-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n&quot;,\n  &quot;client_email&quot;: &quot;...@...iam.gserviceaccount.com&quot;\n}"><?php echo esc_textarea( $options['google_json_key'] ); ?></textarea>
								
								<div class="seo-file-upload-row">
									<span class="seo-upload-caption">Или загрузите файл JSON:</span>
									<input type="file" name="google_json_file" accept=".json" class="seo-file-picker-input" />
								</div>
							</div>
						</div>

						<!-- Setting 2: Post types to submit -->
						<div class="seo-setting-row">
							<div class="seo-setting-col-label">
								<strong>Отправить записи в Google:</strong>
								<p class="description">Отправляйте записи из этих типов записей автоматически в API мгновенной индексации Google при публикации, редактировании или удалении записи.</p>
							</div>
							<div class="seo-setting-col-field">
								<div class="seo-checkbox-list">
									<?php foreach ( $post_types as $pt_slug => $pt_obj ) : 
										$label = ! empty( $pt_obj->labels->name ) ? $pt_obj->labels->name : $pt_slug;
										$checked = in_array( $pt_slug, $google_pts, true );
									?>
										<label class="seo-checkbox-item">
											<input type="checkbox" name="google_index_post_types[]" value="<?php echo esc_attr( $pt_slug ); ?>" <?php checked( $checked, true ); ?> />
											<span class="seo-checkbox-name"><?php echo esc_html( $label ); ?></span>
											<span class="seo-checkbox-slug">(<code><?php echo esc_html( $pt_slug ); ?></code>)</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="seo-setting-row-submit">
							<button type="submit" class="seo-btn-red">
								<?php esc_html_e( 'Сохранить изменения', 'seo-core-best' ); ?>
							</button>
						</div>
					</form>

				<?php elseif ( 'indexnow' === $subtab ) : ?>
					<!-- SUBTAB 3: INDEXNOW SETTINGS -->
					<div class="seo-subtab-header">
						<h2><?php esc_html_e( 'Настройки IndexNow API (Яндекс и Bing)', 'seo-core-best' ); ?></h2>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
						<input type="hidden" name="seo_core_action" value="save_indexnow_settings" />

						<!-- Setting 1: IndexNow Key -->
						<div class="seo-setting-row">
							<div class="seo-setting-col-label">
								<label for="indexnow_api_key"><strong>Ключ IndexNow API:</strong></label>
								<p class="description">Уникальный 32-значный ключ авторизации. Поисковые системы Яндекс и Bing проверяют наличие файла верификации на сервере.</p>
								<div class="seo-doc-link-box">
									<a href="https://yandex.ru/support/webmaster/indexing/instant-indexing/indexnow.html" target="_blank" class="seo-doc-link">
										<span class="dashicons dashicons-editor-help"></span> Справка Яндекс Вебмастер
									</a>
								</div>
							</div>
							<div class="seo-setting-col-field">
								<div class="seo-key-input-wrap">
									<input type="text" name="indexnow_api_key" id="indexnow_api_key" value="<?php echo esc_attr( $indexnow_key ); ?>" class="regular-text seo-key-input" required />
								</div>
								<div class="seo-verify-notice">
									<span class="seo-verify-icon">🌐</span>
									<span>Файл верификации ключа: </span>
									<a href="<?php echo esc_url( $verify_url ); ?>" target="_blank" class="seo-verify-link">
										<code><?php echo esc_html( $verify_url ); ?></code> ↗
									</a>
									<span class="tag-success" style="margin-left: 8px;">Генерируется на лету</span>
								</div>
							</div>
						</div>

						<!-- Setting 2: Post types for IndexNow -->
						<div class="seo-setting-row">
							<div class="seo-setting-col-label">
								<strong>Отправить записи в IndexNow:</strong>
								<p class="description">Отправляйте записи из этих типов записей автоматически в Яндекс и Bing при публикации или обновлении записи.</p>
							</div>
							<div class="seo-setting-col-field">
								<div class="seo-checkbox-list">
									<?php foreach ( $post_types as $pt_slug => $pt_obj ) : 
										$label = ! empty( $pt_obj->labels->name ) ? $pt_obj->labels->name : $pt_slug;
										$checked = in_array( $pt_slug, $indexnow_pts, true );
									?>
										<label class="seo-checkbox-item">
											<input type="checkbox" name="indexnow_post_types[]" value="<?php echo esc_attr( $pt_slug ); ?>" <?php checked( $checked, true ); ?> />
											<span class="seo-checkbox-name"><?php echo esc_html( $label ); ?></span>
											<span class="seo-checkbox-slug">(<code><?php echo esc_html( $pt_slug ); ?></code>)</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="seo-setting-row-submit">
							<button type="submit" class="seo-btn-red">
								<?php esc_html_e( 'Сохранить изменения', 'seo-core-best' ); ?>
							</button>
						</div>
					</form>

				<?php elseif ( 'history' === $subtab ) : 
					$history = Seo_Core_Instant_Indexing::get_history( 50 );
				?>
					<!-- SUBTAB 4: HISTORY -->
					<div class="seo-subtab-header" style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<h2><?php esc_html_e( 'История отправки URL (Google & IndexNow)', 'seo-core-best' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Последние 50 запросов отправки страниц в поисковые системы.', 'seo-core-best' ); ?></p>
						</div>
						<?php if ( ! empty( $history ) ) : ?>
							<form method="post" action="">
								<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
								<input type="hidden" name="seo_core_action" value="clear_indexing_history" />
								<button type="submit" class="button button-secondary" onclick="return confirm('Очистить всю историю отправки?');">
									🗑️ <?php esc_html_e( 'Очистить историю', 'seo-core-best' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>

					<?php if ( empty( $history ) ) : ?>
						<div class="seo-box" style="margin-top: 20px; text-align: center; padding: 40px 20px;">
							<p style="font-size: 15px; color: #64748b; margin: 0 0 10px 0;">История отправки пока пуста.</p>
							<p class="description">Когда вы публикуете записи или отправляете URL из Консоли, результаты запросов к Google и Яндекс будут отображаться здесь.</p>
						</div>
					<?php else : ?>
						<table class="widefat striped seo-history-table" style="margin-top: 20px;">
							<thead>
								<tr>
									<th style="width: 140px;"><?php esc_html_e( 'Время', 'seo-core-best' ); ?></th>
									<th style="width: 130px;"><?php esc_html_e( 'Поисковик', 'seo-core-best' ); ?></th>
									<th style="width: 110px;"><?php esc_html_e( 'Действие', 'seo-core-best' ); ?></th>
									<th style="width: 110px;"><?php esc_html_e( 'Статус', 'seo-core-best' ); ?></th>
									<th><?php esc_html_e( 'URL страницы', 'seo-core-best' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $history as $row ) : 
									$is_ok = ( 200 === intval( $row->response_code ) || 202 === intval( $row->response_code ) );
								?>
									<tr>
										<td><span class="seo-history-time"><?php echo esc_html( date( 'd.m.Y H:i:s', strtotime( $row->created_at ) ) ); ?></span></td>
										<td>
											<?php if ( 'google' === $row->engine ) : ?>
												<span class="seo-badge-engine badge-google">Google API</span>
											<?php else : ?>
												<span class="seo-badge-engine badge-indexnow">IndexNow (Я/Bing)</span>
											<?php endif; ?>
										</td>
										<td>
											<span class="seo-badge-action"><?php echo esc_html( strtoupper( $row->action_type ) ); ?></span>
										</td>
										<td>
											<span class="seo-badge-status <?php echo ( $is_ok ? 'status-ok' : 'status-err' ); ?>">
												<?php echo esc_html( $row->response_code ); ?> <?php echo ( $is_ok ? 'OK' : 'ERR' ); ?>
											</span>
										</td>
										<td>
											<a href="<?php echo esc_url( $row->url ); ?>" target="_blank" class="seo-history-url">
												<?php echo esc_html( $row->url ); ?> ↗
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab: SEO Analyzer (Site-wide and single page on-page audit)
	 */
	protected function render_tab_analyzer() {
		$target_url = isset( $_GET['analyze_url'] ) ? esc_url_raw( trim( $_GET['analyze_url'] ) ) : home_url( '/' );
		$force      = ! empty( $_GET['force'] );
		$audit      = Seo_Core_Analyzer::run_audit( $target_url, $force );
		$audit_post_id = ! empty( $audit['post_id'] ) ? intval( $audit['post_id'] ) : 0;

		$restart_url = add_query_arg( array(
			'page'        => 'seo-core-best',
			'tab'         => 'analyzer',
			'analyze_url' => rawurlencode( $target_url ),
			'force'       => 1,
		), admin_url( 'admin.php' ) );
		?>
		<div class="seo-analyzer-wrapper">
			<!-- Header Bar -->
			<div class="seo-analyzer-header">
				<div class="seo-analyzer-title-box">
					<span class="seo-analyzer-label"><?php esc_html_e( 'SEO АНАЛИЗ ДЛЯ:', 'seo-core-best' ); ?></span>
					<h2 class="seo-analyzer-url">
						<a href="<?php echo esc_url( $target_url ); ?>" target="_blank" title="<?php esc_attr_e( 'Открыть страницу в новой вкладке', 'seo-core-best' ); ?>">
							<?php echo esc_html( $target_url ); ?> ↗
						</a>
					</h2>
				</div>
				<div class="seo-analyzer-actions">
					<a href="<?php echo esc_url( $restart_url ); ?>" class="button button-secondary">
						🔄 <?php esc_html_e( 'Повторить анализ', 'seo-core-best' ); ?>
					</a>
					<button type="button" class="button button-secondary" onclick="window.print();">
						🖨️ <?php esc_html_e( 'Печать', 'seo-core-best' ); ?>
					</button>
				</div>
			</div>

			<!-- URL Input Bar for analyzing any page -->
			<div class="seo-analyzer-search-bar">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="seo-analyzer-url-form">
					<input type="hidden" name="page" value="seo-core-best" />
					<input type="hidden" name="tab" value="analyzer" />
					<input type="hidden" name="force" value="1" />
					
					<label for="analyze_url_input" class="screen-reader-text"><?php esc_html_e( 'URL для анализа', 'seo-core-best' ); ?></label>
					<div class="seo-analyzer-input-wrap">
						<span class="dashicons dashicons-admin-site-alt3 seo-input-icon"></span>
						<input type="url" name="analyze_url" id="analyze_url_input" value="<?php echo esc_attr( $target_url ); ?>" placeholder="https://aliprofi.ru/page-url/" required class="seo-analyzer-input" />
					</div>
					<button type="submit" class="seo-btn-red">
						⚡ <?php esc_html_e( 'Запустить SEO Анализ', 'seo-core-best' ); ?>
					</button>
				</form>
			</div>

			<?php if ( empty( $audit['success'] ) ) : ?>
				<div class="notice notice-error" style="margin-top: 20px; padding: 15px;">
					<p><strong><?php esc_html_e( 'Ошибка анализа:', 'seo-core-best' ); ?></strong> <?php echo esc_html( $audit['error'] ); ?></p>
					<p><a href="<?php echo esc_url( $restart_url ); ?>" class="button button-primary"><?php esc_html_e( 'Попробовать снова', 'seo-core-best' ); ?></a></p>
				</div>
			<?php else : 
				$score         = intval( $audit['score'] );
				$passed_count  = intval( $audit['passed_count'] );
				$warning_count = intval( $audit['warning_count'] );
				$failed_count  = intval( $audit['failed_count'] );
				$total_tests   = intval( $audit['total_tests'] );
				$serp          = $audit['serp_preview'];

				// Circle color
				if ( $score >= 80 ) {
					$score_color = '#10b981'; // Green
					$score_badge = 'score-high';
				} elseif ( $score >= 60 ) {
					$score_color = '#f59e0b'; // Orange
					$score_badge = 'score-med';
				} else {
					$score_color = '#ef4444'; // Red
					$score_badge = 'score-low';
				}

				// Dashoffset for SVG circle (circumference = 2 * PI * 50 = 314.16)
				$dashoffset = 314.16 - ( 314.16 * ( $score / 100 ) );
			?>

				<!-- Summary Card (Matches user screenshot) -->
				<div class="seo-analyzer-summary-card">
					<!-- Col 1: Circular Gauge -->
					<div class="summary-col-score">
						<div class="seo-gauge-wrap">
							<svg class="seo-gauge-svg" viewBox="0 0 120 120">
								<circle class="gauge-bg" cx="60" cy="60" r="50" fill="none" stroke="#e2e8f0" stroke-width="10" />
								<circle class="gauge-progress" cx="60" cy="60" r="50" fill="none" stroke="<?php echo esc_attr( $score_color ); ?>" stroke-width="10"
									stroke-linecap="round"
									stroke-dasharray="314.16"
									stroke-dashoffset="<?php echo esc_attr( $dashoffset ); ?>"
									transform="rotate(-90 60 60)" />
							</svg>
							<div class="seo-gauge-inner">
								<span class="seo-gauge-num" style="color: <?php echo esc_attr( $score_color ); ?>;"><?php echo esc_html( $score ); ?>/100</span>
								<span class="seo-gauge-text">SEO SCORE</span>
							</div>
						</div>
					</div>

					<!-- Col 2: Progress Bars -->
					<div class="summary-col-stats">
						<div class="seo-bar-stat">
							<div class="stat-labels">
								<span class="stat-name">Passed Tests</span>
								<span class="stat-fraction"><strong><?php echo esc_html( $passed_count ); ?></strong>/<?php echo esc_html( $total_tests ); ?></span>
							</div>
							<div class="seo-progress-track">
								<div class="seo-progress-fill fill-pass" style="width: <?php echo ( $total_tests > 0 ? round( ( $passed_count / $total_tests ) * 100 ) : 0 ); ?>%;"></div>
							</div>
						</div>

						<div class="seo-bar-stat">
							<div class="stat-labels">
								<span class="stat-name">Warnings</span>
								<span class="stat-fraction"><strong><?php echo esc_html( $warning_count ); ?></strong>/<?php echo esc_html( $total_tests ); ?></span>
							</div>
							<div class="seo-progress-track">
								<div class="seo-progress-fill fill-warning" style="width: <?php echo ( $total_tests > 0 ? round( ( $warning_count / $total_tests ) * 100 ) : 0 ); ?>%;"></div>
							</div>
						</div>

						<div class="seo-bar-stat">
							<div class="stat-labels">
								<span class="stat-name">Failed Tests</span>
								<span class="stat-fraction"><strong><?php echo esc_html( $failed_count ); ?></strong>/<?php echo esc_html( $total_tests ); ?></span>
							</div>
							<div class="seo-progress-track">
								<div class="seo-progress-fill fill-fail" style="width: <?php echo ( $total_tests > 0 ? round( ( $failed_count / $total_tests ) * 100 ) : 0 ); ?>%;"></div>
							</div>
						</div>
					</div>

					<!-- Col 3: Live SERP Snippet Preview -->
					<div class="summary-col-preview">
						<div class="serp-preview-box">
							<div class="serp-preview-url"><?php echo esc_html( $serp['url'] ); ?></div>
							<div class="serp-preview-title"><?php echo esc_html( $serp['title'] ); ?></div>
							<div class="serp-preview-desc"><?php echo esc_html( $serp['desc'] ); ?></div>
						</div>
						<div class="serp-meta-info">
							<span>🕒 Проверено: <?php echo esc_html( date( 'd.m.Y в H:i', strtotime( $audit['checked_at'] ) ) ); ?></span>
							<span>⚡ Ответ: <?php echo esc_html( $audit['load_time'] ); ?>с (<?php echo esc_html( $audit['html_size_kb'] ); ?> КБ)</span>
						</div>
					</div>
				</div>

				<!-- Interactive Filter Tabs (All / Passed / Warnings / Failed) -->
				<div class="seo-analyzer-filter-bar">
					<button type="button" class="analyzer-pill-btn is-active" data-filter="all">
						<?php esc_html_e( 'Все тесты', 'seo-core-best' ); ?> <span class="pill-badge badge-all"><?php echo esc_html( $total_tests ); ?></span>
					</button>
					<button type="button" class="analyzer-pill-btn" data-filter="pass">
						<?php esc_html_e( 'Успешно', 'seo-core-best' ); ?> <span class="pill-badge badge-pass"><?php echo esc_html( $passed_count ); ?></span>
					</button>
					<button type="button" class="analyzer-pill-btn" data-filter="warning">
						<?php esc_html_e( 'Предупреждения', 'seo-core-best' ); ?> <span class="pill-badge badge-warning"><?php echo esc_html( $warning_count ); ?></span>
					</button>
					<button type="button" class="analyzer-pill-btn" data-filter="fail">
						<?php esc_html_e( 'Ошибки', 'seo-core-best' ); ?> <span class="pill-badge badge-fail"><?php echo esc_html( $failed_count ); ?></span>
					</button>
				</div>

				<!-- Tests List Grouped by Categories -->
				<?php
				$categories_def = array(
					'basic'     => __( 'Базовое SEO (Titles, Meta, Заголовки)', 'seo-core-best' ),
					'content'   => __( 'Контент и качество текста', 'seo-core-best' ),
					'media'     => __( 'Изображения и перелинковка', 'seo-core-best' ),
					'technical' => __( 'Индексация, Безопасность и Скорость', 'seo-core-best' ),
					'social'    => __( 'Соцсети и микроразметка Schema.org', 'seo-core-best' ),
				);

				foreach ( $categories_def as $cat_slug => $cat_title ) :
					$cat_tests = array_filter( $audit['tests'], function( $t ) use ( $cat_slug ) {
						return ( $t['category'] === $cat_slug );
					} );

					if ( empty( $cat_tests ) ) {
						continue;
					}
				?>
					<div class="seo-audit-section" data-cat="<?php echo esc_attr( $cat_slug ); ?>">
						<h3 class="seo-audit-section-title"><?php echo esc_html( $cat_title ); ?></h3>
						
						<div class="seo-audit-cards-list">
							<?php foreach ( $cat_tests as $t_key => $test ) : 
								$status = $test['status']; // pass, warning, fail
							?>
								<div class="seo-audit-item status-<?php echo esc_attr( $status ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
									<div class="audit-item-icon">
										<?php if ( 'pass' === $status ) : ?>
											<span class="icon-circle icon-pass">✓</span>
										<?php elseif ( 'warning' === $status ) : ?>
											<span class="icon-circle icon-warning">!</span>
										<?php else : ?>
											<span class="icon-circle icon-fail">✕</span>
										<?php endif; ?>
									</div>
									<div class="audit-item-body">
										<div class="audit-item-title-row">
											<h4 class="audit-item-title"><?php echo esc_html( $test['title'] ); ?></h4>
											<span class="audit-status-tag tag-<?php echo esc_attr( $status ); ?>">
												<?php
												if ( 'pass' === $status ) {
													esc_html_e( 'Пройдено', 'seo-core-best' );
												} elseif ( 'warning' === $status ) {
													esc_html_e( 'Внимание', 'seo-core-best' );
												} else {
													esc_html_e( 'Критично', 'seo-core-best' );
												}
												?>
											</span>
										</div>
										<p class="audit-item-desc"><?php echo wp_kses_post( $test['message'] ); ?></p>
										<?php if ( ! empty( $test['how_to'] ) ) : ?>
											<div class="audit-item-howto">
												<span class="dashicons dashicons-lightbulb"></span>
												<span><strong><?php esc_html_e( 'Как исправить:', 'seo-core-best' ); ?></strong> <?php echo esc_html( $test['how_to'] ); ?></span>
											</div>
										<?php endif; ?>

										<?php if ( 'pass' !== $status ) : ?>
											<?php if ( 'description' === $t_key ) : ?>
												<div class="audit-item-ai-action" style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
													<button type="button" class="button button-primary seo-btn-ai-fix" data-type="description" data-url="<?php echo esc_url( $target_url ); ?>" data-post-id="<?php echo esc_attr( $audit_post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'seo_core_ai_nonce' ) ); ?>">
														🤖 <?php esc_html_e( 'Исправить Description через AI', 'seo-core-best' ); ?>
													</button>
													<span class="seo-ai-fix-result" style="margin-left: 10px; font-weight: 500; font-size: 13px;"></span>
												</div>
											<?php elseif ( 'title' === $t_key ) : ?>
												<div class="audit-item-ai-action" style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
													<button type="button" class="button button-primary seo-btn-ai-fix" data-type="title" data-url="<?php echo esc_url( $target_url ); ?>" data-post-id="<?php echo esc_attr( $audit_post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'seo_core_ai_nonce' ) ); ?>">
														🤖 <?php esc_html_e( 'Исправить Title через AI', 'seo-core-best' ); ?>
													</button>
													<span class="seo-ai-fix-result" style="margin-left: 10px; font-weight: 500; font-size: 13px;"></span>
												</div>
											<?php elseif ( 'images' === $t_key ) : ?>
												<div class="audit-item-ai-action" style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
													<button type="button" class="button button-primary seo-btn-ai-fix" data-type="alts" data-url="<?php echo esc_url( $target_url ); ?>" data-post-id="<?php echo esc_attr( $audit_post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'seo_core_ai_nonce' ) ); ?>">
														🤖 <?php esc_html_e( 'Заполнить Alt картинок через AI', 'seo-core-best' ); ?>
													</button>
													<span class="seo-ai-fix-result" style="margin-left: 10px; font-weight: 500; font-size: 13px;"></span>
												</div>
											<?php elseif ( 'opengraph' === $t_key ) : ?>
												<div class="audit-item-ai-action" style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
													<a href="?page=seo-core-best&tab=social" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8;">
														🌐 <?php esc_html_e( 'Перейти в настройки «Соцсети и OpenGraph» ↗', 'seo-core-best' ); ?>
													</a>
												</div>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<!-- Fast Instant JS Filtering & AI Fixing -->
				<script>
				(function() {
					var buttons = document.querySelectorAll('.analyzer-pill-btn');
					var items   = document.querySelectorAll('.seo-audit-item');
					var sections = document.querySelectorAll('.seo-audit-section');

					buttons.forEach(function(btn) {
						btn.addEventListener('click', function() {
							buttons.forEach(function(b) { b.classList.remove('is-active'); });
							btn.classList.add('is-active');

							var filter = btn.getAttribute('data-filter');

							items.forEach(function(item) {
								if (filter === 'all' || item.getAttribute('data-status') === filter) {
									item.style.display = 'flex';
								} else {
									item.style.display = 'none';
								}
							});

							sections.forEach(function(sec) {
								var visibleItems = sec.querySelectorAll('.seo-audit-item[style*="display: flex"]');
								if (filter === 'all') {
									sec.style.display = 'block';
								} else {
									sec.style.display = (visibleItems.length > 0) ? 'block' : 'none';
								}
							});
						});
					});

					// Universal AI Fix Button in Analyzer
					document.addEventListener('click', function(e) {
						var btn = e.target.closest('.seo-btn-ai-fix');
						if (!btn) return;

						var type = btn.getAttribute('data-type');
						var url = btn.getAttribute('data-url');
						var postId = btn.getAttribute('data-post-id');
						var nonce = btn.getAttribute('data-nonce');
						var resultEl = btn.nextElementSibling;
						var origText = btn.textContent;

						btn.disabled = true;
						btn.textContent = '⏳ AI думает и исправляет...';
						resultEl.innerHTML = '<span style="color:#64748b;">Нейросеть генерирует и сохраняет исправление...</span>';

						var formData = new FormData();
						formData.append('action', 'seo_core_ai_fix_issue');
						formData.append('nonce', nonce);
						formData.append('fix_type', type);
						formData.append('url', url);
						if (postId) {
							formData.append('post_id', postId);
						}

						fetch(ajaxurl, {
							method: 'POST',
							body: formData
						})
						.then(function(r) { return r.json(); })
						.then(function(resp) {
							if (resp.success) {
								btn.textContent = '✔ Готово и сохранено!';
								btn.style.backgroundColor = '#16a34a';
								btn.style.borderColor = '#15803d';
								resultEl.innerHTML = '<span style="color:#166534; font-weight:600;">' + resp.data.message + ' <a href="?page=seo-core-best&tab=analyzer&analyze_url=' + encodeURIComponent(url) + '&force=1" class="button button-small" style="margin-left:8px; font-weight:700;">Обновить анализ 🔄</a></span>';
							} else {
								btn.disabled = false;
								btn.textContent = origText;
								resultEl.innerHTML = '<span style="color:#b91c1c;">✕ ' + (resp.data && resp.data.message ? resp.data.message : 'Ошибка исправления') + '</span>';
							}
						})
						.catch(function() {
							btn.disabled = false;
							btn.textContent = origText;
							resultEl.innerHTML = '<span style="color:#b91c1c;">✕ Ошибка запроса к серверу</span>';
						});
					});
				})();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tab: Image SEO (Autonomous on-the-fly Alt and Title)
	 */
	protected function render_tab_images( $options ) {
		$auto_alt   = Seo_Core_Options::get( 'enable_auto_image_alt', 1 );
		$alt_tpl    = Seo_Core_Options::get( 'auto_image_alt_template', '%title%' );
		$auto_title = Seo_Core_Options::get( 'enable_auto_image_title', 0 );
		$title_tpl  = Seo_Core_Options::get( 'auto_image_title_template', '%title%' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
			<input type="hidden" name="seo_core_action" value="save_settings" />
			<input type="hidden" name="seo_core_tab" value="images" />

			<div class="seo-migration-card">
				<div class="migration-card-header">
					<h3>🖼️ Автоматический Image SEO (Подстановка на лету)</h3>
					<span class="seo-status-tag tag-ready">100% Автономно</span>
				</div>
				<p>Автоматически добавляет теги <code>alt</code> и <code>title</code> к изображениям в контенте статей и товарах при их отсутствии. Мгновенно решает проблему отсутствующих Alt для поисковых систем Google и Яндекс без изменения исходного контента в базе данных.</p>

				<table class="form-table seo-table">
					<tr>
						<th scope="row">
							<label for="enable_auto_image_alt">Авто-Alt для картинок</label>
						</th>
						<td>
							<label class="seo-checkbox-tile">
								<input type="checkbox" name="enable_auto_image_alt" id="enable_auto_image_alt" value="1" <?php checked( $auto_alt ); ?> />
								<span>Автоматически добавлять тег <code>alt</code> к изображениям, если он пуст</span>
							</label>
							<p class="description">Роботы Google и Яндекс будут видеть заполненные описания для всех картинок сайта.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="auto_image_alt_template">Шаблон для Alt</label>
						</th>
						<td>
							<input type="text" name="auto_image_alt_template" id="auto_image_alt_template" value="<?php echo esc_attr( $alt_tpl ); ?>" class="regular-text" />
							<p class="description">Доступные теги: <code>%title%</code> (заголовок статьи), <code>%filename%</code> (имя файла картинки без дефисов), <code>%count%</code> (порядковый номер фото в посте), <code>%sitename%</code></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="enable_auto_image_title">Авто-Title для картинок</label>
						</th>
						<td>
							<label class="seo-checkbox-tile">
								<input type="checkbox" name="enable_auto_image_title" id="enable_auto_image_title" value="1" <?php checked( $auto_title ); ?> />
								<span>Автоматически добавлять всплывающий атрибут <code>title</code> к изображениям</span>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="auto_image_title_template">Шаблон для Title</label>
						</th>
						<td>
							<input type="text" name="auto_image_title_template" id="auto_image_title_template" value="<?php echo esc_attr( $title_tpl ); ?>" class="regular-text" />
							<p class="description">Доступные теги: <code>%title%</code>, <code>%filename%</code>, <code>%count%</code></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="seo-setting-row-submit">
				<button type="submit" class="seo-btn-red">
					Сохранить настройки Image SEO
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Tab: AI Assistant Settings (DeepSeek & Mistral)
	 */
	protected function render_tab_ai( $options ) {
		$ai_provider  = Seo_Core_Options::get( 'ai_provider', 'deepseek' );
		$deepseek_key = Seo_Core_Options::get( 'deepseek_api_key', '' );
		$mistral_key  = Seo_Core_Options::get( 'mistral_api_key', '' );
		$ai_nonce     = wp_create_nonce( 'seo_core_ai_nonce' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'seo_core_admin_action', 'seo_core_admin_nonce' ); ?>
			<input type="hidden" name="seo_core_action" value="save_settings" />
			<input type="hidden" name="seo_core_tab" value="ai" />

			<div class="seo-migration-card card-info">
				<div class="migration-card-header">
					<h3>🤖 Настройки AI Ассистента (DeepSeek & Mistral AI)</h3>
					<span class="seo-status-tag tag-success">Прямой API доступ</span>
				</div>
				<p>Встроенный искусственный интеллект позволяет в 1 клик исправлять ошибки в <strong>SEO Анализаторе</strong> (сокращать и оптимизировать Description, переписывать Title, генерировать смысловые Alt для фото), а также создавать мета-теги в редакторе статей.</p>

				<table class="form-table seo-table">
					<tr>
						<th scope="row">Выбор AI Провайдера</th>
						<td>
							<div class="seo-radio-group">
								<label class="seo-radio-item">
									<input type="radio" name="ai_provider" value="deepseek" <?php checked( 'deepseek' === $ai_provider ); ?> />
									<span class="seo-radio-label">🚀 <strong>DeepSeek</strong> (Модель <code>deepseek-chat</code> — ультрабыстрая, экономичная и точная)</span>
								</label>
								<label class="seo-radio-item">
									<input type="radio" name="ai_provider" value="mistral" <?php checked( 'mistral' === $ai_provider ); ?> />
									<span class="seo-radio-label">🌪️ <strong>Mistral AI</strong> (Модель <code>mistral-small-latest</code> — европейская модель с отличным русским языком)</span>
								</label>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="deepseek_api_key">DeepSeek API Key</label>
						</th>
						<td>
							<input type="password" name="deepseek_api_key" id="deepseek_api_key" value="<?php echo esc_attr( $deepseek_key ); ?>" class="regular-text seo-code-input" placeholder="sk-..." autocomplete="off" />
							<p class="description">Получить ключ можно в консоли DeepSeek: <a href="https://platform.deepseek.com/api_keys" target="_blank">platform.deepseek.com/api_keys ↗</a></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="mistral_api_key">Mistral API Key</label>
						</th>
						<td>
							<input type="password" name="mistral_api_key" id="mistral_api_key" value="<?php echo esc_attr( $mistral_key ); ?>" class="regular-text seo-code-input" placeholder="..." autocomplete="off" />
							<p class="description">Получить ключ можно в консоли Mistral: <a href="https://console.mistral.ai/api-keys/" target="_blank">console.mistral.ai/api-keys ↗</a></p>
						</td>
					</tr>
					<tr>
						<th scope="row">Проверка соединения</th>
						<td>
							<button type="button" class="button button-secondary" id="btn-test-ai-conn">
								🔌 Проверить подключение к выбранному AI
							</button>
							<span id="ai-conn-result" style="margin-left: 12px; font-weight: 600; font-size: 13px;"></span>
						</td>
					</tr>
				</table>
			</div>

			<div class="seo-setting-row-submit">
				<button type="submit" class="seo-btn-red">
					Сохранить настройки AI
				</button>
			</div>
		</form>

		<script>
		document.getElementById('btn-test-ai-conn').addEventListener('click', function() {
			var resEl = document.getElementById('ai-conn-result');
			var provider = document.querySelector('input[name="ai_provider"]:checked').value;
			var key = (provider === 'mistral') 
				? document.getElementById('mistral_api_key').value 
				: document.getElementById('deepseek_api_key').value;

			resEl.innerHTML = '<span style="color:#64748b;">⏳ Проверка соединения...</span>';

			var formData = new FormData();
			formData.append('action', 'seo_core_ai_test_connection');
			formData.append('nonce', '<?php echo esc_js( $ai_nonce ); ?>');
			formData.append('provider', provider);
			formData.append('key', key);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(function(r) { return r.json(); })
			.then(function(response) {
				if (response.success) {
					resEl.innerHTML = '<span style="color:#166534;">✔ ' + response.data.message + '</span>';
				} else {
					resEl.innerHTML = '<span style="color:#b91c1c;">✕ Ошибка: ' + (response.data.message || 'Не удалось подключиться') + '</span>';
				}
			})
			.catch(function() {
				resEl.innerHTML = '<span style="color:#b91c1c;">✕ Ошибка связи с сервером</span>';
			});
		});
		</script>
		<?php
	}
}
