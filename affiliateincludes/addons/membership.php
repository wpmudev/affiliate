<?php
/*
Plugin Name: Membership
Description: Affiliate system plugin for the WordPress Membership plugin
Author URI: http://premium.wpmudev.org/project/membership/
Depends: membership/membershippremium.php
*/

add_action( 'membership_add_subscription', 						'affiliate_membership_new_subscription', 10, 4 );
add_action( 'membership_subscription_form_after_levels', 		'affiliate_membership_subscription_settings' );
add_action( 'membership_subscription_update', 					'affiliate_membership_subscription_update');
add_action( 'membership_subscription_add', 						'affiliate_membership_subscription_update');


function affiliate_membership_new_subscription( $tosub_id, $tolevel_id, $to_order, $user_id ) {
	global $blog_id, $site_id;

//	echo 'in '. __FILE__ .': '. __FUNCTION__ .': '. __LINE__ .'<br />';
//	echo "tosub_id[". $tosub_id ."]<br />";
//	echo "tolevel_id[". $tolevel_id ."]<br />";
//	echo "to_order[". $to_order ."]<br />";
//	echo "user_id[". $user_id ."]<br />";

	if(function_exists('get_user_meta')) {
		$aff = get_user_meta($user_id, 'affiliate_referred_by', true);
		$paid = get_user_meta($user_id, 'affiliate_paid', true);
	} else {
		$aff = get_usermeta($user_id, 'affiliate_referred_by');
		$paid = get_usermeta($user_id, 'affiliate_paid');
	}
	
	// Was this a referal?
	if(empty($aff)) $aff = false;

	if($aff && $paid != 'yes') {

		$whole = get_option( "membership_whole_payment_" . $tosub_id, 0);
		//echo "whole[". $whole ."]<br />";/
		
		$partial = get_option( "membership_partial_payment_" . $tosub_id, 0);
		//echo "partial[". $partial ."]<br />";
		
		$type = get_option( "membership_payment_type_" . $tosub_id, 'actual' );
		
		switch( $type ) {
			case 'actual':		
				if(!empty($whole) || !empty($partial)) {
					$amount = $whole . '.' . $partial;
				} else {
					$amount = 0;
				}
				break;

			case 'percentage':	// Calculate the charge for this subscription / level / order
				$sub = new M_Subscription( $tosub_id );
				$level = $sub->get_level_at( $tolevel_id, $to_order );

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

		$meta = array(
			'tosub_id'			=>	$tosub_id, 
			'tolevel_id'		=>	$tolevel_id, 
			'to_order'			=>	$to_order, 
			'user_id'			=>	$user_id,
			'blog_id'			=>	$blog_id,
			'site_id'			=>	$site_id,
			'current_user_id'	=>	get_current_user_id(),
			'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
			'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
			'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
			//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
		);
		do_action('affiliate_purchase', $aff, $amount, 'paid:membership', $user_id, __('Membership', 'affiliate'), $meta);
		
		if(defined('AFFILIATE_PAYONCE') && AFFILIATE_PAYONCE == 'yes') {

			if(function_exists('update_user_meta')) {
				update_user_meta($user_id, 'affiliate_paid', 'yes');
			} else {
				update_usermeta($user_id, 'affiliate_paid', 'yes');
			}
		}
	}
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
		<option value='actual' <?php selected( $membership_payment_type, 'actual');  ?>><?php echo esc_html($M_options['paymentcurrency']); ?></option>
		<option value='percentage' <?php selected( $membership_payment_type, 'percentage');  ?>><?php _e('%','membership'); ?></option>
	</select>
	</div>
	<?php
}
