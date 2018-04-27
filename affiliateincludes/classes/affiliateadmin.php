<?php

// Administration side of the affiliate system
class affiliateadmin {

	var $build_version = 9;
	var $installed_version = 0;

	var $db;

	var $mylocation = "";
	var $plugindir = "";
	var $base_uri = '';

	// The page on the public side of the site that has details of the affiliate plan
	var $affiliateinformationpage = 'affiliates';

	var $tables = array( 'affiliatedata', 'affiliatereferrers', 'affiliaterecords' );

	var $affiliatedata;
	var $affiliatereferrers;
	var $affiliaterecords;

	function __construct() {

		// Add support for new WPMUDEV Dashboard Notices
		global $wpmudev_notices;
		$wpmudev_notices[] = array(
			'id'      => 106,
			'name'    => 'Affiliate',
			'screens' => array(
				'toplevel_page_affiliatesadmin',
				'affiliates_page_affiliatesadminmanage',
				'affiliates_page_affiliatesadminsettings',
				'affiliates_page_affiliatesadminaddons',
				'users_page_affiliateearnings',
				'users_page_affiliatebanners',
				'toplevel_page_affiliatesadmin-network',
				'affiliates_page_affiliatesadminmanage-network',
				'affiliates_page_affiliatesadminsettings-network',
				'affiliates_page_affiliatesadminaddons-network',
				'users_page_affiliateearnings-network',
				'users_page_affiliatebanners-network'
			)
		);
		include_once( dirname( __FILE__ ) . '../../external/wpmudev-dash-notification.php' );

		global $wpdb;

		// Grab our own local reference to the database class
		$this->db =& $wpdb;

		foreach ( $this->tables as $table ) {
			if ( ( affiliate_is_plugin_active_for_network() )
			     && ( defined( 'AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED' ) && AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED == 'yes' )
			) {
				// we're activated site wide
				$this->$table = $this->db->base_prefix . $table;
			} else {
				if ( defined( 'AFFILIATE_USE_BASE_PREFIX_IF_EXISTS' ) && AFFILIATE_USE_BASE_PREFIX_IF_EXISTS == 'yes' && ! empty( $this->db->base_prefix ) ) {
					$this->$table = $this->db->base_prefix . $table;
				} else {
					// we're only activated on a blog level so put the admin menu in the main area
					$this->$table = $this->db->prefix . $table;
				}
			}
		}

		$this->installed_version = aff_get_option( 'Aff_Installed', false );

		if ( $this->installed_version === false || $this->installed_version != $this->build_version ) {
			$this->install();
			aff_update_option( 'Aff_Installed', $this->build_version );
		}

		register_activation_hook( __FILE__, array( &$this, 'install' ) );

		add_action( 'init', array( &$this, 'initialise_ajax' ), 1 );

		add_action( 'init', array( &$this, 'aff_report_header' ), 999 );

		add_action( 'init', array( &$this, 'handle_export_link' ) );

		add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );

		// Menus and profile page
		add_action( 'admin_menu', array( &$this, 'add_menu_items' ) );
		add_action( 'network_admin_menu', array( &$this, 'add_menu_items' ) );

		add_action( 'show_user_profile', array( &$this, 'add_profile_box' ) );
		add_action( 'personal_options_update', array( &$this, 'update_profile_box' ) );

		// Affiliate blog and user reporting
		add_filter( 'wpmu_blogs_columns', array( &$this, 'add_affiliate_column' ) );
		add_action( 'manage_blogs_custom_column', array( &$this, 'show_affiliate_column' ), 10, 2 );
		add_action( 'manage_sites_custom_column', array( &$this, 'show_affiliate_column' ), 10, 2 );

		add_filter( 'manage_users_columns', array( &$this, 'add_user_affiliate_column' ) );
		add_filter( 'wpmu_users_columns', array( &$this, 'add_user_affiliate_column' ) );
		add_filter( 'manage_users_custom_column', array( &$this, 'show_user_affiliate_column' ), 10, 3 );

		add_action( 'pre_user_query', array( &$this, 'override_referrer_search' ) );
		add_filter( 'user_row_actions', array( &$this, 'add_referrer_search_link' ), 10, 2 );
		add_filter( 'ms_user_row_actions', array( &$this, 'add_referrer_search_link' ), 10, 2 );

