<?php
/**
 * Image ALT text tools: a bulk "missing alt text" manager plus a
 * filename/context-based suggestion engine (with optional AI assist).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCH_Image_Alt {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'wp_ajax_sch_save_alt_text', array( $this, 'ajax_save_alt_text' ) );
		add_action( 'wp_ajax_sch_suggest_alt_text', array( $this, 'ajax_suggest_alt_text' ) );
	}

	public function add_admin_page() {
		add_submenu_page(
			'sch_dashboard',
			__( 'Image Alt Text', 'content-health-seo' ),
			__( 'Image Alt Text', 'content-health-seo' ),
			'upload_files',
			'sch_alt_text',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Get attachments (images) missing alt text.
	 */
	public static function get_images_missing_alt( $limit = 100 ) {
		global $wpdb;

		$sql = "
			SELECT p.ID, p.post_title
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND ( pm.meta_value IS NULL OR pm.meta_value = '' )
			ORDER BY p.post_date DESC
			LIMIT %d
		";

		return $wpdb->get_results( $wpdb->prepare( $sql, $limit ) ); // phpcs:ignore
	}

	public static function count_images_missing_alt() {
		global $wpdb;
		$sql = "
			SELECT COUNT(*)
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND ( pm.meta_value IS NULL OR pm.meta_value = '' )
		";
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Turn a filename into a readable, human-friendly guess at alt text.
	 * e.g. "blue-mountain_bike-2023.jpg" -> "Blue mountain bike 2023"
	 */
	public static function suggest_from_filename( $attachment_id ) {
		$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$name = basename( $file );
		$name = preg_replace( '/\.[^.]+$/', '', $name ); // strip extension
		$name = preg_replace( '/[-_]+/', ' ', $name );
		$name = preg_replace( '/\b(img|image|dsc|photo|screenshot)\b\d*/i', '', $name );
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );
		$name = ucfirst( strtolower( $name ) );

		if ( strlen( $name ) < 3 ) {
			$post  = get_post( $attachment_id );
			$title = $post ? get_the_title( $post->post_parent ) : '';
			$name  = $title ? sprintf( __( 'Image related to %s', 'content-health-seo' ), $title ) : __( 'Untitled image', 'content-health-seo' );
		}

		return $name;
	}

	public function render_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$images = self::get_images_missing_alt( 200 );
		?>
		<div class="wrap sch-wrap">
			<h1><?php esc_html_e( 'Image Alt Text Manager', 'content-health-seo' ); ?></h1>
			<p><?php esc_html_e( 'These images are missing alt text, which hurts both SEO and accessibility. Click "Suggest" for an automatic guess, edit if needed, then Save.', 'content-health-seo' ); ?></p>

			<?php if ( empty( $images ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Nice — every image has alt text.', 'content-health-seo' ); ?></p></div>
			<?php else : ?>
				<table class="widefat sch-alt-table">
					<thead>
						<tr>
							<th style="width:90px;"><?php esc_html_e( 'Preview', 'content-health-seo' ); ?></th>
							<th><?php esc_html_e( 'File', 'content-health-seo' ); ?></th>
							<th><?php esc_html_e( 'Alt Text', 'content-health-seo' ); ?></th>
							<th style="width:160px;"><?php esc_html_e( 'Actions', 'content-health-seo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $images as $img ) : ?>
							<tr data-id="<?php echo esc_attr( $img->ID ); ?>">
								<td><?php echo wp_get_attachment_image( $img->ID, array( 60, 60 ) ); ?></td>
								<td><?php echo esc_html( $img->post_title ); ?></td>
								<td><input type="text" class="widefat sch-alt-input" value="" placeholder="<?php esc_attr_e( 'No alt text yet…', 'content-health-seo' ); ?>" /></td>
								<td>
									<button class="button sch-suggest-btn"><?php esc_html_e( 'Suggest', 'content-health-seo' ); ?></button>
									<button class="button button-primary sch-save-btn"><?php esc_html_e( 'Save', 'content-health-seo' ); ?></button>
									<span class="sch-row-status"></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function ajax_suggest_alt_text() {
		check_ajax_referer( 'sch_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'forbidden' );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( 'missing_id' );
		}

		$opts = sch_get_options();
		$suggestion = '';

		// Try AI assist first if configured, otherwise fall back to filename heuristic.
		if ( 'anthropic' === $opts['ai_provider'] && ! empty( $opts['ai_api_key'] ) ) {
			$suggestion = SCH_AI_Assist::suggest_alt_text( $attachment_id );
		}

		if ( ! $suggestion ) {
			$suggestion = self::suggest_from_filename( $attachment_id );
		}

		wp_send_json_success( array( 'suggestion' => $suggestion ) );
	}

	public function ajax_save_alt_text() {
		check_ajax_referer( 'sch_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'forbidden' );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$alt_text      = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';

		if ( ! $attachment_id || '' === $alt_text ) {
			wp_send_json_error( 'invalid' );
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		// Recalculate health score for any post this image is attached to.
		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( $parent_id ) {
			SCH_Health_Score::recalculate( $parent_id );
		}

		wp_send_json_success();
	}
}
