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
	
	if (is_ssl()) {
		$M_affiliate_url = str_replace('http://', 'https://', $M_affiliate_url);
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

	if (is_ssl()) {
		$M_affiliate_url = str_replace('http://', 'https://', $M_affiliate_url);
	}

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
			while ( ( $plugin = readdir( $dh ) ) !== false ) {

				// Not sure why this file is present. But ignore it. 
				if ($plugin == "prosites.php") {
					continue;
				}

				if ( substr( $plugin, -4 ) == '.php' ) {
					$aff_plugins[] = $plugin;
				}
			}
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
			while ( ( $plugin = readdir( $dh ) ) !== false ) {

				// Not sure why this file is present. But ignore it. 
				if ($plugin == "prosites.php") {
					continue;
				}

				if ( substr( $plugin, -4 ) == '.php' ) {
					$aff_plugins[] = $plugin;
				}
			}
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

// What calls this function???
function load_all_affiliate_addons() {
	if ( is_dir( affiliate_dir('affiliateincludes/addons') ) ) {
		if ( $dh = opendir( affiliate_dir('affiliateincludes/addons') ) ) {
			$aff_plugins = array ();
			while ( ( $plugin = readdir( $dh ) ) !== false ) {

				// Not sure why this file is present. But ignore it. 
				if ($plugin == "prosites.php")
					continue;

				if ( substr( $plugin, -4 ) == '.php' ) {
					$aff_plugins[] = $plugin;
				}
			}
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

function aff_format_currency($currency = '', $amount = false) {

//	 if (!$currency)
//		 $currency = $this->get_setting('currency', 'USD');

	 // get the currency symbol
	 $symbol = $affiliate_currencies[$currency][1];
	 echo "symbol<pre>"; print_r($symbol); echo "</pre>";
	 
	 // if many symbols are found, rebuild the full symbol
	 $symbols = explode(', ', $symbol);
	 if (is_array($symbols)) {
		 $symbol = "";
		 foreach ($symbols as $temp) {
			 $symbol .= '&#x'.$temp.';';
		 }
	 } else {
		 $symbol = '&#x'.$symbol.';';
 	}

	//check decimal option
//	if ( $this->get_setting('curr_decimal') === '0' ) {
//		$decimal_place = 0;
//		$zero = '0';
//	} else {
		$decimal_place = 2;
		$zero = '0.00';
//	}
//echo "amount[". $amount ."]<br />";

 	//format currency amount according to preference
	 if ($amount) {

//		 if ($this->get_setting('curr_symbol_position') == 1 || !$this->get_setting('curr_symbol_position'))
			 return $symbol . number_format_i18n($amount, $decimal_place);
//		 else if ($this->get_setting('curr_symbol_position') == 2)
//			 return $symbol . ' ' . number_format_i18n($amount, $decimal_place);
//		 else if ($this->get_setting('curr_symbol_position') == 3)
//			 return number_format_i18n($amount, $decimal_place) . $symbol;
//		 else if ($this->get_setting('curr_symbol_position') == 4)
//			 return number_format_i18n($amount, $decimal_place) . ' ' . $symbol;

	 } else if ($amount === false) {
		 return $symbol;
	 } else {
//		 if ($this->get_setting('curr_symbol_position') == 1 || !$this->get_setting('curr_symbol_position'))
			 return $symbol . $zero;
//		 else if ($this->get_setting('curr_symbol_position') == 2)
//			 return $symbol . ' ' . $zero;
//		 else if ($this->get_setting('curr_symbol_position') == 3)
//			 return $zero . $symbol;
//		 else if ($this->get_setting('curr_symbol_position') == 4)
//			 return $zero . ' ' . $symbol;
	 }
}

//currency list - http://www.xe.com/symbols.php
//last perameter is symbol which is unicode hex: http://www.mikezilla.com/exp0012.html
$affiliate_currencies = array(
	"ALL"	=> array("Albania, Leke", "4c, 65, 6b"),
	"AFN"	=> array("Afghanistan, Afghanis", "60b"),
	"ARS"	=> array("Argentina, Pesos", "24"),
	"AWG"	=> array("Aruba, Guilders (also called Florins)", "192"),
	"AUD"	=> array("Australia, Dollars", "24"),
	"AZN"	=> array("Azerbaijan, New Manats", "43c, 430, 43d"),
	"BSD"	=> array("Bahamas, Dollars", "24"),
	"BBD"	=> array("Barbados, Dollars", "24"),
	"BYR"	=> array("Belarus, Rubles", "70, 2e"),
	"BZD"	=> array("Belize, Dollars", "42, 5a, 24"),
	"BMD"	=> array("Bermuda, Dollars", "24"),
	"BOB"	=> array("Bolivia, Bolivianos", "24, 62"),
	"BAM"	=> array("Bosnia and Herzegovina, Convertible Marka", "4b, 4d"),
	"BWP"	=> array("Botswana, Pulas", "50"),
	"BGN"	=> array("Bulgaria, Leva", "43b, 432"),
	"BRL"	=> array("Brazil, Reais", "52, 24"),
	"BND"	=> array("Brunei Darussalam, Dollars", "24"),
	"KHR"	=> array("Cambodia, Riels", "17db"),
	"CAD"	=> array("Canada, Dollars", "24"),
	"KYD"	=> array("Cayman Islands, Dollars", "24"),
	"CLP"	=> array("Chile, Pesos", "24"),
	"CNY"	=> array("China, Yuan Renminbi", "a5"),
	"COP"	=> array("Colombia, Pesos", "24"),
	"CRC"	=> array("Costa Rica, Colon", "20a1"),
	"HRK"	=> array("Croatia, Kuna", "6b, 6e"),
	"CUP"	=> array("Cuba, Pesos", "20b1"),
	"CZK"	=> array("Czech Republic, Koruny", "4b, 10d"),
	"DKK"	=> array("Denmark, Kroner", "6b, 72"),
	"DOP"	=> array("Dominican Republic, Pesos", "52, 44, 24"),
	"XCD"	=> array("East Caribbean, Dollars", "24"),
	"EGP"	=> array("Egypt, Pounds", "45, 47, 50"),
	"SVC"	=> array("El Salvador, Colones", "24"),
	"EEK"	=> array("Estonia, Krooni", "6b, 72"),
	"EUR"	=> array("Euro", "20ac"),
	"FKP"	=> array("Falkland Islands, Pounds", "a3"),
	"FJD"	=> array("Fiji, Dollars", "24"),
	"GEL"	=> array("Georgia, lari", "6c, 61, 72, 69"),
	"GHC"	=> array("Ghana, Cedis", "a2"),
	"GIP"	=> array("Gibraltar, Pounds", "a3"),
	"GTQ"	=> array("Guatemala, Quetzales", "51"),
	"GGP"	=> array("Guernsey, Pounds", "a3"),
	"GYD"	=> array("Guyana, Dollars", "24"),
	"HNL"	=> array("Honduras, Lempiras", "4c"),
	"HKD"	=> array("Hong Kong, Dollars", "24"),
	"HUF"	=> array("Hungary, Forint", "46, 74"),
	"ISK"	=> array("Iceland, Kronur", "6b, 72"),
	"INR"	=> array("India, Rupees", "20a8"),
	"IDR"	=> array("Indonesia, Rupiahs", "52, 70"),
	"IRR"	=> array("Iran, Rials", "fdfc"),
	"IMP"	=> array("Isle of Man, Pounds", "a3"),
	"ILS"	=> array("Israel, New Shekels", "20aa"),
	"JMD"	=> array("Jamaica, Dollars", "4a, 24"),
	"JPY"	=> array("Japan, Yen", "a5"),
	"JEP"	=> array("Jersey, Pounds", "a3"),
	"KZT"	=> array("Kazakhstan, Tenge", "43b, 432"),
	"KES"	=> array("Kenyan Shilling", "4B, 73, 68, 73"),
	"KWD"	=> array("Kuwait, dinar", "4B, 2E, 44, 2E"),
	"KGS"	=> array("Kyrgyzstan, Soms", "43b, 432"),
	"LAK"	=> array("Laos, Kips", "20ad"),
	"LVL"	=> array("Latvia, Lati", "4c, 73"),
	"LBP"	=> array("Lebanon, Pounds", "a3"),
	"LRD"	=> array("Liberia, Dollars", "24"),
	"LTL"	=> array("Lithuania, Litai", "4c, 74"),
	"MKD"	=> array("Macedonia, Denars", "434, 435, 43d"),
	"MYR"	=> array("Malaysia, Ringgits", "52, 4d"),
	"MUR"	=> array("Mauritius, Rupees", "20a8"),
	"MXN"	=> array("Mexico, Pesos", "24"),
	"MNT"	=> array("Mongolia, Tugriks", "20ae"),
	"MAD"	=> array("Morocco, dirhams", "64, 68"),
	"MZN"	=> array("Mozambique, Meticais", "4d, 54"),
	"NAD"	=> array("Namibia, Dollars", "24"),
	"NPR"	=> array("Nepal, Rupees", "20a8"),
	"ANG"	=> array("Netherlands Antilles, Guilders (also called Florins)", "192"),
	"NZD"	=> array("New Zealand, Dollars", "24"),
	"NIO"	=> array("Nicaragua, Cordobas", "43, 24"),
	"NGN"	=> array("Nigeria, Nairas", "20a6"),
	"KPW"	=> array("North Korea, Won", "20a9"),
	"NOK"	=> array("Norway, Krone", "6b, 72"),
	"OMR"	=> array("Oman, Rials", "fdfc"),
	"PKR"	=> array("Pakistan, Rupees", "20a8"),
	"PAB"	=> array("Panama, Balboa", "42, 2f, 2e"),
	"PYG"	=> array("Paraguay, Guarani", "47, 73"),
	"PEN"	=> array("Peru, Nuevos Soles", "53, 2f, 2e"),
	"PHP"	=> array("Philippines, Pesos", "50, 68, 70"),
	"PLN"	=> array("Poland, Zlotych", "7a, 142"),
	"QAR"	=> array("Qatar, Rials", "fdfc"),
	"RON"	=> array("Romania, New Lei", "6c, 65, 69"),
	"RUB"	=> array("Russia, Rubles", "440, 443, 431"),
	"SHP"	=> array("Saint Helena, Pounds", "a3"),
	"SAR"	=> array("Saudi Arabia, Riyals", "fdfc"),
	"RSD"	=> array("Serbia, Dinars", "414, 438, 43d, 2e"),
	"SCR"	=> array("Seychelles, Rupees", "20a8"),
	"SGD"	=> array("Singapore, Dollars", "24"),
	"SBD"	=> array("Solomon Islands, Dollars", "24"),
	"SOS"	=> array("Somalia, Shillings", "53"),
	"ZAR"	=> array("South Africa, Rand", "52"),
	"KRW"	=> array("South Korea, Won", "20a9"),
	"LKR"	=> array("Sri Lanka, Rupees", "20a8"),
	"SEK"	=> array("Sweden, Kronor", "6b, 72"),
	"CHF"	=> array("Switzerland, Francs", "43, 48, 46"),
	"SRD"	=> array("Suriname, Dollars", "24"),
	"SYP"	=> array("Syria, Pounds", "a3"),
	"TWD"	=> array("Taiwan, New Dollars", "4e, 54, 24"),
	"THB"	=> array("Thailand, Baht", "e3f"),
	"TTD"	=> array("Trinidad and Tobago, Dollars", "54, 54, 24"),
	"TRY"	=> array("Turkey, Lira", "54, 4c"),
	"TRL"	=> array("Turkey, Liras", "20a4"),
	"TVD"	=> array("Tuvalu, Dollars", "24"),
	"UAH"	=> array("Ukraine, Hryvnia", "20b4"),
	"AED"	=> array("United Arab Emirates, dirhams", "64, 68"),
	"GBP"	=> array("United Kingdom, Pounds", "a3"),
	"USD"	=> array("United States of America, Dollars", "24"),
	"UYU"	=> array("Uruguay, Pesos", "24, 55"),
	"UZS"	=> array("Uzbekistan, Sums", "43b, 432"),
	"VEF"	=> array("Venezuela, Bolivares Fuertes", "42, 73"),
	"VND"	=> array("Vietnam, Dong", "20ab"),
	"XAF"	=> array("BEAC, CFA Francs", "46, 43, 46, 41"),
	"XOF"	=> array("BCEAO, CFA Francs", "46, 43, 46, 41"),
	"YER"	=> array("Yemen, Rials", "fdfc"),
	"ZWD"	=> array("Zimbabwe, Zimbabwe Dollars", "5a, 24"),
//	"POINTS"=> array("Points (for point based stores)", "50, 6f, 69, 6e, 74, 73"),
//	"CREDITS"=> array("Credits (for credit based stores)", "43, 72, 65, 64, 69, 74, 73")
);

$affiliate_currencies_paypal_masspay = array(
	'EUR', 'USD', 'GBP', 'CAD', 'JPY', 'AUD', 'NZD', 'CHF', 'HKD', 'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'HUF', 'CZK', 'ILS', 'MXN', 'BRL', 'MYR', 'PHP', 'THB', 'TRY', 'TWD', 'RUB'
);;