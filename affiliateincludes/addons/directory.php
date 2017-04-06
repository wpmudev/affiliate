<?php
/*
Plugin Name: Directory
Description: Affiliate system plugin for the WordPress Directory plugin
Author URI: http://premium.wpmudev.org/project/wordpress-directory
Depends: directory/loader.php
Class: Directory_Core
*/

define( 'AFF_DIRECTORY_ADDON', 1 );

// Register actions only if Directory plugin is active.
if ( affiliate_is_plugin_active( 'directory/loader.php' ) || affiliate_is_plugin_active_for_network( 'directory/loader.php' ) ) {

	add_action( 'directory_set_paid_member', 'dr_affiliate_new_paid', 10, 3 );
	add_action( 'directory_affiliate_settings', 'dr_affiliate_settings' );
}

function dr_affiliate_new_paid( $affiliate_settings, $user_id, $billing_type ) {
	global $blog_id, $site_id;
	
	if (empty($user_id)) {
		$user_id = get_current_user_id();
	}

	//echo 'in '. __FILE__ .': '. __FUNCTION__ .': '. __LINE__ .'<br />';
	//echo "affiliate_settings<pre>"; print_r($affiliate_settings); echo "</pre>";
	//echo "user_id[". $user_id ."]<br />";
	//echo "billing_type[". $billing_type ."<br />";
	//die();

	if ( function_exists( 'get_user_meta' ) ) {
		$aff    = get_user_meta( $user_id, 'affiliate_referred_by', true );
		$paid   = get_user_meta( $user_id, 'affiliate_paid', true );
	} else {
		$aff    = get_usermeta( $user_id, 'affiliate_referred_by' );
		$paid   = get_usermeta( $user_id, 'affiliate_paid' );
	}

	//echo "aff[". $aff ."]<br />";
	//echo "paid[". $paid ."]<br />";
	
	if ( empty( $aff ) ) $aff = false;

	if ( $aff && $paid != 'yes' ) {

        if ( 'recurring' == $billing_type ) {
            $whole      = ( isset( $affiliate_settings['dr_recurring_whole_payment'] ) ) ? $affiliate_settings['dr_recurring_whole_payment'] : '0';
            $partial    = ( isset( $affiliate_settings['dr_recurring_partial_payment'] ) ) ? $affiliate_settings['dr_recurring_partial_payment'] : '0';
			$note		= __('Directory recurring', 'affiliate');
        } elseif ( 'one_time' == $billing_type ) {
            $whole      = ( isset( $affiliate_settings['dr_one_time_whole_payment'] ) ) ? $affiliate_settings['dr_one_time_whole_payment'] : '0';
            $partial    = ( isset( $affiliate_settings['dr_one_time_partial_payment'] ) ) ? $affiliate_settings['dr_one_time_partial_payment'] : '0';
			$note		= __('Directory one time', 'affiliate');
        } else {
            $whole      = '0';
            $partial    = '0';
			$note		= __('Directory', 'affiliate') .' '. $billing_type;
        }

		//echo "whole[". $whole ."]<br />";
		//echo "partial[". $partial ."]<br />";
		
		if( !empty( $whole ) || !empty( $partial ) ) {
			$amount = $whole . '.' . $partial;
		} else {
			$amount = 0;
		}
		if ($amount > 0) {
			//echo "amount[". $amount ."]<br />";
		
			$meta = array(
				'affiliate_settings'	=>	affiliate_settings,
				'user_id'				=> 	$user_id,
				'billing_type'			=>	$billing_type,
				'blog_id'				=>	$blog_id,
				'site_id'				=>	$site_id,
				'current_user_id'		=>	get_current_user_id(),
				//'REMOTE_URL'			=>	esc_attr($_SERVER['HTTP_REFERER']),
				'LOCAL_URL'				=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
				'IP'					=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
				//'HTTP_USER_AGENT'		=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
			);
			do_action( 'affiliate_purchase', $aff, $amount, 'paid:directory', $user_id, $note, $meta );

			if ( defined( 'AFFILIATE_PAYONCE' ) && AFFILIATE_PAYONCE == 'yes' ) {

				if ( function_exists( 'update_user_meta' ) ) {
					update_user_meta( $user_id, 'affiliate_paid', 'yes' );
				} else {
					update_usermeta( $user_id, 'affiliate_paid', 'yes' );
				}
			}
		}
	}
}


