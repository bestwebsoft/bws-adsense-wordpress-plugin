( function( $ ){
	$(document).ready( function() {
		/* Hide notice and unset option using AJAX */
		$( document ).on( 'click', '.adsns-banner-vi-wellcome .notice-dismiss', function( e ) {
			e.preventDefault();
			var $form = $( this ).parent( '.adsns-banner-form' ),
				ajax_nonce = $form.find( '#adsns_settings_nonce' ).val();

			$.ajax( {
				type	: 'POST',
				url		: ajaxurl,
				data	: {
					action					: 'adsns_hide_banner_vi_wellcome',
					adsns_settings_nonce	: ajax_nonce
				},
				success: function( data ) {
					if ( '1' === data ) {
						$form.closest( '.adsns-banner' ).hide()
					}
				}
			} );
		} );
	} );
} )( jQuery );