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
		add_action( 'wp_head', array( &$this, 'add_affiliate_styles' ), 99 );

		// Add the debugging message
		add_action( 'wp_footer', array( &$this, 'add_affiliate_notice' ), 99 );
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
				<strong><?php _e( 'Affiliate Debug : ', 'affiliate' ); ?></strong>
				<?php
				echo $this->debug_message();
				?>
			</p>
		</div>
		<?php
	}

	function debug_message() {


		if ( isset( $_COOKIE[ 'affiliate_' . COOKIEHASH ] ) ) {

			$hash    = addslashes( $_COOKIE[ 'affiliate_' . COOKIEHASH ] );
			$user_id = $this->db->get_var( $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_hash' AND meta_value = %s", $hash ) );

			$aff_user_login = "UNKNOWN";
			if ( ! empty( $user_id ) ) {
				$user = new WP_User( $user_id );
				if ( ( $user ) && ( ! empty( $user->user_login ) ) ) {
					$aff_user_login = $user->user_login;
				}
			}

			return sprintf( __( 'I have recorded a cookie for affiliate: <strong>%s</strong>. Any <strong>paid</strong> purchases/signup will be assigned the affiliate for <strong>%d</strong> days after the click.', 'affiliate' ), $aff_user_login, AFFILIATE_COOKIE_DAYS );
		}

		if ( isset( $_COOKIE[ 'noaffiliate_' . COOKIEHASH ] ) ) {
			return __( 'The <strong>Not via an Affiliate</strong> cookie has been set. This means you are accessing the site via a valid WordPress logged in user, or a guest. I am ignoring any future affiliate clicks for this browser session.', 'affiliate' );
		}

		return __( 'No cookies are currently set - I am looking for cookies at : ', 'affiliate' ) . COOKIE_DOMAIN . COOKIEPATH;

	}

}

$affiliate_debugger = new affiliate_debugger();

?>