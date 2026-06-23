<?php
/**
 * The differentiator: a single 0-100 "Content Health Score" per post,
 * combining SEO field completeness AND image alt-text/optimization
 * status — instead of scoring those two things separately.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCH_Health_Score {

	public function __construct() {
		add_action( 'save_post', array( __CLASS__, 'recalculate' ), 30 );

		foreach ( sch_get_options()['post_types'] as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}

		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Core scoring logic. Returns int 0-100 and stores a breakdown.
	 */
	public static function recalculate( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, sch_get_options()['post_types'], true ) ) {
			return;
		}

		$opts = sch_get_options();

		$breakdown = array(
			'title'        => 0, // 25 pts
			'description'  => 0, // 25 pts
			'alt_text'     => 0, // 25 pts
			'image_optim'  => 0, // 25 pts
		);

		// --- SEO Title (25 pts) ---
		$title = get_post_meta( $post_id, '_sch_seo_title', true );
		if ( $title ) {
			$len = strlen( $title );
			if ( $len >= $opts['title_min'] && $len <= $opts['title_max'] ) {
				$breakdown['title'] = 25;
			} else {
				$breakdown['title'] = 12; // present but not ideal length
			}
		}

		// --- Meta Description (25 pts) ---
		$desc = get_post_meta( $post_id, '_sch_meta_description', true );
		if ( $desc ) {
			$len = strlen( $desc );
			if ( $len >= $opts['desc_min'] && $len <= $opts['desc_max'] ) {
				$breakdown['description'] = 25;
			} else {
				$breakdown['description'] = 12;
			}
		}

		// --- Images: alt text + optimization (25 + 25 pts) ---
		$image_ids = self::get_content_image_ids( $post );

		if ( empty( $image_ids ) ) {
			// No images = neutral, don't punish text-only posts.
			$breakdown['alt_text']    = 25;
			$breakdown['image_optim'] = 25;
		} else {
			$with_alt      = 0;
			$optimized_cnt = 0;

			foreach ( $image_ids as $id ) {
				$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
				if ( $alt ) {
					$with_alt++;
				}
				if ( get_post_meta( $id, '_sch_optimized', true ) ) {
					$optimized_cnt++;
				}
			}

			$breakdown['alt_text']    = (int) round( ( $with_alt / count( $image_ids ) ) * 25 );
			$breakdown['image_optim'] = (int) round( ( $optimized_cnt / count( $image_ids ) ) * 25 );
		}

		$score = array_sum( $breakdown );

		update_post_meta( $post_id, '_sch_health_score', $score );
		update_post_meta( $post_id, '_sch_health_breakdown', $breakdown );

		return $score;
	}

	/**
	 * Collect attachment IDs for: the featured image + any images
	 * inserted into post_content via the media library.
	 */
	public static function get_content_image_ids( $post ) {
		$ids = array();

		$thumb_id = get_post_thumbnail_id( $post );
		if ( $thumb_id ) {
			$ids[] = $thumb_id;
		}

		if ( preg_match_all( '/wp-image-(\d+)/', $post->post_content, $matches ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $matches[1] ) );
		}

		return array_unique( $ids );
	}

	/**
	 * --- Post list column ---
	 */
	public function add_column( $columns ) {
		$columns['sch_score'] = __( 'Content Health', 'content-health-seo' );
		return $columns;
	}

	public function render_column( $column, $post_id ) {
		if ( 'sch_score' !== $column ) {
			return;
		}
		$score = (int) get_post_meta( $post_id, '_sch_health_score', true );
		$band  = sch_score_band( $score );
		printf(
			'<span class="sch-badge sch-badge-%s">%d</span>',
			esc_attr( $band ),
			esc_html( $score )
		);
	}

	/**
	 * --- Dashboard widget: site-wide rollup ---
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'sch_dashboard_widget',
			__( 'Content Health Overview', 'content-health-seo' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	public function render_dashboard_widget() {
		global $wpdb;

		$post_types = sch_get_options()['post_types'];
		$in_clause  = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		$sql = "
			SELECT pm.meta_value as score
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_sch_health_score'
			AND p.post_status = 'publish'
			AND p.post_type IN ($in_clause)
		"; // phpcs:ignore

		$scores = array_map( 'intval', $wpdb->get_col( $sql ) );

		$missing_alt   = SCH_Image_Alt::count_images_missing_alt();
		$unoptimized   = SCH_Image_Optimizer::count_unoptimized_images();

		$avg  = $scores ? round( array_sum( $scores ) / count( $scores ) ) : 0;
		$good = count( array_filter( $scores, fn( $s ) => $s >= 80 ) );
		$ok   = count( array_filter( $scores, fn( $s ) => $s >= 50 && $s < 80 ) );
		$poor = count( array_filter( $scores, fn( $s ) => $s < 50 ) );

		?>
		<div class="sch-dash-widget">
			<div class="sch-dash-score sch-score-<?php echo esc_attr( sch_score_band( $avg ) ); ?>">
				<?php echo esc_html( $avg ); ?>/100
				<span><?php esc_html_e( 'average across published content', 'content-health-seo' ); ?></span>
			</div>

			<ul class="sch-dash-bands">
				<li class="sch-good"><?php printf( esc_html__( '%d good', 'content-health-seo' ), (int) $good ); ?></li>
				<li class="sch-ok"><?php printf( esc_html__( '%d needs work', 'content-health-seo' ), (int) $ok ); ?></li>
				<li class="sch-poor"><?php printf( esc_html__( '%d poor', 'content-health-seo' ), (int) $poor ); ?></li>
			</ul>

			<ul class="sch-dash-issues">
				<li>
					<?php printf( esc_html__( '%d image(s) missing alt text', 'content-health-seo' ), (int) $missing_alt ); ?>
					— <a href="<?php echo esc_url( admin_url( 'admin.php?page=sch_alt_text' ) ); ?>"><?php esc_html_e( 'fix now', 'content-health-seo' ); ?></a>
				</li>
				<li>
					<?php printf( esc_html__( '%d image(s) not yet optimized', 'content-health-seo' ), (int) $unoptimized ); ?>
					— <a href="<?php echo esc_url( admin_url( 'admin.php?page=sch_image_optimizer' ) ); ?>"><?php esc_html_e( 'optimize now', 'content-health-seo' ); ?></a>
				</li>
			</ul>
		</div>
		<?php
	}
}
