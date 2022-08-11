<?php
/**
 * Display the content on the plugin settings page
 */

if ( ! class_exists( 'Adsns_Settings_Tabs' ) ) {
	class Adsns_Settings_Tabs extends Bws_Settings_Tabs {

		private $adsns_client;
		private $adsns_service;
		private $vi_revenue;

		/**
		 * Constructor
		 *
		 * @access public
		 *
		 * @see Bws_Settings_Tabs::__constructor() for more information in default arguments.
		 *
		 * @param string $plugin_basename
		 */
		public function __construct( $plugin_basename ) {
			global $adsns_options, $adsns_plugin_info;

			$tabs = array(
				'settings'    => array( 'label' => __( 'Settings', 'bws-adsense-plugin' ) ),
				'settings_vi' => array( 'label' => __( 'VI Intelligence', 'bws-adsense-plugin' ) ),
				'misc'        => array( 'label' => __( 'Misc', 'bws-adsense-plugin' ) ),
				'custom_code' => array( 'label' => __( 'Custom Code', 'bws-adsense-plugin' ) ),
				'license'     => array( 'label' => __( 'License Key', 'bws-adsense-plugin' ) ),
			);

			parent::__construct(
				array(
					'plugin_basename'    => $plugin_basename,
					'plugins_info'       => $adsns_plugin_info,
					'prefix'             => 'adsns',
					'default_options'    => adsns_default_options(),
					'options'            => $adsns_options,
					'is_network_options' => is_network_admin(),
					'tabs'               => $tabs,
					'wp_slug'            => '',
					'pro_page'           => 'admin.php?page=adsense-pro.php',
					'bws_license_plugin' => 'adsense-pro/adsense-pro.php',
					'link_key'           => '2887beb5e9d5e26aebe6b7de9152ad1f',
					'link_pn'            => '80',
				)
			);

			$this->vi_revenue = adsns_vi_get_revenue();

			if ( file_exists( dirname( __FILE__ ) . '/../google_api/client_secrets.json' ) ) {
				$this->adsns_client  = adsns_client();
				$this->adsns_service = adsns_service();
				if ( isset( $this->options['authorization_code'] ) ) {
					$this->adsns_client->fetchAccessTokenWithRefreshToken( $this->options['authorization_code'] );
				}
			}

			add_filter( get_parent_class( $this ) . '_display_custom_messages', array( $this, 'display_custom_messages' ) );
		}

		/**
		 * Display custom error\message\notice
		 *
		 * @access public
		 * @param  $save_results - array with error\message\notice
		 * @return void
		 */
		public function display_custom_messages( $save_results ) {
			global $adsns_options;
			if ( empty( $adsns_options ) ) {
				$adsns_options = get_option( 'adsns_options' );
			}
			if ( isset( $this->options['authorization_code'] ) && ! empty( $this->adsns_client ) ) {
				$this->adsns_client->fetchAccessTokenWithRefreshToken( $this->options['authorization_code'] );
			}

			if ( isset( $this->adsns_client ) && $this->adsns_client->getAccessToken() && empty( $this->options['publisher_id'] ) && ! isset( $_POST['adsns_logout'] ) ) {
				$adsns_adsense          = new Google_Service_AdSense( $this->adsns_client );
				$adsns_adsense_accounts = $adsns_adsense->accounts;
				try {
					$adsns_list_accounts = $adsns_adsense_accounts->listAccounts()->getAccounts();
					if ( ! empty( $adsns_list_accounts ) ) {
						$adsns_options['publisher_id'] = $adsns_list_accounts[0]['name'];
						$this->options                 = $adsns_options;

						update_option( 'adsns_options', $adsns_options );
					}
				} catch ( Google_Service_Exception $e ) {
					$adsns_err = $e->getErrors(); ?>
					<div class="error below-h2">
						<p>
						<?php
						printf(
							'<strong>%s</strong> %s %s',
							esc_html__( 'Account Error:', 'bws-adsense-plugin' ),
							esc_html( $adsns_err[0]['message'] ),
							sprintf( esc_html__( 'Create account in %s', 'bws-adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
						);
						?>
						</p>
					</div>
				<?php } catch ( Exception $e ) { ?>
					<div class="error below-h2">
						<p><strong><?php esc_html_e( 'Error', 'bws-adsense-plugin' ); ?>:</strong> <?php echo esc_html( $e->getMessage() ); ?></p>
					</div>
					<?php
				}
			}
		}

		public function save_options() {
			global $wp_filesystem;
			$message = $notice = $error = '';

			if ( isset( $_POST['adsns_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adsns_nonce_field'] ) ), 'adsns_action' ) ) {
				if ( isset( $_POST['adsns_remove'] ) ) {
					require_once ABSPATH . '/wp-admin/includes/file.php';
					WP_Filesystem();
					$filename = dirname( __FILE__ ) . '/../google_api/client_secrets.json';
					if ( file_exists( $filename ) ) {
						$wp_filesystem->delete( $filename );
					}
					if ( ! file_exists( $filename ) ) {
						$this->adsns_client  = null;
						$this->adsns_service = null;
						$message             = __( 'Google AdSense data has been removed from site', 'bws-adsense-plugin' );
					} else {
						$error = __( 'The error was occured. Google AdSense data has not been removed from site', 'bws-adsense-plugin' );
					}
				} elseif ( isset( $_POST['adsns_logout'] ) ) {
					unset( $this->options['authorization_code'], $this->options['publisher_id'] );
					$message = __( 'You are logged out from Google Account', 'bws-adsense-plugin' );
				} else {
					if ( isset( $_POST['adsns_client_id'] ) && isset( $_POST['adsns_client_secret'] ) ) {
						$adsns_client_id     = sanitize_text_field( wp_unslash( $_POST['adsns_client_id'] ) );
						$adsns_client_secret = sanitize_text_field( wp_unslash( $_POST['adsns_client_secret'] ) );
						if ( ! empty( $adsns_client_id ) && ! empty( $adsns_client_secret ) ) {
							require_once ABSPATH . '/wp-admin/includes/file.php';
							WP_Filesystem();
							$contents = '{' . PHP_EOL . '  "web": {' . PHP_EOL . '	"client_id": "' . $adsns_client_id . '",' . PHP_EOL . '	"client_secret": "' . $adsns_client_secret . '",' . PHP_EOL . '	"redirect_uris": ["' . admin_url( 'admin.php?page=bws-adsense.php' ) . '"]' . PHP_EOL . '  }' . PHP_EOL . '}';
							$filename = dirname( __FILE__ ) . '/../google_api/client_secrets.json';
							$wp_filesystem->put_contents( $filename, $contents );
							$this->adsns_client  = adsns_client();
							$this->adsns_service = adsns_service();
						}
					}

					if ( isset( $this->options['publisher_id'] ) ) {
						$this->options['include_inactive_ads'] = ( isset( $_POST['adsns_include_inactive_id'] ) ) ? 1 : 0;
					}
				}

				update_option( 'adsns_options', $this->options );
			} else {
				$error = __( 'Sorry, your nonce did not verify.', 'bws-adsense-plugin' );
			}

			if ( '' === $message ) {
				$message = __( 'Settings saved.', 'bws-adsense-plugin' );
			}

			return compact( 'message', 'notice', 'error' );
		}

		public function tab_settings() {
			?>
			<h3 class="bws_tab_label"><?php esc_html_e( 'General Settings', 'bws-adsense-plugin' ); ?></h3>
			<?php $this->help_phrase(); ?>
			<hr>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Remote work with Google AdSense', 'bws-adsense-plugin' ); ?></th>
					<td>
						<?php if ( ! isset( $_POST['adsns_logout'] ) && isset( $this->adsns_client ) && $this->adsns_client->getAccessToken() ) { ?>
							<div id="adsns_logout_buttons">
								<input class="button-secondary" name="adsns_logout" type="submit" value="<?php esc_html_e( 'Log out from Google AdSense', 'bws-adsense-plugin' ); ?>" />
							</div>
							<?php
						} else {
							if ( file_exists( dirname( __FILE__ ) . '/../google_api/client_secrets.json' ) ) {
								$this->adsns_client->setApprovalPrompt( 'force' );
								$adsns_auth_url = $this->adsns_client->createAuthUrl();
								?>
								<div id="adsns_authorization_notice">
										<?php esc_html_e( 'Please authorize via your Google Account to manage ad blocks.', 'bws-adsense-plugin' ); ?>
								</div>
									<a id="adsns_authorization_button" class="button-primary" href="<?php echo esc_url( $adsns_auth_url ); ?>"><?php esc_html_e( 'Login To Google Adsense', 'bws-adsense-plugin' ); ?></a>
									<div id="adsns_remove_buttons">
										<input class="button-secondary" name="adsns_remove" type="submit" value="<?php esc_html_e( 'Remove AdSense data from site', 'bws-adsense-plugin' ); ?>" />
								</div>
							<?php } else { ?>
								<div id="adsns_authorization_notice">
								<?php esc_html_e( 'Please enter your Client ID and Client Secret from your Google Account to work with Google AdSense API.', 'bws-adsense-plugin' ); ?> <a href="https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid"><?php esc_html_e( 'Read more', 'bws-adsense-plugin' ); ?></a>
							</div>
							<div id="adsns_api_form">
								<table class="form-table">
									<tr>
										<th>
											<?php esc_html_e( 'Client ID', 'bws-adsense-plugin' ); ?> <br />
										</th>
										<td>
											<label>
												<input id="adsns_client_id" class="bws_no_bind_notice regular-text" name="adsns_client_id" type="text" autocomplete="off" maxlength="150" />
											</label>
										</td>
									</tr>
									<tr>
										<th>
											<?php esc_html_e( 'Client Secret', 'bws-adsense-plugin' ); ?> <br />
										</th>
										<td>
											<label>
												<input id="adsns_client_secret" class="bws_no_bind_notice regular-text" name="adsns_client_secret" type="text" autocomplete="off" maxlength="150" />
											</label>
										</td>
									</tr>
								</table>
							</div>
								<?php
							}
						}
						?>
					</td>
				</tr>
				<?php if ( isset( $this->options['publisher_id'] ) && ! empty( $this->options['publisher_id'] ) && ! empty( $this->adsns_client ) ) { ?>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Your Publisher ID', 'bws-adsense-plugin' ); ?></th>
						<td>
							<span id="adsns_publisher_id">
							<?php
							$publisher_id_array = explode( '/', $this->options['publisher_id'] );
							echo esc_html( end( $publisher_id_array ) );
							?>
							</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Show idle ad blocks', 'bws-adsense-plugin' ); ?></th>
						<td>
							<input id="adsns_include_inactive_id" type="checkbox" name="adsns_include_inactive_id" <?php checked( $this->options['include_inactive_ads'], 1 ); ?> value="1" />
						</td>
					</tr>
					<?php if ( ! $this->hide_pro_tabs ) { ?>
						</table>						
						<div class="bws_pro_version_bloc">
							<div class="bws_pro_version_table_bloc">
								<button type="submit" name="bws_hide_premium_options" class="notice-dismiss bws_hide_premium_options" title="<?php esc_html_e( 'Close', 'bws-adsense-plugin' ); ?>"></button>
								<div class="bws_table_bg"></div>
								<table class="form-table bws_pro_version">									
									<tr valign="top">
										<th scope="row"><?php esc_html_e( 'Add HTML code in head', 'bws-adsense-plugin' ); ?></th>
										<td>
											<textarea disabled="disabled" name="adsns_add_html" class="widefat" rows="8" style="font-family:Courier New;"></textarea>
											<p class="bws_info"><?php esc_html_e( 'Paste the code you provided when you created your AdSense account. This will add your code between the <head> and </head> tags.', 'bws-adsense-plugin' ); ?></p>
										</td>
									</tr>
								</table>
							</div>
							<?php $this->bws_pro_block_links(); ?>
						</div>
						<table class="form-table">
					<?php } ?>
				<?php } ?>
			</table>			
			<?php
			wp_nonce_field( 'adsns_action', 'adsns_nonce_field' );
		}

		public function tab_settings_vi() {
			global $adsns_vi_token, $adsns_vi_settings_api;
			?>
			<h3 class="bws_tab_label"><?php esc_html_e( 'VI Intelligence Settings', 'bws-adsense-plugin' ); ?></h3>
			<?php $this->help_phrase(); ?>
			<hr>
			<div class="bws_tab_sub_label"><?php esc_html_e( 'Overview', 'bws-adsense-plugin' ); ?></div>
			<div class="adsns_vi_widget_header">
				<div class="adsns_vi_widget_header_content">
					<div class="adsns_vi_widget_logo">
						<img src="<?php echo esc_url( plugins_url( 'images/vi_logo_white.svg', dirname( __FILE__ ) ) ); ?>" alt="video intelligence" title="video intelligence" />
					</div>
					<?php if ( ! $adsns_vi_token && ! $this->vi_revenue ) { ?>
						<div class="adsns_vi_widget_title"><?php esc_html_e( 'Video content and video advertising – powered by video intelligence', 'bws-adsense-plugin' ); ?></div>
					<?php } else { ?>
						<div class="adsns_vi_widget_title"><?php esc_html_e( 'vi stories - video content and video advertising', 'bws-adsense-plugin' ); ?></div>
					<?php } ?>
				</div>
			</div>
			<div class="adsns_vi_widget_body">
				<?php if ( ! $adsns_vi_token && ! $this->vi_revenue ) { ?>
					<p>
						<?php
						esc_html_e( 'Advertisers pay more for video advertising when it\'s matched with video content. This new video player will insert both on your page. It increases time on site, and commands a higher CPM than display advertising.', 'bws-adsense-plugin' );
						?>
					</p>
					<p>
						<?php esc_html_e( 'You\'ll see video content that is matched to your sites keywords straight away. A few days after activation you\'ll begin to receive revenue from advertising served before this video content.', 'bws-adsense-plugin' ); ?>
					</p>
					<ul>
						<li><?php esc_html_e( 'The set up takes only a few minutes', 'bws-adsense-plugin' ); ?></li>
						<li><?php esc_html_e( 'Up to 10x higher CPM than traditional display advertising', 'bws-adsense-plugin' ); ?></li>
						<li><?php esc_html_e( 'Users spend longer on your site thanks to professional video content', 'bws-adsense-plugin' ); ?></li>
						<li><?php esc_html_e( 'The video player is customizable to match your site', 'bws-adsense-plugin' ); ?></li>
					</ul>
					<?php if ( ! empty( $adsns_vi_settings_api['demoPageURL'] ) ) { ?>
						<p>
							<?php printf( esc_html__( 'Watch a %s of how vi stories work.', 'bws-adsense-plugin' ), sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $adsns_vi_settings_api['demoPageURL'] ), esc_html__( 'demo', 'bws-adsense-plugin' ) ) ); ?>
						</p>
						<?php
					}
				} else {
					if ( ! isset( $this->vi_revenue['netRevenue'] ) && ! isset( $this->vi_revenue['mtdReport'] ) ) {
						?>
						<p class="adsns_revenue_api_error">
							<?php esc_html_e( 'There was an error processing your request.', 'bws-adsense-plugin' ); ?>
						</p>
						<p class="adsns_revenue_api_error">
							<?php esc_html_e( 'Please try again later.', 'bws-adsense-plugin' ); ?>
						</p>
					<?php } elseif ( ! isset( $this->vi_revenue['netRevenue'] ) && isset( $this->vi_revenue['mtdReport'] ) ) { ?>
						<p>
							<?php esc_html_e( 'Below you can see your current revenues.', 'bws-adsense-plugin' ); ?>
						</p>
						<p>
							<?php
							esc_html_e( 'Don’t see anything?', 'bws-adsense-plugin' );
							printf( ' <a href="https://support.bestwebsoft.com/hc/en-us/requests/new" target="_blank">%s</a>', esc_html__( 'Submit a request', 'bws-adsense-plugin' ) );
							?>
						</p>
					<?php } else { ?>
						<p>
							<?php esc_html_e( 'Below you can see your current revenues.', 'bws-adsense-plugin' ); ?>
						</p>
						<div class="adsns_vi_revenue_content">
							<div class="adsns_vi_revenue_earnings">
								<div class="adsns_vi_revenue_title adsns_vi_revenue_earnings_title">
									<span class="adsns_vi_revenue_title_icon dashicons dashicons-welcome-write-blog"></span><?php esc_html_e( 'Total earnings', 'bws-adsense-plugin' ); ?>
								</div>
								<div class="adsns_vi_revenue_earnings_value">$<?php echo esc_html( number_format( ( null !== $this->vi_revenue['netRevenue'] ? $this->vi_revenue['netRevenue'] : 0 ), 2, '.', ' ' ) ); ?></div>
							</div>
							<div class="adsns_vi_revenue_chart">
								<div class="adsns_vi_revenue_title adsns_vi_revenue_chart_title">
									<span class="adsns_vi_revenue_title_icon dashicons dashicons-chart-area"></span><?php esc_html_e( 'Chart', 'bws-adsense-plugin' ); ?>
								</div>
								<div class="adsns_vi_revenue_chart_canvas_wrapper">
									<canvas id="adsns_vi_revenue_chart_canvas" width="250" height="130"></canvas>
									<noscript>
										<div class="adsns_vi_revenue_chart_canvas_no_js"><?php esc_html_e( 'Please enable JavaScript.', 'bws-adsense-plugin' ); ?></div>
									</noscript>
								</div>
								<?php
								$this->vi_revenue_data = null !== $this->vi_revenue['mtdReport'] ? $this->vi_revenue['mtdReport'] : array();
								$vi_chart_data         = array(
									'labels' => array(),
									'data'   => array(),
								);

								foreach ( $this->vi_revenue_data as $data ) {
									$vi_chart_data['labels'][] = date_i18n( 'M d', strtotime( $data['date'] ) );
									$vi_chart_data['data'][]   = $data['revenue'];
								}

								if ( ! empty( $vi_chart_data ) ) {
									$script = "(function($) {
											$(document).ready( function() {
												var $vi_chart_data = " . wp_json_encode( $vi_chart_data ) . ";
												$('#adsns_vi_revenue_chart_canvas').trigger( 'displayWidgetChart', $vi_chart_data );
											} );
										})(jQuery);";

									wp_register_script( 'adsns_vi_revenue_chart_canvas', '' );
									wp_enqueue_script( 'adsns_vi_revenue_chart_canvas' );
									wp_add_inline_script( 'adsns_vi_revenue_chart_canvas', sprintf( $script ) );
								}
								?>
							</div>
							<div class="clear"></div>
						</div>
						<?php
					}
				}
				?>
				<div class="adsns_vi_widget_footer">
					<?php if ( ! $adsns_vi_token ) { ?>
						<p>
						<?php
						printf(
							esc_html__( 'By clicking Sign Up button you agree to send current domain, email and affiliate ID to %s.', 'bws-adsense-plugin' ),
							sprintf( '<span>%s</span>', esc_html__( 'video intelligence', 'bws-adsense-plugin' ) )
						);
						?>
						</p>
						<div>
							<a href="admin.php?page=bws-adsense.php&action=vi_login" id="adsns_vi_widget_button_login" class="button button-secondary adsns_vi_widget_button"><?php esc_html_e( 'Log In', 'bws-adsense-plugin' ); ?></a>
							<a href="https://www.vi.ai/publishers/#SignUpOpen" target="_blank" id="adsns_vi_widget_button_signup" class="button button-primary adsns_vi_widget_button"><?php esc_html_e( 'Sign Up', 'bws-adsense-plugin' ); ?></a>
						</div>
					<?php } else { ?>
						<div>
							<?php if ( ! empty( $adsns_vi_settings_api['dashboardURL'] ) ) { ?>
								<a href="<?php echo esc_url( $adsns_vi_settings_api['dashboardURL'] ); ?>" id="adsns_vi_widget_button_dashboard" class="button button-primary adsns_vi_widget_button" target="_blank"><?php esc_html_e( 'Publisher Dashboard', 'bws-adsense-plugin' ); ?></a>
							<?php } ?>
							<button id="adsns_vi_widget_button_log_out" class="button button-secondary adsns_vi_widget_button" name="adsns_vi_logout" type="submit"><?php esc_html_e( 'Log Out', 'bws-adsense-plugin' ); ?></button>
						</div>
					<?php } ?>
				</div>
			</div>
			<?php
			wp_nonce_field( 'adsns_action', 'adsns_nonce_field' );
		}

		public function bws_pro_block_links() {
			global $wp_version;
			?>
			<div class="bws_pro_version_tooltip">
				<a class="bws_button" href="<?php echo esc_url( 'https://bestwebsoft.com/products/wordpress/plugins/google-adsense/?k=' . esc_html( $this->link_key ) . '&amp;pn=' . esc_html( $this->link_pn ) . '&amp;v=' . esc_html( $this->plugins_info['Version'] ) . '&amp;wp_v=' . esc_html( $wp_version ) ); ?>" target="_blank" title="<?php echo esc_html( $this->plugins_info['Name'] ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'bestwebsoft' ); ?></a>
				<div class="clear"></div>
			</div>
			<?php
		}
	}
}
