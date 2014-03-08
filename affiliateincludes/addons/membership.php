<?php
/*
Plugin Name: Membership
Description: Affiliate system plugin for the WordPress Membership plugin
Author URI: http://premium.wpmudev.org/project/membership/
Depends: membership/membershippremium.php
Class: M_Membership
*/


add_action( 'membership_payment_processed', 					'affiliate_membership_payment_processed', 10, 5);
add_action( 'membership_add_subscription', 						'affiliate_membership_add_subscription', 10, 4 );

add_action( 'membership_subscription_form_after_levels', 		'affiliate_membership_subscription_settings' );
add_action( 'membership_subscription_update', 					'affiliate_membership_subscription_update');
add_action( 'membership_subscription_add', 						'affiliate_membership_subscription_update');


function affiliate_membership_payment_processed( $m_user_id, $m_sub_id, $m_amount, $m_currency, $m_txn_id ) {
	global $blog_id, $site_id;
	
	$default_headers = array(
		'Name' 				=> 	'Plugin Name',
		'Version'			=>	'Version'
	);
	
	$membership_plugin_base = 'membership/membershippremium.php';
	if ( file_exists(WPMU_PLUGIN_DIR .'/'. $membership_plugin_base)) {
		$membership_plugin_file = WPMU_PLUGIN_DIR .'/'. $membership_plugin_base;
	} else if ( file_exists(WP_PLUGIN_DIR .'/'. $membership_plugin_base)) {
		$membership_plugin_file = WP_PLUGIN_DIR .'/'. $membership_plugin_base;
	}
	$plugin_data = get_file_data( $membership_plugin_file, $default_headers, 'plugin');
	//echo "plugin_data<pre>"; print_r($plugin_data); echo "</pre>";
	
	if (isset($plugin_data['Version'])) {
		if (version_compare($plugin_data['Version'], '3.4.9.9', '<')) {
			//echo "Membersip less than 3.5<br />";
			return;
		} 
	}
	
	//$a_debug = true;

	//if ($a_debug) {
	//	$fp = fopen($_SERVER['DOCUMENT_ROOT']. '/_affiliate_data_'. $m_user_id .'.txt', 'a');
	//	fwrite($fp, "m_user_id[". $m_user_id ."]\r\n");
	//	fwrite($fp, "m_sub_id[". $m_sub_id ."]\r\n");
	//	fwrite($fp, "m_amount[". $m_amount ."]\r\n");
	//	fwrite($fp, "m_currency[". $m_currency ."]\r\n");
	//	fwrite($fp, "m_txn_id[". $m_txn_id ."]\r\n");
	//	fwrite($fp, "_POST<pre>". print_r($_POST, true). "</pre>");
	//}
	
	// If we don't have an affiliate referred by then not an affiliate commission
	$affiliate_referred_by = get_user_meta($m_user_id, 'affiliate_referred_by', true);
	//if ($a_debug) { fwrite($fp, "affiliate_referred_by[". $affiliate_referred_by ."]\r\n"); }
	if (empty($affiliate_referred_by)) {
		//if ($a_debug) { 
		//	fwrite($fp, "affiliate_referred_by is EMPTY\r\n"); 
		//	fclose($fp);
		//}
		return;
	}
	
	// IF we have Affiliate set to PAYPONCE and the affiliate has been paid. Then nothing to give here.
	$affiliate_paid = get_user_meta($m_user_id, 'affiliate_paid', true);
	//if ($a_debug) { fwrite($fp, "affiliate_paid[". $affiliate_paid ."]\r\n"); }
	if ((defined('AFFILIATE_PAYONCE')) && (AFFILIATE_PAYONCE == 'yes') && ($affiliate_paid == 'yes')) {
		//if ($a_debug) { 
		//	fwrite($fp, "affiliate already PAYONCE\r\n");
		//	fclose($fp);
		//}
		return;
	}
	
	$whole = get_option( "membership_whole_payment_" . $m_sub_id, 0);
	//if ($a_debug) { fwrite($fp, "whole[". $whole ."]\r\n"); } 
	//echo "whole[". $whole ."]<br />";
		
	$partial = get_option( "membership_partial_payment_" . $m_sub_id, 0);
	//if ($a_debug) { fwrite($fp, "partial[". $partial ."]\r\n"); } 
	//echo "partial[". $partial ."]<br />";
		
	$type = get_option( "membership_payment_type_" . $m_sub_id, 'actual' );
	//if ($a_debug) { fwrite($fp, "type[". $type ."]\r\n"); } 
	//echo "type[". $type ."]<br />";
		
	switch( $type ) {
		case 'actual':		
			if(!empty($whole) || !empty($partial)) {
				$amount = $whole . '.' . $partial;
			} else {
				$amount = 0;
			}
			break;

		case 'percentage':	// Calculate the charge for this subscription / level / order
			$floatprice = floatval( $m_amount );
			$floatpercentage = floatval( $percentage );

			if( $floatprice > 0 && $floatpercentage > 0 ) {
				// We have a positive value to check against
				$amount = ($floatprice / 100) * $floatpercentage;
				$amount = round($amount, 2, PHP_ROUND_HALF_DOWN);
			} else {
				$amount = 0;
			}
			break;
	}
	//if ($a_debug) {
	//	fwrite($fp, "amount[". $amount ."]\r\n");
	//}
	//echo "amount[". $amount ."]<br />";
		
	$meta = array(
		'tosub_id'			=>	$m_user_id, 
		'tolevel_id'		=>	$m_sub_id, 
		'amount'			=>	$m_amount,
		'blog_id'			=>	$blog_id,
		'site_id'			=>	$site_id,
		'current_user_id'	=>	get_current_user_id(),
		'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
		'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
		'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
		'current_user_id'	=>	get_current_user_id(),
		'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
		'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
		'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
	);
	do_action('affiliate_purchase', $affiliate_referred_by, $amount, 'paid:membership', $m_user_id, __('Membership', 'affiliate'), $meta);
	if(defined('AFFILIATE_PAYONCE') && AFFILIATE_PAYONCE == 'yes') {
		update_user_meta($user_id, 'affiliate_paid', 'yes');
	}
	//if ($a_debug) {
	//	fclose($fp);
	//}
}


