<?php
/**
 * Image optimization: compresses originals on upload, generates a WebP
 * copy, tracks before/after size in postmeta, and (optionally) serves
 * the WebP version on the frontend to supporting browsers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCH_Image_Optimizer {

	public function __construct() {
		add_filter( 'wp_handle_upload', array( $this, 'on_upload' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'wp_ajax_sch_optimize_attachment', array( $this, 'ajax_optimize_attachment' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_lazy_loading' ), 10, 1 );
		add_filter( 'the_content', array( $this, 'maybe_swap_to_webp' ), 20 );
	}

	public function add_admin_page() {
		add_submenu_page(
			'sch_dashboard',
			__( 'Image Optimizer', 'content-health-seo' ),
			__( 'Image Optimizer', 'content-health-seo' ),
			'upload_files',
			'sch_image_optimizer',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Hook fired right after a file is uploaded to the media library.
	 */
	public function on_upload( $upload ) {
		$opts = sch_get_options();
		if ( empty( $opts['auto_optimize_uploads'] ) ) {
			return $upload;
		}
		if ( empty( $upload['type'] ) || strpos( $upload['type'], 'image/' ) !== 0 ) {
			return $upload;
		}
		if ( 'image/svg+xml' === $upload['type'] || 'image/gif' === $upload['type'] ) {
			return $upload; // leave SVG/GIF untouched
		}

		// Optimization runs after the attachment post is created, so just
		// remember the file path; actual processing happens on add_attachment.
		add_action( 'add_attachment', function( $attachment_id ) use ( $upload ) {
			$this->optimize_attachment( $attachment_id );
		} );

		return $upload;
	}

	/**
	 * Compress the original and generate a sibling .webp file.
	 * Returns an array of stats, or false on failure.
	 */
	public function optimize_attachment( $attachment_id ) {
		$opts = sch_get_options();
		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return false;
		}

		$original_size = filesize( $file );

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return false;
		}

		// Re-save the original at a sane quality to shrink it.
		if ( 'image/jpeg' === $mime ) {
			$editor->set_quality( (int) $opts['jpeg_quality'] );
		}
		$saved = $editor->save( $file );

		// Generate a WebP sibling next to the original.
		$webp_path = preg_replace( '/\.[^.]+$/', '.webp', $file );
		$webp_editor = wp_get_image_editor( $file );
		$webp_saved  = false;
		if ( ! is_wp_error( $webp_editor ) && method_exists( $webp_editor, 'set_quality' ) ) {
			$webp_editor->set_quality( (int) $opts['webp_quality'] );
			$result = $webp_editor->save( $webp_path, 'image/webp' );
			$webp_saved = ! is_wp_error( $result );
		}

		clearstatcache();
		$optimized_size = file_exists( $file ) ? filesize( $file ) : $original_size;

		update_post_meta( $attachment_id, '_sch_optimized', 1 );
		update_post_meta( $attachment_id, '_sch_original_size', $original_size );
		update_post_meta( $attachment_id, '_sch_optimized_size', $optimized_size );
		update_post_meta( $attachment_id, '_sch_webp_path', $webp_saved ? $webp_path : '' );

		return array(
			'original_size'  => $original_size,
			'optimized_size' => $optimized_size,
			'webp'           => $webp_saved,
		);
	}

	public static function count_unoptimized_images() {
		global $wpdb;
		$sql = "
			SELECT COUNT(*)
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = '_sch_optimized'
			WHERE p.post_type = 'attachment'
			AND (p.post_mime_type = 'image/jpeg' OR p.post_mime_type = 'image/png')
			AND ( pm.meta_value IS NULL OR pm.meta_value != '1' )
		";
		return (int) $wpdb->get_var( $sql );
	}

	public static function get_unoptimized_images( $limit = 50 ) {
		global $wpdb;
		$sql = "
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = '_sch_optimized'
			WHERE p.post_type = 'attachment'
			AND (p.post_mime_type = 'image/jpeg' OR p.post_mime_type = 'image/png')
			AND ( pm.meta_value IS NULL OR pm.meta_value != '1' )
			ORDER BY p.post_date DESC
			LIMIT %d
		";
		return $wpdb->get_col( $wpdb->prepare( $sql, $limit ) ); // phpcs:ignore
	}

	public function render_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$remaining = self::count_unoptimized_images();
		?>
		<div class="wrap sch-wrap">
			<h1><?php esc_html_e( 'Image Optimizer', 'content-health-seo' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %d: number of images */
					esc_html__( '%d image(s) have not been optimized yet.', 'content-health-seo' ),
					(int) $remaining
				);
				?>
			</p>
			<button id="sch-run-bulk-optimize" class="button button-primary" data-remaining="<?php echo esc_attr( $remaining ); ?>">
				<?php esc_html_e( 'Optimize All Images', 'content-health-seo' ); ?>
			</button>
			<div id="sch-optimize-progress" style="margin-top:15px;"></div>
		</div>
		<?php
	}

	public function ajax_optimize_attachment() {
		check_ajax_referer( 'sch_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'forbidden' );
		}

		$ids = self::get_unoptimized_images( 5 );
		$results = array();

		foreach ( $ids as $id ) {
			$res = $this->optimize_attachment( $id );
			if ( $res ) {
				$results[] = array_merge( array( 'id' => $id ), $res );
				$parent = wp_get_post_parent_id( $id );
				if ( $parent ) {
					SCH_Health_Score::recalculate( $parent );
				}
			}
		}

		wp_send_json_success( array(
			'processed'  => count( $results ),
			'remaining'  => self::count_unoptimized_images(),
			'results'    => $results,
		) );
	}

	/**
	 * Add native lazy-loading/async decoding (small, free performance win).
	 */
	public function add_lazy_loading( $attr ) {
		if ( empty( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	/**
	 * If the visiting browser accepts WebP and a WebP sibling exists for an
	 * image in the_content, swap the <img src> to the WebP version.
	 */
	public function maybe_swap_to_webp( $content ) {
		$opts = sch_get_options();
		if ( empty( $opts['serve_webp_frontend'] ) ) {
			return $content;
		}
		if ( empty( $_SERVER['HTTP_ACCEPT'] ) || false === strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) ) {
			return $content;
		}
		if ( false === strpos( $content, '<img' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<img[^>]+src=["\']([^"\']+\.(?:jpe?g|png))["\'][^>]*>/i',
			function( $matches ) {
				$src        = $matches[1];
				$webp_guess = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $src );
				$path_guess = str_replace( content_url(), WP_CONTENT_DIR, $webp_guess );

				if ( file_exists( $path_guess ) ) {
					return str_replace( $src, $webp_guess, $matches[0] );
				}
				return $matches[0];
			},
			$content
		);
	}
}
