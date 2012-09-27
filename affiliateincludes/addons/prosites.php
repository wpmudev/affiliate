<?php

add_action('wpmu_new_blog', 'affiliate_new_blog', 10, 2);
add_action('supporter_payment_processed', 'affiliate_supporter_paid', 10, 3);
add_action('affililate_settings_form', 'affiliate_supporter_payment_settings');
add_action('affililate_settings_form_update', 'affiliate_supporter_payment_settings_update');
add_filter( 'blog_template_exclude_settings', 'affiliate_supporter_new_blog_template_exclude' );

/*
 * Exclude option from New Blog Template plugin copy
 */
function affiliate_supporter_new_blog_template_exclude( $and ) {
	$and .= " AND `option_name` != 'affiliate_referred_by' AND `option_name` != 'affiliate_paid' AND `option_name` != 'affiliate_referrer' ";
	return $and;
}


function affiliate_new_blog( $blog_id, $user_id ) {

	// Call the affiliate action
	do_action( 'affiliate_signup' );

	if(defined( 'AFFILIATEID' )) {
		// We found an affiliate that referred this blog creator
		if(function_exists('update_blog_option')) {
			update_blog_option( $blog_id, 'affiliate_referred_by', AFFILIATEID );
		}

		if(function_exists('update_user_meta')) {
			update_user_meta($user_id, 'affiliate_referred_by', AFFILIATEID);
		} else {
			update_usermeta($user_id, 'affiliate_referred_by', AFFILIATEID);
		}

	}

}

function affiliate_supporter_paid($bid, $amount, $supporterperiod) {

	if(function_exists('get_site_option')) {
		$getoption = 'get_site_option';
	} else {
		$getoption = 'get_option';
	}

	// Check if the blog is from an affiliate
	if(function_exists('get_blog_option')) {
		$aff = get_blog_option( $bid, 'affiliate_referred_by', false );
		$paid = get_blog_option( $bid, 'affiliate_paid', 'no' );
	} else {
		$aff = false;
	}

	if($aff && $paid != 'yes') {

		switch($supporterperiod) {

			case '1':	$amount = $getoption( "supporter_1_whole_payment", 0 ) . '.' . $getoption( "supporter_1_partial_payment", 0 );
						break;
			case '3':	$amount = $getoption( "supporter_3_whole_payment", 0 ) . '.' . $getoption( "supporter_3_partial_payment", 0 );
						break;
			case '12':	$amount = $getoption( "supporter_12_whole_payment", 0 ) . '.' . $getoption( "supporter_12_partial_payment", 0 );
						break;
			case 'bulk':	$amount = $getoption( "supporter_bulk_whole_payment", 0 ) . '.' . $getoption( "supporter_bulk_partial_payment", 0 );
						break;
			default:
						$amount = 0;
						break;
		}

		do_action('affiliate_purchase', $aff, $amount);

		if(defined('AFFILIATE_PAYONCE') && AFFILIATE_PAYONCE == 'yes') {

			if(function_exists('update_blog_option')) {
				update_blog_option( $bid, 'affiliate_paid', 'yes' );
			}

		}

	}
}

