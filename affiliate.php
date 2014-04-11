<?php
/*
Plugin Name: Affiliate
Plugin URI: http://premium.wpmudev.org/project/wordpress-mu-affiliate
Description: This plugin adds a simple affiliate system to your site. Track incoming clicks from affiliate referer links, Order tracking integration with MarketPress, Prosites paid signups and Membership paid signups.
Author: WPMU DEV
Version: 3.1.5.6
Author URI: http://premium.wpmudev.org
WDP ID: 106
*/

require_once('affiliateincludes/includes/config.php');
require_once('affiliateincludes/includes/functions.php');
// Set up my location
set_affiliate_url(__FILE__);
set_affiliate_dir(__FILE__);

if(is_admin()) {
	include_once('affiliateincludes/includes/affiliate_admin_metaboxes.php');

	// Only include the administration side of things when we need to
	include_once('affiliateincludes/classes/affiliateadmin.php');
	include_once('affiliateincludes/classes/affiliatedashboard.php');

	$affadmin = new affiliateadmin();
	$affdash = new affiliatedashboard();
}

// Include the public and shortcode classes for both public and admin areas
include_once('affiliateincludes/classes/affiliatepublic.php');
include_once('affiliateincludes/classes/affiliateshortcodes.php');

$affiliate = new affiliate();
$affshortcode = new affiliateshortcodes();
