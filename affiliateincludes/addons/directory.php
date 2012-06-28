<?php
/*
Plugin Name: Directory add-on
Description: Affiliate system plugin for the WordPress Directory plugin
Author: Andrey Shipilov (Incsub)
Author URI: http://premium.wpmudev.org
*/

define( 'AFF_DIRECTORY_ADDON', 1 );

add_action( 'user_register', 'dr_affiliate_new_user' );

add_action( 'directory_set_paid_member', 'dr_affiliate_new_paid', 10, 3 );

add_action( 'directory_affiliate_settings', 'dr_affiliate_settings' );

function dr_affiliate_new_user( $user_id ) {

    $user_role = get_user_meta( $user_id, 'wp_capabilities', true );

    //not paid directory user
    if ( isset( $user_role['directory_member_not_paid'] ) || isset( $user_role['directory_member_paid'] ) ) {

	    // Call the affiliate action
	    do_action( 'affiliate_signup' );

	    if ( defined( 'AFFILIATEID' ) ) {
		    // We found an affiliate that referred this blog creator
		    if ( function_exists( 'update_user_meta' ) ) {
			    update_user_meta( $user_id, 'affiliate_referred_by', AFFILIATEID );
		    } else {
			    update_usermeta( $user_id, 'affiliate_referred_by', AFFILIATEID );
		    }
	    }
    }
}


function dr_affiliate_new_paid( $affiliate_settings, $user_id, $billing_type ) {

	if ( function_exists( 'get_user_meta' ) ) {
		$aff    = get_user_meta( $user_id, 'affiliate_referred_by', true );
		$paid   = get_user_meta( $user_id, 'affiliate_paid', true );
	} else {
		$aff    = get_usermeta( $user_id, 'affiliate_referred_by' );
		$paid   = get_usermeta( $user_id, 'affiliate_paid' );
	}

	if ( empty( $aff ) ) $aff = false;

	if ( $aff && $paid != 'yes' ) {

        if ( 'recurring' == $billing_type ) {
            $whole      = ( isset( $affiliate_settings['dr_recurring_whole_payment'] ) ) ? $affiliate_settings['dr_recurring_whole_payment'] : '0';
            $partial    = ( isset( $affiliate_settings['dr_recurring_partial_payment'] ) ) ? $affiliate_settings['dr_recurring_partial_payment'] : '0';
        } elseif ( 'one_time' == $billing_type ) {
            $whole      = ( isset( $affiliate_settings['dr_one_time_whole_payment'] ) ) ? $affiliate_settings['dr_one_time_whole_payment'] : '0';
            $partial    = ( isset( $affiliate_settings['dr_one_time_partial_payment'] ) ) ? $affiliate_settings['dr_one_time_partial_payment'] : '0';
        } else {
            $whole      = '0';
            $partial    = '0';
        }


		if( !empty( $whole ) || !empty( $partial ) ) {
			$amount = $whole . '.' . $partial;
		} else {
			$amount = 0;
		}

		do_action( 'affiliate_purchase', $aff, $amount, 'directory', $user_id, 'Directory referral for user.' );

		if ( defined( 'AFFILIATE_PAYONCE' ) && AFFILIATE_PAYONCE == 'yes' ) {

			if ( function_exists( 'update_user_meta' ) ) {
				update_user_meta( $user_id, 'affiliate_paid', 'yes' );
			} else {
				update_usermeta( $user_id, 'affiliate_paid', 'yes' );
			}

		}

	}

}


function dr_affiliate_settings( $affiliate_settings ) {

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