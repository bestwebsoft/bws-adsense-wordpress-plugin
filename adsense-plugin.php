<?php
/*
Plugin Name: Google AdSense
Plugin URI: http://bestwebsoft.com/plugin/
Description: This plugin allows implementing Google AdSense to your website.
Author: BestWebSoft
Version: 1.24
Author URI: http://bestwebsoft.com/
License: GPLv2 or later
*/

/*  © Copyright 2011  BestWebSoft  ( http://support.bestwebsoft.com )

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
require_once( dirname( __FILE__ ) . '/bws_menu/bws_menu.php' );

include_once( 'adsense-plugin.class.php' );  // including a class which contains a plugin functions

$this_adsns_plugin = plugin_basename(__FILE__);  // path to this file(from plugins dir)

$adsns_plugin = new adsns();  // creating a variable with type of our class
 
$adsns_plugin->page_title = __( 'AdSense Settings', 'adsense' ); // title for options page
 
$adsns_plugin->menu_title = __( 'AdSense', 'adsense' ); 	// name in menu

// This function showing ads at the choosen position
if ( ! function_exists ( 'adsns_show_ads' ) ) {
	function adsns_show_ads() {
		global $adsns_options, $max_ads, $count, $current_count, $adsns_count, $adsns_plugin;
		$adsns_plugin->adsns_activate();

		// checking in what position we should show an ads
		if ( $adsns_options['position'] == 'postend' ) {  									// if we choose ad position after post(single page)
			add_filter( 'the_content', array( $adsns_plugin, 'adsns_end_post_ad' ) );  	// adding ad after post
		}		
		else if ( $adsns_options['position'] == 'homepostend' ) {								// if we choose ad position after post(home page)
			add_filter( 'the_content', array( $adsns_plugin, 'adsns_end_home_post_ad' ) );		// adding ad after post
		}
		else if ( $adsns_options['position'] == 'homeandpostend' ) {										// if we choose ad position after post(home page)
			add_filter( 'the_content', array( $adsns_plugin, 'adsns_end_home_post_ad' ) );		// adding ad after post
			add_filter( 'the_content', array( $adsns_plugin, 'adsns_end_post_ad' ) );  	// adding ad after post
		}
		else if ( $adsns_options['position'] == 'commentform' ) {											// if we choose ad position after comment form
			add_filter( 'comment_id_fields', array( $adsns_plugin, 'adsns_end_comment_ad' ) );		// adding ad after comment form
		}
		else if ( $adsns_options['position'] == 'footer' ) {
		// if we choose ad position in a footer
			add_filter( 'get_footer', array( $adsns_plugin, 'adsns_end_footer_ad' ) );		// adding footer ad
		}
		// end checking
	}
}

if ( ! function_exists ( 'adsns_plugin_init' ) ) {
	function adsns_plugin_init() {
		// Internationalization
		load_plugin_textdomain( 'adsense', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		load_plugin_textdomain( 'bestwebsoft', false, dirname( plugin_basename( __FILE__ ) ) . '/bws_menu/languages/' ); 
	}
}

// add "Settings" link to the plugin action page
add_filter( 'plugin_action_links', array( $adsns_plugin, 'adsns_plugin_action_links'), 10, 2 );

// Additional links on the plugin page
add_filter( 'plugin_row_meta', array( $adsns_plugin, 'adsns_register_plugin_links'), 10, 2 );

add_action( 'init', 'adsns_plugin_init' );
add_action( 'init', array( $adsns_plugin, 'adsns_activate' ) );
add_action( 'admin_init', array( $adsns_plugin, 'adsns_write_admin_head' ) );

// Action for adsns_show_ads
add_action( 'after_setup_theme', 'adsns_show_ads' );

// Display the plugin widget
add_action( 'widgets_init', array( $adsns_plugin, 'adsns_register_widget' ) );

// Adding ads stylesheets
add_action( 'wp_head', array( $adsns_plugin, 'adsns_head' ) );

// Adding 'BWS Plugins' admin menu
add_action( 'admin_menu', array( $adsns_plugin, 'adsns_add_admin_menu' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( $adsns_plugin, 'adsns_deactivate' ) );

// Activation hook
register_activation_hook( __FILE__, array( $adsns_plugin, 'adsns_activate' ) );
?>