<?php
/**
 * Display the content on the plugin settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'Adsns_Settings_Tabs' ) ) {
	/**
	 * Class for display the content on the plugin settings page
	 */
	class Adsns_Settings_Tabs extends Bws_Settings_Tabs {

		/**
		 * Google client info
		 *
		 * @var object
		 */
		private $adsns_client;
		/**
		 * Google Service info
		 *
		 * @var object
		 */
		private $adsns_service;

		/**
		 * Constructor
		 *
		 * @access public
		 *
		 * @see Bws_Settings_Tabs::__constructor() for more information in default arguments.
		 *
		 * @param string $plugin_basename Plugin basename.
		 */
		public function __construct( $plugin_basename ) {
			global $adsns_options, $adsns_plugin_info;

			$tabs = array(
				'settings'    => array( 'label' => __( 'Settings', 'bws-adsense-plugin' ) ),
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
		 * @param  array $save_results Array with error\message\notice.
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

		/**
		 * Save all options
		 */
		public function save_options() {
			global $wp_filesystem;
			$message = '';
			$notice  = '';
			$error   = '';

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

		/**
		 * Display settings tab
		 */
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

		/**
		 * Display Pro block
		 */
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
