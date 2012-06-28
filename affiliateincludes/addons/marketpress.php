<?php
/*
Plugin Name: MarketPress add-on
Description: Affiliate system plugin for the WordPress MarketPress plugin
Author: Barry (Incsub)
Author URI: http://premium.wpmudev.org
*/

function AM_Record_affiliate() {

	global $current_user;

	// Call the affiliate action
	do_action( 'affiliate_signup' );

	if(defined( 'AFFILIATEID' )) {
		// We found an affiliate that referred this order creation - so add a meta to the order recording it

		if(!empty($_SESSION['mp_shipping_info'])) {
			$_SESSION['mp_shipping_info']['affiliate_referrer'] = AFFILIATEID;
		}

	}

}
add_action( 'mp_shipping_process', 'AM_Record_affiliate' );

// Paid order is a complete
function AM_Paid_order( $order ) {
	// Check for the affiliate referrer if there is one
	$shipping_info = get_post_meta( $order->ID, 'mp_shipping_info', true);

	if(isset($shipping_info['affiliate_referrer'])) {
		$aff_id = $shipping_info['affiliate_referrer'];
	}

	if(!empty($aff_id)) {
		$percentage = aff_get_option('affiliate_mp_percentage', 0);
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
			aff_update_option( 'affiliate_mp_percentage', $_POST['affiliate_mp_percentage'] );
		} else {
			aff_delete_option( 'affiliate_mp_percentage' );
		}
    }

	?>
		<div id="mp_gateways" class="postbox">
            <h3 class='hndle'><span><?php _e('Affiliate Settings', 'mp') ?></span></h3>
            <div class="inside">
			  <span class="description"><?php _e('You can set the global commision amount paid to affiliates for referred purchases below. Set it to 0 for no payments.','affiliate'); ?></span>
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Set percentage to be paid to affiliates', 'affiliate') ?></th>
        				<td>
							<?php $percentage = aff_get_option('affiliate_mp_percentage', 0); ?>
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