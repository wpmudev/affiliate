<?php

// Administration side of the affiliate system
class affiliateadmin {

	var $build = 7;

	var $db;

	var $mylocation = "";
	var $plugindir = "";
	var $base_uri = '';

	// The page on the public side of the site that has details of the affiliate plan
	var $affiliateinformationpage = 'affiliates';

	var $tables = array('affiliatedata','affiliatereferrers','affiliaterecords');

	var $affiliatedata;
	var $affiliatereferrers;
	var $affiliaterecords;

	function __construct() {

		global $wpdb;

		// Grab our own local reference to the database class
		$this->db =& $wpdb;

		foreach ($this->tables as $table) {
			if( (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) && (defined('AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED') && AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED == 'yes')) {
				// we're activated site wide
				$this->$table = $this->db->base_prefix . $table;
			} else {
				if(defined('AFFILIATE_USE_BASE_PREFIX_IF_EXISTS') && AFFILIATE_USE_BASE_PREFIX_IF_EXISTS == 'yes' && !empty($this->db->base_prefix)) {
					$this->$table = $this->db->base_prefix . $table;
				} else {
					// we're only activated on a blog level so put the admin menu in the main area
					$this->$table = $this->db->prefix . $table;
				}
			}
		}

		$installed = aff_get_option('Aff_Installed', false);

		if($installed === false || $installed != $this->build) {
			$this->install();

			aff_update_option('Aff_Installed', $this->build);
		}

		register_activation_hook(__FILE__, array(&$this, 'install'));

		add_action( 'init', array( &$this, 'initialise_ajax' ), 1 );

		add_action( 'init', array(&$this, 'aff_report_header'), 999 );

		add_action( 'init', array(&$this, 'handle_export_link' ) );

		add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

		// Menus and profile page
		add_action( 'admin_menu', array(&$this, 'add_menu_items') );
		add_action( 'network_admin_menu', array(&$this, 'add_menu_items') );

		add_action( 'show_user_profile', array(&$this, 'add_profile_box' ) );
		add_action( 'personal_options_update', array(&$this, 'update_profile_box' ) );

		// Affiliate blog and user reporting
		add_filter( 'wpmu_blogs_columns', array(&$this, 'add_affiliate_column' ) );
		add_action( 'manage_blogs_custom_column', array(&$this, 'show_affiliate_column' ), 10, 2 );

		add_filter( 'manage_users_columns', array(&$this, 'add_user_affiliate_column') );
		add_filter( 'wpmu_users_columns', array(&$this, 'add_user_affiliate_column') );
		add_filter( 'manage_users_custom_column', array(&$this, 'show_user_affiliate_column'), 10, 3 );

		add_action( 'pre_user_query', array(&$this, 'override_referrer_search'));
		add_filter( 'user_row_actions', array(&$this, 'add_referrer_search_link'), 10, 2 );
		add_filter( 'ms_user_row_actions', array(&$this, 'add_referrer_search_link'), 10, 2 );

		// Include affiliate plugins
		load_affiliate_addons();

	}

	function affiliateadmin() {
		$this->__construct();
	}

	function install() {
		$this->create_affiliate_tables();
	}

	function create_affiliate_tables() {


		if($this->db->get_var( "SHOW TABLES LIKE '" . $this->affiliatedata . "' ") != $this->affiliatedata) {
			 $sql = "CREATE TABLE `" . $this->affiliatedata . "` (
			  	`user_id` bigint(20) default NULL,
			  	`period` varchar(6) default NULL,
			  	`uniques` bigint(20) default '0',
			  	`signups` bigint(20) default '0',
			  	`completes` bigint(20) default '0',
			  	`debits` decimal(10,2) default '0.00',
			  	`credits` decimal(10,2) default '0.00',
			  	`payments` decimal(10,2) default '0.00',
			  	`lastupdated` datetime default '0000-00-00 00:00:00',
			  	UNIQUE KEY `user_period` (`user_id`,`period`),
			  	KEY `period` (`period`),
			  	KEY `user_id` (`user_id`)
				)";

			$this->db->query($sql);

			$sql = "CREATE TABLE `" . $this->affiliatereferrers . "` (
			  	`user_id` bigint(20) default NULL,
			  	`period` varchar(6) default NULL,
			  	`url` varchar(250) default NULL,
			  	`referred` bigint(20) default '0',
			  	UNIQUE KEY `user_id` (`user_id`,`period`,`url`)
				)";

