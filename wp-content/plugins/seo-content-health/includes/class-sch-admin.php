<?php
/**
 * Admin menu registration, the Settings page, and enqueueing of
 * admin CSS/JS used across the plugin's screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCH_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Content Health', 'content-health-seo' ),
			__( 'Content Health', 'content-health-seo' ),
			'edit_posts',
			'sch_dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-heart',
			59
		);

		add_submenu_page(
			'sch_dashboard',
			__( 'Overview', 'content-health-seo' ),
			__( 'Overview', 'content-health-seo' ),
			'edit_posts',
			'sch_dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'sch_dashboard',
			__( 'Settings', 'content-health-seo' ),
			__( 'Settings', 'content-health-seo' ),
			'manage_options',
			'sch_settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'sch_options_group', 'sch_options', array( $this, 'sanitize_options' ) );
	}

	public function sanitize_options( $input ) {
		$defaults = sch_default_options();
		$out = array();

		$out['post_types']            = isset( $input['post_types'] ) ? array_map( 'sanitize_text_field', (array) $input['post_types'] ) : $defaults['post_types'];
		$out['title_min']             = isset( $input['title_min'] ) ? absint( $input['title_min'] ) : $defaults['title_min'];
		$out['title_max']             = isset( $input['title_max'] ) ? absint( $input['title_max'] ) : $defaults['title_max'];
		$out['desc_min']              = isset( $input['desc_min'] ) ? absint( $input['desc_min'] ) : $defaults['desc_min'];
		$out['desc_max']              = isset( $input['desc_max'] ) ? absint( $input['desc_max'] ) : $defaults['desc_max'];
		$out['ai_provider']           = isset( $input['ai_provider'] ) && in_array( $input['ai_provider'], array( 'none', 'anthropic' ), true ) ? $input['ai_provider'] : 'none';
		$out['ai_api_key']            = isset( $input['ai_api_key'] ) ? sanitize_text_field( $input['ai_api_key'] ) : '';
		$out['auto_optimize_uploads'] = ! empty( $input['auto_optimize_uploads'] ) ? 1 : 0;
		$out['jpeg_quality']          = isset( $input['jpeg_quality'] ) ? min( 100, max( 10, absint( $input['jpeg_quality'] ) ) ) : $defaults['jpeg_quality'];
		$out['webp_quality']          = isset( $input['webp_quality'] ) ? min( 100, max( 10, absint( $input['webp_quality'] ) ) ) : $defaults['webp_quality'];
		$out['serve_webp_frontend']   = ! empty( $input['serve_webp_frontend'] ) ? 1 : 0;

		return $out;
	}

	public function enqueue_assets( $hook ) {
		$is_plugin_screen = ( false !== strpos( $hook, 'sch_' ) );
		$is_post_screen   = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_plugin_screen && ! $is_post_screen ) {
			return;
		}

		wp_enqueue_style( 'sch-admin', SCH_PLUGIN_URL . 'assets/admin.css', array(), SCH_VERSION );
		wp_enqueue_script( 'sch-admin', SCH_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), SCH_VERSION, true );

		wp_localize_script( 'sch-admin', 'SCH_Data', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sch_admin_nonce' ),
			'i18n'    => array(
				'saved'      => __( 'Saved', 'content-health-seo' ),
				'optimizing' => __( 'Optimizing…', 'content-health-seo' ),
				'done'       => __( 'All done!', 'content-health-seo' ),
			),
		) );
	}

	public function render_dashboard_page() {
		?>
		<div class="wrap sch-wrap">
			<h1><?php esc_html_e( 'Content Health — Overview', 'content-health-seo' ); ?></h1>
			<p><?php esc_html_e( 'This plugin scores every post on one combined 0-100 scale: SEO title, meta description, image alt text, and image optimization. Fix the lowest factors first for the fastest score gains.', 'content-health-seo' ); ?></p>
			<?php ( new SCH_Health_Score() )->render_dashboard_widget(); ?>
			<p style="margin-top:20px;">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sch_alt_text' ) ); ?>"><?php esc_html_e( 'Fix Missing Alt Text', 'content-health-seo' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sch_image_optimizer' ) ); ?>"><?php esc_html_e( 'Optimize Images', 'content-health-seo' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sch_settings' ) ); ?>"><?php esc_html_e( 'Settings', 'content-health-seo' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = sch_get_options();
		?>
		<div class="wrap sch-wrap">
			<h1><?php esc_html_e( 'Content Health Settings', 'content-health-seo' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'sch_options_group' ); ?>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'SEO title length (chars)', 'content-health-seo' ); ?></th>
						<td>
							<input type="number" name="sch_options[title_min]" value="<?php echo esc_attr( $opts['title_min'] ); ?>" style="width:80px;" />
							&ndash;
							<input type="number" name="sch_options[title_max]" value="<?php echo esc_attr( $opts['title_max'] ); ?>" style="width:80px;" />
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Meta description length (chars)', 'content-health-seo' ); ?></th>
						<td>
							<input type="number" name="sch_options[desc_min]" value="<?php echo esc_attr( $opts['desc_min'] ); ?>" style="width:80px;" />
							&ndash;
							<input type="number" name="sch_options[desc_max]" value="<?php echo esc_attr( $opts['desc_max'] ); ?>" style="width:80px;" />
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Auto-optimize new uploads', 'content-health-seo' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="sch_options[auto_optimize_uploads]" value="1" <?php checked( $opts['auto_optimize_uploads'], 1 ); ?> />
								<?php esc_html_e( 'Compress and create a WebP copy automatically when an image is uploaded', 'content-health-seo' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'JPEG quality', 'content-health-seo' ); ?></th>
						<td><input type="number" min="10" max="100" name="sch_options[jpeg_quality]" value="<?php echo esc_attr( $opts['jpeg_quality'] ); ?>" style="width:80px;" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WebP quality', 'content-health-seo' ); ?></th>
						<td><input type="number" min="10" max="100" name="sch_options[webp_quality]" value="<?php echo esc_attr( $opts['webp_quality'] ); ?>" style="width:80px;" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Serve WebP on the frontend', 'content-health-seo' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="sch_options[serve_webp_frontend]" value="1" <?php checked( $opts['serve_webp_frontend'], 1 ); ?> />
								<?php esc_html_e( 'Swap <img> src to the WebP version for browsers that support it', 'content-health-seo' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'AI assist (optional)', 'content-health-seo' ); ?></th>
						<td>
							<select name="sch_options[ai_provider]">
								<option value="none" <?php selected( $opts['ai_provider'], 'none' ); ?>><?php esc_html_e( 'Off — use rule-based suggestions only', 'content-health-seo' ); ?></option>
								<option value="anthropic" <?php selected( $opts['ai_provider'], 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (Claude) API', 'content-health-seo' ); ?></option>
							</select>
							<br><br>
							<input type="password" name="sch_options[ai_api_key]" value="<?php echo esc_attr( $opts['ai_api_key'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'API key (only used if a provider is selected)', 'content-health-seo' ); ?>" />
							<p class="description"><?php esc_html_e( 'Your key is stored in this site\'s database and only sent to the provider you choose. Leave blank to use only the built-in filename/context-based suggestions.', 'content-health-seo' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
