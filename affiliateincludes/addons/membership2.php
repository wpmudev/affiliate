<?php
/*
Plugin Name: Membership2
Description: Reward users for bringing new subscriptions via the WPMUDEV Membership2 plugin
Author URI: http://premium.wpmudev.org/project/membership/
Depends: protected-content/protected-content.php
Class: MS_Plugin
Developer: Philipp Stracker
*/

class Affiliate_Membership2_Integration {

	/**
	 * This is the area-key which is passed to affiliates to recognize data
	 * created by this integration.
	 */
	const AREA_KEY = 'paid:membership2';

	/**
	 * This is the ajax action used in the Membership2 payment options form
	 * to save the Affiliate reward options.
	 */
	const AJAX_ACTION = 'affiliate_setting';

	/**
	 * A reference to the Membership2 API instance.
	 * The property is set by $this::init()
	 */
	protected $api = null;

	/**
	 * Create and setup the Membership2 integration.
	 *
	 * @since  1.0.0
	 */
	public static function setup() {
		static $Inst = null;

		if ( null === $Inst ) {
			$Inst = new Affiliate_Membership2_Integration();
		}
	}

	/**
	 * Protected constructor is run once only.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		add_action( 'ms_init', array( $this, 'init' ) );
	}

	/**
	 * Adds the hooks to integrate with Membership2.
	 *
	 * This function is called when Membership2 is active and initializtion
	 * is done.
	 *
	 * @since  1.0.0
	 * @param  MS_Controller_Api $api The API instance.
	 */
	public function init( $api ) {
		$this->api = $api;

		if ( MS_Plugin::is_network_wide() ) {
			$affiliate_plugin = 'affiliate/affiliate.php';

			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}

			if ( ! is_plugin_active_for_network( $affiliate_plugin )
				&& current_user_can( 'manage_options' )
			) {
				lib2()->ui->admin_message(
					__( 'Membership2 uses network-wide protection.<br>Please network activate the Affiliate plugin to avoid problems with the Membership2 integration for Affiliates.', 'affiliate' ),
					'err'
				);
			}
		}

		// -- Frontend integration

		// Reward for the referrer of the member.
		add_action(
			'ms_invoice_paid',
			array( $this, 'payment_processed' ),
			10, 2
		);

		// -- Admin integration

		// Display Affiliate settings on the Membership payment options page.
		add_action(
			'ms_view_membership_payment_form',
			array( $this, 'form' ),
			10, 2
		);