function affiliate_supporter_payment_settings() {

	if(function_exists('get_site_option')) {
		$getoption = 'get_site_option';
	} else {
		$getoption = 'get_option';
	}

	echo '<h3>' . __('Supporter payments settings', 'affiliate') . '</h3>';

	echo '<table class="form-table">';
	echo '<tr>';
	echo '<th scope="row" valign="top">' . __('1 Month payment', 'affiliate') . '</th>';
	echo '<td valign="top">'; ?>
		<select name="supporter_1_whole_payment">
		<?php
			$supporter_1_whole_payment = $getoption( "supporter_1_whole_payment" );
			$counter = 0;
			for ( $counter = 1; $counter <= 300; $counter += 1) {
                echo '<option value="' . $counter . '"' . ($counter == $supporter_1_whole_payment ? ' selected' : '') . '>' . $counter . '</option>' . "\n";
			}
        ?>
        </select>
        .
		<select name="supporter_1_partial_payment">
		<?php
			$supporter_1_partial_payment = $getoption( "supporter_1_partial_payment" );
			$counter = 0;
            echo '<option value="00"' . ('00' == $supporter_1_partial_payment ? ' selected' : '') . '>00</option>' . "\n";
			for ( $counter = 1; $counter <= 99; $counter += 1) {
				if ( $counter < 10 ) {
					$number = '0' . $counter;
				} else {
					$number = $counter;
				}
                echo '<option value="' . $number . '"' . ($number == $supporter_1_partial_payment ? ' selected' : '') . '>' . $number . '</option>' . "\n";
			}
        ?>
        </select>
        <br /><?php _e('Affiliate payment for one month.');
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row" valign="top">' . __('3 Month payment', 'affiliate') . '</th>';
	echo '<td valign="top">'; ?>
		<select name="supporter_3_whole_payment">
		<?php
			$supporter_3_whole_payment = $getoption( "supporter_3_whole_payment" );
			$counter = 0;
			for ( $counter = 1; $counter <= 300; $counter += 1) {
                echo '<option value="' . $counter . '"' . ($counter == $supporter_3_whole_payment ? ' selected' : '') . '>' . $counter . '</option>' . "\n";
			}
        ?>
        </select>
        .
		<select name="supporter_3_partial_payment">
		<?php
			$supporter_3_partial_payment = $getoption( "supporter_3_partial_payment" );
			$counter = 0;
            echo '<option value="00"' . ('00' == $supporter_3_partial_payment ? ' selected' : '') . '>00</option>' . "\n";
			for ( $counter = 1; $counter <= 99; $counter += 1) {
				if ( $counter < 10 ) {
					$number = '0' . $counter;
				} else {
					$number = $counter;
				}
                echo '<option value="' . $number . '"' . ($number == $supporter_3_partial_payment ? ' selected' : '') . '>' . $number . '</option>' . "\n";
			}
        ?>
        </select>
        <br /><?php _e('Affiliate payment for three months.');

	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row" valign="top">' . __('12 Month payment', 'affiliate') . '</th>';
	echo '<td valign="top">'; ?>
		<select name="supporter_12_whole_payment">
		<?php
			$supporter_12_whole_payment = $getoption( "supporter_12_whole_payment" );
			$counter = 0;
			for ( $counter = 1; $counter <= 300; $counter += 1) {
                echo '<option value="' . $counter . '"' . ($counter == $supporter_12_whole_payment ? ' selected' : '') . '>' . $counter . '</option>' . "\n";
			}
        ?>
        </select>
        .
		<select name="supporter_12_partial_payment">
		<?php
			$supporter_12_partial_payment = $getoption( "supporter_12_partial_payment" );
			$counter = 0;
            echo '<option value="00"' . ('00' == $supporter_12_partial_payment ? ' selected' : '') . '>00</option>' . "\n";
			for ( $counter = 1; $counter <= 99; $counter += 1) {
				if ( $counter < 10 ) {
					$number = '0' . $counter;
				} else {
					$number = $counter;
				}
                echo '<option value="' . $number . '"' . ($number == $supporter_12_partial_payment ? ' selected' : '') . '>' . $number . '</option>' . "\n";
			}
        ?>
        </select>
        <br /><?php _e('Affiliate payment for twelve months.');

	echo '</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<th scope="row" valign="top">' . __('Bulk Upgrades payment', 'affiliate') . '</th>';
	echo '<td valign="top">'; ?>
		<select name="$supporter_bulk_whole_payment">
		<?php
			$supporter_bulk_whole_payment = $getoption( "supporter_bulk_whole_payment" );
			$counter = 0;
			for ( $counter = 1; $counter <= 300; $counter += 1) {
                echo '<option value="' . $counter . '"' . ($counter == $supporter_bulk_whole_payment ? ' selected' : '') . '>' . $counter . '</option>' . "\n";
			}
        ?>
        </select>
        .
		<select name="supporter_bulk_partial_payment">
		<?php
			$supporter_bulk_partial_payment = $getoption( "supporter_bulk_partial_payment" );
			$counter = 0;
            echo '<option value="00"' . ('00' == $supporter_bulk_partial_payment ? ' selected' : '') . '>00</option>' . "\n";
			for ( $counter = 1; $counter <= 99; $counter += 1) {
				if ( $counter < 10 ) {
					$number = '0' . $counter;
				} else {
					$number = $counter;
				}
                echo '<option value="' . $number . '"' . ($number == $supporter_bulk_partial_payment ? ' selected' : '') . '>' . $number . '</option>' . "\n";
			}
        ?>
        </select>
        <br /><?php _e('Affiliate payment for bulk upgrades.');

	echo '</td>';
	echo '</tr>';
	
	echo '</table>';
}

function affiliate_supporter_payment_settings_update() {

	if(function_exists('get_site_option')) {
		$updateoption = 'update_site_option';
	} else {
		$updateoption = 'update_option';
	}

	$updateoption( "supporter_1_whole_payment", $_POST[ 'supporter_1_whole_payment' ] );
	$updateoption( "supporter_1_partial_payment", $_POST[ 'supporter_1_partial_payment' ] );
	$updateoption( "supporter_3_whole_payment", $_POST[ 'supporter_3_whole_payment' ] );
	$updateoption( "supporter_3_partial_payment", $_POST[ 'supporter_3_partial_payment' ] );
	$updateoption( "supporter_12_whole_payment", $_POST[ 'supporter_12_whole_payment' ] );
	$updateoption( "supporter_12_partial_payment", $_POST[ 'supporter_12_partial_payment' ] );
	$updateoption( "supporter_bulk_whole_payment", $_POST[ 'supporter_bulk_whole_payment' ] );
	$updateoption( "supporter_bulk_partial_payment", $_POST[ 'supporter_bulk_partial_payment' ] );

}


?>