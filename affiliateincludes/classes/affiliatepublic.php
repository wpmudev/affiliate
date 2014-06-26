<?php
// Front end and reporting part of the affiliate system
class affiliate {

	//var $build = 7;

	var $db;

	// The page on the public side of the site that has details of the affiliate plan
	var $affiliateinformationpage = 'affiliates';

	var $tables = array('affiliatedata','affiliatereferrers','affiliaterecords');

	var $affiliatedata;
	var $affiliatereferrers;
	var $affiliaterecords;

	var $mylocation = "";
	var $plugindir = "";
	var $base_uri = '';

	var $onmu = false;

	function __construct() {

		global $wpdb;

		// Grab our own local reference to the database class
		$this->db =& $wpdb;

		$this->detect_location(1);

		foreach ($this->tables as $table) {
			if ((affiliate_is_plugin_active_for_network()) 
			 && (defined('AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED') && AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED == 'yes')) {// we're activated site wide
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
                
                $cookie_domain = network_home_url();
                $cookie_domain = str_replace('http://','',$cookie_domain);
                $cookie_domain = str_replace('https://','',$cookie_domain);
                $cookie_domain = str_replace('www.','',$cookie_domain);
                $this->cookie_domain = '.'.$cookie_domain;
                
		//$installed = aff_get_option('Aff_Installed', false);

		//if($installed === false || $installed != $this->build) {
		//	$this->install();
		//
		//	aff_update_option('Aff_Installed', $this->build);
		//}

		//register_activation_hook(__FILE__, array(&$this, 'install'));

		add_action( 'init', array(&$this, 'handle_affiliate_link' ) );

		// Global generic functions
		add_action('affiliate_click', array(&$this, 'record_click'), 10, 6);
		add_action('affiliate_signup', array(&$this, 'record_signup'), 10, 6);
		add_action('affiliate_purchase', array(&$this, 'record_purchase'), 10, 6);

		add_action('affiliate_credit', array(&$this, 'record_credit'), 10, 2);
		add_action('affiliate_debit', array(&$this, 'record_debit'), 10, 2);

		add_action('affiliate_referrer', array(&$this, 'record_referrer'), 10, 2);

		add_action('user_register', array(&$this, 'user_register'), 10);
		add_action('wpmu_activate_user', array(&$this, 'wpmu_activate_user'), 10, 3);

		add_filter('add_signup_meta', array(&$this, 'add_signup_meta'), 10);
		add_action('wpmu_activate_blog', array(&$this, 'wpmu_activate_blog'), 10, 5);

		// Include affiliate plugins 
		if (!is_admin()) // We only need to load if we are not in admin. 
			load_affiliate_addons();
	}

	function __destruct() {

	}

	function affiliatelite() {
		$this->__construct();
	}

	function install() {
		return; // The install is done via the admin class on plugin activation. WTF is this doing here?
		
		// This shouldn't really need to be called as the admin area will set up the tables - but just in case
		if($this->db->get_var( "SHOW TABLES LIKE '" . $this->affiliatedata . "' ") != $this->affiliatedata) {

			$charset_collate = '';

			if ( ! empty($this->db->charset) ) {
				$charset_collate = "DEFAULT CHARACTER SET " . $this->db->charset;
			}

			if ( ! empty($this->db->collate) ) {
				$charset_collate .= " COLLATE " . $this->db->collate;
			}

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
			  	UNIQUE KEY `user_period` (`user_id`,`period`)
				) $charset_collate;";

			$this->db->query($sql);

			$sql = "CREATE TABLE `" . $this->affiliatereferrers . "` (
			  	`user_id` bigint(20) default NULL,
			  	`period` varchar(6) default NULL,
			  	`url` varchar(250) default NULL,
			  	`referred` bigint(20) default '0',
			  	UNIQUE KEY `user_id` (`user_id`,`period`,`url`)
				) $charset_collate;";

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
				) $charset_collate;";

			$this->db->query($sql);
		}