			$this->db->query($sql);
		}

		if($this->db->get_var( "SHOW TABLES LIKE '" . $this->affiliaterecords . "' ") != $this->affiliaterecords) {
			 $sql = "CREATE TABLE `" . $this->affiliaterecords . "` (
			  	`user_id` bigint(20) unsigned NOT NULL,
				  `period` varchar(6) DEFAULT NULL,
				  `affiliatearea` varchar(50) DEFAULT NULL,
				  `area_id` bigint(20) DEFAULT NULL,
				  `affiliatenote` text,
				  `amount` decimal(10,2) DEFAULT NULL,
				  KEY `user_id` (`user_id`),
				  KEY `period` (`period`)
				)";

			$this->db->query($sql);
		}

		if( (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) && (defined('AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED') && AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED == 'yes')) {

			// We need to check for a transfer across from old options to new ones
			$option = aff_get_option('affiliateheadings', false );
			if( $option == false ) {
				$option = get_blog_option(1, 'affiliateheadings');
				aff_update_option('affiliateheadings', $option);
			}

			$option = aff_get_option('affiliatesettingstext', false );
			if( $option == false ) {
				$option = get_blog_option(1, 'affiliatesettingstext');
				aff_update_option('affiliatesettingstext', $option);
			}

			$option = aff_get_option('affiliateadvancedsettingstext', false );
			if( $option == false ) {
				$option = get_blog_option(1, 'affiliateadvancedsettingstext');
				aff_update_option('affiliateadvancedsettingstext', $option);
			}

			$option = aff_get_option('affiliateenablebanners', false );
			if( $option == false ) {
				$option = get_blog_option(1, 'affiliateenablebanners');
				aff_update_option('affiliateenablebanners', $option);
			}

			$option = aff_get_option('affiliatelinkurl', false );
			if( $option == false ) {
				$option = get_blog_option(1, 'affiliatelinkurl');
				aff_update_option('affiliatelinkurl', $option);
			}

			$option = aff_get_option('affiliatebannerlinks', false );
			if( $option == false ) {
				$option = get_blog_option(1, 'affiliatebannerlinks');
				aff_update_option('affiliatebannerlinks', $option);
			}

			$option = aff_get_option('affiliate_activated_addons', false );
			if( $option == false ) {
				$option = get_blog_option(1, 'affiliate_activated_addons');
				aff_update_option('affiliate_activated_addons', $option);
			}


		}


	}

	function load_textdomain() {

		$locale = apply_filters( 'affiliate_locale', get_locale() );
		$mofile = affiliate_dir( "affiliateincludes/languages/affiliate-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'affiliate', $mofile );

	}

	function initialise_ajax() {
		add_action( 'wp_ajax__aff_getstats', array(&$this,'ajax__aff_getstats') );
		add_action( 'wp_ajax__aff_getvisits', array(&$this,'ajax__aff_getvisits') );
	}

	function ajax__aff_getstats() {

		global $user;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		$headings = aff_get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));

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


	function add_menu_items() {

		global $submenu;

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		// Add administration menu
		if(is_multisite()) {
			if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
				// we're activated site wide so put the admin menu in the network area
				if(function_exists('is_network_admin')) {
					if(is_network_admin()) {
						add_menu_page( __('Affiliates', 'affiliate'), __('Affiliates', 'affiliate'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'), affiliate_url('affiliateincludes/images/affiliatelogo.png'));
					}
				}
			} else {
				// we're only activated on a blog level so put the admin menu in the main area
				if(!function_exists('is_network_admin')) {
					add_menu_page( __('Affiliates', 'affiliate'), __('Affiliates', 'affiliate'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'), affiliate_url('affiliateincludes/images/affiliatelogo.png'));
				} elseif(!is_network_admin()) {
					add_menu_page( __('Affiliates', 'affiliate'), __('Affiliates', 'affiliate'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'), affiliate_url('affiliateincludes/images/affiliatelogo.png'));
				}
			}
		} else {
			add_menu_page( __('Affiliates', 'affiliate'), __('Affiliates', 'affiliate'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'), affiliate_url('affiliateincludes/images/affiliatelogo.png'));
		}

		add_submenu_page( 'affiliatesadmin', __('Manage Affiliates', 'affiliate'), __('Manage Affiliates', 'affiliate'), 'manage_options', 'affiliatesadminmanage', array(&$this,'handle_affiliates_panel') );
		add_submenu_page( 'affiliatesadmin', __('Settings', 'affiliate'), __('Settings', 'affiliate'), 'manage_options', 'affiliatesadminsettings', array(&$this,'handle_affiliates_panel') );
		add_submenu_page( 'affiliatesadmin', __('Add-ons', 'affiliate'), __('Add-ons', 'affiliate'), 'manage_options', 'affiliatesadminaddons', array(&$this,'handle_affiliates_panel') );

		if(isset($submenu['affiliatesadmin'])) {
			$submenu['affiliatesadmin'][0][0] = __('Affiliate Reports', 'affiliate');
		}

		add_submenu_page('users.php', __('Affiliate Earnings Report','affiliate'), __('Affiliate Referrals','affiliate'), 'read', "affiliateearnings", array(&$this,'add_profile_report_page'));

		// Add profile menu
		if(get_user_meta($user_ID, 'enable_affiliate', true) == 'yes') {
			if(aff_get_option('affiliateenablebanners', 'no') == 'yes') {
				add_submenu_page('users.php', __('Affiliate Banners','affiliate'), __('Affiliate Banners','affiliate'), 'read', "affiliatebanners", array(&$this,'add_profile_banner_page'));
			}
		}
	}

	function add_profile_box() {

		// Removed for now

	}

	function update_profile_box() {

		// Removed for now

	}

	function aff_report_header() {

		// Main user report page
		if(isset($_GET['page']) && addslashes($_GET['page']) == 'affiliateearnings') {
			wp_enqueue_script('flot_js', affiliate_url('affiliateincludes/js/jquery.flot.min.js'), array('jquery'));
			wp_enqueue_script('flot_js', affiliate_url('affiliateincludes/js/jquery.flot.pie.min.js'), array('flot_js'));
			wp_enqueue_script('aff_js', affiliate_url('affiliateincludes/js/affiliateliteuserreport.js'), array('jquery'));

			add_action('admin_head', array(&$this, 'add_iehead') );
		}

		// Admin user report page
		if( (isset($_GET['page']) && addslashes($_GET['page']) == 'affiliatesadminmanage') && ( isset($_GET['subpage']) && addslashes($_GET['subpage']) == 'users') && isset($_GET['id'])) {
			wp_enqueue_script('flot_js', affiliate_url('affiliateincludes/js/jquery.flot.min.js'), array('jquery'));

			wp_enqueue_script('aff_js', affiliate_url('affiliateincludes/js/affiliateadminuserreport.js'), array('jquery'));

			add_action('admin_head', array(&$this, 'add_iehead') );
		}


	}

	function add_iehead() {
		echo '<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="' . affiliate_url('affiliateincludes/js/excanvas.min.js') . '"></script><![endif]-->';
	}

	function is_duplicate_url( $url, $user_id ) {

		if(empty($url)) return false;

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

	function add_profile_report_page() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		$headings = aff_get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));

		$headings = array_merge($headings, array( __('Debits','affiliate'), __('Credits','affiliate'), __('Payments','affiliate') ));

		$newcolumns = apply_filters('affiliate_column_names', $headings);
		if(count($newcolumns) == 6) {
			// We must have 6 columns
			$columns = $newcolumns;
		}

		$reference = get_user_meta($user_ID, 'affiliate_reference', true);

		if(function_exists('is_multisite') && is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$site = aff_get_option('site_name');
			// switch to use new option
			$siteurl = get_blog_option(1,'home');
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		} else {
			$site = aff_get_option('blogname');
			// switch to use new option
			$siteurl = aff_get_option('home');
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		}

		if(isset($_POST['action']) && addslashes($_POST['action']) == 'update') {

			check_admin_referer('affiliatesettings');

			update_user_meta($user_ID, 'enable_affiliate', $_POST['enable_affiliate']);
			update_user_meta($user_ID, 'affiliate_paypal', $_POST['affiliate_paypal']);
			if(!empty($_POST['affiliate_referrer'])) {

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
				$reference = $user->user_login . '-' . strrev(sprintf('%02d', $user_ID + (int) AFFILIATE_REFERENCE_KEY));
				update_user_meta($user_ID, 'affiliate_reference', $reference);
				update_user_meta($user_ID, 'affiliate_hash', 'aff' . md5(AUTH_SALT . $reference));
			} else {
				// Wipe the affiliation details
				delete_user_meta($user_ID, 'affiliate_reference');
				delete_user_meta($user_ID, 'affiliate_hash');
			}

		}

		echo "<div class='wrap'>";
		echo '<div class="icon32" id="icon-themes"><br/></div>';
		echo "<h2>" . __('Affiliate Referral Report','affiliate') . "</h2>";

			echo "<div style='width: 98%; margin-top: 20px; background-color: #FFFEEB; margin-left: auto; margin-right: auto; margin-bottom: 20px; border: 1px #e6db55 solid; padding: 10px;'>";
			if(get_user_meta($user_ID, 'enable_affiliate', true) == 'yes') {
				echo "<strong>" . __('Hello, Thank you for supporting us</strong>, to view or change any of your affiliate settings click on the edit link.','affiliate') . "</strong><a href='#view' id='editaffsettingslink' style='float:right; font-size: 8pt;'>" . __('edit','affiliate') . "</a>";

				if(empty($error)) {
					echo "<div id='innerbox' style='width: 100%; display: none;'>";
				} else {
					echo "<div id='innerbox' style='width: 100%;'>";
				}

				echo "<form action='' method='post'>";
				wp_nonce_field( "affiliatesettings" );

				echo "<input type='hidden' name='action' value='update' />";

				$settingstextdefault = __("<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p>
			<p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p>
			<p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>", 'affiliate');

				echo stripslashes( aff_get_option('affiliatesettingstext', $settingstextdefault) );

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

					if(defined('AFFILIATE_CHECKALL') && AFFILIATE_CHECKALL == 'yes') { ?>

						<h3><?php _e('Affiliate Advanced Settings', 'affiliate') ?></h3>

						<?php
						$advsettingstextdefault = __("<p>There are times when you would rather hide your affiliate link, or simply not have to bother remembering the affiliate reference to put on the end of our URL.</p>
					<p>If this is the case, then you can enter the main URL of the site you will be sending requests from below, and we will sort out the tricky bits for you.</p>", 'affiliate');

						echo stripslashes( aff_get_option('affiliateadvancedsettingstext', $advsettingstextdefault) );


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
								<th><label for="affiliate_referrer"><?php _e('Your URL', 'affiliate'); ?></label></th>
								<td>
									http://&nbsp;<input type="text" name="affiliate_referrer" id="affiliate_referrer" value="<?php echo $referrer; ?>" class="regular-text" />
									<?php 	echo "&nbsp;&nbsp;";
									 		if (isset($msg)) {
												echo $msg;
											}
									?>
									<?php
									if(defined('AFFILIATE_VALIDATE_REFERRER_URLS') && AFFILIATE_VALIDATE_REFERRER_URLS == 'yes' ) {
										if(empty($referrer) || (!empty($validreferrer) && $validreferrer == 'yes')) {}
										else {
											// Not valid - generate filename
											$filename = md5('affiliatefilename-' . $user_ID . '-' . $user->user_login . "-" . $referrer) . '.html';

											// Output message
											echo "<br/>";
											_e('You need to validate this URL by uploading a file to the root of the site above with the following name : ','affiliate');
											echo "<br/>";
											echo __('Filename : ', 'affiliate') . $filename;
											echo " <a href='http://" . trailingslashit($referrer) . $filename . "' target=_blank>" . __('[click here to check if the file exists]', 'affiliate') . "</a>";
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
					<p><?php _e('In order for us to track your referrals, you should use the following URL to link to our site:', 'affiliate'); ?></p>
					<p><?php echo sprintf(__('<strong>%s?ref=%s</strong>', 'affiliate'), $affiliatelinkurl, $reference ); ?></p>

					<?php
						if(defined('AFFILIATE_CHECKALL') && AFFILIATE_CHECKALL == 'yes' && !empty($referrer)) {
							// We are always going to check for a referer site
							?>
							<p><?php _e('Alternatively you can just link directly to the URL below from the site you entered in the advanced settings above:', 'affiliate'); ?></p>
							<p><?php echo sprintf(__('<strong>%s</strong>', 'affiliate'), $siteurl ); ?></p>
							<?php

						}


					if(aff_get_option('affiliateenablebanners', 'no') == 'yes') {
					?>
					<p><?php echo sprintf(__('If you would rather use a banner or button then we have a wide selection of sizes <a href="%s">here</a>.', 'affiliate'), "profile.php?page=affiliatebanners" ); ?></p>
					<?php } ?>
					<p><?php _e('<strong>You can check on your referral totals by viewing the details on this page</strong>', 'affiliate'); ?></p>
				<?php
				}

				echo '<p class="submit">';
				echo '<input type="submit" class="button-primary" name="Submit" value="' . __('Update Settings','affiliate') . '" /></p>';

				echo "</form>";
				echo "</div>";

			} else {
				// Not an affiliate yet, so display the form
				echo "<strong>" . __('Hello, why not consider becoming an affiliate?','affiliate') . "</strong><br/>";

				echo "<div id='innerbox' style='width: 100%'>";

				echo "<form action='' method='post'>";
				wp_nonce_field( "affiliatesettings" );

				echo "<input type='hidden' name='action' value='update' />";


				$settingstextdefault = __("<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p>
			<p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p>
			<p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>", 'affiliate');

				echo stripslashes( aff_get_option('affiliatesettingstext', $settingstextdefault) );

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
				echo '<input type="submit" class="button-primary" name="Submit" value="' . __('Update Settings','affiliate') . '" /></p>';

				echo "</form>";
				echo "</div>";


			}

			echo "</div>";


			$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC", $user_ID ) );


			echo "<div id='affdashgraph' style='width: 100%; margin-top: 20px; min-height: 350px; background-color: #fff; margin-bottom: 20px;'>";
			echo "</div>";

			echo "<div id='clickscolumn' style='width: 48%; margin-right: 2%; margin-top: 20px; min-height: 400px; float: left;'>";

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

				echo "<tr class='periods' id='period-$place'>";
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


			echo "<div id='referrerscolumn' style='width: 48%; margin-left: 2%; min-height: 400px; margin-top: 20px; background: #fff; float: left;'>";

			do_action('affiliate_before_profile_graphs', $user_ID);
			do_action('affiliate_before_visits_table', $user_ID);

			echo "<div id='affvisitgraph' style='width: 100%; min-height: 350px; background-color: #fff; margin-bottom: 20px;'>";
			echo "</div>";

			// This months visits table
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

			do_action('affiliate_after_visits_table', $user_ID);

			do_action('affiliate_before_topreferrers_table', $user_ID);

			// Top referrers of all time

			// Build 18 months of years
			$startat = strtotime(date("Y-m-15"));
			$years = array();
			for($n = 0; $n < 18; $n++) {
				$rdate = strtotime("-$n month", $startat);
				$years[] = "'" . date('Ym', $rdate) . "'";
			}

			$rows = $this->db->get_results( $this->db->prepare( "SELECT url, SUM(referred) as totalreferred FROM {$this->affiliatereferrers} WHERE user_id = %d AND period in (" . implode(',', $years) . ") GROUP BY url ORDER BY totalreferred DESC LIMIT 0, 15", $user_ID ) );
			echo "<br/>";
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

			do_action('affiliate_after_profile_graphs', $user_ID);

			echo "<div style='clear: both;'></div>";

		?>

		</div>
		<?php

	}

	function add_profile_banner_page() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		$reference = get_user_meta($user_ID, 'affiliate_reference', true);

		if(function_exists('is_multisite') && is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$site = aff_get_option('site_name');
			// switch to use new option
			$siteurl = get_blog_option(1,'home');
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		} else {
			$site = aff_get_option('blogname');
			// switch to use new option
			$siteurl = aff_get_option('home');
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		}

		?>
		<div class='wrap'>
		<h2>Affiliate Banners</h2>

		<p><?php _e("So, you want something more exciting than a straight forward text link?",'affiliate'); ?></p>
		<p><?php _e("Not to worry, we've got banners and buttons galore. To use them simply copy and paste the HTML underneath the graphic that you want to use.",'affiliate'); ?></p>

		<?php

		$banners = aff_get_option('affiliatebannerlinks');
		foreach((array) $banners as $banner) {
			// Split the string in case there is a | in there
			$advbanner = explode("|", $banner);
			if(count($advbanner) == 1) {
				$advbanner[] = $affiliatelinkurl;
			}
			// Trim the array so that it removes none text characters
			array_map('trim', $advbanner);
			?>
			<img src='<?php echo $advbanner[0]; ?>' />
			<br/><br/>
			<textarea cols='80' rows='5'><?php
				echo sprintf("<a href='%s?ref=%s'>\n", $advbanner[1], $reference);
				echo "<img src='" . $advbanner[0] . "' alt='" . htmlentities(stripslashes($site),ENT_QUOTES, 'UTF-8') . "' title='Check out " . htmlentities(stripslashes($site),ENT_QUOTES, 'UTF-8') . "' />\n";
				echo "</a>";
			?></textarea>
			<br/><br/>
			<?php

		}

		?>

		</div>
		<?php
	}

	function handle_export_link() {

		$page = (isset($_GET['page'])) ? addslashes($_GET['page']) : false;

		if($page == 'affiliatesadmin' && isset($_GET['action'])) {

			switch(addslashes($_GET['action'])) {

				case 'allaffiliates':	// Bulk operations
										check_admin_referer('allaffiliateactions');
										if(isset($_POST['allaction_exportpayments'])) {
											// Create an export file
											header("Content-type: application/octet-stream");
											header("Content-Disposition: attachment; filename=\"masspayexport.txt\"");

											if(!empty($_POST['allpayments'])) {
												foreach($_POST['allpayments'] as $affiliate) {
													// Reset variables
													$paypal = "";
													$amount = "0.00";
													$currency = "USD";
													$id = "AFF_PAYMENT";
													$notes = __("Affiliate payment for", "affiliate");

													$affdetails = explode('-', $affiliate);
													if(count($affdetails) == 2) {

														$name = get_option('blogname');

														$user = get_userdata($affdetails[0]);

														$id = substr("AFF_PAYMENT_" . strtoupper($user->user_login),0, 30);
														$notes = __("Affiliate payment for ", "affiliate") . $name;

														$paypal = get_user_meta($affdetails[0], 'affiliate_paypal', true);
														$amounts = $this->db->get_row( "SELECT debits, credits FROM " . $this->affiliatedata . " WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );

														$amount = $amounts->credits - $amounts->debits;

														if($amount > 0 && !empty($paypal)) {
															$line = sprintf("%s\t%01.2f\t%s\t%s\t%s\n", $paypal, $amount, $currency, $id, $notes);
															echo $line;
														}

													}
												}
											}

											die();
										}

			}

		}

	}

	function handle_affiliate_settings_panel() {

		if(isset($_GET['action']) && addslashes($_GET['action']) == 'updateaffiliateoptions') {
			check_admin_referer('affiliateoptions');

			$headings = array();
			$headings[] = $_POST['uniqueclicks'];
			$headings[] = $_POST['signups'];
			$headings[] = $_POST['paidmembers'];

			aff_update_option('affiliateheadings', $headings);

			aff_update_option('affiliatesettingstext', $_POST['affiliatesettingstext']);
			aff_update_option('affiliateadvancedsettingstext', $_POST['affiliateadvancedsettingstext']);

			aff_update_option('affiliateenablebanners', $_POST['affiliateenablebanners']);

			if(!empty($_POST['affiliatelinkurl'])) {
				aff_update_option('affiliatelinkurl', $_POST['affiliatelinkurl']);
			} else {
				aff_delete_option('affiliatelinkurl');
			}

			$banners = explode( "\n", stripslashes($_POST['affiliatebannerlinks']));

			foreach($banners as $key => $b) {
				$banners[$key] = trim($b);
			}
			aff_update_option('affiliatebannerlinks', $banners);

			do_action('affililate_settings_form_update');

			echo '<div id="message" class="updated fade"><p>' . __('Affiliate settings saved.','affiliate') . '</p></div>';
		}

		$page = (isset($_GET['page'])) ? addslashes($_GET['page']) : '';
		$subpage = (isset($_GET['subpage'])) ? addslashes($_GET['subpage']) : '';

		echo '<div  id="poststuff" class=class="metabox-holder m-settings">';

		echo '<form method="post" action="?page=' . $page . '&amp;subpage=' . $subpage . '&amp;action=updateaffiliateoptions">';
		wp_nonce_field( "affiliateoptions" );

		echo '<div class="postbox">';
		echo '<h3 class="hndle" style="cursor:auto;"><span>' . __('Affiliate Link URL', 'affiliate') . '</span></h3>';

		echo '<div class="inside">';

		if(function_exists('is_multisite') && is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			// switch to use new option
			$siteurl = get_blog_option(1,'home');
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		} else {
			// switch to use new option
			$siteurl = aff_get_option('home');
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		}

		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th valign="top" scope="row">' . __('Link URL','affiliate') . '</th>';
		echo '<td valign="top">';
		echo '<input name="affiliatelinkurl" type="text" id="affiliatelinkurl" style="width: 50%" value="' . htmlentities(stripslashes($affiliatelinkurl),ENT_QUOTES, 'UTF-8') . '" />';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		echo '</div>';
		echo '</div>';


		echo '<div class="postbox">';
		echo '<h3 class="hndle" style="cursor:auto;"><span>' . __('Column Settings', 'affiliate') . '</span></h3>';

		echo '<div class="inside">';

		$headings = aff_get_option( 'affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')) );

		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th valign="top" scope="row">' . __('Unique Clicks','affiliate') . '</th>';
		echo '<td valign="top">';
		echo '<input name="uniqueclicks" type="text" id="uniqueclicks" style="width: 50%" value="' . htmlentities(stripslashes($headings[0]),ENT_QUOTES, 'UTF-8') . '" />';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th valign="top" scope="row">' . __('Sign ups','affiliate') . '</th>';
		echo '<td valign="top">';
		echo '<input name="signups" type="text" id="signups" style="width: 50%" value="' . htmlentities(stripslashes($headings[1]),ENT_QUOTES, 'UTF-8') . '" />';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th valign="top" scope="row">' . __('Paid members','affiliate') . '</th>';
		echo '<td valign="top">';
		echo '<input name="paidmembers" type="text" id="paidmembers" style="width: 50%" value="' . htmlentities(stripslashes($headings[2]),ENT_QUOTES, 'UTF-8') . '" />';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		echo '</div>';
		echo '</div>';

		echo '<div class="postbox">';
		echo '<h3 class="hndle" style="cursor:auto;"><span>' . __('Profile page text', 'affiliate') . '</span></h3>';

		echo '<div class="inside">';

		$settingstextdefault = __("<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p>
<p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p>
<p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>", 'affiliate');

		echo '<table class="form-table">';
		echo '<tr valign="top">';
		echo '<th scope="row">' . __('Affiliate settings profile text', 'affiliate') . '</th>';
		echo '<td>';
		$args = array("textarea_name" => "affiliatesettingstext");
		wp_editor( stripslashes( aff_get_option('affiliatesettingstext', $settingstextdefault) ), "affiliatesettingstext", $args );
		echo '</td>';
		echo '</tr>';

		$advsettingstextdefault = __("<p>There are times when you would rather hide your affiliate link, or simply not have to bother remembering the affiliate reference to put on the end of our URL.</p>
<p>If this is the case, then you can enter the main URL of the site you will be sending requests from below, and we will sort out the tricky bits for you.</p>", 'affiliate');

		echo '<table class="form-table">';
		echo '<tr valign="top">';
		echo '<th scope="row">' . __('Affiliate advanced settings profile text', 'affiliate') . '</th>';
		echo '<td>';
		$args = array("textarea_name" => "affiliateadvancedsettingstext");
		wp_editor( stripslashes( aff_get_option('affiliateadvancedsettingstext', $advsettingstextdefault) ), "affiliateadvancedsettingstext", $args );
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		echo '</div>';
		echo '</div>';

		echo '<div class="postbox">';
		echo '<h3 class="hndle" style="cursor:auto;"><span>' . __('Banner Settings', 'affiliate') . '</span></h3>';

		echo '<div class="inside">';

			echo '<table class="form-table">';
			echo '<tr>';
			echo '<th valign="top" scope="row">' . __('Enable Banners','affiliate') . '</th>';
			echo '<td valign="top">';

			echo "<select name='affiliateenablebanners'>";
			echo "<option value='yes'";
			if(aff_get_option('affiliateenablebanners', 'no') == 'yes') echo "selected = 'selected'";
			echo '>' . __('Yes please', 'affiliate') . "</option>";

			echo "<option value='no'";
			if(aff_get_option('affiliateenablebanners', 'no') == 'no') echo "selected = 'selected'";
			echo '>' . __('No thanks', 'affiliate') . "</option>";

			echo "</select>";

			echo '</td>';
			echo '</tr>';

			$banners = aff_get_option('affiliatebannerlinks');
			if(is_array($banners)) {
				$banners = implode("\n", $banners);
			}

			echo '<tr valign="top">';
			echo '<th scope="row">' . __('Banner Image URLs (one per line)', 'affiliate') . '</th>';
			echo '<td>';
			echo '<textarea name="affiliatebannerlinks" id="affiliatebannerlinks" cols="60" rows="10">' . stripslashes( $banners ) . '</textarea>';
			echo '</td>';
			echo '</tr>';

			echo '</table>';

			echo '</div>';
			echo '</div>';


		do_action('affililate_settings_form');

		echo '<p class="submit">';
		echo '<input type="submit" name="Submit" value="' . __('Update Settings','affiliate') . '" class="button-primary" /></p>';

		echo '</form>';

		echo '</div>';

		echo "</div>";


	}

	function handle_affiliate_users_panel() {

		if(isset($_REQUEST['id'])) {
			// There is a user so we'll grab the data
			$user_id = addslashes($_REQUEST['id']);

			if(isset($_POST['action'])) {

				switch(addslashes($_POST['action'])) {

					case 'userdebit':
						check_admin_referer('debit-user-' . $user_id);
						$period = addslashes($_POST['debitperiod']);
						$debit = addslashes($_POST['debitvalue']);
						$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, debits, lastupdated) VALUES (%d, %s, %01.2f, now()) ON DUPLICATE KEY UPDATE debits = debits + %f", $user_id, $period, $debit, $debit );
						$queryresult = $this->db->query($sql);
						if($queryresult) {
							echo '<div id="message" class="updated fade"><p>' . __('Debit has been assigned correctly.', 'affiliate') . '</p></div>';
						}
						break;
					case 'usercredit':
						check_admin_referer('credit-user-' . $user_id);
						$period = addslashes($_POST['creditperiod']);
						$credit = addslashes($_POST['creditvalue']);
						$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, credits, lastupdated) VALUES (%d, %s, %01.2f, now()) ON DUPLICATE KEY UPDATE credits = credits + %f", $user_id, $period, $credit, $credit );
						$queryresult = $this->db->query($sql);
						if($queryresult) {
							echo '<div id="message" class="updated fade"><p>' . __('Credit has been assigned correctly.', 'affiliate') . '</p></div>';
						}
						break;
					case 'userpayment':
						check_admin_referer('pay-user-' . $user_id);
						$period = addslashes($_POST['payperiod']);
						$payment = addslashes($_POST['payvalue']);
						$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, payments, lastupdated) VALUES (%d, %s, %01.2f, now()) ON DUPLICATE KEY UPDATE payments = payments + %f", $user_id, $period, $payment, $payment );
						$queryresult = $this->db->query($sql);
						if($queryresult) {
							echo '<div id="message" class="updated fade"><p>' . __('Payment has been assigned correctly.', 'affiliate') . '</p></div>';
						}
						break;
					case 'findusers':
						check_admin_referer('find-user');
						$userlist = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->db->users} WHERE user_login = %s", addslashes($_POST['username']) ) );
						//print_r($userlist);
						break;
				}

			}

			$headings = aff_get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));

			$headings = array_merge($headings, array( __('Debits','affiliate'), __('Credits','affiliate'), __('Payments','affiliate') ));

			$newcolumns = apply_filters('affiliate_column_names', $headings);
			if(count($newcolumns) == 6) {
				// We must have 6 columns
				$columns = $newcolumns;
			}

			$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC", $user_id ) );

			$user = get_userdata($user_id);

			echo "<strong>" . __('Details for user : ','affiliate') . $user->user_login . " ( " . get_user_meta($user_id, 'affiliate_paypal', true) . " )" . "</strong>";
			// Get the affiliate website listing
			$referrer = get_user_meta($user_id, 'affiliate_referrer', true);
			if(!empty($referrer)) {
				echo " " . __('linked to ', 'affiliate') . "<a href='http://{$referrer}'>" . $referrer . "</a>";
			}
			echo "<br/>";
			echo "<div id='clickscolumn' style='width: 48%; margin-right: 2%; margin-top: 20px; min-height: 400px; float: left;'>";

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
				$place = 10 - $n;

				echo "<tr class='periods' id='period-$place'>";
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


			echo "<div id='referrerscolumn' style='width: 48%; margin-left: 2%; min-height: 400px; margin-top: 20px; background: #fff; float: left;'>";

			echo "<div id='affdashgraph' style='height: 300px; width: 100%; background-color: #fff; margin-left: 0px; margin-right: 10px; margin-bottom: 20px;'>" . "</div>";

			// Add credit and debits table and form

			echo "<form action='' method='post'>";
			wp_nonce_field( 'debit-user-' . $user_id );
			echo '<input type="hidden" name="action" value="userdebit" />';
			echo '<input type="hidden" name="userid" id="debituserid" value="' . $user_id . '" />';
			echo "<table class='widefat'>";

			echo "<thead>";
				echo "<tr>";
				echo "<th scope='col'>";
				echo  __('Debit user account','affiliate');
				echo "</th>";
				echo "<th scope='col' style='width: 3em;'>";
				echo '&nbsp;';
				echo "</th>";
				echo "</tr>";
			echo "</thead>";

			echo "<tbody>";

					echo "<tr class='' style=''>";
					echo "<td style='padding: 5px;'>";
					echo __('Period : ','affiliate');
					echo '<select name="debitperiod" id="debitperiod">';
					$startat = strtotime(date("Y-m-15"));
					for($n=0; $n <=24; $n++) {
						$rdate = strtotime("-$n month", $startat);
						$period = date('Ym', $rdate);
						echo '<option value="' . $period . '"';
						echo '>' . date('M Y', $rdate) . '</option>';
					}
					echo '</select>&nbsp;';
					echo __('Value : ','affiliate');
					echo '<input type="text" name="debitvalue" value="" style="width: 6em;"/>';
					echo "</td>";
					echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
					echo "<input type='submit' name='debitaccount' value='" . __('Add Debit','affiliate') . "' class='button-primary' />";
					echo "</td>";
					echo "</tr>";

			echo "</tbody>";
			echo "</table>";
			echo "</form>";

			echo "<br/>";

			echo "<form action='' method='post'>";
			wp_nonce_field( 'credit-user-' . $user_id );
			echo '<input type="hidden" name="action" value="usercredit" />';
			echo '<input type="hidden" name="userid" id="credituserid" value="' . $user_id . '" />';
			echo "<table class='widefat'>";

			echo "<thead>";
				echo "<tr>";
				echo "<th scope='col'>";
				echo  __('Credit user account','affiliate');
				echo "</th>";
				echo "<th scope='col' style='width: 3em;'>";
				echo '&nbsp;';
				echo "</th>";
				echo "</tr>";
			echo "</thead>";

			echo "<tbody>";

					echo "<tr class='' style=''>";
					echo "<td style='padding: 5px;'>";
					echo __('Period : ','affiliate');
					echo '<select name="creditperiod" id="creditperiod">';
					$startat = strtotime(date("Y-m-15"));
					for($n=0; $n <=24; $n++) {
						$rdate = strtotime("-$n month", $startat);
						$period = date('Ym', $rdate);
						echo '<option value="' . $period . '"';
						echo '>' . date('M Y', $rdate) . '</option>';
					}
					echo '</select>&nbsp;';
					echo __('Value : ','affiliate');
					echo '<input type="text" name="creditvalue" value="" style="width: 6em;"/>';
					echo "</td>";
					echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
					echo "<input type='submit' name='creditaccount' value='" . __('Add Credit','affiliate') . "' class='button-primary' />";
					echo "</td>";
					echo "</tr>";


			echo "</tbody>";
			echo "</table>";
			echo "</form>";

			echo "<br/>";

			echo "<form action='' method='post'>";
			wp_nonce_field( 'pay-user-' . $user_id );
			echo '<input type="hidden" name="action" value="userpayment" />';
			echo '<input type="hidden" name="userid" id="payuserid" value="' . $user_id . '" />';
			echo "<table class='widefat'>";

			echo "<thead>";
				echo "<tr>";
				echo "<th scope='col'>";
				echo  __('Set Payment on account','affiliate');
				echo "</th>";
				echo "<th scope='col' style='width: 3em;'>";
				echo '&nbsp;';
				echo "</th>";
				echo "</tr>";
			echo "</thead>";

			echo "<tbody>";

					echo "<tr class='' style=''>";
					echo "<td style='padding: 5px;'>";
					echo __('Period : ','affiliate');
					echo '<select name="payperiod" id="payperiod">';
					$startat = strtotime(date("Y-m-15"));
					for($n=0; $n <=24; $n++) {
						$rdate = strtotime("-$n month", $startat);
						$period = date('Ym', $rdate);
						echo '<option value="' . $period . '"';
						echo '>' . date('M Y', $rdate) . '</option>';
					}
					echo '</select>&nbsp;';
					echo __('Value : ','affiliate');
					echo '<input type="text" name="payvalue" value="" style="width: 6em;" />';
					echo "</td>";
					echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
					echo "<input type='submit' name='payaccount' value='" . __('Add Payment','affiliate') . "' class='button-primary' />";
					echo "</td>";
					echo "</tr>";

			echo "</tbody>";
			echo "</table>";
			echo "</form>";

			echo "</div>";

			echo "<div style='clear: both;'></div>";
		}
 		else {
			// Get the page
			$page = addslashes($_GET['page']);

			// Have we submitted a query?
			if(isset($_POST['action'])) {

				switch(addslashes($_POST['action'])) {

					case 'findusers':
						check_admin_referer('find-user');
						$userlist = $this->db->get_results( "SELECT * FROM {$this->db->users} WHERE user_login LIKE '%" . mysql_real_escape_string($_POST['username']) . "%'" );
						break;
				}

			}

			// No user sent so display a pick user form
			echo '<form id="form-affiliate-list" action="" method="post">';
			echo '<input type="hidden" name="action" value="findusers" />';
			echo '<input type="hidden" name="page" value="' . $page . '" />';
			wp_nonce_field( 'find-user' );

			echo '<div class="tablenav">';

				echo '<div class="alignleft">';

				echo __('Find user with username','affiliate') . '&nbsp;';
				if(!empty($_POST['username'])) {
					echo '<input type="text" name="username" value="' . addslashes($_POST['username']) . '" />';
				} else {
					echo '<input type="text" name="username" value="" />';
				}
				echo '&nbsp;';
				echo '<input type="submit" value="' . __('Search') . '" name="allaction_search" class="button-secondary" />';

				echo '<br class="clear" />';
				echo '</div>';

			echo '</div>';


			echo '<table cellpadding="3" cellspacing="3" class="widefat" style="width: 100%;">';
			echo '<thead>';
			echo '<tr>';

					echo '<th scope="col" class="check-column"></th>';

					echo '<th scope="col">';
					echo __('Username','affiliate');
					echo '</th>';

			echo '</tr>';
			echo '</thead>';

			echo '<tbody id="the-list">';
			if(!empty($userlist)) {
				foreach($userlist as $result) {

					echo "<tr class=''>";

					// Check boxes
					echo '<th scope="row" class="check-column">';
					echo '</th>';

					echo '<td valign="top">';
					$user = get_userdata($result->ID);
					echo $user->user_login;
					echo " ( " . get_user_meta($result->ID, 'affiliate_paypal', true) . " )";

					// Get the affiliate website listing
					$referrer = get_user_meta($result->ID, 'affiliate_referrer', true);
					if(!empty($referrer)) {
						echo " " . __('linked to ', 'affiliate') . "<a href='http://{$referrer}'>" . $referrer . "</a>";
					}

						// Quick links
					$actions = array();

					$actions[] = "<a href='?page=$page&amp;subpage=users&amp;id=". $result->ID . "' class='edit'>" . __('Manage Affiliate','affiliate') . "</a>";

					echo '<div class="row-actions">';
					echo implode(' | ', $actions);
					echo '</div>';
					echo '</td>';
					echo '</tr>';
				}
			} else {

				echo "<tr class=''>";

				echo '<td colspan="2" valign="top">';
				echo __('There are no users matching the search criteria.','affiliate');
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '<tfoot>';
			echo '<tr>';
				echo '<th scope="col" class="check-column"></th>';
					echo '<th scope="col">';
					echo __('Username','affiliate');
					echo '</th>';
				echo '</tr>';
			echo '</tfoot>';

			echo '</table>';

			echo '</form>';
		}


	}

	function show_affiliates_panel_menu() {

		global $page, $subpage;

		$tab = $page;
		if( empty($tab) ) {
			$tab = 'affiliatesadmin';
		}

		$menus = array();
		$menus['affiliatesadmin'] = __('Affiliate reports', 'affiliate');
		$menus['affiliatesadminmanage'] = __('Manage affiliates', 'affiliate');
		$menus['affiliatesadminsettings'] = __('Affiliate settings', 'affiliate');
		$menus['affiliatesadminaddons'] = __('Manage add-ons', 'affiliate');

		$menus = apply_filters('affiliate_menus', $menus);
		?>

		<h3 class="nav-tab-wrapper">
			<?php
				foreach($menus as $key => $menu) {
					?>
					<a class="nav-tab<?php if($tab == $key) echo ' nav-tab-active'; ?>" href="admin.php?page=<?php echo $key; ?>"><?php echo $menu; ?></a>
					<?php
				}

			?>
		</h3>

		<?php

	}

	function handle_affiliates_panel() {

		global $page;

		wp_reset_vars( array('page') );

		$page = addslashes($_GET['page']);

		echo "<div class='wrap nosubsub'>";
		echo "<h2>" . __('Affiliate System Administration','affiliate') . "</h2>";

		$this->show_affiliates_panel_menu();

		if(!empty($page) && $page != 'affiliatesadmin') {
			switch($page) {
				case 'affiliatesadminsettings':
							$this->handle_affiliate_settings_panel();
							break;
				case 'affiliatesadminmanage':
							$this->handle_affiliate_users_panel();
							break;
				case 'affiliatesadminaddons':
							$this->handle_addons_panel();
							break;
				default:
							break;

			}
		} else {
			if(isset($_GET['action'])) {

				switch(addslashes($_GET['action'])) {

					case 'allaffiliates':	// Bulk operations
											if(isset($_POST['allaction_markaspaid'])) {
												check_admin_referer('allaffiliateactions');

												if(!empty($_POST['allpayments'])) {
													foreach($_POST['allpayments'] as $affiliate) {
														$affdetails = explode('-', $affiliate);
														if(count($affdetails) == 2) {
															if(defined('AFFILIATE_ORIGINAL_PAYMENT_CALCULATION') && AFFILIATE_ORIGINAL_PAYMENT_CALCULATION == true) {
																$affected = $this->db->query( "UPDATE " . $this->affiliatedata . " SET payments = payments + (credits - debits), lastupdated = '" . current_time('mysql', true) . "' WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );
															} else {
																$affected = $this->db->query( "UPDATE " . $this->affiliatedata . " SET payments = (credits - debits), lastupdated = '" . current_time('mysql', true) . "' WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );
															}

														}
													}
													echo '<div id="message" class="updated fade"><p>' . __('Payments has been assigned correctly.', 'affiliate') . '</p></div>';
												}

												// Mark as paid
											}
											break;

					case 'makepayment':		// Mark a payment
											$affiliate = addslashes($_GET['id']);
											if(isset($affiliate)) {
												$affdetails = explode('-', $affiliate);

												if(count($affdetails) == 2) {
													if(defined('AFFILIATE_ORIGINAL_PAYMENT_CALCULATION') && AFFILIATE_ORIGINAL_PAYMENT_CALCULATION == true) {
														$affected = $this->db->query( "UPDATE " . $this->affiliatedata . " SET payments = payments + (credits - debits), lastupdated = '" . current_time('mysql', true) . "' WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );
													} else {
														$affected = $this->db->query( "UPDATE " . $this->affiliatedata . " SET payments = (credits - debits), lastupdated = '" . current_time('mysql', true) . "' WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );
													}
													if($affected) {
														echo '<div id="message" class="updated fade"><p>' . __('Payment has been assigned correctly.', 'affiliate') . '</p></div>';
													}
												}

											}

											break;

				}

				}


			$headings = aff_get_option('affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')));

			$headings = array_merge($headings, array( __('Debits','affiliate'), __('Credits','affiliate'), __('Payments','affiliate') ));

			$newcolumns = apply_filters('affiliate_column_names', $headings);
			if(count($newcolumns) == 6) {
				// We must have 6 columns
				$columns = $newcolumns;
			}

			if(isset($_REQUEST['reportperiod'])) {
				$reportperiod = addslashes($_REQUEST['reportperiod']);
			} else {
				$reportperiod = date('Ym');
			}

			$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE period = %s ORDER BY credits DESC", $reportperiod ) );

			echo '<form id="form-affiliate-list" action="?page=' . $page . '&amp;action=allaffiliates" method="post">';
			echo '<input type="hidden" name="action" value="allaffiliates" />';
			echo '<input type="hidden" name="page" value="' . $page . '" />';

			echo '<div class="tablenav">';

				echo '<div class="alignleft">';

				echo __('Show report for','affiliate') . '&nbsp;';
				echo '<select name="reportperiod" id="reportperiod">';
				$startat = strtotime(date("Y-m-15"));
				for($n=0; $n <=24; $n++) {
					$rdate = strtotime("-$n month", $startat);
					$period = date('Ym', $rdate);
					echo '<option value="' . $period . '"';
					if($reportperiod == $period) echo ' selected="selected"';
					echo '>' . date('M Y', $rdate) . '</option>';
				}
				echo '</select>&nbsp;';
				echo '<input type="submit" value="' . __('Refresh', 'affiliate') . '" name="allaction_refresh" class="button-secondary" />';

				echo '<br class="clear" />';
				echo '</div>';

				echo '<div class="alignright">';

				echo '<input type="submit" value="' . __('Export Payments', 'affiliate') . '" name="allaction_exportpayments" class="button-secondary delete" />&nbsp;&nbsp;';
				echo '<input type="submit" value="' . __('Mark as Paid', 'affiliate') . '" name="allaction_markaspaid" class="button-secondary" />';
				wp_nonce_field( 'allaffiliateactions' );
				echo '<br class="clear" />';
				echo '</div>';

			echo '</div>';


			echo '<table cellpadding="3" cellspacing="3" class="widefat" style="width: 100%;">';
			echo '<thead>';
			echo '<tr>';

					echo '<th scope="col" class="check-column"><input type="checkbox" label="check all" /></th>';

					echo '<th scope="col">';
					echo __('Username','affiliate');
					echo '</th>';

					foreach($columns as $column) {
						echo '<th scope="col" class="num">';
						echo $column;
						echo '</th>';
					}

			echo '</tr>';
			echo '</thead>';

			echo '<tbody id="the-list">';
			if($results) {
				foreach($results as $result) {

					$user = get_userdata($result->user_id);
					if(empty($user)) {
						continue;
					}

					echo "<tr class=''>";

					// Check boxes
					echo '<th scope="row" class="check-column">';
					echo '<input type="checkbox" id="payment-'. $result->user_id . "-" . $result->period .'" name="allpayments[]" value="'. $result->user_id . "-" . $result->period .'" />';
					echo '</th>';

					echo '<td valign="top">';

					echo $user->user_login;
					echo " ( " . get_user_meta($result->user_id, 'affiliate_paypal', true) . " )";

					// Get the affiliate website listing
					$referrer = get_user_meta($result->user_id, 'affiliate_referrer', true);
					if(!empty($referrer)) {
						echo " " . __('linked to ', 'affiliate') . "<a href='http://{$referrer}'>" . $referrer . "</a>";
					}

						// Quick links
					$actions = array();
					$actions[] = "<a href='?page=$page&amp;action=makepayment&amp;id=". $result->user_id . "-" . $result->period ."&amp;reportperiod=" . $reportperiod . "' class='edit'>" . __('Mark as Paid','affiliate') . "</a>";

					$actions[] = "<a href='?page=affiliatesadminmanage&amp;subpage=users&amp;id=". $result->user_id . "' class='edit'>" . __('Manage Affiliate','affiliate') . "</a>";


					echo '<div class="row-actions">';
					echo implode(' | ', $actions);
					echo '</div>';

					echo '</td>';

					echo '<td valign="top" class="num">';
					echo $result->uniques;
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo $result->signups;
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo $result->completes;
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo number_format($result->debits,2);
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo number_format($result->credits,2);
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo number_format($result->payments,2);
					echo '</td>';
					echo '</tr>';
				}
			} else {

				echo "<tr class=''>";

				echo '<td colspan="8" valign="top">';
				echo __('There are no results for the selected month.','affiliate');
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '<tfoot>';
			echo '<tr>';
				echo '<th scope="col" class="check-column"><input type="checkbox" label="check all" /></th>';
				echo '<th scope="col">';
				echo __('Username','affiliate');
				echo '</th>';
				reset($columns);
				foreach($columns as $column) {
					echo '<th scope="col" class="num">';
					echo $column;
					echo '</th>';
				}
			echo '</tr>';
			echo '</tfoot>';

			echo '</table>';

			echo '</form>';

			?>
			</div>
			<?php

		}


	}

	function add_user_affiliate_column( $columns ) {
		$columns['referred'] = __('Referred by', 'affiliate');

		return $columns;
	}

	function show_user_affiliate_column( $content, $column_name, $user_id ) {

		if($column_name == 'referred') {

			$affid = get_user_meta($user_id, 'affiliate_referred_by', true);

			if(!empty($affid)) {
				// was referred so get the referrers details
				$referrer = new WP_User( $affid );

				if(is_network_admin()) {
					$content .= "<a href='" . network_admin_url('users.php?s=') . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
				} else {
					$content .= "<a href='" . admin_url('users.php?s=') . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
				}

			}

		}

		return $content;

	}

	function add_affiliate_column($columns) {

		$columns['referred'] = __('Referred by', 'affiliate');

		return $columns;

	}

	function show_affiliate_column( $column_name, $blog_id ) {

		if($column_name == 'referred') {
			$affid = get_blog_option( $blog_id, 'affiliate_referrer', false );

			if(!empty($affid)) {
				// was referred so get the referrers details
				$referrer = new WP_User( $affid );

				if(is_network_admin()) {
					$content .= "<a href='" . network_admin_url('users.php?s=') . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
				} else {
					$content .= "<a href='" . admin_url('users.php?s=') . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
				}

			}

		}

	}

	// Plugins interface
	function handle_addons_panel_updates() {
		global $action, $page;

		if(isset($_GET['doaction']) || isset($_GET['doaction2'])) {
			if(addslashes($_GET['action']) == 'toggle' || addslashes($_GET['action2']) == 'toggle') {
				$action = 'bulk-toggle';
			}
		}

		$active = aff_get_option('affiliate_activated_addons', array());

		switch(addslashes($action)) {

			case 'deactivate':	$key = addslashes($_GET['addon']);
								if(!empty($key)) {
									check_admin_referer('toggle-addon-' . $key);

									$found = array_search($key, $active);
									if($found !== false) {
										unset($active[$found]);
										aff_update_option('affiliate_activated_addons', array_unique($active));
										return 5;
									} else {
										return 6;
									}
								}
								break;

			case 'activate':	$key = addslashes($_GET['addon']);
								if(!empty($key)) {
									check_admin_referer('toggle-addon-' . $key);

									if(!in_array($key, $active)) {
										$active[] = $key;
										aff_update_option('affiliate_activated_addons', array_unique($active));
										return 3;
									} else {
										return 4;
									}
								}
								break;

			case 'bulk-toggle':
								check_admin_referer('bulk-addon');
								if( is_array($_GET['plugincheck'])) {
									foreach($_GET['plugincheck'] AS $key) {
										$found = array_search($key, $active);
										if($found !== false) {
											unset($active[$found]);
										} else {
											$active[] = $key;
										}
									}
									aff_update_option('affiliate_activated_addons', array_unique($active));
								}
								return 7;
								break;

		}
	}

	function handle_addons_panel() {
		global $action, $page, $subpage;

		wp_reset_vars( array('action', 'page', 'subpage') );

		$messages = array();
		$messages[1] = __('Addon updated.','affiliate');
		$messages[2] = __('Addon not updated.','affiliate');

		$messages[3] = __('Addon activated.','affiliate');
		$messages[4] = __('Addon not activated.','affiliate');

		$messages[5] = __('Addon deactivated.','affiliate');
		$messages[6] = __('Addon not deactivated.','affiliate');

		$messages[7] = __('Addon activation toggled.','affiliate');

		if(!empty($action)) {
			$msg = $this->handle_addons_panel_updates();
		}

		if ( !empty($msg) ) {
			echo '<div id="message" class="updated fade"><p>' . $messages[(int) $msg] . '</p></div>';
			$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		}

		?>

			<form method="get" action="?page=<?php echo esc_attr($page); ?>&amp;subpage=<?php echo esc_attr($subpage); ?>" id="posts-filter">

			<input type='hidden' name='page' value='<?php echo esc_attr($page); ?>' />
			<input type='hidden' name='subpage' value='<?php echo esc_attr($subpage); ?>' />

			<div class="tablenav">

			<div class="alignleft actions">
			<select name="action">
			<option selected="selected" value=""><?php _e('Bulk Actions', 'affiliate'); ?></option>
			<option value="toggle"><?php _e('Toggle activation', 'affiliate'); ?></option>
			</select>
			<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="<?php _e('Apply', 'affiliate'); ?>">

			</div>

			<div class="alignright actions"></div>

			<br class="clear">
			</div>

			<div class="clear"></div>

			<?php
				wp_original_referer_field(true, 'previous'); wp_nonce_field('bulk-addon');

				$columns = array(	"name"		=>	__('Addon Name', 'affiliate'),
									"active"	=>	__('Active','affiliate')
								);

				$columns = apply_filters('affiliate_plugincolumns', $columns);

				$plugins = get_affiliate_addons();

				$active = aff_get_option('affiliate_activated_addons', array());

			?>

			<table cellspacing="0" class="widefat fixed">
				<thead>
				<tr>
				<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
				<?php
					foreach($columns as $key => $col) {
						?>
						<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
						<?php
					}
				?>
				</tr>
				</thead>

				<tfoot>
				<tr>
				<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
				<?php
					reset($columns);
					foreach($columns as $key => $col) {
						?>
						<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
						<?php
					}
				?>
				</tr>
				</tfoot>

				<tbody>
					<?php
					if(!empty($plugins)) {
						foreach($plugins as $key => $plugin) {
							$default_headers = array(
								                'Name' => 'Plugin Name',
												'Author' => 'Author',
												'Description'	=>	'Description',
												'AuthorURI' => 'Author URI'
								        );

							$plugin_data = get_file_data( affiliate_dir('affiliateincludes/addons/' . $plugin), $default_headers, 'plugin' );

							if(empty($plugin_data['Name'])) {
								continue;
							}

							?>
							<tr valign="middle" class="alternate" id="plugin-<?php echo $plugin; ?>">
								<th class="check-column" scope="row"><input type="checkbox" value="<?php echo esc_attr($plugin); ?>" name="plugincheck[]"></th>
								<td class="column-name">
									<strong><?php echo esc_html($plugin_data['Name']) . "</strong>"; ?>
									<?php if(!empty($plugin_data['Description'])) {
										?><br/><?php echo esc_html($plugin_data['Description']);
										}

										$actions = array();

										if(in_array($plugin, $active)) {
											$actions['toggle'] = "<span class='edit activate'><a href='" . wp_nonce_url("?page=" . $page. "&amp;subpage=" . $subpage . "&amp;action=deactivate&amp;addon=" . $plugin . "", 'toggle-addon-' . $plugin) . "'>" . __('Deactivate', 'affiliate') . "</a></span>";
										} else {
											$actions['toggle'] = "<span class='edit deactivate'><a href='" . wp_nonce_url("?page=" . $page. "&amp;subpage=" . $subpage . "&amp;action=activate&amp;addon=" . $plugin . "", 'toggle-addon-' . $plugin) . "'>" . ((function_exists('is_network_admin') && is_network_admin()) ? __('Network Activate', 'affiliate') : __('Activate', 'affiliate')) . "</a></span>";
										}
									?>
									<br><div class="row-actions"><?php echo implode(" | ", $actions); ?></div>
									</td>

								<td class="column-active">
									<?php
										if(in_array($plugin, $active)) {
											echo "<strong>" . __('Active', 'affiliate') . "</strong>";
										} else {
											echo __('Inactive', 'affiliate');
										}
									?>
								</td>
						    </tr>
							<?php
						}
					} else {
						$columncount = count($columns) + 1;
						?>
						<tr valign="middle" class="alternate" >
							<td colspan="<?php echo $columncount; ?>" scope="row"><?php _e('No Addons where found for this install.','affiliate'); ?></td>
					    </tr>
						<?php
					}
					?>

				</tbody>
			</table>


			<div class="tablenav">

			<div class="alignleft actions">
			<select name="action2">
				<option selected="selected" value=""><?php _e('Bulk Actions', 'affiliate'); ?></option>
				<option value="toggle"><?php _e('Toggle activation', 'affiliate'); ?></option>
			</select>
			<input type="submit" class="button-secondary action" id="doaction2" name="doaction2" value="Apply">
			</div>
			<div class="alignright actions"></div>
			<br class="clear">
			</div>

			</form>

		<?php
	}

	function get_referred_by( $id ) {

		$sql = $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_referred_by' AND meta_value = %s", $id );

		$results = $this->db->get_col( $sql );

		return $results;
	}

	function override_referrer_search( &$search ) {

		$s = (!empty($_REQUEST['s'])) ? $_REQUEST['s'] : '';

		if(substr($s, 0, 9) == 'referrer:') {
			// we have a referrer search so modify it
			$searchstring = explode( 'referrer:', $s );

			if(!empty($searchstring[1])) {
				$user = get_user_by( 'login',$searchstring[1] );
				if($user) {
					$referred = $this->get_referred_by( $user->ID );

					$search->query_where = "WHERE 1=1 AND ( ID IN (0," . implode(',', $referred) . ") )";

					if(!empty($search->query_vars['blog_id']) && is_multisite()) {
						$search->query_where .= " AND (wp_usermeta.meta_key = '" . $this->db->get_blog_prefix( $search->query_vars['blog_id'] ) . "capabilities' )";
					}

				}
			}
		}

	}

	function add_referrer_search_link( $actions, $user_object ) {

		if(is_network_admin()) {
			$actions['referred'] = '<a href="' . network_admin_url('users.php?s=referrer:' . $user_object->user_login ) . '">' . __( 'Referred', 'affiliate' ) . '</a>';
		} else {
			$actions['referred'] = '<a href="' . admin_url('users.php?s=referrer:' . $user_object->user_login ) . '">' . __( 'Referred', 'affiliate' ) . '</a>';
		}

		return $actions;
	}

}

?>