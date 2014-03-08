<?php
function show_affiliate_admin_metabox_reports_affiliate_link() {
	if(function_exists('is_multisite') && is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('affiliate/affiliate.php')) {
		// switch to use new option
		$siteurl = get_blog_option(1,'home');
		$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
	} else {
		// switch to use new option
		$siteurl = aff_get_option('home');
		$affiliatelinkurl = aff_get_option( 'affiliatelinkurl', $siteurl );
	}

	?>
	<div class="postbox">
		<h3 class="hndle" style="cursor:auto;"><span><?php _e('Affiliate Link URL', 'affiliate') ?></span></h3>
		<div class="inside">
			<table class="form-table">
			<tr>
				<th valign="top" scope="row"><?php _e('Link URL','affiliate') ?></th>
				<td valign="top">
					<input name="affiliatelinkurl" type="text" id="affiliatelinkurl" style="width: 50%" value="<?php 
						echo htmlentities(stripslashes($affiliatelinkurl),ENT_QUOTES, 'UTF-8') ?>" />
				</td>
			</tr>
			</table>
		</div>
	</div>
	<?php
}

function show_affiliate_admin_metabox_reports_monetary_precision() {
	$affiliatemonetaryprecision = aff_get_option('affiliatemonetaryprecision');
	?>
	<div class="postbox">
		<h3 class="hndle" style="cursor:auto;"><span><?php _e('Monetary Precision', 'affiliate')  ?></span></h3>
		<div class="inside">
			<table class="form-table">
			<tr>
				<th valign="top" scope="row"><?php _e('Number or decimal places for stored calculations','affiliate') ?></th>
				<td valign="top">
					<select name='affiliatemonetaryprecision'>
						<option value="2" <?php if(aff_get_option('affiliatemonetaryprecision', '2') == '2') echo ' selected="selected" ' ?>><?php _e('2 places - 0.00', 'affiliate') ?></option>

						<option value="4" <?php if(aff_get_option('affiliatemonetaryprecision', '2') == '4') echo 'selected="selected" ' ?>><?php _e('4 places - 0.0000', 'affiliate') ?></option>
					</select>
				</td>
			</tr>
			</table>
			<p><?php _e('Warning: changing the precision will effect existing data and calculations.', 'affiliate')?></p>
		</div>
	</div>
	<?php
}


function show_affiliate_admin_metabox_reports_column_settings() {
	$headings = aff_get_option( 'affiliateheadings', array( __('Unique Clicks','affiliate'), __('Sign ups','affiliate'), __('Paid members','affiliate')) );
	?>
	<div class="postbox">
		<h3 class="hndle" style="cursor:auto;"><span><?php _e('Column Settings', 'affiliate') ?></span></h3>
		<div class="inside">
			<table class="form-table">
				<tr>
					<th valign="top" scope="row"><?php _e('Unique Clicks','affiliate') ?></th>
					<td valign="top">
						<input name="uniqueclicks" type="text" id="uniqueclicks" style="width: 50%" value="<?php echo htmlentities(stripslashes($headings[0]),ENT_QUOTES, 'UTF-8') ?>" />
					</td>
				</tr>
				<tr>
					<th valign="top" scope="row"><?php _e('Sign ups','affiliate') ?></th>
					<td valign="top">
						<input name="signups" type="text" id="signups" style="width: 50%" value="<?php echo htmlentities(stripslashes($headings[1]),ENT_QUOTES, 'UTF-8') ?>" />
					</td>
				</tr>
				<tr>
					<th valign="top" scope="row"><?php _e('Paid members','affiliate') ?></th>
					<td valign="top">
						<input name="paidmembers" type="text" id="paidmembers" style="width: 50%" value="<?php echo htmlentities(stripslashes($headings[2]),ENT_QUOTES, 'UTF-8') ?>" />
					</td>
				</tr>
			</table>
		</div>
	</div>
	<?php
}

