<?php

// Class of Google AdSense functions
class adsns {
	var $page_title;  // title for options page
	var $menu_title;  // name in menu
	var $adsns_options;
	 
	// Constructor
	function adsns() {
		$this->adsns_options = get_option( 'adsns_settings' );
		$this->adsns_options['code'] = stripslashes( $this->adsns_options['code'] );
		$this->adsns_options['num_show'] = 0;
		update_option( 'adsns_settings', $this->adsns_options );
	}

	// Show ads after post on a single page
	function adsns_end_post_ad( $content ) {
		global $adsns_count;
		$this->adsns_donate();  // Calling a donate function
		if ( ! is_feed() && is_single() && $adsns_count < $this->adsns_options[ 'max_ads' ] && $adsns_count < $this->adsns_options['max_homepostads'] ) {  // Checking if we are on a single page
			$content.= '<div id="end_post_ad" class="ads">' . $this->adsns_options['code'] . '</div>';  // Adding an ad code on page
			$this->adsns_options['num_show'] ++;  // Counting views
			update_option( 'adsns_settings', $this->adsns_options );
			$adsns_count = $this->adsns_options['num_show'];
		}
		return $content;
	}
	
	// Show ads after comment form
	function adsns_end_comment_ad() {
		global $adsns_count;
		$this->adsns_donate();
		if( ! is_feed() && $adsns_count < $this->adsns_options[ 'max_ads' ] && $adsns_count < $this->adsns_options['max_homepostads'] ) {
			echo '<div id="end_comment_ad" class="ads">' . $this->adsns_options['code'] . '</div>';
			$this->adsns_options['num_show'] ++;  // Counting views
			update_option( 'adsns_settings', $this->adsns_options );
			$adsns_count = $this->adsns_options['num_show'];
		}
	}
	
	// Show ads after post on home page
	function adsns_end_home_post_ad( $content ) {
		global $adsns_count;
		if ( $adsns_count < $this->adsns_options[ 'max_ads' ] && $adsns_count < $this->adsns_options['max_homepostads'] ) {
			if( ! is_feed() && ( is_home() || is_front_page() ) ) {
		//	if( ! is_feed() && is_home() ) {
				$this->adsns_donate();		// Calling a donate function
				$content .= '<div class="ads">' . $this->adsns_options['code'] . '</div>';
				$this->adsns_options['num_show'] ++;  // Counting views
				update_option( 'adsns_settings', $this->adsns_options );
				$adsns_count = $this->adsns_options['num_show'];		// restore count value
			}
		}
		return $content;
	}
	
	// Show ads in footer
	function adsns_end_footer_ad() {
		$this->adsns_donate();
		if( ! is_feed() && $adsns_count < $this->adsns_options[ 'max_ads' ] && $adsns_count < $this->adsns_options['max_homepostads'] ) {
			echo '<div id="end_footer_ad" class="ads">' . $this->adsns_options['code'] . '</div>';
			$this->adsns_options['num_show'] ++;  // Counting views
			update_option( 'adsns_settings', $this->adsns_options );
			$adsns_count = $this->adsns_options['num_show'];		// restore count value
		}
	}
	
	// Add 'BWS Plugins' menu at the left side in administer panel
	function adsns_add_admin_menu() {
		add_menu_page( 'BWS Plugins', 'BWS Plugins', 'manage_options', 'bws_plugins', 'bws_add_menu_render', plugins_url( "images/px.png", __FILE__ ), 1001 ); 
		add_submenu_page( 'bws_plugins', __( 'AdSense Settings', 'adsense'), __( 'AdSense', 'adsense' ), 'manage_options', "adsense-plugin.php", array( $this, 'adsns_settings_page' ) );
	}

