<?php
/**
Plugin Name: AdS by BestWebSoft
Plugin URI: https://bestwebsoft.com/products/wordpress/plugins/google-adsense/
Description: Add Adsense ads to pages, posts, custom posts, search results, categories, tags, pages, and widgets.
Author: BestWebSoft
Text Domain: bws-adsense-plugin
Domain Path: /languages
Version: 1.54
Author URI: https://bestwebsoft.com/
License: GPLv2 or later
 */

/*
	© Copyright 2022  BestWebSoft ( support@bestwebsoft.com )

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! function_exists( 'adsns_add_admin_menu' ) ) {
	/** Add 'BWS Plugins' menu at the left side in administer panel */
	function adsns_add_admin_menu() {
		global $submenu, $wp_version, $adsns_plugin_info;

		add_menu_page(
			'AdS', /* $page_title */
			'AdS', /* $menu_title */
			'manage_options', /* $capability */
			'bws-adsense.php', /* $menu_slug */
			'adsns_settings_page', /* $callable_function */
			'none' /* $icon_url */
		);

		$settings = add_submenu_page(
			'bws-adsense.php', /* $parent_slug */
			'AdS', /* $page_title */
			__( 'Settings', 'bws-adsense-plugin' ), /* $menu_title */
			'manage_options', /* $capability */
			'bws-adsense.php', /* $menu_slug */
			'adsns_settings_page' /* $callable_function */
		);

		$ads = add_submenu_page(
			'bws-adsense.php', /* $parent_slug */
			'AdSense Ads', /* $page_title */
			__( 'AdS', 'bws-adsense-plugin' ), /* $menu_title */
			'manage_options', /* $capability */
			'adsense-list.php', /* $menu_slug */
			'adsns_list_page' /* $callable_function */
		);

		add_submenu_page(
			'bws-adsense.php', /* $parent_slug */
			'BWS Panel', /* $page_title */
			'BWS Panel', /* $menu_title */
			'manage_options', /* $capability */
			'adsns-bws-panel', /* $menu_slug */
			'bws_add_menu_render' /* $callable_function */
		);

		/* Add "Go Pro" submenu link */
		if ( isset( $submenu['bws-adsense.php'] ) ) {
			$submenu['bws-adsense.php'][] = array(
				'<span style="color:#d86463"> ' . __( 'Upgrade to Pro', 'bws-adsense-plugin' ) . '</span>',
				'manage_options',
				'https://bestwebsoft.com/products/wordpress/plugins/google-adsense/?k=2887beb5e9d5e26aebe6b7de9152ad1f&pn=80&v=' . $adsns_plugin_info['Version'] . '&wp_v=' . $wp_version,
			);
		}

		add_action( 'load-' . $settings, 'adsns_add_tabs' );
		add_action( 'load-' . $ads, 'adsns_add_tabs' );
	}
}

