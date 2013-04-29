<?php
/*
Plugin Name: Membership add-on
Description: Affiliate system plugin for the WordPress Membership plugin
Author: Barry (Incsub)
Author URI: http://caffeinatedb.com
*/

add_action( 'user_register', 'affiliate_new_user' );
add_action( 'membership_add_subscription', 'affiliate_new_subscription', 10, 4 );

add_action( 'membership_subscription_form_after_levels', 'affiliate_membership_subscription_settings' );
add_action( 'membership_subscription_update', 'affiliate_membership_subscription_update');
add_action( 'membership_subscription_add', 'affiliate_membership_subscription_update');

function affiliate_new_user( $user_id ) {

	// Call the affiliate action
	do_action( 'affiliate_signup' );

	if(defined( 'AFFILIATEID' )) {
		// We found an affiliate that referred this blog creator
		if(function_exists('update_user_meta')) {
			update_user_meta($user_id, 'affiliate_referred_by', AFFILIATEID);
		} else {
			update_usermeta($user_id, 'affiliate_referred_by', AFFILIATEID);
		}

	}
}

function affiliate_new_subscription( $tosub_id, $tolevel_id, $to_order, $user_id ) {

	if(function_exists('get_user_meta')) {
		$aff = get_user_meta($user_id, 'affiliate_referred_by', true);
		$paid = get_user_meta($user_id, 'affiliate_paid', true);
	} else {
		$aff = get_usermeta($user_id, 'affiliate_referred_by');
		$paid = get_usermeta($user_id, 'affiliate_paid');
	}

	if(empty($aff)) $aff = false;

	if($aff && $paid != 'yes') {

		$whole = get_option( "membership_whole_payment_" . $tosub_id, 0);
		$partial = get_option( "membership_partial_payment_" . $tosub_id, 0);

		$type = get_option( "membership_payment_type_" . $tosub_id, 'actual' );

		switch( $type ) {
			case 'actual':		if(!empty($whole) || !empty($partial)) {
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

		do_action('affiliate_purchase', $aff, $amount);

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

?>