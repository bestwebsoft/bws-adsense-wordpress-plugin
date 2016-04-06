<?php
/*
Plugin Name: Google AdSense by BestWebSoft
Plugin URI: http://bestwebsoft.com/products/
Description: This plugin allows implementing Google AdSense to your website.
Author: BestWebSoft
Text Domain: adsense-plugin
Domain Path: /languages
Version: 1.39
Author URI: http://bestwebsoft.com/
License: GPLv2 or later
*/

/*
	© Copyright 2016  BestWebSoft  ( http://support.bestwebsoft.com )

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

include_once( 'adsense-plugin.class.php' ); /* Including a class which contains a plugin functions */
$adsns_plugin =	new adsns(); /* Creating a variable with type of our class */

/* Function fo uninstall */
if ( ! function_exists( 'adsns_uninstall' ) ) {
	function adsns_uninstall() {
        if ( function_exists( 'is_multisite' ) && is_multisite() ) {
            global $wpdb;
            $old_blog = $wpdb->blogid;
            /* Get all blog ids */
            $blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
            foreach ( $blogids as $blog_id ) {
                switch_to_blog( $blog_id );
                delete_option( 'adsns_settings' );
            }
            switch_to_blog( $old_blog );
        } else {
            delete_option( 'adsns_settings' );
        }
        
        require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
        bws_include_init( plugin_basename( __FILE__ ) );
        bws_delete_plugin( plugin_basename( __FILE__ ) );
	}
}

/* Activation hook */
register_activation_hook( __FILE__, array( $adsns_plugin, 'adsns_activate' ) );
/* Adding 'BWS Plugins' admin menu */
add_action( 'admin_menu', array( $adsns_plugin, 'adsns_add_admin_menu' ) );
add_action( 'init', array( $adsns_plugin, 'adsns_plugin_init') );
/* Plugin localization */
add_action( 'plugins_loaded', array( $adsns_plugin, 'adsns_localization' ) );
add_action( 'admin_init', array( $adsns_plugin, 'adsns_plugin_admin_init') );
add_action( 'admin_enqueue_scripts', array( $adsns_plugin, 'adsns_write_admin_head' ) );
/* Action for adsns_show_ads */
add_action( 'after_setup_theme', array( $adsns_plugin, 'adsns_show_ads' ) );
/* Display the plugin widget */
add_action( 'widgets_init', array( $adsns_plugin, 'adsns_register_widget' ) );
/* Adding ads stylesheets */
add_action( 'wp_enqueue_scripts', array( $adsns_plugin, 'adsns_head' ) );
/* Add "Settings" link to the plugin action page */
add_filter( 'plugin_action_links', array( $adsns_plugin, 'adsns_plugin_action_links'), 10, 2 );
/* Additional links on the plugin page */
add_filter( 'plugin_row_meta', array( $adsns_plugin, 'adsns_register_plugin_links'), 10, 2 );
/* Display notices */
add_action( 'admin_notices', array( $adsns_plugin, 'adsns_plugin_notice') );
/* When uninstall plugin */
register_uninstall_hook( __FILE__, 'adsns_uninstall' );