if ( ! function_exists( 'adsns_plugin_init' ) ) {
	/**
	 * Main init function
	 */
	function adsns_plugin_init() {
		global $adsns_plugin_info, $adsns_options;

		require_once dirname( __FILE__ ) . '/bws_menu/bws_include.php';
		bws_include_init( plugin_basename( __FILE__ ) );

		if ( empty( $adsns_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$adsns_plugin_info = get_plugin_data( __FILE__ );
		}

		/* Function check if plugin is compatible with current WP version */
		bws_wp_min_version_check( plugin_basename( __FILE__ ), $adsns_plugin_info, '4.5' );

		/* Call register settings function */
		if ( ! is_admin() || ( isset( $_GET['page'] ) && ( 'bws-adsense.php' === $_GET['page'] || 'adsense-list.php' === $_GET['page'] ) ) ) {
			adsns_activate();
		}

		if ( isset( $_GET['code'] ) ) {
			$client = adsns_client();
			$client->authenticate( wp_unslash( $_GET['code'] ) );
			/**
			 * Note that "getAccessToken" actually retrieves both the access and refresh
			 * tokens, assuming both are available.
			 */
			$token                               = $client->getAccessToken();
			$adsns_options['authorization_code'] = $token['refresh_token'];
			update_option( 'adsns_options', $adsns_options );
			echo '<script>if (window.opener != null && !window.opener.closed) { window.opener.location.reload(); } self.close(); </script>';
			exit;
		}
	}
}

if ( ! function_exists( 'adsns_plugin_admin_init' ) ) {
	/**
	 * Init for dashboard
	 */
	function adsns_plugin_admin_init() {
		global $bws_plugin_info, $pagenow, $adsns_options, $adsns_plugin_info;

		if ( isset( $_GET['page'] ) && ( 'bws-adsense.php' === $_GET['page'] || 'adsense-list.php' === $_GET['page'] ) ) {
			if ( ! session_id() ) {
				session_start();
			}
		}

		if ( empty( $bws_plugin_info ) ) {
			$bws_plugin_info = array(
				'id'      => '80',
				'version' => $adsns_plugin_info['Version'],
			);
		}

		if ( 'plugins.php' === $pagenow ) {
			/* Install the option defaults */
			if ( function_exists( 'bws_plugin_banner_go_pro' ) ) {
				adsns_activate();
				bws_plugin_banner_go_pro( $adsns_options, $adsns_plugin_info, 'adsns', 'google-adsense', '6057da63c4951b1a7b03296e54ed6d02', '80', 'bws-adsense-plugin' );
			}
		}
	}
}

if ( ! function_exists( 'adsns_localization' ) ) {
	/**
	 * Load textdomain
	 */
	function adsns_localization() {
		load_plugin_textdomain( 'bws-adsense-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}

if ( ! function_exists( 'adsns_activate' ) ) {
	/** Creating a default options for showing ads. Starts on plugin activation. */
	function adsns_activate() {
		global $adsns_plugin_info, $adsns_options;

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$adsns_plugin_info = get_plugin_data( dirname( __FILE__ ) . '/bws-adsense.php' );

		if ( ! get_option( 'adsns_options' ) ) {
			/**
			* Check if plugin has old plugin options (renamed for Adsns_Settings_Tabs)
			 *
			* @deprecated 1.50
			* @todo Remove function after 01.08.2020
			*/
			$old_option = get_option( 'adsns_settings' );
			if ( ! empty( $old_option ) ) {
				$options_defaults = $old_option;
				delete_option( 'adsns_settings' );
			} else { /* end todo */
				$options_defaults = adsns_default_options();
			}
			add_option( 'adsns_options', $options_defaults );
		}

		$adsns_options = get_option( 'adsns_options' );

		/* Array merge incase this version has added new options */
		if ( ! isset( $adsns_options['plugin_option_version'] ) || $adsns_options['plugin_option_version'] !== $adsns_plugin_info['Version'] ) {
			$options_defaults                            = adsns_default_options();
			$options_defaults['display_settings_notice'] = 0;
			$adsns_options                               = array_merge( $options_defaults, $adsns_options );
			$adsns_options['plugin_option_version']      = $adsns_plugin_info['Version'];
			update_option( 'adsns_options', $adsns_options );
		}
	}
}

if ( ! function_exists( 'adsns_default_options' ) ) {
	/**
	 * Default options for plugin
	 */
	function adsns_default_options() {
		global $adsns_plugin_info;

		$seconds = (int) gmdate( 's', strtotime( 'now' ) );

		$default_options = array(
			'plugin_option_version'   => $adsns_plugin_info['Version'],
			'display_settings_notice' => 1,
			'suggest_feature_banner'  => 1,

			'widget_title'            => '',
			'publisher_id'            => '',
			'include_inactive_ads'    => 1,
		);

		return $default_options;
	}
}

if ( ! function_exists( 'adsns_after_setup_theme' ) ) {
	/**
	 * Add filter for the_content and comment_id_fields
	 */
	function adsns_after_setup_theme() {
		global $adsns_options;

		if ( ! $adsns_options ) {
			adsns_activate();
		}

		add_filter( 'the_content', 'adsns_content' );
		add_filter( 'comment_id_fields', 'adsns_comments' );
	}
}

if ( ! function_exists( 'adsns_client' ) ) {
	/**
	 * Google Client init
	 *
	 * @return object $client Google client.
	 */
	function adsns_client() {
		global $adsns_plugin_info;

		require_once dirname( __FILE__ ) . '/google_api/vendor/autoload.php';

		$client = new Google_Client();
		$client->addScope( 'https://www.googleapis.com/auth/adsense.readonly' );
		$client->setAccessType( 'offline' );

		/* Be sure to replace the contents of client_secrets.json with your developer credentials. */
		$client->setAuthConfig( dirname( __FILE__ ) . '/google_api/client_secrets.json' );

		return $client;
	}
}

if ( ! function_exists( 'adsns_service' ) ) {
	/**
	 * Google Asense API
	 *
	 * @param object $client (Optional) Google client.
	 */
	function adsns_service( $client = null ) {
		if ( empty( $client ) ) {
			$client = adsns_client();
		}

		/* Create service */
		$service = new Google_Service_Adsense( $client );

		return $service;
	}
}


if ( ! function_exists( 'adsns_content' ) ) {
	/**
	 * Show ads on the home page / single page / post / custom post / categories page / tags page via Google AdSense API
	 *
	 * @param string $content Post content.
	 * @return string $content
	 */
	function adsns_content( $content ) {
		global $adsns_options, $adsns_content_count, $adsns_excerpt_count, $adsns_is_main_query;

		if ( $adsns_is_main_query && ! is_feed() && ( is_home() || is_front_page() || is_category() || is_tag() ) ) {
			$adsns_count    = empty( $adsns_count ) ? 0 : $adsns_count;

			if ( is_home() || is_front_page() ) {
				$adsns_area = 'home';
			}

			if ( is_category() || is_tag() ) {
				$adsns_area = 'categories+tags';
			}

			if ( ! empty( $adsns_options['publisher_id'] ) && isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ][ $adsns_count ] ) ) {

				$adsns_ad_unit          = $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ][ $adsns_count ];
				$adsns_ad_unit_id       = $adsns_ad_unit['id'];
				$adsns_ad_unit_position = $adsns_ad_unit['position'];
				$adsns_ad_unit_code     = htmlspecialchars_decode( $adsns_ad_unit['code'] );

				$adsns_count++;

				switch ( $adsns_ad_unit_position ) {
					case 'after':
						$adsns_ads = sprintf( '<div id="%s" class="ads ads_after">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
						$content   = $content . $adsns_ads;
						break;
					case 'before':
						$adsns_ads = sprintf( '<div id="%s" class="ads ads_before">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
						$content   = $adsns_ads . $content;
						break;
				}
			}

			return $content;
		}

		if ( $adsns_is_main_query && ! is_feed() && ( is_single() || is_page() ) ) {
			if ( is_single() ) {
				$adsns_area = 'posts+custom_posts';
			}

			if ( is_page() ) {
				$adsns_area = 'pages';
			}

			if ( isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ] ) ) {
				$adsns_ad_units = $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ];
				foreach ( $adsns_ad_units as $adsns_ad_unit ) {
					$adsns_ad_unit_id       = $adsns_ad_unit['id'];
					$adsns_ad_unit_position = $adsns_ad_unit['position'];
					$adsns_ad_unit_code     = htmlspecialchars_decode( $adsns_ad_unit['code'] );
					switch ( $adsns_ad_unit_position ) {
						case 'after':
							$adsns_ads = sprintf( '<div id="%s" class="ads ads_after">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
							$content   = $content . $adsns_ads;
							break;
						case 'before':
							$adsns_ads = sprintf( '<div id="%s" class="ads ads_before">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
							$content   = $adsns_ads . $content;
							break;
						default:
							break;
					}
				}
			}
		}

		return $content;
	}
}

if ( ! function_exists( 'adsns_comments' ) ) {
	/**
	 * Show ads after comment form via Google AdSense API
	 *
	 * @param string $content Comment content.
	 * @return string $content
	 */
	function adsns_comments( $content ) {
		global $adsns_options;

		$adsns_area = '';
		if ( is_single() ) {
			$adsns_area = 'posts+custom_posts';
		}

		if ( is_page() ) {
			$adsns_area = 'pages';
		}
		if ( isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ] ) ) {
			$adsns_ad_units = $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ];
			foreach ( $adsns_ad_units as $adsns_ad_unit ) {
				$adsns_ad_unit_id       = $adsns_ad_unit['id'];
				$adsns_ad_unit_position = $adsns_ad_unit['position'];
				$adsns_ad_unit_code     = htmlspecialchars_decode( $adsns_ad_unit['code'] );
				if ( 'commentform' === $adsns_ad_unit_position ) {
					$content .= sprintf( '<div id="%s" class="ads ads_comments">%s</div>', $adsns_ad_unit_id, $adsns_ad_unit_code );
				}
			}
		}
		return $content;
	}
}

