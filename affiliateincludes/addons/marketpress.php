<?php
/*
Plugin Name: MarketPress add-on
Description: Affiliate system plugin for the WordPress MarketPress plugin
Author: Barry (Incsub)
Author URI: http://premium.wpmudev.org
*/

//New order is a signup
function AM_New_order( $order ) {

	// Call the affiliate action
	do_action( 'affiliate_signup' );

	if(defined( 'AFFILIATEID' )) {
		// We found an affiliate that referred this order creation - so add a meta to the order recording it
		add_post_meta($order->ID, 'affiliate_marketpress_order_referrered', AFFILIATEID, true);
	}

}
add_action( 'mp_new_order', 'AM_New_order' );

// Paid order is a complete
function AM_Paid_order( $order ) {
	// Check for the affiliate referrer if there is one
	$aff_id = get_post_meta( $order->ID, 'affiliate_marketpress_order_referrered', true);

	if(!empty($aff_id)) {
		// We have a referrer - get the total
		$total_amount = get_post_meta($order->ID, 'mp_order_total', true);

		// calculate the amount to give the referrer
		$amount = $total_amount;

		do_action('affiliate_purchase', $aff_id, $amount);
	}

}
add_action( 'mp_order_paid', 'AM_Paid_order' );

?>