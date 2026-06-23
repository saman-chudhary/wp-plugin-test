<?php
/**
 * Handles the SEO Title + Meta Description meta box, saving, and
 * frontend <head> output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCH_Meta_Fields {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ) );
	}

	private function get_post_types() {
		$opts = sch_get_options();
		return $opts['post_types'];
	}

	public function add_meta_box() {
		foreach ( $this->get_post_types() as $post_type ) {
			add_meta_box(
				'sch_seo_meta_box',
				__( 'Content Health: SEO', 'content-health-seo' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'sch_save_meta_box', 'sch_meta_box_nonce' );

		$seo_title = get_post_meta( $post->ID, '_sch_seo_title', true );
		$meta_desc = get_post_meta( $post->ID, '_sch_meta_description', true );
		$opts      = sch_get_options();
		$score     = (int) get_post_meta( $post->ID, '_sch_health_score', true );

		?>
		<div class="sch-metabox">
			<div class="sch-score-banner sch-score-<?php echo esc_attr( sch_score_band( $score ) ); ?>">
				<strong><?php esc_html_e( 'Content Health Score:', 'content-health-seo' ); ?></strong>
				<span class="sch-score-number"><?php echo esc_html( $score ); ?>/100</span>
				<span class="sch-score-note"><?php esc_html_e( '(Recalculated automatically when you update/publish.)', 'content-health-seo' ); ?></span>
			</div>

			<p>
				<label for="sch_seo_title"><strong><?php esc_html_e( 'SEO Title', 'content-health-seo' ); ?></strong></label><br>
				<input type="text" id="sch_seo_title" name="sch_seo_title" class="widefat"
					value="<?php echo esc_attr( $seo_title ); ?>"
					maxlength="160"
					data-min="<?php echo esc_attr( $opts['title_min'] ); ?>"
					data-max="<?php echo esc_attr( $opts['title_max'] ); ?>" />
				<span class="sch-char-count" data-target="sch_seo_title"></span>
			</p>

			<p>
				<label for="sch_meta_description"><strong><?php esc_html_e( 'Meta Description', 'content-health-seo' ); ?></strong></label><br>
				<textarea id="sch_meta_description" name="sch_meta_description" class="widefat" rows="3"
					maxlength="320"
					data-min="<?php echo esc_attr( $opts['desc_min'] ); ?>"
					data-max="<?php echo esc_attr( $opts['desc_max'] ); ?>"><?php echo esc_textarea( $meta_desc ); ?></textarea>
				<span class="sch-char-count" data-target="sch_meta_description"></span>
			</p>

			<div class="sch-snippet-preview">
				<div class="sch-snippet-title" id="sch_snippet_title"><?php echo esc_html( $seo_title ? $seo_title : get_the_title( $post ) ); ?></div>
				<div class="sch-snippet-url"><?php echo esc_html( home_url( '/' ) ); ?></div>
				<div class="sch-snippet-desc" id="sch_snippet_desc"><?php echo esc_html( $meta_desc ? $meta_desc : wp_trim_words( $post->post_content, 25 ) ); ?></div>
			</div>
		</div>
		<?php
	}

	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['sch_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['sch_meta_box_nonce'], 'sch_save_meta_box' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['sch_seo_title'] ) ) {
			update_post_meta( $post_id, '_sch_seo_title', sanitize_text_field( wp_unslash( $_POST['sch_seo_title'] ) ) );
		}
		if ( isset( $_POST['sch_meta_description'] ) ) {
			update_post_meta( $post_id, '_sch_meta_description', sanitize_textarea_field( wp_unslash( $_POST['sch_meta_description'] ) ) );
		}
	}

	/**
	 * Override the document title if a custom SEO title is set.
	 */
	public function filter_document_title( $title ) {
		if ( ! is_singular() ) {
			return $title;
		}
		$custom = get_post_meta( get_the_ID(), '_sch_seo_title', true );
		return $custom ? $custom : $title;
	}

	/**
	 * Print the meta description tag (and a few useful OG tags) in <head>.
	 */
	public function output_meta_tags() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		$desc    = get_post_meta( $post_id, '_sch_meta_description', true );

		if ( ! $desc ) {
			$desc = wp_trim_words( get_the_excerpt( $post_id ), 30, '...' );
		}

		if ( $desc ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $desc ) );
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
		}

		$title = get_post_meta( $post_id, '_sch_seo_title', true );
		if ( $title ) {
			printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		}
	}
}

/**
 * Shared helper: convert a numeric score into a CSS-friendly band name.
 */
function sch_score_band( $score ) {
	if ( $score >= 80 ) {
		return 'good';
	} elseif ( $score >= 50 ) {
		return 'ok';
	}
	return 'poor';
}