if ( ! function_exists( 'adsns_settings_page' ) ) {
	/**
	 * Main settings page
	 */
	function adsns_settings_page() {
		global $adsns_options, $adsns_plugin_info;
		?>
		<div class="wrap" id="adsns_wrap">
			<h1><?php esc_html_e( 'AdS Settings', 'bws-adsense-plugin' ); ?></h1>
			<noscript>
				<div class="error below-h2">
					<p><strong><?php esc_html_e( 'WARNING', 'bws-adsense-plugin' ); ?>:</strong> <?php esc_html_e( 'The plugin works correctly only if JavaScript is enabled.', 'bws-adsense-plugin' ); ?></p>
				</div>
			</noscript>
			<?php
			if ( ! isset( $_GET['action'] ) ) {
				if ( ! class_exists( 'Bws_Settings_Tabs' ) ) {
					require_once dirname( __FILE__ ) . '/bws_menu/class-bws-settings.php';
				}
				require_once dirname( __FILE__ ) . '/includes/class-adsns-settings.php';
				$page = new Adsns_Settings_Tabs( plugin_basename( __FILE__ ) );
				$page->display_content();
				?>
				<div class="clear"></div>
				<?php
				adsns_plugin_reviews_block( $adsns_plugin_info['Name'], 'bws-adsense-plugin' );
			}
			?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'adsns_list_page' ) ) {
	/**
	 * Main settings page
	 */
	function adsns_list_page() {
		global $adsns_options, $adsns_plugin_info, $wp_version, $title;

		$adsns_table_data     = array();

		$adsns_current_tab = ( isset( $_GET['tab'] ) ) ? urlencode( wp_kses_post( wp_unslash( $_GET['tab'] ) ) ) : 'home';
		$adsns_form_action = $adsns_tab_url = '';

		if ( isset( $_GET ) ) {
			unset( $_GET['page'] );
			foreach ( $_GET as $action => $value ) {
				$adsns_form_action .= sprintf( '&%s=%s', $action, sanitize_text_field( rawurlencode( wp_unslash( $value ) ) ) );
			}
			$adsns_tab_url = preg_replace( '/&tab=[\w\d+]+/', '', $adsns_form_action );
		}

		$adsns_tabs = array(
			'home'               => array(
				'tab'                  => array(
					'title' => __( 'Home page', 'bws-adsense-plugin' ),
					'url'   => sprintf( 'admin.php?page=adsense-list.php%s', $adsns_tab_url ),
				),
				'adunit_positions'     => array(
					'before' => __( 'Before the content', 'bws-adsense-plugin' ),
					'after'  => __( 'After the content', 'bws-adsense-plugin' ),
				),
				'adunit_positions_pro' => array(
					'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'bws-adsense-plugin' ),
					'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'bws-adsense-plugin' ),
				),
			),
			'pages'              => array(
				'tab'                  => array(
					'title' => __( 'Pages', 'bws-adsense-plugin' ),
					'url'   => sprintf( 'admin.php?page=adsense-list.php&tab=pages%s', $adsns_tab_url ),
				),
				'adunit_positions'     => array(
					'before'      => __( 'Before the content', 'bws-adsense-plugin' ),
					'after'       => __( 'After the content', 'bws-adsense-plugin' ),
					'commentform' => __( 'Below the comment form', 'bws-adsense-plugin' ),
				),
				'adunit_positions_pro' => array(
					'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'bws-adsense-plugin' ),
					'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'bws-adsense-plugin' ),
				),
			),
			'posts+custom_posts' => array(
				'tab'                  => array(
					'title' => __( 'Posts / Custom posts', 'bws-adsense-plugin' ),
					'url'   => sprintf( 'admin.php?page=adsense-list.php&tab=posts+custom_posts%s', $adsns_tab_url ),
				),
				'adunit_positions'     => array(
					'before'      => __( 'Before the content', 'bws-adsense-plugin' ),
					'after'       => __( 'After the content', 'bws-adsense-plugin' ),
					'commentform' => __( 'Below the comment form', 'bws-adsense-plugin' ),
				),
				'adunit_positions_pro' => array(
					'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'bws-adsense-plugin' ),
					'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'bws-adsense-plugin' ),
				),
			),
			'categories+tags'    => array(
				'tab'                  => array(
					'title' => __( 'Categories / Tags', 'bws-adsense-plugin' ),
					'url'   => sprintf( 'admin.php?page=adsense-list.php&tab=categories+tags%s', $adsns_tab_url ),
				),
				'adunit_positions'     => array(
					'before' => __( 'Before the content', 'bws-adsense-plugin' ),
					'after'  => __( 'After the content', 'bws-adsense-plugin' ),
				),
				'adunit_positions_pro' => array(
					'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'bws-adsense-plugin' ),
					'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'bws-adsense-plugin' ),
				),
			),
			'search'             => array(
				'tab'                  => array(
					'title' => __( 'Search results', 'bws-adsense-plugin' ),
					'url'   => sprintf( 'admin.php?page=adsense-list.php&tab=search%s', $adsns_tab_url ),
				),
				'adunit_positions'     => array(
					'before' => __( 'Before the content', 'bws-adsense-plugin' ),
					'after'  => __( 'After the content', 'bws-adsense-plugin' ),
				),
				'adunit_positions_pro' => array(
					'1st_paragraph'    => __( 'After the first paragraph (Available in Pro)', 'bws-adsense-plugin' ),
					'random_paragraph' => __( 'After a random paragraph (Available in Pro)', 'bws-adsense-plugin' ),
				),
			),
			'widget'             => array(
				'tab'                  => array(
					'title' => __( 'Widget', 'bws-adsense-plugin' ),
					'url'   => sprintf( 'admin.php?page=adsense-list.php&tab=widget%s', $adsns_tab_url ),
				),
				'adunit_positions'     => array(
					'static' => __( 'Static', 'bws-adsense-plugin' ),
				),
				'adunit_positions_pro' => array(
					'fixed' => __( 'Fixed (Available in Pro)', 'bws-adsense-plugin' ),
				),
				'max_ads'              => 1,
			),
		);

		$adsns_adunit_types = array(
			'TEXT'       => __( 'Text', 'bws-adsense-plugin' ),
			'IMAGE'      => __( 'Image', 'bws-adsense-plugin' ),
			'TEXT_IMAGE' => __( 'Text/Image', 'bws-adsense-plugin' ),
			'LINK'       => __( 'Link', 'bws-adsense-plugin' ),
		);

		$adsns_adunit_statuses = array(
			'NEW'      => __( 'New', 'bws-adsense-plugin' ),
			'ACTIVE'   => __( 'Active', 'bws-adsense-plugin' ),
			'INACTIVE' => __( 'Idle', 'bws-adsense-plugin' ),
			'ARCHIVED' => __( 'Archived', 'bws-adsense-plugin' ),
		);

		$adsns_adunit_sizes = array(
			'RESPONSIVE' => __( 'Responsive', 'bws-adsense-plugin' ),
		);

		if ( file_exists( dirname( __FILE__ ) . '/google_api/client_secrets.json' ) ) {
			$adsns_client = adsns_client();
		}

		$adsns_authorize = false;

		if ( isset( $adsns_options['authorization_code'] ) && ! empty( $adsns_client ) ) {
			$adsns_client->fetchAccessTokenWithRefreshToken( $adsns_options['authorization_code'] );
		}

		if ( ! empty( $adsns_client ) && $adsns_client->getAccessToken() ) {
			$adsns_service = adsns_service( $adsns_client );
			spl_autoload_register(
				function ( $class_name ) {
					if ( file_exists( dirname( __FILE__ ) . '/google_api/custom_classes/' . $class_name . '.php' ) ) {
						include dirname( __FILE__ ) . '/google_api/custom_classes/' . $class_name . '.php';
					}
				}
			);

			try {
				$adsns_list_accounts = GetAllAccounts::run( $adsns_service, 10 );
				if ( ! empty( $adsns_list_accounts ) ) {
					try {
							$adsns_list_adclients = GetAllAdClients::run( $adsns_service, $adsns_options['publisher_id'], 50 );
						$adsns_ad_client          = null;
						foreach ( $adsns_list_adclients as $adsns_list_adclient ) {
							if ( 'AFC' === $adsns_list_adclient['productCode'] ) {
								$adsns_ad_client = $adsns_list_adclient['id'];
							}
						}
						if ( ! empty( $adsns_ad_client ) ) {
							try {
									$adsns_adunits = GetAllAdUnits::run( $adsns_service, $adsns_ad_client, 50 );
								foreach ( $adsns_adunits as $adsns_adunit ) {
									$adsns_adunit_type = $adsns_adunit['contentAdsSettings']['type'];
									$adsns_adunit_size = $adsns_adunit['contentAdsSettings']['size'];
									if ( array_key_exists( $adsns_adunit_size, $adsns_adunit_sizes ) ) {
										$adsns_adunit_size = $adsns_adunit_sizes[ $adsns_adunit_size ];
									}
									$adsns_adunit_status = $adsns_adunit['state'];
									if ( array_key_exists( $adsns_adunit_status, $adsns_adunit_statuses ) ) {
										$adsns_adunit_status = $adsns_adunit_statuses[ $adsns_adunit_status ];
									}
									if ( 1 !== absint( $adsns_options['include_inactive_ads'] ) && ( 'INACTIVE' === $adsns_adunit['state'] || 'ARCHIVED' === $adsns_adunit['state'] ) ) {
										continue;
									}
									$ids = explode( '/', $adsns_adunit['name'] );
									try {
										$adsns_table_data[ $adsns_adunit['displayName'] ] = array(
											'id'           => $adsns_adunit['name'],
											'name'         => $adsns_adunit['displayName'],
											'code'         => end( $ids ),
											'summary'      => sprintf( '%s, %s', $adsns_adunit_type, $adsns_adunit_size ),
											'type'         => $adsns_adunit_type,
											'status'       => $adsns_adunit_status,
											'status_value' => $adsns_adunit['state'],
										);
									} catch ( Google_Service_Exception $e ) {
										$adsns_err        = $e->getErrors();
										$adsns_api_notice = array(
											'class'   => 'error adsns_api_notice below-h2',
											'message' => sprintf(
												'<strong>%s</strong> %s %s',
												esc_html__( 'AdUnit Error:', 'bws-adsense-plugin' ),
												$adsns_err[0]['message'],
												sprintf( esc_html__( 'Check Unit in %s', 'bws-adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
											),
										);
									}
								}
							} catch ( Google_Service_Exception $e ) {
								$adsns_err        = $e->getErrors();
								$adsns_api_notice = array(
									'class'   => 'error adsns_api_notice below-h2',
									'message' => sprintf(
										'<strong>%s</strong> %s %s',
										esc_html__( 'AdUnits Error:', 'bws-adsense-plugin' ),
										$adsns_err[0]['message'],
										sprintf( esc_html__( 'Check Units in %s', 'bws-adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
									),
								);
							}
						}
					} catch ( Google_Service_Exception $e ) {
						$adsns_err        = $e->getErrors();
						$adsns_api_notice = array(
							'class'   => 'error adsns_api_notice below-h2',
							'message' => sprintf(
								'<strong>%s</strong> %s %s',
								esc_html__( 'AdClient Error:', 'bws-adsense-plugin' ),
								$adsns_err[0]['message'],
								sprintf( esc_html__( 'Check Clients in in %s', 'bws-adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
							),
						);
					}
				}
			} catch ( Google_Service_Exception $e ) {
				$adsns_err        = $e->getErrors();
				$adsns_api_notice = array(
					'class'   => 'error adsns_api_notice below-h2',
					'message' => sprintf(
						'<strong>%s</strong> %s %s',
						esc_html__( 'Account Error:', 'bws-adsense-plugin' ),
						$adsns_err[0]['message'],
						sprintf( esc_html__( 'Create account in %s', 'bws-adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
					),
				);
			} catch ( Exception $e ) {
				$adsns_api_notice = array(
					'class'   => 'error adsns_api_notice below-h2',
					'message' => $e->getMessage(),
				);
			}
		}

		if ( isset( $_POST['adsns_save_settings'] ) && check_admin_referer( plugin_basename( __FILE__ ), 'adsns_nonce_name' ) ) {
			$adsns_old_options = $adsns_options;
			$adsns_area        = isset( $_POST['adsns_area'] ) ? sanitize_text_field( wp_unslash( $_POST['adsns_area'] ) ) : '';

			if ( array_key_exists( $adsns_area, $adsns_tabs ) ) {

				$adsns_save_settings = true;

				if ( isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ] ) ) {
					$adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ] = array();
				}

				if ( isset( $_POST['adsns_adunit_ids'] ) ) {
					$adsns_max_ads           = isset( $adsns_tabs[ $adsns_area ]['max_ads'] ) ? $adsns_tabs[ $adsns_area ]['max_ads'] : null;
					$adsns_posted_adunit_ids = isset( $_POST['adsns_adunit_ids'] ) ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['adsns_adunit_ids'] ) ) : '';

					if ( $adsns_max_ads ) {
						$adsns_adunit_ids = array_slice( $adsns_posted_adunit_ids, 0, $adsns_tabs[ $adsns_area ]['max_ads'] );
					} else {
						$adsns_adunit_ids = $adsns_posted_adunit_ids;
					}

					$adsns_adunit_positions = isset( $_POST['adsns_adunit_position'] ) ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['adsns_adunit_position'] ) ) : array();

					if ( isset( $adsns_options['publisher_id'] ) && isset( $adsns_ad_client ) ) {
						foreach ( $adsns_adunit_ids as $adsns_adunit_id ) {
							try {
								$adsns_adunit_code     = GetAdUnitCode::run( $adsns_service, $adsns_adunit_id );
								$adsns_adunit_position = array_key_exists( $adsns_adunit_id, $adsns_adunit_positions ) ? $adsns_adunit_positions[ $adsns_adunit_id ] : null;
								$adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_area ][] = array(
									'id'       => $adsns_adunit_id,
									'position' => $adsns_adunit_position,
									'code'     => htmlspecialchars( $adsns_adunit_code ),
								);
							} catch ( Google_Service_Exception $e ) {
								$adsns_err                = $e->getErrors();
								$adsns_save_settings      = false;
								$adsns_settings_notices[] = array(
									'class'   => 'error below-h2',
									'message' => sprintf( '%s<br/>%s<br/>%s', sprintf( esc_html__( 'An error occurred while obtaining the code for the block %s.', 'bws-adsense-plugin' ), sprintf( '<strong>%s</strong>', $adsns_adunit_id ) ), $adsns_err[0]['message'], esc_html__( 'Settings are not saved.', 'bws-adsense-plugin' ) ),
								);
							}
						}
					}
				}

				if ( $adsns_save_settings ) {
					update_option( 'adsns_options', $adsns_options );
					$adsns_settings_notices[] = array(
						'class'   => 'updated fade below-h2',
						'message' => __( 'Settings saved.', 'bws-adsense-plugin' ),
					);
				} else {
					$adsns_options = $adsns_old_options;
				}
			} else {
				$adsns_settings_notices[] = array(
					'class'   => 'error below-h2',
					'message' => __( 'Settings are not saved.', 'bws-adsense-plugin' ),
				);
			}
		}

		$adsns_hidden_idle_notice = false;
		if ( 1 !== absint( $adsns_options['include_inactive_ads'] ) && isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_current_tab ] ) ) {
			$current_ads = $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_current_tab ];
			if ( ! empty( $current_ads ) ) {
				foreach ( $adsns_table_data as $adname => $addata ) {
					foreach ( $current_ads as $current_ad ) {
						if ( $current_ad['id'] === $addata['id'] ) {
							if ( 'INACTIVE' === $addata['status_value'] || 'ARCHIVED' === $addata['status_value'] ) {
								$adsns_hidden_idle_notice = true;
								break( 2 );
							}
							break;
						}
					}
				}
			}
		}
		?>
		<div class="wrap" id="adsns_wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php
			if ( isset( $adsns_api_notice ) ) {
				printf( '<div class="below-h2 %s"><p>%s</p></div>', esc_html( $adsns_api_notice['class'] ), wp_kses_post( $adsns_api_notice['message'] ) );
			}
			if ( isset( $adsns_settings_notices ) ) {
				foreach ( $adsns_settings_notices as $adsns_settings_notice ) {
					printf( '<div class="below-h2 %s"><p>%s</p></div>', esc_html( $adsns_settings_notice['class'] ), esc_html( $adsns_settings_notice['message'] ) );
				}
			}
			if ( ! isset( $_GET['action'] ) ) {
				?>
				<div class="updated notice notice-warning below-h2 adsns-hidden-idle-notice<?php echo esc_html( ( $adsns_hidden_idle_notice ) ? '' : ' hidden' ); ?>">
					<p><?php esc_html_e( 'Some of hidden idle ad blocks still set to be displayed.', 'bws-adsense-plugin' ); ?></p>
				</div>
				<form action="admin.php?page=adsense-list.php<?php echo isset( $_GET['tab'] ) ? '&tab=' . esc_html( sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) : ''; ?>" method="post">
					<?php if ( ( isset( $adsns_options['publisher_id'] ) && isset( $adsns_tabs[ $adsns_current_tab ] ) ) ) { ?>
						<h2 class="nav-tab-wrapper">
							<?php
							foreach ( $adsns_tabs as $adsns_tab => $adsns_tab_data ) {
								$adsns_count_ads = 0;

								if ( isset( $adsns_options['publisher_id'] ) && isset( $adsns_tabs[ $adsns_current_tab ] ) ) {
									if ( isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_tab ] ) ) {
										$adsns_count_ads = count( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_tab ] );
									}
								} else {
									if ( 'widget' === $adsns_tab ) {
										continue;
									}
								}

								printf( '<a class="nav-tab%s" href="%s">%s <span class="adsns_count_ads">%d</span></a>', ( $adsns_tab === $adsns_current_tab ) ? ' nav-tab-active' : '', esc_url( $adsns_tab_data['tab']['url'] ), esc_html( $adsns_tab_data['tab']['title'] ), esc_html( $adsns_count_ads ) );
							}
							?>
						</h2>
						<div id="adsns_tab_content" 
						<?php
						if ( 'search' === $adsns_current_tab ) {
							echo 'class="bws_pro_version_bloc adsns_pro_version_bloc"';
						}
						?>
							>
							<div 
							<?php
							if ( 'search' === $adsns_current_tab ) {
								echo 'class="bws_pro_version_table_bloc adsns_pro_version_table_bloc"';
							}
							?>
							>
								<div 
								<?php
								if ( 'search' === $adsns_current_tab ) {
									echo 'class="bws_table_bg adsns_table_bg"';
								}
								?>
								></div>				
								<div id="adsns_usage_notice">
									<?php if ( 'widget' === $adsns_current_tab ) { ?>
										<p>
											<?php
											printf( esc_html__( "Please don't forget to place the AdSense widget into a needed sidebar on the %s.", 'bws-adsense-plugin' ), sprintf( '<a href="widgets.php" target="_blank">%s</a>', esc_html__( 'widget page', 'bws-adsense-plugin' ) ) );
											printf( ' %s <a href="https://bestwebsoft.com/products/wordpress/plugins/google-adsense/?k=2887beb5e9d5e26aebe6b7de9152ad1f&amp;pn=80&amp;v=%s&amp;wp_v=%s" target="_blank"><strong>Pro</strong></a>.', esc_html__( 'An opportunity to add several widgets is available in the', 'bws-adsense-plugin' ), esc_html( $adsns_plugin_info['Version'] ), esc_html( $wp_version ) );
											?>
																																		
										</p>
									<?php } ?>
									<p>
										<?php printf( esc_html__( 'Add or manage existing ad blocks in the %s.', 'bws-adsense-plugin' ), sprintf( '<a href="https://www.google.com/adsense/app#main/myads-viewall-adunits" target="_blank">%s</a>', esc_html__( 'Google AdSense', 'bws-adsense-plugin' ) ) ); ?><br />
										<span class="bws_info"><?php printf( esc_html__( 'After adding the ad block in Google AdSense, please %s to see the new ad block in the list of plugin ad blocks.', 'bws-adsense-plugin' ), sprintf( '<a href="admin.php?page=adsense-list.php%s">%s</a>', esc_html( $adsns_form_action ), esc_html__( 'reload the page', 'bws-adsense-plugin' ) ) ); ?></span>
									</p>
								</div>
								<?php
								if ( isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_current_tab ] ) ) {
									foreach ( $adsns_options['adunits'][ $adsns_options['publisher_id'] ][ $adsns_current_tab ] as $adsns_table_adunit ) {
										$adsns_table_adunits[ $adsns_table_adunit['id'] ] = $adsns_table_adunit['position'];
									}
								}

								require_once dirname( __FILE__ ) . '/includes/adsns-list-table.php';
								$adsns_lt                             = new Adsns_List_Table( $adsns_options, $adsns_current_tab, $adsns_table_data, ( isset( $adsns_table_adunits ) && is_array( $adsns_table_adunits ) ) ? $adsns_table_adunits : array(), $adsns_tabs[ $adsns_current_tab ]['adunit_positions'], $adsns_tabs[ $adsns_current_tab ]['adunit_positions_pro'] );
								$adsns_lt->prepare_items();
								echo '<div class="adsns-ads-list">';
									$adsns_lt->display();
								echo '</div>';
								?>
							</div>
							<?php if ( 'search' === $adsns_current_tab ) { ?>
								<div class="bws_pro_version_tooltip adsns_pro_version_tooltip">
									<a class="bws_button" href="https://bestwebsoft.com/products/wordpress/plugins/google-adsense/?k=2887beb5e9d5e26aebe6b7de9152ad1f&amp;pn=80&amp;v=<?php echo esc_html( $adsns_plugin_info['Version'] ); ?>&amp;wp_v=<?php echo esc_html( $wp_version ); ?>" target="_blank" title="AdS  Pro"><?php esc_html_e( 'Upgrade to Pro', 'bws-adsense-plugin' ); ?></a>
									<div class="clear"></div>
								</div>
							<?php } ?>
						</div>						
					<?php } else { ?>
						<p>
							<?php printf( esc_html__( 'Please authorize via your Google Account in %s to manage ad blocks.', 'bws-adsense-plugin' ), sprintf( '<a href="admin.php?page=bws-adsense.php">%s</a>', esc_html__( 'the AdS  settings page', 'bws-adsense-plugin' ) ) ); ?>
						</p>
						<?php
					}
					if ( isset( $adsns_options['publisher_id'] ) ) {
						?>
						<p>
							<input type="hidden" name="adsns_area" value="<?php echo esc_html( $adsns_current_tab ); ?>" />
							<input id="bws-submit-button" type="submit" class="button-primary" name="adsns_save_settings" value="<?php esc_html_e( 'Save Changes', 'bws-adsense-plugin' ); ?>" />
						</p>
						<?php
					}
					wp_nonce_field( plugin_basename( __FILE__ ), 'adsns_nonce_name' );
					?>
				</form>
			<?php	} ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'adsns_plugin_reviews_block' ) ) {
	/**
	 * Display review block (moved from BWS_Menu)
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $plugin_slug Plugin slug.
	 */
	function adsns_plugin_reviews_block( $plugin_name, $plugin_slug ) {
		?>
		<div class="bws-plugin-reviews">
			<div class="bws-plugin-reviews-rate">
				<?php esc_html_e( 'Like the plugin?', 'bws-adsense-plugin' ); ?>
				<a href="https://wordpress.org/support/view/plugin-reviews/<?php echo esc_attr( $plugin_slug ); ?>?filter=5" target="_blank" title="<?php printf( esc_html__( '%s reviews', 'bws-adsense-plugin' ), esc_html( $plugin_name ) ); ?>">
					<?php esc_html_e( 'Rate it', 'bws-adsense-plugin' ); ?>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
				</a>
			</div>
			<div class="bws-plugin-reviews-support">
				<?php esc_html_e( 'Need help?', 'bws-adsense-plugin' ); ?>
				<a href="mailto:support@bestwebsoft.com">support@bestwebsoft.com</a>
			</div>
			<div class="bws-plugin-reviews-donate">
				<?php esc_html_e( 'Want to support the plugin?', 'bws-adsense-plugin' ); ?>
				<a href="https://bestwebsoft.com/donate/"><?php esc_html_e( 'Donate', 'bws-adsense-plugin' ); ?></a>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'adsns_get_domain' ) ) {
	/**
	 * Get domain
	 */
	function adsns_get_domain() {
		$site_url = wp_parse_url( site_url( '/' ) );
		return $site_url['host'];
	}
}

