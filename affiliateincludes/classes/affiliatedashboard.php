<?php

// Dashboard affiliate graphs
class affiliatedashboard {

	var $build = 1;
	var $db;

	var $mylocation = "";
	var $plugindir = "";
	var $base_uri = '';

	var $affiliatedata = '';
	var $affiliatereferrers = '';


	function __construct() {

		global $wpdb;

		$this->db =& $wpdb;

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			// we're activated site wide
			$this->affiliatedata = $this->db->base_prefix . 'affiliatedata';
			$this->affiliatereferrers = $this->db->base_prefix . 'affiliatereferrers';
		} else {
			if(defined('AFFILIATE_USE_BASE_PREFIX_IF_EXISTS') && AFFILIATE_USE_BASE_PREFIX_IF_EXISTS == 'yes' && !empty($this->db->base_prefix)) {
				$this->affiliatedata = $this->db->base_prefix . 'affiliatedata';
				$this->affiliatereferrers = $this->db->base_prefix . 'affiliatereferrers';
			} else {
				// we're only activated on a blog level so put the admin menu in the main area
				$this->affiliatedata = $this->db->prefix . 'affiliatedata';
				$this->affiliatereferrers = $this->db->prefix . 'affiliatereferrers';
			}
		}

		add_action ('init', array(&$this, 'initialise_ajax'), 1);

		add_action ('init', array(&$this, 'dashboard_widget_header'), 999);

		add_action( 'wp_dashboard_setup', array(&$this, 'dashboard_affiliate_register') );

	}

	function affiliatedashboard() {
		$this->__construct();
	}

	function initialise_ajax() {
		add_action( 'wp_ajax__aff_getdashstats', array(&$this,'ajax__aff_getdashstats') );
	}

	function ajax__aff_getdashstats() {

		global $user;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$headings = get_site_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		} else {
			$headings = get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		}
		$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC LIMIT 0, 18", $user_ID ) );

		$startat = strtotime(date("Y-m-15"));
		$clicks = array();
		$signups = array();
		$payments = array();

		$ticks = array();

		if(!empty($results)) {
			$recent = array_shift($results);
		} else {
			$recent = array();
		}

		for($n = 0; $n < 10; $n++) {
			$place = 10 - $n;
			$rdate = strtotime("-$n month", $startat);
			$period = date('Ym', $rdate);

			$ticks[] = array((int) $place, date('M y', $rdate));

			if(!empty($recent) && $recent->period == $period) {
				// We are on the current period
				$clicks[] = array( (int) $place, (int) $recent->uniques);
				$signups[] = array( (int) $place, (int) $recent->signups);
				$payments[] = array( (int) $place, (int) $recent->completes);

				if(!empty($results)) {
					$recent = array_shift($results);
				} else {
					$recent = array();
				}

			} else {
				// A zero blank row
				$clicks[] = array( (int) $place, (int) 0);
				$signups[] = array( (int) $place, (int) 0);
				$payments[] = array( (int) $place, (int) 0);

			}
		}

		$return = array();
		$return['chart'][] = array("label" => stripslashes($headings[0]), "data" => $clicks);
		$return['chart'][] = array("label" => stripslashes($headings[1]), "data" => $signups);
		$return['chart'][] = array("label" => stripslashes($headings[2]), "data" => $payments);

		$return['ticks'] = $ticks;

		$this->return_json($return);

		exit;
	}

	function return_json($results) {

		// Check for callback
		if(isset($_GET['callback'])) {
			// Add the relevant header
			header('Content-type: text/javascript');
			echo addslashes($_GET['callback']) . " (";
		} else {
			if(isset($_GET['pretty'])) {
				// Will output pretty version
				header('Content-type: text/html');
			} else {
				header('Content-type: application/json');
			}
		}

		if(function_exists('json_encode')) {
			echo json_encode($results);
		} else {
			// PHP4 version
			require_once(ABSPATH."wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php");
			$json_obj = new Moxiecode_JSON();
			echo $json_obj->encode($results);
		}

		if(isset($_GET['callback'])) {
			echo ")";
		}

	}

	function dashboard_widget_header() {
		if(strstr(strtolower($_SERVER['SCRIPT_NAME']), 'index.php')) {
			wp_enqueue_script('flot_js', affiliate_url('affiliateincludes/js/jquery.flot.min.js'), array('jquery'));
			wp_enqueue_script('aff_js', affiliate_url('affiliateincludes/js/affiliatelitedash.js'), array('jquery'));

			add_action ('admin_head', array(&$this, 'dashboard_widget_iehead'));
		}
	}

	function dashboard_widget_iehead() {
		echo '<!--[if IE]><script language="javascript" type="text/javascript" src="' . affiliate_url('affiliateincludes/js/excanvas.min.js') . '"></script><![endif]-->';
	}

	function dashboard_affiliate_register() {

		global $user;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(get_usermeta($user_ID, 'enable_affiliate') == 'yes') {
			wp_add_dashboard_widget( 'affwidgetstats', __( 'Affiliate Report' ), array(&$this, 'dashboard_aff_report'));
		}

	}


	function dashboard_aff_report() {

		global $user;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		echo "<div id='affdashgraph' style='height: 200px; background-color: #fff; margin-left: 10px; margin-right: 10px; margin-bottom: 20px;'>" . "</div>";

		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatereferrers} WHERE user_id = %d AND period = %s ORDER BY referred DESC LIMIT 0, 15", $user_ID, date("Ym") ) );


		echo "<table class='widefat'>";

		echo "<thead>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo  __('Top referrers for ','affiliate') . date("M Y");
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __('Visits','affiliate');
			echo "</th>";
			echo "</tr>";
		echo "</thead>";
		echo "<tbody>";

		if(!empty($rows)) {


			$class = 'alternate';
			foreach($rows as $r) {

				echo "<tr class='$class' style='$style'>";
				echo "<td style='padding: 5px;'>";
				echo "<a href='http://" . $r->url . "'>" . $r->url . "</a>";
				echo "</td>";
				echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
				echo $r->referred;
				echo "</td>";
				echo "</tr>";

				if($class != 'alternate') {
					$class = '';
				} else {
					$class = 'alternate';
				}

			}

		} else {
			echo __('<tr><td colspan="2">You have no referred visits this month.</td></tr>','affiliate');
		}

		echo "</tbody>";
		echo "</table>";


	}

}

$affdash =& new affiliatedashboard();

?>