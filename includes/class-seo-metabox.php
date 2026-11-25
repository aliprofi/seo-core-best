<?php
/**
 * Editor Metabox for Posts, Pages & CPTs
 *
 * @package SeoCoreBest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Core_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'hook_taxonomy_fields' ), 20 );
	}

	/**
	 * Dynamically attach SEO fields to all public taxonomies
	 */
	public function hook_taxonomy_fields() {
		$taxonomies = get_taxonomies( array( 'public' => true ) );
		foreach ( $taxonomies as $tax ) {
			add_action( "{$tax}_edit_form_fields", array( $this, 'render_term_fields' ), 10, 2 );
			add_action( "edited_{$tax}", array( $this, 'save_term_fields' ), 10, 2 );
		}
	}

	/**
	 * Register meta box
	 */
	public function add_meta_box() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $pt ) {
			if ( 'attachment' === $pt ) {
				continue;
			}
			add_meta_box(
				'seo_core_best_metabox',
				__( '⭐ SEO Оптимизация (SEO Core Best)', 'seo-core-best' ),
				array( $this, 'render_metabox' ),
				$pt,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Enqueue admin scripts & styles
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook && false === strpos( $hook, 'seo-core-best' ) ) {
			return;
		}

		wp_enqueue_style(
			'seo-core-admin',
			plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'seo-core-metabox',
			plugins_url( 'assets/js/metabox.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script( 'seo-core-metabox', 'seoCoreAI', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'seo_core_ai_nonce' ),
		) );
	}

	/**
	 * Render Metabox HTML
	 *
	 * @param WP_Post $post
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'seo_core_metabox_nonce_action', 'seo_core_metabox_nonce' );

		// Title
		$seo_title = get_post_meta( $post->ID, '_seo_title', true );
		if ( empty( $seo_title ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
			$seo_title = get_post_meta( $post->ID, 'rank_math_title', true );
		}

		// Description
		$seo_desc = get_post_meta( $post->ID, '_seo_description', true );
		if ( empty( $seo_desc ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
			$seo_desc = get_post_meta( $post->ID, 'rank_math_description', true );
		}

		// Focus Keyword
		$focus_kw = get_post_meta( $post->ID, '_seo_focus_kw', true );
		if ( empty( $focus_kw ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
			$focus_kw = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
		}

		// Canonical
		$canonical = get_post_meta( $post->ID, '_seo_canonical', true );
		if ( empty( $canonical ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
			$canonical = get_post_meta( $post->ID, 'rank_math_canonical_url', true );
		}

		// Robots
		$robots = get_post_meta( $post->ID, '_seo_robots', true );
		if ( empty( $robots ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
			$rm_r = get_post_meta( $post->ID, 'rank_math_robots', true );
			if ( is_array( $rm_r ) ) {
				$robots = implode( ', ', $rm_r );
			} elseif ( is_string( $rm_r ) ) {
				$robots = $rm_r;
			}
		}

		$is_noindex  = ( false !== stripos( (string) $robots, 'noindex' ) );
		$is_nofollow = ( false !== stripos( (string) $robots, 'nofollow' ) );

		// Computed preview values
		$rendered_title = ! empty( $seo_title ) ? Seo_Core_Variables::replace( $seo_title, $post ) : ( get_the_title( $post->ID ) . ' ' . Seo_Core_Options::get( 'title_separator', '—' ) . ' ' . get_bloginfo( 'name' ) );
		$rendered_desc  = ! empty( $seo_desc ) ? Seo_Core_Variables::replace( $seo_desc, $post ) : Seo_Core_Variables::get_post_excerpt( $post );
		$permalink      = get_permalink( $post->ID );
		?>
		<div class="seo-core-metabox-wrapper">
			<!-- Google SERP Preview Card -->
			<div class="seo-core-serp-preview">
				<div class="seo-core-serp-header">
					<span class="seo-core-serp-tag">Google SERP Snippet Preview</span>
					<span class="seo-core-badge-rankmath">Rank Math Meta Compatible ✔</span>
				</div>
				<div class="seo-core-snippet-box">
					<div class="seo-core-snippet-url" id="seo-preview-url"><?php echo esc_html( $permalink ); ?></div>
					<div class="seo-core-snippet-title" id="seo-preview-title"><?php echo esc_html( $rendered_title ); ?></div>
					<div class="seo-core-snippet-desc" id="seo-preview-desc"><?php echo esc_html( $rendered_desc ? $rendered_desc : 'Пожалуйста, введите мета-описание для поисковой выдачи Google и Яндекс...' ); ?></div>
				</div>
			</div>

			<!-- Meta Fields Form -->
			<div class="seo-core-fields-container">
				<!-- Title -->
				<div class="seo-core-field-group">
					<div class="seo-core-field-label-row">
						<label for="seo_core_title"><strong><?php esc_html_e( 'SEO Заголовок (Title):', 'seo-core-best' ); ?></strong></label>
						<div style="display: flex; gap: 10px; align-items: center;">
							<button type="button" class="button button-small seo-ai-gen-meta" data-post-id="<?php echo $post->ID; ?>" title="<?php esc_attr_e( 'Сгенерировать привлекательный Title и Description через AI', 'seo-core-best' ); ?>">
								🤖 <?php esc_html_e( 'AI Генерация', 'seo-core-best' ); ?>
							</button>
							<span class="seo-core-counter"><span id="seo-title-count"><?php echo mb_strlen( $seo_title ); ?></span> / 60 знаков</span>
						</div>
					</div>
					<input type="text" name="seo_core_title" id="seo_core_title" class="widefat seo-core-input" value="<?php echo esc_attr( $seo_title ); ?>" placeholder="<?php esc_attr_e( 'Оставьте пустым для автогенерации по шаблону', 'seo-core-best' ); ?>" />
					<div class="seo-core-quick-vars">
						<span>Вставить тег:</span>
						<button type="button" class="button button-small seo-tag-insert" data-tag="%title%">%title%</button>
						<button type="button" class="button button-small seo-tag-insert" data-tag="%sep%">%sep%</button>
						<button type="button" class="button button-small seo-tag-insert" data-tag="%sitename%">%sitename%</button>
						<button type="button" class="button button-small seo-tag-insert" data-tag="%org_name%">%org_name%</button>
						<button type="button" class="button button-small seo-tag-insert" data-tag="%category%">%category%</button>
						<button type="button" class="button button-small seo-tag-insert" data-tag="%tag%">%tag%</button>
						<button type="button" class="button button-small seo-tag-insert" data-tag="[currentyear]">[currentyear]</button>
						<button type="button" class="button button-small seo-tag-insert" data-tag="[upcomingmonth]">[upcomingmonth]</button>
					</div>
				</div>

				<!-- Description -->
				<div class="seo-core-field-group">
					<div class="seo-core-field-label-row">
						<label for="seo_core_description"><strong><?php esc_html_e( 'Мета Описание (Description):', 'seo-core-best' ); ?></strong></label>
						<span class="seo-core-counter"><span id="seo-desc-count"><?php echo mb_strlen( $seo_desc ); ?></span> / 160 знаков</span>
					</div>
					<textarea name="seo_core_description" id="seo_core_description" rows="3" class="widefat seo-core-input" placeholder="<?php esc_attr_e( 'Краткое привлекательное описание статьи для сниппета Google / Яндекс...', 'seo-core-best' ); ?>"><?php echo esc_textarea( $seo_desc ); ?></textarea>
				</div>

				<!-- AI Image Alt Generation Row -->
				<div class="seo-ai-metabox-row" style="margin-top: 10px; margin-bottom: 18px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
					<div style="display: flex; align-items: center; gap: 10px;">
						<button type="button" class="button button-secondary" id="seo-btn-gen-alts" data-post-id="<?php echo $post->ID; ?>">
							🖼️ <?php esc_html_e( 'Заполнить Alt для картинок статьи через AI', 'seo-core-best' ); ?>
						</button>
						<span id="seo-ai-alts-status" style="font-size: 13px;"></span>
					</div>
					<span style="font-size: 11px; color: #64748b; font-weight: 500;">Поддержка DeepSeek / Mistral</span>
				</div>

				<!-- Focus Keyword & Canonical Grid -->
				<div class="seo-core-grid-2col">
					<div class="seo-core-field-group">
						<label for="seo_core_focus_kw"><strong><?php esc_html_e( 'Фокусное ключевое слово:', 'seo-core-best' ); ?></strong></label>
						<input type="text" name="seo_core_focus_kw" id="seo_core_focus_kw" class="widefat" value="<?php echo esc_attr( $focus_kw ); ?>" placeholder="например: промокоды алиэкспресс" />
					</div>
					<div class="seo-core-field-group">
						<label for="seo_core_canonical"><strong><?php esc_html_e( 'Канонический URL (Canonical):', 'seo-core-best' ); ?></strong></label>
						<input type="url" name="seo_core_canonical" id="seo_core_canonical" class="widefat" value="<?php echo esc_attr( $canonical ); ?>" placeholder="По умолчанию: текущий URL" />
					</div>
				</div>

				<!-- Robots Robots Meta -->
				<div class="seo-core-field-group seo-core-checkboxes-row">
					<strong>Индексация (Robots Meta):</strong>
					<label class="seo-core-checkbox-label">
						<input type="checkbox" name="seo_core_noindex" value="1" <?php checked( $is_noindex ); ?> />
						<span>Запретить индексацию (<code>noindex</code>)</span>
					</label>
					<label class="seo-core-checkbox-label">
						<input type="checkbox" name="seo_core_nofollow" value="1" <?php checked( $is_nofollow ); ?> />
						<span>Запретить переход по ссылкам (<code>nofollow</code>)</span>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Metabox Data
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_meta_box( $post_id, $post ) {
		// Nonce check
		if ( ! isset( $_POST['seo_core_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['seo_core_metabox_nonce'], 'seo_core_metabox_nonce_action' ) ) {
			return;
		}

		// Autosave / permissions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Title
		$title = isset( $_POST['seo_core_title'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_core_title'] ) ) : '';
		update_post_meta( $post_id, '_seo_title', $title );

		// Description
		$desc = isset( $_POST['seo_core_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['seo_core_description'] ) ) : '';
		update_post_meta( $post_id, '_seo_description', $desc );

		// Focus KW
		$kw = isset( $_POST['seo_core_focus_kw'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_core_focus_kw'] ) ) : '';
		update_post_meta( $post_id, '_seo_focus_kw', $kw );

		// Canonical
		$canonical = isset( $_POST['seo_core_canonical'] ) ? esc_url_raw( wp_unslash( $_POST['seo_core_canonical'] ) ) : '';
		update_post_meta( $post_id, '_seo_canonical', $canonical );

		// Robots
		$robots = array();
		if ( ! empty( $_POST['seo_core_noindex'] ) ) {
			$robots[] = 'noindex';
		} else {
			$robots[] = 'index';
		}
		if ( ! empty( $_POST['seo_core_nofollow'] ) ) {
			$robots[] = 'nofollow';
		} else {
			$robots[] = 'follow';
		}
		$robots_str = implode( ', ', $robots );
		update_post_meta( $post_id, '_seo_robots', $robots_str );

		// Synchronize with Rank Math meta keys for 100% backward compatibility
		if ( Seo_Core_Options::get( 'rank_math_compat_write', 1 ) ) {
			update_post_meta( $post_id, 'rank_math_title', $title );
			update_post_meta( $post_id, 'rank_math_description', $desc );
			update_post_meta( $post_id, 'rank_math_focus_keyword', $kw );
			update_post_meta( $post_id, 'rank_math_canonical_url', $canonical );
			update_post_meta( $post_id, 'rank_math_robots', $robots );
		}
	}

	/**
	 * Render SEO fields on Taxonomy Term Edit screens
	 *
	 * @param WP_Term $term
	 */
	public function render_term_fields( $term ) {
		$term_id = $term->term_id;

		$title = get_term_meta( $term_id, '_seo_title', true );
		if ( empty( $title ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
			$title = get_term_meta( $term_id, 'rank_math_title', true );
		}

		$desc = get_term_meta( $term_id, '_seo_description', true );
		if ( empty( $desc ) && Seo_Core_Options::get( 'rank_math_compat_read', 1 ) ) {
			$desc = get_term_meta( $term_id, 'rank_math_description', true );
		}

		$robots = get_term_meta( $term_id, '_seo_robots', true );
		$is_noindex = ( false !== stripos( (string) $robots, 'noindex' ) );
		?>
		<tr class="form-field">
			<th scope="row" colspan="2" style="background: #f8fafc; padding: 10px 14px; font-weight: 700; border-top: 2px solid #e2e8f0;">
				⭐ SEO Оптимизация термина (SEO Core Best)
			</th>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="seo_core_term_title"><?php esc_html_e( 'SEO Title', 'seo-core-best' ); ?></label></th>
			<td>
				<input type="text" name="seo_core_term_title" id="seo_core_term_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" placeholder="По умолчанию: %term% — %sitename%" />
				<p class="description">Пользовательский SEO-заголовок для этой рубрики/термина.</p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="seo_core_term_desc"><?php esc_html_e( 'Meta Description', 'seo-core-best' ); ?></label></th>
			<td>
				<textarea name="seo_core_term_desc" id="seo_core_term_desc" rows="3" class="large-text" placeholder="По умолчанию: описание термина"><?php echo esc_textarea( $desc ); ?></textarea>
				<p class="description">Краткое описание рубрики для сниппета поисковика.</p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Индексация (Robots)', 'seo-core-best' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="seo_core_term_noindex" value="1" <?php checked( $is_noindex ); ?> />
					Запретить индексацию этого термина (<code>noindex</code>)
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save SEO fields for Taxonomy Term
	 *
	 * @param int $term_id
	 */
	public function save_term_fields( $term_id ) {
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$title = isset( $_POST['seo_core_term_title'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_core_term_title'] ) ) : '';
		update_term_meta( $term_id, '_seo_title', $title );

		$desc = isset( $_POST['seo_core_term_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['seo_core_term_desc'] ) ) : '';
		update_term_meta( $term_id, '_seo_description', $desc );

		$robots = ! empty( $_POST['seo_core_term_noindex'] ) ? 'noindex, follow' : 'index, follow';
		update_term_meta( $term_id, '_seo_robots', $robots );

		// Dual-write to Rank Math term meta
		if ( Seo_Core_Options::get( 'rank_math_compat_write', 1 ) ) {
			update_term_meta( $term_id, 'rank_math_title', $title );
			update_term_meta( $term_id, 'rank_math_description', $desc );
			update_term_meta( $term_id, 'rank_math_robots', array( $robots ) );
		}
	}
}
