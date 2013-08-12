<?php
/*
Plugin Name: Affiliate debugging
Description: Adds in a debug system to the affiliate plugin to help track down potential cookie issues
Author: Barry (Incsub)
Author URI: http://premium.wpmudev.org
*/

class affiliate_debugger {

	var $db;

	function __construct() {

		global $wpdb;

		$this->db =& $wpdb;

		// Add the debugging styles
		add_action('wp_head', array( &$this, 'add_affiliate_styles'), 99 );

		// Add the debugging message
		add_action('wp_footer', array( &$this, 'add_affiliate_notice'), 99 );
	}

	function affiliate_debugger() {
		$this->__construct();
	}

	function add_affiliate_styles() {
		?>
		<style type="text/css">
			#debugaffiliatefooter {
				position: fixed;
				width: 100%;
				min-height: 35px;
				bottom: 0px;
				left: 0px;
				background: #ffa82f;
			}

			#debugaffiliatefooter p {
				padding-left: 20px;
				padding-right: 20px;
				margin-top: 10px;
				margin-bottom: 5px;
			}
		</style>
		<?php
	}

	function add_affiliate_notice() {
		?>
		<div id='debugaffiliatefooter'>
			<p>
			<strong><?php _e('Affiliate Debug : ','affiliate'); ?></strong>
			<?php
				echo $this->debug_message();
			?>
			</p>
		</div>
		<?php
	}

	function debug_message() {


		if( isset( $_COOKIE['affiliate_' . COOKIEHASH] ) ) {
			$msg = __('I have recorded a cookie from <strong>','affiliate');

			$hash = addslashes($_COOKIE['affiliate_' . COOKIEHASH]);
			$user_id = $this->db->get_var( $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_hash' AND meta_value = %s", $hash) );

			if(!empty($user_id)) {
				$user = new WP_User( $user_id );
				$msg .= $user->user_login;
			}

			$msg .= __('</strong>. Any purchases will be assigned to that account for <strong>','affiliate') . AFFILIATE_COOKIE_DAYS . __('</strong> days after the click.' , 'affiliate');

			return $msg;
		}

		if( isset( $_COOKIE['noaffiliate_' . COOKIEHASH] ) ) {
			return __('The <strong>Not via an Affiliate</strong> cookie has been set. I am ignoring any future affiliate clicks for this browser session.','affiliate');
		}

		return __('No cookies are currently set - I am looking for cookies at : ','affiliate') . COOKIE_DOMAIN . COOKIEPATH;

	}

}

$affiliate_debugger = new affiliate_debugger();

?>