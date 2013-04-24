<?php
/*
Plugin Name: Pro-sites basic add-on
Description: Affiliate system plugin for the WordPress Pro-Sites plugin
Author: Barry (Incsub)
Author URI: http://premium.wpmudev.org
*/

add_action( 'wpmu_new_blog', 'affiliate_new_blog', 10, 2 );
add_action( 'supporter_payment_processed', 'affiliate_supporter_paid', 10, 3 );
add_filter( 'blog_template_exclude_settings', 'affiliate_supporter_new_blog_template_exclude' );

add_action( 'psts_settings_page', 'affiliate_prosites_settings' );
add_action( 'psts_settings_process', 'affiliate_prosites_settings_update' );

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

function affiliate_prosites_settings_update() {

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

	$updateoption( "supporter_1_payment_type", $_POST[ 'supporter_1_payment_type' ] );
	$updateoption( "supporter_3_payment_type", $_POST[ 'supporter_3_payment_type' ] );
	$updateoption( "supporter_12_payment_type", $_POST[ 'supporter_12_payment_type' ] );

}

function affiliate_prosites_settings() {

	global $psts;

	if(function_exists('get_site_option')) {
		$getoption = 'get_site_option';
	} else {
		$getoption = 'get_option';
	}

	?>
	<div class="postbox">
        <h3 class="hndle" style="cursor:auto;"><span><?php _e('Affiliate Settings', 'affiliate') ?></span></h3>
        <div class="inside">
          <table class="form-table">
            <tr valign="top">
            <th scope="row"><?php _e('1 Month payment', 'affiliate'); ?></th>
            <td>
				<select name="supporter_1_whole_payment">
				<?php
					$supporter_1_whole_payment = $getoption( "supporter_1_whole_payment" );
					$counter = 0;
					for ( $counter = 0; $counter <= 300; $counter += 1) {
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
				&nbsp;
				<?php
				$supporter_1_payment_type = $getoption( "supporter_1_payment_type", 'actual' );
				?>
				<select name="supporter_1_payment_type">
					<option value='actual' <?php selected( $supporter_1_payment_type, 'actual');  ?>><?php echo esc_html($psts->get_setting('currency')); ?></option>
					<option value='percentage' <?php selected( $supporter_1_payment_type, 'percentage');  ?>><?php _e('%','membership'); ?></option>
				</select>
		        <br /><?php _e('Affiliate payment for one month.'); ?>
            </td>
            </tr>

			<tr valign="top">
            <th scope="row"><?php _e('3 Month payment', 'affiliate'); ?></th>
            <td>
				<select name="supporter_3_whole_payment">
				<?php
					$supporter_3_whole_payment = $getoption( "supporter_3_whole_payment" );
					$counter = 0;
					for ( $counter = 0; $counter <= 300; $counter += 1) {
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
				&nbsp;
				<?php
				$supporter_3_payment_type = $getoption( "supporter_3_payment_type", 'actual' );
				?>
				<select name="supporter_3_payment_type">
					<option value='actual' <?php selected( $supporter_3_payment_type, 'actual');  ?>><?php echo esc_html($psts->get_setting('currency')); ?></option>
					<option value='percentage' <?php selected( $supporter_3_payment_type, 'percentage');  ?>><?php _e('%','membership'); ?></option>
				</select>
		        <br /><?php _e('Affiliate payment for three months.'); ?>
            </td>
            </tr>

			<tr valign="top">
            <th scope="row"><?php _e('12 Month payment', 'affiliate'); ?></th>
            <td>
				<select name="supporter_12_whole_payment">
				<?php
					$supporter_12_whole_payment = $getoption( "supporter_12_whole_payment" );
					$counter = 0;
					for ( $counter = 0; $counter <= 300; $counter += 1) {
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
				&nbsp;
				<?php
				$supporter_12_payment_type = $getoption( "supporter_12_payment_type", 'actual' );
				?>
				<select name="supporter_12_payment_type">
					<option value='actual' <?php selected( $supporter_12_payment_type, 'actual');  ?>><?php echo esc_html($psts->get_setting('currency')); ?></option>
					<option value='percentage' <?php selected( $supporter_12_payment_type, 'percentage');  ?>><?php _e('%','membership'); ?></option>
				</select>
		        <br /><?php _e('Affiliate payment for twelve months.'); ?>
            </td>
            </tr>

          </table>
        </div>
      </div>
	<?php

}

?>