function affiliate_membership_add_subscription( $m_tosub_id, $m_tolevel_id, $m_to_order, $m_user_id ) {
	global $blog_id, $site_id;

	//echo "m_tosub_id[". 	$m_tosub_id ."]<br />";
	//echo "m_tolevel_id[". 	$m_tolevel_id ."]<br />";
	//echo "m_to_order[". 	$m_to_order ."]<br />";
	//echo "m_user_id[". 		$m_user_id ."]<br />";

	$default_headers = array(
		'Name' 				=> 	'Plugin Name',
		'Version'			=>	'Version'
	);
	
	$membership_plugin_base = 'membership/membershippremium.php';
	if ( file_exists(WPMU_PLUGIN_DIR .'/'. $membership_plugin_base)) {
		$membership_plugin_file = WPMU_PLUGIN_DIR .'/'. $membership_plugin_base;
	} else if ( file_exists(WP_PLUGIN_DIR .'/'. $membership_plugin_base)) {
		$membership_plugin_file = WP_PLUGIN_DIR .'/'. $membership_plugin_base;
	}
	$plugin_data = get_file_data( $membership_plugin_file, $default_headers, 'plugin');
	//echo "plugin_data<pre>"; print_r($plugin_data); echo "</pre>";
	
	if (isset($plugin_data['Version'])) {
		if (version_compare($plugin_data['Version'], '3.4.9.9', '>')) {
			//echo "Membersip 3.5 or more<br />";
			return;
		} 
	}
 
	//$aff = get_user_meta($user_id, 'affiliate_referred_by', true);
	$affiliate_referred_by = get_user_meta($m_user_id, 'affiliate_referred_by', true);
	//echo "affiliate_referred_by[". $affiliate_referred_by ."]<br />";
	if (empty($affiliate_referred_by)) {
		return;
	}
	
	//$paid = get_user_meta($user_id, 'affiliate_paid', true);
	$affiliate_paid = get_user_meta($m_user_id, 'affiliate_paid', true);
	//echo "affiliate_paid[". $affiliate_paid ."]<br />";
	
	if ((defined('AFFILIATE_PAYONCE')) && (AFFILIATE_PAYONCE == 'yes') && ($affiliate_paid == 'yes')) {
		return;
	}

	$whole = get_option( "membership_whole_payment_" . $m_tosub_id, 0);
	//echo "whole[". $whole ."]<br />";
		
	$partial = get_option( "membership_partial_payment_" . $m_tosub_id, 0);
	//echo "partial[". $partial ."]<br />";
		
	$type = get_option( "membership_payment_type_" . $m_tosub_id, 'actual' );
	//echo "type[". $type ."]<br />";
	//die();
		
	switch( $type ) {
		case 'actual':		
			if(!empty($whole) || !empty($partial)) {
				$amount = $whole . '.' . $partial;
			} else {
				$amount = 0;
			}
			break;

		case 'percentage':	// Calculate the charge for this subscription / level / order
			$sub = new M_Subscription( $m_tosub_id );
			$level = $sub->get_level_at( $m_tolevel_id, $m_to_order );

			if(!empty($level)) {
				// We have a level so we need to get the charge
				$percentage = $whole . '.' . $partial;
				$levelprice = $level->level_price;

				$floatprice = floatval( $levelprice );
				$floatpercentage = floatval( $percentage );

				if( $floatprice > 0 && $floatpercentage > 0 ) {
					// We have a positive value to check against
					$amount = ($floatprice / 100) * $floatpercentage;
					$amount = round($amount, 2, PHP_ROUND_HALF_DOWN);
				} else {
					$amount = 0;
				}
			} else {
				$amount = 0;
			}
			break;
	}
	//echo "amount[". $amount ."]<br />";
		
	$meta = array(
		'tosub_id'			=>	$m_tosub_id, 
		'tolevel_id'		=>	$m_tolevel_id, 
		'to_order'			=>	$m_to_order, 
		'user_id'			=>	$m_user_id,
		'blog_id'			=>	$blog_id,
		'site_id'			=>	$site_id,
		'current_user_id'	=>	get_current_user_id(),
		'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
		'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
		'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
		//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
	);
	do_action('affiliate_purchase', $affiliate_referred_by, $amount, 'paid:membership', $m_user_id, __('Membership', 'affiliate'), $meta);
		
	if(defined('AFFILIATE_PAYONCE') && AFFILIATE_PAYONCE == 'yes') {

		if(function_exists('update_user_meta')) {
			update_user_meta($m_user_id, 'affiliate_paid', 'yes');
		} else {
			update_usermeta($m_user_id, 'affiliate_paid', 'yes');
		}
	}
}

