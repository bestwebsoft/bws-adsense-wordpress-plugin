<?php

if ( ! class_exists( 'Adsns' ) ) {
	/* Class of Google AdSense functions */
	class Adsns {
		var $adsns_plugin_info, $adsns_options, $adsns_is_main_query;

		private $adsns_vi_settings_api = array();
		private $adsns_vi_settings_api_error = false;
		private $adsns_vi_token = NULL;
		private $adsns_vi_publisher_id = NULL;

		function adsns_show_ads() {
			if ( ! $this->adsns_options )
				$this->adsns_activate();

			if ( ! is_admin() ) {
				add_filter( 'the_content', array( $this, 'adsns_content' ) );
				add_filter( 'comment_id_fields', array( $this, 'adsns_comments' ) );
			}
		}

		/* Add 'BWS Plugins' menu at the left side in administer panel */
		function adsns_add_admin_menu() {
			bws_general_menu();
			$settings = add_submenu_page( 'bws_panel', __( 'Google AdSense Settings', 'adsense-plugin' ), 'Google AdSense', 'manage_options', "adsense-plugin.php", array( $this, 'adsns_settings_page' ) );
			add_action( 'load-' . $settings, array( $this, 'adsns_add_tabs' ) );
		}

		function adsns_plugin_init() {

			require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
			bws_include_init( 'adsense-plugin/adsense-plugin.php' );

			if ( empty( $this->adsns_plugin_info ) ) {
				if ( ! function_exists( 'get_plugin_data' ) )
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				$this->adsns_plugin_info = get_plugin_data( dirname( __FILE__ ) . '/adsense-plugin.php' );
			}

			/* Function check if plugin is compatible with current WP version */
			bws_wp_min_version_check( 'adsense-plugin/adsense-plugin.php', $this->adsns_plugin_info, '3.8' );

			/* Call register settings function */
			if ( ! is_admin() || ( isset( $_GET['page'] ) && 'adsense-plugin.php' == $_GET['page'] ) ) {
				$this->adsns_activate();
			}
		}

		/* Plugin localization */
		function adsns_localization() {
			/* Internationalization */
			load_plugin_textdomain( 'adsense-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		function adsns_plugin_admin_init() {
			global $bws_plugin_info;

			if ( isset( $_GET['page'] ) && "adsense-plugin.php" == $_GET['page'] ) {
				if ( ! session_id() ) {
					session_start();
				}
			}

			if ( empty( $bws_plugin_info ) )
				$bws_plugin_info = array( 'id' => '80', 'version' => $this->adsns_plugin_info["Version"] );
		}

		/* Creating a default options for showing ads. Starts on plugin activation. */
		function adsns_activate() {

			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$this->adsns_plugin_info = get_plugin_data( dirname( __FILE__ ) . '/adsense-plugin.php' );

			$seconds = (int) date( 's', strtotime( 'now' ) );

			$adsns_options_defaults = array(
				'plugin_option_version'		=> $this->adsns_plugin_info["Version"],
				'widget_title'				=> '',
				'publisher_id'				=> '',
				'include_inactive_ads'		=> 1,
				'display_settings_notice'	=> 1,
				'first_install'				=> strtotime( "now" ),
				'suggest_feature_banner'	=> 1,
				'vi_token'					=> '',
				'vi_publisher_id'			=> '',
				'vi_banner_color'			=> ( $seconds % 2 ) ? 'black' : 'white'
			);

			if ( ! get_option( 'adsns_settings' ) ) {
				add_option( 'adsns_settings', $adsns_options_defaults );
			}

			$this->adsns_options = get_option( 'adsns_settings' );

			/* Array merge incase this version has added new options */
			if ( ! isset( $this->adsns_options['plugin_option_version'] ) || $this->adsns_options['plugin_option_version'] != $this->adsns_plugin_info["Version"] ) {
				$adsns_options_defaults['display_settings_notice'] = 0;
				$this->adsns_options = array_merge( $adsns_options_defaults, $this->adsns_options );
				$this->adsns_options['plugin_option_version'] = $this->adsns_plugin_info["Version"];
				update_option( 'adsns_settings', $this->adsns_options );
			}

			$this->adsns_vi_init();
		}

		/* vi init */
		function adsns_vi_init() {
			$this->adsns_vi_get_settings_api();
			$this->adsns_vi_get_token();
			$this->adsns_vi_publisher_id = $this->adsns_options['vi_publisher_id'];
		}

		/* Google Asense API */
		function adsns_client() {
			require_once( dirname( __FILE__ ) . '/google_api/autoload.php' );
			$client = new Google_Client();
			$client->setClientId( '903234641369-4mm0lqt76r0rracrdn2on3qrk6c554aa.apps.googleusercontent.com' );
			$client->setClientSecret( 'Twlx072svotXexK5rvqC5bb-' );
			$client->setScopes( array( 'https://www.googleapis.com/auth/adsense' ) );
			$client->setRedirectUri( 'urn:ietf:wg:oauth:2.0:oob' );
			$client->setAccessType( 'offline' );
			$client->setDeveloperKey( 'AIzaSyBa4vT_9do8e7Yxv88EXle6546nFVGLHI8' );
			$client->setApplicationName( $this->adsns_plugin_info['Name'] );
			return $client;
		}

		/* Show ads on the home page / single page / post / custom post / categories page / tags page via Google AdSense API */
		function adsns_content( $content ) {
			global $adsns_count, $adsns_vi_count;

			$adsns_ads_vi_min_width = ( ! wp_is_mobile() ) ? 336 : 301;

			if ( $this->adsns_is_main_query && ! is_feed() && ( is_home() || is_front_page() || is_category() || is_tag() ) ) {
				$adsns_count = empty( $adsns_count ) ? 0 : $adsns_count;
				$adsns_vi_count = empty( $adsns_vi_count ) ? 0 : $adsns_vi_count;

				if ( is_home() || is_front_page() ) {
					$adsns_area = 'home';
				}

				if ( is_category() || is_tag() ) {
					$adsns_area = 'categories+tags';
				}

				if ( isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ][ $adsns_count ] ) ) {

					$adsns_ad_unit = $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ][ $adsns_count ];
					$adsns_ad_unit_id = $adsns_ad_unit['id'];
					$adsns_ad_unit_position = $adsns_ad_unit['position'];
					$adsns_ad_unit_code = htmlspecialchars_decode( $adsns_ad_unit['code'] );

					$adsns_count++;

					switch ( $adsns_ad_unit_position ) {
						case 'after':
							$adsns_ads = sprintf( '<div id="%s" class="ads ads_after">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
							$content = $content . $adsns_ads;
							break;
						case 'before':
							$adsns_ads = sprintf( '<div id="%s" class="ads ads_before">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
							$content = $adsns_ads . $content;
							break;
					}
				}

				if (
					! empty( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['jstag'] ) &&
					isset( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $adsns_area ] ) &&
					$this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $adsns_area ] === true &&
					$adsns_vi_count == 0
				) {

					$adsns_ads_vi = sprintf( '<div id="ads_vi" class="ads ads_before ads_vi ads_vi_before" style="min-width: %dpx;"><script type="text/javascript">%s</script></div>', $adsns_ads_vi_min_width, $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['jstag'] );
					$content = $adsns_ads_vi . $content;
					$adsns_vi_count++;
				}

				return $content;
			}

			if ( $this->adsns_is_main_query && ! is_feed() && ( is_single() || is_page() ) ) {
				if ( is_single() ) {
					$adsns_area = 'posts+custom_posts';
				}

				if ( is_page() ) {
					$adsns_area = 'pages';
				}

				if ( isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ] ) ) {
					$adsns_ad_units = $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ];
					for ( $i = 0; $i < count( $adsns_ad_units ); $i++ ) {
						if ( isset( $adsns_ad_units[ $i ] ) ) {
							$adsns_ad_unit = $adsns_ad_units[ $i ];
							$adsns_ad_unit_id = $adsns_ad_unit['id'];
							$adsns_ad_unit_position = $adsns_ad_unit['position'];
							$adsns_ad_unit_code = htmlspecialchars_decode( $adsns_ad_unit['code'] );
							$adsns_count++;
							switch ( $adsns_ad_unit_position ) {
								case 'after':
									$adsns_ads = sprintf( '<div id="%s" class="ads ads_after">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
									$content = $content . $adsns_ads;
									break;
								case 'before':
									$adsns_ads = sprintf( '<div id="%s" class="ads ads_before">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
									$content = $adsns_ads . $content;
									break;
								default:
									break;
							}
						}
					}
				}

				if ( ! empty( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['jstag'] ) && isset( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $adsns_area ] ) && $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $adsns_area ] === true ) {

					$adsns_ads_vi = sprintf( '<div id="ads_vi" class="ads ads_before ads_vi ads_vi_before" style="min-width: %dpx;"><script type="text/javascript">%s</script></div>', $adsns_ads_vi_min_width, $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['jstag'] );
					$content = $adsns_ads_vi . $content;
				}
			}

			return $content;
		}

		/* Show ads after comment form via Google AdSense API */
		function adsns_comments( $content ) {
			$adsns_area = '';
			if ( is_single() ) {
				$adsns_area = 'posts+custom_posts';
			}

			if ( is_page() ) {
				$adsns_area = 'pages';
			}
			if ( isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ] ) ) {
				$adsns_ad_units = $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ];
				for ( $i = 0; $i < count( $adsns_ad_units ); $i++ ) {
					if ( isset( $adsns_ad_units[ $i ] ) ) {
						$adsns_ad_unit = $adsns_ad_units[ $i ];
						$adsns_ad_unit_id = $adsns_ad_unit['id'];
						$adsns_ad_unit_position = $adsns_ad_unit['position'];
						$adsns_ad_unit_code = htmlspecialchars_decode( $adsns_ad_unit['code'] );
						if ( $adsns_ad_unit_position == 'commentform' ) {
							$content .= sprintf( '<div id="%s" class="ads ads_comments">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
						}
					}
				}
			}
			return $content;
		}

		/* Main settings page */
		function adsns_settings_page() {
			global $wp_version;

			$plugin_basename = plugin_basename( __FILE__ );

			if (
				! isset( $_GET['action'] ) ||
				(
					isset( $_GET['action'] ) &&
					in_array( $_GET['action'], array( 'vi_login', 'vi_signup', 'vi_story' ) )
				)
			) {
				$adsns_table_data = array();
				$vi_story_save_result = array();

				if ( isset( $_POST['adsns_vi_logout'] ) ) {
					$this->adsns_vi_logout();
				}

				$vi_revenue = $this->adsns_vi_get_revenue();

				if ( isset( $_POST['adsns_vi_story_submit'] ) ) {
					$vi_story_save_result = $this->adsns_vi_story_save();
				}

				if ( ! empty( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['jstag'] ) && ! ( isset( $_GET['tab'] ) && $_GET['tab'] == 'widget' ) ) {
					$adsns_table_data[ 'vi_story' ] = array(
						'id'      => 'vi_story',
						'name'    => 'vi story',
						'code'    => '-',
						'summary' => '-',
						'status'  => '-',
						'status_value' => '-'
					);
				}

				if ( isset( $_POST['adsns_upgrade'] ) && check_admin_referer( plugin_basename( __FILE__ ), 'adsns_nonce_name' ) ) {
					$adsns_new_options['plugin_option_version'] = $this->adsns_options['plugin_option_version'];
					$adsns_new_options['widget_title'] = $this->adsns_options['widget_title'];
					$this->adsns_options = $adsns_new_options;
					update_option( 'adsns_settings', $this->adsns_options );
				}

				$adsns_current_tab = ( isset( $_GET['tab'] ) ) ? urlencode( $_GET['tab'] ) : 'home';

				$adsns_form_action = $adsns_tab_url = '';

				if ( isset( $_GET ) ) {
					unset( $_GET['page'] );
					foreach ( $_GET as $action => $value ) {
						$adsns_form_action .= sprintf( '&%s=%s', $action, urlencode( $value ) );
					}
					$adsns_tab_url = preg_replace( '/&tab=[\w\d+]+/', '', $adsns_form_action );
				}

				$adsns_tabs = array(
					'home' => array(
						'tab' => array(
							'title' => __( 'Home page', 'adsense-plugin' ),
							'url'   => sprintf( 'admin.php?page=adsense-plugin.php%s', $adsns_tab_url )
						),
						'adunit_positions' => array(
							'before'      => __( 'Before the content', 'adsense-plugin' ),
							'after'       => __( 'After the content', 'adsense-plugin' )
						),
						'adunit_positions_pro' => array(
							'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'adsense-plugin' ),
							'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'adsense-plugin' )
						)
					),
					'pages' => array(
						'tab' => array(
							'title' => __( 'Pages', 'adsense-plugin' ),
							'url'   => sprintf( 'admin.php?page=adsense-plugin.php&tab=pages%s', $adsns_tab_url )
						),
						'adunit_positions' => array(
							'before'      => __( 'Before the content', 'adsense-plugin' ),
							'after'       => __( 'After the content', 'adsense-plugin' ),
							'commentform' => __( 'Below the comment form', 'adsense-plugin' )
						),
						'adunit_positions_pro' => array(
							'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'adsense-plugin' ),
							'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'adsense-plugin' )
						)
					),
					'posts+custom_posts' => array(
						'tab' => array(
							'title' => __( 'Posts / Custom posts', 'adsense-plugin' ),
							'url'   => sprintf( 'admin.php?page=adsense-plugin.php&tab=posts+custom_posts%s', $adsns_tab_url )
						),
						'adunit_positions' => array(
							'before'      => __( 'Before the content', 'adsense-plugin' ),
							'after'       => __( 'After the content', 'adsense-plugin' ),
							'commentform' => __( 'Below the comment form', 'adsense-plugin' )
						),
						'adunit_positions_pro' => array(
							'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'adsense-plugin' ),
							'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'adsense-plugin' )
						)
					),
					'categories+tags' => array(
						'tab' => array(
							'title' => __( 'Categories / Tags', 'adsense-plugin' ),
							'url'   => sprintf( 'admin.php?page=adsense-plugin.php&tab=categories+tags%s', $adsns_tab_url )
						),
						'adunit_positions' => array(
							'before'      => __( 'Before the content', 'adsense-plugin' ),
							'after'       => __( 'After the content', 'adsense-plugin' )
						),
						'adunit_positions_pro' => array(
							'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'adsense-plugin' ),
							'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'adsense-plugin' )
						)
					),
					'search' => array(
						'tab' => array(
							'title' => __( 'Search results', 'adsense-plugin' ),
							'url'   => sprintf( 'admin.php?page=adsense-plugin.php&tab=search%s', $adsns_tab_url )
						),
						'adunit_positions' => array(
							'before'           => __( 'Before the content', 'adsense-plugin' ),
							'after'            => __( 'After the content', 'adsense-plugin' )
						),
						'adunit_positions_pro' => array(
							'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'adsense-plugin' ),
							'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'adsense-plugin' )
						)
					),
					'widget' => array(
						'tab' => array(
							'title' => __( 'Widget', 'adsense-plugin' ),
							'url'   => sprintf( 'admin.php?page=adsense-plugin.php&tab=widget%s', $adsns_tab_url )
						),
						'adunit_positions' => array(
							'static'       => __( 'Static', 'adsense-plugin' )
						),
						'adunit_positions_pro' => array(
							'fixed'    => __( 'Fixed (Available in Pro)', 'adsense-plugin' ),
						),
						'max_ads' => 1
					)
				);

				$adsns_adunit_types = array(
					'TEXT'       => __( 'Text', 'adsense-plugin' ),
					'IMAGE'      => __( 'Image', 'adsense-plugin' ),
					'TEXT_IMAGE' => __( 'Text/Image', 'adsense-plugin' ),
					'LINK'       => __( 'Link', 'adsense-plugin' )
				);

				$adsns_adunit_statuses = array(
					'NEW'      => __( 'New', 'adsense-plugin' ),
					'ACTIVE'   => __( 'Active', 'adsense-plugin' ),
					'INACTIVE' => __( 'Idle', 'adsense-plugin' )
				);

				$adsns_adunit_sizes = array(
					'RESPONSIVE' => __( 'Responsive', 'adsense-plugin' )
				);

				$adsns_client = $this->adsns_client();
				$adsns_authorize = false;

				if ( isset( $_POST['adsns_logout'] ) && check_admin_referer( plugin_basename( __FILE__ ), 'adsns_nonce_name' ) ) {
					unset( $this->adsns_options['authorization_code'] );
					update_option( 'adsns_settings', $this->adsns_options );
				}

				if ( isset( $_POST['adsns_authorization_code'] ) && ! empty( $_POST['adsns_authorization_code'] ) && check_admin_referer( plugin_basename(__FILE__), 'adsns_nonce_name' ) ) {
					try {
						$adsns_client->authenticate( $_POST['adsns_authorization_code'] );
						$this->adsns_options['authorization_code'] = $adsns_client->getAccessToken();
						update_option( 'adsns_settings', $this->adsns_options );
						$adsns_authorize = true;
					} catch ( Exception $e ) {}
				}

				if ( isset( $this->adsns_options['authorization_code'] ) ) {
					$adsns_client->setAccessToken( $this->adsns_options['authorization_code'] );
				}

				if ( $adsns_client->getAccessToken() ) {
					$adsns_adsense = new Google_Service_AdSense( $adsns_client );
					$adsns_adsense_accounts = $adsns_adsense->accounts;
					$adsns_adsense_adclients = $adsns_adsense->adclients;
					$adsns_adsense_adunits = $adsns_adsense->adunits;
					try {
						$adsns_list_accounts = $adsns_adsense_accounts->listAccounts()->getItems();
						$adsns_publisher_id = $adsns_list_accounts[0]['id'];
						$this->adsns_options['publisher_id'] = $adsns_publisher_id;

						if ( $adsns_authorize ) {
							$this->adsns_vi_create_ads_file( 'google', $this->adsns_vi_get_google_ads_file_content() );
						}
						/* Start fix old options */
						if ( isset( $this->adsns_options['adunits'] ) && ! isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ] ) ) {
							$adsns_temp_adunits = $this->adsns_options['adunits'];
							unset( $this->adsns_options['adunits'] );
							$this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ] = $adsns_temp_adunits;
						}
						/* End fix old options */
						update_option( 'adsns_settings', $this->adsns_options );
						try {
							$adsns_list_adclients = $adsns_adsense_adclients->listAdclients()->getItems();
							$adsns_ad_client = NULL;
							foreach ( $adsns_list_adclients as $adsns_list_adclient ) {
								if ( $adsns_list_adclient['productCode'] == 'AFC' ) {
									$adsns_ad_client = $adsns_list_adclient['id'];
								}
							}
							if ( $adsns_ad_client ) {
								try {
									$adsns_adunits = $adsns_adsense_adunits->listAdunits( $adsns_ad_client )->getItems();
									foreach ( $adsns_adunits as $adsns_adunit ) {
										$adsns_adunit_type = $adsns_adunit_types[ $adsns_adunit->getContentAdsSettings()->getType() ];
										$adsns_adunit_size = preg_replace( '/SIZE_([\d]+)_([\d]+)/', '$1x$2', $adsns_adunit->getContentAdsSettings()->getSize() );
										if ( array_key_exists( $adsns_adunit_size, $adsns_adunit_sizes ) ) {
											$adsns_adunit_size = $adsns_adunit_sizes[ $adsns_adunit_size ];
										}
										$adsns_adunit_status = $adsns_adunit->getStatus();
										if ( array_key_exists( $adsns_adunit_status, $adsns_adunit_statuses ) ) {
											$adsns_adunit_status = $adsns_adunit_statuses[ $adsns_adunit_status ];
										}
										$adsns_table_data[ $adsns_adunit->getName() ] = array(
											'id'      => $adsns_adunit->getId(),
											'name'    => $adsns_adunit->getName(),
											'code'    => $adsns_adunit->getCode(),
											'summary' => sprintf( '%s, %s', $adsns_adunit_type, $adsns_adunit_size ),
											'status'  => $adsns_adunit_status,
											'status_value' => $adsns_adunit['status']
										);
									}
								} catch ( Google_Service_Exception $e ) {
									$adsns_err = $e->getErrors();
									$adsns_api_notice = array(
										'class'    => 'error adsns_api_notice below-h2',
										'message'  => sprintf( '<strong>%s</strong> %s %s',
														__( 'AdUnits Error:', 'adsense-plugin' ),
														$adsns_err[0]['message'],
														sprintf( __( 'Create account in %s', 'adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
													)
									);
								}
							}
						} catch ( Google_Service_Exception $e ) {
							$adsns_err = $e->getErrors();
							$adsns_api_notice = array(
								'class'    => 'error adsns_api_notice below-h2',
								'message'  => sprintf( '<strong>%s</strong> %s %s',
												__( 'AdClient Error:', 'adsense-plugin' ),
												$adsns_err[0]['message'],
												sprintf( __( 'Create account in %s', 'adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
											)
							);
						}
					} catch ( Google_Service_Exception $e ) {
						$adsns_err = $e->getErrors();
						$adsns_api_notice = array(
							'class'    => 'error adsns_api_notice below-h2',
							'message'  => sprintf( '<strong>%s</strong> %s %s',
											__( 'Account Error:', 'adsense-plugin' ),
											$adsns_err[0]['message'],
											sprintf( __( 'Create account in %s', 'adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
										)
						);
					} catch ( Exception $e ) {
						$adsns_api_notice = array(
							'class'   => 'error adsns_api_notice below-h2',
							'message' => $e->getMessage()
						);
					}
				}

				if ( isset( $_POST['adsns_authorization_code'] ) && isset( $_POST['adsns_authorize'] ) && ! $adsns_client->getAccessToken() && check_admin_referer( plugin_basename( __FILE__ ), 'adsns_nonce_name' ) ) {
					$adsns_api_notice = array(
						'class'   => 'error adsns_api_notice below-h2',
						'message' => __( 'Invalid authorization code. Please, try again.', 'adsense-plugin' )
					);
				}

				if ( isset( $_POST['adsns_save_settings'] ) && check_admin_referer( plugin_basename( __FILE__ ), 'adsns_nonce_name' ) ) {
					$adsns_old_options = $this->adsns_options;
					$adsns_area = isset( $_POST['adsns_area'] ) ? $_POST['adsns_area'] : '';

					if ( array_key_exists( $adsns_area, $adsns_tabs ) ) {

						$adsns_save_settings = true;
						$this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $adsns_area ] = ( isset( $_POST['adsns_vi_id'] ) ) ? true : false;


						if ( isset( $_POST['adsns_include_inactive_id'] ) ) {
							$this->adsns_options['include_inactive_ads'] = ( $_POST['adsns_include_inactive_id'] == 1 ) ? 1 : 0;
						}

						if ( isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ] ) ) {
							$this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ] = array();
						}

						if ( isset( $_POST['adsns_adunit_ids'] ) ) {
							$adsns_max_ads = isset( $adsns_tabs[ $adsns_area ]['max_ads'] ) ? $adsns_tabs[ $adsns_area ]['max_ads'] : NULL;
							$adsns_posted_adunit_ids = isset( $_POST['adsns_adunit_ids'] ) ? $_POST['adsns_adunit_ids'] : array();

							if ( $adsns_max_ads ) {
								$adsns_adunit_ids = array_slice( $adsns_posted_adunit_ids, 0, $adsns_tabs[ $adsns_area ]['max_ads'] );
							} else {
								$adsns_adunit_ids = $adsns_posted_adunit_ids;
							}

							$adsns_adunit_positions = isset( $_POST['adsns_adunit_position'] ) ? $_POST['adsns_adunit_position'] : array();

							if ( isset( $adsns_publisher_id ) && isset( $adsns_ad_client ) ) {
								foreach ( $adsns_adunit_ids as $adsns_adunit_id ) {
									try {
										$adsns_adunit_code = $adsns_adsense_adunits->getAdCode( $adsns_ad_client, $adsns_adunit_id )->getAdCode();
										$adsns_adunit_position = array_key_exists( $adsns_adunit_id, $adsns_adunit_positions ) ? $adsns_adunit_positions[ $adsns_adunit_id ] : NULL;
										$this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_area ][] = array(
											'id'       => $adsns_adunit_id,
											'position' => $adsns_adunit_position,
											'code'     => htmlspecialchars( $adsns_adunit_code )
										);
									} catch ( Google_Service_Exception $e ) {
										$adsns_err = $e->getErrors();
										$adsns_save_settings = false;
										$adsns_settings_notices[] = array(
											'class'    => 'error below-h2',
											'message'  => sprintf( '%s<br/>%s<br/>%s', sprintf( __( 'An error occurred while obtaining the code for the block %s.', 'adsense-plugin' ), sprintf( '<strong>%s</strong>', $adsns_adunit_id ) ), $adsns_err[0]['message'], __( "Settings are not saved.", 'adsense-plugin' ) )
										);
									}
								}
							}
						}

						if ( $adsns_save_settings ) {
							update_option( 'adsns_settings', $this->adsns_options );
							$adsns_settings_notices[] = array(
								'class'    => 'updated fade below-h2',
								'message'  => __( "Settings saved.", 'adsense-plugin' )
							);
						} else {
							$this->adsns_options = $adsns_old_options;
						}
					} else {
						$adsns_settings_notices[] = array(
							'class'    => 'error below-h2',
							'message'  => __( "Settings are not saved.", 'adsense-plugin' )
						);
					}
				}

				$adsns_hidden_idle_notice = false;
				if ( 1 != $this->adsns_options['include_inactive_ads'] && isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_current_tab ] ) ) {
					$current_ads = $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_current_tab ];
					if ( ! empty( $current_ads ) ) {
						foreach ( $adsns_table_data as $adname => $addata ) {
							foreach ( $current_ads as $current_ad ) {
								if ( $current_ad['id'] == $addata['id'] ) {
									if ( 'INACTIVE' == $addata['status_value'] ) {
										$adsns_hidden_idle_notice = true;
										break(2);
									}
									break;
								}
							}
						}
					}
				}
			}
			/* GO PRO */
			if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) {
				$go_pro_result = bws_go_pro_tab_check( $plugin_basename );
				if ( ! empty( $go_pro_result['error'] ) ) {
					$adsns_settings_notices[] = array(
						'class'    => 'error below-h2',
						'message'  => $go_pro_result['error']
					);
				}
			} ?>
			<div class="wrap" id="adsns_wrap">
				<h1><?php _e( 'Google AdSense Settings', 'adsense-plugin' ); ?></h1>
				<h2 class="nav-tab-wrapper">
					<a class="nav-tab<?php if ( ! isset( $_GET['action'] ) ) echo ' nav-tab-active'; ?>" href="admin.php?page=adsense-plugin.php"><?php _e( 'Settings', 'adsense-plugin' ); ?></a>
					<a class="nav-tab <?php if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=adsense-plugin.php&amp;action=custom_code"><?php _e( 'Custom code', 'adsense-plugin' ); ?></a>
					<a class="nav-tab<?php if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) echo ' nav-tab-active'; ?> bws_go_pro_tab" href="admin.php?page=adsense-plugin.php&amp;action=go_pro"><?php _e( 'Go PRO', 'adsense-plugin' ); ?></a>
				</h2>
				<noscript>
					<div class="error below-h2">
						<p><strong><?php _e( 'WARNING', 'adsense-plugin' ); ?>:</strong> <?php _e( 'The plugin works correctly only if JavaScript is enabled.', 'adsense-plugin' ); ?></p>
					</div>
				</noscript>
				<?php if ( ! empty( $this->adsns_vi_settings_api_error ) ) { ?>
					<div class="error below-h2 adsns_vi_get_settings_api_error">
						<p><strong><?php _e( 'WARNING', 'adsense-plugin' ); ?>:</strong> <?php echo $this->adsns_vi_settings_api_error; ?></p>
					</div>
				<?php }
				if ( isset( $adsns_api_notice ) ) {
					printf( '<div class="below-h2 %s"><p>%s</p></div>', $adsns_api_notice['class'], $adsns_api_notice['message'] );
				}
				if ( isset( $adsns_settings_notices ) ) {
					foreach ( $adsns_settings_notices as $adsns_settings_notice ) {
						printf( '<div class="below-h2 %s"><p>%s</p></div>', $adsns_settings_notice['class'], $adsns_settings_notice['message'] );
					}
				}
				bws_show_settings_notice();
				if ( ! isset( $_GET['action'] ) ) { ?>
					<div class="updated notice notice-warning below-h2 adsns-hidden-idle-notice<?php echo ( $adsns_hidden_idle_notice ) ? '' : ' hidden'; ?>">
						<p><?php _e( 'Some of hidden idle ad blocks still set to be displayed', 'adsense-plugin' ); ?></p>
					</div>
					<?php if ( $this->adsns_vi_token ) {
						$home_path = get_home_path();
						if ( ! file_exists( $home_path . "ads.txt" ) ) {
							$vi_ads_file_content = $this->adsns_vi_get_ads_file_content();
							$vi_ads_google_file_content = $this->adsns_vi_get_google_ads_file_content();
							if ( ! empty( $vi_ads_google_file_content ) ) {
								$vi_ads_file_content .= "\r\n" . $vi_ads_google_file_content;
							} ?>
							<div class="updated error below-h2 adsns_vi_ads_file_notice">
								<div class="adsns_vi_ads_file_notice_content">
									<div class="adsns_vi_ads_file_notice_logo">
										<img class="adsns_vi_ads_file_notice_logo_img" src="<?php echo plugins_url( 'images/vi_logo_white.svg', __FILE__ ); ?>" alt="video intelligence" title="video intelligence" />
									</div>
									<p><strong><?php _e( 'ADS.TXT couldn\'t be added', 'adsense-plugin' ); ?></strong></p>
									<?php if ( ! empty( $vi_ads_file_content ) ) { ?>
										<p><?php _e( 'Important note: Google AdSense by BestWebSoft hasn\'t been able to update your ads.txt file. Please make sure that you enter the following lines manually:', 'adsense-plugin' ); ?></p>
										<div class="adsns_vi_ads_file_content"><?php echo nl2br( $vi_ads_file_content ); ?></div>
										<p><?php _e( 'Only by doing so, you\'ll be able to make more money through video intelligence (vi.ai).' , 'adsense-plugin' ); ?></p>
									<?php } else { ?>
										<p><?php _e( 'If the file is missing, you won\'t be able to make more money through video intelligence (vi.ai).' , 'adsense-plugin' ); ?></p>
									<?php } ?>
								</div>
							</div>
						<?php }
					} ?>
					<form id="adsns_settings_form" class="bws_form" action="admin.php?page=adsense-plugin.php<?php echo $adsns_form_action; ?>" method="post">
						<div class="adsns_container">
							<div class="adsns_api_block">
								<table id="adsns_api_table" class="form-table">
									<tr valign="top">
										<th scope="row"><?php _e( 'Remote work with Google AdSense', 'adsense-plugin' ); ?></th>
										<td>
											<?php if ( $adsns_client->getAccessToken() ) { ?>
												<div id="adsns_api_buttons">
													<input class="button-secondary" name="adsns_logout" type="submit" value="<?php _e( 'Log out from Google AdSense', 'adsense-plugin' ); ?>" />
												</div>
											<?php } else {
												$adsns_auth_url = $adsns_client->createAuthUrl(); ?>
												<div id="adsns_authorization_notice">
													<?php _e( "Please authorize via your Google Account to manage ad blocks.", 'adsense-plugin' ); ?>
												</div>
												<a id="adsns_authorization_button" class="button-primary" href="<?php echo $adsns_auth_url; ?>" target="_blank" onclick="window.open(this.href,'','top='+(screen.height/2-560/2)+',left='+(screen.width/2-640/2)+',width=640,height=560,resizable=0,scrollbars=0,menubar=0,toolbar=0,status=1,location=0').focus(); return false;"><?php _e( 'Get Authorization Code', 'adsense-plugin' ); ?></a>
												<div id="adsns_authorization_form">
													<input id="adsns_authorization_code" class="bws_no_bind_notice" name="adsns_authorization_code" type="text" autocomplete="off" maxlength="100">
													<input id="adsns_authorize" class="button-primary" name="adsns_authorize" type="submit" value="<?php _e( 'Authorize', 'adsense-plugin' ); ?>">
												</div>
											<?php } ?>
										</td>
									</tr>
									<?php if ( isset( $adsns_publisher_id ) ) { ?>
										<tr valign="top">
											<th scope="row"><?php _e( 'Your Publisher ID:', 'adsense-plugin' ); ?></th>
											<td>
												<span id="adsns_publisher_id"><?php echo $adsns_publisher_id; ?></span>
											</td>
										</tr>
									<?php }
									if ( isset( $adsns_publisher_id ) ) {?>
										<tr valign="top">
											<th scope="row"><label for="adsns_include_inactive_id"><?php _e( 'Show idle ad blocks', 'adsense-plugin' ); ?>:</label></th>
											<td>
												<input id="adsns_include_inactive_id" type="checkbox" name="adsns_include_inactive_id" <?php if ( isset( $this->adsns_options['include_inactive_ads'] ) && 1 == $this->adsns_options['include_inactive_ads'] ) echo 'checked="checked"'; ?> value="1">
											</td>
										</tr>
									<?php } ?>
								</table>
							</div>
							<div class="adsns_api_block">
								<div id="adsns_vi_widget" class="adsns_vi_widget_right">
									<div class="adsns_vi_widget_header">
										<div class="adsns_vi_widget_header_content">
											<div class="adsns_vi_widget_logo">
												<img src="<?php echo plugins_url( 'images/vi_logo_white.svg', __FILE__ ); ?>" alt="video intelligence" title="video intelligence" />
											</div>
											<?php if ( ! $this->adsns_vi_token && ! $vi_revenue ) { ?>
												<div class="adsns_vi_widget_title"><?php _e( 'Video content and video advertising – powered by video intelligence', 'adsense-plugin' ); ?></div>
											<?php } else { ?>
												<div class="adsns_vi_widget_title"><?php _e( 'vi stories - video content and video advertising', 'adsense-plugin' ); ?></div>
											<?php } ?>
										</div>
									</div>
									<div class="adsns_vi_widget_body">
										<?php if ( ! $this->adsns_vi_token && ! $vi_revenue ) { ?>
											<p>
												<?php _e( 'Advertisers pay more for video advertising when it\'s matched with video content. This new video player will insert both on your page. It increases time on site, and commands a higher CPM than display advertising.', 'adsense-plugin' );
												?>
											</p>
											<p>
												<?php _e( 'You\'ll see video content that is matched to your sites keywords straight away. A few days after activation you\'ll begin to receive revenue from advertising served before this video content.', 'adsense-plugin' ); ?>
											</p>
											<ul>
												<li><?php _e( 'The set up takes only a few minutes', 'adsense-plugin' ); ?></li>
												<li><?php _e( 'Up to 10x higher CPM than traditional display advertising', 'adsense-plugin' ); ?></li>
												<li><?php _e( 'Users spend longer on your site thanks to professional video content', 'adsense-plugin' ); ?></li>
												<li><?php _e( 'The video player is customizable to match your site', 'adsense-plugin' ); ?></li>
											</ul>
											<p>
												<?php printf( __( 'Watch a %s of how vi stories work.', 'adsense-plugin' ), sprintf( '<a href="%s" target="_blank">%s</a>', $this->adsns_vi_settings_api['demoPageURL'], __( 'demo', 'adsense-plugin' ) ) ); ?>
											</p>
										<?php } else {
											if ( ! isset( $vi_revenue['netRevenue'] ) || ! isset( $vi_revenue['mtdReport'] ) ) { ?>
												<p class="adsns_revenue_api_error">
													<?php _e( 'There was an error processing your request, our team was notified.', 'adsense-plugin' ); ?>
												</p>
												<p class="adsns_revenue_api_error">
													<?php _e( 'Please try again later.', 'adsense-plugin' ); ?>
												</p>
											<?php } else { ?>
												<p>
													<?php _e( 'Below you can see your current revenues.', 'adsense-plugin' ); ?>
												</p>
												<p>
													<?php printf( __( 'Don’t see anything? Consult the %s.', 'adsense-plugin' ), sprintf( '<a href="https://www.vi.ai/frequently-asked-questions-vi-stories-for-wordpress/?utm_source=WordPress&utm_medium=Plugin%%20FAQ&utm_campaign=WP%%20gas" target="_blank">%s</a>', __( 'FAQs', 'adsense-plugin' ) ) ); ?>
												</p>
												<div class="adsns_vi_revenue_content">
													<div class="adsns_vi_revenue_earnings">
														<div class="adsns_vi_revenue_title adsns_vi_revenue_earnings_title">
															<span class="adsns_vi_revenue_title_icon dashicons dashicons-welcome-write-blog"></span><?php _e( 'Total earnings', 'adsense-plugin' ); ?>
														</div>
														<div class="adsns_vi_revenue_earnings_value">$<?php echo number_format( ( $vi_revenue['netRevenue'] !== NULL ? $vi_revenue['netRevenue'] : 0 ), 2, '.', ' ' ); ?></div>
													</div>
													<div class="adsns_vi_revenue_chart">
														<div class="adsns_vi_revenue_title adsns_vi_revenue_chart_title">
															<span class="adsns_vi_revenue_title_icon dashicons dashicons-chart-area"></span><?php _e( 'Chart', 'adsense-plugin' ); ?>
														</div>
														<div class="adsns_vi_revenue_chart_canvas_wrapper">
															<canvas id="adsns_vi_revenue_chart_canvas" width="250" height="130"></canvas>
															<noscript>
																<div class="adsns_vi_revenue_chart_canvas_no_js"><?php _e( 'Please enable JavaScript.', 'adsense-plugin' ); ?></div>
															</noscript>
														</div>
														<?php $vi_revenue_data = $vi_revenue['mtdReport'] !== NULL ? $vi_revenue['mtdReport'] : array();
														$vi_chart_data = array(
															'labels' => array(),
															'data'   => array()
														);

														foreach ( $vi_revenue_data as $data ) {
															$vi_chart_data['labels'][] = date_i18n( 'M d', strtotime( $data['date'] ) );
															$vi_chart_data['data'][] = $data['revenue'];
														} ?>
														<script type="text/javascript">
															(function($) {
																$(document).ready( function() {
																	var $vi_chart_data = <?php echo json_encode( $vi_chart_data ); ?>;
																	$('#adsns_vi_revenue_chart_canvas').trigger( 'displayWidgetChart', $vi_chart_data );
																} );
															})(jQuery);
														</script>
													</div>
													<div class="clear"></div>
												</div>
											<?php }
										} ?>
									</div>
									<div class="adsns_vi_widget_footer">
										<?php if ( ! $this->adsns_vi_token ) { ?>
											<p><?php printf(
												__( 'By clicking Sign Up button you agree to send current domain, email and affiliate ID to %s.', 'adsense-plugin' ),
												sprintf( '<span>%s</span>', __( 'video intelligence', 'adsense-plugin' ) )
											); ?></p>
											<div class="adsns_vi_widget_actions">
												<a href="admin.php?page=adsense-plugin.php&action=vi_login" id="adsns_vi_widget_button_login" class="button button-secondary adsns_vi_widget_button"><?php _e( 'Log In', 'adsense-plugin' )?></a>
												<a href="admin.php?page=adsense-plugin.php&action=vi_signup" id="adsns_vi_widget_button_signup" class="button button-primary adsns_vi_widget_button"><?php _e( 'Sign Up', 'adsense-plugin' )?></a>
											</div>
										<?php } else { ?>
											<div class="adsns_vi_widget_actions">
												<?php if ( ! empty( $this->adsns_vi_settings_api['dashboardURL'] ) ) { ?>
													<a href="<?php echo $this->adsns_vi_settings_api['dashboardURL']; ?>" id="adsns_vi_widget_button_dashboard" class="button button-primary adsns_vi_widget_button" target="_blank"><?php _e( 'Publisher Dashboard', 'adsense-plugin' )?></a>
												<?php } ?>
												<button id="adsns_vi_widget_button_log_out" class="button button-secondary adsns_vi_widget_button" name="adsns_vi_logout" type="submit"><?php _e( 'Log Out', 'adsense-plugin' )?></button>
											</div>
										<?php } ?>
									</div>
								</div>
							</div>
							<div class="clear"></div>
						</div>
						<?php if ( ( isset( $adsns_publisher_id ) && isset( $adsns_tabs[ $adsns_current_tab ] ) ) || ( $this->adsns_vi_token && $vi_revenue ) ) { ?>
							<h2 id="adsns-tabs" class="nav-tab-wrapper">
								<?php foreach( $adsns_tabs as $adsns_tab => $adsns_tab_data ) {
									$adsns_count_ads = 0;

									if ( isset( $adsns_publisher_id ) && isset( $adsns_tabs[ $adsns_current_tab ] ) ) {
										if ( isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_tab ] ) ) {
											$adsns_count_ads = count( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_tab ] );
										}
									} else {
										if ( $adsns_tab == 'widget' ) {
											continue;
										}
									}

									if ( $this->adsns_vi_token && $vi_revenue ) {
										if ( isset( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $adsns_tab ] ) && $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $adsns_tab ] === true ) {
											$adsns_count_ads = $adsns_count_ads + 1;
										}
									}

									printf( '<a class="nav-tab%s" href="%s#adsns-tabs">%s <span class="adsns_count_ads">%d</span></a>', ( $adsns_tab == $adsns_current_tab ) ? ' nav-tab-active' : '', $adsns_tab_data['tab']['url'], $adsns_tab_data['tab']['title'], $adsns_count_ads );
								} ?>
							</h2>
							<div id="adsns_tab_content" <?php if ( $adsns_current_tab == 'search' ) echo 'class="bws_pro_version_bloc adsns_pro_version_bloc"'; ?>>
								<div <?php if ( $adsns_current_tab == 'search' ) echo 'class="bws_pro_version_table_bloc adsns_pro_version_table_bloc"'?>>
									<div <?php if ( $adsns_current_tab == 'search' ) echo 'class="bws_table_bg adsns_table_bg"'?>></div>
									<div id="adsns_usage_notice">
										<?php if ( $adsns_current_tab == 'widget' ) { ?>
											<p><?php printf( __( "Please don't forget to place the AdSense widget into a needed sidebar on the %s.", 'adsense-plugin' ), sprintf( '<a href="widgets.php" target="_blank">%s</a>', __( 'widget page', 'adsense-plugin' ) ) ); printf( ' %s <a href="https://bestwebsoft.com/products/wordpress/plugins/google-adsense/?k=2887beb5e9d5e26aebe6b7de9152ad1f&amp;pn=80&amp;v=%s&amp;wp_v=%s" target="_blank"><strong>Pro</strong></a>.', __( 'An opportunity to add several widgets is available in the', 'adsense-plugin' ), $this->adsns_plugin_info["Version"], $wp_version ); ?></p>
										<?php } ?>
										<p>
											<?php printf( __( 'Add or manage existing ad blocks in the %s.', 'adsense-plugin' ), sprintf( '<a href="https://www.google.com/adsense/app#main/myads-viewall-adunits" target="_blank">%s</a>', __( 'Google AdSense', 'adsense-plugin' ) ) ); ?><br />
											<span class="bws_info"><?php printf( __( 'After adding the ad block in Google AdSense, please %s to see the new ad block in the list of plugin ad blocks.', 'adsense-plugin' ), sprintf( '<a href="admin.php?page=adsense-plugin.php%s">%s</a>', $adsns_form_action, __( 'reload the page', 'adsense-plugin' ) ) ) ; ?></span>
										</p>
									</div>
									<?php if ( ( $this->adsns_vi_token && $vi_revenue ) && ( ! isset( $_GET['tab'] ) || $_GET['tab'] != 'widget' ) ) { ?>
										<div class="adsns_vi_story_new_wrapper">
											<a href="admin.php?page=adsense-plugin.php&action=vi_story" id="adsns_vi_story_new" class="button button-secondary"><?php echo ( empty( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['data'] ) ) ? __( 'Add New vi story', 'adsense-plugin' ) : __( 'Edit vi story', 'adsense-plugin' ); ?></a>
										</div>
									<?php } ?>
									<?php if ( isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_current_tab ] ) ) {
										foreach ( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ][ $adsns_current_tab ] as $adsns_table_adunit ) {
											$adsns_table_adunits[ $adsns_table_adunit['id'] ] = $adsns_table_adunit['position'];
										}
									}
									$adsns_lt = new Adsns_List_Table( $this->adsns_options );
									$adsns_lt->adsns_table_area = $adsns_current_tab;
									$adsns_lt->adsns_vi_publisher_id = $this->adsns_vi_publisher_id;
									$adsns_lt->adsns_vi_token = $this->adsns_vi_token;
									$adsns_lt->adsns_table_data = $adsns_table_data;
									$adsns_lt->adsns_table_adunits = ( isset( $adsns_table_adunits ) && is_array( $adsns_table_adunits ) ) ? $adsns_table_adunits : array();
									$adsns_lt->adsns_adunit_positions = $adsns_tabs[ $adsns_current_tab ]['adunit_positions'];
									$adsns_lt->adsns_adunit_positions_pro = $adsns_tabs[ $adsns_current_tab ]['adunit_positions_pro'];
									$adsns_lt->prepare_items();
									echo '<div class="adsns-ads-list">';
										$adsns_lt->display();
									echo "</div>"; ?>
								</div>
								<?php if ( $adsns_current_tab == 'search' ) { ?>
									<div class="bws_pro_version_tooltip adsns_pro_version_tooltip">
										<a class="bws_button" href="https://bestwebsoft.com/products/wordpress/plugins/google-adsense/?k=2887beb5e9d5e26aebe6b7de9152ad1f&amp;pn=80&amp;v=<?php echo $this->adsns_plugin_info["Version"]; ?>&amp;wp_v=<?php echo $wp_version; ?>" target="_blank" title="Google AdSense Pro"><?php _e( 'Learn More', 'adsense-plugin' ); ?></a>
										<div class="clear"></div>
									</div>
								<?php } ?>
							</div>
						<?php }
						if ( isset( $adsns_publisher_id ) || ( $this->adsns_vi_token && $vi_revenue ) ) { ?>
							<p>
								<input type="hidden" name="adsns_area" value="<?php echo $adsns_current_tab; ?>" />
								<input id="bws-submit-button" type="submit" class="button-primary" name="adsns_save_settings" value="<?php _e( 'Save Changes', 'adsense-plugin' ); ?>" />
							</p>
						<?php } ?>
						<?php wp_nonce_field( plugin_basename( __FILE__ ), 'adsns_nonce_name' ); ?>
					</form>
					<?php if ( ! $this->adsns_vi_token ) { ?>
						<div id="adsns_modal_signup" class="adsns_modal">
							<div class="adsns_modal_dialog">
								<div class="adsns_modal_dialog_content">
									<div class="adsns_modal_dialog_header">
										<button class="notice-dismiss adsns_modal_dialog_close" type="button"></button>
									</div>
									<div class="adsns_modal_dialog_body">
										<?php $this->adsns_vi_signup_form(); ?>
									</div>
								</div>
							</div>
						</div>
						<div id="adsns_modal_login" class="adsns_modal">
							<div class="adsns_modal_dialog">
								<div class="adsns_modal_dialog_content">
									<div class="adsns_modal_dialog_header">
										<div class="adsns_modal_dialog_title"><?php _e( 'Log In', 'adsense-plugin' )?></div>
										<button class="notice-dismiss adsns_modal_dialog_close" type="button"></button>
									</div>
									<div class="adsns_modal_dialog_body">
										<div class="adsns_vi_login_form_wrapper">
											<?php $this->adsns_vi_login_form(); ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					<?php } elseif ( $this->adsns_vi_token && $vi_revenue ) { ?>
						<div id="adsns_modal_new_story" class="adsns_modal">
							<div class="adsns_modal_dialog">
								<div class="adsns_modal_dialog_content">
									<div class="adsns_modal_dialog_header">
										<div class="adsns_modal_dialog_title"><?php _e( 'vi stories: customize your video player', 'adsense-plugin' ); ?></div>
										<button class="notice-dismiss adsns_modal_dialog_close" type="button"></button>
									</div>
									<div class="adsns_modal_dialog_body">
										<div class="adsns_vi_story_form_wrapper">
											<?php $this->adsns_vi_story_form(); ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					<?php } ?>
				<?php } elseif ( 'vi_login' == $_GET['action'] ) { ?>
					<div class="adsns_vi_page_title">video intelligence: <strong><?php _e( 'Log In', 'adsense-plugin' ); ?></strong></div>
						<div class="adsns_vi_login_form_no_js">
							<?php if ( ! $this->adsns_vi_token ) {
								$vi_display_login_form = true;
								$vi_login_form_error = false;
								if ( isset( $_POST['adsns_vi_login_submit'] ) && wp_verify_nonce( $_POST['adsns_vi_login_nonce'], 'adsns_vi_login_nonce' ) ) {
									$vi_login_response = $this->adsns_vi_login();
									if ( $vi_login_response['status'] == 'error' ) {
										$vi_login_form_error = $vi_login_response['error']['description'];
									} else {
										$vi_display_login_form = false;
									}
								}
								if ( $vi_display_login_form ) {
									$this->adsns_vi_login_form( $vi_login_form_error );
								} else {
									printf(
										'%s <a href="admin.php?page=adsense-plugin.php">%s</a> %s',
										__( 'You are logged in.', 'adsense-plugin' ),
										__( 'Go back', 'adsense-plugin' ),
										__( 'to the settings page.', 'adsense-plugin' )
									);
								}
							} else {
								printf(
									'%s <a href="admin.php?page=adsense-plugin.php">%s</a> %s',
									__( 'You are logged in.', 'adsense-plugin' ),
									__( 'Go back', 'adsense-plugin' ),
									__( 'to the settings page.', 'adsense-plugin' )
								);
							} ?>
						</div>
				<?php } elseif ( 'vi_signup' == $_GET['action'] ) {
					if ( ! $this->adsns_vi_token ) { ?>
						<div class="adsns_vi_page_title">video intelligence: <strong><?php _e( 'Sign Up', 'adsense-plugin' ); ?></strong></div>
						<?php $this->adsns_vi_signup_form();
					}
				} elseif ( 'vi_story' == $_GET['action'] ) {
					if ( $this->adsns_vi_token ) { ?>
						<div class="adsns_vi_page_title">video intelligence: <strong><?php _e( 'vi stories: customize your video player', 'adsense-plugin' ); ?></strong></div>
						<div class="adsns_vi_story_form_no_js">
							<?php $this->adsns_vi_story_form( $vi_story_save_result ); ?>
						</div>
					<?php }
				} elseif ( 'custom_code' == $_GET['action'] ) {
					bws_custom_code_tab();
				} elseif ( 'go_pro' == $_GET['action'] ) {
					bws_go_pro_tab_show( false, $this->adsns_plugin_info, $plugin_basename, 'adsense-plugin.php', 'adsense-pro.php', 'adsense-pro/adsense-pro.php', 'google-adsense', '2887beb5e9d5e26aebe6b7de9152ad1f', '80', isset( $go_pro_result['pro_plugin_is_activated'] ) );
				}
				$this->adsns_plugin_reviews_block( $this->adsns_plugin_info['Name'], 'adsense-plugin' ); ?>
			</div>
		<?php }

		/* Display review block (moved from BWS_Menu) */
		function adsns_plugin_reviews_block( $plugin_name, $plugin_slug ) { ?>
			<div class="bws-plugin-reviews">
				<div class="bws-plugin-reviews-rate">
					<?php _e( 'Like the plugin?', 'adsense-plugin' ); ?>
					<a href="https://wordpress.org/support/view/plugin-reviews/<?php echo esc_attr( $plugin_slug ); ?>?filter=5" target="_blank" title="<?php printf( __( '%s reviews', 'adsense-plugin' ), sanitize_text_field( $plugin_name ) ); ?>">
						<?php _e( 'Rate it', 'adsense-plugin' ); ?>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
					</a>
				</div>
				<div class="bws-plugin-reviews-support">
					<?php _e( 'Need help?', 'adsense-plugin' ); ?>
					<a href="mailto:support@gasplugin.com">support@gasplugin.com</a>
				</div>
				<div class="bws-plugin-reviews-donate">
					<?php _e( 'Want to support the plugin?', 'adsense-plugin' ); ?>
					<a href="https://bestwebsoft.com/donate/"><?php _e( 'Donate', 'adsense-plugin' ); ?></a>
				</div>
			</div>
		<?php }

		/* Get vi settings API */
		function adsns_vi_get_settings_api() {
			if ( is_admin() || is_network_admin() ) {
				$vi_api_url = 'https://dashboard-api.vidint.net/v1/api/widget/settings';

				$vi_settings_response = wp_remote_get( $vi_api_url, array(
					'timeout'	=> 30,
					'headers'	=> array( 'Content-Type' => 'application/json' )
					)
				);

				$vi_settings_api['response'] = $vi_settings_response;

				if ( is_wp_error( $vi_settings_response ) ) {
					$this->adsns_vi_settings_api_error = '<strong>vi Settings API</strong>: ' .$vi_settings_response->get_error_message();
				} else {
					if ( wp_remote_retrieve_response_code( $vi_settings_response ) == 200 ) {
						$vi_settings_response_body = json_decode( wp_remote_retrieve_body( $vi_settings_response ), true );
						if ( ! empty( $vi_settings_response_body['data'] ) && is_array( $vi_settings_response_body['data'] ) ) {
							$this->adsns_vi_settings_api = $vi_settings_response_body['data'];
						} else {
							$this->adsns_vi_settings_api_error = '<strong>vi Settings API</strong>: ' . __( 'Something went wrong.', 'adsense-plugin' );
						}
					}
				}
			}
		}

		/* Get domain */
		function adsns_get_domain() {
			$site_url = parse_url( site_url( '/' ) );
			return $site_url['host'];
		}

		/* Set vi token */
		function adsns_vi_set_token( $vi_token ) {
			$this->adsns_vi_token = $this->adsns_options['vi_token'] = $vi_token;
			update_option( 'adsns_settings', $this->adsns_options );
		}

		/* Get vi token */
		function adsns_vi_get_token() {
			$this->adsns_vi_token = isset( $this->adsns_options['vi_token'] ) ? $this->adsns_options['vi_token'] : NULL;
		}

		/* Get data from vi token */
		function adsns_vi_get_token_data( $vi_token = '', $param = NULL ) {
			$vi_token_data = NULL;

			if ( $vi_token ) {
				$vi_token_arr = explode( '.', $vi_token );
				if ( ! empty( $vi_token_arr[1] ) ) {
					$vi_token_data_json_decode = json_decode( base64_decode( $vi_token_arr[1] ), true );
				}

				if ( $param && isset( $vi_token_data_json_decode[ $param ] ) ) {
					$vi_token_data = $vi_token_data_json_decode[ $param ];
				} else {
					$vi_token_data = $vi_token_data_json_decode;
				}
			}

			return $vi_token_data;
		}

		/* Get vi story categories */
		function adsns_vi_get_story_iab_categories() {
			return array(
				'IAB1' => 'Arts & Entertainment',
				'IAB2' => 'Automotive',
				'IAB3' => 'Business',
				'IAB4' => 'Careers',
				'IAB5' => 'Education',
				'IAB6' => 'Family & Parenting',
				'IAB7' => 'Health & Fitness',
				'IAB8' => 'Food & Drink',
				'IAB9' => 'Hobbies & Interests',
				'IAB10' => 'Home & Garden',
				'IAB11' => 'Law, Gov’t & Politics',
				'IAB12' => 'News',
				'IAB13' => 'Personal Finance',
				'IAB14' => 'Society',
				'IAB15' => 'Science',
				'IAB16' => 'Pets',
				'IAB17' => 'Sports',
				'IAB18' => 'Style & Fashion',
				'IAB19' => 'Technology & Computing',
				'IAB20' => 'Travel',
				'IAB21' => 'Real Estate',
				'IAB22' => 'Shopping',
				'IAB23' => 'Religion & Spirituality',
				'IAB24' => 'Uncategorized',
				'IAB25' => 'Non-Standard Content',
				'IAB26' => 'Illegal Content'
			);
		}

		/* Get vi story subcategories */
		function adsns_vi_get_story_iab_subcategories() {
			return array(
				'IAB1-1' => 'Books & Literature',
				'IAB1-2' => 'Celebrity Fan/Gossip',
				'IAB1-3' => 'Fine Art',
				'IAB1-4' => 'Humor',
				'IAB1-5' => 'Movies',
				'IAB1-6' => 'Music',
				'IAB1-7' => 'Television',
				'IAB2-1' => 'Auto Parts',
				'IAB2-2' => 'Auto Repair',
				'IAB2-3' => 'Buying/Selling Cars',
				'IAB2-4' => 'Car Culture',
				'IAB2-5' => 'Certified Pre-Owned',
				'IAB2-6' => 'Convertible',
				'IAB2-7' => 'Coupe',
				'IAB2-8' => 'Crossover',
				'IAB2-9' => 'Diesel',
				'IAB2-10' => 'Electric Vehicle',
				'IAB2-11' => 'Hatchback',
				'IAB2-12' => 'Hybrid',
				'IAB2-13' => 'Luxury',
				'IAB2-14' => 'MiniVan',
				'IAB2-15' => 'Mororcycles',
				'IAB2-16' => 'Off-Road Vehicles',
				'IAB2-17' => 'Performance Vehicles',
				'IAB2-18' => 'Pickup',
				'IAB2-19' => 'Road-Side Assistance',
				'IAB2-20' => 'Sedan',
				'IAB2-21' => 'Trucks & Accessories',
				'IAB2-22' => 'Vintage Cars',
				'IAB2-23' => 'Wagon',
				'IAB3-1' => 'Advertising',
				'IAB3-2' => 'Agriculture',
				'IAB3-3' => 'Biotech/Biomedical',
				'IAB3-4' => 'Business Software',
				'IAB3-5' => 'Construction',
				'IAB3-6' => 'Forestry',
				'IAB3-7' => 'Government',
				'IAB3-8' => 'Green Solutions',
				'IAB3-9' => 'Human Resources',
				'IAB3-10' => 'Logistics',
				'IAB3-11' => 'Marketing',
				'IAB3-12' => 'Metals',
				'IAB4-1' => 'Career Planning',
				'IAB4-2' => 'College',
				'IAB4-3' => 'Financial Aid',
				'IAB4-4' => 'Job Fairs',
				'IAB4-5' => 'Job Search',
				'IAB4-6' => 'Resume Writing/Advice',
				'IAB4-7' => 'Nursing',
				'IAB4-8' => 'Scholarships',
				'IAB4-9' => 'Telecommuting',
				'IAB4-10' => 'U.S. Military',
				'IAB4-11' => 'Career Advice',
				'IAB5-1' => '7-12 Education',
				'IAB5-2' => 'Adult Education',
				'IAB5-3' => 'Art History',
				'IAB5-4' => 'Colledge Administration',
				'IAB5-5' => 'College Life',
				'IAB5-6' => 'Distance Learning',
				'IAB5-7' => 'English as a 2nd Language',
				'IAB5-8' => 'Language Learning',
				'IAB5-9' => 'Graduate School',
				'IAB5-10' => 'Homeschooling',
				'IAB5-11' => 'Homework/Study Tips',
				'IAB5-12' => 'K-6 Educators',
				'IAB5-13' => 'Private School',
				'IAB5-14' => 'Special Education',
				'IAB5-15' => 'Studying Business',
				'IAB6-1' => 'Adoption',
				'IAB6-2' => 'Babies & Toddlers',
				'IAB6-3' => 'Daycare/Pre School',
				'IAB6-4' => 'Family Internet',
				'IAB6-5' => 'Parenting – K-6 Kids',
				'IAB6-6' => 'Parenting teens',
				'IAB6-7' => 'Pregnancy',
				'IAB6-8' => 'Special Needs Kids',
				'IAB6-9' => 'Eldercare',
				'IAB7-1' => 'Exercise',
				'IAB7-2' => 'A.D.D.',
				'IAB7-3' => 'AIDS/HIV',
				'IAB7-4' => 'Allergies',
				'IAB7-5' => 'Alternative Medicine',
				'IAB7-6' => 'Arthritis',
				'IAB7-7' => 'Asthma',
				'IAB7-8' => 'Autism/PDD',
				'IAB7-9' => 'Bipolar Disorder',
				'IAB7-10' => 'Brain Tumor',
				'IAB7-11' => 'Cancer',
				'IAB7-12' => 'Cholesterol',
				'IAB7-13' => 'Chronic Fatigue Syndrome',
				'IAB7-14' => 'Chronic Pain',
				'IAB7-15' => 'Cold & Flu',
				'IAB7-16' => 'Deafness',
				'IAB7-17' => 'Dental Care',
				'IAB7-18' => 'Depression',
				'IAB7-19' => 'Dermatology',
				'IAB7-20' => 'Diabetes',
				'IAB7-21' => 'Epilepsy',
				'IAB7-22' => 'GERD/Acid Reflux',
				'IAB7-23' => 'Headaches/Migraines',
				'IAB7-24' => 'Heart Disease',
				'IAB7-25' => 'Herbs for Health',
				'IAB7-26' => 'Holistic Healing',
				'IAB7-27' => 'IBS/Crohn’s Disease',
				'IAB7-28' => 'Incest/Abuse Support',
				'IAB7-29' => 'Incontinence',
				'IAB7-30' => 'Infertility',
				'IAB7-31' => 'Men’s Health',
				'IAB7-32' => 'Nutrition',
				'IAB7-33' => 'Orthopedics',
				'IAB7-34' => 'Panic/Anxiety Disorders',
				'IAB7-35' => 'Pediatrics',
				'IAB7-36' => 'Physical Therapy',
				'IAB7-37' => 'Psychology/Psychiatry',
				'IAB7-38' => 'Senor Health',
				'IAB7-39' => 'Sexuality',
				'IAB7-40' => 'Sleep Disorders',
				'IAB7-41' => 'Smoking Cessation',
				'IAB7-42' => 'Substance Abuse',
				'IAB7-43' => 'Thyroid Disease',
				'IAB7-44' => 'Weight Loss',
				'IAB7-45' => 'Women’s Health',
				'IAB8-1' => 'American Cuisine',
				'IAB8-2' => 'Barbecues & Grilling',
				'IAB8-3' => 'Cajun/Creole',
				'IAB8-4' => 'Chinese Cuisine',
				'IAB8-5' => 'Cocktails/Beer',
				'IAB8-6' => 'Coffee/Tea',
				'IAB8-7' => 'Cuisine-Specific',
				'IAB8-8' => 'Desserts & Baking',
				'IAB8-9' => 'Dining Out',
				'IAB8-10' => 'Food Allergies',
				'IAB8-11' => 'French Cuisine',
				'IAB8-12' => 'Health/Lowfat Cooking',
				'IAB8-13' => 'Italian Cuisine',
				'IAB8-14' => 'Japanese Cuisine',
				'IAB8-15' => 'Mexican Cuisine',
				'IAB8-16' => 'Vegan',
				'IAB8-17' => 'Vegetarian',
				'IAB8-18' => 'Wine',
				'IAB9-1' => 'Art/Technology',
				'IAB9-2' => 'Arts & Crafts',
				'IAB9-3' => 'Beadwork',
				'IAB9-4' => 'Birdwatching',
				'IAB9-5' => 'Board Games/Puzzles',
				'IAB9-6' => 'Candle & Soap Making',
				'IAB9-7' => 'Card Games',
				'IAB9-8' => 'Chess',
				'IAB9-9' => 'Cigars',
				'IAB9-10' => 'Collecting',
				'IAB9-11' => 'Comic Books',
				'IAB9-12' => 'Drawing/Sketching',
				'IAB9-13' => 'Freelance Writing',
				'IAB9-14' => 'Genealogy',
				'IAB9-15' => 'Getting Published',
				'IAB9-16' => 'Guitar',
				'IAB9-17' => 'Home Recording',
				'IAB9-18' => 'Investors & Patents',
				'IAB9-19' => 'Jewelry Making',
				'IAB9-20' => 'Magic & Illusion',
				'IAB9-21' => 'Needlework',
				'IAB9-22' => 'Painting',
				'IAB9-23' => 'Photography',
				'IAB9-24' => 'Radio',
				'IAB9-25' => 'Roleplaying Games',
				'IAB9-26' => 'Sci-Fi & Fantasy',
				'IAB9-27' => 'Scrapbooking',
				'IAB9-28' => 'Screenwriting',
				'IAB9-29' => 'Stamps & Coins',
				'IAB9-30' => 'Video & Computer Games',
				'IAB9-31' => 'Woodworking',
				'IAB10-1' => 'Appliances',
				'IAB10-2' => 'Entertaining',
				'IAB10-3' => 'Environmental Safety',
				'IAB10-4' => 'Gardening',
				'IAB10-5' => 'Home Repair',
				'IAB10-6' => 'Home Theater',
				'IAB10-7' => 'Interior Decorating',
				'IAB10-8' => 'Landscaping',
				'IAB10-9' => 'Remodeling & Construction',
				'IAB11-1' => 'Immigration',
				'IAB11-2' => 'Legal Issues',
				'IAB11-3' => 'U.S. Government Resources',
				'IAB11-4' => 'Politics',
				'IAB11-5' => 'Commentary',
				'IAB12-1' => 'International News',
				'IAB12-2' => 'National News',
				'IAB12-3' => 'Local News',
				'IAB13-1' => 'Beginning Investing',
				'IAB13-2' => 'Credit/Debt & Loans',
				'IAB13-3' => 'Financial News',
				'IAB13-4' => 'Financial Planning',
				'IAB13-5' => 'Hedge Fund',
				'IAB13-6' => 'Insurance',
				'IAB13-7' => 'Investing',
				'IAB13-8' => 'Mutual Funds',
				'IAB13-9' => 'Options',
				'IAB13-10' => 'Retirement Planning',
				'IAB13-11' => 'Stocks',
				'IAB13-12' => 'Tax Planning',
				'IAB14-1' => 'Dating',
				'IAB14-2' => 'Divorce Support',
				'IAB14-3' => 'Gay Life',
				'IAB14-4' => 'Marriage',
				'IAB14-5' => 'Senior Living',
				'IAB14-6' => 'Teens',
				'IAB14-7' => 'Weddings',
				'IAB14-8' => 'Ethnic Specific',
				'IAB15-1' => 'Astrology',
				'IAB15-2' => 'Biology',
				'IAB15-3' => 'Chemistry',
				'IAB15-4' => 'Geology',
				'IAB15-5' => 'Paranormal Phenomena',
				'IAB15-6' => 'Physics',
				'IAB15-7' => 'Space/Astronomy',
				'IAB15-8' => 'Geography',
				'IAB15-9' => 'Botany',
				'IAB15-10' => 'Weather',
				'IAB16-1' => 'Aquariums',
				'IAB16-2' => 'Birds',
				'IAB16-3' => 'Cats',
				'IAB16-4' => 'Dogs',
				'IAB16-5' => 'Large Animals',
				'IAB16-6' => 'Reptiles',
				'IAB16-7' => 'Veterinary Medicine',
				'IAB17-1' => 'Auto Racing',
				'IAB17-2' => 'Baseball',
				'IAB17-3' => 'Bicycling',
				'IAB17-4' => 'Bodybuilding',
				'IAB17-5' => 'Boxing',
				'IAB17-6' => 'Canoeing/Kayaking',
				'IAB17-7' => 'Cheerleading',
				'IAB17-8' => 'Climbing',
				'IAB17-9' => 'Cricket',
				'IAB17-10' => 'Figure Skating',
				'IAB17-11' => 'Fly Fishing',
				'IAB17-12' => 'Football',
				'IAB17-13' => 'Freshwater Fishing',
				'IAB17-14' => 'Game & Fish',
				'IAB17-15' => 'Golf',
				'IAB17-16' => 'Horse Racing',
				'IAB17-17' => 'Horses',
				'IAB17-18' => 'Hunting/Shooting',
				'IAB17-19' => 'Inline Skating',
				'IAB17-20' => 'Martial Arts',
				'IAB17-21' => 'Mountain Biking',
				'IAB17-22' => 'NASCAR Racing',
				'IAB17-23' => 'Olympics',
				'IAB17-24' => 'Paintball',
				'IAB17-25' => 'Power & Motorcycles',
				'IAB17-26' => 'Pro Basketball',
				'IAB17-27' => 'Pro Ice Hockey',
				'IAB17-28' => 'Rodeo',
				'IAB17-29' => 'Rugby',
				'IAB17-30' => 'Running/Jogging',
				'IAB17-31' => 'Sailing',
				'IAB17-32' => 'Saltwater Fishing',
				'IAB17-33' => 'Scuba Diving',
				'IAB17-34' => 'Skateboarding',
				'IAB17-35' => 'Skiing',
				'IAB17-36' => 'Snowboarding',
				'IAB17-37' => 'Surfing/Bodyboarding',
				'IAB17-38' => 'Swimming',
				'IAB17-39' => 'Table Tennis/Ping-Pong',
				'IAB17-40' => 'Tennis',
				'IAB17-41' => 'Volleyball',
				'IAB17-42' => 'Walking',
				'IAB17-43' => 'Waterski/Wakeboard',
				'IAB17-44' => 'World Soccer',
				'IAB18-1' => 'Beauty',
				'IAB18-2' => 'Body Art',
				'IAB18-3' => 'Fashion',
				'IAB18-4' => 'Jewelry',
				'IAB18-5' => 'Clothing',
				'IAB18-6' => 'Accessories',
				'IAB19-1' => '3-D Graphics',
				'IAB19-2' => 'Animation',
				'IAB19-3' => 'Antivirus Software',
				'IAB19-4' => 'C/C++',
				'IAB19-5' => 'Cameras & Camcorders',
				'IAB19-6' => 'Cell Phones',
				'IAB19-7' => 'Computer Certification',
				'IAB19-8' => 'Computer Networking',
				'IAB19-9' => 'Computer Peripherals',
				'IAB19-10' => 'Computer Reviews',
				'IAB19-11' => 'Data Centers',
				'IAB19-12' => 'Databases',
				'IAB19-13' => 'Desktop Publishing',
				'IAB19-14' => 'Desktop Video',
				'IAB19-15' => 'Email',
				'IAB19-16' => 'Graphics Software',
				'IAB19-17' => 'Home Video/DVD',
				'IAB19-18' => 'Internet Technology',
				'IAB19-19' => 'Java',
				'IAB19-20' => 'JavaScript',
				'IAB19-21' => 'Mac Support',
				'IAB19-22' => 'MP3/MIDI',
				'IAB19-23' => 'Net Conferencing',
				'IAB19-24' => 'Net for Beginners',
				'IAB19-25' => 'Network Security',
				'IAB19-26' => 'Palmtops/PDAs',
				'IAB19-27' => 'PC Support',
				'IAB19-28' => 'Portable',
				'IAB19-29' => 'Entertainment',
				'IAB19-30' => 'Shareware/Freeware',
				'IAB19-31' => 'Unix',
				'IAB19-32' => 'Visual Basic',
				'IAB19-33' => 'Web Clip Art',
				'IAB19-34' => 'Web Design/HTML',
				'IAB19-35' => 'Web Search',
				'IAB19-36' => 'Windows',
				'IAB20-1' => 'Adventure Travel',
				'IAB20-2' => 'Africa',
				'IAB20-3' => 'Air Travel',
				'IAB20-4' => 'Australia & New Zealand',
				'IAB20-5' => 'Bed & Breakfasts',
				'IAB20-6' => 'Budget Travel',
				'IAB20-7' => 'Business Travel',
				'IAB20-8' => 'By US Locale',
				'IAB20-9' => 'Camping',
				'IAB20-10' => 'Canada',
				'IAB20-11' => 'Caribbean',
				'IAB20-12' => 'Cruises',
				'IAB20-13' => 'Eastern Europe',
				'IAB20-14' => 'Europe',
				'IAB20-15' => 'France',
				'IAB20-16' => 'Greece',
				'IAB20-17' => 'Honeymoons/Getaways',
				'IAB20-18' => 'Hotels',
				'IAB20-19' => 'Italy',
				'IAB20-20' => 'Japan',
				'IAB20-21' => 'Mexico & Central America',
				'IAB20-22' => 'National Parks',
				'IAB20-23' => 'South America',
				'IAB20-24' => 'Spas',
				'IAB20-25' => 'Theme Parks',
				'IAB20-26' => 'Traveling with Kids',
				'IAB20-27' => 'United Kingdom',
				'IAB21-1' => 'Apartments',
				'IAB21-2' => 'Architects',
				'IAB21-3' => 'Buying/Selling Homes',
				'IAB22-1' => 'Contests & Freebies',
				'IAB22-2' => 'Couponing',
				'IAB22-3' => 'Comparison',
				'IAB22-4' => 'Engines',
				'IAB23-1' => 'Alternative Religions',
				'IAB23-2' => 'Atheism/Agnosticism',
				'IAB23-3' => 'Buddhism',
				'IAB23-4' => 'Catholicism',
				'IAB23-5' => 'Christianity',
				'IAB23-6' => 'Hinduism',
				'IAB23-7' => 'Islam',
				'IAB23-8' => 'Judaism',
				'IAB23-9' => 'Latter-Day Saints',
				'IAB23-10' => 'Pagan/Wiccan',
				'IAB25-1' => 'Unmoderated UGC',
				'IAB25-2' => 'Extreme Graphic/Explicit Violence',
				'IAB25-3' => 'Pornography',
				'IAB25-4' => 'Profane Content',
				'IAB25-5' => 'Hate Content',
				'IAB25-6' => 'Under Construction',
				'IAB25-7' => 'Incentivized',
				'IAB26-1' => 'Illegal Content',
				'IAB26-2' => 'Warez',
				'IAB26-3' => 'Spyware/Malware',
				'IAB26-4' => 'Copyright Infringement'
			);
		}

		/* Get vi story type of ad units */
		function adsns_vi_get_story_ad_units() {
			return array( 'NATIVE_VIDEO_UNIT' => 'vi stories' );
		}

		/* Get vi story language */
		function adsns_vi_get_story_languages() {
			$vi_story_languages = array();

			if ( $this->adsns_vi_settings_api ) {
				foreach ( $this->adsns_vi_settings_api['languages'] as $language ) {
					foreach ( $language as $key => $value ) {
						$vi_story_languages[ $key ] = $value;
					}
				}
			}

			return $vi_story_languages;
		}

		/* Get vi story font family */
		function adsns_vi_get_story_font_families() {
			return array(
				'Arial',
				'Arial Black',
				'Comic Sans MS',
				'Courier New',
				'Georgia',
				'Impact',
				'Lucida Console',
				'Lucida Sans Unicode',
				'Palatino Linotype',
				'Tahoma',
				'Times New Roman',
				'Trebuchet MS',
				'Verdana'
			);
		}

		/* Get vi story font size */
		function adsns_vi_get_story_font_sizes() {
			return array( 8, 9, 10, 11, 12, 14, 16, 18, 20, 22, 24, 26, 28, 36 );
		}

		/* Get vi login form */
		function adsns_vi_login_form( $error = '' ) { ?>
			<form class="adsns_vi_login_form" method="post" action="">
				<div class="adsns_modal_login_content">
					<div class="adsns_vi_login_error <?php if ( ! empty( $error ) ) echo 'adsns_vi_login_error_visible'; ?>"><?php if ( ! empty( $error ) ) echo $error; ?></div>
					<div class="adsns_modal_login_row">
						<div class="adsns_dialog_login_input_label"><?php _e( 'Email', 'adsense-plugin' ); ?></div>
						<input class="adsns_dialog_login_input adsns_dialog_login_input_email" type="text" name="adsns_vi_login_email" maxlength="150" />
					</div>
					<div class="adsns_modal_login_row">
						<div class="adsns_dialog_login_input_label"><?php _e( 'Password', 'adsense-plugin' ); ?></div>
						<input class="adsns_dialog_login_input adsns_dialog_login_input_password" type="password" name="adsns_vi_login_password" maxlength="150" />
					</div>
					<div class="adsns_modal_login_row">
						<button class="button button-primary adsns_dialog_login_button" type="submit" name="adsns_vi_login_submit"><?php _e( 'Log In', 'adsense-plugin' ); ?></button>
						<input type="hidden" name="adsns_vi_login_nonce" value="<?php echo wp_create_nonce( 'adsns_vi_login_nonce' ); ?>">
					</div>
				</div>
			</form>
			<div class="adsns_vi_login_blocker" style="background-image: url( <?php echo plugins_url( 'images/ajax_loader.svg' , __FILE__ ); ?> );"></div>
		<?php }

		/* Get vi sign up form */
		function adsns_vi_signup_form() {
			if ( $this->adsns_vi_settings_api ) {
				$vi_iframe_url = sprintf( $this->adsns_vi_settings_api['signupURL'] . "?aid=WP_gas&domain=%s&email=%s", $this->adsns_get_domain(), get_option( 'admin_email' ) );
			} else {
				$vi_iframe_url = 'about:blank'; ?>
					<div class="error below-h2 adsns_vi_get_settings_api_error">
						<p><strong><?php _e( 'WARNING', 'adsense-plugin' ); ?>:</strong> <?php echo $this->adsns_vi_settings_api_error; ?></p>
					</div>
			<?php } ?>
			<iframe id="adsns_vi_signup_iframe" src="<?php echo $vi_iframe_url; ?>" frameborder="0"></iframe>
		<?php }

		/* Get vi story form */
		function adsns_vi_story_form( $save_result = array() ) {
			$vi_story_data_defaults = array(
				'adUnitType'		=> '',
				'keywords'			=> '',
				'iabCategory'		=> '',
				'language'			=> '',
				'backgroundColor'	=> '',
				'textColor'			=> '',
				'font'				=> '',
				'fontSize'			=> '',
				'vioptional1'		=> '',
				'vioptional2'		=> '',
				'vioptional3'		=> ''
			);

			$vi_story_data_saved =
				( ! empty ( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['data'] ) )
			?
				$this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['data']
			:
				array();

			$vi_story_error = '';
			$vi_story_field_errors = array();

			if ( ! empty( $save_result ) && $save_result['status'] == 'error' ) {
				$vi_story_error = ( ! empty( $save_result['error']['description'] ) ) ? $save_result['error']['description'] : '';
				$vi_story_field_errors = array_merge( $vi_story_data_defaults, $save_result['data']['errors'] );
				$vi_story_data = array_merge( $vi_story_data_defaults, $save_result['data']['values'] );
			} else {
				$vi_story_data = array_merge( $vi_story_data_defaults, $vi_story_data_saved );
			} ?>
			<form class="adsns_vi_story_form" method="post" action="">
				<div class="adsns_vi_story_form_content">
					<div class="adsns_vi_story_error <?php echo ( ! empty( $vi_story_error ) ) ? 'adsns_vi_story_error_visible' : ''; ?>"><?php echo ( ! empty( $vi_story_error ) ) ? $vi_story_error : ''; ?></div>
					<div class="adsns_vi_story_notice"><?php _e( 'Use this form to customize the look of the video unit. Use the same parameters as your WordPress theme for a natural look on your site.', 'adsense-plugin' ); ?></div>
					<div class="adsns_vi_story_block_left">
						<table class="adsns_vi_story_table adsns_vi_story_table_left">
							<tbody>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_ad_unit"><?php _e( 'Ad unit', 'adsense-plugin' ); ?>*</label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field_tooltip">
											<div class="adsns_vi_story_field adsns_vi_story_field_ad_unit">
												<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['adUnitType'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="adUnitType"><?php echo ( ! empty( $vi_story_field_errors['adUnitType'] ) ) ? $vi_story_field_errors['adUnitType'] : ''; ?></div>
												<select id="adsns_vi_story_ad_unit" class="adsns_vi_story_select" name="adsns_vi_story_ad_unit" data-field-id="adUnitType">
													<?php foreach ( $this->adsns_vi_get_story_ad_units() as $key => $value ) {
														printf( '<option value="%s" %s>%s</option>', $key, selected( $vi_story_data['adUnitType'], $key, false ), $value );
													} ?>
												</select>
											</div>
											<span class="adsns_vi_story_tooltip">
												<span class="adsns_vi_story_tooltip_icon dashicons dashicons-info"></span>
												<span class="adsns_vi_story_tooltip_content"><?php _e( 'vi stories (video advertising + video content).', 'adsense-plugin' ) ?></span>
											</span>
										</div>
									</td>
								</tr>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_keywords"><?php _e( 'Keywords', 'adsense-plugin' ); ?></label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field_tooltip">
											<div class="adsns_vi_story_field adsns_vi_story_field_keywords">
												<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['keywords'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="keywords"><?php echo ( ! empty( $vi_story_field_errors['keywords'] ) ) ? $vi_story_field_errors['keywords'] : ''; ?></div>
												<textarea id="adsns_vi_story_keywords" class="adsns_vi_story_textarea" name="adsns_vi_story_keywords" maxlength="200" cols="50" rows="4" placeholder="<?php printf( '%s %s', __( 'Max length 200 chars.', 'adsense-plugin' ), __( 'a-z, A-Z, numbers, dashes, umlauts and accents are allowed.', 'adsense-plugin' ) ); ?>" data-field-id="keywords"><?php echo $vi_story_data['keywords']; ?></textarea>
											</div>
											<span class="adsns_vi_story_tooltip">
												<span class="adsns_vi_story_tooltip_icon dashicons dashicons-info"></span>
												<span class="adsns_vi_story_tooltip_content"><?php _e( 'Comma separated values describing the content of the page e.g. \'cooking, grilling, pulled pork\'.', 'adsense-plugin' ) ?></span>
											</span>
										</div>
									</td>
								</tr>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_iab_category"><?php _e( 'IAB Category', 'adsense-plugin' ); ?><span class="vi_story_symbol_required">*</span></label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field_wrapper">
											<div class="adsns_vi_story_field adsns_vi_story_field_iab_category">
												<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['iabCategory'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="iabCategory"><?php echo ( ! empty( $vi_story_field_errors['iabCategory'] ) ) ? $vi_story_field_errors['iabCategory'] : ''; ?></div>
												<select id="adsns_vi_story_iab_category" class="adsns_vi_story_select" name="adsns_vi_story_iab_category" data-field-id="iabCategory">
													<option value=""><?php _e( 'Select tier 1 category', 'adsense-plugin' ); ?></option>
													<?php foreach ( $this->adsns_vi_get_story_iab_categories() as $key => $value ) {
														$vi_category = preg_replace( '/(-[\d]{1,2})/', '', $vi_story_data['iabCategory'] );
														printf( '<option value="%s"%s>%s</option>', $key, selected( $vi_category, $key, false ), $value );
													} ?>
												</select>
											</div>
											<div class="adsns_vi_story_field adsns_vi_story_field_iab_subcategory">
												<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['iabSubCategory'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="iabSubCategory"><?php echo ( ! empty( $vi_story_field_errors['iabSubCategory'] ) ) ? $vi_story_field_errors['iabSubCategory'] : ''; ?></div>
												<select id="adsns_vi_story_iab_subcategory" class="adsns_vi_story_select" name="adsns_vi_story_iab_subcategory" data-field-id="iabSubCategory">
													<option value=""><?php _e( 'Select tier 2 category', 'adsense-plugin' ); ?></option>
													<?php foreach ( $this->adsns_vi_get_story_iab_subcategories() as $key => $value ) {
														$vi_category = preg_replace( '/(-[\d]{1,2})/', '', $key );
														printf( '<option value="%s" data-category="%s"%s>%s</option>', $key, $vi_category, selected( $vi_story_data['iabCategory'], $key, false ), $value );
													} ?>
												</select>
											</div>
										</div>
										<div class="adsns_vi_story_field_right_content">
											<?php if ( ! empty( $this->adsns_vi_settings_api['iabCategoriesURL'] ) ) { ?>
												<a class="adsns_vi_story_field_link" href="<?php echo $this->adsns_vi_settings_api['iabCategoriesURL'];?>" target="_blank"><?php _e( 'See complete list', 'adsense-plugin' ); ?></a>
											<?php } ?>
										</div>
									</td>
								</tr>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_language"><?php _e( 'Language', 'adsense-plugin' ); ?><span class="vi_story_symbol_required">*</span></label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field adsns_vi_story_field_language">
											<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['language'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="language"><?php echo ( ! empty( $vi_story_field_errors['language'] ) ) ? $vi_story_field_errors['language'] : ''; ?></div>
											<select id="adsns_vi_story_language" class="adsns_vi_story_select" name="adsns_vi_story_language" data-field-id="language">
												<option value=""><?php _e( 'Select language', 'adsense-plugin' ); ?></option>
												<?php foreach ( $this->adsns_vi_get_story_languages() as $key => $value ) {
													printf( '<option value="%s"%s>%s</option>', $key, selected( $vi_story_data['language'], $key, false ), $value );
												} ?>
											</select>
										</div>
									</td>
								</tr>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_background_color"><?php _e( 'Native background color', 'adsense-plugin' ); ?></label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field adsns_vi_story_field_background_color">
											<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['backgroundColor'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="backgroundColor"><?php echo ( ! empty( $vi_story_field_errors['backgroundColor'] ) ) ? $vi_story_field_errors['backgroundColor'] : ''; ?></div>
											<input id="adsns_vi_story_background_color" type="text" name="adsns_vi_story_background_color" maxlength="7" value="<?php echo $vi_story_data['backgroundColor']; ?>" placeholder="<?php _e( 'Select color', 'adsense-plugin' ); ?>" autocomplete="off" data-field-id="backgroundColor" />
										</div>
									</td>
								</tr>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_text_color"><?php _e( 'Native text color', 'adsense-plugin' ); ?></label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field adsns_vi_story_field_text_color">
											<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['textColor'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="textColor"><?php echo ( ! empty( $vi_story_field_errors['textColor'] ) ) ? $vi_story_field_errors['textColor'] : ''; ?></div>
											<input id="adsns_vi_story_text_color" type="text" name="adsns_vi_story_text_color" value="<?php echo $vi_story_data['textColor']; ?>" maxlength="7" placeholder="<?php _e( 'Select color', 'adsense-plugin' ); ?>" autocomplete="off" data-field-id="textColor" />
										</div>
									</td>
								</tr>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_font_family"><?php _e( 'Native text font family', 'adsense-plugin' ); ?></label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field adsns_vi_story_field_font_family">
											<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['font'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="font"><?php echo ( ! empty( $vi_story_field_errors['font'] ) ) ? $vi_story_field_errors['font'] : ''; ?></div>
											<select id="adsns_vi_story_font_family" class="adsns_vi_story_select" name="adsns_vi_story_font_family" data-field-id="font">
												<option value=""><?php _e( 'Select font family', 'adsense-plugin' ); ?></option>
												<?php foreach ( $this->adsns_vi_get_story_font_families() as $value ) {
													printf( '<option value="%1$s"%2$s>%1$s</option>', $value, selected( $vi_story_data['font'], $value, false ) );
												} ?>
											</select>
										</div>
									</td>
								</tr>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_font_size"><?php _e( 'Native text font size', 'adsense-plugin' ); ?></label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field adsns_vi_story_field_font_size">
											<div class="adsns_vi_story_field_error <?php echo ( ! empty( $vi_story_field_errors['fontSize'] ) ) ? 'adsns_vi_story_field_error_visible' : ''; ?>" data-error-id="fontSize"><?php echo ( ! empty( $vi_story_field_errors['fontSize'] ) ) ? $vi_story_field_errors['fontSize'] : ''; ?></div>
											<select id="adsns_vi_story_font_size" class="adsns_vi_story_select" name="adsns_vi_story_font_size" data-field-id="fontSize">
												<option value=""><?php _e( 'Select font size', 'adsense-plugin' ); ?></option>
												<?php foreach ( $this->adsns_vi_get_story_font_sizes() as $value ) {
													printf( '<option value="%1$s"%2$s>%1$s %3$s</option>', $value, selected( $vi_story_data['fontSize'], $value, false ), __( 'px', 'adsense-plugin' ) );
												} ?>
											</select>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
						<table class="adsns_vi_story_table adsns_vi_story_table_right">
							<tbody>
								<tr>
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_optional_1"><?php _e( 'Optional', 'adsense-plugin' ); ?> 1</label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field_wrapper">
											<div class="adsns_vi_story_field adsns_vi_story_field adsns_vi_story_optional">
												<textarea id="adsns_vi_story_optional_1" class="adsns_vi_story_textarea" name="adsns_vi_story_optional[]" maxlength="200" cols="50" rows="4" placeholder="<?php _e( 'Max length 200 chars', 'adsense-plugin' ); ?>"><?php echo $vi_story_data['vioptional1']; ?></textarea>
											</div>
										</div>
										<div class="adsns_vi_story_field_right_content">
											<button class="adsns_vi_story_field_button adsns_vi_story_field_button_add hide-if-no-js" type="button">
												<span class="adsns_vi_story_field_button_icon dashicons dashicons-plus-alt"></span>
												<span class="adsns_vi_story_field_button_text"><?php _e( 'Add field', 'adsense-plugin' ); ?></span>
											</button>
										</div>
									</td>
								</tr>
								<tr class="adsns_vi_story_table_row_optional adsns_vi_story_table_row_optional_hidden">
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_optional_2"><?php _e( 'Optional', 'adsense-plugin' ); ?> 2</label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field_wrapper">
											<div class="adsns_vi_story_field adsns_vi_story_field adsns_vi_story_optional">
												<textarea id="adsns_vi_story_optional_2" class="adsns_vi_story_textarea" name="adsns_vi_story_optional[]" maxlength="200" cols="50" rows="4" placeholder="<?php _e( 'Max length 200 chars', 'adsense-plugin' ); ?>"><?php echo $vi_story_data['vioptional2']; ?></textarea>
											</div>
										</div>
										<div class="adsns_vi_story_field_right_content">
											<button class="adsns_vi_story_field_button adsns_vi_story_field_button_remove hide-if-no-js" type="button">
												<span class="adsns_vi_story_field_button_icon dashicons dashicons-dismiss"></span>
												<span class="adsns_vi_story_field_button_text"><?php _e( 'Remove field', 'adsense-plugin' ); ?></span>
											</button>
										</div>
									</td>
								</tr>
								<tr class="adsns_vi_story_table_row_optional adsns_vi_story_table_row_optional_hidden">
									<td class="adsns_vi_story_table_title">
										<label for="adsns_vi_story_optional_3"><?php _e( 'Optional', 'adsense-plugin' ); ?> 3</label>
									</td>
									<td class="adsns_vi_story_table_content">
										<div class="adsns_vi_story_field_wrapper">
											<div class="adsns_vi_story_field adsns_vi_story_field adsns_vi_story_optional">
												<textarea id="adsns_vi_story_optional_3" class="adsns_vi_story_textarea" name="adsns_vi_story_optional[]" maxlength="200" cols="50" rows="4" placeholder="<?php _e( 'Max length 200 chars', 'adsense-plugin' ); ?>"><?php echo $vi_story_data['vioptional3']; ?></textarea>
											</div>
										</div>
										<div class="adsns_vi_story_field_right_content">
											<button class="adsns_vi_story_field_button adsns_vi_story_field_button_remove hide-if-no-js" type="button">
												<span class="adsns_vi_story_field_button_icon dashicons dashicons-dismiss"></span>
													<span class="adsns_vi_story_field_button_text"><?php _e( 'Remove field', 'adsense-plugin' ); ?></span>
											</button>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
						<div class="clear"></div>
					</div>
					<div class="adsns_vi_story_block_right">
						<div class="adsns_vi_story_example">
							<img class="adsns_vi_story_example_image" src="<?php echo plugins_url( 'images/vi_example_ads.jpg', __FILE__ ); ?>" title="vi story" alt="vi story">
						</div>
					</div>
					<div class="clear"></div>
					<div class="adsns_vi_story_info">
						<?php _e( 'vi Ad Changes might take some time to take into effect', 'adsense-plugin' ); ?>
					</div>
				</div>
				<div class="adsns_vi_story_actions">
					<button id="adsns_vi_story_submit" class="button button-primary adsns_dialog_vi_story_button" type="submit" name="adsns_vi_story_submit"><?php _e( 'Save Changes', 'adsense-plugin' ); ?></button>
					<button id="adsns_vi_story_cancel" class="button button-secondary adsns_dialog_vi_story_button" type="button" name="adsns_vi_story_cancel"><?php _e( 'Cancel', 'adsense-plugin' ); ?></button>
					<input type="hidden" name="adsns_vi_story_nonce" value="<?php echo wp_create_nonce( 'adsns_vi_story_nonce' ); ?>">
				</div>
			</form>
			<div class="adsns_vi_story_blocker" style="background-image: url( <?php echo plugins_url( 'images/ajax_loader.svg', __FILE__ ); ?> );"></div>
		<?php }

		/* Create ads.txt file */
		function adsns_vi_create_ads_file( $type, $content = '' ) {

			$result = false;

			if ( ! empty( $content ) ) {
				$home_path = get_home_path();
				$ads_txt = $home_path . "ads.txt";
				$file_content = '';

				if ( is_writable( $home_path ) ) {
					if ( file_exists( $ads_txt ) ) {
						$file_content = file( $ads_txt, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

						switch ( $type ) {
							case 'vi':
								$google_content = preg_grep( '/google.com,[\s]pub-[\d]+,[\s]DIRECT/', $file_content );
								$file_content = implode( "\r\n", $google_content );

								if ( ! empty( $file_content ) ) {
									$file_content = $content . "\r\n" . $file_content;
								} else {
									$file_content = $content;
								}

								break;
							case 'google':
								$vi_content = preg_grep( '/google.com,[\s]pub-[\d]+,[\s]DIRECT/', $file_content, PREG_GREP_INVERT );
								$file_content = implode( "\r\n", $vi_content );

								if ( ! empty( $file_content ) ) {
									$file_content = $file_content . "\r\n" . $content;
								} else {
									$file_content = $content;
								}

								break;
							default:
								break;
						}
					} else {
						$file_content = $content;
					}

					$fp = fopen( $ads_txt, "w" );
					@fwrite( $fp, $file_content );
					fclose( $fp );
					@chmod( $ads_txt, 0777 );
					$result = true;
				}
			}

			return $result;
		}

		/* Get content for ads.txt file */
		function adsns_vi_get_ads_file_content() {
			$vi_ads_file_content = '';
			if ( $this->adsns_vi_token && $this->adsns_vi_settings_api ) {
				$vi_ads_txt_response = wp_remote_get( $this->adsns_vi_settings_api['adsTxtAPI'], array(
					'timeout'	=> 30,
					'headers'	=> array(
						'Content-Type'	=> 'application/json',
						'Authorization'	=> $this->adsns_vi_token
						)
					)
				);

				if ( is_wp_error( $vi_ads_txt_response ) ) {
					$vi_response_data['error']['description'] = '<strong>vi Ads-txt API</strong>: ' . $vi_ads_txt_response->get_error_message();
				} else {
					$vi_ads_txt_response_code = wp_remote_retrieve_response_code( $vi_ads_txt_response );

					if ( $vi_ads_txt_response_code == 200 ) {
						$vi_ads_txt_response_body = json_decode( wp_remote_retrieve_body( $vi_ads_txt_response ), 200 );
						if ( isset( $vi_ads_txt_response_body['data'] ) ) {
							$vi_ads_file_content =  $vi_ads_txt_response_body['data'];
						}
					}
				}
			}

			return $vi_ads_file_content;
		}

		/* Get content for ads.txt file */
		function adsns_vi_get_google_ads_file_content() {
			$vi_ads_file_content = '';

			if ( ! empty( $this->adsns_options['publisher_id'] ) ) {
				$vi_ads_file_content = sprintf( "google.com, %s, DIRECT", $this->adsns_options['publisher_id'] );
			}

			return $vi_ads_file_content;
		}

		/* vi login proccess */
		function adsns_vi_login() {
			$vi_response_data = array(
				'status'	=> 'error',
				'error'		=> array(
					'message'		=> __( 'Request error', 'adsense-plugin' ),
					'description'	=> '<strong>vi Login API</strong>: ' . __( 'Something went wrong.', 'adsense-plugin' )
				),
				'data' => NULL
			);

			if (
				isset( $_POST['adsns_vi_login_nonce'] ) &&
				wp_verify_nonce( $_POST['adsns_vi_login_nonce'], 'adsns_vi_login_nonce' )
			) {
				if ( $this->adsns_vi_settings_api ) {

					$vi_login_response = wp_remote_post( $this->adsns_vi_settings_api['loginAPI'], array(
						'method' 	=> 'POST',
						'timeout' 	=> 30,
						'headers'	=> array( 'Content-Type' => 'application/json' ),
						'body'		=> json_encode( array(
							'email'		=> $_POST["adsns_vi_login_email"],
							'password'	=> $_POST["adsns_vi_login_password"]
							) )
						)
					);

					if ( is_wp_error( $vi_login_response ) ) {
						$vi_response_data['error']['description'] = '<strong>vi Login API</strong>: ' . $vi_login_response->get_error_message();
					} else {
						$vi_login_response_code = wp_remote_retrieve_response_code( $vi_login_response );
						$vi_login_response_body = wp_remote_retrieve_body( $vi_login_response );

						if ( $vi_login_response_code == 200 ) {
							$vi_login_response_json_decode = json_decode( $vi_login_response_body, true );
							$vi_token = $vi_login_response_json_decode['data'];

							$this->adsns_vi_set_token( $vi_token );

							$this->adsns_vi_create_ads_file( 'vi', $this->adsns_vi_get_ads_file_content() );
							$this->adsns_vi_create_ads_file( 'google', $this->adsns_vi_get_google_ads_file_content() );

							$this->adsns_options['vi_publisher_id'] = $this->adsns_vi_get_token_data( $this->adsns_vi_token, 'publisherId' );
							update_option( 'adsns_settings', $this->adsns_options );

							$vi_response_data = array(
								'status'	=> 'ok',
								'error'		=> NULL,
								'data'		=> NULL
							);
						} else {
							$vi_response_data = json_decode( $vi_login_response_body, true );
						}
					}
				} else {
					$vi_response_data['error']['description'] = $this->adsns_vi_settings_api_error;
				}

				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					echo json_encode( $vi_response_data );
					wp_die();
				} else {
					return $vi_response_data;
				}
			}
		}

		/* vi logout proccess */
		function adsns_vi_logout() {
			$this->adsns_vi_token = NULL;
			$this->adsns_options['vi_token'] = '';
			update_option( 'adsns_settings', $this->adsns_options );
		}

		/* Get vi revenue proccess */
		function adsns_vi_get_revenue() {
			$vi_revenue = array();
			if ( $this->adsns_vi_settings_api && $this->adsns_vi_token ) {
				$vi_revenue_response = wp_remote_get( $this->adsns_vi_settings_api['revenueAPI'], array(
					'timeout'	=> 30,
					'headers'	=> array(
						'Content-Type'	=> 'application/json',
						'Authorization'	=> $this->adsns_vi_token
						)
					)
				);

				if ( ! is_wp_error( $vi_revenue_response ) ) {
					$vi_revenue_response_code = wp_remote_retrieve_response_code( $vi_revenue_response );

					if ( $vi_revenue_response_code == 200 ) {
						$vi_revenue_response_body = wp_remote_retrieve_body( $vi_revenue_response );
						$vi_revenue_response_json_decode = json_decode( $vi_revenue_response_body, true );
						$vi_revenue = $vi_revenue_response_json_decode['data'];
					}
				}
			}

			return $vi_revenue;
		}

		/* Get vi story errors */
		function adsns_vi_get_story_error( $type = '' ) {
			$error = '';
			$error_types = array(
				'required'		=> __( 'This field is required.', 'adsense-plugin' ),
				'isIn'			=> __( 'Please select a correct value.', 'adsense-plugin' ),
				'isNumber'		=> __( 'Please select a correct value.', 'adsense-plugin' ),
				'isHexColor'	=> __( 'Please enter a correct HEX value.', 'adsense-plugin' ),
				'isMatch'		=> __( 'Allowed only a-z, A-Z, numbers, dashes, umlauts and accents.', 'adsense-plugin' )
			);

			if ( array_key_exists( $type, $error_types ) ) {
				$error = $error_types[ $type ];
			}

			return $error;
		}

		/* vi story jstag proccess */
		function adsns_vi_story_jstag( $vi_story_data = array() ) {
			$vi_response_data = array(
				'status'	=> 'error',
				'error'		=> array(
					'message'		=> __( 'Request error', 'adsense-plugin' ),
					'description'	=> '<strong>vi jsTag API</strong>: ' . __( 'Something went wrong.', 'adsense-plugin' )
				),
				'data' => NULL
			);

			if ( $this->adsns_vi_settings_api ) {
				$vi_story_jstag_response = wp_remote_post( $this->adsns_vi_settings_api['jsTagAPI'], array(
					'method' 	=> 'POST',
					'timeout' 	=> 30,
					'headers'	=> array(
						'Content-Type'	=> 'application/json',
						'Authorization'	=> $this->adsns_vi_token
					),
					'body'		=> json_encode( $vi_story_data )
				) );

				if ( is_wp_error( $vi_story_jstag_response ) ) {
					$vi_response_data['error']['description'] = '<strong>vi jsTag API</strong>: ' . $vi_story_jstag_response->get_error_message();
				} else {

					$vi_story_jstag_response_code = wp_remote_retrieve_response_code( $vi_story_jstag_response );
					$vi_story_jstag_response_body = wp_remote_retrieve_body( $vi_story_jstag_response );

					if ( $vi_story_jstag_response_code == 200 || $vi_story_jstag_response_code == 201 ) {
						$vi_story_jstag_response_json_decode = json_decode( $vi_story_jstag_response_body, true );

						if ( ! empty( $vi_story_jstag_response_json_decode['data'] ) ) {
							$this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['data'] = $vi_story_data;
							$this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['jstag'] = $vi_story_jstag_response_json_decode['data'];
							update_option( 'adsns_settings', $this->adsns_options );

							$vi_response_data = array(
								'status'	=> 'ok',
								'error'		=> NULL,
								'data'		=> NULL
							);
						}
					} else {
						$vi_response_data = json_decode( $vi_story_jstag_response_body, true );

						$vi_story_data_return = array(
							'values'	=> $vi_story_data,
							'errors'	=> array()
						);

						if ( ! empty( $vi_response_data['error']['description'] ) ) {
							if ( is_array( $vi_response_data['error']['description'] ) ) {
								foreach ( $vi_response_data['error']['description'] as $data ) {
									$error_type = $data['failed'];
									foreach ( $data['path'] as $key => $field ) {
										$vi_story_data_return['errors'][ $field ] = $this->adsns_vi_get_story_error( $error_type );
									}
								}
								$vi_response_data['error']['description'] = __( 'Some errors occurred.', 'adsense-plugin' );
							} else {
								$vi_response_data['error']['description'] = $vi_response_data['error']['description'];
							}
						}

						$vi_response_data['data'] = $vi_story_data_return;
					}
				}
			}

			return $vi_response_data;
		}

		/* Save\update vi story proccess */
		function adsns_vi_story_save() {
			$vi_response_data = array(
				'status'	=> 'error',
				'error'		=> array(
					'message'		=> __( 'Request error', 'adsense-plugin' ),
					'description'	=> '<strong>vi jsTag API</strong>: ' . __( 'Something went wrong.', 'adsense-plugin' )
				),
				'data' => NULL
			);

			if (
				isset( $_POST['adsns_vi_story_nonce'] ) &&
				wp_verify_nonce( $_POST['adsns_vi_story_nonce'], 'adsns_vi_story_nonce' )
			) {
				if ( $this->adsns_vi_settings_api ) {
					$vi_story_data_posted = array(
						'adUnitType'		=> isset( $_POST['adsns_vi_story_ad_unit'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_ad_unit'] ) ) ) : '',
						'keywords'			=> isset( $_POST['adsns_vi_story_keywords'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_keywords'] ) ) ) : '',
						'iabCategory'		=> isset( $_POST['adsns_vi_story_iab_category'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_iab_category'] ) ) ) : '',
						'iabSubCategory'	=> isset( $_POST['adsns_vi_story_iab_subcategory'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_iab_subcategory'] ) ) ) : '',
						'language'			=> isset( $_POST['adsns_vi_story_language'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_language'] ) ) ) : '',
						'backgroundColor'	=> isset( $_POST['adsns_vi_story_background_color'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_background_color'] ) ) ) : '',
						'textColor'			=> isset( $_POST['adsns_vi_story_text_color'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_text_color'] ) ) ) : '',
						'font'				=> isset( $_POST['adsns_vi_story_font_family'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_font_family'] ) ) ) : '',
						'fontSize'			=> isset( $_POST['adsns_vi_story_font_size'] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_font_size'] ) ) ) : '',
						'vioptional1'		=> isset( $_POST['adsns_vi_story_optional'][0] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_optional'][0] ) ) ) : '',
						'vioptional2'		=> isset( $_POST['adsns_vi_story_optional'][1] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_optional'][1] ) ) ) : '',
						'vioptional3'		=> isset( $_POST['adsns_vi_story_optional'][2] ) ? trim( strip_tags( stripslashes( $_POST['adsns_vi_story_optional'][2] ) ) ) : ''
					);

					$vi_story_data_return = array(
						'values'	=> $vi_story_data_posted,
						'errors'	=> array()
					);

					$vi_story_data_jstag = array(
						'domain'	=> $this->adsns_get_domain(),
						'divId'		=> 'ads_vi'
					);

					/* adUnitType */
					if ( ! empty( $vi_story_data_posted['adUnitType'] ) ) {
						if ( array_key_exists( $vi_story_data_posted['adUnitType'], $this->adsns_vi_get_story_ad_units() ) ) {
							$vi_story_data_jstag['adUnitType'] = $vi_story_data_posted['adUnitType'];
						} else {
							$vi_story_data_return['errors']['adUnitType'] = $this->adsns_vi_get_story_error( 'isIn' );
						}
					} else {
						$vi_story_data_return['errors']['adUnitType'] = $this->adsns_vi_get_story_error( 'required' );
					}

					/* keywords */
					if ( ! empty( $vi_story_data_posted['keywords'] ) ) {
						if ( preg_match( '/^[a-zA-ZàâäôéèëêïîçùûüÿæœÀÂÄÔÉÈËÊÏÎŸÇÙÛÜÆŒößÖẞ0-9-,\s]+$/', $vi_story_data_posted['keywords'] ) ) {
							$vi_story_data_jstag['keywords'] = $vi_story_data_posted['keywords'];
						} else {
							$vi_story_data_return['errors']['keywords'] = $this->adsns_vi_get_story_error( 'isMatch' );
						}
					}

					/* iabCategory */
					if ( ! empty( $vi_story_data_posted['iabCategory'] ) ) {
						if ( preg_match( '/^IAB[\d]{1,2}$/', $vi_story_data_posted['iabCategory'] ) ) {
							$vi_story_data_jstag['iabCategory'] = $vi_story_data_posted['iabCategory'];
						} else {
							$vi_story_data_return['errors']['iabCategory'] = $this->adsns_vi_get_story_error( 'isIn' );
						}
					} else {
						if ( ! empty( $vi_story_data_jstag['adUnitType'] ) && $vi_story_data_jstag['adUnitType'] == 'NATIVE_VIDEO_UNIT' ) {
							$vi_story_data_return['errors']['iabCategory'] = $this->adsns_vi_get_story_error( 'required' );
						}
					}

					/* iabSubCategory */
					if ( ! empty( $vi_story_data_jstag['iabCategory'] ) && ! empty( $vi_story_data_posted['iabSubCategory'] ) ) {
						if ( preg_match( '/^' . $vi_story_data_jstag['iabCategory'] . '-[\d]{1,2}$/', $vi_story_data_posted['iabSubCategory'] ) ) {
							$vi_story_data_jstag['iabCategory'] = $vi_story_data_posted['iabCategory'] = $vi_story_data_posted['iabSubCategory'];
						} else {
							if ( ! empty( $vi_story_data_jstag['adUnitType'] ) && $vi_story_data_jstag['adUnitType'] == 'NATIVE_VIDEO_UNIT' ) {
								$vi_story_data_return['errors']['iabSubCategory'] = $this->adsns_vi_get_story_error( 'isIn' );
							}
						}
					}

					/* language */
					if ( ! empty( $vi_story_data_posted['language'] ) ) {
						if ( array_key_exists( $vi_story_data_posted['language'], $this->adsns_vi_get_story_languages() ) ) {
							$vi_story_data_jstag['language'] = $vi_story_data_posted['language'];
						} else {
							$vi_story_data_return['errors']['language'] = $this->adsns_vi_get_story_error( 'isIn' );
						}
					} else {
						if ( ! empty( $vi_story_data_jstag['adUnitType'] ) && $vi_story_data_jstag['adUnitType'] == 'NATIVE_VIDEO_UNIT' ) {
							$vi_story_data_return['errors']['language'] = $this->adsns_vi_get_story_error( 'required' );
						}
					}

					/* backgroundColor */
					if ( ! empty( $vi_story_data_posted['backgroundColor'] ) ) {
						if ( preg_match( '/^#([a-f0-9]{6}|[a-f0-9]{3})$/', $vi_story_data_posted['backgroundColor'] ) ) {
							$vi_story_data_jstag['backgroundColor'] = $vi_story_data_posted['backgroundColor'];
						} else {
							$vi_story_data_return['errors']['backgroundColor'] = $this->adsns_vi_get_story_error( 'isHex' );
						}
					}

					/* textColor */
					if ( ! empty( $vi_story_data_posted['textColor'] ) ) {
						if ( preg_match( '/^#([a-f0-9]{6}|[a-f0-9]{3})$/', $vi_story_data_posted['textColor'] ) ) {
							$vi_story_data_jstag['textColor'] = $vi_story_data_posted['textColor'];
						} else {
							$vi_story_data_return['errors']['textColor'] = $this->adsns_vi_get_story_error( 'isHex' );
						}
					}

					/* font */
					if ( ! empty( $vi_story_data_posted['font'] ) ) {
						if ( in_array( $vi_story_data_posted['font'], $this->adsns_vi_get_story_font_families() ) ) {
							$vi_story_data_jstag['font'] = $vi_story_data_posted['font'];
						} else {
							$vi_story_data_return['errors']['font'] = $this->adsns_vi_get_story_error( 'isIn' );
						}
					}

					/* fontSize */
					if ( ! empty( $vi_story_data_posted['fontSize'] ) ) {
						if ( in_array( $vi_story_data_posted['fontSize'], $this->adsns_vi_get_story_font_sizes() ) ) {
							$vi_story_data_jstag['fontSize'] = $vi_story_data_posted['fontSize'];
						} else {
							$vi_story_data_return['errors']['fontSize'] = $this->adsns_vi_get_story_error( 'isIn' );
						}
					}

					/* vioptional1 */
					if ( ! empty( $vi_story_data_posted['vioptional1'] ) ) {
						$vi_story_data_jstag['vioptional1'] = $vi_story_data_posted['vioptional1'];
					}

					/* vioptional2 */
					if ( ! empty( $vi_story_data_posted['vioptional2'] ) ) {
						$vi_story_data_jstag['vioptional2'] = $vi_story_data_posted['vioptional2'];
					}

					/* vioptional3 */
					if ( ! empty( $vi_story_data_posted['vioptional3'] ) ) {
						$vi_story_data_jstag['vioptional3'] = $vi_story_data_posted['vioptional3'];
					}

					if ( $vi_story_data_return['errors'] ) {
						$vi_response_data['error']['description'] = __( 'Some errors occurred.', 'adsense-plugin' );
						$vi_response_data['data'] = $vi_story_data_return;
					} else {
						$vi_response_data = $this->adsns_vi_story_jstag( $vi_story_data_jstag );
					}
				}
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				echo json_encode( $vi_response_data );
				wp_die();
			} else {
				return $vi_response_data;
			}
		}

		/* Including scripts and stylesheets for admin interface of plugin */
		public function adsns_write_admin_head() {
			if ( isset( $_GET['page'] ) && "adsense-plugin.php" == $_GET['page'] ) {
				wp_enqueue_script( 'adsns_chart_js', plugins_url( 'js/chart.min.js' , __FILE__ ), array( 'jquery' ), $this->adsns_plugin_info["Version"] );
				wp_enqueue_script( 'adsns_color_picker_js', plugins_url( 'js/jquery.minicolors.min.js' , __FILE__ ), array( 'jquery' ), $this->adsns_plugin_info["Version"] );
				wp_enqueue_script( 'adsns_admin_js', plugins_url( 'js/admin.js' , __FILE__ ), array( 'jquery' ), $this->adsns_plugin_info["Version"] );
				wp_enqueue_style( 'adsns_color_picker_css', plugins_url( 'css/jquery.minicolors.css', __FILE__ ), false, $this->adsns_plugin_info["Version"] );

				bws_enqueue_settings_scripts();

				if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] )
					bws_plugins_include_codemirror();
			}
			wp_enqueue_style( 'adsns_admin_css', plugins_url( 'css/style.css', __FILE__ ), false, $this->adsns_plugin_info["Version"] );
			wp_enqueue_script( 'adsns_admin_notice_js', plugins_url( 'js/admin-notice.js' , __FILE__ ), array( 'jquery' ), $this->adsns_plugin_info["Version"] );
		}

		/* Stylesheets for ads */
		function adsns_head() {
			wp_enqueue_style( 'adsns_css', plugins_url( 'css/adsns.css', __FILE__ ), false, $this->adsns_plugin_info["Version"] );
		}

		/* Display notice in the main dashboard page / plugins page */
		function adsns_plugin_notice() {
			global $hook_suffix, $current_user;
			if ( 'plugins.php' == $hook_suffix ) {
				if ( isset( $this->adsns_options['first_install'] ) && strtotime( '-1 week' ) > $this->adsns_options['first_install'] )
					bws_plugin_banner( $this->adsns_plugin_info, 'adsns', 'google-adsense', '6057da63c4951b1a7b03296e54ed6d02', '80', '//ps.w.org/adsense-plugin/assets/icon-128x128.png' );

				bws_plugin_banner_to_settings( $this->adsns_plugin_info, 'adsns_settings', 'adsense-plugin', 'admin.php?page=adsense-plugin.php' );
			}

			if ( isset( $_GET['page'] ) && 'adsense-plugin.php' == $_GET['page'] ) {
				$this->adsns_plugin_suggest_feature_banner( $this->adsns_plugin_info, 'adsns_settings', 'adsense-plugin' );
			}

			/* No JS: on Form submit */
			if ( isset( $_POST['adsns_hide_banner_vi_welcome'] ) && ! defined( 'DOING_AJAX' ) ) {
				$this->adsns_hide_banner_vi_welcome();
			}

			if (
				current_user_can( 'manage_options' ) &&
				! get_user_meta( $current_user->ID, 'adsns_hide_banner_vi_welcome' )
			) {

				$vi_banner_email = get_option( 'admin_email' );
				$vi_banner_domain = $this->adsns_get_domain();
				$vi_banner_color = ( ! empty( $this->adsns_options['vi_banner_color'] ) ) ? $this->adsns_options['vi_banner_color'] : 'white';
				$vi_banner_link = array(
					'white' => array(
						'signup'	=> "https://www.vi.ai/publisher-registration/?utm_source=Wordpress&utm_medium=gas%20plugin&utm_campaign=white&aid=WP_gas&email=$vi_banner_email&domain=$vi_banner_domain",
						'logo'		=> 'https://www.vi.ai/?utm_source=Wordpress&utm_medium=gas%20plugin&utm_campaign=white',
						'faq'		=> 'https://www.vi.ai/publisherfaq/?utm_source=Wordpress&utm_medium=gas%20plugin&utm_campaign=white'
					),
					'black' => array(
						'signup'	=> "https://www.vi.ai/publisher-registration/?utm_source=Wordpress&utm_medium=gas%20plugin&utm_campaign=black&aid=WP_gas&email=$vi_banner_email&domain=$vi_banner_domain",
						'logo'		=> 'https://www.vi.ai/?utm_source=Wordpress&utm_medium=gas%20plugin&utm_campaign=black',
						'faq'		=> 'https://www.vi.ai/publisherfaq/?utm_source=Wordpress&utm_medium=gas%20plugin&utm_campaign=black'
					)
				); ?>
				<div class="updated adsns_banner adsns_banner_vi_welcome adsns_banner_<?php echo $vi_banner_color; ?>">
					<div class="adsns_banner_content">
						<div class="adsns_banner_logo">
							<a href="<?php echo $vi_banner_link[ $vi_banner_color ]['logo']; ?>" class="adsns_banner_logo_link" target="_blank">
								<img src="<?php echo plugins_url( "images/vi_logo_{$vi_banner_color}.svg", __FILE__ ); ?>" alt="video intelligence" title="video intelligence" />
							</a>
						</div>
						<div class="adsns_banner_text">
							<div class="adsns_banner_text_row"><?php
							printf(
								__( 'This update features vi stories from %s. This video player will supply both video content and video advertising.', 'adsense-plugin' ),
								'<strong>video intelligence</strong>'
							); ?></div>
							<div class="adsns_banner_text_row"><?php
							printf(
								__( 'To begin earning, visit the BestWebSoft plugin page, %s to %s and %s! Read the %s.', 'adsense-plugin' ),
								sprintf( '<a href="%s" class="adsns_banner_link adsns_banner_link_underline adsns_banner_link_large adsns_banner_link_strong" target="_blank">%s</a>', $vi_banner_link[ $vi_banner_color ]['signup'], __( 'sign up', 'adsense-plugin' ) ),
								'<strong>vi stories</strong>',
								sprintf( '<strong>%s</strong>', __( 'place the ad live now', 'adsense-plugin' ) ),
								sprintf( '<a href="%s" class="adsns_banner_link adsns_banner_link_underline" target="_blank">%s</a>', $vi_banner_link[ $vi_banner_color ]['faq'], __( 'FAQ', 'adsense-plugin' ) )
							); ?></div>
						</div>
					</div>
					<form class="adsns_banner_form" action="" method="post">
						<input type="hidden" id="adsns_settings_nonce" name="adsns_settings_nonce" value="<?php echo wp_create_nonce( 'adsns-settings-nonce' ); ?>">
						<input type="hidden" id="adsns_hide_banner_vi_welcome" name="adsns_hide_banner_vi_welcome" value="1" />
						<button class="notice-dismiss adsns_banner_close" title="<?php _e( 'Close notice', 'adsense-plugin' ); ?>"></button>
					</form>
				</div>
			<?php }
		}

		/* Display Suggest Feature bunner (moved from BWS_Menu) */
		function adsns_plugin_suggest_feature_banner( $plugin_info, $plugin_options_name, $banner_url_or_slug ) {
			$is_network_admin = is_network_admin();

			$plugin_options = $is_network_admin ? get_site_option( $plugin_options_name ) : get_option( $plugin_options_name );

			if ( isset( $plugin_options['display_suggest_feature_banner'] ) && 0 == $plugin_options['display_suggest_feature_banner'] )
				return;

			if ( ! isset( $plugin_options['first_install'] ) ) {
				$plugin_options['first_install'] = strtotime( "now" );
				$update_option = $return = true;
			} elseif ( strtotime( '-2 week' ) < $plugin_options['first_install'] ) {
				$return = true;
			}

			if ( ! isset( $plugin_options['go_settings_counter'] ) ) {
				$plugin_options['go_settings_counter'] = 1;
				$update_option = $return = true;
			} elseif ( 20 > $plugin_options['go_settings_counter'] ) {
				$plugin_options['go_settings_counter'] = $plugin_options['go_settings_counter'] + 1;
				$update_option = $return = true;
			}

			if ( isset( $update_option ) ) {
				if ( $is_network_admin )
					update_site_option( $plugin_options_name, $plugin_options );
				else
					update_option( $plugin_options_name, $plugin_options );
			}

			if ( isset( $return ) )
				return;

			if ( isset( $_POST['bws_hide_suggest_feature_banner_' . $plugin_options_name ] ) && check_admin_referer( $plugin_info['Name'], 'bws_settings_nonce_name' )  ) {
				$plugin_options['display_suggest_feature_banner'] = 0;
				if ( $is_network_admin )
					update_site_option( $plugin_options_name, $plugin_options );
				else
					update_option( $plugin_options_name, $plugin_options );
				return;
			}

			if ( false == strrpos( $banner_url_or_slug, '/' ) ) {
				$banner_url_or_slug = '//ps.w.org/' . $banner_url_or_slug . '/assets/icon-128x128.png';
			} ?>
			<div class="updated" style="padding: 0; margin: 0; border: none; background: none;">
				<div class="bws_banner_on_plugin_page bws_suggest_feature_banner">
					<div class="icon">
						<img title="" src="<?php echo esc_attr( $banner_url_or_slug ); ?>" alt="" />
					</div>
					<div class="text">
						<strong><?php printf( __( 'Thank you for choosing %s plugin!', 'adsense-plugin' ), $plugin_info['Name'] ); ?></strong><br />
						<?php _e( "If you have a feature, suggestion or idea you'd like to see in the plugin, we'd love to hear about it!", 'adsense-plugin' ); ?>
						<a href="mailto:support@gasplugin.com"><?php _e( 'Suggest a Feature', 'adsense-plugin' ); ?></a>
					</div>
					<form action="" method="post">
						<button class="notice-dismiss bws_hide_settings_notice" title="<?php _e( 'Close notice', 'adsense-plugin' ); ?>"></button>
						<input type="hidden" name="bws_hide_suggest_feature_banner_<?php echo $plugin_options_name; ?>" value="hide" />
						<?php wp_nonce_field( $plugin_info['Name'], 'bws_settings_nonce_name' ); ?>
					</form>
				</div>
			</div>
		<?php }

		/* Write user metadata to hide notification about cooperation start on "cross" button click */
		function adsns_hide_banner_vi_welcome() {
			global $current_user;

			$update = false;
			if (
				isset( $_POST['adsns_settings_nonce'] ) &&
				!! wp_verify_nonce( $_POST['adsns_settings_nonce'], 'adsns-settings-nonce' )
			) {
				update_user_meta( $current_user->ID, 'adsns_hide_banner_vi_welcome', 1 );
				$update = true;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX && $update ) {
				echo "1";
				wp_die();
			}
		}

		/*
		*displays AdSense in widget
		*@return array()
		*/
		function adsns_widget_display() {
			$title = $this->adsns_options['widget_title'];
			if ( ! empty( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ]['widget'] ) ) {
				$adsns_ad_unit_id = $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ]['widget'][0]['id'];
				$adsns_ad_unit_code = htmlspecialchars_decode( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ]['widget'][0]['code'] );
				printf( '<aside class="widget widget-container adsns_widget"><h1 class="widget-title">%s</h1><div id="%s" class="ads ads_widget">%s</div></aside>', $title, $adsns_ad_unit_id, $adsns_ad_unit_code );
			}
		}

		/*
		*Register widget for use in sidebars.
		*Registers widget control callback for customizing options
		*/
		function adsns_register_widget() {
			if ( isset( $this->adsns_options['publisher_id'] ) && isset( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ]['widget'] ) && count( $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ]['widget'] ) > 0 ) {
				$adsns_widget_positions = array(
					'static' => __( 'Static', 'adsense-plugin' ),
					'fixed'  => __( 'Fixed', 'adsense-plugin' ),
				);
				$adsns_widget = $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ]['widget'][0];
				$adsns_id = substr( strstr( $adsns_widget['id'], ':' ), 1 );
				$adsns_widget_position = isset( $adsns_widget['position'] ) ? $adsns_widget['position'] : 'static';
				if ( $adsns_widget_position != 'static' ) {
					$adsns_widget_position = $this->adsns_options['adunits'][ $this->adsns_options['publisher_id'] ]['widget'][0]['position'] = 'static';
					update_option( 'adsns_settings', $this->adsns_options );
				}
				wp_register_sidebar_widget(
					'adsns_widget', /* Unique widget id */
					sprintf( 'AdSense: ID: %s, %s', $adsns_id, $adsns_widget_positions[ $adsns_widget_position ] ),
					array( $this, 'adsns_widget_display' ), /* Callback function */
					array( 'description' => sprintf( '%s ID: %s, %s', __( 'Widget displays Google AdSense.', 'adsense-plugin' ), $adsns_id, $adsns_widget_positions[ $adsns_widget_position ] ) ) /* Options */
				);
				wp_register_widget_control(
					'adsns_widget', /* Unique widget id */
					sprintf( 'AdSense: ID: %s, %s', $adsns_id, $adsns_widget_positions[ $adsns_widget_position ] ),
					array( $this, 'adsns_widget_control' ) /* Callback function */
				);
			}
		}

		/*
		*Registers widget control callback for customizing options
		*@return array
		*/
		function adsns_widget_control() {
			if ( isset( $_POST["adsns-widget-submit"] ) ) {
				$this->adsns_options['widget_title'] = strip_tags( stripslashes( $_POST["adsns-widget-title"] ) );
				update_option( 'adsns_settings', $this->adsns_options );
			}
			$title = isset( $this->adsns_options['widget_title'] ) ? $this->adsns_options['widget_title'] : '' ;
			printf( '<p><label for="adsns-widget-title">%s<input class="widefat" id="adsns-widget-title" name="adsns-widget-title" type="text" value="%s" /></label></p><input type="hidden" id="adsns-widget-submit" name="adsns-widget-submit" value="1" />', __( 'Title', 'adsense-plugin' ), $title ); ?>
			<p>
				<?php printf( '<strong>%s</strong> %s', __( 'Please note:', 'adsense-plugin' ), sprintf( '<a href="admin.php?page=adsense-plugin.php&tab=widget" target="_blank">%s</a>', __( "Select ad block to display in the widget you can on the plugin settings page in the 'Widget' tab.", 'adsense-plugin' ) ) ); ?>
			</p>
		<?php }

		/* Add a link for settings page */
		function adsns_plugin_action_links( $links, $file ) {
			if ( ! is_network_admin() && ! is_plugin_active( 'adsense-pro/adsense-pro.php' ) ) {
				if ( $file == 'adsense-plugin/adsense-plugin.php' ) {
					$settings_link = '<a href="admin.php?page=adsense-plugin.php">' . __( 'Settings', 'adsense-plugin' ) . '</a>';
					array_unshift( $links, $settings_link );
				}
			}
			return $links;
		}

		function adsns_register_plugin_links( $links, $file ) {
			if ( $file == 'adsense-plugin/adsense-plugin.php' ) {
				if ( ! is_network_admin() )
					$links[]	=	'<a href="admin.php?page=adsense-plugin.php">' . __( 'Settings', 'adsense-plugin' ) . '</a>';
				$links[]	=	'<a href="https://support.bestwebsoft.com/hc/en-us/sections/200538919" target="_blank" target="_blank">' . __( 'FAQ', 'adsense-plugin' ) . '</a>';
				$links[]	=	'<a href="mailto:support@gasplugin.com">' . __( 'Support', 'adsense-plugin' ) . '</a>';
			}
			return $links;
		}

		/* Display Help Tab (moved from BWS_Menu) */
		function adsns_add_tabs() {
			$content = sprintf( '<p>%s %s</p>',
				__( 'Have a problem? Contact us', 'adsense-plugin' ),
				'<a href="mailto:support@gasplugin.com">support@gasplugin.com</a>'
			);

			$screen = get_current_screen();

			$screen->add_help_tab(
				array(
					'id'      => 'adsns_help_tab',
					'title'   => __( 'FAQ', 'adsense-plugin' ),
					'content' => $content
				)
			);

			$screen->set_help_sidebar(
				'<p><strong>' . __( 'For more information:', 'adsense-plugin' ) . '</strong></p>' .
				'<p><a href="https://drive.google.com/folderview?id=0B5l8lO-CaKt9VGh0a09vUjNFNjA&usp=sharing#list" target="_blank">' . __( 'Documentation', 'adsense-plugin' ) . '</a></p>' .
				'<p><a href="http://www.youtube.com/user/bestwebsoft/playlists?flow=grid&sort=da&view=1" target="_blank">' . __( 'Video Instructions', 'adsense-plugin' ) . '</a></p>' .
				'<p><a href="mailto:support@gasplugin.com">' . __( 'Contact us', 'adsense-plugin' ) . '</a></p>'

			);
		}

		function adsns_loop_start( $content ) {
			global $wp_query;
			if ( is_main_query() && $content === $wp_query ) {
				$this->adsns_is_main_query = true;
			}
		}

		function adsns_loop_end( $content ) {
			$this->adsns_is_main_query = false;
		}

		function adsns_body_classes( $classes ) {
			global $wp_version;

			if ( version_compare( $wp_version, '4.1', '<' ) ) {
				$classes .= ' adsns_old_wp';
			}

			return $classes;
		}

	} /* Class */
}

if ( ! class_exists( 'Adsns_List_Table' ) ) {

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
	}

	class Adsns_List_Table extends WP_List_Table {

		public $adsns_table_data, $adsns_table_adunits, $adsns_table_area, $adsns_adunit_positions, $adsns_adunit_positions_pro, $adsns_vi_publisher_id, $adsns_vi_token;
		private $include_inactive_ads, $adsns_options, $item_counter;

		function __construct( $options ) {
			$this->adsns_options = $options;
			$this->include_inactive_ads = $this->adsns_options['include_inactive_ads'];
			$this->item_counter = 0;
			parent::__construct( array(
				'singular'  => __( 'item', 'adsense-plugin' ),
				'plural'    => __( 'items', 'adsense-plugin' ),
				'ajax'      => false,
				)
			);
		}

		function get_table_classes() {
			return array( 'adsns-list-table', 'widefat', 'fixed', 'striped', $this->_args['plural'] );
		}

		function get_columns() {
			$columns = array(
				'cb'	   => __( 'Display', 'adsense-plugin' ),
				'name'     => __( 'Name', 'adsense-plugin' ),
				'code'     => __( 'Id', 'adsense-plugin' ),
				'summary'  => __( 'Type / Size', 'adsense-plugin' ),
				'status'   => __( 'Status', 'adsense-plugin' ),
				'position' => __( 'Position', 'adsense-plugin' )
			);
			if ( ! $this->adsns_adunit_positions ) {
				unset( $columns['position'] );
			}
			return $columns;
		}

		function usort_reorder( $a, $b ) {
			$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'name';
			$order = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'asc';
			$result = strcasecmp( $a[$orderby], $b[$orderby] );
			return ( $order === 'asc' ) ? $result : -$result;
		}

		function get_sortable_columns() {
			$sortable_columns = array(
				'name'    => array( 'name',false ),
				'code'    => array( 'code',false ),
				'summary' => array( 'summary', false ),
				'status'  => array( 'status', false )
			);
			return $sortable_columns;
		}

		/**
		 * Add necessary css classes depending on item status
		 * @param     array     $item        The current item data.
		 * @return    void
		 */
		function single_row( $item ) {
			$row_class = 'adsns_table_row';
			$row_class .= isset( $item['status_value'] ) && 'INACTIVE' == $item['status_value'] ? ' adsns_inactive' : '';
			if ( '1' != $this->include_inactive_ads ) {
				if ( isset( $item['status_value'] ) && 'INACTIVE' != $item['status_value'] ) {
					if ( $this->item_counter%2 == 0 ) {
						$row_class .= ( '' != $row_class ) ? ' adsns_table_row_odd' : '';
					}
					$this->item_counter++;
				} elseif ( isset( $item['status_value'] ) && 'INACTIVE' == $item['status_value'] ) {
						$row_class .= ( '' != $row_class ) ? ' hidden' : '';
				}
			} else {
				if ( $this->item_counter%2 == 0 ) {
					$row_class .= ( '' != $row_class ) ? ' adsns_table_row_odd' : '';
				}
				$this->item_counter++;
			}

			$row_class = ( '' != $row_class ) ? ' class="' . $row_class . '"' : '';

			echo "<tr{$row_class}>";
				$this->single_row_columns( $item );
			echo '</tr>';
		}

		function prepare_items() {
			global $adsns_table_rows;
			$columns = $this->get_columns();
			$hidden = array();
			$sortable = $this->get_sortable_columns();
			$primary = 'name';
			$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

			$vi_story_tbl_data = NULL;
			if ( array_key_exists( 'vi_story', $this->adsns_table_data ) ) {
				$vi_story_tbl_data = $this->adsns_table_data['vi_story'];
				unset( $this->adsns_table_data['vi_story'] );
			}

			usort( $this->adsns_table_data, array( &$this, 'usort_reorder' ) );

			if ( $vi_story_tbl_data && $this->adsns_vi_token ) {
				array_unshift( $this->adsns_table_data, $vi_story_tbl_data );
			}

			$this->items = $this->adsns_table_data;
		}

		function column_default( $item, $column_name ) {
			switch( $column_name ) {
				case 'cb':
				case 'name':
				case 'code':
				case 'summary':
				case 'status':
				case 'position':
					return $item[ $column_name ];
			default:
				return print_r( $item, true );
			}
		}

		function column_cb( $item ) {
			if ( $item['id'] != 'vi_story' ) {
				return sprintf( '<input class="adsns_adunit_ids" type="checkbox" name="adsns_adunit_ids[]" value="%s" %s/>', $item['id'], ( array_key_exists( $item['id'], $this->adsns_table_adunits ) ) ? 'checked="checked"' : '' );
			} else {
				return sprintf( '<input class="adsns_adunit_ids" type="checkbox" name="adsns_vi_id" value="%s" %s/>', $item['id'], ( isset( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] ) && $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] === true ) ? 'checked="checked"' : '' );
			}
		}

		function column_position( $item ) {
			$adsns_adunit_positions = is_array( $this->adsns_adunit_positions ) ? $this->adsns_adunit_positions : array();

			if ( $item['id'] != 'vi_story' ) {
				$disabled = ( ! array_key_exists( $item['id'], $this->adsns_table_adunits ) ) ? 'disabled="disabled"' : '';

				$adsns_adunit_positions_pro = is_array( $this->adsns_adunit_positions_pro ) ? $this->adsns_adunit_positions_pro : array();
				$adsns_position = $adsns_position_pro = '';
				foreach ( $adsns_adunit_positions as $value => $name ) {
					$adsns_position .= sprintf( '<option value="%s" %s>%s</option>', $value, ( array_key_exists( $item['id'], $this->adsns_table_adunits ) && $this->adsns_table_adunits[ $item['id'] ] == $value ) ? 'selected="selected"' : '', $name );
				}
				if ( $adsns_adunit_positions_pro ) {
					foreach ( $adsns_adunit_positions_pro as $value_pro => $name_pro ) {
						$adsns_position_pro .= sprintf( '<optgroup label="%s"></optgroup>', $name_pro );
					}
					$adsns_position .= $adsns_position_pro;
				}
				return sprintf(
					'<select class="adsns_adunit_position" name="adsns_adunit_position[%s]" %s>%s</select>',
					$item['id'],
					$disabled,
					$adsns_position
				);
			} else {
				$disabled = ( ! ( isset( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] ) && $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] === true ) ) ? 'disabled="disabled"' : '';
				$vi_story_position = '';
				foreach ( $adsns_adunit_positions as $value => $name ) {
					$vi_story_position .= sprintf( '<option value="%s" selected="selected">%s</option>', $value, $name );
					break;
				}

				return sprintf(
					'<select class="adsns_adunit_position" name="adsns_vi_position" %s>%s</select>', $disabled, $vi_story_position
				);
			}
		}
	}
}