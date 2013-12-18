<?php
function affiliate_is_plugin_active_for_network($plugin = 'affiliate/affiliate.php') {
	if (!is_multisite()) return false;
		
	if ( !function_exists( 'is_plugin_active_for_network' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}


	if ( (function_exists('is_plugin_active_for_network')) && (is_plugin_active_for_network($plugin)) ) 
		return true;
	else
		return false;
}

function affiliate_is_plugin_active($plugin = 'affiliate/affiliate.php') {
		
	if ( !function_exists( 'is_plugin_active' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}

	if ( (function_exists('is_plugin_active')) && (is_plugin_active($plugin)) ) 
		return true;
	else
		return false;
}

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

function get_affiliate_addons() {
	if ( is_dir( affiliate_dir('affiliateincludes/addons') ) ) {
		if ( $dh = opendir( affiliate_dir('affiliateincludes/addons') ) ) {
			$aff_plugins = array ();
			while ( ( $plugin = readdir( $dh ) ) !== false )
				if ( substr( $plugin, -4 ) == '.php' )
					$aff_plugins[] = $plugin;
			closedir( $dh );
			sort( $aff_plugins );

			return apply_filters('affiliate_available_addons', $aff_plugins);

		}
	}

	return false;
}

function load_affiliate_addons() {

	$plugins = aff_get_option('affiliate_activated_addons', array());
	
	if ( is_dir( affiliate_dir('affiliateincludes/addons') ) ) {
		if ( $dh = opendir( affiliate_dir('affiliateincludes/addons') ) ) {
			$aff_plugins = array ();
			while ( ( $plugin = readdir( $dh ) ) !== false )
				if ( substr( $plugin, -4 ) == '.php' )
					$aff_plugins[] = $plugin;
			closedir( $dh );
			sort( $aff_plugins );

			$aff_plugins = apply_filters('affiliate_available_addons', $aff_plugins);

			foreach( $aff_plugins as $aff_plugin ) {
				if(in_array($aff_plugin, (array) $plugins)) {
					include_once( affiliate_dir('affiliateincludes/addons/' . $aff_plugin) );
				}
			}

		}
	}
}

function load_all_affiliate_addons() {
	if ( is_dir( affiliate_dir('affiliateincludes/addons') ) ) {
		if ( $dh = opendir( affiliate_dir('affiliateincludes/addons') ) ) {
			$aff_plugins = array ();
			while ( ( $plugin = readdir( $dh ) ) !== false )
				if ( substr( $plugin, -4 ) == '.php' )
					$aff_plugins[] = $plugin;
			closedir( $dh );
			sort( $aff_plugins );
			foreach( $aff_plugins as $aff_plugin )
				include_once( affiliate_dir('affiliateincludes/addons/' . $aff_plugin) );
		}
	}
}

function aff_get_option( $option, $default = false ) {

	if (affiliate_is_plugin_active_for_network()) {	
		return get_site_option( $option, $default);
	} else {
		return get_option( $option, $default);
	}
}

function aff_update_option( $option, $value = null ) {
	if (affiliate_is_plugin_active_for_network()) {	
		return update_site_option( $option, $value);
	} else {
		return update_option( $option, $value);
	}
}

function aff_delete_option( $option ) {
	if (affiliate_is_plugin_active_for_network()) {	
		return delete_site_option( $option );
	} else {
		return delete_option( $option );
	}
}

function aff_build_reference( $user ) {

	if(defined('AFFILIATE_REFERENCE_PREFIX') && AFFILIATE_REFERENCE_PREFIX != '' ) {
		$ref = AFFILIATE_REFERENCE_PREFIX . '-' . strrev(sprintf('%02d', $user->ID + (int) AFFILIATE_REFERENCE_KEY));
	} else {
		$ref = $user->user_login . '-' . strrev(sprintf('%02d', $user->ID + (int) AFFILIATE_REFERENCE_KEY));
	}

	return $ref;

}