		// Save Affiliate settings via Ajax request.
		add_action(
			'wp_ajax_' . self::AJAX_ACTION,
			array( $this, 'save' )
		);
	}

	/**
	 * A payment was received, award affiliate reward to the referrer.
	 *
	 * Whenever a Membership2 invoice is paid we try to find the referrer of
	 * the member and award a reward to him according to the payment settings.
	 *
	 * This function uses the Membership2 hook `ms_invoice_paid` which is called
	 * when a user does either
	 * (1) sucessfully make a payment for a paid membership or
	 * (2) successfully subscribe to a free membership.
	 *
	 * @since  1.0.0
	 * @param  MS_Model_Invoice $invoice The invoice which was paid.
	 * @param  MS_Model_Relationship $subscription
	 */
	public function payment_processed( $invoice, $subscription ) {
		global $affiliate; // Used for communication with Affiliates plugin.
		global $blog_id, $site_id; // Used for logging.

		$user_id = $invoice->user_id;
		$membership = $subscription->get_membership();
		$pay_once = defined( 'AFFILIATE_PAYONCE' ) && 'yes' == AFFILIATE_PAYONCE;

		$user_id_referrer = get_user_meta( $user_id, 'affiliate_referred_by', true );
		if ( empty( $user_id_referrer ) ) {
			// We do not know who referred the user, don't pay a commission.
			return;
		}

		$affiliate_paid = get_user_meta( $user_id, 'affiliate_paid', true );
		if ( $pay_once && 'yes' == $affiliate_paid ) {
			// The referrer already got a one-time commission, don't pay again.
			return;
		}

		$complete_records = $affiliate->get_complete_records(
			$user_id_referrer,
			date( 'Ym' ),
			array( self::AREA_KEY ),
			$user_id
		);

		if ( is_array( $complete_records ) ) {
			// Make sure that this subscription was not commissioned before.
			foreach ( $complete_records as $record ) {
				$meta = maybe_unserialize( $record->meta );

				if ( $meta['subscription_id'] == $subscription->id ) {
					// It seems this subscription was already commissioned.
					return;
				}
			}
		}

		// Okay, the referrer is entitiled to the commission!

		/*
		 * Reward is the money that the user receives.
		 * It is stored in cents/milli-percent.
		 * I.e. '100' $ -> 1.00 $  and '100' % -> 1.00 %
		 */
		$reward = $this->get_value( $membership );
		$type = $this->get_type( $membership );

		switch ( $type ) {
			case 'inv':
				$base = $invoice->subtotal; // Invoice amount without taxes.
				$reward = $base * $reward / 100;
				break;

			case 'mem':
				$base = $membership->price; // Membership price setting.
				$reward = $base * $reward / 100;
				break;

			case 'abs':
			default:
				// Reward already has correct value.
				break;
		}

		$reward = round( $reward, 2, PHP_ROUND_HALF_DOWN );

		// Note: lib2() used here is provided by the Membership2 plugin.
		$meta = array(
			'subscription_id' => $subscription->id,
			'invoice_id'      => $invoice->id,
			'gateway_id'      => $invoice->gateway_id,
			'transaction_id'  => $invoice->external_id,
			'blog_id'         => $blog_id,
			'site_id'         => $site_id,
			'current_user_id' => get_current_user_id(),
			'REMOTE_URL'      => $_SERVER['HTTP_REFERER'],
			'LOCAL_URL'       => lib2()->net->current_url(),
			'IP'              => lib2()->net->current_ip()->ip,
		);

		do_action(
			'affiliate_purchase',
			$user_id_referrer,
			$reward,
			self::AREA_KEY,
			$user_id,
			__( 'Membership2', 'affiliate' ),
			$meta
		);

		if ( $pay_once ) {
			update_user_meta( $user_id, 'affiliate_paid', 'yes' );
		}
	}

	/**
	 * Display additional fields in the Membership payment options screen.
	 *
	 * This function is called on the admin screen where Membership payment
	 * settings are configured.
	 * We can directly output HTML code that is appended to the default form.
	 *
	 * Note that the payment form has no submit button! All settings must be
	 * saved directly via Ajax directly when they are changed.
	 *
	 * @since  1.0.0
	 * @param  MS_View $view The view object that called this function.
	 * @param  MS_Model_Membership $membership The membership that is modified.
	 */
	public function form( $view, $membership ) {
		$membership_currency = $this->api->settings->currency;
		$affiliate_currency = aff_get_option( 'affiliate-currency-paypal-masspay', 'USD' );

		if ( $membership->is_free ) {
			$label = __( 'Reward on subscription', 'affiliate' );
		} else {
			$label = __( 'Reward on first payment', 'affiliate' );
		}

		$fields = array();
		$fields['value'] = array(
			'type' => MS_Helper_Html::INPUT_TYPE_NUMBER,
			'id' => 'aff_reward_value',
			'before' => $label,
			'value' => $this->get_value( $membership ),
			'class' => 'ms-text-smallish',
			'config' => array(
				'step' => '0.01',
				'min' => '0',
			),
			'ajax_data' => array(
				'action' => self::AJAX_ACTION,
				'membership_id' => $membership->id,
			),
		);
		$fields['type'] = array(
			'type' => MS_Helper_Html::INPUT_TYPE_SELECT,
			'id' => 'aff_reward_type',
			'value' => $this->get_type( $membership ),
			'field_options' => array(
				'inv' => __( 'Percent (payment)', 'affiliate' ) . '*',
				'mem' => __( 'Percent (price)', 'affiliate' ) . '**',
				'abs' => $affiliate_currency,
			),
			'ajax_data' => array(
				'action' => self::AJAX_ACTION,
				'membership_id' => $membership->id,
			),
		);
		?>
		<div class="aff-payment-options">
			<?php MS_Helper_Html::html_separator(); ?>
			<div class="aff-title">
				<strong><?php _e( 'Affiliate settings', 'affiliate' ); ?></strong>
			</div>
			<div class="aff-inside">
				<?php
				foreach ( $fields as $field ) {
					MS_Helper_Html::html_element( $field );
				}
				?>
				<p>
				* <?php _e( 'Reward is based on the actually paid amount which includes any discounts (but no taxes).', 'affiliate' ); ?><br />
				** <?php _e( 'Reward is based on the current membership price setting.', 'affiliate' ); ?>
				</p>
				<p>
				<?php
				printf(
					__( 'Tipp: There is no currency conversion done. All rewards are paid in %s, regardless of the currency used by Membership2 or the invoice.', 'affiliate' ),
					'<strong>' . $affiliate_currency . '</strong>'
				); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Ajax handler that saves a single Affiliate reward option from the
	 * Membership2 payment settings screen.
	 *
	 * @since  1.0.0
	 */
	public function save() {
		if ( empty( $_POST['_wpnonce'] ) ) { exit; }
		if ( empty( $_POST['field'] ) ) { exit; }
		if ( ! isset( $_POST['value'] ) ) { exit; }
		if ( ! isset( $_POST['membership_id'] ) ) { exit; }

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], self::AJAX_ACTION ) ) { exit; }

		$membership_id = $_POST['membership_id'];
		$membership = $this->api->get_membership( $membership_id );

		if ( ! $membership->is_valid() ) { exit; }

		$field = $_POST['field'];
		$value = $_POST['value'];

		switch ( $field ) {
			case 'aff_reward_value':
				$value = floatval( $value );
				$value *= 100;
				$value = (int) $value;
				break;
		}

		$membership->set_custom_data( $field, $value );
		$membership->save();

		echo '3'; // 3 means 'OK'
		exit;
	}

	//
	// INTERNAL HELPER FUNCTIONS.
	//

	/**
	 * Returns the sanitized reward value for display or calculation.
	 *
	 * @since  1.0.0
	 * @param  MS_Model_Membership $membership
	 * @return float The sanitized float value.
	 */
	protected function get_value( $membership ) {
		$reward = $membership->get_custom_data( 'aff_reward_value' );
		$reward = max( 0, intval( $reward ) );
		$reward = (float) $reward / 100;

		return $reward;
	}

	/**
	 * Returns the sanitized reward type for display or calculation.
	 *
	 * (1) inv .. Percentage based on actually paid amount.
	 * (2) mem .. Percentage based on current membership price setting.
	 * (3) abs .. Absolute value (in USD/etc).
	 *
	 * @since  1.0.0
	 * @param  MS_Model_Membership $membership
	 * @return string A valid reward type: inv/mem/abs
	 */
	protected function get_type( $membership ) {
		$available_types = array( 'inv', 'mem', 'abs' );
		$type = $membership->get_custom_data( 'aff_reward_type' );

		if ( ! in_array( $type, $available_types ) ) {
			$type = 'abs';
		}

		return $type;
	}
};

// Initialize the integration
Affiliate_Membership2_Integration::setup();