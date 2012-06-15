<?php
/*
Plugin Name: Affiliated debugging
Description: Adds in a debug system to the affiliate plugin to help track down potential cookie issues
Author: Barry (Incsub)
Author URI: http://premium.wpmudev.org
*/

class affiliate_debugger {


	function affiliate_debugger() {
		$this->__construct();
	}

	function __construct() {
		// Add the debugging styles
		add_action('wp_head', array( &$this, 'add_affiliate_styles'), 99 );

		// Add the debugging message
		add_action('wp_footer', array( &$this, 'add_affiliate_notice'), 99 );
	}

	function add_affiliate_styles() {

	}

	function add_affiliate_notice() {

	}


}

$affiliate_debugger = new affiliate_debugger();

?>