<?php

// Administration side of the affiliate system
class affiliateadmin {

	var $build = 3;

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

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			// we're activated site wide
			$this->affiliatedata = $this->db->base_prefix . 'affiliatedata';
			$this->affiliatereferrers = $this->db->base_prefix . 'affiliatereferrers';
		} else {
			// we're only activated on a blog level so put the admin menu in the main area
			$this->affiliatedata = $this->db->prefix . 'affiliatedata';
			$this->affiliatereferrers = $this->db->prefix . 'affiliatereferrers';
		}

		$installed = get_option('Aff_Installed', false);

		if($installed === false || $installed != $this->build) {
			$this->install();

			update_option('Aff_Installed', $this->build);
		}

		register_activation_hook(__FILE__, array(&$this, 'install'));

		add_action( 'init', array( &$this, 'initialise_ajax' ), 1 );

		add_action( 'init', array(&$this, 'aff_report_header'), 999 );

		add_action( 'init', array(&$this, 'handle_export_link' ) );

		// Menus and profile page
		add_action( 'admin_menu', array(&$this, 'add_menu_items') );
		add_action( 'network_admin_menu', array(&$this, 'add_menu_items') );

		add_action( 'show_user_profile', array(&$this, 'add_profile_box' ) );
		add_action( 'personal_options_update', array(&$this, 'update_profile_box' ) );

		// Affiliate blog reporting
		add_filter( 'wpmu_blogs_columns', array(&$this, 'add_affiliate_column' ) );
		add_action( 'manage_blogs_custom_column', array(&$this, 'show_affiliate_column' ), 10, 2 );

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

	}

	function initialise_ajax() {
		add_action( 'wp_ajax__aff_getstats', array(&$this,'ajax__aff_getstats') );
		add_action( 'wp_ajax__aff_getvisits', array(&$this,'ajax__aff_getvisits') );
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


	function add_menu_items() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		if(is_multisite()) {
			$getoption = 'get_site_option';
		} else {
			$getoption = 'get_option';
		}

		// Add administration menu
		if(is_multisite()) {
			if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
				// we're activated site wide so put the admin menu in the network area
				if(function_exists('is_network_admin')) {
					if(is_network_admin()) {
						add_submenu_page('index.php', __('Affiliates'), __('Affiliates'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'));
					}
				} else {
					add_submenu_page('index.php', __('Affiliates'), __('Affiliates'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'));
				}
			} else {
				// we're only activated on a blog level so put the admin menu in the main area
				if(!function_exists('is_network_admin')) {
					add_submenu_page('index.php', __('Affiliates'), __('Affiliates'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'));
				} elseif(!is_network_admin()) {
					add_submenu_page('index.php', __('Affiliates'), __('Affiliates'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'));
				}
			}
		} else {
			add_submenu_page('index.php', __('Affiliates'), __('Affiliates'), 'manage_options', 'affiliatesadmin', array(&$this,'handle_affiliates_panel'));
		}

		add_submenu_page('users.php', __('Affiliate Earnings Report','affiliate'), __('Affiliate Referrals','affiliate'), 'read', "affiliateearnings", array(&$this,'add_profile_report_page'));

		// Add profile menu
		if(get_usermeta($user_ID, 'enable_affiliate') == 'yes') {
			if($getoption('affiliateenablebanners', 'no') == 'yes') {
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
		echo '<!--[if IE]><script language="javascript" type="text/javascript" src="' . affiliate_url('affiliateincludes/js/excanvas.min.js') . '"></script><![endif]-->';
	}

	function add_profile_report_page() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

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

		$reference = get_usermeta($user_ID, 'affiliate_reference');

		if(is_multisite()) {
			$getoption = 'get_site_option';
			$site = $getoption('site_name');
			$url = get_blog_option(1,'home');
		} else {
			$getoption = 'get_option';
			$site = $getoption('blogname');
			$url = $getoption('home');
		}

		if(isset($_POST['action']) && addslashes($_POST['action']) == 'update') {

			check_admin_referer('affiliatesettings');

			update_usermeta($user_ID, 'enable_affiliate', $_POST['enable_affiliate']);
			update_usermeta($user_ID, 'affiliate_paypal', $_POST['affiliate_paypal']);
			if(isset($_POST['affiliate_referrer'])) {
				update_usermeta($user_ID, 'affiliate_referrer', str_replace('http://', '', untrailingslashit($_POST['affiliate_referrer'])));
			} else {
				delete_usermeta($user_ID, 'affiliate_referrer');
			}
			if(isset($_POST['enable_affiliate']) && addslashes($_POST['enable_affiliate']) == 'yes') {
				// Set up the affiliation details
				// Store a record of the reference
				$reference = $user->user_login . '-' . strrev(sprintf('%02d', $user_ID + 35));
				update_usermeta($user_ID, 'affiliate_reference', $reference);
				update_usermeta($user_ID, 'affiliate_hash', 'aff' . md5(AUTH_SALT . $reference));
			} else {
				// Wipe the affiliation details
				delete_usermeta($user_ID, 'affiliate_reference');
				delete_usermeta($user_ID, 'affiliate_hash');
			}

		}

		echo "<div class='wrap'>";
		echo '<div class="icon32" id="icon-themes"><br/></div>';
		echo "<h2>" . __('Affiliate Referral Report','affiliate') . "</h2>";


			echo "<div style='width: 98%; margin-top: 20px; background-color: #FFFEEB; margin-left: auto; margin-right: auto; margin-bottom: 20px; border: 1px #e6db55 solid; padding: 10px;'>";
			if(get_usermeta($user_ID, 'enable_affiliate') == 'yes') {
				echo "<strong>" . __('Hello, Thank you for supporting us</strong>, to view or change any of your affiliate settings click on the edit link.','affiliate') . "</strong><a href='#view' id='editaffsettingslink' style='float:right; font-size: 8pt;'>" . __('edit','affiliate') . "</a>";

				echo "<div id='innerbox' style='width: 100%; display: none;'>";

				echo "<form action='' method='post'>";
				wp_nonce_field( "affiliatesettings" );

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
								<option value='yes' <?php if(get_usermeta($user_ID, 'enable_affiliate') == 'yes') echo "selected = 'selected'"; ?>><?php _e('Yes please', 'affiliate'); ?></option>
								<option value='no' <?php if(get_usermeta($user_ID, 'enable_affiliate') != 'yes') echo "selected = 'selected'"; ?>><?php _e('No thanks', 'affiliate'); ?></option>
							</select>
						</td>
					</tr>

					<tr style='background: transparent;'>
						<th><label for="affiliate_paypal"><?php _e('PayPal Email Address', 'affiliate'); ?></label></th>
						<td>
						<input type="text" name="affiliate_paypal" id="affiliate_paypal" value="<?php echo get_usermeta($user_ID, 'affiliate_paypal'); ?>" class="regular-text" />
						</td>
					</tr>

				</table>

				<?php

				if(get_usermeta($user_ID, 'enable_affiliate') == 'yes') {

					$reference = get_usermeta($user_ID, 'affiliate_reference');
					$referrer = get_usermeta($user_ID, 'affiliate_referrer');
					$refurl = "profile.php?page=affiliateearnings";

					if(defined('AFFILIATE_CHECKALL')) { ?>

						<h3><?php _e('Affiliate Advanced Settings', 'affiliate') ?></h3>

						<?php
						$advsettingstextdefault = "<p>There are times when you would rather hide your affiliate link, or simply not have to bother remembering the affiliate reference to put on the end of our URL.</p>
					<p>If this is the case, then you can enter the main URL of the site you will be sending requests from below, and we will sort out the tricky bits for you.</p>";

						echo stripslashes( $getoption('affiliateadvancedsettingstext', $advsettingstextdefault) );

						?>

						<table class="form-table">
							<tr style='background: transparent;'>
								<th><label for="affiliate_referrer"><?php _e('Your URL', 'affiliate'); ?></label></th>
								<td>
									http://&nbsp;<input type="text" name="affiliate_referrer" id="affiliate_referrer" value="<?php echo get_usermeta($user_ID, 'affiliate_referrer'); ?>" class="regular-text" />
								</td>
							</tr>

						</table>

					<?php
					}
					?>
					<p><?php _e('<h3>Affiliate Details</h3>', 'affiliate') ?></p>
					<p><?php _e(sprintf('In order for us to track your referrals, you should use the following URL to link to our site:'), 'affiliate') ?></p>
					<p><?php _e(sprintf('<strong>http://%s?ref=%s</strong>', $url, $reference ), 'affiliate') ?></p>

					<?php
						if(defined('AFFILIATE_CHECKALL') && !empty($referrer)) {
							// We are always going to check for a referer site
							?>
							<p><?php _e(sprintf('Alternatively you can just link directly to the URL below from the site you entered in the advanced settings above:'), 'affiliate') ?></p>
							<p><?php _e(sprintf('<strong>http://%s</strong>', $url ), 'affiliate') ?></p>
							<?php

						}


					if($getoption('affiliateenablebanners', 'no') == 'yes') {
					?>
					<p><?php _e(sprintf('If you would rather use a banner or button then we have a wide selection of sizes <a href="%s">here</a>.', "profile.php?page=affiliatebanners" ), 'affiliate') ?></p>
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

				echo "<div id='innerbox' style='width: 100%'>";

				echo "<form action='' method='post'>";
				wp_nonce_field( "affiliatesettings" );

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
								<option value='yes' <?php if(get_usermeta($user_ID, 'enable_affiliate') == 'yes') echo "selected = 'selected'"; ?>><?php _e('Yes please', 'affiliate'); ?></option>
								<option value='no' <?php if(get_usermeta($user_ID, 'enable_affiliate') != 'yes') echo "selected = 'selected'"; ?>><?php _e('No thanks', 'affiliate'); ?></option>
							</select>
						</td>
					</tr>

					<tr style='background: transparent;'>
						<th><label for="affiliate_paypal"><?php _e('PayPal Email Address', 'affiliate'); ?></label></th>
						<td>
						<input type="text" name="affiliate_paypal" id="affiliate_paypal" value="<?php echo get_usermeta($user_ID, 'affiliate_paypal'); ?>" class="regular-text" />
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


			echo "<div id='referrerscolumn' style='width: 48%; margin-left: 2%; min-height: 400px; margin-top: 20px; background: #fff; float: left;'>";

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

			echo "<div style='clear: both;'></div>";

		?>

		</div>
		<?php

	}

	function add_profile_banner_page() {

		$user = wp_get_current_user();
		$user_ID = $user->ID;

		$reference = get_usermeta($user_ID, 'affiliate_reference');

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$getoption = 'get_site_option';
			$site = $getoption('site_name');
			$url = get_blog_option(1,'home');
		} else {
			$getoption = 'get_option';
			$site = $getoption('blogname');
			$url = $getoption('home');
		}

		?>
		<div class='wrap'>
		<h2>Affiliate Banners</h2>

		<p><?php _e("So, you want something more exciting than a straight forward text link?",'affiliate'); ?></p>
		<p><?php _e("Not to worry, we've got banners and buttons galore. To use them simply copy and paste the HTML underneath the graphic that you want to use.",'affiliate'); ?></p>

		<?php

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

		?>

		</div>
		<?php
	}

	function handle_export_link() {

		$page = addslashes($_GET['page']);

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

														$paypal = get_usermeta($affdetails[0], 'affiliate_paypal');
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

		if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
			$getoption = 'get_site_option';
			$updateoption = 'update_site_option';
		} else {
			$getoption = 'get_option';
			$updateoption = 'update_option';
		}

		if(addslashes($_GET['action']) == 'updateaffiliateoptions') {
			check_admin_referer('affiliateoptions');

			$headings = array();
			$headings[] = $_POST['uniqueclicks'];
			$headings[] = $_POST['signups'];
			$headings[] = $_POST['paidmembers'];

			$updateoption('affiliateheadings', $headings);

			$updateoption('affiliatesettingstext', $_POST['affiliatesettingstext']);
			$updateoption('affiliateadvancedsettingstext', $_POST['affiliateadvancedsettingstext']);

			$updateoption('affiliateenablebanners', $_POST['affiliateenablebanners']);

			$banners = split( "\n", stripslashes($_POST['affiliatebannerlinks']));

			foreach($banners as $key => $b) {
				$banners[$key] = trim($b);
			}
			$updateoption('affiliatebannerlinks', $banners);

			do_action('affililate_settings_form_update');

			echo '<div id="message" class="updated fade"><p>' . __('Affiliate settings saved.','blogsmu') . '</p></div>';
		}

		$page = addslashes($_GET['page']);
		$subpage = addslashes($_GET['subpage']);

		echo '<form method="post" action="?page=' . $page . '&amp;subpage=' . $subpage . '&amp;action=updateaffiliateoptions">';
		wp_nonce_field( "affiliateoptions" );

		echo '<h3>' . __('Column Settings', 'affiliate') . '</h3>';

		$headings = $getoption( 'affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')) );

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

		echo '<h3>' . __('Profile page text', 'affiliate') . '</h3>';

		$settingstextdefault = "<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p>
<p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p>
<p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>";

		echo '<table class="form-table">';
		echo '<tr valign="top">';
		echo '<th scope="row">' . __('Affiliate settings profile text') . '</th>';
		echo '<td>';
		echo '<textarea name="affiliatesettingstext" id="affiliatesettingstext" cols="60" rows="10">' . stripslashes( $getoption('affiliatesettingstext', $settingstextdefault) ) . '</textarea>';
		echo '</td>';
		echo '</tr>';

		$advsettingstextdefault = "<p>There are times when you would rather hide your affiliate link, or simply not have to bother remembering the affiliate reference to put on the end of our URL.</p>
<p>If this is the case, then you can enter the main URL of the site you will be sending requests from below, and we will sort out the tricky bits for you.</p>";

		echo '<table class="form-table">';
		echo '<tr valign="top">';
		echo '<th scope="row">' . __('Affiliate advanced settings profile text', 'affiliate') . '</th>';
		echo '<td>';
		echo '<textarea name="affiliateadvancedsettingstext" id="affiliateadvancedsettingstext" cols="60" rows="10">' . stripslashes( $getoption('affiliateadvancedsettingstext', $advsettingstextdefault) ) . '</textarea>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		echo '<h3>' . __('Banner Settings', 'affiliate') . '</h3>';

			echo '<table class="form-table">';
			echo '<tr>';
			echo '<th valign="top" scope="row">' . __('Enable Banners','affiliate') . '</th>';
			echo '<td valign="top">';

			echo "<select name='affiliateenablebanners'>";
			echo "<option value='yes'";
			if($getoption('affiliateenablebanners', 'no') == 'yes') echo "selected = 'selected'";
			echo '>' . __('Yes please', 'affiliate') . "</option>";

			echo "<option value='no'";
			if($getoption('affiliateenablebanners', 'no') == 'no') echo "selected = 'selected'";
			echo '>' . __('No thanks', 'affiliate') . "</option>";

			echo "</select>";

			echo '</td>';
			echo '</tr>';

			$banners = $getoption('affiliatebannerlinks');
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


		do_action('affililate_settings_form');

		echo '<p class="submit">';
		echo '<input type="submit" name="Submit" value="' . __('Update Settings','affiliate') . '" /></p>';

		echo '</form>';

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
						print_r($userlist);
						break;
				}

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

			$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC", $user_id ) );

			$user = get_userdata($user_id);

			echo "<strong>" . __('Details for user : ','affiliate') . $user->user_login . " ( " . get_usermeta($user_id, 'affiliate_paypal') . " )" . "</strong>";
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


			echo "<div id='referrerscolumn' style='width: 48%; margin-left: 2%; min-height: 400px; margin-top: 20px; background: #fff; float: left;'>";

			echo "<div id='affdashgraph' style='height: 300px; background-color: #fff; margin-left: 0px; margin-right: 10px; margin-bottom: 20px;'>" . "</div>";

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

					echo "<tr class='$class' style='$style'>";
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
					echo "<input type='submit' name='debitaccount' value='" . __('Debit','affiliate') . "' class='button' />";
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

					echo "<tr class='$class' style='$style'>";
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
					echo "<input type='submit' name='creditaccount' value='" . __('Credit','affiliate') . "' class='button' />";
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

					echo "<tr class='$class' style='$style'>";
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
					echo "<input type='submit' name='payaccount' value='" . __('Payment','affiliate') . "' class='button' />";
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
				echo '<input type="text" name="username" value="' . addslashes($_POST['username']) . '" />';
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
			if($userlist) {
				foreach($userlist as $result) {

					echo "<tr $bgcolour class='$class'>";

					// Check boxes
					echo '<th scope="row" class="check-column">';
					echo '</th>';

					echo '<td valign="top">';
					$user = get_userdata($result->ID);
					echo $user->user_login;
					echo " ( " . get_usermeta($result->ID, 'affiliate_paypal') . " )";

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

				echo "<tr $bgcolour class='$class'>";

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

	function handle_affiliates_panel() {

		$page = addslashes($_GET['page']);
		$subpage = addslashes($_GET['subpage']);

		echo "<div class='wrap'>";
		echo "<h2>" . __('Affiliate System Administration','affiliate') . "</h2>";

		if(!empty($subpage)) {
			switch($subpage) {
				case 'settings':
							echo '<ul class="subsubsub">';
							echo '<li><a href="?page=' . $page . '" class="rbutton">' . __('Affiliate reports', 'affiliate') . '</a> | </li>';
							echo '<li><a href="?page=' . $page . '&amp;subpage=users" class="rbutton">' . __('Manage affiliates', 'affiliate') . '</a> | </li>';
							echo '<li><a href="?page=' . $page . '&amp;subpage=settings" class="rbutton"><strong>' . __('Affiliate settings', 'affiliate') . '</strong></a></li>';
							echo '</ul>';
							echo '<br clear="all" />';
							$this->handle_affiliate_settings_panel();
							break;
				case 'users':
							echo '<ul class="subsubsub">';
							echo '<li><a href="?page=' . $page . '" class="rbutton">' . __('Affiliate reports', 'affiliate') . '</a> | </li>';
							echo '<li><a href="?page=' . $page . '&amp;subpage=users" class="rbutton"><strong>' . __('Manage affiliates', 'affiliate') . '</strong></a> | </li>';
							echo '<li><a href="?page=' . $page . '&amp;subpage=settings" class="rbutton">' . __('Affiliate settings', 'affiliate') . '</a></li>';
							echo '</ul>';
							echo '<br clear="all" />';
							$this->handle_affiliate_users_panel();
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
															$affected = $this->db->query( "UPDATE " . $this->affiliatedata . " SET payments = payments + (credits - debits), lastupdated = '" . current_time('mysql', true) . "' WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );
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
													$affected = $this->db->query( "UPDATE " . $this->affiliatedata . " SET payments = payments + (credits - debits), lastupdated = '" . current_time('mysql', true) . "' WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );
													if($affected) {
														echo '<div id="message" class="updated fade"><p>' . __('Payment has been assigned correctly.', 'affiliate') . '</p></div>';
													}
												}

											}

											break;

				}

				}

			echo '<ul class="subsubsub">';
			echo '<li><a href="?page=' . $page . '" class="rbutton"><strong>' . __('Affiliate reports', 'affiliate') . '</strong></a> | </li>';
			echo '<li><a href="?page=' . $page . '&amp;subpage=users" class="rbutton">' . __('Manage affiliates', 'affiliate') . '</a> | </li>';
			echo '<li><a href="?page=' . $page . '&amp;subpage=settings" class="rbutton">' . __('Affiliate settings', 'affiliate') . '</a></li>';
			echo '</ul>';
			echo '<br clear="all" />';

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
				echo '<input type="submit" value="' . __('Refresh') . '" name="allaction_refresh" class="button-secondary" />';

				echo '<br class="clear" />';
				echo '</div>';

				echo '<div class="alignright">';

				echo '<input type="submit" value="' . __('Export Payments') . '" name="allaction_exportpayments" class="button-secondary delete" />';
				echo '<input type="submit" value="' . __('Mark as Paid') . '" name="allaction_markaspaid" class="button-secondary" />';
				wp_nonce_field( 'allaffiliateactions' );
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

					echo "<tr $bgcolour class='$class'>";

					// Check boxes
					echo '<th scope="row" class="check-column">';
					echo '<input type="checkbox" id="payment-'. $result->user_id . "-" . $result->period .'" name="allpayments[]" value="'. $result->user_id . "-" . $result->period .'" />';
					echo '</th>';

					echo '<td valign="top">';
					$user = get_userdata($result->user_id);
					echo $user->user_login;
					echo " ( " . get_usermeta($result->user_id, 'affiliate_paypal') . " )";

						// Quick links
					$actions = array();
					$actions[] = "<a href='?page=$page&amp;action=makepayment&amp;id=". $result->user_id . "-" . $result->period ."&amp;reportperiod=" . $reportperiod . "' class='edit'>" . __('Mark as Paid','affiliate') . "</a>";

					$actions[] = "<a href='?page=$page&amp;subpage=users&amp;id=". $result->user_id . "' class='edit'>" . __('Manage Affiliate','affiliate') . "</a>";


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

				echo "<tr $bgcolour class='$class'>";

				echo '<td colspan="6" valign="top">';
				echo __('There are no results for the selected month.','affiliate');
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

	function add_affiliate_column($columns) {

		$columns['affiliate'] = __('Affiliate', 'affiliate');

		return $columns;

	}

	function show_affiliate_column( $column_name, $blog_id ) {

		if($column_name == 'affiliate') {
			$aff = get_blog_option( $blog_id, 'affiliate_referrer', 'none' );
			if($aff != 'none') {
				// This is an affiliate
				echo "<img src='" .  affiliate_url("affiliateincludes/images/affiliatelink.png") . "' alt='referred'>&nbsp;";
			}
			$paid = get_blog_option( $blog_id, 'affiliate_paid', 'no' );
			if($paid != 'no') {
				// This is an affiliate
				echo "<img src='" . affiliate_url("affiliateincludes/images/affiliatemoney.png") . "' alt='paid'>";
			}
		}

	}

}

$affadmin =& new affiliateadmin();

?>