function dr_affiliate_settings( $affiliate_settings ) {
	//echo "affiliate_settings<pre>"; print_r($affiliate_settings); echo "</pre>";
	
    $dr_recurring_whole_payment     = ( isset( $affiliate_settings['cost']['dr_recurring_whole_payment'] ) ) ? $affiliate_settings['cost']['dr_recurring_whole_payment'] : '0';
    $dr_recurring_partial_payment   = ( isset( $affiliate_settings['cost']['dr_recurring_partial_payment'] ) ) ? $affiliate_settings['cost']['dr_recurring_partial_payment'] : '00';		    	     						
    $dr_one_time_whole_payment      = ( isset( $affiliate_settings['cost']['dr_one_time_whole_payment'] ) ) ? $affiliate_settings['cost']['dr_one_time_whole_payment'] : '0';
    $dr_one_time_partial_payment    = ( isset( $affiliate_settings['cost']['dr_one_time_partial_payment'] ) ) ? $affiliate_settings['cost']['dr_one_time_partial_payment'] : '00';

    ?>
    <form method="post" class="affiliate_settings" id="affiliate_settings">
        <table class="table">
            <tr>
                <td>
                    <label for="aff_pay"><?php echo $affiliate_settings['dr_labels_txt']['recurring']; ?></label>
                </td>
              </tr>
              <tr>
                <td>
                    <select name="dr_recurring_whole_payment">
                    <?php
                        $counter = 0;
                        for ( $counter = 0; $counter <= floor( $affiliate_settings['payment_settings']['recurring_cost'] ); $counter += 1 ) {
                            echo '<option value="' . $counter . '"' . ( $counter == $dr_recurring_whole_payment ? ' selected' : '' ) . '>' . $counter . '</option>' . "\n";
                        }
                    ?>
                    </select>
                    .
                    <select name="dr_recurring_partial_payment">
                    <?php
                        $counter = 0;
                        echo '<option value="00"' . ( '00' == $dr_recurring_partial_payment ? ' selected' : '' ) . '>00</option>' . "\n";
                        for ( $counter = 1; $counter <= 99; $counter += 1 ) {
                            if ( $counter < 10 ) {
                                $number = '0' . $counter;
                            } else {
                                $number = $counter;
                            }
                            echo '<option value="' . $number . '"' . ( $number == $dr_recurring_partial_payment ? ' selected' : '' ) . '>' . $number . '</option>' . "\n";
                        }
                    ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <br /><br />
                    <label for="aff_pay"><?php echo $affiliate_settings['dr_labels_txt']['one_time']; ?></label>
                </td>
                </tr>
                <tr>
                <td>
                    <select name="dr_one_time_whole_payment">
                    <?php
                        $counter = 0;
                        for ( $counter = 0; $counter <= floor( $affiliate_settings['payment_settings']['one_time_cost'] ); $counter += 1 ) {
                            echo '<option value="' . $counter . '"' . ( $counter == $dr_one_time_whole_payment ? ' selected' : '' ) . '>' . $counter . '</option>' . "\n";
                        }
                    ?>
                    </select>
                    .
                    <select name="dr_one_time_partial_payment">
                    <?php
                        $counter = 0;
                        echo '<option value="00"' . ( '00' == $dr_one_time_partial_payment ? ' selected' : '' ) . '>00</option>' . "\n";
                        for ( $counter = 1; $counter <= 99; $counter += 1) {
                            if ( $counter < 10 ) {
                                $number = '0' . $counter;
                            } else {
                                $number = $counter;
                            }
                            echo '<option value="' . $number . '"' . ( $number == $dr_one_time_partial_payment ? ' selected' : '' ) . '>' . $number . '</option>' . "\n";
                        }
                    ?>
                    </select>
                </td>
            </tr>


        </table>
        <p class="submit">
            <?php wp_nonce_field( 'verify' ); ?>
            <input type="hidden" name="key" value="affiliate_settings" />
            <input type="submit" class="button-primary" name="save" value="Save Changes">
        </p>
    </form>
<?php
}

?>