if ( ! function_exists( 'adsns_write_admin_head' ) ) {
	/**
	 * Including scripts and stylesheets for admin interface of plugin
	 */
	function adsns_write_admin_head() {
		global $adsns_plugin_info;

		wp_enqueue_style( 'adsns_stylesheet_icon', plugins_url( '/css/icon_style.css', __FILE__ ), false, $adsns_plugin_info['Version'] );

		if ( isset( $_GET['page'] ) && ( 'bws-adsense.php' === $_GET['page'] || 'adsense-list.php' === $_GET['page'] ) ) {
			wp_enqueue_script( 'adsns_chart_js', plugins_url( 'js/chart.min.js', __FILE__ ), array( 'jquery' ), $adsns_plugin_info['Version'], true );
			wp_enqueue_script( 'adsns_color_picker_js', plugins_url( 'js/jquery.minicolors.min.js', __FILE__ ), array( 'jquery' ), $adsns_plugin_info['Version'], true );
			wp_enqueue_script( 'adsns_admin_js', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), $adsns_plugin_info['Version'], true );
			wp_enqueue_style( 'adsns_color_picker_css', plugins_url( 'css/jquery.minicolors.css', __FILE__ ), false, $adsns_plugin_info['Version'], true );

			bws_enqueue_settings_scripts();
			bws_plugins_include_codemirror();
		}

		wp_enqueue_style( 'adsns_admin_css', plugins_url( 'css/style.css', __FILE__ ), false, $adsns_plugin_info['Version'] );
	}
}