		if ((affiliate_is_plugin_active_for_network()) 
		 && (defined('AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED') && AFFILIATE_USE_GLOBAL_IF_NETWORK_ACTIVATED == 'yes')) {

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

	function detect_location($level = 1) {
		$directories = explode(DIRECTORY_SEPARATOR,dirname(__FILE__));

		$mydir = array();
		for($depth = $level; $depth >= 1; $depth--) {
			$mydir[] = $directories[count($directories)-$depth];
		}

		$mydir = implode('/', $mydir);

		if($mydir == 'mu-plugins') {
			$this->mylocation = basename(__FILE__);
			$level = 0;
		} else {
			$this->mylocation = $mydir . DIRECTORY_SEPARATOR . basename(__FILE__);
		}

		if(defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/' . $this->mylocation)) {
			$this->plugindir = WP_PLUGIN_URL;
			$this->onmu = false;
		} else {
			$this->plugindir = WPMU_PLUGIN_URL;
			$this->onmu = true;
		}

		$this->base_uri = trailingslashit($this->plugindir . '/' . $directories[count($directories)-$level]);

	}

	function user_register( $new_user_id ) {
//		echo 'in '. __FILE__ .': '. __FUNCTION__ .': '. __LINE__ .'<br />';
//		echo "new_user_id[". $new_user_id ."]<br />";
		
//		echo "_GET<pre>"; print_r($_GET); echo "</pre>";
//		echo "_POST<pre>"; print_r($_POST); echo "</pre>";
//		echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
//		echo "trace<pre>"; debug_print_backtrace(); echo "</pre>";
		
//		die();
		// The user_register hook is only for regular (non-Multisite) systems. Fro Multisite see wpmu_activate_user
//		if (is_multisite()) return;

		// Since this action does not support the meta information like the wpmu_activate_user() function we need to 
		// query the signups to see if we have the information. 	
		//$affiliate_referred_by = get_user_meta($new_user_id, 'affiliate_referred_by', true);

		$affiliate_referred_id = get_user_meta($new_user_id, 'affiliate_referred_by', true);
		if (!empty($affiliate_referred_id)) {
			// This user is already associated with an affiliate. So nothing left to do. 
		}

		$affiliate_referred_id = $this->get_affiliate_user_id_from_hash();
		//echo "affiliate_referred_id[". $affiliate_referred_id .']<br />';
		if (empty($affiliate_referred_id)) {
			if (is_multisite()) {
                                // If we don't have the affiliate reference then double check the signup meta info. 
			
                                global $wpdb;
                        
                                // the table won't exist if it isn't multisite
                        
                                $sql_str = $wpdb->prepare("SELECT meta FROM $wpdb->signups WHERE user_email = %s LIMIT 1", get_the_author_meta('user_email', $new_user_id));
                                //echo "sql_str[". $sql_str ."]<br />";	
                                $signup_meta = $wpdb->get_var($sql_str);
                                //echo "signup_meta<pre>"; print_r($signup_meta); echo "</pre>";
                        
                        
                                if ($signup_meta) {
                                        $signup_meta = maybe_unserialize($signup_meta);
                                        //echo "signup_meta<pre>"; print_r($signup_meta); echo "</pre>";
                                        if ((isset($signup_meta['affiliate_referred_by'])) && (!empty($signup_meta['affiliate_referred_by']))) {
                                                $affiliate_referred_id = $signup_meta['affiliate_referred_by'];
                                        } 
                                }
                        }
		}

		if (!empty($affiliate_referred_id)) {
			//echo "affiliate_referred_id[". $affiliate_referred_id .']<br />';

			// Call the affiliate action
			$meta = array(
				'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
				'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
				'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
				//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
			);
		
			$note = __('User', 'affiliate');
			do_action( 'affiliate_signup', $affiliate_referred_id, false, 'signup:user', $new_user_id, $note, $meta); 
			update_user_meta($new_user_id, 'affiliate_referred_by', $affiliate_referred_id);
		}		
	}
	
	function wpmu_activate_user($new_user_id, $new_user_password, $new_blog_meta) {
//		echo 'in '. __FILE__ .': '. __FUNCTION__ .': '. __LINE__ .'<br />';
//		echo "new_user_id[". $new_user_id ."]<br />";
//		echo "new_blog_meta[". $new_blog_meta ."]<br />";
//		
//		echo "_GET<pre>"; print_r($_GET); echo "</pre>";
//		echo "_POST<pre>"; print_r($_POST); echo "</pre>";
//		echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
//		echo "trace<pre>"; debug_print_backtrace(); echo "</pre>";
		
		// Check if this signup was from an affiliate referal. 
		$affiliate_referred_by = get_user_meta($new_user_id, 'affiliate_referred_by', true);
		if (!$affiliate_referred_by) {
			if (isset($new_blog_meta['affiliate_referred_by'])) {
			
				$affiliate_user_id = intval($new_blog_meta['affiliate_referred_by']);
			
				// Call the affiliate action
				$meta = array(
					'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
					'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
					'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
					//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
				);
			
				//echo "blog_details<pre>"; print_r($blog_details); echo "</pre>";
				//echo "adding affiliate_signup<br />";
				
				$note = __('User', 'affiliate');
				do_action( 'affiliate_signup', $affiliate_user_id, false, 'signup:user', $new_user_id, $note, $meta); 

				update_user_meta($new_user_id, 'affiliate_referred_by', $affiliate_user_id);
			}
		}		
	}
	
	function wpmu_activate_blog($new_blog_id, $new_user_id, $new_user_password, $new_blog_title, $new_blog_meta) {
		//echo 'in '. __FILE__ .': '. __FUNCTION__ .': '. __LINE__ .'<br />';
		
		// Check if this signup was from an affiliate referal. 
		if (isset($new_blog_meta['affiliate_referred_by'])) {
			$affiliate_user_id = intval($new_blog_meta['affiliate_referred_by']);
			
			// Call the affiliate action
			$meta = array(
				'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
				'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
				'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
				//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
			);
			
			$note = __('Blog', 'affiliate');
			do_action( 'affiliate_signup', $affiliate_user_id, false, 'signup:blog', $new_blog_id, $note, $meta); 

			update_blog_option( $new_blog_id, 'affiliate_referred_by', $affiliate_user_id );

//			$this->wpmu_activate_user($new_user_id, '', $new_blog_meta);
		}
	}
	
	function add_signup_meta($meta) {
		//echo "meta<pre>"; print_r($meta); echo "</pre>";
		$affiliate_user_id = $this->get_affiliate_user_id_from_hash();
		if (!empty($affiliate_user_id)) {
			$meta['affiliate_referred_by'] = $affiliate_user_id;
		}
		return $meta;
	}
	
	function get_affiliate_user_id_from_hash() {
		
		//echo 'in '. __FUNCTION__ .': '. __LINE__ .'<br />';
		
		//echo "_COOKIE<pre>"; print_r($_COOKIE); echo "</pre>";
		//echo "COOKIEHASH[". COOKIEHASH ."]<br />";
		
		if(isset( $_COOKIE['affiliate_' . COOKIEHASH])) {
			// Get the cookie hash so we know who the referrer is
			$hash = addslashes($_COOKIE['affiliate_' . COOKIEHASH]);
			//echo "hash[". $hash ."]<br />";
			$sql_str = $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_hash' AND meta_value = %s", $hash);
			//echo "sql_str[". $sql_str ."]<br />";
			//die();
			return $this->db->get_var( $sql_str );
		} //else {
		//	echo "COOKIE not set<br />";
		//}
	}

	// Recording of affiliate information

	function record_click($affiliate_user_id, $amount = false, $area = false, $area_id = false, $note = false, $meta = false ) {
		if (!$affiliate_user_id) {
			$affiliate_user_id = $this->get_affiliate_user_id_from_hash();
		}
		
		if($affiliate_user_id) {
		
			// Record the click in the affiliate table - v0.2+
			$period = date('Ym');

			$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, uniques, credits, lastupdated) VALUES (%d, %s, %d, %01.2f, now()) ON DUPLICATE KEY UPDATE uniques = uniques + %d, credits = credits + %01.2f", $affiliate_user_id, $period, 1, $amount, 1, $amount );
			//error_log(__FUNCTION__ .": sql[". $sql ."]");
			
			$queryresult = $this->db->query($sql);

			if( $area !== false ) {
				$args = array( 'user_id' => $affiliate_user_id, 'period' => $period, 'affiliatearea' => $area, 'area_id' => $area_id, 'affiliatenote' => $note, 'amount' => $amount, 'meta' => maybe_serialize($meta), 'timestamp' => current_time('mysql', true));
				//echo "args<pre>"; print_r($args); echo "</pre>";
				
				$this->db->insert( $this->affiliaterecords, $args );
				//echo "db<pre>"; print_r($this->db); echo "</pre>";
			}
		}
	}