	// Add a link for settings page
	function adsns_plugin_action_links( $links, $file ) {
		global $this_adsns_plugin;
		if ( $file == $this_adsns_plugin ){
			$settings_link = '<a href="admin.php?page=adsense-plugin.php">' . __( 'Settings', 'adsense' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	function adsns_register_plugin_links($links, $file) {
		global $this_adsns_plugin;
		if ( $file == $this_adsns_plugin ) {
			$links[] = '<a href="admin.php?page=adsense-plugin.php">' . __( 'Settings', 'adsense' ) . '</a>';
			$links[] = '<a href="http://wordpress.org/extend/plugins/adsense-plugin/faq/" target="_blank">' . __( 'FAQ', 'adsense' ) . '</a>';
			$links[] = '<a href="http://support.bestwebsoft.com">' . __( 'Support', 'adsense' ) . '</a>';
		}
		return $links;
	}

	// Creating a default options for showing ads. Starts on plugin activation.
	function adsns_activate()	{
		global $wpmu, $adsns_options, $count, $current_count, $adsns_count, $max_ads;		
		$new_options = array(
			'num_show' => '0',
			'donate' => '0',
			'max_ads' => '3',
			'max_homepostads' => '1',
			'clientid' => '',
			'clientid_prefix' => 'pub',
			'donate_id' => '1662250046693311',
			'adtypeselect' => 'text',			
			'donate_width' => '',
			'donate_height' => '',
			'default' => '468x60',
			'image_only' => '',
			'link_unit' => '',
			'adtype' => 'adunit',
			'corner_style' => 'none',
			'border' => '#FFFFFF',
			'title' => '#0000FF',
			'background' => '#FFFFFF',
			'text' => '#000000',
			'url' => '#008000',
			'palette' => 'Default Google pallete',
			'position' => 'homepostend',
			'code' => '	<script type="text/javascript">
						google_ad_client = "pub-1662250046693311";
						google_ad_width = 468;
						google_ad_height = 60;
						google_ad_format = "468x60_as";
						google_ad_type = "text";
						google_color_border = "#FFFFFF";
						google_color_bg = "#FFFFFF";
						google_color_link = "#0000FF";
						google_color_text = "#000000";
						google_color_url = "#008000";
						</script><input type="hidden" value="Version: 1.11" />',
			'widget_title' => ''
		);

		if ( 1 == $wpmu ) {
			if ( ! get_site_option( 'adsns_settings' ) ) {
				add_site_option( 'adsns_settings', $new_options, '', 'yes' );
			}
		} else {
			if ( ! get_option( 'adsns_settings' ) )
				add_option( 'adsns_settings', $new_options, '', 'yes' );
		}
		
		$count = 0; 							//current number of showed ads
		$current_count = 0; 					// tmp var for storing a number of already showed ads
		$adsns_count = 0; 						// number of posts on home page

		if ( 1 == $wpmu )
			$adsns_options = get_site_option( 'adsns_settings' ); 
		else
			$adsns_options = get_option( 'adsns_settings' );

		$adsns_options = array_merge( $new_options, $adsns_options );
		update_option( 'adsns_settings', $adsns_options );

		$max_ads = $adsns_options['max_ads'];			// max number of ads
	}
	
	// Donate settings
	function adsns_donate() {
		if ( $this->adsns_options['donate'] > 0 ) {
			$don = intval( 100/$this->adsns_options['donate'] );  // Calculating number of donate ads for showing
		}
		if ( $this->adsns_options['donate'] > 0 && $this->adsns_options['num_show'] % $don == 0) {  // Checking if now showing ad must be a donate ad
			$dimensions = explode( "x", $this->adsns_options['default'] );  // Calculating dimensions of ad block
			$this->adsns_options['donate_width'] = $dimensions[0];		// Width
			$this->adsns_options['donate_height'] = $dimensions[1];		// Height
			$don_code = '<script type="text/javascript">
						google_ad_client = "pub-' .$this->adsns_options['donate_id']. '";
						google_ad_width = ' .$this->adsns_options['donate_width']. ';
						google_ad_height = ' .$this->adsns_options['donate_height']. ';
						google_ad_format = "' .$this->adsns_options['default']. '_as";
						google_ad_type = "text";
						google_color_border = "' .$this->adsns_options['border']. '";
						google_color_bg = "' .$this->adsns_options['background']. '";
						google_color_link = "' .$this->adsns_options['title']. '";
						google_color_text = "' .$this->adsns_options['text']. '";
						google_color_url = "' .$this->adsns_options['url']. '";
						</script><script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></script><input type="hidden" value="Version: 1.11" />';	
			$this->adsns_options['code'] = $don_code;
			//update_option( 'adsns_settings', $this->adsns_options );
		} else {
			if( $this->adsns_options['adtype'] == 'ad_unit' ) {
				if($this->adsns_options['adtypeselect'] == 'default_image')
					$adtypeselect = 'default';
				else 
					$adtypeselect = $this->adsns_options['adtypeselect'];
				$dimensions = explode( "x", $this->adsns_options[ $adtypeselect ] );  // Calculating dimensions of ad block
				$format = $this->adsns_options[ $adtypeselect ];
				$format .= '_as';
				switch($this->adsns_options['adtypeselect']) {
						case 'image_only':
							$type = 'google_ad_type = "image";';
							break;
						case 'default_image':
							$type = 'google_ad_type = "text_image";';
							break;
						default:
							$type = 'google_ad_type = "text";';
							break;
				}
			} else {
				$dimensions = explode( "x", $this->adsns_options[ $this->adsns_options['adtype'] ] );  // Calculating dimensions of ad block
				$format = $this->adsns_options[ $this->adsns_options['adtype'] ];
				$format .= '_0ads_al';
				$type = '';
			}

			if ( 'none' == $this->adsns_options['corner_style'] ) {
				$features = '';
			} else {
				$features = 'google_ui_features = "rc:'.$this->adsns_options['corner_style'].'";';
			}
			$this->adsns_options['donate_width'] = $dimensions[0];		// Width
			$this->adsns_options['donate_height'] = $dimensions[1];		// Height
			$don_code = '<script type="text/javascript">
					google_ad_client = "'.$this->adsns_options['clientid_prefix'].'-' .$this->adsns_options['clientid'] . '";
					google_ad_width = ' . $this->adsns_options['donate_width'] . ';
					google_ad_height = ' . $this->adsns_options['donate_height'] . ';
					google_ad_format = "' . $format . '";
					' . $type . '
					google_color_border = "' . $this->adsns_options['border'] . '";
					google_color_bg = "' . $this->adsns_options['background'] . '";
					google_color_link = "' . $this->adsns_options['title'] . '";
					google_color_text = "' . $this->adsns_options['text'] . '";
					google_color_url = "' . $this->adsns_options['url'] . '";
					' . $features . '
					</script><script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></script><input type="hidden" value="Version: 1.11" />';			
			$this->adsns_options['code'] = $don_code;
			//update_option( 'adsns_settings', $this->adsns_options );
		}
	}
	
	// Function fo deactivation
	function adsns_deactivate(){
	}

	// Saving settings
	function adsns_settings_page(){
		// Run once
		if ( ! $adsns_options = get_option( 'adsns_settings' ) ){
				$this->adsns_activate();
		}
		echo '
		<div class="wrap" id="adsns_wrap">
		<div class="icon32 icon32-bws" id="icon-options-general"></div>
		<h2>' . $this->page_title . '</h2>
		';
		
		if ( isset( $_REQUEST['adsns_update'] ) && check_admin_referer( plugin_basename(__FILE__), 'adsns_nonce_name' )  ) { ### if click on Save Changes button

			if ( strlen( $_REQUEST['client_id'] ) > 0 ) {
				echo "<div class='updated'><p>".__( "Settings saved", 'adsense' )."</p></div>";
				
				if ( strlen( trim( $_REQUEST['clientid_prefix'] ) ) >= 3 && substr( trim( $_REQUEST['clientid_prefix'] ) , -3, 3 ) == 'pub' ) {
					$this->adsns_options['clientid_prefix'] = $_REQUEST['clientid_prefix'];					

					if ( isset( $_REQUEST['client_id'] ) ) { ## client
						$this->adsns_options['clientid'] = $_REQUEST['client_id'];					
					}
					if ( isset( $_REQUEST['mycode'] ) ) { ## ad code
						$id = stripslashes( $_REQUEST['mycode'] );
						if ( strlen( $id ) > 0 ) {
							//$this->adsns_options['code'] = $id;
						}
					}	 
					if ( isset( $_REQUEST['homeAds'] ) ) { ## select
						$this->adsns_options['max_homepostads'] = $_REQUEST['homeAds'];				
					}				
					if ( isset( $_REQUEST['adtypeselect'] ) ) { ## adtypeselect
						$this->adsns_options['adtypeselect'] = $_REQUEST['adtypeselect'];								
					} else {
						$this->adsns_options['adtypeselect'] = '';	
					}				
					if ( isset( $_REQUEST['default'] ) ) { ## format
						$this->adsns_options['default'] = $_REQUEST['default'];		
					} else {
						$this->adsns_options['default'] = '';	
					}
					if ( isset( $_REQUEST['image_only'] ) ) { ## format
						$this->adsns_options['image_only'] = $_REQUEST['image_only'];	
					} else {
						$this->adsns_options['image_only'] = '';	
					}				
					if ( isset( $_REQUEST['link_unit'] ) ) { ## format
						$this->adsns_options['link_unit'] = $_REQUEST['link_unit'];	
					} else {
						$this->adsns_options['link_unit'] = '';	
					}				
					if ( isset( $_REQUEST['adtype'] ) ) { ## adtype
						$this->adsns_options['adtype'] = $_REQUEST['adtype'];		
					}				
					if ( isset( $_REQUEST['corner_style'] ) ) { ## corner_style
						$this->adsns_options['corner_style'] = $_REQUEST['corner_style'];
					}				
					if ( isset( $_REQUEST['pallete'] ) ) { ## pallete
						$this->adsns_options['pallete'] = $_REQUEST['pallete'];	
					}				
					if ( isset( $_REQUEST['border'] ) ) { ## border
						$this->adsns_options['border'] = $_REQUEST['border'];		
					}
					if ( isset( $_REQUEST['title'] ) ) { ## title
						$this->adsns_options['title'] = $_REQUEST['title'];		
					}
					if ( isset( $_REQUEST['background'] ) ) { ## background
						$this->adsns_options['background'] = $_REQUEST['background'];
					}
					if ( isset( $_REQUEST['text'] ) ) { ## text
						$this->adsns_options['text'] = $_REQUEST['text'];		
					}
					if ( isset( $_REQUEST['url'] ) ) { ## url
						$this->adsns_options['url'] = $_REQUEST['url'];		
					}				
					if ( isset( $_REQUEST['position'] ) ) { ## position
						$this->adsns_options['position'] = $_REQUEST['position'];	
					}
					
					if ( isset( $_REQUEST['donate'] ) ) { ## donate
						$this->adsns_options['donate'] = $_REQUEST['donate'];	
					}
					if ( $this->adsns_options['adtype'] == 'ad_unit' ) {
						if ( $this->adsns_options['adtypeselect'] == 'default_image' )
							$adtypeselect = 'default';
						else 
							$adtypeselect = $this->adsns_options['adtypeselect'];

						$dimensions = explode( "x", $this->adsns_options[ $adtypeselect ] );  // Calculating dimensions of ad block
						$format = $this->adsns_options[ $adtypeselect ];
						$format .= '_as';
						switch($this->adsns_options['adtypeselect']) {
								case 'image_only':
									$type = 'google_ad_type = "image";';
									break;
								case 'default_image':
									$type = 'google_ad_type = "text_image";';
									break;
								default:
									$type = 'google_ad_type = "text";';
									break;
						}
					} else {
						$dimensions = explode( "x", $this->adsns_options[ $this->adsns_options['adtype'] ] );  // Calculating dimensions of ad block
						$format = $this->adsns_options[ $this->adsns_options['adtype'] ];
						$format .= '_0ads_al';
						$type = '';
					}

					$this->adsns_options['donate_width'] = $dimensions[0];		// Width
					$this->adsns_options['donate_height'] = $dimensions[1];		// Height
					$don_code = '<script type="text/javascript">
							google_ad_client = "'.$this->adsns_options['clientid_prefix'].'-' .$this->adsns_options['clientid'] . '";
							google_ad_width = ' . $this->adsns_options['donate_width'] . ';
							google_ad_height = ' . $this->adsns_options['donate_height'] . ';
							google_ad_format = "' . $format . '";
							' . $type . '
							google_color_border = "' . $this->adsns_options['border'] . '";
							google_color_bg = "' . $this->adsns_options['background'] . '";
							google_color_link = "' . $this->adsns_options['title'] . '";
							google_color_text = "' . $this->adsns_options['text'] . '";
							google_color_url = "' . $this->adsns_options['url'] . '";
							</script><script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></script><input type="hidden" value="Version: 1.11" />';			
					$this->adsns_options['code'] = $don_code;
					update_option( 'adsns_settings', $this->adsns_options );
				} else
					echo "<div class='error'><p>" . __( "Please enter valid Publisher ID.", 'adsense' ) . "</p></div>";
			} else
				echo "<div class='error'><p>" . __( "Please enter your Publisher ID.", 'adsense' ) . "</p></div>";
		} // Click on Save Changes button end	 
		$this->adsns_view_options_page();	 
		echo '</div>';
	}

	// Admin interface of plugin
	function adsns_view_options_page(){
		static $sp_nonce_flag = false;

		$this->adsns_options = get_option( 'adsns_settings' );
		?>  	
		<form id="option" name="option" action="" method="post">
			<div class="settings_head"> 
				<label for="color"> <?php _e( 'Network', 'adsense' ); ?> </label>
			</div>
			<div class="settings_body" id="network">
				<label for="client_id" class="left" ><?php _e( 'Publisher ID:', 'adsense' ); ?></label>
				<div class="right">
					<input type="text" id="clientid_prefix" name="clientid_prefix" size="8" maxlength="10" value="<?php echo $this->adsns_options['clientid_prefix'] ?>" />
					-
					<input type="hidden" id="client_id_val" name="client_id_val" value="<?php echo $this->adsns_options['clientid'] ?>" />
					<input type="text" id="client_id" name="client_id" class ="positive-integer" size="20" maxlength="16" value="<?php echo $this->adsns_options['clientid'] ?>" />
					<br />
					<div style="width: 250px; padding-left: 2px;">
						<span class="description"><?php _e( 'Publisher ID is a unique identifier of', 'adsense' ); ?> <a href="https://www.google.com/adsense"><?php _e( 'your account', 'adsense' ); ?></a> <?php _e( 'in Google AdSense.', 'adsense' ); ?></span>
					</div>
				</div>
			</div>
			
			<div class="settings_head"> 			
				<label for="adtype"><?php _e( 'Ad Type &amp; Format', 'adsense' ); ?></label>	
			</div>	
			<div class="settings_body">
				<label for="adtype" class="left"><?php _e( 'Type:', 'adsense' ); ?></label>
				<div class="right">
					<input type="hidden" id="adtype_val" value="<?php echo $this->adsns_options['adtype'] ?>">
					<input type="radio" name="adtype" id="ad_type1" value="ad_unit" <?php if( $this->adsns_options['adtype'] == 'ad_unit' || $this->adsns_options['adtype'] == 'adunit') echo 'checked="checked"'; ?>/>
					<label for="ad_type1"><?php _e( 'Ad block', 'adsense' ); ?></label>
					<input type="hidden" id="adtypesel_val" value="<?php echo $this->adsns_options['adtypeselect'] ?>">					
					<select id="adtypeselect" name ="adtypeselect" style="width: 168px; margin-left: 10px;">
						<option value="default_image" <?php if( $this->adsns_options['adtypeselect'] == 'default_image' || $this->adsns_options['adtypeselect'] == 'text_image') echo 'selected="selected"'; ?>><?php _e( 'Text and image ads', 'adsense' ); ?></option>
						<option value="default" <?php if( $this->adsns_options['adtypeselect'] == 'default' || $this->adsns_options['adtypeselect'] == 'text') echo 'selected="selected"'; ?>><?php _e( 'Text ads only (default)', 'adsense' ); ?></option>
						<option value="image_only" <?php if( $this->adsns_options['adtypeselect'] == 'image_only' || $this->adsns_options['adtypeselect'] == 'image') echo 'selected="selected"'; ?>><?php _e( 'Image ads only', 'adsense' ); ?></option>
					</select>
					<br />
					<input type="radio" name="adtype" id="ad_type2" value="link_unit" <?php if( $this->adsns_options['adtype'] == 'link_unit' ) echo 'checked="checked"'; ?>/>
					<label for="ad_type2"><?php _e( 'Block of links', 'adsense' ); ?></label>
				</div>
				<br />
				
				<label for="default" class="left"><?php _e( 'Format:', 'adsense' ); ?></label>
				<div class="right">
					<div id="def" <?php if($this->adsns_options['adtype'] == 'ad_unit' && ( $this->adsns_options['adtypeselect'] == 'default' || $this->adsns_options['adtypeselect'] == 'default_image' ) || $this->adsns_options['adtypeselect'] == 'text' || $this->adsns_options['adtypeselect'] == 'text_image' ) echo 'style="visibility: visible;"'; else echo 'style="visibility: hidden;"'; ?>> 
						<input type="hidden" id="default_val" value="<?php echo $this->adsns_options['default'] ?>">
						<select id="default" name="default">
							<optgroup label="Horizontal">
								<option value="728x90" <?php if( $this->adsns_options['default'] == '728x90' ) echo 'selected="selected"'; ?>>728x90 Leaderboard</option>
								<option value="468x60" <?php if( $this->adsns_options['default'] == '468x60' ) echo 'selected="selected"'; ?>>468x60 Banner</option>
								<option value="234x60" <?php if( $this->adsns_options['default'] == '234x60' ) echo 'selected="selected"'; ?>>234x60 Half Banner</option>
							</optgroup>
							<optgroup label="Vertical">
								<option value="120x600" <?php if( $this->adsns_options['default'] == '120x600' ) echo 'selected="selected"'; ?>>120x600 Skyscraper</option>
								<option value="160x600" <?php if( $this->adsns_options['default'] == '160x600' ) echo 'selected="selected"'; ?>>160x600 Wide Skyscraper</option>
								<option value="120x240" <?php if( $this->adsns_options['default'] == '120x240' ) echo 'selected="selected"'; ?>>120x240 Vertical Banner</option>
							</optgroup>
							<optgroup label="Square">
								<option value="336x280" <?php if( $this->adsns_options['default'] == '336x280' ) echo 'selected="selected"'; ?>>336x280 Large Rectangle</option>
								<option value="300x250" <?php if( $this->adsns_options['default'] == '300x250' ) echo 'selected="selected"'; ?>>300x250 Medium Rectangle</option>
								<option value="250x250" <?php if( $this->adsns_options['default'] == '250x250' ) echo 'selected="selected"'; ?>>250x250 Square</option>
								<option value="200x200" <?php if( $this->adsns_options['default'] == '200x200' ) echo 'selected="selected"'; ?>>200x200 Small Square</option>
								<option value="180x150" <?php if( $this->adsns_options['default'] == '180x150' ) echo 'selected="selected"'; ?>>180x150 Small Rectangle</option>
								<option value="125x125" <?php if( $this->adsns_options['default'] == '125x125' ) echo 'selected="selected"'; ?>>125x125 Button</option></optgroup>
						</select>
					</div>
				
					<div id="img_only" <?php if($this->adsns_options['adtype'] == 'ad_unit' && ( $this->adsns_options['adtypeselect'] == 'image_only' || $this->adsns_options['adtypeselect'] == 'image' ) ) echo 'style="visibility: visible;"'; else echo 'style="visibility: hidden;"'; ?> class="right_img">
						<input type="hidden" id="image_only_val" value="<?php echo $this->adsns_options['image_only'] ?>">
						<select id="image_only" name="image_only">
							<optgroup label="Horizontal">
								<option value="728x90" <?php if( $this->adsns_options['image_only'] == '728x90' ) echo 'selected="selected"'; ?>>728x90 Leaderboard</option>
								<option value="468x60" <?php if( $this->adsns_options['image_only'] == '468x60' ) echo 'selected="selected"'; ?>>468x60 Banner</option>
							</optgroup>
							<optgroup label="Vertical">
								<option value="120x600" <?php if( $this->adsns_options['image_only'] == '120x600' ) echo 'selected="selected"'; ?>>120x600 Skyscraper</option>
								<option value="160x600" <?php if( $this->adsns_options['image_only'] == '160x600' ) echo 'selected="selected"'; ?>>160x600 Wide Skyscraper</option>
							</optgroup>
							<optgroup label="Square">
								<option value="336x280" <?php if( $this->adsns_options['image_only'] == '336x280' ) echo 'selected="selected"'; ?>>336x280 Large Rectangle</option>
								<option value="300x250" <?php if( $this->adsns_options['image_only'] == '300x250' ) echo 'selected="selected"'; ?>>300x250 Medium Rectangle</option>
								<option value="250x250" <?php if( $this->adsns_options['image_only'] == '250x250' ) echo 'selected="selected"'; ?>>250x250 Square</option>
								<option value="200x200" <?php if( $this->adsns_options['image_only'] == '200x200' ) echo 'selected="selected"'; ?>>200x200 Small Square</option>
							</optgroup>
						</select>
					</div>

					<div id="lnk_unit" <?php if ( $this->adsns_options['adtype'] == 'link_unit' ) echo 'style="visibility: visible;margin-top: -28px"'; else echo 'style="visibility: hidden;margin-top: -28px"'; ?> class="right">
						<input type="hidden" id="link_unit_val" value="<?php echo $this->adsns_options['link_unit'] ?>">
						<select id="link_unit" name="link_unit">
							<optgroup label="Horizontal">
								<option value="728x15" <?php if( $this->adsns_options['link_unit'] == '728x15' ) echo 'selected="selected"'; ?>>728x15</option>
								<option value="468x15" <?php if( $this->adsns_options['link_unit'] == '468x15' ) echo 'selected="selected"'; ?>>468x15</option>
							</optgroup>
							<optgroup label="Square">
								<option value="200x90" <?php if( $this->adsns_options['link_unit'] == '200x90' ) echo 'selected="selected"'; ?>>200x90</option>
								<option value="180x90" <?php if( $this->adsns_options['link_unit'] == '180x90' ) echo 'selected="selected"'; ?>>180x90</option>
								<option value="160x90" <?php if( $this->adsns_options['link_unit'] == '160x90' ) echo 'selected="selected"'; ?>>160x90</option>
								<option value="120x90" <?php if( $this->adsns_options['link_unit'] == '120x90' ) echo 'selected="selected"'; ?>>120x90</option>
							</optgroup>
						</select>
					</div>
				</div>
			</div>
							
			<div class="settings_head">
				<label for="position"><?php _e( 'Position &amp; amount of ads', 'adsense' ); ?></label>
			</div>
			<div class="settings_body" id="pos_num">
				<label for="position" class="left"><?php _e( 'Position:', 'adsense' ); ?></label>
				<div class="right">
					<input type="hidden" id="position_val" value="<?php echo $this->adsns_options['position'] ?>">
					<select name="position" id="position">
						<option value="postend" <?php if( $this->adsns_options['position'] == 'postend' ) echo 'selected="selected"'; ?>><?php _e( 'Below the post (Single post page)', 'adsense' ); ?></option>
						<option value="homepostend" <?php if( $this->adsns_options['position'] == 'homepostend' ) echo 'selected="selected"'; ?>><?php _e( 'Below the post (Home page)', 'adsense' ); ?></option>
						<option value="homeandpostend" <?php if( $this->adsns_options['position'] == 'homeandpostend' ) echo 'selected="selected"'; ?>><?php _e( 'Below the post (Single post page and Home page)', 'adsense' ); ?></option>
						<option value="commentform" <?php if( $this->adsns_options['position'] == 'commentform' ) echo 'selected="selected"'; ?>><?php _e( 'Below the comment form', 'adsense' ); ?></option>
						<option value="footer" <?php if( $this->adsns_options['position'] == 'footer' ) echo 'selected="selected"'; ?>><?php _e( 'Above the footer', 'adsense' ); ?></option>
					</select>
				</div>
				<br />
				
				<label for="homeAds" class="left"><?php _e( 'Number of Ads:', 'adsense' ); ?></label>
				<div class="right">
					<input type="hidden" id="homeads_val" name="homeads_val" value="<?php echo $this->adsns_options['max_homepostads'] ?>" />		
					<select name="homeAds" id="homeAds" style="width: 40px;" />
						<option value="1" <?php if ( isset( $this->adsns_options['homeAds'] ) && $this->adsns_options['homeAds'] == '1' ) echo 'selected="selected"'; ?>>1</option>
						<option value="2" <?php if ( isset( $this->adsns_options['homeAds'] ) && $this->adsns_options['homeAds'] == '2' ) echo 'selected="selected"'; ?>>2</option>
						<option value="3" <?php if ( isset( $this->adsns_options['homeAds'] ) && $this->adsns_options['homeAds'] == '3' ) echo 'selected="selected"'; ?>>3</option>
					</select> 
				</div>
				<div style="width: 265px; padding-left: 2px;">
					<span class="description"><?php _e( 'Number of ads below the posts on the home page.', 'adsense' ); ?></span>
				</div>
			</div>
				
			<div class="settings_head"> 
				<label for="color"> <?php _e( 'Visualisation', 'adsense' ); ?> </label>
			</div>
			<div class="settings_body" id="visual">
				<label for="Border" class="left"><?php _e( 'Colors:', 'adsense' ); ?></label>
				<input type="hidden" id="border_val" value="<?php echo $this->adsns_options['border'] ?>">
				<input type="hidden" id="title_val" value="<?php echo $this->adsns_options['title'] ?>">
				<input type="hidden" id="background_val" value="<?php echo $this->adsns_options['background'] ?>">
				<input type="hidden" id="text_val" value="<?php echo $this->adsns_options['text'] ?>">
				<input type="hidden" id="url_val" value="<?php echo $this->adsns_options['url'] ?>">

				<table cellpadding="0" cellspacing="0" border="0" class="right">
					<tr class="paddings">
						<td align="right">
							<label for="Border"><?php _e( 'Border', 'adsense' ); ?>&nbsp;&nbsp;&nbsp;</label>
						</td>
						<td>
							<input type="text" id="Border" size="7" maxlength="7" name="border" value="<?php echo $this->adsns_options['border']; ?>" />
							<div id="colorpicker1" class="col_pal" ></div>					
							<div id="colorpicker2" class="col_pal" ></div>					
							<div id="colorpicker3" class="col_pal" ></div>				
							<div id="colorpicker4" class="col_pal" ></div>				
							<div id="colorpicker5" class="col_pal" ></div>					
						</td>
						<td>
						</td>
					</tr>
					<tr class="paddings">
						<td align="right">
							<label for="Title"><?php _e( 'Title', 'adsense' ); ?>&nbsp;&nbsp;&nbsp;</label>
						</td>
						<td>
							<input type="text" id="Title" size="7" maxlength="7" name="title" value="<?php echo $this->adsns_options['title']; ?>" />
						</td>
						<td>
						</td>
					</tr>
					<tr class="paddings">
						<td align="right">
							<label for="Background"><?php _e( 'Background', 'adsense' ); ?>&nbsp;&nbsp;&nbsp;</label>
						</td>
						<td>
							<input type="text" id="Background" size="7" maxlength="7" name="background" value="<?php echo $this->adsns_options['background']; ?>" />
						</td>
					</tr>
					<tr class="paddings">
						<td align="right">
							<label for="Text"><?php _e( 'Text', 'adsense' ); ?>&nbsp;&nbsp;&nbsp;</label>
						</td>
						<td>
							<input type="text" id="Text" size="7" maxlength="7" name="text" value="<?php echo $this->adsns_options['text']; ?>" />
						</td>
					</tr>
					<tr class="paddings">
						<td align="right">
							<label for="URL"><?php _e( 'URL', 'adsense' ); ?>&nbsp;&nbsp;&nbsp;</label>
						</td>
						<td>
							<input type="text" id="URL" size="7" maxlength="7" name="url" value="<?php echo $this->adsns_options['url']; ?>" />
						</td>
					</tr>
				</table>
				<br />
				<label for="pallete" class="left"><?php _e( 'Palette:', 'adsense' ); ?></label>
				<div class="right">
					<select id="pallete" name="pallete">
						<optgroup label="Default Pallete">
							<option value="Default Google pallete" <?php if( 'Default Google pallete' == $this->adsns_options['pallete'] ) echo 'selected="selected"';?>><?php _e( 'Default Google pallete', 'adsense' ); ?></option>
						</optgroup>
						<optgroup label="AdSense Pallete">
							<option value="Open Air" <?php if( 'Open Air' == $this->adsns_options['pallete'] ) echo 'selected="selected"';?>><?php _e( 'Open Air', 'adsense' ); ?></option>
							<option value="Seaside" <?php if( 'Seaside' == $this->adsns_options['pallete'] ) echo 'selected="selected"';?>><?php _e( 'Seaside', 'adsense' ); ?></option>
							<option value="Shadow" <?php if( 'Shadow' == $this->adsns_options['pallete'] ) echo 'selected="selected"';?>><?php _e( 'Shadow', 'adsense' ); ?></option>
							<option value="Blue Mix" <?php if( 'Blue Mix' == $this->adsns_options['pallete'] ) echo 'selected="selected"';?>><?php _e( 'Blue Mix', 'adsense' ); ?></option>
							<option value="Ink" <?php if( 'Ink' == $this->adsns_options['pallete'] ) echo 'selected="selected"';?>><?php _e( 'Ink', 'adsense' ); ?></option>
							<option value="Graphite" <?php if( 'Graphite' == $this->adsns_options['pallete'] ) echo 'selected="selected"';?>><?php _e( 'Graphite', 'adsense' ); ?></option>
						</optgroup>
					</select>
				</div>
				<div style="width: 250px; padding-left: 2px;">
					<span class="description"><?php _e( 'These are the standard Google color palettes.', 'adsense' ); ?></span>
				</div>
				<br />
				
				<label for="corner_style" class="left"><?php _e( 'Corner Style:', 'adsense' ); ?></label>
				<div class="right">
					<input type="hidden" id="corner_style_val" value="<?php echo $this->adsns_options['corner_style'] ?>">
					<select name="corner_style" id="corner_style">
						<option value="none" <?php if( 'none' == $this->adsns_options['corner_style'] ) echo 'selected="selected"';?>> <?php _e( 'Select a corner style', 'adsense' ); ?> </option>
						<option value="0" <?php if( '0' == $this->adsns_options['corner_style'] ) echo 'selected="selected"';?>> <?php _e( 'Square corners', 'adsense' ); ?> </option>
						<option value="6" <?php if( '6' == $this->adsns_options['corner_style'] ) echo 'selected="selected"';?>> <?php _e( 'Slightly rounded corners', 'adsense' ); ?> </option>
						<option value="10" <?php if( '10' == $this->adsns_options['corner_style'] ) echo 'selected="selected"';?>> <?php _e( 'Rounded corners', 'adsense' ); ?> </option>
					</select>
				</div>
				<div style="width: 250px; padding-left: 2px;">
					<span class="description"><?php _e( 'Corner style property will help you to make your Ad corners rounded.', 'adsense' ); ?></span>
				</div>
			</div>
			
			<div class="settings_head">
				<label for="position"><?php _e( 'Donations', 'adsense' ); ?></label>
			</div>
			<div class="settings_body" id="donate_menu">
				<label for="donate" class="left"><?php _e( 'Donate:', 'adsense' ); ?></label>
				<div class="right">
					<input type="hidden" id="donate_val" value="<?php echo $this->adsns_options['donate'] ?>">
					<input type="text" id="donate" size="2" maxlength="2" name="donate" style="padding-left: 10px; padding-right: 10px; text-align: center;" value="<?php echo $this->adsns_options['donate'] ?>" />%
					<br />
					<span class="description"><?php _e( 'Support us by Donating Ad Space.', 'adsense' ); ?></span>
					<br />
					<span class="description"><?php _e( 'Please enter a percentage value of the ad slots you are ready to share [Default: 0%].', 'adsense' ); ?></span>
				</div>
			</div>

			<div id="code_generate"> 
				<textarea id="mycode" name="mycode" rows="15" cols="60"></textarea>
				<input type="button" id="update" value="Update!" />
				<input type="button" id="generate" value="Generate!" />
				<div id="ads_generate"></div>
			</div>
			<div style="margin-top: 25px;" >
				<input type="submit" class="button-primary" name="adsns_update" id="adsns_update" value="<?php _e( 'Save Changes', 'adsense' ) ?>" />
			</div>
				<?php wp_nonce_field( plugin_basename(__FILE__), 'adsns_nonce_name' ); ?>
		</form>		
	<?php		
	}

	// Including scripts and stylesheets for admin interface of plugin
	public function adsns_write_admin_head() {
		wp_register_style( 'adsnsStylesheet', plugins_url( 'css/style.css' , __FILE__ ) );
		wp_enqueue_style( 'adsnsStylesheet' );

		if ( (is_admin() ) && ( isset( $_GET['page'] ) ) && ( $_GET['page'] == "adsense-plugin.php") ) {
			wp_register_script( 'adsns_admin_script', plugins_url( 'js/admin.js' , __FILE__ ) );
			wp_register_script( 'adsns_numeric_script', plugins_url( 'js/numeric.js' , __FILE__ ) );
			wp_register_script( 'adsns_farbtastic_script', plugins_url( 'farbtastic/farbtastic.js' , __FILE__ ) );
			
			wp_enqueue_script( 'adsns_admin_script' );
			wp_enqueue_script( 'adsns_numeric_script' );
			wp_enqueue_script( 'adsns_farbtastic_script' );
			
			wp_register_style( 'adsnsFarbtasticStylesheet', plugins_url( 'farbtastic/farbtastic.css' , __FILE__ ) ) ;
			wp_enqueue_style( 'adsnsFarbtasticStylesheet' );			
		}
		if ( isset( $_GET['page'] ) && $_GET['page'] == "bws_plugins" )
			wp_enqueue_script( 'bws_menu_script', plugins_url( 'js/bws_menu.js' , __FILE__ ) );
	}
	
	// Stylesheets for ads
	function adsns_head() {
		echo <<<EOF
		<style type="text/css">
		.ads {
			position: relative;
			text-align: center;		
			clear: both;
		}
		</style>		
EOF;
	}

	/*
	*displays AdSense in widget 
	*@return array()
	*/
	function adsns_widget_display() {
		global $adsns_count;
		$this->adsns_options = get_option( 'adsns_settings' );
		$title = $this->adsns_options['widget_title'];
		echo '<li class="widget-container"><h3 class="widget-title">'.$title.'</h3>';
		if ( $adsns_count < $this->adsns_options[ 'max_ads' ] ) { 
			$this->adsns_donate();
			echo '<div class="ads">' . $this->adsns_options['code'] . '</div>';
			$this->adsns_options['num_show']++;
			update_option( 'adsns_settings', $this->adsns_options );
			$adsns_count = $this->adsns_options['num_show'];
		}
		echo "</li>";
	}

	/*
	*Register widget for use in sidebars. 
	*Registers widget control callback for customizing options
	*/
	function adsns_register_widget() {
		wp_register_sidebar_widget(
			'adsns_widget',        // unique widget id
			'AdSense',          // widget name
			array( $this, 'adsns_widget_display' ),  // callback function
			array(                  // options
				'description' => 'Widget displays AdSense'
			)
		);
		wp_register_widget_control(
			'adsns_widget', // unique widget id
			'AdSense', // widget name
			array( $this, 'adsns_widget_control' ) // Callback function
		); 
	}

	/*
	*Registers widget control callback for customizing options
	*@return array
	*/
	function adsns_widget_control() {
		if( isset( $_POST["adsns-widget-submit"] ) ) {
			$this->adsns_options['widget_title'] = strip_tags( stripslashes( $_POST["adsns-widget-title"] ) );
			update_option( 'adsns_settings', $this->adsns_options );
		}
		$title = $this->adsns_options['widget_title'];
		echo '<p><label for="adsns-widget-title">'.__( 'Title', 'adsns' ).'<input class="widefat" id="adsns-widget-title" name="adsns-widget-title" type="text" value="'.$title.'" /></label></p>';
		echo '<input type="hidden" id="adsns-widget-submit" name="adsns-widget-submit" value="1" />';
	}		
} // class
?>