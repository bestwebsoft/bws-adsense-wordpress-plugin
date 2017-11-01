( function( $ ){
	$(document).ready( function() {
		/* Hide notice and unset option using AJAX */
		$( document ).on( 'click', '.adsns-coop-start-banner .notice-dismiss', function( e ) {
			e.preventDefault();
			var form = $( this ).closest( '.adsns-coop-start-banner' ).find( 'form' ),
				ajax_nonce = form.find( '#adsns_settings_nonce' ).val();
			$.ajax( {
				type	: 'POST',
				url		: ajaxurl,
				data	: {
					action							: 'adsns_hide_coop_start_banner',
					adsns_settings_nonce			: ajax_nonce
				},
				success: function( data ) {
					if ( '1' === data ) {
						form.closest('.adsns-banner').hide();
					}
				}
			} );
		} );
	} );
} )( jQuery );