jQuery( function( $ ) {

	/* ---------- Live char counters + SERP snippet preview (post edit screen) ---------- */

	function updateCounter( $field ) {
		var val = $field.val() || '';
		var min = parseInt( $field.data( 'min' ), 10 ) || 0;
		var max = parseInt( $field.data( 'max' ), 10 ) || 999;
		var len = val.length;

		var $counter = $( '.sch-char-count[data-target="' + $field.attr( 'id' ) + '"]' );
		var status = 'sch-good';
		if ( len === 0 ) {
			status = '';
		} else if ( len < min ) {
			status = 'sch-too-short';
		} else if ( len > max ) {
			status = 'sch-too-long';
		}

		$counter
			.removeClass( 'sch-too-short sch-too-long sch-good' )
			.addClass( status )
			.text( len + ' / ' + min + '-' + max + ' chars' );
	}

	$( '#sch_seo_title, #sch_meta_description' ).on( 'input', function () {
		updateCounter( $( this ) );

		if ( this.id === 'sch_seo_title' ) {
			$( '#sch_snippet_title' ).text( $( this ).val() || $( '#title' ).val() || '' );
		} else {
			$( '#sch_snippet_desc' ).text( $( this ).val() );
		}
	} ).each( function () {
		updateCounter( $( this ) );
	} );

	/* ---------- Alt text bulk manager ---------- */

	$( '.sch-suggest-btn' ).on( 'click', function () {
		var $row = $( this ).closest( 'tr' );
		var id = $row.data( 'id' );
		var $btn = $( this );
		var $status = $row.find( '.sch-row-status' );

		$btn.prop( 'disabled', true );
		$status.text( '…' );

		$.post( SCH_Data.ajaxUrl, {
			action: 'sch_suggest_alt_text',
			nonce: SCH_Data.nonce,
			attachment_id: id
		} ).done( function ( res ) {
			if ( res.success ) {
				$row.find( '.sch-alt-input' ).val( res.data.suggestion );
				$status.text( '' );
			} else {
				$status.text( 'error' );
			}
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '.sch-save-btn' ).on( 'click', function () {
		var $row = $( this ).closest( 'tr' );
		var id = $row.data( 'id' );
		var altText = $row.find( '.sch-alt-input' ).val();
		var $status = $row.find( '.sch-row-status' );
		var $btn = $( this );

		if ( ! altText ) {
			$status.text( 'Enter alt text first' );
			return;
		}

		$btn.prop( 'disabled', true );

		$.post( SCH_Data.ajaxUrl, {
			action: 'sch_save_alt_text',
			nonce: SCH_Data.nonce,
			attachment_id: id,
			alt_text: altText
		} ).done( function ( res ) {
			if ( res.success ) {
				$status.text( SCH_Data.i18n.saved );
				$row.fadeOut( 400, function () {
					$row.remove();
				} );
			} else {
				$status.text( 'error' );
			}
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	/* ---------- Bulk image optimizer ---------- */

	var $optimizeBtn = $( '#sch-run-bulk-optimize' );
	if ( $optimizeBtn.length ) {
		$optimizeBtn.on( 'click', function () {
			$optimizeBtn.prop( 'disabled', true ).text( SCH_Data.i18n.optimizing );
			runOptimizeBatch();
		} );
	}

	function runOptimizeBatch() {
		$.post( SCH_Data.ajaxUrl, {
			action: 'sch_optimize_attachment',
			nonce: SCH_Data.nonce
		} ).done( function ( res ) {
			if ( ! res.success ) {
				$( '#sch-optimize-progress' ).text( 'Error processing images.' );
				return;
			}

			var remaining = res.data.remaining;
			$( '#sch-optimize-progress' ).text( remaining + ' image(s) remaining…' );

			if ( remaining > 0 && res.data.processed > 0 ) {
				runOptimizeBatch();
			} else {
				$( '#sch-optimize-progress' ).text( SCH_Data.i18n.done );
				$optimizeBtn.text( SCH_Data.i18n.done );
			}
		} );
	}

} );
