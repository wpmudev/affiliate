<?php
/* -------------------- Update Notifications Notice -------------------- */
if ( !function_exists( 'wdp_un_check' ) ) {
  add_action( 'admin_notices', 'wdp_un_check', 5 );
  add_action( 'network_admin_notices', 'wdp_un_check', 5 );
  function wdp_un_check() {
    if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
      echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
  }
}
/* --------------------------------------------------------------------- */

function set_affiliate_url($base) {

	global $M_affiliate_url;

	if(defined('WPMU_PLUGIN_URL') && defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename($base))) {
		$M_affiliate_url = trailingslashit(WPMU_PLUGIN_URL);
	} elseif(defined('WP_PLUGIN_URL') && defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/affiliate/' . basename($base))) {
		$M_affiliate_url = trailingslashit(WP_PLUGIN_URL . '/affiliate');
	} else {
		$M_affiliate_url = trailingslashit(WP_PLUGIN_URL . '/affiliate');
	}

}

function set_affiliate_dir($base) {

	global $M_affiliate_dir;

	if(defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename($base))) {
		$M_affiliate_dir = trailingslashit(WPMU_PLUGIN_DIR);
	} elseif(defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/affiliate/' . basename($base))) {
		$M_affiliate_dir = trailingslashit(WP_PLUGIN_DIR . '/affiliate');
	} else {
		$M_affiliate_dir = trailingslashit(WP_PLUGIN_DIR . '/affiliate');
	}


}

function affiliate_url($extended) {

	global $M_affiliate_url;

	return $M_affiliate_url . $extended;

}

function affiliate_dir($extended) {

	global $M_affiliate_dir;

	return $M_affiliate_dir . $extended;


}

function get_affiliate_plugins() {
	if ( is_dir( affiliate_dir('affiliateincludes/plugins') ) ) {
		if ( $dh = opendir( affiliate_dir('affiliateincludes/plugins') ) ) {
			$aff_plugins = array ();
			while ( ( $plugin = readdir( $dh ) ) !== false )
				if ( substr( $plugin, -4 ) == '.php' )
					$aff_plugins[] = $plugin;
			closedir( $dh );
			sort( $aff_plugins );

			return apply_filters('affiliate_available_plugins', $aff_plugins);

		}
	}

	return false;
}

function load_affiliate_plugins() {

	$plugins = get_option('affiliate_activated_plugins', array());

	if ( is_dir( affiliate_dir('affiliateincludes/plugins') ) ) {
		if ( $dh = opendir( affiliate_dir('affiliateincludes/plugins') ) ) {
			$aff_plugins = array ();
			while ( ( $plugin = readdir( $dh ) ) !== false )
				if ( substr( $plugin, -4 ) == '.php' )
					$aff_plugins[] = $plugin;
			closedir( $dh );
			sort( $aff_plugins );
			foreach( $aff_plugins as $aff_plugin ) {
				if(in_array($aff_plugin, $plugins)) {
					include_once( affiliate_dir('affiliateincludes/plugins/' . $aff_plugin) );
				}
			}

		}
	}
}

function load_all_affiliate_plugins() {
	if ( is_dir( affiliate_dir('affiliateincludes/plugins') ) ) {
		if ( $dh = opendir( affiliate_dir('affiliateincludes/plugins') ) ) {
			$aff_plugins = array ();
			while ( ( $plugin = readdir( $dh ) ) !== false )
				if ( substr( $plugin, -4 ) == '.php' )
					$aff_plugins[] = $plugin;
			closedir( $dh );
			sort( $aff_plugins );
			foreach( $aff_plugins as $aff_plugin )
				include_once( affiliate_dir('affiliateincludes/plugins/' . $aff_plugin) );
		}
	}
}
?>