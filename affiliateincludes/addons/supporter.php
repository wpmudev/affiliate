<?php
/*
Plugin Name: Pro-sites
Description: Affiliate system plugin for the WordPress Pro-Sites plugin. Captures Affiliate transaction for Pro-Site paid upgrades.
Author URI: http://premium.wpmudev.org
Network: true
Depends: pro-sites/pro-sites.php
Class: ProSites
*/

add_action( 'supporter_payment_processed', 			'affiliate_supporter_paid', 10, 4 );
add_filter( 'blog_template_exclude_settings', 		'affiliate_supporter_new_blog_template_exclude' );
add_action( 'psts_settings_page', 					'affiliate_supporter_settings' );
add_action( 'psts_settings_process', 				'affiliate_supporter_settings_update' );

/*
 * Exclude option from New Blog Template plugin copy
 */
function affiliate_supporter_new_blog_template_exclude( $and ) {
	$and .= " AND `option_name` != 'affiliate_referred_by' AND `option_name` != 'affiliate_paid' AND `option_name` != 'affiliate_referrer' ";
	return $and;
}

function affiliate_supporter_paid($bid, $periodamount, $period, $level) {
	global $blog_id, $site_id;
	
	//echo "bid[". $bid ."]<br />";
	//echo "amount[". $amount ."]<br />";
	//echo "supporterperiod<pre>"; print_r($supporterperiod); echo "</pre>";
	//die();

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

		switch($period) {

			case '1':	$supporter_1_payment_type = $getoption( "supporter_1_payment_type", 'actual' );
						$affamount = $getoption( "supporter_1_whole_payment", 0 ) . '.' . $getoption( "supporter_1_partial_payment", 0 );

						if($supporter_1_payment_type == 'percentage') {
							$floatpercentage = floatval( $affamount );
							$floatamount = floatval( $periodamount );
							// We are on a percentage payment so calculate the amount we need to charge

							if($floatamount > 0 && $floatpercentage > 0) {
								// We have a positive value to check against - need to check if there is an affiliate
								$amount = ($floatamount / 100) * $floatpercentage;
								$amount = round($amount, 2, PHP_ROUND_HALF_DOWN);
							} else {
								$amount = 0;
							}
						} else {
							$amount = $affamount;
						}
						break;

			case '3':	$supporter_3_payment_type = $getoption( "supporter_3_payment_type", 'actual' );
						$affamount = $getoption( "supporter_3_whole_payment", 0 ) . '.' . $getoption( "supporter_3_partial_payment", 0 );

						if($supporter_3_payment_type == 'percentage') {
							$floatpercentage = floatval( $affamount );
							$floatamount = floatval( $periodamount );
							// We are on a percentage payment so calculate the amount we need to charge

							if($floatamount > 0 && $floatpercentage > 0) {
								// We have a positive value to check against - need to check if there is an affiliate
								$amount = ($floatamount / 100) * $floatpercentage;
								$amount = round($amount, 2, PHP_ROUND_HALF_DOWN);
							} else {
								$amount = 0;
							}
						} else {
							$amount = $affamount;
						}
						break;

			case '12':	$supporter_12_payment_type = $getoption( "supporter_12_payment_type", 'actual' );
						$affamount = $getoption( "supporter_12_whole_payment", 0 ) . '.' . $getoption( "supporter_12_partial_payment", 0 );

						if($supporter_12_payment_type == 'percentage') {
							$floatpercentage = floatval( $affamount );
							$floatamount = floatval( $periodamount );
							// We are on a percentage payment so calculate the amount we need to charge

							if($floatamount > 0 && $floatpercentage > 0) {
								// We have a positive value to check against - need to check if there is an affiliate
								$amount = ($floatamount / 100) * $floatpercentage;
								$amount = round($amount, 2, PHP_ROUND_HALF_DOWN);
							} else {
								$amount = 0;
							}
						} else {
							$amount = $affamount;
						}
						break;

			default:
						$amount = 0;
						break;
		}
		$meta = array(
			'bid'				=>	$bid, 
			'periodamount'		=>	$periodamount, 
			'period'			=>	$period, 
			'level'				=>	$level,
			'blog_id'			=>	$blog_id,
			'site_id'			=>	$site_id,
			'current_user_id'	=>	get_current_user_id(),
			'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
			'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
			'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
			//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
		);
		do_action('affiliate_purchase', $aff, $amount, 'paid:prosites', $bid, false, $meta);

		if(defined('AFFILIATE_PAYONCE') && AFFILIATE_PAYONCE == 'yes') {

			if(function_exists('update_blog_option')) {
				update_blog_option( $bid, 'affiliate_paid', 'yes' );
			}

		}

	}
}

function affiliate_supporter_settings_update() {

	if(function_exists('get_site_option')) {
		$updateoption = 'update_site_option';
	} else {
		$updateoption = 'update_option';
	}

	$updateoption( "supporter_1_whole_payment", $_POST[ 'supporter_1_whole_payment' ] );
	$updateoption( "supporter_1_partial_payment", $_POST[ 'supporter_1_partial_payment' ] );
	$updateoption( "supporter_1_payment_type", $_POST[ 'supporter_1_payment_type' ] );

	$updateoption( "supporter_3_whole_payment", $_POST[ 'supporter_3_whole_payment' ] );
	$updateoption( "supporter_3_partial_payment", $_POST[ 'supporter_3_partial_payment' ] );
	$updateoption( "supporter_3_payment_type", $_POST[ 'supporter_3_payment_type' ] );

	$updateoption( "supporter_12_whole_payment", $_POST[ 'supporter_12_whole_payment' ] );
	$updateoption( "supporter_12_partial_payment", $_POST[ 'supporter_12_partial_payment' ] );
	$updateoption( "supporter_12_payment_type", $_POST[ 'supporter_12_payment_type' ] );

}

function affiliate_supporter_settings() {

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
			<?php
				$prosites_currency = $psts->get_setting('currency');
				$affiliate_currency = aff_get_option('affiliate-currency-paypal-masspay', 'USD');
				//echo "prosites_currency[". $prosites_currency ."] affiliate_currency[". $affiliate_currency ."]<br />";
				if ($prosites_currency != $affiliate_currency) {
					?><p class="error"><?php echo sprintf(__('Currency mismatch. Your Pro Sites currency is set to <strong>%s</strong> but Affiliate currency is set to <strong>%s</strong>. Please ensure both are set correctly.'), $prosites_currency, $affiliate_currency) ?></p><?php
				}
			?>
			
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
					<option value='actual' <?php selected( $supporter_1_payment_type, 'actual');  ?>><?php echo esc_html($affiliate_currency); ?></option>
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
					<option value='actual' <?php selected( $supporter_3_payment_type, 'actual');  ?>><?php echo esc_html($affiliate_currency); ?></option>
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
					<option value='actual' <?php selected( $supporter_12_payment_type, 'actual');  ?>><?php echo esc_html($affiliate_currency); ?></option>
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
