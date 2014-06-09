<?php
/*
Plugin Name: MarketPress
Description: Affiliate system plugin for the WordPress MarketPress plugin. Used to track purchases from affiliate referals. 
Author URI: http://premium.wpmudev.org/project/e-commerce/
Network: false
Depends: marketpress/marketpress.php
Class: MarketPress
*/

add_action( 'mp_shipping_process', 'affiliate_marketpress_record_order' );
add_action( 'mp_order_paid', 'affiliate_marketpress_paid_order' );
add_action( 'mp_single_order_display_box', 'affiliate_marketpress_display_metabox' );
add_action( 'mp_gateway_settings', 'affiliate_marketpress_settings' );

// Catch order status changes in MP
//add_action('order_received_to_trash', 'affiliate_marketpress_order_to_trash');

/*
function affiliate_marketpress_order_to_trash($order) {
	if (($order) && (isset($order->post_type)) && ($order->post_type == 'mp_order')) {
		//$order->post_content = maybe_unserialize($order->post_content);
		//echo "order<pre>"; print_r($order); echo "</pre>";
		
		if ((isset($order->mp_shipping_info['affiliate_referrer'])) && (!empty($order->mp_shipping_info['affiliate_referrer']))) {
			global $affadmin;
			$compete_records = $affadmin->get_complete_records($order->mp_shipping_info['affiliate_referrer'], false, 'paid:marketpress', $order->ID);
			echo "compete_records<pre>"; print_r($compete_records); echo "</pre>";
		}
		die();
	}
}
*/
function affiliate_marketpress_record_order() {

	if(!empty($_SESSION['mp_shipping_info'])) {
		global $affiliate;
		$affiliate_user_id = $affiliate->get_affiliate_user_id_from_hash();
		if ($affiliate_user_id) {
			$_SESSION['mp_shipping_info']['affiliate_referrer'] = $affiliate_user_id;
			//echo "_SESSION<pre>"; print_r($_SESSION); echo "</pre>";
		}
	}
}


// Paid order is a complete
function affiliate_marketpress_paid_order( $order ) {
	global $blog_id, $site_id;
	
	//echo "order<pre>"; print_r($order); echo "</pre>";

	//if (isset($order->post_content)) {
	//	echo "post_content<pre>"; print_r(unserialize($order_post_content)); echo "</pre>";
	//}
	// Check for the affiliate referrer if there is one
	$shipping_info = get_post_meta( $order->ID, 'mp_shipping_info', true);
	//echo "shipping_info<pre>"; print_r($shipping_info); echo "</pre>";
	//die();
	
	if(!isset($shipping_info['affiliate_referrer']))
		 return;

	$affiliate_user_id = $shipping_info['affiliate_referrer'];

	if(!empty($affiliate_user_id)) {
		
		// We have a referrer - get the total
		//$total_amount = get_post_meta($order->ID, 'mp_order_total', true);
		//echo "total_amount[". $total_amount ."]<br />";
		
		// From above we have the order total. It is passed to use in the $order data structure. 
		if (isset($order->mp_order_total))
			$total_amount = $order->mp_order_total;
		else
			$total_amount = get_post_meta($order->ID, 'mp_order_total', true);
		
		$total_amount = floatval($total_amount);
		//echo "total_amount before[". $total_amount ."]<br />";
		
		if ((isset($order->mp_shipping_total)) && (!empty($order->mp_shipping_total))) {
			$total_amount -= floatval($order->mp_shipping_total);
		}
		if ((isset($order->mp_tax_total)) && (!empty($order->mp_tax_total))) {
			$total_amount -= floatval($order->mp_tax_total);
		}
		
		//echo "total_amount after[". $total_amount ."]<br />";
		//die();


		$percentage = aff_get_option('affiliate_mp_percentage', 0);
		//echo "percentage[". $percentage ."]<br />";
		
		// calculate the amount to give the referrer - hardcoded for testing to 30%
		$amount = number_format(($total_amount / 100) * $percentage, 2);
		//echo "amount[". $amount ."]<br />";
		
		//die();
		
		$meta = array(
			'order_id'			=>	$order->ID,
			'order_amount'		=>	$total_amount,
			'commision_type'	=>	'percentage',
			'commision_rate'	=>	$percentage,
			'blog_id'			=>	$blog_id,
			'site_id'			=>	$site_id,
			'current_user_id'	=>	get_current_user_id(),
			'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
			'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
			'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
			//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
		);

		// run the standard affiliate action to do the recording and assigning
		$note = __('Affiliate payment for MarketPress order.', 'affiliate)');
		do_action('affiliate_purchase', $affiliate_user_id, $amount, 'paid:marketpress', $order->ID, $note, $meta);
		
		// record the amount paid / assigned in the meta for the order
		add_post_meta($order->ID, 'affiliate_marketpress_order_paid', $amount, true);
	}
	//die();
}

function affiliate_marketpress_display_metabox($order) {
	//echo "order<pre>"; print_r($order); echo "</pre>";
	
	if ((isset($order->mp_shipping_info['affiliate_referrer'])) && (!empty($order->mp_shipping_info['affiliate_referrer']))) {
	    ?>
		<div id="mp-order-notes-affiliate" class="postbox">
			<h3 class='hndle'><span><?php _e('Affiliate', 'affiliate'); ?></span> - <span class="description"><?php _e('This order was received via an Affiliate Referrer link.', 'affiliate'); ?></span></h3>
			<div class="inside">
			<?php
			//echo "order status[". $order->post_status ."]<br />";
			if ($order->post_status == "order_received") {
				?><p><?php _e("Affiliate information will be displayed when the order status is changed to 'paid'", 'affiliate'); ?></p><?php
			} else if ( ($order->post_status == 'order_paid') || ($order->post_status == 'order_shipped') || ($order->post_status == 'order_closed') ) {
				//echo $order->mp_shipping_info['affiliate_referrer']."<br />";
				//echo "order<pre>"; print_r($order); echo "</pre>";
				$user = new WP_User( intval($order->mp_shipping_info['affiliate_referrer']) );
				if ($user) {
					//echo "user<pre>"; print_r($user); echo "</pre>";
					if (affiliate_is_plugin_active_for_network()) {
						if (current_user_can('manage_network_options')) {
							?><p><?php _e('Affiliate User', 'affiliate') ?> <a href="<?php echo network_admin_url('admin.php?page=affiliatesadminmanage&subpage=details&id='. $user->ID); ?>"><?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)</a></p><?php
						} else {
							?><p><?php echo $user->display_name; ?></p><?php
						}
						
					} else {
						if (current_user_can('edit_others_posts')) {
							?><p><?php _e('Affiliate User', 'affiliate') ?> <a href="<?php echo admin_url('admin.php?page=affiliatesadminmanage&subpage=details&id='. $user->ID); ?>"><?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)</a></p><?php
						} else {
							?><p><?php echo $user->display_name; ?></p><?php
						}
					}
				
					if ((isset($_GET['order_id'])) && (!empty($_GET['order_id']))) {
						global $affadmin;
						$affadmin->show_complete_records_table($order->mp_shipping_info['affiliate_referrer'], false, array('paid:marketpress'), intval($_GET['order_id']));
					}
				} 
			} 		
			?>
			</div>
		</div>
		<?php
	}
}

function affiliate_marketpress_settings( $settings ) {

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
							<input type='text' name='affiliate_mp_percentage' value='<?php echo esc_attr(number_format($percentage, 2)); ?>' style='width:5em;'/>&nbsp;<?php _e('%', 'affiliate'); ?>
                			<?php

                			?>
        				</td>
                </tr>
              </table>
            </div>
          </div>
	<?php
}