function show_affiliate_admin_metabox_profile_text() {

		$settingstextdefault = __("<p>We love it when people talk about us, and even more so when they recommend us to their friends.</p>
<p>As a thank you we would like to offer something back, which is why we have set up this affiliate program.</p>
<p>To get started simply enable the links for your account and enter your PayPal email address below, for more details on our affiliate program please visit our main site.</p>", 'affiliate');

		$advsettingstextdefault = __("<p>There are times when you would rather hide your affiliate link, or simply not have to bother remembering the affiliate reference to put on the end of our URL.</p>
<p>If this is the case, then you can enter the main URL of the site you will be sending requests from below, and we will sort out the tricky bits for you.</p>", 'affiliate');
		
	?>
	<div class="postbox">
		<h3 class="hndle" style="cursor:auto;"><span><?php _e('Profile page text', 'affiliate')  ?></span></h3>
		<div class="inside">
			<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Affiliate settings profile text', 'affiliate') ?></th>
				<td><?php
					$args = array("textarea_name" => "affiliatesettingstext");
					wp_editor( stripslashes( aff_get_option('affiliatesettingstext', $settingstextdefault) ), "affiliatesettingstext", $args );
				?></td>
			</tr>
			</table>
	
			<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Affiliate advanced settings profile text', 'affiliate') ?></th>
				<td><?php
					$args = array("textarea_name" => "affiliateadvancedsettingstext");
					wp_editor( stripslashes( aff_get_option('affiliateadvancedsettingstext', $advsettingstextdefault) ), "affiliateadvancedsettingstext", $args );
					?></td>
				</tr>
			</table>
		</div>
	</div>
	<?php
}

function show_affiliate_admin_metabox_settings_banner() {
	$banners = aff_get_option('affiliatebannerlinks');
	//echo "banners<pre>"; print_r($banners); echo "</pre>";
	if(is_array($banners)) {
		$banners = implode("\n", $banners);
	}

	?>
	<div class="postbox">
		<h3 class="hndle" style="cursor:auto;"><span><?php _e('Banner Settings', 'affiliate')  ?></span></h3>
		<div class="inside">
			<table class="form-table">
			<tr>
				<th valign="top" scope="row"><?php _e('Enable Banners','affiliate') ?></th>
				<td valign="top">
					<select name='affiliateenablebanners'>
						<option value="yes" <?php if(aff_get_option('affiliateenablebanners', 'no') == 'yes') echo ' selected="selected" ' ?>><?php _e('Yes please', 'affiliate') ?></option>

						<option value="no" <?php if(aff_get_option('affiliateenablebanners', 'no') == 'no') echo 'selected="selected" ' ?>><?php _e('No thanks', 'affiliate') ?></option>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Banner Image URLs (one per line)', 'affiliate') ?></th>
				<td>
					<textarea name="affiliatebannerlinks" id="affiliatebannerlinks" cols="60" rows="10"><?php echo stripslashes( $banners ) ?></textarea>
				</td>
			</tr>
			</table>
			
		</div>
	</div>
	<?php
}

function show_affiliate_admin_metabox_settings_approval() {
	?>
	<div class="postbox">
		<h3 class="hndle" style="cursor:auto;"><span><?php _e('Approval Settings', 'affiliate') ?></span></h3>
		<div class="inside">
			<p class="description">
				<?php _e('If you want to delay payouts to affiliates until they have been manually approved then set this option below. Affiliates will still be able to generate leads, whilst they are waiting to be approved.','affiliate');?>
			</p>
			<table class="form-table">
			<tr>
				<th valign="top" scope="row"><?php _e('Pay only approved affiliates','affiliate') ?></th>
				<td valign="top">
					<select name='affiliateenableapproval'>
						<option value="yes" <?php if (aff_get_option('affiliateenableapproval', 'no') == 'yes') echo ' selected="selected" '; ?>><?php _e('Yes please', 'affiliate') ?></option>

						<option value="no" <?php if (aff_get_option('affiliateenableapproval', 'no') == 'no') echo ' selected="selected" ' ?>><?php _e('No thanks', 'affiliate') ?></option>

					</select>
				</td>
			</tr>
			</table>

		</div>
	</div>
	<?php
}


function show_affiliate_admin_metabox_settings_paypal_masspay_currency() {
	global $affiliate_currencies, $affiliate_currencies_paypal_masspay;

	sort($affiliate_currencies_paypal_masspay);

	?>
	<div class="postbox">
		<h3 class='hndle'><span><?php _e('Currency used for PayPal Masspay', 'affiliate') ?></span></h3>
		<div class="inside">
			<span class="description"><?php echo sprintf(__('This setting defines the 3-character currency used for the %s.', 'affiliate'), '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_batch-payment-format-outside" target="_blank">'. __('PayPal masspay file', 'affiliate') .'</a>') ?></span>
			
			
			
			<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Currency', 'mp') ?></th>
				<td>
					<select id="affiliate-currency-paypal-masspay" name="affiliate-currency-paypal-masspay">
					<?php
						foreach ($affiliate_currencies_paypal_masspay as $key) {
							if (isset($affiliate_currencies[$key])) {
								?><option value="<?php echo $key; ?>"<?php selected(aff_get_option('affiliate-currency-paypal-masspay', 'USD'), $key); ?>><?php echo esc_attr($key) .' - '. esc_attr($affiliate_currencies[$key][0]) ?></option><?php
							}
						}
					?>
					</select>
				</td>
			</tr>
			</table>
		</div>
	</div>
	<?php
}
