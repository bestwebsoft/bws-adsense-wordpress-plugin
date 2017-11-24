( function( $ ){
	$(document).ready( function() {
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

		$( '.adsns-list-table tbody .adsns_adunit_ids' ).each( function() {
			var $adsns_checkbox = $( this );
				$adsns_checkbox.trigger( 'availability' );
		}).on( 'change', function() {
			var $adsns_checkbox = $( this );
				$adsns_checkbox.trigger( 'availability' );
		}).on( 'availability', function() {
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
			if ( ! $( e.target ).is( 'input[type="checkbox"], select' ) ) {
				var $adsns_tr = $( this ),
					$adsns_checkbox = $adsns_tr.find( '.adsns_adunit_ids' );

				$adsns_checkbox.trigger( 'click' );
			}
		});

		$( '.adsns-list-table #cb-select-all-1, .adsns-list-table #cb-select-all-2' ).on( 'change', function() {
			$( '.adsns-list-table tbody .adsns_adunit_ids' ).trigger( 'availability' );
		});

		var $viModalSignUp = $( '#adsns_modal_signup' ),
			$viModalLogIn = $( '#adsns_modal_login' ),
			$viModalStory = $( '#adsns_modal_new_story' );

		/* vi Log In modal */
		$viModalSignUp.adsns_modal({
			maxWidth: '850px',
			onClose: function( $this ) {
				$viSignUpIframe = $this.find( '#adsns_vi_signup_iframe' );
				$viSignUpIframe.attr( 'src', $viSignUpIframe.attr( 'src' ) );
			}
		});

		/* vi Sign Up modal */
		$viModalLogIn.adsns_modal({
			maxWidth: '600px',
			onClose: function( $this ) {
				var	$viLogInForm = $this.find( '.adsns_vi_login_form' ),
					$viLogInError = $this.find( '.adsns_vi_login_error' );

				$viLogInError
					.removeClass( 'adsns_vi_login_error_visible' )
					.html( '' );
			}
		});

		/* vi Story modal */
		$viModalStory.adsns_modal({
			maxWidth: '1110px',
			onClose: function( $this ) {
				var	$viStoryForm = $this.find( '.adsns_vi_story_form' ),
					$viLogInError = $this.find( '.adsns_vi_story_error' );

				$viLogInError
					.removeClass( 'adsns_vi_story_error_visible' )
					.html( '' );

				$viStoryForm.find( '[data-field-id]' ).trigger( 'hideError' );

			}
		});

		/* On click sign up button */
		$( '#adsns_vi_widget_button_signup' ).on( 'click', function( e ) {
			e.preventDefault();
			$viModalSignUp.adsns_modal( 'open' );
		});

		/* On click log in button */
		$( '#adsns_vi_widget_button_login' ).on( 'click', function( e ) {
			e.preventDefault();
			$viModalLogIn.adsns_modal( 'open' );
		});

		/* Open vi story form */
		$( '#adsns_vi_story_new' ).on( 'click', function( e ) {
			e.preventDefault();
			$viModalStory.adsns_modal( 'open' );
		});

		/* Close vi story form */
		$( '#adsns_vi_story_cancel' ).on( 'click', function( e ) {
			e.preventDefault();
			$viModalStory.adsns_modal( 'close' );
		});

		/* On submit vi login form */
		$( '.adsns_vi_login_form' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var $viLogInForm = $( this ),
				$viLogInError = $viLogInForm.find( '.adsns_vi_login_error' ),
				$viLogInFormInput = $viLogInForm.find( '.adsns_dialog_login_input' ).filter( function() {
					return $( this ).val() == '';
				}),
				$viLogInBlocker = $viLogInForm.next( '.adsns_vi_login_blocker' ),
				formData = $viLogInForm.serialize();

			if ( $viLogInFormInput.length ) {
				$viLogInFormInput.first().focus();
			} else {
				$.ajax( {
					type		: 'POST',
					url			: ajaxurl,
					data		: formData + '&action=adsns_vi_login',
					dataType	: 'json',
					timeout		: 30000,
					beforeSend: function() {
						$viLogInError
							.removeClass( 'adsns_vi_login_error_visible' )
							.html( '' );

						$viModalLogIn
							.adsns_modal( 'pending', true )
							.adsns_modal( 'resize' );

						$viLogInBlocker.addClass( 'adsns_vi_login_blocker_visible' );
					},
					success: function( response ) {
						if ( response.status == 'error' ) {

							if ( response.error.description ) {
								$viLogInError
									.addClass( 'adsns_vi_login_error_visible' )
									.html( response.error.description );
							}

						} else {
							$viModalLogIn.adsns_modal( 'close', true );
							window.location.reload( true );
						}
					},
					complete: function() {
						$viModalLogIn
							.adsns_modal( 'pending', false )
							.adsns_modal( 'resize' );

						$viLogInBlocker.removeClass( 'adsns_vi_login_blocker_visible' );
					}
				} );
			}

			return false;
		}).on( 'change', '.adsns_dialog_login_input', function() {
			var $viLogInFormInput = $( this ),
				$viLogInForm = $viLogInFormInput.closest( '.adsns_vi_login_form' ),
				$viLogInError = $viLogInForm.find( '.adsns_vi_login_error' );

			$viLogInError.filter( '.adsns_vi_login_error_visible' ).removeClass( 'adsns_vi_login_error_visible' );
		});

		/* On submit vi story form */
		$( '.adsns_vi_story_form' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var $viStoryForm = $( this ),
				$viStoryError = $viStoryForm.find( '.adsns_vi_story_error' ),
				$viStoryBlocker = $viStoryForm.next( '.adsns_vi_story_blocker' ),
				formData = $viStoryForm.serialize();

				$.ajax( {
					type		: 'POST',
					url			: ajaxurl,
					data		: formData + '&action=adsns_vi_story_save',
					dataType	: 'json',
					timeout		: 30000,
					beforeSend: function() {
						$( '[data-field-id]' ).trigger( 'hideError' );

						$viStoryError
							.removeClass( 'adsns_vi_story_error_visible' )
							.html( '' );

						$viModalStory
							.adsns_modal( 'pending', true )
							.adsns_modal( 'resize' );

						$viStoryBlocker.addClass( 'adsns_vi_story_blocker_visible' );
					},
					success: function( response ) {
						if ( response.status == 'error' ) {
							if ( response.error.description ) {
								$viStoryError
									.addClass( 'adsns_vi_story_error_visible' )
									.html( response.error.description );
							}

							if ( response.data.hasOwnProperty( 'errors' ) && typeof( response.data.errors ) == 'object' ) {
								var errors = response.data.errors;
								for ( var field in errors ) {
									var $errorField = $( '.adsns_vi_story_field_error[data-error-id="' + field + '"]' ),
										errorText = errors[ field ];

									$errorField
										.html( errorText )
										.addClass('adsns_vi_story_field_error_visible');
								}
							}
						} else {
							$viModalStory.adsns_modal( 'close', true );
							window.location.reload( true );
						}
					},
					complete: function() {
						$viModalStory
							.adsns_modal( 'pending', false )
							.adsns_modal( 'resize' );

							$viStoryBlocker.removeClass( 'adsns_vi_story_blocker_visible' );
					}
				} );

			return false;
		});

		/* On change value in the vi story field that has an error */
		$( '[data-field-id]' ).on( 'hideError', function() {
			var field = $( this ).attr( 'data-field-id' ),
				$errorField = $( '.adsns_vi_story_field_error_visible[data-error-id="' + field + '"]' );

			$errorField
				.html( '' )
				.removeClass('adsns_vi_story_field_error_visible');

			if( $( '.adsns_vi_story_field_error_visible' ).length == 0 ) {
				$( '.adsns_vi_story_error' )
					.removeClass( 'adsns_vi_story_error_visible' )
					.html( '' );
			}

		}).on( 'input paste', function() {
			$( this ).trigger( 'hideError' );
		});

		/* On change vi story ad unit type */
		$( '#adsns_vi_story_ad_unit' ).on( 'change', function() {
			var value = $( this ).find( 'option:selected' ).val(),
				$symbol = $( '.vi_story_symbol_required' );

			$symbol.show();

			if ( value != 'NATIVE_VIDEO_UNIT' ) {
				$symbol.hide();
			}
		}).trigger( 'change' );

		/* vi story Add field Optional */
		$( '.adsns_vi_story_field_button_add' ).on( 'click', function() {
			var $button = $( this ),
				$table = $button.closest( '.adsns_vi_story_table_right' ),
				$row = $table.find( '.adsns_vi_story_table_row_optional.adsns_vi_story_table_row_optional_hidden' );

			$row.filter( ':first' ).removeClass( 'adsns_vi_story_table_row_optional_hidden' );
		} );

		/* vi story Remove field Optional */
		$( '.adsns_vi_story_field_button_remove' ).on( 'click', function() {
			var $button = $( this ),
				$row = $button.closest( '.adsns_vi_story_table_row_optional' );

			$row.find( 'textarea' ).val( '' );
			$row.addClass( 'adsns_vi_story_table_row_optional_hidden' );
		} );

		/* vi story IAB Category */
		$( '#adsns_vi_story_iab_category' ).on( 'change', function() {
			var $viSubCategory = $( this ),
				$viSubCategorySelected = $viSubCategory.find( 'option:selected' )
				viIabCategoryVal = $viSubCategorySelected.val(),
				$viIabSubCategory = $( '#adsns_vi_story_iab_subcategory' ),
				$viIabSubCategoryOptions = $viIabSubCategory.find( 'option' );

			if ( $viSubCategorySelected.index() == 0 ) {
				$viIabSubCategory.attr( 'disabled', true );
			} else {
				$viIabSubCategory.attr( 'disabled', false );
			}

			$viIabSubCategoryOptions.filter( ':hidden' ).show();
			$viIabSubCategoryOptions.not( ':eq(0)' ).filter( '[data-category!="' + viIabCategoryVal + '"]' ).hide();

			if ( $viIabSubCategoryOptions.filter( ':selected' ).css( 'display' ) == 'none' ) {
				$viIabSubCategoryOptions.eq( 0 ).attr( 'selected', true );
			}
		}).trigger( 'change' );

		/* vi story Color Picker */
		$( '#adsns_vi_story_text_color, #adsns_vi_story_background_color' ).minicolors({
			format: 'hex'
		});

		/* vi story optional 1, 2, 3 */
		$( '.adsns_vi_story_table_row_optional' ).each( function() {
			var $row = $( this ),
				value = $row.find( 'textarea' ).val();

			if ( value ) {
				$row.removeClass( 'adsns_vi_story_table_row_optional_hidden' );
			}
		});

		/* vi Widget chart */
		$( '#adsns_vi_revenue_chart_canvas' ).on( 'displayWidgetChart', function( e, chartData ) {
			var $adsns_chart = $( this ),
				$adsns_ctx = $adsns_chart.get( 0 ).getContext( '2d' ),
				adsns_chart_labels = chartData.labels || [],
				adsns_chart_data = chartData.data || [];

			new Chart( $adsns_ctx, {
				type: 'line',
				data: {
					labels: adsns_chart_labels,
					datasets: [ {
						label: '',
						data: adsns_chart_data,
						backgroundColor: [
							'rgba(190, 225, 241, 0.2)'
						],
						borderColor: [
							'rgba(112,168,194,1)'
						],
						borderWidth: 1,
					} ]
				},
				options: {
					legend: {
						display: false
					},
					scales: {
						xAxes: [ {
							ticks: {
								display: false
							},
							gridLines: {
								display: false,
								drawTicks: false,
							}
						} ],
						yAxes: [ {
							ticks: {
								display: false,
								beginAtZero: true
							},
							gridLines: {
								drawTicks: false,
								drawBorder: false,
								zeroLineWidth: 0
							}
						} ]
					},
					tooltips: {
						displayColors: false,
						callbacks: {
							label: function ( tooltipItem, data ) {
								return '$' + tooltipItem.yLabel.toFixed( 2 );
							},
						}
					}
				}
			} );

			$adsns_chart.addClass( 'adsns_vi_revenue_chart_canvas_loaded' );
		} );

		$( window ).on( 'resize', function() {
			var $window = $( this );

			$ ( '.adsns_vi_story_tooltip' ).toggleClass( 'adsns_vi_story_tooltip_mirrored', $window.width() <= 600 );
		} ).trigger( 'resize' );
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