if ( ! function_exists( 'adsns_head' ) ) {
	/**
	 * Stylesheets for ads
	 */
	function adsns_head() {
		global $adsns_plugin_info;
		wp_enqueue_style( 'adsns_css', plugins_url( 'css/adsns.css', __FILE__ ), false, $adsns_plugin_info['Version'] );
	}
}

if ( ! function_exists( 'adsns_plugin_notice' ) ) {
	/**
	 * Display notice in the main dashboard page / plugins page
	 */
	function adsns_plugin_notice() {
		global $hook_suffix, $current_user, $adsns_plugin_info;

		if ( 'plugins.php' === $hook_suffix ) {
			bws_plugin_banner_to_settings( $adsns_plugin_info, 'adsns_options', 'bws-adsense-plugin', 'admin.php?page=bws-adsense.php' );
		}

		if ( isset( $_GET['page'] ) && ( 'bws-adsense.php' === $_GET['page'] || 'adsense-list.php' === $_GET['page'] ) ) {
			adsns_plugin_suggest_feature_banner( $adsns_plugin_info, 'adsns_options', 'bws-adsense-plugin' );
		}
	}
}

if ( ! function_exists( 'adsns_plugin_suggest_feature_banner' ) ) {
	/**
	 * Display Suggest Feature bunner (moved from BWS_Menu)
	 *
	 * @param array  $plugin_info         Array with plugin info.
	 * @param string $plugin_options_name Option name.
	 * @param string $banner_url_or_slug  URL or slug for banner.
	 */
	function adsns_plugin_suggest_feature_banner( $plugin_info, $plugin_options_name, $banner_url_or_slug ) {
		$is_network_admin = is_network_admin();

		$plugin_options = $is_network_admin ? get_site_option( $plugin_options_name ) : get_option( $plugin_options_name );

		if ( isset( $plugin_options['display_suggest_feature_banner'] ) && 0 === absint( $plugin_options['display_suggest_feature_banner'] ) ) {
			return;
		}

		if ( ! isset( $plugin_options['first_install'] ) ) {
			$plugin_options['first_install'] = strtotime( 'now' );
			$update_option                   = true;
			$return                          = true;
		} elseif ( strtotime( '-2 week' ) < $plugin_options['first_install'] ) {
			$return = true;
		}

		if ( ! isset( $plugin_options['go_settings_counter'] ) ) {
			$plugin_options['go_settings_counter'] = 1;
			$update_option                         = true;
			$return                                = true;
		} elseif ( 20 > $plugin_options['go_settings_counter'] ) {
			$plugin_options['go_settings_counter'] = $plugin_options['go_settings_counter'] + 1;
			$update_option                         = true;
			$return                                = true;
		}

		if ( isset( $update_option ) ) {
			if ( $is_network_admin ) {
				update_site_option( $plugin_options_name, $plugin_options );
			} else {
				update_option( $plugin_options_name, $plugin_options );
			}
		}

		if ( isset( $return ) ) {
			return;
		}

		if ( isset( $_POST[ 'bws_hide_suggest_feature_banner_' . $plugin_options_name ] ) && check_admin_referer( $plugin_info['Name'], 'bws_settings_nonce_name' ) ) {
			$plugin_options['display_suggest_feature_banner'] = 0;
			if ( $is_network_admin ) {
				update_site_option( $plugin_options_name, $plugin_options );
			} else {
				update_option( $plugin_options_name, $plugin_options );
			}
			return;
		}

		if ( false === strrpos( $banner_url_or_slug, '/' ) ) {
			$banner_url_or_slug = '//ps.w.org/' . $banner_url_or_slug . '/assets/icon-128x128.png';
		}
		?>
		<div class="updated" style="padding: 0; margin: 0; border: none; background: none;">
			<div class="bws_banner_on_plugin_page bws_suggest_feature_banner">
				<div class="icon">
					<img title="" src="<?php echo esc_attr( $banner_url_or_slug ); ?>" alt="" />
				</div>
				<div class="text">
					<strong><?php printf( esc_html__( 'Thank you for choosing %s plugin!', 'bws-adsense-plugin' ), esc_html( $plugin_info['Name'] ) ); ?></strong><br />
					<?php esc_html_e( "If you have a feature, suggestion or idea you'd like to see in the plugin, we'd love to hear about it!", 'bws-adsense-plugin' ); ?>
					<a href="mailto:support@bestwebsoft.com"><?php esc_html_e( 'Suggest a Feature', 'bws-adsense-plugin' ); ?></a>
				</div>
				<form action="" method="post">
					<button class="notice-dismiss bws_hide_settings_notice" title="<?php esc_html_e( 'Close notice', 'bws-adsense-plugin' ); ?>"></button>
					<input type="hidden" name="bws_hide_suggest_feature_banner_<?php echo esc_html( $plugin_options_name ); ?>" value="hide" />
					<?php wp_nonce_field( $plugin_info['Name'], 'bws_settings_nonce_name' ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'adsns_widget_display' ) ) {
	/**
	 * Displays AdSense in widget
	 *
	 * @echo array()
	 */
	function adsns_widget_display() {
		global $adsns_options;
		$title = $adsns_options['widget_title'];
		if ( ! empty( $adsns_options['adunits'][ $adsns_options['publisher_id'] ]['widget'] ) ) {
			$adsns_ad_unit_id   = $adsns_options['adunits'][ $adsns_options['publisher_id'] ]['widget'][0]['id'];
			$adsns_ad_unit_code = htmlspecialchars_decode( $adsns_options['adunits'][ $adsns_options['publisher_id'] ]['widget'][0]['code'] );
			printf( '<aside class="widget widget-container adsns_widget"><h1 class="widget-title">%s</h1><div id="%s" class="ads ads_widget">%s</div></aside>', esc_html( $title ), esc_html( $adsns_ad_unit_id ), $adsns_ad_unit_code );
		}
	}
}

if ( ! function_exists( 'adsns_register_widget' ) ) {
	/**
	 * Register widget for use in sidebars.
	 * Registers widget control callback for customizing options
	 */
	function adsns_register_widget() {
		global $adsns_options;
		if ( isset( $adsns_options['publisher_id'] ) && isset( $adsns_options['adunits'][ $adsns_options['publisher_id'] ]['widget'] ) && count( $adsns_options['adunits'][ $adsns_options['publisher_id'] ]['widget'] ) > 0 ) {
			$adsns_widget_positions = array(
				'static' => __( 'Static', 'bws-adsense-plugin' ),
				'fixed'  => __( 'Fixed', 'bws-adsense-plugin' ),
			);
			$adsns_widget           = $adsns_options['adunits'][ $adsns_options['publisher_id'] ]['widget'][0];
			$adsns_widget_ids       = explode( '/', $adsns_widget['id'] );
			$adsns_id               = end( $adsns_widget_ids );
			$adsns_widget_position  = isset( $adsns_widget['position'] ) ? $adsns_widget['position'] : 'static';
			if ( 'static' !== $adsns_widget_position ) {
				$adsns_widget_position = 'static';

				$adsns_options['adunits'][ $adsns_options['publisher_id'] ]['widget'][0]['position'] = 'static';
				update_option( 'adsns_options', $adsns_options );
			}
			wp_register_sidebar_widget(
				'adsns_widget', /* Unique widget id */
				sprintf( 'AdSense: ID: %s, %s', $adsns_id, $adsns_widget_positions[ $adsns_widget_position ] ),
				'adsns_widget_display', /* Callback function */
				array( 'description' => sprintf( '%s ID: %s, %s', esc_html__( 'Widget displays AdS .', 'bws-adsense-plugin' ), $adsns_id, $adsns_widget_positions[ $adsns_widget_position ] ) ) /* Options */
			);
			wp_register_widget_control(
				'adsns_widget', /* Unique widget id */
				sprintf( 'AdSense: ID: %s, %s', $adsns_id, $adsns_widget_positions[ $adsns_widget_position ] ),
				'adsns_widget_control' /* Callback function */
			);
		}
	}
}

if ( ! function_exists( 'adsns_widget_control' ) ) {
	/**
	 * Registers widget control callback for customizing options
	 */
	function adsns_widget_control() {
		global $adsns_options;
		if ( isset( $_POST['adsns-widget-submit'] ) && isset( $_POST['adsns-widget-title'] ) ) {
			$adsns_options['widget_title'] = sanitize_text_field( wp_unslash( $_POST['adsns-widget-title'] ) );
			update_option( 'adsns_options', $adsns_options );
		}
		$title = isset( $adsns_options['widget_title'] ) ? $adsns_options['widget_title'] : '';
		printf( '<p><label for="adsns-widget-title">%s<input class="widefat" id="adsns-widget-title" name="adsns-widget-title" type="text" value="%s" /></label></p><input type="hidden" id="adsns-widget-submit" name="adsns-widget-submit" value="1" />', esc_html__( 'Title', 'bws-adsense-plugin' ), esc_html( $title ) );
		?>
		<p>
			<?php printf( '<strong>%s</strong> %s', esc_html__( 'Please note:', 'bws-adsense-plugin' ), sprintf( '<a href="admin.php?page=bws-adsense.php&tab=widget" target="_blank">%s</a>', esc_html__( "Select ad block to display in the widget you can on the plugin settings page in the 'Widget' tab.", 'bws-adsense-plugin' ) ) ); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'adsns_plugin_action_links' ) ) {
	/**
	 * Add action links
	 *
	 * @param array $links Action link array.
	 * @param file  $file  Plugin file.
	 * @return  array $links   Returned link array.
	 */
	function adsns_plugin_action_links( $links, $file ) {
		if ( ! is_network_admin() && ! is_plugin_active( 'adsense-pro/adsense-pro.php' ) ) {
			if ( 'bws-adsense/bws-adsense.php' === $file ) {
				$settings_link = '<a href="admin.php?page=bws-adsense.php">' . __( 'Settings', 'bws-adsense-plugin' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
		}
		return $links;
	}
}

if ( ! function_exists( 'adsns_register_plugin_links' ) ) {
	/**
	 * Add Settings and Support links
	 *
	 * @param   array $links   Action link array.
	 * @param   file  $file    Plugin file.
	 * @return  array    $links   Returned link array.
	 */
	function adsns_register_plugin_links( $links, $file ) {
		if ( 'bws-adsense/bws-adsense.php' === $file ) {
			if ( ! is_network_admin() ) {
				$links[] = '<a href="admin.php?page=bws-adsense.php">' . __( 'Settings', 'bws-adsense-plugin' ) . '</a>';
			}
			$links[] = '<a href="https://support.bestwebsoft.com/hc/en-us/sections/200538919" target="_blank">' . __( 'FAQ', 'bws-adsense-plugin' ) . '</a>';
			$links[] = '<a href="mailto:support@bestwebsoft.com">' . __( 'Support', 'bws-adsense-plugin' ) . '</a>';
		}
		return $links;
	}
}

if ( ! function_exists( 'adsns_add_tabs' ) ) {
	/**
	 * Add help tab
	 */
	function adsns_add_tabs() {
		$content = sprintf(
			'<p>%s %s</p>',
			__( 'Have a problem? Contact us', 'bws-adsense-plugin' ),
			'<a href="mailto:support@bestwebsoft.com">support@bestwebsoft.com</a>'
		);

		$screen = get_current_screen();

		$screen->add_help_tab(
			array(
				'id'      => 'adsns_help_tab',
				'title'   => __( 'FAQ', 'bws-adsense-plugin' ),
				'content' => $content,
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'bws-adsense-plugin' ) . '</strong></p>' .
			'<p><a href="https://drive.google.com/folderview?id=0B5l8lO-CaKt9VGh0a09vUjNFNjA&usp=sharing#list" target="_blank">' . __( 'Documentation', 'bws-adsense-plugin' ) . '</a></p>' .
			'<p><a href="http://www.youtube.com/user/bestwebsoft/playlists?flow=grid&sort=da&view=1" target="_blank">' . __( 'Video Instructions', 'bws-adsense-plugin' ) . '</a></p>' .
			'<p><a href="mailto:support@bestwebsoft.com">' . __( 'Contact us', 'bws-adsense-plugin' ) . '</a></p>'
		);
	}
}

if ( ! function_exists( 'adsns_loop_start' ) ) {
	/**
	 * The content Loop start
	 *
	 * @param string $content Content for loop.
	 */
	function adsns_loop_start( $content ) {
		global $wp_query, $adsns_is_main_query;
		if ( is_main_query() && $content === $wp_query ) {
			$adsns_is_main_query = true;
		}
	}
}

if ( ! function_exists( 'adsns_loop_end' ) ) {
	/**
	 * The content Loop end
	 *
	 * @param string $content Content for loop.
	 */
	function adsns_loop_end( $content ) {
		global $adsns_is_main_query;
		$adsns_is_main_query = false;
	}
}

if ( ! function_exists( 'adsns_uninstall' ) ) {
	/**
	 * Function fo uninstall
	 */
	function adsns_uninstall() {
		global $wpdb;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( ! array_key_exists( 'adsense-pro/adsense-pro.php', $all_plugins ) ) {
			if ( is_multisite() ) {
				global $wpdb;
				$old_blog = $wpdb->blogid;
				/* Get all blog ids */
				$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					delete_option( 'adsns_options' );
				}
				switch_to_blog( $old_blog );
			} else {
				delete_option( 'adsns_options' );
			}
		}

		/* Delete ads.txt file */
		$home_path = get_home_path();
		$ads_txt   = $home_path . 'ads.txt';

		if ( file_exists( $ads_txt ) ) {
			unlink( $ads_txt );
		}

		require_once dirname( __FILE__ ) . '/bws_menu/bws_include.php';
		bws_include_init( plugin_basename( __FILE__ ) );
		bws_delete_plugin( plugin_basename( __FILE__ ) );
	}
}
/* Activation hook */
register_activation_hook( __FILE__, 'adsns_activate' );
/* Adding 'BWS Plugins' admin menu */
add_action( 'admin_menu', 'adsns_add_admin_menu' );
add_action( 'init', 'adsns_plugin_init' );
/* Plugin localization */
add_action( 'plugins_loaded', 'adsns_localization' );
add_action( 'admin_init', 'adsns_plugin_admin_init' );
add_action( 'admin_enqueue_scripts', 'adsns_write_admin_head' );
/* Action for adsns_show_ads */
add_action( 'after_setup_theme', 'adsns_after_setup_theme' );
/* Display the plugin widget */
add_action( 'widgets_init', 'adsns_register_widget' );
/* Adding ads stylesheets */
add_action( 'wp_enqueue_scripts', 'adsns_head' );
/* Add "Settings" link to the plugin action page */
add_filter( 'plugin_action_links', 'adsns_plugin_action_links', 10, 2 );
/* Additional links on the plugin page */
add_filter( 'plugin_row_meta', 'adsns_register_plugin_links', 10, 2 );
/* Adding actions to define variable as true inside the main loop and as false outside of it */
add_action( 'loop_start', 'adsns_loop_start' );
add_action( 'loop_end', 'adsns_loop_end' );
/* Display notices */
add_action( 'admin_notices', 'adsns_plugin_notice' );
add_action( 'network_admin_admin_notices', 'adsns_plugin_notice' );
/* When uninstall plugin */
register_uninstall_hook( __FILE__, 'adsns_uninstall' );
