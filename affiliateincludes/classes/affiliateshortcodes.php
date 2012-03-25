<?php

// Administration side of the affiliate system
class affiliateshortcodes {

	var $build = 4;

	var $db;

	var $mylocation = "";
	var $plugindir = "";
	var $base_uri = '';

	// The page on the public side of the site that has details of the affiliate plan
	var $affiliateinformationpage = 'affiliates';

	var $affiliatedata = '';
	var $affiliatereferrers = '';

	function __construct() {

		global $wpdb;

		// Grab our own local reference to the database class
		$this->db =& $wpdb;

		if( (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) && (defined('AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED') && AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED == 'yes')) {
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

		add_action( 'init', array( &$this, 'initialise' ), 1 );

		add_action( 'init', array(&$this, 'aff_report_header'), 999 );

		add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

	}

	function affiliateshortcodes() {
		$this->__construct();
	}

	function load_textdomain() {

		$locale = apply_filters( 'affiliate_locale', get_locale() );
		$mofile = affiliate_dir( "affiliateincludes/languages/affiliate-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'affiliate', $mofile );

	}

	function initialise() {
		// Ajax
		add_action( 'wp_ajax__aff_getstats', array(&$this,'ajax__aff_getstats') );
		add_action( 'wp_ajax__aff_getvisits', array(&$this,'ajax__aff_getvisits') );

		// Shortcodes
		add_shortcode('affiliatelogincheck', array(&$this, 'do_affiliatelogincheck_shortcode') );
		add_shortcode('affiliateuserdetails', array(&$this, 'do_affiliateuserdetails_shortcode') );

		add_shortcode('affiliatestatstable', array(&$this, 'do_affiliatestatstable_shortcode') );
		add_shortcode('affiliatestatschart', array(&$this, 'do_affiliatestatschart_shortcode') );
		add_shortcode('affiliatevisitstable', array(&$this, 'do_affiliatevisitstable_shortcode') );
		add_shortcode('affiliatetopvisitstable', array(&$this, 'do_affiliatetopvisitstable_shortcode') );
		add_shortcode('affiliatevisitschart', array(&$this, 'do_affiliatevisitschart_shortcode') );

		add_shortcode('affiliatebanners', array(&$this, 'do_affiliatebanners_shortcode') );

		// Check for shortcodes in any posts
		add_action( 'template_redirect', array(&$this, 'check_for_shortcodes') );

	}

	function get_custom_stylesheet() {
		return apply_filters( 'affiliate_custom_shortcode_style', affiliate_url('affiliateincludes/styles/shortcode.css') );
	}

	function get_custom_javascript() {
		return apply_filters( 'affiliate_custom_shortcode_javascript', affiliate_url('affiliateincludes/js/shortcode.js') );
	}

	function check_for_shortcodes( ) {
		global $wp_query;

		if ( is_singular() ) {
			$post = $wp_query->get_queried_object();
			if ( false !== strpos($post->post_content, '[affiliatestatschart') || false !== strpos($post->post_content, '[affiliatevisitschart') || false !== strpos($post->post_content, '[affiliateuserdetails')  ) {
				if( !current_theme_supports( 'affiliate_scripts' )) {
					wp_enqueue_script('flot_js', affiliate_url('affiliateincludes/js/jquery.flot.min.js'), array('jquery'));
					wp_enqueue_script( 'affiliatepublicjs', $this->get_custom_javascript(), array('jquery') );
					wp_localize_script( 'affiliatepublicjs', 'affiliate', array( 'ajaxurl' => admin_url('admin-ajax.php') ) );

					add_action('wp_head', array(&$this, 'add_iehead') );
				}
			}

			if ( false !== strpos($post->post_content, '[affiliate') && !current_theme_supports( 'affiliate_styles' ) ) {
				wp_enqueue_style( 'affiliatepubliccss', $this->get_custom_stylesheet(), array() );
			}

		}
	}

	function do_affiliatelogincheck_shortcode($atts, $content = null, $code = "") {

		global $wp_query, $user;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			$html .= "<div class='affiliateloginmessage'>";
			if(empty($content)) {
				$html .= sprintf( __( 'You are not currently logged in. Please %s to see your affiliate information.','affiliate' ), '<a href="' . wp_login_url() . '">' . __('login','affiliate') . '</a>' );
			} else {
				$html .= $content;
			}
			$html .= "</div>";
		}

		$html .= $postfix;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function do_affiliateuserdetails_shortcode($atts, $content = null, $code = "") {

		global $wp_query, $user;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"bannerlink"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(!empty($user_ID)) {
			$html .= "<div id='affiliateuserdetails'>";

			$html .= $this->show_user_details( $bannerlink );

			$html .= "</div>";
		}

		$html .= $postfix;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function do_affiliatestatstable_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;
		$html .= $this->show_clicks_table();
		$html .= $postfix;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function do_affiliatestatschart_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;
		$html .= $this->show_clicks_chart();
		$html .= $postfix;


		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function do_affiliatevisitstable_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;
		$html .= $this->show_visits_table();
		$html .= $postfix;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function do_affiliatetopvisitstable_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;
		$html .= $this->show_top_visits_table();
		$html .= $postfix;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function do_affiliatevisitschart_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;
		$html .= $this->show_visits_chart();
		$html .= $postfix;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function ajax__aff_getstats() {

		global $user;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$headings = get_site_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		} else {
			$headings = get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		}

		if(isset($_GET['number'])) {
			$number = intval(addslashes($_GET['number']));
		} else {
			$number = 18;
		}

		if(isset($_GET['userid'])) {
			$user_ID = intval(addslashes($_GET['userid']));
		}

		$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC LIMIT 0, %d", $user_ID, $number ) );

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

		for($n = 0; $n < $number; $n++) {
			$place = $number - $n;
			$rdate = strtotime("-$n month", $startat);
			$period = date('Ym', $rdate);

			$ticks[] = array((int) $place, date('M', $rdate) . '<br/>' . date('Y', $rdate) );

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

	function ajax__aff_getvisits() {

		global $user;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		// Build 18 months of years
		$startat = strtotime(date("Y-m-15"));
		$years = array();
		for($n = 0; $n < 18; $n++) {
			$rdate = strtotime("-$n month", $startat);
			$years[] = "'" . date('Ym', $rdate) . "'";
		}

		$visitresults = $this->db->get_results( $this->db->prepare( "SELECT ar.* FROM {$this->affiliatereferrers} as ar INNER JOIN ( SELECT url FROM {$this->affiliatereferrers} WHERE user_id = $user_ID AND period in (" . implode(',', $years) . ") GROUP BY url ORDER BY sum(referred) DESC LIMIT 0, 10 ) as arr ON ar.url = arr.url WHERE ar.user_id = %d ORDER BY ar.url, ar.period DESC", $user_ID ) );

		$urls = $this->db->get_col(null, 2);

		$startat = strtotime(date("Y-m-15"));
		$visits = array();

		$ticks = array();
		$urls = array_unique($urls);

		$return = array();

		foreach($urls as $key => $url) {
			$results = $visitresults;
			if(!empty($results)) {
				$recent = array_shift($results);
				while($recent->url != $url) {
					$recent = array_shift($results);
				}
			}
			for($n = 0; $n < 12; $n++) {
				$place = 12 - $n;
				$rdate = strtotime("-$n month", $startat);
				$period = date('Ym', $rdate);

				$ticks[] = array((int) $place, date('M', $rdate) . '<br/>' . date('Y', $rdate) );

				if(!empty($recent) && $recent->period == $period && $recent->url == $url) {
					// We are on the current period
					$visits[$url][] = array( (int) $place, (int) $recent->referred);

					if(!empty($results)) {
						$recent = array_shift($results);
					} else {
						$recent = array();
					}

				} else {
					// A zero blank row
					$visits[$url][] = array( (int) $place, (int) 0);

				}
			}

			$return['chart'][] = array("label" => $url, "data" => $visits[$url]);

		}

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

	function aff_report_header() {

		// Main user report page
		if(isset($_GET['page']) && addslashes($_GET['page']) == 'affiliateearnings') {
			wp_enqueue_script('flot_js', affiliate_url('affiliateincludes/js/jquery.flot.min.js'), array('jquery'));
			wp_enqueue_script('aff_js', affiliate_url('affiliateincludes/js/affiliateliteuserreport.js'), array('jquery'));

			add_action('admin_head', array(&$this, 'add_iehead') );
		}

		// Admin user report page
		if(isset($_GET['page']) && addslashes($_GET['page']) == 'affiliatesadmin' && addslashes($_GET['subpage']) == 'users' && isset($_GET['id'])) {
			wp_enqueue_script('flot_js', affiliate_url('affiliateincludes/js/jquery.flot.min.js'), array('jquery'));
			wp_enqueue_script('aff_js', affiliate_url('affiliateincludes/js/affiliateadminuserreport.js'), array('jquery'));

			add_action('admin_head', array(&$this, 'add_iehead') );
		}


	}

	function add_iehead() {
		echo '<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="' . affiliate_url('affiliateincludes/js/excanvas.min.js') . '"></script><![endif]-->';
	}

	function show_clicks_chart() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			return '';
		}

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$headings = get_site_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		} else {
			$headings = get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		}
		$headings = array_merge($headings, array( __('Debits','affiliate'), __('Credits','affiliate'), __('Payments','affiliate') ));

		$newcolumns = apply_filters('affiliate_column_names', $headings);
		if(count($newcolumns) == 6) {
			// We must have 6 columns
			$columns = $newcolumns;
		}

		$reference = get_user_meta($user_ID, 'affiliate_reference', true);

		if(is_multisite()) {
			$getoption = 'get_site_option';
			$site = $getoption('site_name');
			$url = get_blog_option(1,'home');
		} else {
			$getoption = 'get_option';
			$site = $getoption('blogname');
			$url = $getoption('home');
		}

		ob_start();

		echo "<div id='affdashlegend' style='background-color: #fff;'>";
		echo "</div>";


		echo "<div id='affdashgraph' style='width: 100%; min-height: 350px; background-color: #fff;'>";
		echo "</div>";

		$html = ob_get_contents();
		ob_end_clean();

		return $html;


	}

	function show_clicks_table() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			return '';
		}

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$headings = get_site_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		} else {
			$headings = get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));
		}
		$headings = array_merge($headings, array( __('Debits','affiliate'), __('Credits','affiliate'), __('Payments','affiliate') ));

		$newcolumns = apply_filters('affiliate_column_names', $headings);
		if(count($newcolumns) == 6) {
			// We must have 6 columns
			$columns = $newcolumns;
		}

		$reference = get_user_meta($user_ID, 'affiliate_reference', true);

		if(is_multisite()) {
			$getoption = 'get_site_option';
			$site = $getoption('site_name');
			$url = get_blog_option(1,'home');
		} else {
			$getoption = 'get_option';
			$site = $getoption('blogname');
			$url = $getoption('home');
		}

		$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC", $user_ID ) );

		ob_start();
		echo "<div id='clickstable' style=''>";

		// The table
		echo '<table width="100%" cellpadding="3" cellspacing="3" class="widefat" style="width: 100%;">';
		echo '<thead>';
		echo '<tr>';
			echo '<th scope="col">';
			echo __('Date','affiliate');
			echo '</th>';
			foreach($columns as $column) {
				echo '<th scope="col" class="num">';
				echo stripslashes($column);
				echo '</th>';
			}
		echo '</tr>';
		echo '</thead>';

		echo '<tbody id="the-list">';

		$totalclicks = 0;
		$totalsignups = 0;
		$totalcompletes = 0;
		$totaldebits = 0;
		$totalcredits = 0;
		$totalpayments = 0;

		if(!empty($results)) {
			$recent = array_shift($results);
		} else {
			$recent = array();
		}

		$startat = strtotime(date("Y-m-15"));

		for($n = 0; $n < 18; $n++) {
			$rdate = strtotime("-$n month", $startat);
			$period = date('Ym', $rdate);
			$place = 18 - $n;

			echo "<tr $bgcolour class='$class periods' id='period-$place'>";
			echo '<td valign="top">';
			echo date("M", $rdate) . '<br/>' . date("Y", $rdate);
			echo '</td>';

			if(!empty($recent) && $recent->period == $period) {
				// We are on the current period
				echo '<td valign="top" class="num">';
				echo $recent->uniques;
				$totalclicks += $recent->uniques;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo $recent->signups;
				$totalsignups += $recent->signups;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo $recent->completes;
				$totalcompletes += $recent->completes;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format($recent->debits, 2);
				$totaldebits += (float) $recent->debits;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format($recent->credits, 2);
				$totalcredits += (float) $recent->credits;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format($recent->payments, 2);
				$totalpayments += (float) $recent->payments;
				echo '</td>';

				if(!empty($results)) {
					$recent = array_shift($results);
				} else {
					$recent = array();
				}

			} else {
				// A zero blank row
				echo '<td valign="top" class="num">';
				echo 0;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo 0;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo 0;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo "0.00";
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format(0, 2);
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format(0, 2);
				echo '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody>';

		echo '<tfoot>';
		echo '<tr>';
			echo '<th scope="col">';
			echo '</th>';
			echo '<th scope="col" class="num">';
			echo $totalclicks;
			echo '</th>';
			echo '<th scope="col" class="num">';
			echo $totalsignups;
			echo '</th>';
			echo '<th scope="col" class="num">';
			echo $totalcompletes;
			echo '</th>';
			echo '<th scope="col" class="num">';
			echo number_format($totaldebits, 2);
			echo '</th>';
			echo '<th scope="col" class="num">';
			echo number_format($totalcredits, 2);
			echo '</th>';
			echo '<th scope="col" class="num">';
			echo number_format($totalpayments, 2);
			echo '</th>';
		echo '</tr>';
		echo '</tfoot>';

		echo '</table>';

		echo "</div>";

		$html = ob_get_contents();
		ob_end_clean();

		return $html;


	}

	function show_visits_table() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			return '';
		}

		ob_start();

		do_action('affiliate_before_visits_table', $user_ID);

		// This months visits table
		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatereferrers} WHERE user_id = %d AND period = %s ORDER BY referred DESC LIMIT 0, 15", $user_ID, date("Ym") ) );
		echo "<div id='visitstable' style=''>";
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

		echo "<tfoot>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo  __('Top referrers for ','affiliate') . date("M Y");
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __('Visits','affiliate');
			echo "</th>";
			echo "</tr>";
		echo "</tfoot>";

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
		echo "</div>";

		do_action('affiliate_after_visits_table', $user_ID);

		$html = ob_get_contents();
		ob_end_clean();

		return $html;

	}

	function show_top_visits_table() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			return '';
		}

		ob_start();

		do_action('affiliate_before_topreferrers_table', $user_ID);

		// Build 18 months of years
		$startat = strtotime(date("Y-m-15"));
		$years = array();
		for($n = 0; $n < 18; $n++) {
			$rdate = strtotime("-$n month", $startat);
			$years[] = "'" . date('Ym', $rdate) . "'";
		}

		$rows = $this->db->get_results( $this->db->prepare( "SELECT url, SUM(referred) as totalreferred FROM {$this->affiliatereferrers} WHERE user_id = %d AND period in (" . implode(',', $years) . ") GROUP BY url ORDER BY totalreferred DESC LIMIT 0, 15", $user_ID ) );
		echo "<div id='topvisitstable' style=''>";
		echo "<table class='widefat'>";

		echo "<thead>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo  __('Top referrers over past 18 months','affiliate');
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __('Visits','affiliate');
			echo "</th>";
			echo "</tr>";
		echo "</thead>";

		echo "<tfoot>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo  __('Top referrers over past 18 months','affiliate');
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __('Visits','affiliate');
			echo "</th>";
			echo "</tr>";
		echo "</tfoot>";

		echo "<tbody>";

		if(!empty($rows)) {

			$class = 'alternate';
			foreach($rows as $r) {

				echo "<tr class='$class' style='$style'>";
				echo "<td style='padding: 5px;'>";
				echo "<a href='http://" . $r->url . "'>" . $r->url . "</a>";
				echo "</td>";
				echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
				echo $r->totalreferred;
				echo "</td>";
				echo "</tr>";

				if($class != 'alternate') {
					$class = '';
				} else {
					$class = 'alternate';
				}

			}

		} else {
			echo __('<tr><td colspan="2">You have no overall referred visits.</td></tr>','affiliate');
		}

		echo "</tbody>";
		echo "</table>";
		echo "</div>";

		do_action('affiliate_after_topreferrers_table', $user_ID);

		$html = ob_get_contents();
		ob_end_clean();

		return $html;

	}

	function show_visits_chart() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			return '';
		}

		ob_start();
		echo "<div id='affvisitlegend' style='background-color: #fff;'>";
		echo "</div>";

		echo "<div id='affvisitgraph' style='width: 100%; min-height: 350px; background-color: #fff;'>";
		echo "</div>";

		$html = ob_get_contents();
		ob_end_clean();

		return $html;

	}

	function is_duplicate_url( $url, $user_id ) {
		$affiliate = $this->db->get_var( $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_referrer' AND meta_value='%s' AND user_id != %d", $url, $user_id) );

		if(empty($affiliate)) {
			return false;
		} else {
			return true;
		}

	}

	function validate_url_for_file( $url, $file ) {

		$fullurl = 'http://' . $url . $file;

		$response = wp_remote_head($fullurl);

		if(!empty($response['response']['code']) && $response['response']['code'] == '200') {
			return true;
		} else {
			return false;
		}

	}

	function show_user_details( $bannerlink = '' ) {
		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			return '';
		}

		if(is_multisite()) {
			$getoption = 'get_site_option';
			$site = $getoption('site_name');
			$url = get_blog_option(1,'home');
		} else {
			$getoption = 'get_option';
			$site = $getoption('blogname');
			$url = $getoption('home');
		}

		ob_start();

		if(isset($_POST['action']) && addslashes($_POST['action']) == 'update') {

			check_admin_referer("affiliatepublicsettings-" . $user_ID);

			update_user_meta($user_ID, 'enable_affiliate', $_POST['enable_affiliate']);
			update_user_meta($user_ID, 'affiliate_paypal', $_POST['affiliate_paypal']);
			if(isset($_POST['affiliate_referrer'])) {

				$url = str_replace('http://', '', untrailingslashit($_POST['affiliate_referrer']));
				// store the update - even though it could be wrong
				update_user_meta($user_ID, 'affiliate_referrer', $url );
				// Remove any validated referrers as it may have been changed
				delete_user_meta($user_ID, 'affiliate_referrer_validated');

				// Check for duplicate and if not unique we need to display the open box with an error message
				if($this->is_duplicate_url($url, $user_ID)) {
					$error = 'yes';
					$chkmsg = __('This URL is already in use.','affiliate');
				} else {
					// Create the message we are looking for
					$chkmsg = '';
					// Check a file with it exists and contains the content
					if(defined('AFFILIATE_VALIDATE_REFERRER_URLS') && AFFILIATE_VALIDATE_REFERRER_URLS == 'yes' ) {
						$referrer = $_POST['affiliate_referrer'];
						$filename = md5('affiliatefilename-' . $user_ID . '-' . $user->user_login . "-" . $referrer) . '.html';

						if($this->validate_url_for_file( trailingslashit($url), $filename)) {
							update_user_meta($user_ID, 'affiliate_referrer_validated', 'yes');
							$chkmsg = __('Validated', 'affiliate');
						} else {
							$error = 'yes';
							$chkmsg = __('Not validated', 'affiliate');
						}
					}
				}

			} else {
				delete_user_meta($user_ID, 'affiliate_referrer_validated');
				delete_user_meta($user_ID, 'affiliate_referrer');
			}
			if(isset($_POST['enable_affiliate']) && addslashes($_POST['enable_affiliate']) == 'yes') {
				// Set up the affiliation details
				// Store a record of the reference
				$reference = $user->user_login . '-' . strrev(sprintf('%02d', $user_ID + 35));
				update_user_meta($user_ID, 'affiliate_reference', $reference);
				update_user_meta($user_ID, 'affiliate_hash', 'aff' . md5(AUTH_SALT . $reference));
			} else {
				// Wipe the affiliation details
				delete_user_meta($user_ID, 'affiliate_reference');
				delete_user_meta($user_ID, 'affiliate_hash');
			}

		}

		echo "<div class='formholder'>";
		if(get_user_meta($user_ID, 'enable_affiliate', true) == 'yes') {
			echo "<strong>" . __('Hello, Thank you for supporting us</strong>, to view or change any of your affiliate settings click on the edit link.','affiliate') . "</strong><a href='#view' id='editaffsettingslink' style='float:right; font-size: 8pt;'>" . __('edit','affiliate') . "</a>";

			if(empty($error)) {
				echo "<div class='innerbox closed'>";
			} else {
				echo "<div class='innerbox open'>";
			}


			echo "<form action='' method='post'>";
			wp_nonce_field( "affiliatepublicsettings-" . $user_ID );

			echo "<input type='hidden' name='action' value='update' />";

			$settingstextdefault = "<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p>
		<p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p>
		<p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>";

			echo stripslashes( $getoption('affiliatesettingstext', $settingstextdefault) );

			?>

			<table class="form-table">
				<tr style='background: transparent;'>
					<th><label for="enable_affiliate"><?php _e('Enable Affiliate links', 'affiliate'); ?></label></th>
					<td>
						<select name='enable_affiliate'>
							<option value='yes' <?php if(get_user_meta($user_ID, 'enable_affiliate', true) == 'yes') echo "selected = 'selected'"; ?>><?php _e('Yes please', 'affiliate'); ?></option>
							<option value='no' <?php if(get_user_meta($user_ID, 'enable_affiliate', true) != 'yes') echo "selected = 'selected'"; ?>><?php _e('No thanks', 'affiliate'); ?></option>
						</select>
					</td>
				</tr>

				<tr style='background: transparent;'>
					<th><label for="affiliate_paypal"><?php _e('PayPal Email Address', 'affiliate'); ?></label></th>
					<td>
					<input type="text" name="affiliate_paypal" id="affiliate_paypal" value="<?php echo get_user_meta($user_ID, 'affiliate_paypal', true); ?>" class="regular-text" />
					</td>
				</tr>

			</table>

			<?php

			if(get_user_meta($user_ID, 'enable_affiliate', true) == 'yes') {

				$reference = get_user_meta($user_ID, 'affiliate_reference', true);
				$referrer = get_user_meta($user_ID, 'affiliate_referrer', true);
				$refurl = "profile.php?page=affiliateearnings";

				$validreferrer = get_user_meta($user_ID, 'affiliate_referrer_validated', true);

				if(defined('AFFILIATE_CHECKALL')) { ?>

					<h3><?php _e('Affiliate Advanced Settings', 'affiliate') ?></h3>

					<?php
					$advsettingstextdefault = "<p>There are times when you would rather hide your affiliate link, or simply not have to bother remembering the affiliate reference to put on the end of our URL.</p>
				<p>If this is the case, then you can enter the main URL of the site you will be sending requests from below, and we will sort out the tricky bits for you.</p>";

					echo stripslashes( $getoption('affiliateadvancedsettingstext', $advsettingstextdefault) );

						if(!empty($chkmsg)) {
							if(empty($error)) {
								// valid
								$msg = "<span style='color: green;'>" . $chkmsg . "</span>";
							} else {
								// not valid
								$msg = "<span style='color: red;'>" . $chkmsg . "</span>";
							}
						}

					?>

					<table class="form-table">
						<tr style='background: transparent;'>
							<th valign='top'><label for="affiliate_referrer"><?php _e('Your URL', 'affiliate'); ?></label></th>
							<td>
								http://&nbsp;<input type="text" name="affiliate_referrer" id="affiliate_referrer" value="<?php echo get_user_meta($user_ID, 'affiliate_referrer', true); ?>" class="regular-text" /><?php echo "&nbsp;&nbsp;" . $msg;?>
								<?php
								if(defined('AFFILIATE_VALIDATE_REFERRER_URLS') && AFFILIATE_VALIDATE_REFERRER_URLS == 'yes' ) {
									if(!empty($validreferrer) && $validreferrer == 'yes') {}
									else {
										// Not valid - generate filename
										$filename = md5('affiliatefilename-' . $user_ID . '-' . $user->user_login . "-" . $referrer) . '.html';

										// Output message
										echo "<br/>";
										_e('You need to validate this URL by uploading a file to the root of the site above with the following name : ','affiliate');
										echo "<br/>";
										echo __('Filename : ', 'affiliate') . $filename;
										echo " <a href='http://" . trailingslashit($referrer) . $filename . "' target=_blank>" . __('[click here to check if the file exists]') . "</a>";
										echo '<br/><input type="submit" name="Submit" class="button" value="' . __('Validate','affiliate') . '" />';
									}
								}

								?>
							</td>
						</tr>

					</table>

				<?php
				}
				?>
				<p><?php _e('<h3>Affiliate Details</h3>', 'affiliate') ?></p>
				<p><?php _e(sprintf('In order for us to track your referrals, you should use the following URL to link to our site:'), 'affiliate') ?></p>
				<p><?php _e(sprintf('<strong>%s?ref=%s</strong>', $url, $reference ), 'affiliate') ?></p>

				<?php
					if(defined('AFFILIATE_CHECKALL') && !empty($referrer)) {
						// We are always going to check for a referer site
						?>
						<p><?php _e(sprintf('Alternatively you can just link directly to the URL below from the site you entered in the advanced settings above:'), 'affiliate') ?></p>
						<p><?php _e(sprintf('<strong>%s</strong>', $url ), 'affiliate') ?></p>
						<?php

					}


				if($getoption('affiliateenablebanners', 'no') == 'yes' && !empty($bannerlink)) {
				?>
				<p><?php _e(sprintf('If you would rather use a banner or button then we have a wide selection of sizes <a href="%s">here</a>.', $bannerlink ), 'affiliate') ?></p>
				<?php } ?>
				<p><?php _e(sprintf('<strong>You can check on your referral totals by viewing the details on this page</strong>' ), 'affiliate') ?></p>
			<?php
			}

			echo '<p class="submit">';
			echo '<input type="submit" name="Submit" value="' . __('Update Settings','affiliate') . '" /></p>';

			echo "</form>";
			echo "</div>";

		} else {
			// Not an affiliate yet, so display the form
			echo "<strong>" . __('Hello, why not consider becoming an affiliate?','affiliate') . "</strong><br/>";

			echo "<div class='innerbox open'>";

			echo "<form action='' method='post'>";
			wp_nonce_field( "affiliatepublicsettings-" . $user_ID );

			echo "<input type='hidden' name='action' value='update' />";


			$settingstextdefault = "<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p>
		<p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p>
		<p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>";

			echo stripslashes( $getoption('affiliatesettingstext', $settingstextdefault) );

			?>

			<table class="form-table">
				<tr style='background: transparent;'>
					<th><label for="enable_affiliate"><?php _e('Enable Affiliate links', 'affiliate'); ?></label></th>
					<td>
						<select name='enable_affiliate'>
							<option value='yes' <?php if(get_user_meta($user_ID, 'enable_affiliate', true) == 'yes') echo "selected = 'selected'"; ?>><?php _e('Yes please', 'affiliate'); ?></option>
							<option value='no' <?php if(get_user_meta($user_ID, 'enable_affiliate', true) != 'yes') echo "selected = 'selected'"; ?>><?php _e('No thanks', 'affiliate'); ?></option>
						</select>
					</td>
				</tr>

				<tr style='background: transparent;'>
					<th><label for="affiliate_paypal"><?php _e('PayPal Email Address', 'affiliate'); ?></label></th>
					<td>
					<input type="text" name="affiliate_paypal" id="affiliate_paypal" value="<?php echo get_user_meta($user_ID, 'affiliate_paypal', true); ?>" class="regular-text" />
					</td>
				</tr>

			</table>

			<?php

			echo '<p class="submit">';
			echo '<input type="submit" name="Submit" value="' . __('Update Settings','affiliate') . '" /></p>';

			echo "</form>";
			echo "</div>";


		}

		echo "</div>";

		$html = ob_get_contents();
		ob_end_clean();

		return $html;

	}

	function output_banners() {
		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(empty($user_ID)) {
			return '';
		}

		$reference = get_user_meta($user_ID, 'affiliate_reference', true);

		if(function_exists('is_multisite') && is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$getoption = 'get_site_option';
			$site = $getoption('site_name');
			$url = get_blog_option(1,'home');
		} else {
			$getoption = 'get_option';
			$site = $getoption('blogname');
			$url = $getoption('home');
		}

		ob_start();

		$banners = $getoption('affiliatebannerlinks');
		foreach((array) $banners as $banner) {

			?>
			<img src='<?php echo $banner; ?>' />
			<br/><br/>
			<textarea cols='80' rows='5'><?php
				echo sprintf("<a href='%s?ref=%s'>\n", $url, $reference);
				echo "<img src='" . $banner . "' alt='" . htmlentities(stripslashes($site),ENT_QUOTES, 'UTF-8') . "' title='Check out " . htmlentities(stripslashes($site),ENT_QUOTES, 'UTF-8') . "' />\n";
				echo "</a>";
			?></textarea>
			<br/><br/>
			<?php

		}

		$html = ob_get_contents();
		ob_end_clean();

		return $html;

	}

	function do_affiliatebanners_shortcode($atts, $content = null, $code = "") {
		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;

		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $prefix;
		$html .= $this->output_banners();
		$html .= $postfix;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}



}

$affshortcode = new affiliateshortcodes();

?>