		//add_action( 'show_user_profile', array(&$this, 'edit_user_profile') );
		//add_action( 'edit_user_profile', array(&$this, 'edit_user_profile') );

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

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		}

		// Get the correct character collate
		if ( ! empty( $this->db->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET " . $this->db->charset;
		}

		if ( ! empty( $this->db->collate ) ) {
			$charset_collate .= " COLLATE " . $this->db->collate;
		}

		$sql_affiliatedata_current = "CREATE TABLE `" . $this->affiliatedata . "` (
				`user_id` bigint(20) default NULL,
				`period` varchar(6) default NULL,
				`uniques` bigint(20) default '0',
				`signups` bigint(20) default '0',
				`completes` bigint(20) default '0',
				`debits` decimal(10,4) default '0.0000',
				`credits` decimal(10,4) default '0.0000',
				`payments` decimal(10,4) default '0.0000',
				`lastupdated` datetime default '0000-00-00 00:00:00',
				UNIQUE KEY user_period (user_id,period),
				KEY user_id (user_id),
				KEY period (period)
			) " . $charset_collate . ";";


		$sql_affiliatereferrers_current = "CREATE TABLE `" . $this->affiliatereferrers . "` (
				`user_id` bigint(20) default NULL,
				`period` varchar(6) default NULL,
				`url` varchar(255) default NULL,
				`referred` bigint(20) default '0',
				UNIQUE KEY user_period_url (user_id,period),
				KEY user_id (user_id),
				KEY period (period)
			) " . $charset_collate . ";";

		$sql_affiliaterecords_current = "CREATE TABLE `" . $this->affiliaterecords . "` (
		  		`id` BIGINT NOT NULL AUTO_INCREMENT,
				`user_id` bigint(20) unsigned NOT NULL,
				`period` varchar(6) DEFAULT NULL,
				`affiliatearea` varchar(255) DEFAULT NULL,
				`area_id` bigint(20) DEFAULT NULL,
				`affiliatenote` text,
				`amount` decimal(10,4) DEFAULT '0.0000',
				`meta` varchar(1000) DEFAULT NULL,
				`timestamp` datetime default '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY `user_id` (`user_id`),
				KEY `period` (period)
			) " . $charset_collate . ";";

		//echo "sql_affiliatedata_current[". $sql_affiliatedata_current ."]<br />";
		dbDelta( $sql_affiliatedata_current );

		//echo "sql_affiliatedata_current[". $sql_affiliatereferrers_current ."]<br />";
		dbDelta( $sql_affiliatereferrers_current );

		if ( $this->db->get_var( "SHOW TABLES LIKE '" . $this->affiliaterecords . "'" ) != $this->affiliaterecords ) {
			//echo "IF sql_affiliaterecords_current[". $sql_affiliaterecords_current ."]<br />";
			dbDelta( $sql_affiliaterecords_current );
		} else {
			//echo "ELSE sql_affiliaterecords_current[". $sql_affiliaterecords_current ."]<br />";
			dbDelta( $sql_affiliaterecords_current );

			if ( $this->installed_version < 8 ) {
				$sql_str = "ALTER TABLE `" . $this->affiliaterecords . "` ADD `id` BIGINT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (  `id` ) ;";
				//echo "sql_str[". $sql_str ."<br />";
				$this->db->query( $sql_str );
			}
		}
		//die();

		if ( ( affiliate_is_plugin_active_for_network() )
		     && ( defined( 'AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED' ) && AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED == 'yes' )
		) {

			// We need to check for a transfer across from old options to new ones
			$option = aff_get_option( 'affiliateheadings', false );
			if ( $option == false ) {
				$option = get_blog_option( 1, 'affiliateheadings' );
				aff_update_option( 'affiliateheadings', $option );
			}

			$option = aff_get_option( 'affiliatesettingstext', false );
			if ( $option == false ) {
				$option = get_blog_option( 1, 'affiliatesettingstext' );
				aff_update_option( 'affiliatesettingstext', $option );
			}

			$option = aff_get_option( 'affiliateadvancedsettingstext', false );
			if ( $option == false ) {
				$option = get_blog_option( 1, 'affiliateadvancedsettingstext' );
				aff_update_option( 'affiliateadvancedsettingstext', $option );
			}

			$option = aff_get_option( 'affiliateenablebanners', false );
			if ( $option == false ) {
				$option = get_blog_option( 1, 'affiliateenablebanners' );
				aff_update_option( 'affiliateenablebanners', $option );
			}

			$option = aff_get_option( 'affiliatelinkurl', false );
			if ( $option == false ) {
				$option = get_blog_option( 1, 'affiliatelinkurl' );
				aff_update_option( 'affiliatelinkurl', $option );
			}

			$option = aff_get_option( 'affiliatebannerlinks', false );
			if ( $option == false ) {
				$option = get_blog_option( 1, 'affiliatebannerlinks' );
				aff_update_option( 'affiliatebannerlinks', $option );
			}

			$option = aff_get_option( 'affiliate_activated_addons', false );
			if ( $option == false ) {
				$option = get_blog_option( 1, 'affiliate_activated_addons' );
				aff_update_option( 'affiliate_activated_addons', $option );
			}
		}
	}

	function load_textdomain() {

		$locale = apply_filters( 'affiliate_locale', get_locale() );
		$mofile = affiliate_dir( "affiliateincludes/languages/affiliate-$locale.mo" );

		if ( file_exists( $mofile ) ) {
			load_textdomain( 'affiliate', $mofile );
		}

	}

	function initialise_ajax() {
		add_action( 'wp_ajax__aff_getstats', array( &$this, 'ajax__aff_getstats' ) );
		add_action( 'wp_ajax__aff_getvisits', array( &$this, 'ajax__aff_getvisits' ) );
	}

	function ajax__aff_getstats() {

		global $user;

		$user    = wp_get_current_user();
		$user_ID = $user->ID;

		$headings = aff_get_option( 'affiliateheadings', array(
			__( 'Unique Clicks', 'affiliate' ),
			__( 'Sign ups', 'affiliate' ),
			__( 'Paid members', 'affiliate' )
		) );

		if ( isset( $_GET['number'] ) ) {
			$number = intval( addslashes( $_GET['number'] ) );
		} else {
			$number = 18;
		}

		if ( isset( $_GET['userid'] ) ) {
			$user_ID = intval( addslashes( $_GET['userid'] ) );
		}

		$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC LIMIT 0, %d", $user_ID, $number ) );

		$startat  = strtotime( date( "Y-m-15" ) );
		$clicks   = array();
		$signups  = array();
		$payments = array();

		$ticks = array();

		if ( ! empty( $results ) ) {
			$recent = array_shift( $results );
		} else {
			$recent = array();
		}

		for ( $n = 0; $n < $number; $n ++ ) {
			$place  = $number - $n;
			$rdate  = strtotime( "-$n month", $startat );
			$period = date( 'Ym', $rdate );

			$ticks[] = array( (int) $place, date_i18n( 'M', $rdate ) . '<br/>' . date_i18n( 'Y', $rdate ) );

			if ( ! empty( $recent ) && $recent->period == $period ) {
				// We are on the current period
				$clicks[]   = array( (int) $place, (int) $recent->uniques );
				$signups[]  = array( (int) $place, (int) $recent->signups );
				$payments[] = array( (int) $place, (int) $recent->completes );

				if ( ! empty( $results ) ) {
					$recent = array_shift( $results );
				} else {
					$recent = array();
				}

			} else {
				// A zero blank row
				$clicks[]   = array( (int) $place, (int) 0 );
				$signups[]  = array( (int) $place, (int) 0 );
				$payments[] = array( (int) $place, (int) 0 );

			}
		}

		$return            = array();
		$return['chart'][] = array( "label" => stripslashes( $headings[0] ), "data" => $clicks );
		$return['chart'][] = array( "label" => stripslashes( $headings[1] ), "data" => $signups );
		$return['chart'][] = array( "label" => stripslashes( $headings[2] ), "data" => $payments );

		$return['ticks'] = $ticks;

		$this->return_json( $return );

		exit;
	}

	function ajax__aff_getvisits() {

		global $user;

		$user    = wp_get_current_user();
		$user_ID = $user->ID;

		// Build 18 months of years
		$startat = strtotime( date( "Y-m-15" ) );
		$years   = array();
		for ( $n = 0; $n < 18; $n ++ ) {
			$rdate   = strtotime( "-$n month", $startat );
			$years[] = "'" . date( 'Ym', $rdate ) . "'";
		}

		$visitresults = $this->db->get_results( $this->db->prepare( "SELECT ar.* FROM {$this->affiliatereferrers} as ar INNER JOIN ( SELECT url FROM {$this->affiliatereferrers} WHERE user_id = $user_ID AND period in (" . implode( ',', $years ) . ") GROUP BY url ORDER BY sum(referred) DESC LIMIT 0, 10 ) as arr ON ar.url = arr.url WHERE ar.user_id = %d ORDER BY ar.url, ar.period DESC", $user_ID ) );

		$urls = $this->db->get_col( null, 2 );

		$startat = strtotime( date( "Y-m-15" ) );
		$visits  = array();

		$ticks = array();
		$urls  = array_unique( $urls );

		$return = array();

		foreach ( $urls as $key => $url ) {
			$results = $visitresults;
			if ( ! empty( $results ) ) {
				$recent = array_shift( $results );
				while ( $recent->url != $url ) {
					$recent = array_shift( $results );
				}
			}
			for ( $n = 0; $n < 12; $n ++ ) {
				$place  = 12 - $n;
				$rdate  = strtotime( "-$n month", $startat );
				$period = date( 'Ym', $rdate );

				$ticks[] = array( (int) $place, date_i18n( 'M', $rdate ) . '<br/>' . date_i18n( 'Y', $rdate ) );

				if ( ! empty( $recent ) && $recent->period == $period && $recent->url == $url ) {
					// We are on the current period
					$visits[ $url ][] = array( (int) $place, (int) $recent->referred );

					if ( ! empty( $results ) ) {
						$recent = array_shift( $results );
					} else {
						$recent = array();
					}

				} else {
					// A zero blank row
					$visits[ $url ][] = array( (int) $place, (int) 0 );

				}
			}

			$return['chart'][] = array( "label" => $url, "data" => $visits[ $url ] );

		}

		$return['ticks'] = $ticks;

		$this->return_json( $return );

		exit;
	}

	function return_json( $results ) {

		// Check for callback
		if ( isset( $_GET['callback'] ) ) {
			// Add the relevant header
			header( 'Content-type: text/javascript' );
			echo addslashes( $_GET['callback'] ) . " (";
		} else {
			if ( isset( $_GET['pretty'] ) ) {
				// Will output pretty version
				header( 'Content-type: text/html' );
			} else {
				header( 'Content-type: application/json' );
			}
		}

		if ( function_exists( 'json_encode' ) ) {
			echo json_encode( $results );
		} else {
			// PHP4 version
			require_once( ABSPATH . "wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php" );
			$json_obj = new Moxiecode_JSON();
			echo $json_obj->encode( $results );
		}

		if ( isset( $_GET['callback'] ) ) {
			echo ")";
		}
	}


	function add_menu_items() {

		global $submenu;

		$user    = wp_get_current_user();
		$user_ID = $user->ID;

		if ( function_exists( 'is_network_admin' ) && is_network_admin() ) {
			$capabilty = 'manage_network_options';
		} else {
			$capabilty = 'manage_options';
		}

		// Add administration menu
		if ( is_multisite() ) {
			if ( affiliate_is_plugin_active_for_network() ) {
				// we're activated site wide so put the admin menu in the network area
				if ( function_exists( 'is_network_admin' ) ) {
					if ( is_network_admin() ) {
						add_menu_page( __( 'Affiliates', 'affiliate' ), __( 'Affiliates', 'affiliate' ), $capabilty, 'affiliatesadmin', array(
							&$this,
							'handle_affiliates_panel'
						), affiliate_url( 'affiliateincludes/images/affiliatelogo.png' ) );
					}
				}
			} else {
				// we're only activated on a blog level so put the admin menu in the main area
				if ( ! function_exists( 'is_network_admin' ) ) {
					add_menu_page( __( 'Affiliates', 'affiliate' ), __( 'Affiliates', 'affiliate' ), $capabilty, 'affiliatesadmin', array(
						&$this,
						'handle_affiliates_panel'
					), affiliate_url( 'affiliateincludes/images/affiliatelogo.png' ) );
				} elseif ( ! is_network_admin() ) {
					add_menu_page( __( 'Affiliates', 'affiliate' ), __( 'Affiliates', 'affiliate' ), $capabilty, 'affiliatesadmin', array(
						&$this,
						'handle_affiliates_panel'
					), affiliate_url( 'affiliateincludes/images/affiliatelogo.png' ) );
				}
			}
		} else {
			add_menu_page( __( 'Affiliates', 'affiliate' ), __( 'Affiliates', 'affiliate' ), $capabilty, 'affiliatesadmin', array(
				&$this,
				'handle_affiliates_panel'
			), affiliate_url( 'affiliateincludes/images/affiliatelogo.png' ) );
		}

		add_submenu_page( 'affiliatesadmin', __( 'Manage Affiliates', 'affiliate' ), __( 'Manage Affiliates', 'affiliate' ), $capabilty, 'affiliatesadminmanage', array(
			&$this,
			'handle_affiliates_panel'
		) );
		add_submenu_page( 'affiliatesadmin', __( 'Settings', 'affiliate' ), __( 'Settings', 'affiliate' ), $capabilty, 'affiliatesadminsettings', array(
			&$this,
			'handle_affiliates_panel'
		) );
		add_submenu_page( 'affiliatesadmin', __( 'Add-ons', 'affiliate' ), __( 'Add-ons', 'affiliate' ), $capabilty, 'affiliatesadminaddons', array(
			&$this,
			'handle_affiliates_panel'
		) );

		if ( isset( $submenu['affiliatesadmin'] ) ) {
			$submenu['affiliatesadmin'][0][0] = __( 'Affiliate Reports', 'affiliate' );
		}

		add_submenu_page( 'users.php', __( 'Affiliate Earnings Report', 'affiliate' ), __( 'Affiliate Referrals', 'affiliate' ), 'read', "affiliateearnings", array(
			&$this,
			'add_profile_report_page'
		) );

		// Add profile menu
		if ( get_user_meta( $user_ID, 'enable_affiliate', true ) == 'yes' ) {
			if ( aff_get_option( 'affiliateenablebanners', 'no' ) == 'yes' ) {
				add_submenu_page( 'users.php', __( 'Affiliate Banners', 'affiliate' ), __( 'Affiliate Banners', 'affiliate' ), 'read', "affiliatebanners", array(
					&$this,
					'add_profile_banner_page'
				) );
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
		if ( isset( $_GET['page'] ) && addslashes( $_GET['page'] ) == 'affiliateearnings' ) {
			wp_enqueue_script( 'flot_js', affiliate_url( 'affiliateincludes/js/jquery.flot.min.js' ), array( 'jquery' ) );
			wp_enqueue_script( 'flot_js', affiliate_url( 'affiliateincludes/js/jquery.flot.pie.min.js' ), array( 'flot_js' ) );
			wp_enqueue_script( 'aff_js', affiliate_url( 'affiliateincludes/js/affiliateliteuserreport.js' ), array( 'jquery' ) );

			add_action( 'admin_head', array( &$this, 'add_iehead' ) );
		}

		// Admin user report page
		if ( ( ( isset( $_GET['page'] ) ) && ( $_GET['page'] == 'affiliatesadminmanage' ) )
		     && isset( $_GET['id'] )
		) {
			wp_enqueue_script( 'flot_js', affiliate_url( 'affiliateincludes/js/jquery.flot.min.js' ), array( 'jquery' ) );

			wp_enqueue_script( 'aff_js', affiliate_url( 'affiliateincludes/js/affiliateadminuserreport.js' ), array( 'jquery' ) );

			add_action( 'admin_head', array( &$this, 'add_iehead' ) );
		}


	}

	function add_iehead() {
		echo '<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="' . affiliate_url( 'affiliateincludes/js/excanvas.min.js' ) . '"></script><![endif]-->';
	}

	function is_duplicate_url( $url, $user_id ) {

		if ( empty( $url ) ) {
			return false;
		}

		$affiliate = $this->db->get_var( $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_referrer' AND meta_value='%s' AND user_id != %d", $url, $user_id ) );

		if ( empty( $affiliate ) ) {
			return false;
		} else {
			return true;
		}

	}

	function validate_url_for_file( $url, $file ) {
                $schema = is_ssl() ? 'https://' : 'http://';

		$fullurl = $schema . $url . $file;

		$response = wp_remote_head( $fullurl );

		if ( ! empty( $response['response']['code'] ) && $response['response']['code'] == '200' ) {
			return true;
		} else {
			return false;
		}

	}

	function add_profile_report_page() {

		$user    = wp_get_current_user();
		$user_ID = $user->ID;

		$headings = aff_get_option( 'affiliateheadings', array(
			__( 'Unique Clicks', 'affiliate' ),
			__( 'Sign ups', 'affiliate' ),
			__( 'Paid members', 'affiliate' )
		) );

		$headings = array_merge( $headings, array(
			__( 'Debits', 'affiliate' ),
			__( 'Credits', 'affiliate' ),
			__( 'Payments', 'affiliate' )
		) );

		$newcolumns = apply_filters( 'affiliate_column_names', $headings );
		if ( count( $newcolumns ) == 6 ) {
			// We must have 6 columns
			$columns = $newcolumns;
		}

		$reference = get_user_meta( $user_ID, 'affiliate_reference', true );

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( 'affiliate/affiliate.php' ) ) {
			$site = aff_get_option( 'site_name' );
			// switch to use new option
			$siteurl          = get_blog_option( 1, 'home' );
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		} else {
			$site = aff_get_option( 'blogname' );
			// switch to use new option
			$siteurl          = aff_get_option( 'home' );
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		}

		if ( isset( $_POST['action'] ) && addslashes( $_POST['action'] ) == 'update' ) {

			//echo "_POST<pre>"; print_r($_POST); echo "</pre>";

			check_admin_referer( 'affiliatesettings' );

			update_user_meta( $user_ID, 'enable_affiliate', $_POST['enable_affiliate'] );
			update_user_meta( $user_ID, 'affiliate_paypal', $_POST['affiliate_paypal'] );
			if ( ! empty( $_POST['affiliate_referrer'] ) ) {

				$url = str_replace( array('http://', 'https://'), '', untrailingslashit( $_POST['affiliate_referrer'] ) );
				// store the update - even though it could be wrong
				update_user_meta( $user_ID, 'affiliate_referrer', $url );
				// Remove any validated referrers as it may have been changed
				delete_user_meta( $user_ID, 'affiliate_referrer_validated' );

				// Check for duplicate and if not unique we need to display the open box with an error message
				if ( $this->is_duplicate_url( $url, $user_ID ) ) {
					$error  = 'yes';
					$chkmsg = __( 'This URL is already in use.', 'affiliate' );
				} else {
					// Create the message we are looking for
					$chkmsg = '';
					// Check a file with it exists and contains the content
					if ( defined( 'AFFILIATE_VALIDATE_REFERRER_URLS' ) && AFFILIATE_VALIDATE_REFERRER_URLS == 'yes' ) {
						$referrer = $_POST['affiliate_referrer'];
						$filename = md5( 'affiliatefilename-' . $user_ID . '-' . $user->user_login . "-" . $referrer ) . '.html';
						//echo "filename=[". $filename ."]<br />";
						if ( $this->validate_url_for_file( trailingslashit( $url ), $filename ) ) {
							update_user_meta( $user_ID, 'affiliate_referrer_validated', 'yes' );
							$chkmsg = __( 'Validated', 'affiliate' );
						} else {
							$error  = 'yes';
							$chkmsg = __( 'Not validated', 'affiliate' );
						}
					}
				}
			} else {
				delete_user_meta( $user_ID, 'affiliate_referrer_validated' );
				delete_user_meta( $user_ID, 'affiliate_referrer' );
			}
			if ( isset( $_POST['enable_affiliate'] ) && addslashes( $_POST['enable_affiliate'] ) == 'yes' ) {
				// Set up the affiliation details
				// Store a record of the reference
				$reference = aff_build_reference( $user );
				update_user_meta( $user_ID, 'affiliate_reference', $reference );
				update_user_meta( $user_ID, 'affiliate_hash', 'aff' . md5( AUTH_SALT . $reference ) );
			} else {
				// Wipe the affiliation details
				delete_user_meta( $user_ID, 'affiliate_reference' );
				delete_user_meta( $user_ID, 'affiliate_hash' );
			}

		}

		echo "<div class='wrap'>";
		echo '<div class="icon32" id="icon-themes"><br/></div>';
		echo "<h2>" . __( 'Affiliate Referral Report', 'affiliate' ) . "</h2>";

		echo "<div style='width: 98%; margin-top: 20px; background-color: #FFFEEB; margin-left: auto; margin-right: auto; margin-bottom: 20px; border: 1px #e6db55 solid; padding: 10px;'>";
		if ( get_user_meta( $user_ID, 'enable_affiliate', true ) == 'yes' ) {
			echo "<strong>" . __( 'Hello, Thank you for supporting us</strong>, to view or change any of your affiliate settings click on the edit link.', 'affiliate' ) . "</strong><a href='#view' id='editaffsettingslink' style='float:right; font-size: 8pt;'>" . __( 'edit', 'affiliate' ) . "</a>";

			if ( empty( $error ) ) {
				echo "<div id='innerbox' style='width: 100%; display: none;'>";
			} else {
				echo "<div id='innerbox' style='width: 100%;'>";
			}

			echo "<form action='' method='post'>";
			wp_nonce_field( "affiliatesettings" );

			echo "<input type='hidden' name='action' value='update' />";

			$settingstextdefault = __( "<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p><p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p><p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>", 'affiliate' );

			echo stripslashes( aff_get_option( 'affiliatesettingstext', $settingstextdefault ) );

			?>

			<table class="form-table">
				<tr style='background: transparent;'>
					<th><label for="enable_affiliate"><?php _e( 'Enable Affiliate links', 'affiliate' ); ?></label></th>
					<td>
						<select name='enable_affiliate'>
							<option
								value='yes' <?php if ( get_user_meta( $user_ID, 'enable_affiliate', true ) == 'yes' ) {
								echo "selected = 'selected'";
							} ?>><?php _e( 'Yes please', 'affiliate' ); ?></option>
							<option
								value='no' <?php if ( get_user_meta( $user_ID, 'enable_affiliate', true ) != 'yes' ) {
								echo "selected = 'selected'";
							} ?>><?php _e( 'No thanks', 'affiliate' ); ?></option>
						</select>
					</td>
				</tr>

				<tr style='background: transparent;'>
					<th><label for="affiliate_paypal"><?php _e( 'PayPal Email Address', 'affiliate' ); ?></label></th>
					<td>
						<input type="text" name="affiliate_paypal" id="affiliate_paypal"
						       value="<?php echo esc_attr( get_user_meta( $user_ID, 'affiliate_paypal', true ) ); ?>"
						       class="regular-text"/>
					</td>
				</tr>

			</table>

			<?php

			if ( get_user_meta( $user_ID, 'enable_affiliate', true ) == 'yes' ) {

				$reference = get_user_meta( $user_ID, 'affiliate_reference', true );
				//echo "reference[". $reference ."]<br />";

				$referrer = get_user_meta( $user_ID, 'affiliate_referrer', true );
				//echo "referrer[". $referrer ."]<br />";

				$refurl = "profile.php?page=affiliateearnings";

				$validreferrer = get_user_meta( $user_ID, 'affiliate_referrer_validated', true );
				//echo "validreferrer[". $validreferrer ."]<br />";

				?>
				<p><?php _e( '<h3>Affiliate Details</h3>', 'affiliate' ) ?></p>
				<p><?php _e( 'In order for us to track your referrals, you should use the following URL to link to our site:', 'affiliate' ); ?></p>
				<p><?php echo sprintf( __( '<strong>%s?ref=%s</strong>', 'affiliate' ), $affiliatelinkurl, $reference ); ?></p>
				<?php
				/*
						if(defined('AFFILIATE_CHECKALL') && AFFILIATE_CHECKALL == 'yes' && !empty($referrer)) {
							// We are always going to check for a referer site
							?>
							<p><?php _e('Alternatively you can use the just link directly to the URL below from the site you entered in the advanced settings above:', 'affiliate'); ?></p>
							<p><?php echo sprintf(__('<strong>%s</strong>', 'affiliate'), $siteurl ); ?></p>
							<?php

						}
						*/

				if ( aff_get_option( 'affiliateenablebanners', 'no' ) == 'yes' ) {
					?>
					<p><?php echo sprintf( __( 'If you would rather use a banner or button then we have a wide selection of sizes <a href="%s">here</a>.', 'affiliate' ), "profile.php?page=affiliatebanners" ); ?></p>
				<?php } ?>
				<?php /* ?><p><?php _e('<strong>You can check on your referral totals by viewing the details on this page</strong>', 'affiliate'); ?></p><?php */ ?>
				<?php

				if ( defined( 'AFFILIATE_CHECKALL' ) && AFFILIATE_CHECKALL == 'yes' ) { ?>

					<h3><?php _e( 'Affiliate Advanced Settings', 'affiliate' ) ?></h3>

					<?php
					$advsettingstextdefault = __( "<p>There are times when you would rather hide your affiliate link, or simply not have to bother remembering the affiliate reference to put on the end of our URL.</p><p>If this is the case, then you can enter the main URL of the site you will be sending requests from below, and we will sort out the tricky bits for you.</p>", 'affiliate' );

					echo stripslashes( aff_get_option( 'affiliateadvancedsettingstext', $advsettingstextdefault ) );


					if ( ! empty( $chkmsg ) ) {
						if ( empty( $error ) ) {
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
							<th><label for="affiliate_referrer"><?php _e( 'Your URL', 'affiliate' ); ?></label></th>
							<td>
								<?php echo is_ssl() ? 'https' : 'http'; ?>://&nbsp;<input type="text"
								                                                          name="affiliate_referrer"
								                                                          id="affiliate_referrer"
								                                                          value="<?php echo esc_attr( $referrer ); ?>"
								                                                          class="regular-text"/>
								<?php echo "&nbsp;&nbsp;";
								if ( isset( $msg ) ) {
									echo $msg;
								}
								?>
								<?php
								if ( defined( 'AFFILIATE_VALIDATE_REFERRER_URLS' ) && AFFILIATE_VALIDATE_REFERRER_URLS == 'yes' ) {
									if ( empty( $referrer ) || ( ! empty( $validreferrer ) && $validreferrer == 'yes' ) ) {
									} else {
										// Not valid - generate filename
										$filename = md5( 'affiliatefilename-' . $user_ID . '-' . $user->user_login . "-" . $referrer ) . '.html';
                                                                                $schema = is_ssl() ? 'https://' : 'http://';

										// Output message
										echo "<br/>";
										_e( 'You need to validate this URL by uploading a file to the root of the site above with the following name : ', 'affiliate' );
										echo "<br/>";
										echo __( 'Filename : ', 'affiliate' ) . $filename;
										echo " <a href='" . $schema . trailingslashit( $referrer ) . $filename . "' target=_blank>" . __( '[click here to check if the file exists]', 'affiliate' ) . "</a>";
										echo '<br/><input type="submit" name="Submit" class="button" value="' . __( 'Validate', 'affiliate' ) . '" />';
									}
								}

								?>
							</td>
						</tr>


					</table>
					<?php
				}
			}

			echo '<p class="submit">';
			echo '<input type="submit" class="button-primary" name="Submit" value="' . __( 'Update Settings', 'affiliate' ) . '" /></p>';

			echo "</form>";
			echo "</div>";

		} else {
			// Not an affiliate yet, so display the form
			echo "<strong>" . __( 'Hello, why not consider becoming an affiliate?', 'affiliate' ) . "</strong><br/>";

			echo "<div id='innerbox' style='width: 100%'>";

			echo "<form action='' method='post'>";
			wp_nonce_field( "affiliatesettings" );

			echo "<input type='hidden' name='action' value='update' />";


			$settingstextdefault = __( "<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p><p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p><p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>", 'affiliate' );

			echo stripslashes( aff_get_option( 'affiliatesettingstext', $settingstextdefault ) );

			?>

			<table class="form-table">
				<tr style='background: transparent;'>
					<th><label for="enable_affiliate"><?php _e( 'Enable Affiliate links', 'affiliate' ); ?></label></th>
					<td>
						<select name='enable_affiliate'>
							<option
								value='yes' <?php if ( get_user_meta( $user_ID, 'enable_affiliate', true ) == 'yes' ) {
								echo "selected = 'selected'";
							} ?>><?php _e( 'Yes please', 'affiliate' ); ?></option>
							<option
								value='no' <?php if ( get_user_meta( $user_ID, 'enable_affiliate', true ) != 'yes' ) {
								echo "selected = 'selected'";
							} ?>><?php _e( 'No thanks', 'affiliate' ); ?></option>
						</select>
					</td>
				</tr>

				<tr style='background: transparent;'>
					<th><label for="affiliate_paypal"><?php _e( 'PayPal Email Address', 'affiliate' ); ?></label></th>
					<td>
						<input type="text" name="affiliate_paypal" id="affiliate_paypal"
						       value="<?php echo esc_attr( get_user_meta( $user_ID, 'affiliate_paypal', true ) ); ?>"
						       class="regular-text"/>
					</td>
				</tr>

			</table>

			<?php

			echo '<p class="submit">';
			echo '<input type="submit" class="button-primary" name="Submit" value="' . __( 'Update Settings', 'affiliate' ) . '" /></p>';

			echo "</form>";
			echo "</div>";


		}

		echo "</div>";


		//$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC", $user_ID ) );

		if ( get_user_meta( $user_ID, 'enable_affiliate', true ) == 'yes' ) {


			if ( ( isset( $_GET['subpage'] ) ) && ( $_GET['subpage'] == "details" ) ) {
				echo '<br /><a href="' . esc_url( add_query_arg( 'subpage', 'summary' ) ) . '">' . __( '&larr; Return to Affiliate Period Summary', 'affiliate' ) . '</a>';
			}


			echo "<div id='affdashgraph' style='width: 100%; margin-top: 20px; min-height: 350px; background-color: #fff; margin-bottom: 20px;'>";
			echo "</div>";

			echo "<div id='clickscolumn' style='width: 48%; margin-right: 2%; margin-top: 20px; min-height: 400px; float: left;'>";

			if ( ( isset( $_GET['subpage'] ) ) && ( $_GET['subpage'] == "details" ) ) {
				$period = '';
				if ( isset( $_GET['period'] ) ) {
					$period = esc_attr( $_GET['period'] );
				}
				if ( ! empty( $period ) ) {
					$period = date( 'Ym' );
				}
				$this->show_users_period_details_table( $user_ID, $period );
			} else {
				$this->show_users_period_summary_table( $user_ID );
			}

			echo "</div>";


			echo "<div id='referrerscolumn' style='width: 48%; margin-left: 2%; min-height: 400px; margin-top: 20px; background: #fff; float: left;'>";

			do_action( 'affiliate_before_profile_graphs', $user_ID );
			do_action( 'affiliate_before_visits_table', $user_ID );

			echo "<div id='affvisitgraph' style='width: 100%; min-height: 350px; background-color: #fff; margin-bottom: 20px;'>";
			echo "</div>";

			// This months visits table
			$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatereferrers} WHERE user_id = %d AND period = %s ORDER BY referred DESC LIMIT 0, 15", $user_ID, date( "Ym" ) ) );
			echo "<table class='widefat'>";

			echo "<thead>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo __( 'Top referrers for ', 'affiliate' ) . date( "M Y" );
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __( 'Visits', 'affiliate' );
			echo "</th>";
			echo "</tr>";
			echo "</thead>";

			echo "<tfoot>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo __( 'Top referrers for ', 'affiliate' ) . date( "M Y" );
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __( 'Visits', 'affiliate' );
			echo "</th>";
			echo "</tr>";
			echo "</tfoot>";

			echo "<tbody>";

                        $schema = is_ssl() ? 'https://' : 'http://';

			if ( ! empty( $rows ) ) {

				$class = 'alternate';
				foreach ( $rows as $r ) {

					echo "<tr class='$class' style='$style'>";
					echo "<td style='padding: 5px;'>";
					echo "<a href='" . $schema . $r->url . "'>" . $r->url . "</a>";
					echo "</td>";
					echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
					echo $r->referred;
					echo "</td>";
					echo "</tr>";

					if ( $class != 'alternate' ) {
						$class = '';
					} else {
						$class = 'alternate';
					}

				}

			} else {
				echo __( '<tr><td colspan="2">You have no referred visits this month.</td></tr>', 'affiliate' );
			}

			echo "</tbody>";
			echo "</table>";

			do_action( 'affiliate_after_visits_table', $user_ID );

			do_action( 'affiliate_before_topreferrers_table', $user_ID );

			// Top referrers of all time

			// Build 18 months of years
			$startat = strtotime( date( "Y-m-15" ) );
			$years   = array();
			for ( $n = 0; $n < 18; $n ++ ) {
				$rdate   = strtotime( "-$n month", $startat );
				$years[] = "'" . date( 'Ym', $rdate ) . "'";
			}

			$rows = $this->db->get_results( $this->db->prepare( "SELECT url, SUM(referred) as totalreferred FROM {$this->affiliatereferrers} WHERE user_id = %d AND period in (" . implode( ',', $years ) . ") GROUP BY url ORDER BY totalreferred DESC LIMIT 0, 15", $user_ID ) );
			echo "<br/>";
			echo "<table class='widefat'>";

			echo "<thead>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo __( 'Top referrers over past 18 months', 'affiliate' );
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __( 'Visits', 'affiliate' );
			echo "</th>";
			echo "</tr>";
			echo "</thead>";

			echo "<tfoot>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo __( 'Top referrers over past 18 months', 'affiliate' );
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo __( 'Visits', 'affiliate' );
			echo "</th>";
			echo "</tr>";
			echo "</tfoot>";

			echo "<tbody>";

                        $schema = is_ssl() ? 'https://' : 'http://';

			if ( ! empty( $rows ) ) {

				$class = 'alternate';
				foreach ( $rows as $r ) {

					echo "<tr class='$class' style='$style'>";
					echo "<td style='padding: 5px;'>";
					echo "<a href='" . $schema . $r->url . "'>" . $r->url . "</a>";
					echo "</td>";
					echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
					echo $r->totalreferred;
					echo "</td>";
					echo "</tr>";

					if ( $class != 'alternate' ) {
						$class = '';
					} else {
						$class = 'alternate';
					}

				}

			} else {
				echo __( '<tr><td colspan="2">You have no overall referred visits.</td></tr>', 'affiliate' );
			}

			echo "</tbody>";
			echo "</table>";


			echo "</div>";

			do_action( 'affiliate_after_topreferrers_table', $user_ID );

			do_action( 'affiliate_after_profile_graphs', $user_ID );

			echo "<div style='clear: both;'></div>";
		}
		?>

		</div>
		<?php

	}

	function add_profile_banner_page() {

		$user    = wp_get_current_user();
		$user_ID = $user->ID;

		$reference = get_user_meta( $user_ID, 'affiliate_reference', true );

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( 'affiliate/affiliate.php' ) ) {
			$site = aff_get_option( 'site_name' );
			// switch to use new option
			$siteurl          = get_blog_option( 1, 'home' );
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		} else {
			$site = aff_get_option( 'blogname' );
			// switch to use new option
			$siteurl          = aff_get_option( 'home' );
			$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
		}

		?>
		<div class='wrap'>
			<h2>Affiliate Banners</h2>

			<p><?php _e( "So, you want something more exciting than a straight forward text link?", 'affiliate' ); ?></p>
			<p><?php _e( "Not to worry, we've got banners and buttons galore. To use them simply copy and paste the HTML underneath the graphic that you want to use.", 'affiliate' ); ?></p>

			<?php

			$banners = aff_get_option( 'affiliatebannerlinks' );
			foreach ( (array) $banners as $banner ) {
				// Split the string in case there is a | in there
				$advbanner = explode( "|", $banner );
				if ( count( $advbanner ) == 1 ) {
					$advbanner[] = $affiliatelinkurl;
				}
				// Trim the array so that it removes none text characters
				array_map( 'trim', $advbanner );
				?>
				<img src='<?php echo $advbanner[0]; ?>'/>
				<br/><br/>
				<textarea cols='80' rows='5'><?php
					echo sprintf( "<a href='%s?ref=%s'>\n", $advbanner[1], $reference );
					echo "<img src='" . $advbanner[0] . "' alt='" . htmlentities( stripslashes( $site ), ENT_QUOTES, 'UTF-8' ) . "' title='Check out " . htmlentities( stripslashes( $site ), ENT_QUOTES, 'UTF-8' ) . "' />\n";
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

		//echo "_GET<pre>"; print_r($_GET); echo "</pre>";
		//echo "_POST<pre>"; print_r($_POST); echo "</pre>";

		$page = ( isset( $_GET['page'] ) ) ? addslashes( $_GET['page'] ) : false;

		if ( $page == 'affiliatesadmin' && isset( $_GET['action'] ) ) {

			//echo "_GET<pre>"; print_r($_GET); echo "</pre>";
			//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
			switch ( addslashes( $_GET['action'] ) ) {

				case 'allaffiliates':    // Bulk operations
					check_admin_referer( 'allaffiliateactions' );
					if ( isset( $_POST['allaction_exportpayments'] ) ) {
						// Create an export file
						header( "Content-type: application/octet-stream" );
						header( "Content-Disposition: attachment; filename=\"masspayexport.txt\"" );

						if ( ! empty( $_POST['allpayments'] ) ) {
							foreach ( $_POST['allpayments'] as $affiliate ) {
								// Reset variables
								$paypal   = "";
								$amount   = "0.00";
								$currency = aff_get_option( 'affiliate-currency-paypal-masspay', 'USD' );
								$id       = "AFF_PAYMENT";
								$notes    = __( "Affiliate payment for", "affiliate" );

								$affdetails = explode( '-', $affiliate );
								//echo "affdetails<pre>"; print_r($affdetails); echo "</pre>";
								if ( count( $affdetails ) == 2 ) {

									$name = get_option( 'blogname' );

									$user = get_userdata( $affdetails[0] );

									$id    = substr( "AFF_PAYMENT_" . strtoupper( $user->user_login ), 0, 30 );
									$notes = __( "Affiliate payment for ", "affiliate" ) . $name;

									$paypal  = get_user_meta( $affdetails[0], 'affiliate_paypal', true );
									$amounts = $this->db->get_row( "SELECT debits, credits, payments FROM " . $this->affiliatedata . " WHERE user_id = " . $affdetails[0] . " AND period = '" . $affdetails[1] . "'" );
									//echo "amounts<pre>"; print_r($amounts); echo "</pre>";

									$amount = ( $amounts->credits - $amounts->debits ) - $amounts->payments;
									//echo "amount[". $amount ."]<br />";
									if ( $amount > 0 && ! empty( $paypal ) ) {
										$line = sprintf( "%s\t%01.2f\t%s\t%s\t%s\n", $paypal, $amount, $currency, $id, $notes );
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

		if ( isset( $_GET['action'] ) && addslashes( $_GET['action'] ) == 'updateaffiliateoptions' ) {
			check_admin_referer( 'affiliateoptions' );

			//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
			//die();

			$headings   = array();
			$headings[] = $_POST['uniqueclicks'];
			$headings[] = $_POST['signups'];
			$headings[] = $_POST['paidmembers'];

			aff_update_option( 'affiliateheadings', $headings );

			aff_update_option( 'affiliatesettingstext', $_POST['affiliatesettingstext'] );
			aff_update_option( 'affiliateadvancedsettingstext', $_POST['affiliateadvancedsettingstext'] );

			aff_update_option( 'affiliateenablebanners', $_POST['affiliateenablebanners'] );
			aff_update_option( 'affiliateenableapproval', $_POST['affiliateenableapproval'] );

			if ( ! empty( $_POST['affiliatelinkurl'] ) ) {
				aff_update_option( 'affiliatelinkurl', $_POST['affiliatelinkurl'] );
			} else {
				aff_delete_option( 'affiliatelinkurl' );
			}

			if ( ( isset( $_POST['affiliate-currency-paypal-masspay'] ) ) && ( ! empty( $_POST['affiliate-currency-paypal-masspay'] ) ) ) {
				aff_update_option( 'affiliate-currency-paypal-masspay', $_POST['affiliate-currency-paypal-masspay'] );
			} else {
				aff_delete_option( 'affiliate-currency-paypal-masspay' );
			}


			$banners = explode( "\n", stripslashes( $_POST['affiliatebannerlinks'] ) );

			foreach ( $banners as $key => $b ) {
				$banners[ $key ] = trim( $b );
			}
			aff_update_option( 'affiliatebannerlinks', $banners );

			do_action( 'affililate_settings_form_update' );

			echo '<div id="message" class="updated fade"><p>' . __( 'Affiliate settings saved.', 'affiliate' ) . '</p></div>';
		}

		$page    = ( isset( $_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : '';
		$subpage = ( isset( $_GET['subpage'] ) ) ? esc_attr( $_GET['subpage'] ) : '';

		echo '<div  id="poststuff" class=class="metabox-holder m-settings">';

		echo '<form method="post" action="?page=' . $page . '&amp;subpage=' . $subpage . '&amp;action=updateaffiliateoptions">';
		wp_nonce_field( "affiliateoptions" );


		show_affiliate_admin_metabox_reports_affiliate_link();
		//show_affiliate_admin_metabox_reports_monetary_precision();
		show_affiliate_admin_metabox_settings_paypal_masspay_currency();
		show_affiliate_admin_metabox_reports_column_settings();
		show_affiliate_admin_metabox_profile_text();
		show_affiliate_admin_metabox_settings_banner();
		show_affiliate_admin_metabox_settings_approval();

		do_action( 'affililate_settings_form' );

		echo '<p class="submit">';
		echo '<input type="submit" name="Submit" value="' . __( 'Update Settings', 'affiliate' ) . '" class="button-primary" /></p>';

		echo '</form>';

		echo '</div>';

		echo "</div>";


	}

	function handle_affiliate_users_panel() {

		if ( isset( $_REQUEST['id'] ) ) {
			// There is a user so we'll grab the data
			$user_id = addslashes( $_REQUEST['id'] );

			if ( isset( $_POST['action'] ) ) {

				switch ( addslashes( $_POST['action'] ) ) {

					case 'userdebit':
						check_admin_referer( 'debit-user-' . $user_id );
						$period      = addslashes( $_POST['debitperiod'] );
						$debit       = abs( floatval( $_POST['debitvalue'] ) );
						$note        = esc_attr( $_POST['debitnote'] );
						$sql         = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, debits, lastupdated) VALUES (%d, %s, %f, now()) ON DUPLICATE KEY UPDATE debits = debits + %f", $user_id, $period, $debit, $debit );
						$queryresult = $this->db->query( $sql );
						if ( $queryresult ) {
							$user = wp_get_current_user();
							$meta = array(
								'current_user_id' => $user->ID,
								'LOCAL_URL'       => ( is_ssl() ? 'https://' : 'http://' ) . esc_attr( $_SERVER['HTTP_HOST'] ) . esc_attr( $_SERVER['REQUEST_URI'] ),
								'IP'              => ( isset( $_SERVER['HTTP_X_FORWARD_FOR'] ) ) ? esc_attr( $_SERVER['HTTP_X_FORWARD_FOR'] ) : esc_attr( $_SERVER['REMOTE_ADDR'] ),
								//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
							);

							//$note .= ' '. __('by', 'affiliate') .': ';
							//if ( !empty( $user->display_name ) )
							//	$note .= $user->display_name .' ('. $user->user_login.')';
							//else
							//	$note .= $user->user_login;

							$this->db->insert( $this->affiliaterecords, array(
								'user_id'       => $user_id,
								'period'        => $period,
								'affiliatearea' => 'debit',
								'area_id'       => false,
								'affiliatenote' => $note,
								'amount'        => $debit,
								'meta'          => maybe_serialize( $meta ),
								'timestamp'     => current_time( 'mysql', true )
							) );

							echo '<div id="message" class="updated fade"><p>' . __( 'Debit has been assigned correctly.', 'affiliate' ) . '</p></div>';
						}
						break;
					case 'usercredit':
						check_admin_referer( 'credit-user-' . $user_id );
						$period = addslashes( $_POST['creditperiod'] );
						$credit = abs( floatval( $_POST['creditvalue'] ) );
						$note   = esc_attr( $_POST['creditnote'] );
						//echo "note[". $note ."]<br />";
						//die();

						$sql         = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, credits, lastupdated) VALUES (%d, %s, %f, now()) ON DUPLICATE KEY UPDATE credits = credits + %f", $user_id, $period, $credit, $credit );
						$queryresult = $this->db->query( $sql );
						if ( $queryresult ) {
							$user = wp_get_current_user();
							$meta = array(
								'current_user_id' => $user->ID,
								'LOCAL_URL'       => ( is_ssl() ? 'https://' : 'http://' ) . esc_attr( $_SERVER['HTTP_HOST'] ) . esc_attr( $_SERVER['REQUEST_URI'] ),
								'IP'              => ( isset( $_SERVER['HTTP_X_FORWARD_FOR'] ) ) ? esc_attr( $_SERVER['HTTP_X_FORWARD_FOR'] ) : esc_attr( $_SERVER['REMOTE_ADDR'] ),
								//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
							);
							//$note .= ' '. __('by', 'affiliate') .': ';
							//if ( !empty( $user->display_name ) )
							//	$note .= $user->display_name .' ('. $user->user_login.')';
							//else
							//	$note .= $user->user_login;

							$this->db->insert( $this->affiliaterecords, array(
								'user_id'       => $user_id,
								'period'        => $period,
								'affiliatearea' => 'credit',
								'area_id'       => false,
								'affiliatenote' => $note,
								'amount'        => $credit,
								'meta'          => maybe_serialize( $meta ),
								'timestamp'     => current_time( 'mysql', true )
							) );

							echo '<div id="message" class="updated fade"><p>' . __( 'Credit has been assigned correctly.', 'affiliate' ) . '</p></div>';
						}
						break;
					case 'userpayment':
						check_admin_referer( 'pay-user-' . $user_id );
						$period  = addslashes( $_POST['payperiod'] );
						$payment = abs( floatval( $_POST['payvalue'] ) );
						$note    = esc_attr( $_POST['paynote'] );

						$sql         = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, payments, lastupdated) VALUES (%d, %s, %f, now()) ON DUPLICATE KEY UPDATE payments = payments + %f", $user_id, $period, $payment, $payment );
						$queryresult = $this->db->query( $sql );
						if ( $queryresult ) {
							$user = wp_get_current_user();
							$meta = array(
								'current_user_id' => $user->ID,
								'LOCAL_URL'       => ( is_ssl() ? 'https://' : 'http://' ) . esc_attr( $_SERVER['HTTP_HOST'] ) . esc_attr( $_SERVER['REQUEST_URI'] ),
								'IP'              => ( isset( $_SERVER['HTTP_X_FORWARD_FOR'] ) ) ? esc_attr( $_SERVER['HTTP_X_FORWARD_FOR'] ) : esc_attr( $_SERVER['REMOTE_ADDR'] ),
								//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
							);
							//$note .= ' '. __('by', 'affiliate') .': ';
							//if ( !empty( $user->display_name ) )
							//	$note .= $user->display_name .' ('. $user->user_login.')';
							//else
							//	$note .= $user->user_login;

							$this->db->insert( $this->affiliaterecords, array(
								'user_id'       => $user_id,
								'period'        => $period,
								'affiliatearea' => 'payment',
								'area_id'       => false,
								'affiliatenote' => $note,
								'amount'        => $payment,
								'meta'          => maybe_serialize( $meta ),
								'timestamp'     => current_time( 'mysql', true )
							) );

							echo '<div id="message" class="updated fade"><p>' . __( 'Payment has been assigned correctly.', 'affiliate' ) . '</p></div>';
						}
						break;
					case 'findusers':
						check_admin_referer( 'find-user' );
						$userlist = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->db->users} WHERE user_login = %s", addslashes( $_POST['username'] ) ) );
						//print_r($userlist);
						break;
					case 'approveuser':
						check_admin_referer( 'approve-user-' . $user_id );
						if ( $_POST['userapproved'] == 'yes' ) {
							update_user_meta( $user_id, 'affiliateapproved', 'yes' );
						} else {
							update_user_meta( $user_id, 'affiliateapproved', 'no' );
						}
						break;
				}

			}

			$user = get_userdata( $user_id );

			echo "<strong>" . __( 'Details for user : ', 'affiliate' ) . $user->user_login . " ( " . get_user_meta( $user_id, 'affiliate_paypal', true ) . " )" . "</strong>";
			// Get the affiliate website listing
			$referrer = get_user_meta( $user_id, 'affiliate_referrer', true );
                        $schema = is_ssl() ? 'https://' : 'http://';
			if ( ! empty( $referrer ) ) {
                                echo " " . __( 'linked to ', 'affiliate' ) . "<a href='{$schema}{$referrer}'>" . $referrer . "</a>";
			}


			if ( ( isset( $_GET['subpage'] ) ) && ( $_GET['subpage'] == "details" ) ) {
				echo '<br /><a href="' . esc_url( add_query_arg( 'subpage', 'summary' ) ) . '">' . __( '&larr; Return to Affiliate Period Summary', 'affiliate' ) . '</a>';
			}

			echo "<br/>";
			echo "<div id='clickscolumn' style='width: 48%; margin-right: 10px; margin-top: 20px; min-height: 400px; float: left;'>";

			//echo "_GET<pre>"; print_r($_GET); echo "</pre>";
			if ( ( isset( $_GET['subpage'] ) ) && ( $_GET['subpage'] == "summary" ) ) {
				$this->show_users_period_summary_table( $user_id );
			} else if ( ( isset( $_GET['subpage'] ) ) && ( $_GET['subpage'] == "details" ) ) {
				$period = '';
				if ( isset( $_GET['period'] ) ) {
					$period = esc_attr( $_GET['period'] );
				}
				if ( ! empty( $period ) ) {
					$period = date( 'Ym' );
				}
				$this->show_users_period_details_table( $user_id, $period );
			}
			echo "</div>";


			echo "<div id='referrerscolumn' style='width: 48%; min-height: 400px; margin-top: 20px; padding: 10px; background: #fff; float: left;'>";

			echo "<div id='affdashgraph' style='height: 300px; width: 100%; background-color: #fff; margin-left: 0px; margin-right: 10px; margin-bottom: 20px;'>" . "</div>";


			// Enable / disbale affiliate
			if ( aff_get_option( 'affiliateenableapproval', 'no' ) == 'yes' ) {
				echo "<form action='' method='post'>";
				wp_nonce_field( 'approve-user-' . $user_id );
				echo '<input type="hidden" name="action" value="approveuser" />';
				echo '<input type="hidden" name="userid" id="approveuserid" value="' . esc_attr( $user_id ) . '" />';
				echo "<table class='widefat'>";

				echo "<thead>";
				echo "<tr>";
				echo "<th scope='col'>";
				echo __( 'Approve user account', 'affiliate' );
				echo "</th>";
				echo "<th scope='col' style='width: 3em;'>";
				echo '&nbsp;';
				echo "</th>";
				echo "</tr>";
				echo "</thead>";

				echo "<tbody>";
				$app = get_user_meta( $user_id, 'affiliateapproved', true );
				if ( empty( $app ) ) {
					$app = 'no';
				}
				echo "<tr class='' style=''>";
				echo "<td style='padding: 5px;'>";
				echo __( 'User is ', 'affiliate' );
				echo '<select name="userapproved" id="userapproved">';
				echo '<option value="no" ' . selected( $app, 'no', false ) . '>' . __( 'not approved', 'affiliate' ) . "</option>";
				echo '<option value="yes" ' . selected( $app, 'yes', false ) . '>' . __( 'approved', 'affiliate' ) . "</option>";
				echo '</select>&nbsp;';
				echo __( ' to receive affiliate payments.', 'affiliate' );
				echo "</td>";
				echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
				echo "<input type='submit' name='approveaccount' value='" . __( 'Update', 'affiliate' ) . "' class='button-primary' />";
				echo "</td>";
				echo "</tr>";

				echo "</tbody>";
				echo "</table>";
				echo "</form>";

				echo "<br/>";
			}

			// Add credit and debits table and form

			$period_options = '';
			$startat        = strtotime( date( "Y-m-15" ) );
			for ( $n = 0; $n <= 24; $n ++ ) {
				$rdate  = strtotime( "-$n month", $startat );
				$period = date( 'Ym', $rdate );
				$period_options .= '<option value="' . $period . '">' . date_i18n( 'M Y', $rdate ) . '</option>';
			}

			echo "<form action='' method='post'>";
			wp_nonce_field( 'credit-user-' . $user_id );
			echo '<input type="hidden" name="action" value="usercredit" />';
			echo '<input type="hidden" name="userid" id="credituserid" value="' . esc_attr( $user_id ) . '" />';
			echo "<table class='widefat'>";

			echo "<thead>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo __( 'Credit user account', 'affiliate' );
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo '&nbsp;';
			echo "</th>";
			echo "</tr>";
			echo "</thead>";

			echo "<tbody>";

			echo "<tr class='' style=''>";
			echo "<td style='padding: 5px;'>";
			echo '<label for="creditperiod">' . __( 'Period : ', 'affiliate' ) . '</label>';
			echo '<select name="creditperiod" id="creditperiod">';
			echo $period_options;
			echo '</select>&nbsp;';

			echo '<label for="creditvalue">' . __( 'Value : ', 'affiliate' ) . '</label>';
			echo '<input type="text" id="creditvalue" name="creditvalue" value="" style="width: 10%;"/>&nbsp;';

			echo '<label for="creditnote">' . __( 'Note : ', 'affiliate' ) . '</label>';
			echo '<input type="text" id="creditnote" name="creditnote" value="" style="width: 45%;" placeholder="' . __( 'Credit for...', 'affiliate' ) . '"/>';

			echo "</td>";
			echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
			echo "<input type='submit' name='creditaccount' value='" . __( 'Add Credit', 'affiliate' ) . "' class='button-primary' />";
			echo "</td>";
			echo "</tr>";


			echo "</tbody>";
			echo "</table>";
			echo "</form>";

			echo "<br/>";

			echo "<form action='' method='post'>";
			wp_nonce_field( 'debit-user-' . $user_id );
			echo '<input type="hidden" name="action" value="userdebit" />';
			echo '<input type="hidden" name="userid" id="debituserid" value="' . esc_attr( $user_id ) . '" />';
			echo "<table class='widefat'>";

			echo "<thead>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo __( 'Debit user account', 'affiliate' );
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo '&nbsp;';
			echo "</th>";
			echo "</tr>";
			echo "</thead>";

			echo "<tbody>";

			echo "<tr class='' style=''>";
			echo "<td style='padding: 5px;'>";
			echo '<label for="debitperiod">' . __( 'Period : ', 'affiliate' ) . '</label>';
			echo '<select name="debitperiod" id="debitperiod">';
			echo $period_options;
			echo '</select>&nbsp;';
			echo '<label for="debitvalue">' . __( 'Value : ', 'affiliate' ) . '</label>';
			echo '<input type="text" id="debitvalue" name="debitvalue" value="" style="width: 10%;"/>';
			echo '  <label for="debitnote">' . __( 'Note : ', 'affiliate' ) . '</label>';
			echo '<input type="text" id="debitnote" name="debitnote" value="" style="width: 45%;" placeholder="' . __( 'Debit for...', 'affiliate' ) . '"/>';
			echo "</td>";
			echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
			echo "<input type='submit' name='debitaccount' value='" . __( 'Add Debit', 'affiliate' ) . "' class='button-primary' />";
			echo "</td>";
			echo "</tr>";

			echo "</tbody>";
			echo "</table>";
			echo "</form>";

			echo "<br/>";


			echo "<form action='' method='post'>";
			wp_nonce_field( 'pay-user-' . $user_id );
			echo '<input type="hidden" name="action" value="userpayment" />';
			echo '<input type="hidden" name="userid" id="payuserid" value="' . esc_attr( $user_id ) . '" />';
			echo "<table class='widefat'>";

			echo "<thead>";
			echo "<tr>";
			echo "<th scope='col'>";
			echo __( 'Set Payment on account', 'affiliate' );
			echo "</th>";
			echo "<th scope='col' style='width: 3em;'>";
			echo '&nbsp;';
			echo "</th>";
			echo "</tr>";
			echo "</thead>";

			echo "<tbody>";

			echo "<tr class='' style=''>";
			echo "<td style='padding: 5px;'>";
			echo '<label for="payperiod">' . __( 'Period : ', 'affiliate' ) . '</label>';
			echo '<select name="payperiod" id="payperiod">';
			echo $period_options;
			echo '</select>&nbsp;';

			echo '<label for="payvalue">' . __( 'Value : ', 'affiliate' ) . '</label>';
			echo '<input type="text" id="payvalue" name="payvalue" value="" style="width: 10%;" />&nbsp;';

			echo '<label for="paynote">' . __( 'Note : ', 'affiliate' ) . '</label>';
			echo '<input type="text" id="paynote" name="paynote" value="" style="width: 45%;" placeholder="' . __( 'Payment for...', 'affiliate' ) . '"/>';

			echo "</td>";
			echo "<td style='width: 3em; padding: 5px; text-align: right;'>";
			echo "<input type='submit' name='payaccount' value='" . __( 'Add Payment', 'affiliate' ) . "' class='button-primary' />";
			echo "</td>";
			echo "</tr>";

			echo "</tbody>";
			echo "</table>";
			echo "</form>";

			echo "</div>";

			echo "<div style='clear: both;'></div>";
		} else {
			// Get the listing page
			require_once( 'affiliates-list-table.php' );

			$aff_list_table = new Affiliates_List_Table();

			$pagenum = $aff_list_table->get_pagenum();
			$aff_list_table->prepare_items();

			$total_pages = $aff_list_table->get_pagination_arg( 'total_pages' );
			if ( $pagenum > $total_pages && $total_pages > 0 ) {
				$pagenum = $total_pages;
			}

			$page = addslashes( $_GET['page'] );

			$aff_list_table->views(); ?>

			<form action="" method="get">
				<?php
				echo '<input type="hidden" name="page" value="' . esc_attr( $page ) . '" />';

				$aff_list_table->search_box( __( 'Search Users' ), 'user' );
				?>

				<?php $aff_list_table->display(); ?>
			</form>
			<?php
		}


	}

	function show_affiliates_panel_menu() {
		return;

		global $page, $subpage;

		$tab = $page;
		if ( empty( $tab ) ) {
			$tab = 'affiliatesadmin';
		}

		$menus                            = array();
		$menus['affiliatesadmin']         = __( 'Affiliate reports', 'affiliate' );
		$menus['affiliatesadminmanage']   = __( 'Manage affiliates', 'affiliate' );
		$menus['affiliatesadminsettings'] = __( 'Affiliate settings', 'affiliate' );
		$menus['affiliatesadminaddons']   = __( 'Manage add-ons', 'affiliate' );

		$menus = apply_filters( 'affiliate_menus', $menus );
		?>

		<h3 class="nav-tab-wrapper">
			<?php
			foreach ( $menus as $key => $menu ) {
				?>
				<a class="nav-tab<?php if ( $tab == $key ) {
					echo ' nav-tab-active';
				} ?>" href="admin.php?page=<?php echo $key; ?>"><?php echo $menu; ?></a>
				<?php
			}

			?>
		</h3>

		<?php

	}

	function handle_affiliates_panel() {

		global $page;

		wp_reset_vars( array( 'page' ) );

		$page = addslashes( $_GET['page'] );

		echo "<div class='wrap nosubsub'>";
		echo "<h2>" . __( 'Affiliate System Administration', 'affiliate' ) . "</h2>";

		$this->show_affiliates_panel_menu();

		if ( ! empty( $page ) && $page != 'affiliatesadmin' ) {
			switch ( $page ) {
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
			if ( isset( $_GET['action'] ) ) {

				switch ( addslashes( $_GET['action'] ) ) {

					case 'allaffiliates':    // Bulk operations
						if ( isset( $_POST['allaction_markaspaid'] ) ) {
							check_admin_referer( 'allaffiliateactions' );

							if ( ! empty( $_POST['allpayments'] ) ) {
								foreach ( $_POST['allpayments'] as $affiliate ) {
									$affdetails = explode( '-', $affiliate );
									if ( count( $affdetails ) == 2 ) {
										$sql_str = $this->db->prepare( "SELECT * FROM " . $this->affiliatedata . " WHERE user_id = %d AND period = %s", $affdetails[0], $affdetails[1] );
										//echo "sql_str[". $sql_str ."]<br />";
										$record = $this->db->get_row( $sql_str );
										if ( $record ) {
											echo "record<pre>";
											print_r( $record );
											//die();

											//if(defined('AFFILIATE_ORIGINAL_PAYMENT_CALCULATION') && AFFILIATE_ORIGINAL_PAYMENT_CALCULATION == true) {
											$sql_str = $this->db->prepare( "UPDATE " . $this->affiliatedata . " SET payments = %f, lastupdated = %s WHERE user_id = %d AND period = %s", $record->payments + ( $record->credits - $record->debits ), current_time( 'mysql', true ), $affdetails[0], $affdetails[1] );


											$user = wp_get_current_user();
											$meta = array(
												'current_user_id' => $user->ID,
												'LOCAL_URL'       => ( is_ssl() ? 'https://' : 'http://' ) . esc_attr( $_SERVER['HTTP_HOST'] ) . esc_attr( $_SERVER['REQUEST_URI'] ),
												'IP'              => ( isset( $_SERVER['HTTP_X_FORWARD_FOR'] ) ) ? esc_attr( $_SERVER['HTTP_X_FORWARD_FOR'] ) : esc_attr( $_SERVER['REMOTE_ADDR'] ),
												//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
											);

											$this->db->insert( $this->affiliaterecords, array(
												'user_id'       => $affdetails[0],
												'period'        => $affdetails[1],
												'affiliatearea' => 'payment',
												'area_id'       => false,
												'affiliatenote' => $note,
												'amount'        => $record->credits - $record->debits,
												'meta'          => maybe_serialize( $meta ),
												'timestamp'     => current_time( 'mysql', true )
											) );


											//echo "#1 sql_str[". $sql_str ."]<br />";
											//die();
											$affected = $this->db->query( $sql_str );
											//} else {
											//	$sql_str = $this->db->prepare("UPDATE " . $this->affiliatedata . " SET payments = %f, lastupdated = %s WHERE user_id = %d AND period = %s", $record->credits - $record->debits, current_time('mysql', true), $affdetails[0], $affdetails[1]);
											//	echo "#2 sql_str[". $sql_str ."]<br />";
											//	die();

											//	$affected = $this->db->query( $sql_str );
											//}
											if ( $affected ) {
												echo '<div id="message" class="updated fade"><p>' . __( 'Payment has been assigned correctly.', 'affiliate' ) . '</p></div>';
											}
										}
									}
								}
								echo '<div id="message" class="updated fade"><p>' . __( 'Payments has been assigned correctly.', 'affiliate' ) . '</p></div>';
							}

							// Mark as paid
						}
						break;

					case 'makepayment':        // Mark a payment
						$affiliate = addslashes( $_GET['id'] );
						if ( isset( $affiliate ) ) {
							$affdetails = explode( '-', $affiliate );

							if ( count( $affdetails ) == 2 ) {
								$sql_str = $this->db->prepare( "SELECT * FROM " . $this->affiliatedata . " WHERE user_id = %d AND period = %s", $affdetails[0], $affdetails[1] );
								//echo "sql_str[". $sql_str ."]<br />";
								$record = $this->db->get_row( $sql_str );
								if ( $record ) {
									//echo "record<pre>"; print_r($record);

									$balance = ( $record->credits - $record->debits ) - $record->payments;
									//echo "balance[". $balance ."]<br />";

									if ( $balance > 0 ) {

										$sql_str = $this->db->prepare( "UPDATE " . $this->affiliatedata . " SET payments = %f, lastupdated = %s WHERE user_id = %d AND period = %s", $balance + $record->payments, current_time( 'mysql', true ), $affdetails[0], $affdetails[1] );
										//echo "#1 sql_str[". $sql_str ."]<br />";
										$affected = $this->db->query( $sql_str );
										if ( $affected ) {
											echo '<div id="message" class="updated fade"><p>' . __( 'Payment has been assigned correctly.', 'affiliate' ) . '</p></div>';

											$user = wp_get_current_user();
											$meta = array(
												'current_user_id' => $user->ID,
												'LOCAL_URL'       => ( is_ssl() ? 'https://' : 'http://' ) . esc_attr( $_SERVER['HTTP_HOST'] ) . esc_attr( $_SERVER['REQUEST_URI'] ),
												'IP'              => ( isset( $_SERVER['HTTP_X_FORWARD_FOR'] ) ) ? esc_attr( $_SERVER['HTTP_X_FORWARD_FOR'] ) : esc_attr( $_SERVER['REMOTE_ADDR'] ),
											);

											$this->db->insert( $this->affiliaterecords, array(
												'user_id'       => $affdetails[0],
												'period'        => $affdetails[1],
												'affiliatearea' => 'payment',
												'area_id'       => false,
												'affiliatenote' => $note,
												'amount'        => $balance,
												'meta'          => maybe_serialize( $meta ),
												'timestamp'     => current_time( 'mysql', true )
											) );
										}
									}
								}
							}
						}
						break;

				}

			}


			$headings = aff_get_option( 'affiliateheadings', array(
				__( 'Unique Clicks', 'affiliate' ),
				__( 'Sign ups', 'affiliate' ),
				__( 'Paid members', 'affiliate' )
			) );

			$headings = array_merge( $headings, array(
				__( 'Credits', 'affiliate' ),
				__( 'Debits', 'affiliate' ),
				__( 'Payments', 'affiliate' ),
				__( 'Balance', 'affiliate' )
			) );

			$newcolumns = apply_filters( 'affiliate_column_names', $headings );
			if ( count( $newcolumns ) == 7 ) {
				// We must have 6 columns
				$columns = $newcolumns;
			}

			if ( isset( $_REQUEST['reportperiod'] ) ) {
				$reportperiod = addslashes( $_REQUEST['reportperiod'] );
			} else {
				$reportperiod = date( 'Ym' );
			}

			$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE period = %s ORDER BY credits DESC", $reportperiod ) );

			echo '<form id="form-affiliate-list" action="?page=' . $page . '&amp;action=allaffiliates" method="post">';
			echo '<input type="hidden" name="action" value="allaffiliates" />';
			echo '<input type="hidden" name="page" value="' . esc_attr( $page ) . '" />';

			echo '<div class="tablenav">';

			echo '<div class="alignleft">';

			echo __( 'Show report for', 'affiliate' ) . '&nbsp;';
			echo '<select name="reportperiod" id="reportperiod">';
			$startat = strtotime( date( "Y-m-15" ) );
			for ( $n = 0; $n <= 24; $n ++ ) {
				$rdate  = strtotime( "-$n month", $startat );
				$period = date( 'Ym', $rdate );
				echo '<option value="' . $period . '"';
				if ( $reportperiod == $period ) {
					echo ' selected="selected"';
				}
				echo '>' . date( 'M Y', $rdate ) . '</option>';
			}
			echo '</select>&nbsp;';
			echo '<input type="submit" value="' . __( 'Refresh', 'affiliate' ) . '" name="allaction_refresh" class="button-secondary" />';

			echo '<br class="clear" />';
			echo '</div>';

			echo '<div class="alignright">';

			echo '<input type="submit" value="' . __( 'Export Payments', 'affiliate' ) . '" name="allaction_exportpayments" class="button-secondary delete" />&nbsp;&nbsp;';
			echo '<input type="submit" value="' . __( 'Pay Balances', 'affiliate' ) . '" name="allaction_markaspaid" class="button-secondary" />';
			wp_nonce_field( 'allaffiliateactions' );
			echo '<br class="clear" />';
			echo '</div>';

			echo '</div>';


			echo '<table cellpadding="3" cellspacing="3" class="widefat" style="width: 100%;">';
			echo '<thead>';
			echo '<tr>';

			echo '<th scope="col" class="check-column"><input type="checkbox" label="check all" /></th>';

			echo '<th scope="col">';
			echo __( 'Username', 'affiliate' );
			echo '</th>';

			if ( aff_get_option( 'affiliateenableapproval', 'no' ) == 'yes' ) {
				echo '<th scope="col">';
				echo __( 'Approved', 'affiliate' );
				echo '</th>';
			}

			foreach ( $columns as $column ) {
				echo '<th scope="col" class="num">';
				echo $column;
				echo '</th>';
			}

			echo '</tr>';
			echo '</thead>';

			echo '<tbody id="the-list">';
			if ( $results ) {
				foreach ( $results as $result ) {

					$user = get_userdata( $result->user_id );
					if ( empty( $user ) ) {
						continue;
					}

					echo "<tr class=''>";

					// Check boxes
					echo '<th scope="row" class="check-column">';

					if ( $this->approved_affiliate( $result->user_id ) ) {
						echo '<input type="checkbox" id="payment-' . $result->user_id . "-" . $result->period . '" name="allpayments[]" value="' . esc_attr( $result->user_id . "-" . $result->period ) . '" />';
					}

					echo '</th>';

					echo '<td valign="top">';

					echo $user->user_login;
					echo " ( " . get_user_meta( $result->user_id, 'affiliate_paypal', true ) . " )";

					// Get the affiliate website listing
					$referrer = get_user_meta( $result->user_id, 'affiliate_referrer', true );
                                        $schema = is_ssl() ? 'https://' : 'http://';
					if ( ! empty( $referrer ) ) {
						echo " " . __( 'linked to ', 'affiliate' ) . "<a href='{$schema}{$referrer}'>" . $referrer . "</a>";
					}

					// Quick links
					$actions = array();
					if ( $this->approved_affiliate( $result->user_id ) ) {
						$actions[] = "<a href='?page=$page&amp;action=makepayment&amp;id=" . $result->user_id . "-" . $result->period . "&amp;reportperiod=" . $reportperiod . "' class='edit'>" . __( 'Pay Balance', 'affiliate' ) . "</a>";
					}
					$actions[] = "<a href='?page=affiliatesadminmanage&amp;subpage=summary&amp;id=" . $result->user_id . "' class='edit'>" . __( 'Manage Affiliate', 'affiliate' ) . "</a>";

					/*
					if ((intval($result->uniques)) || (intval($result->signups)) || intval($result->completes)) {
						if(is_network_admin()) {
							$actions[] = '<a href="' . network_admin_url('user-edit.php?user_id='. $result->user_id .'&period='. $reportperiod .'#affiliate-details') .'">'. __('details', 'affiliate') .'</a>';
						} else {
							$actions[] = '<a href="'. admin_url('user-edit.php?user_id='. $result->user_id .'&period='.$reportperiod .'#affiliate-details') .'">'. __('details', 'affiliate') .'</a>';
						}
					}
*/
					echo '<div class="row-actions">';
					echo implode( ' | ', $actions );
					echo '</div>';

					echo '</td>';


					if ( aff_get_option( 'affiliateenableapproval', 'no' ) == 'yes' ) {
						echo '<td valign="top" class="">';
						if ( $this->approved_affiliate( $result->user_id ) ) {
							echo __( 'Yes', 'affiliate' );
						} else {
							echo __( 'No', 'affiliate' );
						}
						echo '</td>';
					}

					echo '<td valign="top" class="num">';
					echo intval( $result->uniques );
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo intval( $result->signups );
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo intval( $result->completes );
					echo '</div>';

					echo '</td>';

					echo '<td valign="top" class="num">';
					echo number_format( $result->credits, 2 );
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo number_format( $result->debits * - 1, 2 );
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo number_format( $result->payments * - 1, 2 );
					echo '</td>';

					echo '<td valign="top" class="num">';
					echo number_format( ( $result->credits - $result->debits ) - $result->payments, 2 );
					echo '</td>';

					echo '</tr>';
				}
			} else {

				echo "<tr class=''>";

				echo '<td colspan="8" valign="top">';
				echo __( 'There are no results for the selected month.', 'affiliate' );
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '<tfoot>';
			echo '<tr>';
			echo '<th scope="col" class="check-column"><input type="checkbox" label="check all" /></th>';
			echo '<th scope="col">';
			echo __( 'Username', 'affiliate' );
			echo '</th>';

			if ( aff_get_option( 'affiliateenableapproval', 'no' ) == 'yes' ) {
				echo '<th scope="col">';
				echo __( 'Approved', 'affiliate' );
				echo '</th>';
			}

			reset( $columns );
			foreach ( $columns as $column ) {
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
		$columns['referred'] = __( 'Referred by', 'affiliate' );

		return $columns;
	}

	function show_user_affiliate_column( $content, $column_name, $user_id ) {

		if ( $column_name == 'referred' ) {

			$affid = get_user_meta( $user_id, 'affiliate_referred_by', true );

			if ( ! empty( $affid ) ) {
				// was referred so get the referrers details
				$referrer = new WP_User( $affid );

				if ( is_network_admin() ) {
					$content .= "<a href='" . network_admin_url( 'users.php?s=' ) . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
				} else {
					$content .= "<a href='" . admin_url( 'users.php?s=' ) . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
				}

			}

		}

		return $content;

	}

	function add_affiliate_column( $columns ) {

		$columns['referred'] = __( 'Referred by', 'affiliate' );

		return $columns;

	}

	function show_affiliate_column( $column_name, $blog_id ) {

		if ( $column_name == 'referred' ) {
			$affid = get_blog_option( $blog_id, 'affiliate_referrer', false );
			if ( empty( $affid ) ) {
				$affid = get_blog_option( $blog_id, 'affiliate_referred_by', false );
			}
			if ( ! empty( $affid ) ) {
				// was referred so get the referrers details
				$referrer = get_user_by( 'id', $affid );
				if ( ! empty( $referrer ) ) {
					if ( is_network_admin() ) {
						echo "<a href='" . network_admin_url( 'users.php?s=' ) . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
					} else {
						echo "<a href='" . admin_url( 'users.php?s=' ) . $referrer->user_login . "'>" . $referrer->user_login . "</a>";
					}
				}
			}
		}
	}

	// Plugins interface
	function handle_addons_panel_updates() {
		global $action, $page;

		if ( isset( $_GET['doaction'] ) || isset( $_GET['doaction2'] ) ) {
			if ( addslashes( $_GET['action'] ) == 'toggle' || addslashes( $_GET['action2'] ) == 'toggle' ) {
				$action = 'bulk-toggle';
			}
		}

		$active = aff_get_option( 'affiliate_activated_addons', array() );

		switch ( addslashes( $action ) ) {

			case 'deactivate':
				$key = addslashes( $_GET['addon'] );
				if ( ! empty( $key ) ) {
					check_admin_referer( 'toggle-addon-' . $key );

					$found = array_search( $key, $active );
					if ( $found !== false ) {
						unset( $active[ $found ] );
						aff_update_option( 'affiliate_activated_addons', array_unique( $active ) );

						return 5;
					} else {
						return 6;
					}
				}
				break;

			case 'activate':
				$key = addslashes( $_GET['addon'] );
				if ( ! empty( $key ) ) {
					check_admin_referer( 'toggle-addon-' . $key );

					if ( ! in_array( $key, $active ) ) {
						$active[] = $key;
						aff_update_option( 'affiliate_activated_addons', array_unique( $active ) );

						return 3;
					} else {
						return 4;
					}
				}
				break;

			case 'bulk-toggle':
				check_admin_referer( 'bulk-addon' );
				if ( is_array( $_GET['plugincheck'] ) ) {
					foreach ( $_GET['plugincheck'] AS $key ) {
						$found = array_search( $key, $active );
						if ( $found !== false ) {
							unset( $active[ $found ] );
						} else {
							$active[] = $key;
						}
					}
					aff_update_option( 'affiliate_activated_addons', array_unique( $active ) );
				}

				return 7;
				break;

		}
	}

	function handle_addons_panel() {
		global $action, $page, $subpage;

		wp_reset_vars( array( 'action', 'page', 'subpage' ) );

		$messages    = array();
		$messages[1] = __( 'Addon updated.', 'affiliate' );
		$messages[2] = __( 'Addon not updated.', 'affiliate' );

		$messages[3] = __( 'Addon activated.', 'affiliate' );
		$messages[4] = __( 'Addon not activated.', 'affiliate' );

		$messages[5] = __( 'Addon deactivated.', 'affiliate' );
		$messages[6] = __( 'Addon not deactivated.', 'affiliate' );

		$messages[7] = __( 'Addon activation toggled.', 'affiliate' );

		if ( ! empty( $action ) ) {
			$msg = $this->handle_addons_panel_updates();
		}

		//$mu_plugins = get_mu_plugins();
		//echo "mu_plugins<pre>"; print_r($mu_plugins); echo "</pre>";

		//$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins');
		//echo "network active plugins<pre>"; print_r($active_sitewide_plugins); echo "</pre>";

		//$active_plugins = get_option( 'active_plugins', array());
		//echo "active plugins<pre>"; print_r($active_plugins); echo "</pre>";

		if ( ! empty( $msg ) ) {
			echo '<div id="message" class="updated fade"><p>' . $messages[ (int) $msg ] . '</p></div>';
			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] );
		}

		?>

		<form method="get"
		      action="?page=<?php echo esc_attr( $page ); ?>&amp;subpage=<?php echo esc_attr( $subpage ); ?>"
		      id="posts-filter">

			<input type='hidden' name='page' value='<?php echo esc_attr( $page ); ?>'/>
			<input type='hidden' name='subpage' value='<?php echo esc_attr( $subpage ); ?>'/>

			<div class="tablenav">

				<div class="alignleft actions">
					<select name="action">
						<option selected="selected" value=""><?php _e( 'Bulk Actions', 'affiliate' ); ?></option>
						<option value="toggle"><?php _e( 'Toggle activation', 'affiliate' ); ?></option>
					</select>
					<input type="submit" class="button-secondary action" id="doaction" name="doaction"
					       value="<?php _e( 'Apply', 'affiliate' ); ?>">

				</div>

				<div class="alignright actions"></div>

				<br class="clear">
			</div>

			<div class="clear"></div>

			<?php
			wp_original_referer_field( true, 'previous' );
			wp_nonce_field( 'bulk-addon' );

			$columns = array(
				"name"   => __( 'Addon Name', 'affiliate' ),
				"active" => __( 'Addon Status', 'affiliate' )
			);

			$columns = apply_filters( 'affiliate_plugincolumns', $columns );

			$plugins = get_affiliate_addons();

			$active = aff_get_option( 'affiliate_activated_addons', array() );

			?>

			<table cellspacing="0" class="widefat fixed">
				<thead>
				<tr>
					<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input
							type="checkbox"></th>
					<?php
					foreach ( $columns as $key => $col ) {
						?>
						<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>"
						    scope="col"><?php echo $col; ?></th>
						<?php
					}
					?>
				</tr>
				</thead>

				<tfoot>
				<tr>
					<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
					<?php
					reset( $columns );
					foreach ( $columns as $key => $col ) {
						?>
						<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>"
						    scope="col"><?php echo $col; ?></th>
						<?php
					}
					?>
				</tr>
				</tfoot>

				<tbody>
				<?php
				if ( ! empty( $plugins ) ) {

					$AFFILIATE_IS_NETWORK_ACTIVE = affiliate_is_plugin_active_for_network();

					foreach ( $plugins as $key => $plugin ) {
						$default_headers = array(
							'Name'        => 'Plugin Name',
							//'Author' 			=> 	'Author',
							'Description' => 'Description',
							'AuthorURI'   => 'Author URI',
							'Network'     => 'Network',
							'Depends'     => 'Depends',
							'Class'       => 'Class',
							'Deprecated'  => 'Deprecated',
						);

						$plugin_data = get_file_data( affiliate_dir( 'affiliateincludes/addons/' . $plugin ), $default_headers, 'plugin' );
						//echo "plugin_data<pre>"; print_r($plugin_data); echo "</pre>";

						if ( empty( $plugin_data['Name'] ) ) {
							continue;
						}

						if ( ( ! isset( $plugin_data['Network'] ) ) || ( empty( $plugin_data['Network'] ) ) || ( $plugin_data['Network'] != 'true' ) ) {
							$plugin_data['Network'] = false;
						} else if ( $plugin_data['Network'] == 'true' ) {
							$plugin_data['Network'] = true;
						}
						if ( ( $plugin_data['Network'] == true ) && ( ! is_multisite() ) && ( is_network_admin() ) ) {
							continue;
						}

						//echo "plugin_data<pre>"; print_r($plugin_data); echo "</pre>";

						$PLUGINS_CAN_BE_ACTIVE = true;
						if ( ( $plugin_data['Network'] == true ) && ( ! $AFFILIATE_IS_NETWORK_ACTIVE ) ) {
							$PLUGINS_CAN_BE_ACTIVE = false;
						}
						//echo "[". $plugin_data['Name'] ."] PLUGINS_CAN_BE_ACTIVE[". $PLUGINS_CAN_BE_ACTIVE ."]<br />";

						// Set the initial active
						$PLUGIN_INSTALLED = true;
						if ( ( ! isset( $plugin_data['Depends'] ) ) || ( empty( $plugin_data['Depends'] ) ) ) {
							$plugin_data['Network'] = array();
							if ( ( isset( $plugin_data['Class'] ) ) && ( ! empty( $plugin_data['Class'] ) ) ) {
								if ( ! class_exists( $plugin_data['Class'] ) ) {
									$PLUGIN_INSTALLED = false;
								}
							}

						} else {
							$depends = explode( ',', $plugin_data['Depends'] );
							if ( ( $depends ) && ( is_array( $depends ) ) && ( count( $depends ) ) ) {
								foreach ( $depends as $depend ) {
									//echo "depend[". $depend ."]<br />";
									if ( ( ! affiliate_is_plugin_active( $depend ) ) && ( ! affiliate_is_plugin_active_for_network( $depend ) ) ) {
										if ( ( isset( $plugin_data['Class'] ) ) || ( ! empty( $plugin_data['Class'] ) ) ) {
											//echo "class[". $plugin_data['Class'] ."]<br />";
											if ( ! class_exists( $plugin_data['Class'] ) ) {
												$PLUGIN_INSTALLED = false;
											}
										} else {
											$PLUGIN_INSTALLED = false;
										}
									}
								}
							}
						}

						//echo "[". $plugin_data['Name'] ."] PLUGIN_INSTALLED[". $PLUGIN_INSTALLED ."]<br />";

						if ( 'yes' == $plugin_data['Deprecated'] && ! $PLUGIN_INSTALLED ) {
							// We only display deprecated Add-Ons when the deprecated dependency is installed.
							continue;
						}


						?>
						<tr valign="middle" class="alternate" id="plugin-<?php echo $plugin; ?>">
							<th class="check-column" scope="row">
								<?php
								if ( ( $PLUGINS_CAN_BE_ACTIVE ) && ( $PLUGIN_INSTALLED ) ) {
									?><input type="checkbox" value="<?php echo esc_attr( $plugin ); ?>"
									         name="plugincheck[]"><?php
								}
								?>
							</th>
							<td class="column-name">
								<strong><?php echo esc_html( $plugin_data['Name'] ) ?></strong>
								<?php
								if ( ! $PLUGIN_INSTALLED ) {
									//echo ' <span>'. __('Base plugin not activate/installed', 'affiliate') .'</span>';
									echo ' -- <strong>' . __( 'plugin not installed', 'affiliate' ) . '</strong>';
								}

								if ( ! empty( $plugin_data['Description'] ) ) {
									?><br/><?php echo esc_html( $plugin_data['Description'] );
								}

								//if ($plugin_data['Network'] == true)  {
								//	echo '<br /><strong>' .__('Network only - Requires Affiliate plugin is Network Activated', 'affiliate') .'</strong>';
								//}

								if ( ( $PLUGINS_CAN_BE_ACTIVE ) && ( $PLUGIN_INSTALLED ) ) {
									$actions = array();
									if ( in_array( $plugin, $active ) ) {
										$actions['toggle'] = "<span class='edit activate'><a href='" . wp_nonce_url( "?page=" . $page . "&amp;subpage=" . $subpage . "&amp;action=deactivate&amp;addon=" . $plugin . "", 'toggle-addon-' . $plugin ) . "'>" . __( 'Deactivate', 'affiliate' ) . "</a></span>";
									} else {
										$actions['toggle'] = "<span class='edit deactivate'><a href='" . wp_nonce_url( "?page=" . $page . "&amp;subpage=" . $subpage . "&amp;action=activate&amp;addon=" . $plugin . "", 'toggle-addon-' . $plugin ) . "'>" . ( ( function_exists( 'is_network_admin' ) && is_network_admin() ) ? __( 'Network Activate', 'affiliate' ) : __( 'Activate', 'affiliate' ) ) . "</a></span>";
									}
									if ( count( $actions ) ) {
										?><br/>
										<div class="row-actions"><?php echo implode( " | ", $actions ); ?></div><?php
									}
								}
								?>
							</td>
							<td class="column-active">
								<?php
								if ( ( $PLUGINS_CAN_BE_ACTIVE ) && ( $PLUGIN_INSTALLED ) ) {
									if ( in_array( $plugin, $active ) ) {
										echo "<strong>" . __( 'Active', 'affiliate' ) . "</strong>";
									} else {
										echo __( 'Inactive', 'affiliate' );
									}
								}
								?>
							</td>
						</tr>
						<?php
					}
				} else {
					$columncount = count( $columns ) + 1;
					?>
					<tr valign="middle" class="alternate">
						<td colspan="<?php echo $columncount; ?>"
						    scope="row"><?php _e( 'No Addons where found for this install.', 'affiliate' ); ?></td>
					</tr>
					<?php
				}
				?>

				</tbody>
			</table>


			<div class="tablenav">

				<div class="alignleft actions">
					<select name="action2">
						<option selected="selected" value=""><?php _e( 'Bulk Actions', 'affiliate' ); ?></option>
						<option value="toggle"><?php _e( 'Toggle activation', 'affiliate' ); ?></option>
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

	function override_referrer_search( $search ) {

		$s = ( ! empty( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : '';

		if ( substr( $s, 0, 9 ) == 'referrer:' ) {
			// we have a referrer search so modify it
			$searchstring = explode( 'referrer:', $s );

			if ( ! empty( $searchstring[1] ) ) {
				$user = get_user_by( 'login', $searchstring[1] );
				if ( $user ) {
					$referred = $this->get_referred_by( $user->ID );
					if ( ! empty( $referred ) ) {
						$search->query_where = "WHERE 1=1 AND ( ID IN (0," . implode( ',', $referred ) . ") )";
					}

					if ( ! empty( $search->query_vars['blog_id'] ) && is_multisite() ) {
						$search->query_where .= " AND (wp_usermeta.meta_key = '" . $this->db->get_blog_prefix( $search->query_vars['blog_id'] ) . "capabilities' )";
					}
				}
			}
		}
	}

	function add_referrer_search_link( $actions, $user_object ) {

		if ( is_network_admin() ) {
			$actions['referred'] = '<a href="' . network_admin_url( 'users.php?s=referrer:' . $user_object->user_login ) . '">' . __( 'Referred', 'affiliate' ) . '</a>';
		} else {
			$actions['referred'] = '<a href="' . admin_url( 'users.php?s=referrer:' . $user_object->user_login ) . '">' . __( 'Referred', 'affiliate' ) . '</a>';
		}

		return $actions;
	}

	// Function to return a true / false if the user is an affiliate that can be paid out.
	function approved_affiliate( $user_id ) {
		if ( aff_get_option( 'affiliateenableapproval', 'no' ) == 'yes' ) {
			$app = get_user_meta( $user_id, 'affiliateapproved', true );

			if ( ! empty( $app ) && $app == 'yes' ) {
				return true;
			} else {
				// Not set or a no
				return false;
			}

		} else {
			return true;
		}
	}

	function edit_user_profile( $user = '' ) {

		if ( ! $user ) {
			global $current_user;
			$user = $current_user;
		}

		if ( isset( $_GET['period'] ) ) {
			$period = esc_attr( $_GET['period'] );
		} else {
			$period = $period = date( 'Ym' );
		}

		$area = array();
		if ( isset( $_GET['type'] ) ) {
			$type = esc_attr( $_GET['type'] );
			if ( $type == 'paid' ) {
				$area[] = 'marketpress';
			} else if ( $type == 'uniques' ) {
				$area[] = 'click';
			} else if ( $type == 'signups' ) {
				$area[] = 'signups';
			}
		}

		?><h3><?php _e( 'Affiliate Transactions', 'affiliate' ); ?></h3><?php
		$this->show_complete_records_table( $user->ID, $period, $area );
	}

	function get_complete_records( $user_id, $period = false, $area = array(), $area_id = false ) {
		$sql_str = "SELECT * FROM " . $this->affiliaterecords . " WHERE `user_id`=" . $user_id;
		if ( ! empty( $period ) ) {
			$sql_str .= " AND `period`='" . $period . "' ";
		}

		if ( ! empty( $area ) ) {
			$area_str = '';
			foreach ( $area as $area_item ) {
				if ( ! empty( $area_str ) ) {
					$area_str .= ',';
				}
				$area_str .= "'" . $area_item . "'";
			}
			if ( ! empty( $area_str ) ) {
				$sql_str .= " AND `affiliatearea` IN (" . $area_str . ") ";
			}
		}
		if ( ! empty( $area_id ) ) {
			$sql_str .= " AND `area_id`='" . $area_id . "' ";
		}

		$sql_str .= ' LIMIT 50';

		//echo "sql_str[". $sql_str ."]<br />";
		return $this->db->get_results( $sql_str );
	}

	function show_complete_records_table( $user_id, $period = false, $area = false, $area_id = false ) {
		?>
		<table id="affiliate-details" class="affiliate-details">
			<tr>
				<th class="affiliate-period"><?php _e( 'Period', 'affiliate' ); ?></th>
				<th class="affiliate-type"><?php _e( 'Type', 'affiliate' ); ?></th>
				<th class="affiliate-period"><?php _e( 'Note', 'affiliate' ); ?></th>
				<th class="affiliate-period"><?php _e( 'Amount', 'affiliate' ); ?></th>
			</tr>
			<?php

			$compete_records = $this->get_complete_records( $user_id, $period, $area, $area_id );
			if ( ( $compete_records ) && ( ! empty( $compete_records ) ) ) {
				//echo "compete_records<pre>"; print_r($compete_records); echo "</pre>";
				$amount_total = 0.00;
				foreach ( $compete_records as $compete_record ) {
					$amount_total += $compete_record->amount;
					$compete_record->meta = maybe_unserialize( $compete_record->meta );
					//echo "compete_record->meta<pre>"; print_r($compete_record->meta); echo "</pre>";

					$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
					?>
					<tr <?php echo $style; ?>>
						<td class="affiliate-period"><?php echo $compete_record->period; ?></td>
						<td class="affiliate-type"><?php
							$order_label = __( 'Order#', 'affiliate' ) . ' ' . $compete_record->area_id;
							if ( ( $compete_record->affiliatearea == 'paid:marketpress' ) && ( ! empty( $compete_record->area_id ) ) ) {
								if ( ( ! isset( $_GET['page'] ) ) || ( $_GET['page'] != 'marketpress-orders' ) ) {
									global $mp;
									if ( isset( $mp ) ) {
										echo '<a title="' . __( 'view order details', 'affiliate' ) . '" href="' .
										     admin_url( 'edit.php?post_type=product&page=marketpress-orders&order_id=' . $compete_record->area_id ) . '">' . $order_label . ' ' . $compete_record->area_id . '</a>';
									} else {
										echo $order_label;
									}
								} else {
									echo $order_label;
								}
							} else if ( $compete_record->affiliatearea == 'unique' ) {
								echo __( 'Referral Link', 'affiliate' );
							} else {

							}

							?></td>
						<td class="affiliate-note"><?php
							if ( $compete_record->affiliatearea == 'unique' ) {
								if ( ! empty( $compete_record->meta ) ) {
									//$meta = maybe_unserialize($compete_record->meta);
									//echo "meta<pre>"; print_r($meta); echo "</pre>";
									if ( isset( $compete_record->meta['REMOTE_URL'] ) ) {
										echo $compete_record->meta['REMOTE_URL'];

										if ( isset( $compete_record->meta['IP'] ) ) {
											echo ' (' . $compete_record->meta['IP'] . ')';
										}
									}
									if ( isset( $compete_record->meta['LOCAL_URL'] ) ) {
										echo ' -> ' . $compete_record->meta['LOCAL_URL'];
									}
								}
							} else if ( $compete_record->affiliatearea == 'paid:marketpress' ) {
								echo $compete_record->affiliatenote;
								//echo "compete_record->meta<pre>"; print_r($compete_record->meta); echo "</pre>";
								if ( ( isset( $compete_record->meta['order_amount'] ) ) && ( ! empty( $compete_record->meta['order_amount'] ) ) ) {
									if ( ( isset( $compete_record->meta['commision_rate'] ) ) && ( ! empty( $compete_record->meta['commision_rate'] ) ) ) {
										echo ' ($' . $compete_record->meta['order_amount'] . ' X ' . $compete_record->meta['commision_rate'];
										if ( ( isset( $compete_record->meta['commision_type'] ) ) && ( $compete_record->meta['commision_type'] == 'percentage' ) ) {
											echo '%';
										}
										echo ')';
									}
								}

							} else {
								echo $compete_record->affiliatenote;
							}
							?></td>
						<td class="affiliate-amount"><?php
							if ( $compete_record->affiliatearea == 'paid:marketpress' ) {
								echo number_format( $compete_record->amount, 2 );
							} else {
								echo '&nbsp;';
							} ?></td>
					</tr>
					<?php
				}
				if ( count( $compete_records ) > 1 ) {
					?>
					<tr>
						<td class="affiliate-period">&nbsp;</td>
						<td class="affiliate-type">&nbsp;</td>
						<td class="affiliate-note"><?php _e( 'Total', 'affiliate' ); ?></td>
						<td class="affiliate-amount"><?php echo number_format( $amount_total, 2 ); ?></td>
					</tr>
					<?php
				}
			} else {
				?>
				<tr>
				<td colspan="4"><?php _e( 'No affiliate transactions found for this user.', 'affiliate' ); ?></td>
				</tr><?php
			}
			?>
		</table>
		<style type="text/css" id="affiliate-mp-order-details-css">
			table.affiliate-details {
				width: 100%;
			}

			table.affiliate-details th {
				font-weight: bold;
			}

			table.affiliate-details td {
				font-weight: normal;
			}

			table.affiliate-details td {
				text-align: center;
			}
		</style>
		<?php
	}


	function show_users_period_summary_table( $user_id ) {
		$headings = aff_get_option( 'affiliateheadings',
			array(
				__( 'Unique Clicks', 'affiliate' ),
				__( 'Sign ups', 'affiliate' ),
				__( 'Paid members', 'affiliate' )
			)
		);

		$headings = array_merge( $headings,
			array(
				__( 'Credits', 'affiliate' ),
				__( 'Debits', 'affiliate' ),
				__( 'Payments', 'affiliate' ),
				__( 'Balance', 'affiliate' )
			)
		);

		$newcolumns = apply_filters( 'affiliate_summary_columns', $headings );
		if ( count( $newcolumns ) == 7 ) {
			// We must have 6 columns
			$columns = $newcolumns;
		}

		$results = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliatedata} WHERE user_id = %d ORDER BY period DESC", $user_id ) );

		// The table
		echo '<table width="100%" cellpadding="3" cellspacing="3" class="widefat" style="width: 100%;">';
		echo '<thead>';
		echo '<tr>';
		echo '<th scope="col">';
		echo __( 'Period', 'affiliate' );
		echo '</th>';
		foreach ( $columns as $column ) {
			echo '<th scope="col" class="num">';
			echo stripslashes( $column );
			echo '</th>';
		}
		echo '</tr>';
		echo '</thead>';

		echo '<tbody id="the-list">';

		$totalclicks    = 0.00;
		$totalsignups   = 0.00;
		$totalcompletes = 0.00;
		$totaldebits    = 0.00;
		$totalcredits   = 0.00;
		$totalpayments  = 0.00;
		$totalbalances  = 0.00;

		if ( ! empty( $results ) ) {
			$recent = array_shift( $results );
		} else {
			$recent = array();
		}

		$startat = strtotime( date( "Y-m-15" ) );
		//echo "startat[". $startat ."]<br />";

		//global $wp_locale;
		//echo "wp_locale<pre>"; print_r($wp_locale); echo "</pre>";

		for ( $n = 0; $n < 18; $n ++ ) {
			$rdate  = strtotime( "-$n month", $startat );
			$period = date( 'Ym', $rdate );
			$place  = 10 - $n;

			echo "<tr class='periods' id='period-$place'>";
			echo '<td valign="top">';
			//echo "recent<pre>"; print_r($recent); echo "</pre>";
			if ( ( ! empty( $recent->uniques ) ) || ( ! empty( $recent->signups ) ) || ( ! empty( $recent->completes ) )
			     || ( ! empty( $recent->debits ) ) || ( ! empty( $recent->credits ) ) || ( ! empty( $recent->payments ) )
			) {
				/*
				if(is_network_admin()) {
					echo '<a href="' . network_admin_url('user-edit.php?user_id='. $user_id .'&period='. $period .'#affiliate-details') .'">'. date("M", $rdate) . '<br/>' . date("Y", $rdate) .'</a>';
				} else {
					echo '<a href="'. admin_url('user-edit.php?user_id='. $user_id .'&period='. $period .'#affiliate-details') .'">'. date("M", $rdate) . '<br/>' . date("Y", $rdate) .'</a>';
				}
				*/

				$period_url = add_query_arg( array(
					'subpage' => 'details',
					'period'  => $period
				) );

				$period_url = esc_url( $period_url );
				echo '<a title="' . esc_attr( __( 'View Affiliate detail transactions for this period', 'affiliate' ) ) . '" href="' . $period_url . '">' . date_i18n( "M", $rdate, true ) . '<br/>' . date_i18n( "Y", $rdate ) . '</a>';

			} else {
				echo date_i18n( "M", $rdate, true ) . '<br/>' . date_i18n( "Y", $rdate );
			}
			echo '</td>';

			if ( ! empty( $recent ) && $recent->period == $period ) {
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
				echo number_format( $recent->credits, 2 );
				$totalcredits += (float) $recent->credits;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format( $recent->debits * - 1, 2 );
				$totaldebits += (float) $recent->debits;
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format( $recent->payments * - 1, 2 );
				$totalpayments += (float) $recent->payments;
				echo '</td>';

				echo '<td valign="top" class="num">';
				$balanace = ( $recent->credits - $recent->debits ) - $recent->payments;
				$totalbalances += $balanace;
				echo number_format( $balanace, 2 );
				echo '</td>';

				if ( ! empty( $results ) ) {
					$recent = array_shift( $results );
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
				echo number_format( 0, 2 );
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format( 0, 2 );
				echo '</td>';

				echo '<td valign="top" class="num">';
				echo number_format( 0, 2 );
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
		echo number_format( $totalcredits, 2 );
		echo '</th>';

		echo '<th scope="col" class="num">';
		echo number_format( $totaldebits * - 1, 2 );
		echo '</th>';

		echo '<th scope="col" class="num">';
		echo number_format( $totalpayments * - 1, 2 );
		echo '</th>';

		echo '<th scope="col" class="num">';
		echo number_format( $totalbalances, 2 );
		echo '</th>';
		echo '</tr>';
		echo '</tfoot>';

		echo '</table>';
	}

	function show_users_period_details_table( $user_id, $period ) {
		$columns = array(
			'date'   => __( 'Date', 'affiliate' ),
			'type'   => __( 'Type', 'affiliate' ),
			'note'   => __( 'Note', 'affiliate' ),
			'amount' => __( 'Amount', 'affiliate' )
		);

		$columns = apply_filters( 'affiliate_details_columns', $columns );

		// The table
		echo '<table width="100%" cellpadding="3" cellspacing="3" class="widefat" style="width: 100%;">';
		echo '<thead>';
		echo '<tr>';
		foreach ( $columns as $key => $column ) {
			echo '<th scope="col" class="column-' . $key . '">';
			echo stripslashes( $column );
			echo '</th>';
		}
		echo '</tr>';
		echo '</thead>';

		$amountTotal = 0.00;

		echo '<tbody id="the-list">';
		$records = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->affiliaterecords} WHERE user_id = %d ORDER BY timestamp DESC", $user_id ) );
		//echo "records<pre>"; print_r($records); echo "</pre>";
		if ( ( $records ) && ( count( $records ) ) ) {
			foreach ( $records as $record ) {

				if ( ! empty( $record->meta ) ) {
					$record->meta = maybe_unserialize( $record->meta );
				}
				//echo "record<pre>"; print_r($record); echo "</pre>";

				echo '<tr>';
				foreach ( $columns as $key => $column ) {
					switch ( $key ) {
						case 'date':
							echo '<td>' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record->timestamp ) + get_option( 'gmt_offset' ) * 3600, false ) . '</td>';
							break;

						case 'type':
							echo '<td>';

							if ( ( $record->affiliatearea == 'paid:marketpress' ) && ( ! empty( $record->area_id ) ) ) {
								_e( 'Paid', 'affiliate' );
							} else if ( substr( $record->affiliatearea, 0, strlen( 'paid:' ) ) == 'paid:' ) {
								_e( 'Paid', 'affiliate' );
							} else if ( substr( $record->affiliatearea, 0, strlen( 'unique:' ) ) == 'unique:' ) {
								_e( 'Unique', 'affiliate' );
							} else if ( substr( $record->affiliatearea, 0, strlen( 'signup:' ) ) == 'signup:' ) {
								//if ($record->affiliatearea == "signup:user")
								//	_e('Signup User', 'affiliate');
								//else if ($record->affiliatearea == "signup:blog")
								//	_e('Signup Blog', 'affiliate');
								//else
								_e( 'Signup', 'affiliate' );
							} else {
								echo ucwords( $record->affiliatearea );
							}

							echo '</td>';
							break;

						case 'note':
							echo '<td>';

							if ( ! empty( $record->affiliatenote ) ) {
								if ( ( ( $record->affiliatearea == 'paid:marketpress' ) || ( $record->affiliatearea == 'marketpress' ) ) && ( ! empty( $record->area_id ) ) ) {
									_e( 'MarketPress', 'affiliate' );
									//echo "meta<pre>"; print_r(unserialize($record->meta)); echo "</pre>";
									global $mp;
									if ( ( isset( $mp ) ) && ( current_user_can( 'edit_others_posts' ) ) ) {
										if ( isset( $record->meta['blog_id'] ) ) {
											$order_href = get_admin_url( $record->meta['blog_id'], 'edit.php?post_type=product&page=marketpress-orders&order_id=' . $record->area_id );
										} else {
											$order_href = admin_url( 'edit.php?post_type=product&page=marketpress-orders&order_id=' . $record->area_id );
										}
										echo ' <a title="' . __( 'view order details', 'affiliate' ) . '" href="' . $order_href . '">' . __( 'Order#', 'affiliate' ) . ' ' . $record->area_id . '</a>';
									} else {
										echo ' ' . __( 'Order#:', 'affiliate' ) . ' ' . $record->area_id;
									}
								} else if ( ( $record->affiliatearea == 'signup:user' ) && ( ! empty( $record->area_id ) ) ) {
									_e( 'User:', 'affiliate' );
									$user = new WP_User( $record->area_id );
									if ( ( $user ) && ( intval( $user->ID ) === intval( $record->area_id ) ) ) {
										//echo ' '.$record->area_id;
										$display_name = $user->display_name;
										if ( current_user_can( 'edit_users' ) ) {
											if ( is_network_admin() ) {
												echo ' <a href="' . network_admin_url( 'user-edit.php?user_id=' . $user->ID ) . '">' . $display_name . '</a>';
											} else {
												echo ' <a title="' . __( 'View User', 'affiliate' ) . '" href="' . admin_url( 'user-edit.php?user_id=' . $user_ID ) . '">' . $display_name . '</a>';
											}
										} else {
											echo ' ' . $display_name;
										}
									} else {
										echo ' ' . __( 'user id', 'affiliate' ) . ' ' . $record->area_id;
									}
								} else if ( ( $record->affiliatearea == 'signup:blog' ) && ( ! empty( $record->area_id ) ) ) {
									_e( 'Blog:', 'affiliate' );
									$blog_details = get_blog_details( $record->area_id );
									if ( $blog_details ) {
										//echo "blog_details<pre>"; print_r($blog_details); echo "</pre>";
										//$display_name = $user->display_name;
										echo ' <a href="' . $blog_details->siteurl . '">' . $blog_details->blogname . '</a>';
									}

								} else if ( ( $record->affiliatearea == 'paid:membership' )
								            && ( ! empty( $record->area_id ) ) && ( isset( $record->meta['tolevel_id'] ) )
								            && ( function_exists( 'affiliate_membership_get_subscription_levels' ) )
								) {

									//echo "record<pre>"; print_r($record); echo "</pre>";

									$levels = affiliate_membership_get_subscription_levels();
									//echo "levels<pre>"; print_r($levels); echo "</pre>";
									if ( ! empty( $levels ) ) {
										foreach ( $levels as $level ) {
											if ( $level->id == $record->meta['tolevel_id'] ) {
												$record->affiliatenote .= ': ' . $level->sub_name;
												break;
											}
										}
									}
									echo $record->affiliatenote;
								} else {
									echo $record->affiliatenote;

									if ( ( $record->affiliatearea == "debit" ) || ( $record->affiliatearea == "credit" ) || ( $record->affiliatearea == "payment" ) ) {
										if ( ( isset( $record->meta['current_user_id'] ) ) && ( ! empty( $record->meta['current_user_id'] ) ) ) {
											$user = new WP_User( intval( $record->meta['current_user_id'] ) );
											if ( ( $user ) && ( $user->ID > 0 ) ) {
												$note = ' ' . __( 'by', 'affiliate' ) . ': ';
												if ( ! empty( $user->display_name ) ) {
													$note .= $user->display_name;
													if ( $user->display_name !== $user->user_login ) {
														$note .= ' (' . $user->user_login . ')';
													}
												} else {
													$note .= $user->user_login;
												}
												echo $note;

											}
										}
									}
								}
							}
							//echo "<br />meta<pre>"; print_r($record->meta); echo "</pre>";
							echo '</td>';
							break;

						case 'amount':
							if ( ( $record->affiliatearea == 'payment' ) || ( $record->affiliatearea == 'debit' ) ) {
								if ( $record->amount == 0 ) {
									echo '<td class="afiliate-credit">' . number_format( 0, 2 ) . '</td>';
								} else {
									echo '<td class="afiliate-credit">-' . number_format( $record->amount, 2 ) . '</td>';
									$amountTotal -= $record->amount;
								}
							} else {
								echo '<td>' . number_format( $record->amount, 2 ) . '</td>';
								$amountTotal += $record->amount;
							}
							break;

						default:
							do_action( 'affiliate_details_column_' . $key, $record );
							break;
					}
				}
				echo '</tr>';


			}
		}
		echo '</tbody>';

		echo '<tfoot>';
		echo '<tr>';
		$col_count = count( $columns ) - 1;
		echo '<td colspan="' . $col_count . '">' . __( 'Period Balance', 'affiliate' ) . '</td>';
		echo '<td>' . number_format( $amountTotal, 2 ) . '</td>';
		echo '</tr>';
		echo '</tfoot>';

		echo '</table>';
	}
}