function affiliate_membership_get_subscription_levels() {
	static $subscriptions = '';
	if (!$subscriptions) {
		if (class_exists('M_Communication')) {
			$comm = new M_Communication(false);
			$subscriptions = $comm->get_active_subscriptions();
		}
	}
	//echo "subscriptions<pre>"; print_r($subscriptions); echo "</pre>";
	return $subscriptions;
}

function affiliate_membership_subscription_update( $sub_id ) {
	//echo "in ". __FILE__ .": ". __FUNCTION__ .": ". __LINE__ ."<br />";
	update_option( "membership_whole_payment_" . $sub_id, (int) $_POST['membership_whole_payment'] );
	update_option( "membership_partial_payment_" . $sub_id, (int) $_POST['membership_partial_payment'] );
	update_option( "membership_payment_type_" . $sub_id, $_POST['membership_payment_type'] );
}

function affiliate_membership_subscription_settings( $sub_id ) {

	global $M_options;

	?>
	<h3><?php _e('Affiliate settings','affiliate'); ?></h3>
	<div class='sub-details'>

	<?php
		$membership_currency = $M_options['paymentcurrency'];
		$affiliate_currency = aff_get_option('affiliate-currency-paypal-masspay', 'USD');
		if ($membership_currency != $affiliate_currency) {
			?><p class="error"><?php echo sprintf(__('Currency mismatch. Your Membership currency is set to <strong>%s</strong> but Affiliate currency is set to <strong>%s</strong>. Please ensure both are set correctly.'), $membership_currency, $affiliate_currency) ?></p><?php
		}
	?>
	<label for='aff_pay'><?php _e('Affiliate payment credited for a signup on this subscription','management'); ?></label>
	<select name="membership_whole_payment">
	<?php
		$membership_whole_payment = get_option( "membership_whole_payment_" . $sub_id );
		$counter = 0;
		for ( $counter = 0; $counter <= MEMBERSHIP_MAX_CHARGE; $counter += 1) {
            echo '<option value="' . $counter . '"' . ($counter == $membership_whole_payment ? ' selected' : '') . '>' . $counter . '</option>' . "\n";
		}
    ?>
    </select>
    .
	<select name="membership_partial_payment">
	<?php
		$membership_partial_payment = get_option( "membership_partial_payment_" . $sub_id );
		$counter = 0;
        echo '<option value="00"' . ('00' == $membership_partial_payment ? ' selected' : '') . '>00</option>' . "\n";
		for ( $counter = 1; $counter <= 99; $counter += 1) {
			if ( $counter < 10 ) {
				$number = '0' . $counter;
			} else {
				$number = $counter;
			}
            echo '<option value="' . $number . '"' . ($number == $membership_partial_payment ? ' selected' : '') . '>' . $number . '</option>' . "\n";
		}
    ?>
    </select>
	&nbsp;
	<?php
	$membership_payment_type = get_option( "membership_payment_type_" . $sub_id, 'actual' );
	?>
	<select name="membership_payment_type">
		<option value='actual' <?php selected( $membership_payment_type, 'actual');  ?>><?php echo esc_html($affiliate_currency); ?></option>
		<option value='percentage' <?php selected( $membership_payment_type, 'percentage');  ?>><?php _e('%','membership'); ?></option>
	</select>
	</div>
	<?php
}
