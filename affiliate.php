<?php
/*
Plugin Name: Affiliate
Plugin URI: http://premium.wpmudev.org/project/wordpress-mu-affiliate
Description: This plugin adds a simple affiliate system to your site. Track incoming clicks from affiliate referer links, Order tracking integration with MarketPress, Prosites paid signups and Membership paid signups.
Author: WPMU DEV
Version: 3.1.7-beta-1
Author URI: http://premium.wpmudev.org
WDP ID: 106
Domain Path: /affiliateincludes/languages
*/

require_once(plugin_dir_path( __FILE__ ) . 'affiliateincludes/includes/config.php');
require_once(plugin_dir_path( __FILE__ ) . 'affiliateincludes/includes/functions.php');
// Set up my location
set_affiliate_url(__FILE__);
set_affiliate_dir(__FILE__);

if(is_admin()) {
	include_once(plugin_dir_path( __FILE__ ) . 'affiliateincludes/includes/affiliate_admin_metaboxes.php');

	// Only include the administration side of things when we need to
	include_once(plugin_dir_path( __FILE__ ) . 'affiliateincludes/classes/affiliateadmin.php');
	include_once(plugin_dir_path( __FILE__ ) . 'affiliateincludes/classes/affiliatedashboard.php');

	$affadmin = new affiliateadmin();
	$affdash = new affiliatedashboard();
}

// Include the public and shortcode classes for both public and admin areas
include_once(plugin_dir_path( __FILE__ ) . 'affiliateincludes/classes/affiliatepublic.php');
include_once(plugin_dir_path( __FILE__ ) . 'affiliateincludes/classes/affiliateshortcodes.php');

$affiliate = new affiliate();
$affshortcode = new affiliateshortcodes();
