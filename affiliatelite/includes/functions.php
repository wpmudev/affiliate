<?php
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
?>