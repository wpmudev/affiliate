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
		$percentage = get_option('affiliate_mp_percentage', 0)
		// We have a referrer - get the total
		$total_amount = get_post_meta($order->ID, 'mp_order_total', true);
		// calculate the amount to give the referrer - hardcoded for testing to 30%
		$amount = ($total_amount / 100) * $percentage;
		// run the standard affiliate action to do the recording and assigning
		do_action('affiliate_purchase', $aff_id, $amount);
		// record the amount paid / assigned in the meta for the order
		add_post_meta($order->ID, 'affiliate_marketpress_order_paid', $amount, true);
	}

}
add_action( 'mp_order_paid', 'AM_Paid_order' );

function AM_Show_Affiliate_Settings( $settings ) {

	if (isset($_POST['gateway_settings'])) {
      // Do processing here
		if( !empty($_POST['affiliate_mp_percentage']) && $_POST['affiliate_mp_percentage'] > 0) {
			update_option( 'affiliate_mp_percentage', $_POST['affiliate_mp_percentage'] );
		} else {
			delete_option( 'affiliate_mp_percentage' );
		}
    }

	?>
		<div id="mp_gateways" class="postbox">
            <h3 class='hndle'><span><?php _e('Affiliate Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Set percentage to be paid to affiliates', 'affiliate') ?></th>
        				<td>
							<?php $percentage = get_option('affiliate_mp_percentage', 0); ?>
							<input type='text' name='affiliate_mp_percentage' value='<?php echo number_format($percentage, 2); ?>' style='width:5em;'/>&nbsp;<?php _e('%', 'affiliate'); ?>
                			<?php

                			?>
        				</td>
                </tr>
              </table>
            </div>
          </div>
	<?php
}
add_action('mp_gateway_settings', 'AM_Show_Affiliate_Settings');

?>