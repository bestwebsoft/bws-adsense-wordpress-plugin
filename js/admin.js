( function( $ ){
	$(document).ready( function() {

		$( '.adsns-list-table tbody .adsns_adunit_ids' ).each( function() {
			var $adsns_checkbox = $( this );
				$adsns_checkbox.trigger( 'availability' );
		} ).on( 'change', function() {
			var $adsns_checkbox = $( this );
				$adsns_checkbox.trigger( 'availability' );
		} ).on( 'availability', function() {
			var $adsns_checkbox = $( this ),
				$adsns_tr = $adsns_checkbox.closest( 'tr' ),
				$adsns_position = $adsns_tr.find( '.adsns_adunit_position' );

			if ( ! $adsns_checkbox.is( ':checked' ) ) {
				$adsns_position.attr( 'disabled', true );
			} else {
				$adsns_position.attr( 'disabled', false );
			}
		});

		$( '.adsns-list-table tbody tr' ).on( 'click', function( e ) {
			if ( ! $( e.target ).is( 'input[type="checkbox"], select, option' ) ) {
				var $adsns_tr = $( this ),
					$adsns_checkbox = $adsns_tr.find( '.adsns_adunit_ids' );

				$adsns_checkbox.trigger( 'click' );
			}
		});

		$( '.adsns-list-table #cb-select-all-1, .adsns-list-table #cb-select-all-2' ).on( 'change', function() {
			$( '.adsns-list-table tbody .adsns_adunit_ids' ).trigger( 'availability' );
		});


		var windowObjectReference = null;
		$( '#adsns_authorization_button' ).on( 'click', function(){
			windowObjectReference = window.open(
				$( this ).attr( 'href' ),'','top='+(screen.height/2-560/2)+',left='+(screen.width/2-640/2)+',width=640,height=560,resizable=0,scrollbars=0,menubar=0,toolbar=0,status=1,location=1'
			).focus();
			return false;
		});
	} );
} )( jQuery );

(function($) {
	$.fn.adsns_modal = function( method ) {
		var methods = {
			init : function( options ) {
				var params = $.extend( {
					width: '100%',
					maxWidth: '520px',
					onOpen: function() {},
					onClose: function() {}
				}, options );

				return this.each( function() {
					var $self = $( this ),
						$dialog = $self.find( '.adsns_modal_dialog' ),
						$close = $dialog.find( '.adsns_modal_dialog_close' ),
						$overlay;

					if ( ! $( '.adsns_modal_overlay' ).length ) {
						$overlay = $( '<div/>', {
							'class' : 'adsns_modal_overlay'
						} ).appendTo( 'body' );
					} else {
						$overlay = $( '.adsns_modal_overlay' );
					}

					$dialog
						.css( {
							'width'		: params.width,
							'max-width'	: params.maxWidth,
						} ).on( 'resize.dialog', function() {
							methods.resize.call( $self );
						} );

					$( window ).on( 'resize', function() {
						methods.resize.call( $self );
					} ).trigger( 'resize' );

					$close.on( 'click', function() {
						methods.close.call( $self );
					} );

					$self
						.on( 'click', function( e ) {
							if ( $( e.target ).is( '.adsns_modal_opened' ) ) {
								methods.close.call( $self );
							}
						} )
						.data( 'overlay', $overlay )
						.data( 'params', params )
						.insertBefore( $overlay );
				});
			},
			open : function() {
				return this.each( function() {
					var $self = $( this ),
						$overlay = $self.data( 'overlay' );

					methods.onOpen.call( $self );
					$('body').addClass( 'adsns_body_modal_opened' );
					$self.addClass( 'adsns_modal_opened' );
					$overlay.addClass( 'adsns_modal_overlay_visible' );
					methods.resize.call( $self );
				});
			},
			close : function( force ) {
				return this.each( function() {
					var $self = $( this ),
						$overlay = $self.data( 'overlay' );

					if ( ! $self.is( '.adsns_modal_doing_ajax' ) || force === true ) {
						methods.onClose.call( $self );
						$('body').removeClass( 'adsns_body_modal_opened' );
						$self.removeClass( 'adsns_modal_opened' );
						$overlay.removeClass( 'adsns_modal_overlay_visible' );
					}
				});
			},
			resize : function() {
				return this.each( function() {
					var $self = $( this ),
						$dialog = $self.find( '.adsns_modal_dialog' );

					if ( $dialog.is( ':hidden' ) ) {
						return;
					}

					var $window = $( window ),
						dialogWidth = $dialog.innerWidth(),
						dialogHeight = $dialog.innerHeight(),
						dialogTop = $window.height() > dialogHeight ? '50%' : '0px';
						dialogLeft = $window.width() > dialogWidth ? '50%' : '0px';
						dialogMarginTop = dialogTop == '50%' ? -1 * ( dialogHeight / 2 ) : '0px';
						dialogMarginLeft = dialogLeft == '50%' ? -1 * ( dialogWidth / 2 ) : '0px';

					$dialog.css( {
						'top'			: dialogTop,
						'left'			: dialogLeft,
						'margin-top'	: dialogMarginTop,
						'margin-left'	: dialogMarginLeft
					} );
				});
			},
			pending: function( status ) {
				return this.each( function() {
					var $self = $( this );

					if ( typeof( status ) != 'boolean' ) {
						return;
					}

					switch( status ) {
						case true:
							$self.addClass( 'adsns_modal_doing_ajax' );
							break;
						case false:
							$self.removeClass( 'adsns_modal_doing_ajax' );
							break;
					}
				});
			},
			onOpen : function() {
				return this.each( function() {
					var $self = $( this ),
						params = $self.data( 'params' );

					if ( typeof params.onOpen == 'function' ) {
						params.onOpen( $self );
					}
				});
			},
			onClose : function() {
				return this.each( function() {
					var $self = $( this ),
						params = $self.data( 'params' );

					if ( typeof params.onClose == 'function' ) {
						params.onClose( $self );
					}
				});
			}
		}

		if ( methods[method] ) {
			return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
		} else if ( typeof method === 'object' || ! method ) {
			return methods.init.apply( this, arguments );
		} else {
			$.error( 'There is no method with name ' +  method );
		}
	}
} )( jQuery );