	function record_signup( $affiliate_user_id = false, $amount = false, $area = false, $area_id = false, $note = false, $meta = false ) {

		if (!$affiliate_user_id) {
			if(defined( 'AFFILIATEID' )) {
				$affiliate_user_id = AFFILIATEID;
			} else {
				$affiliate_user_id = $this->get_affiliate_user_id_from_hash();
			}
		}

		if($affiliate_user_id) {

			$period = date('Ym');

			$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, signups, credits, lastupdated) VALUES (%d, %s, %d, %01.2f, now()) ON DUPLICATE KEY UPDATE signups = signups + %d, credits = credits + %01.2f", $affiliate_user_id, $period, 1, $amount, 1, $amount );
			$queryresult = $this->db->query($sql);
			
			if(!defined( 'AFFILIATEID' )) {
				define( 'AFFILIATEID', $affiliate_user_id );
			}
			if( !empty($area) && $area !== false ) {
				$this->db->insert( $this->affiliaterecords, array( 'user_id' => $affiliate_user_id, 'period' => $period, 'affiliatearea' => $area, 'area_id' => $area_id, 'affiliatenote' => $note, 'amount' => $amount, 'meta' => maybe_serialize($meta), 'timestamp' => current_time('mysql', true) ) );
			}			
		}
	}

	function record_purchase($affiliate_user_id = false, $amount = false, $area = false, $area_id = false, $note = false, $meta = false) {
		
		if (!$affiliate_user_id) {
			$affiliate_user_id = $this->get_affiliate_user_id_from_hash();
		}
		
		if( !(empty($affiliate_user_id)) && (is_numeric($affiliate_user_id))) {

			$period = date('Ym');

			// Need to get the amount paid and calculate the commision
			$amount = number_format($amount, 2, '.', '');

			$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, completes, credits, lastupdated) VALUES (%d, %s, %d, %01.2f, now()) ON DUPLICATE KEY UPDATE completes = completes + %d, credits = credits + %01.2f ", $affiliate_user_id, $period, 1, $amount, 1, $amount );
			//echo "sql[". $sql ."]<br />";
			$queryresult = $this->db->query($sql);

			if( !empty($area) && $area !== false ) {
				$this->db->insert( $this->affiliaterecords, array( 'user_id' => $affiliate_user_id, 'period' => $period, 'affiliatearea' => $area, 'area_id' => $area_id, 'affiliatenote' => $note, 'amount' => $amount, 'meta' => maybe_serialize($meta), 'timestamp' => current_time('mysql', true) ) );
			}
			//die();
		}
	}


	function record_credit($affiliate_user_id, $amount = false) {

		if( !empty($affiliate_user_id) && is_numeric($affiliate_user_id) && $amount ) {

			$period = date('Ym');

			// Need to get the amount paid and calculate the commision
			$amount = number_format($amount, 2, '.', '');

			$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, credits, lastupdated) VALUES (%d, %s, %01.2f, now()) ON DUPLICATE KEY UPDATE credits = credits + %01.2f ", $affiliate_user_id, $period, $amount, $amount );
			$queryresult = $this->db->query($sql);
			
			$meta = array(
				'current_user_id'	=>	get_current_user_id(),
				'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
				'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
				//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
			);
			
			$this->db->insert( $this->affiliaterecords, array( $affiliate_user_id, $period, 'credit', false, false, 'amount' => $amount, 'meta' => maybe_serialize($meta), 'timestamp' => current_time('mysql', true) ) );
		}
	}

	function record_debit($affiliate_user_id, $amount = false) {

		if( !empty($affiliate_user_id) && is_numeric($affiliate_user_id) && $amount ) {

			$period = date('Ym');

			// Need to get the amount paid and calculate the commision
			$amount = number_format($amount, 2, '.', '');

			$sql = $this->db->prepare( "INSERT INTO {$this->affiliatedata} (user_id, period, debits, lastupdated) VALUES (%d, %s, %01.2f, now()) ON DUPLICATE KEY UPDATE debits = debits + %01.2f ", $affiliate_user_id, $period, $amount, $amount );
			$queryresult = $this->db->query($sql);

			$meta = array(
				'current_user_id'	=>	get_current_user_id(),
				'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
				'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
				//'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
			);
			$this->db->insert( $this->affiliaterecords, array( $affiliate_user_id, $period, 'debit', false, false, 'amount' => $amount, 'meta' => maybe_serialize($meta), 'timestamp' => current_time('mysql', true) ) );
			//echo "db<pre>"; print_r($this->db); echo "</pre>";
			//die();
		}
	}

	function record_referrer($affiliate_user_id, $url = false) {

		if( !empty($affiliate_user_id) && is_numeric($affiliate_user_id) && $url ) {

			$period = date('Ym');

			// Need to get the amount paid and calculate the commision
			//$amount = number_format($amount, 2, '.', '');

			$sql = $this->db->prepare( "INSERT INTO {$this->affiliatereferrers} (user_id, period, url, referred) VALUES (%d, %s, %s, %d) ON DUPLICATE KEY UPDATE referred = referred + %d ", $affiliate_user_id, $period, $url, 1, 1 );

			$queryresult = $this->db->query($sql);

		}
	}


	function handle_affiliate_link() {
            
            $reference = false;
            $referrer = '';
            $affiliate = false;
            

            if($this->no_affiliate_condition()){
                if(isset($_GET['ref'])) {
                    // redirect to the none affiliate url anyway, just to be tidy
                    $this->redirect( remove_query_arg( array('ref') ) );
                }
                
                // return if the condition exists
                return;
                                
            }
            
            // set the reference
            $reference = isset($_GET['ref'])?$_GET['ref']:false;
            
            // set the referrer, if we have one
            if(isset($_SERVER['HTTP_REFERER'])) {
                $referrer = parse_url(esc_attr($_SERVER['HTTP_REFERER']), PHP_URL_HOST);
            }
            
            // we have a ref parameter in the url
            if($reference){
                // if a cookie doesn't exist already or we are allowed to replace it
                if ((!isset( $_COOKIE['affiliate_' . COOKIEHASH])) || (AFFILIATE_REPLACE_COOKIE == 'yes')) {
                    // get the affiliate user_id from the reference
                    $affiliate = $this->reference_has_user($reference);
                }
                
            }
            
            // we have an affiliate
            if($affiliate){
                // set the cookie
                $this->set_affiliate_cookie($affiliate,$reference,$referrer);
                // The cookie is set so redirect to the page called but without the ref in the url
                // for SEO reasons.
                $this->redirect( remove_query_arg( array('ref') ) );
            }
            
            
            // cool, we haven't been redirected, so no ref was passed and no cookie was set
            // affiliate is false and reference is false 
           
            // are we still supposed to continue?
            if(defined('AFFILIATE_CHECKALL') && AFFILIATE_CHECKALL == 'yes') {
                // we still don't have a cookie, but we do have a referrer
                if(!isset( $_COOKIE['affiliate_' . COOKIEHASH]) && !empty($referrer)) {
                    // get affiliate user_id from referrer
                    $affiliate = $this->referrer_has_user($referrer);
                }
                
            }
            
            // we have an affiliate for the referrer!
            if($affiliate){
                // we aren't setting the cookie by default
                $set_cookie = false;
                
                // see if we are going to set the cookie
                if(defined('AFFILIATE_VALIDATE_REFERRER_URLS') && AFFILIATE_VALIDATE_REFERRER_URLS == 'yes' ) {
                        // Check the URL is verified
                        $validated = get_user_meta($affiliate, 'affiliate_referrer_validated', true);
                        if(!empty($validated) && $validated == 'yes') {
                                $set_cookie = true;
                        }
                } else {
                    $set_cookie = true;
                }
                
                // we can set the cookie
                if($set_cookie){
                                            
                    // get a valid reference for the user_id
                    $reference = get_user_meta($affiliate, 'affiliate_reference',true);
                    // Update a quick count for this month
                    //do_action( 'affiliate_click', $affiliate);
                    do_action( 'affiliate_click', $affiliate, false, 'unique', false, false, $meta);

                    // Store the referrer
                    do_action( 'affiliate_referrer', $affiliate, $referrer );

                    // Write the affiliate hash out - valid for 30 days.
                    @setcookie('affiliate_' . COOKIEHASH, 'aff' . md5(AUTH_SALT . $reference),
                            (time() + (60 * 60 * 24 * ((int) AFFILIATE_COOKIE_DAYS ))),
                            COOKIEPATH,
                            $this->cookie_domain
                            );

                }
                
                // return, so that no affiliate cookie is never set
                return;
                
            }
            
            if(!$reference){
                // everything failed, we don't have a reference
                if(defined('AFFILIATE_SETNOCOOKIE') && AFFILIATE_SETNOCOOKIE == 'yes'){
                    // we are supposed to set a no-affiliate cookie
                    @setcookie('noaffiliate_' . COOKIEHASH, 'notanaff', 0, COOKIEPATH, $this->cookie_domain);
                }
            }
		
	}
        
        /**
         * To check the no_affiliate cookie
         * @return boolean True if the cookie is set and needs to be considered
         */
        function no_affiliate_condition(){
            // if we are supposed to check this cookie
            if(defined('AFFILIATE_SETNOCOOKIE') && 'yes' == AFFILIATE_SETNOCOOKIE ){
                if(isset($_COOKIE['noaffiliate_' . COOKIEHASH])) {
                    // If there isn't a reference set then return because we don't want to do anything
                    return true;
		}
                return false;
            }
            return false;
        }
        
        function reference_has_user($reference){
            return $this->db->get_var( $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_reference' AND meta_value='%s'", $reference) );
        }
        
        function referrer_has_user($referrer){
            return $this->db->get_var( $this->db->prepare( "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = 'affiliate_referrer' AND meta_value='%s'", $referrer) );
        }
        
        function set_affiliate_cookie($affiliate, $reference, $referrer){
					
                $meta = array(
                        'REMOTE_URL'		=>	esc_attr($_SERVER['HTTP_REFERER']),
                        'LOCAL_URL'			=>	( is_ssl() ? 'https://' : 'http://' ) . esc_attr($_SERVER['HTTP_HOST']) . esc_attr($_SERVER['REQUEST_URI']),
                        'IP'				=>	(isset($_SERVER['HTTP_X_FORWARD_FOR'])) ? esc_attr($_SERVER['HTTP_X_FORWARD_FOR']) : esc_attr($_SERVER['REMOTE_ADDR']),
                        //'HTTP_USER_AGENT'	=>	esc_attr($_SERVER['HTTP_USER_AGENT'])
                );

                // Update a quick count for this month
                $note = __('Referal', 'affiliate') .' '. esc_attr($_SERVER['HTTP_REFERER']);
                $amount = apply_filters('affiliate_click_amount_filter', '0.00', $affiliate );
                $amount = number_format($amount, 2);
                //error_log(__FUNCTION__ .": amount[". $amount ."]");
                do_action( 'affiliate_click', $affiliate, $amount, 'unique:click', false, $note, $meta);

                do_action( 'affiliate_referrer', $affiliate, $referrer );

                // Write the affiliate hash out - valid for 30 days.
                @setcookie('affiliate_' . COOKIEHASH, 'aff' . md5(AUTH_SALT . $reference), (time() + (60 * 60 * 24 * ((int) AFFILIATE_COOKIE_DAYS ))), COOKIEPATH, $this->cookie_domain);

        }

	function redirect($location, $status = 302) {
		// Put our own version of the redirect function here because even though the
		// proper WordPress one asks for a status code, it doesn't actually use it.

		global $is_IIS;

		$location = apply_filters('wp_redirect', $location, $status);
		$status = apply_filters('wp_redirect_status', $status, $location);

		if ( !$location ) // allows the wp_redirect filter to cancel a redirect
			return false;

		$location = wp_sanitize_redirect($location);

		if ( $is_IIS ) {
			header("Refresh: 0;url=$location", true, $status);
		} else {
			if ( php_sapi_name() != 'cgi-fcgi' ) {
				status_header($status); // This causes problems on IIS and some FastCGI setups
			}

			// Adding cache control
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: pre-check=0, post-check=0, max-age=0');
			header('Pragma: no-cache');
			//header('P3P: CP="NOI ADM DEV COM NAV OUR STP"');

			header("Location: $location", true, $status);
		}
		// Ensure we have an exit
		exit;
	}
	
	function get_complete_records($user_id, $period = false, $area = array(), $area_id = false) {
		$sql_str = "SELECT * FROM ". $this->affiliaterecords ." WHERE `user_id`=". $user_id;
		if (!empty($period))
			$sql_str .= " AND `period`='". $period ."' ";

		if (!empty($area)) {
			$area_str = '';
			foreach($area as $area_item) {
				if (!empty($area_str)) $area_str .= ',';
				$area_str .= "'". $area_item ."'";
			}
			if (!empty($area_str)) {
				$sql_str .= " AND `affiliatearea` IN (". $area_str .") ";
			}
		}
		if (!empty($area_id))
			$sql_str .= " AND `area_id`='". $area_id ."' ";

		$sql_str .= ' LIMIT 50';

		//echo "sql_str[". $sql_str ."]<br />";
		return $this->db->get_results($sql_str);
	}
	
}
