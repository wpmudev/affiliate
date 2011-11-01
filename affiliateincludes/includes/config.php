<?php
// Uncomment to have the system check all pages and referrers
if(!defined('AFFILIATE_CHECKALL')) define('AFFILIATE_CHECKALL', 'yes');
// Uncomment to have the system set a 'browser-session' cookie if no referrer is found - this reduces server load
// and is recommended if the above setting is un-commented
if(!defined('AFFILIATE_SETNOCOOKIE')) define('AFFILIATE_SETNOCOOKIE', 'yes');
// Pay the affiliate only once
if(!defined('AFFILIATE_PAYONCE')) define('AFFILIATE_PAYONCE', 'yes');
// Force the system to use global tables
if(!defined('AFFILIATE_USE_BASE_PREFIX_IF_EXISTS')) define('AFFILIATE_USE_BASE_PREFIX_IF_EXISTS', 'no');
// Force users using the advanced settings and URL have to validate their URL's before they can use them.
if(!defined('AFFILIATE_VALIDATE_REFERRER_URLS')) define('AFFILIATE_VALIDATE_REFERRER_URLS','no');

?>