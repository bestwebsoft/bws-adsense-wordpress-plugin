( function( $ ){
	$(document).ready( function() {

		var max_ads= {
			'home' : 3,
			'pages' : 3,
			'posts+custom_posts' : 3,
			'categories+tags' : 3,
			'widget' : 1,
		},

		$area = $( 'input[name="adsns_area"]' );

		$( '#adsns_include_inactive_id' ).on( 'click', function() {
			$( '.adsns_table_row_odd' ).removeClass( 'adsns_table_row_odd' );
			if ( $( this ).is( ':checked' ) ) {
				$( '.adsns_inactive' ).show();
				$( '.adsns-hidden-idle-notice' ).hide();
			} else {
				$( '.adsns_inactive' ).hide();
				if ( $( '.adsns_inactive input:checkbox:checked' ).length > 0 ) {
					$( '.adsns-hidden-idle-notice' ).show();
				}
			}
				$( '#adsns_tab_content .wp-list-table tbody tr:visible:even' ).addClass( 'adsns_table_row_odd' );
		} );

		$( '.wp-list-table.bws-plugins_page_adsense-plugin #the-list tr' ).each( function(e) {
			if ( ! $( this ).find( '.adsns_adunit_ids' ).is( ':checked' ) ) {
				$( this ).find( '.adsns_adunit_position' ).attr( 'disabled', true );
			}
		} );

		$( '.wp-list-table.bws-plugins_page_adsense-plugin #the-list tr' ).click( function(e) {
			var $row = $( this ),
				$cb =  $( this ).find( '.adsns_adunit_ids' );
			if ( ! $( e.target ).closest( '.adsns_adunit_ids, .adsns_adunit_position' ).length ) {
				$cb.trigger( 'click' );
			}
		} );

		if( max_ads[ $area.val() ] == $( '.adsns_adunit_ids' ).filter( ':checked' ).length ) {
			$( '.adsns_adunit_ids' ).filter( ':not(:checked)' ).attr( 'disabled', true );
		}

		$( '.adsns_adunit_ids' ).change( function(e) {
			var $cb = $( this ),
				$row = $cb.parents( 'tr' );
			if ( $( '.adsns_adunit_ids' ).filter( ':checked' ).length >= max_ads[ $area.val() ] ) {
				$( '.adsns_adunit_ids' ).filter( ':not(:checked)' ).attr( 'disabled', true );
			} else {
				$( '.adsns_adunit_ids' ).filter( ':not(:checked)' ).attr( 'disabled', false );
			}
			if ( $cb.is( ':checked' ) ) {
				$row.find( '.adsns_adunit_position' ).attr( 'disabled', false );
			} else {
				$row.find( '.adsns_adunit_position, .adsns_adunit_order' ).attr( 'disabled', true );
			}
		} );
	} );
} )( jQuery );