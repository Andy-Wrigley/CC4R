<?php
if ( ! defined( 'CCA_MAXMIND_DATA_DIR' ) ) exit; // do not run directly remove when file renamed inc
/*
standard function used by CCA family of plugins
sets $GLOBALS['CCA_ISO_CODE'] to country code
sets $GLOBALS['cca-lookup-msg'] with staus/error msg
can be run within or outside Wordpress
*/

function cca_run_geo_lookup($max_script_dir) {
  $GLOBALS['CCA_ISO_CODE'] = '';  // prevent need to set for different errors
  $GLOBALS['cca-lookup-msg'] = '';  // info on look-up - global as used by a variety of plugins

  // if USER plugin exists, place ISO code in $GLOBALS['CCA_ISO_CODE']
  if (is_readable(CCA_MAXMIND_DATA_DIR . 'use-my-geolocation.inc')) @include CCA_MAXMIND_DATA_DIR . 'use-my-geolocation.inc';
  if (function_exists('cca_custom_lookup')): // then include file found and has function 
	  $GLOBALS['cca-lookup-msg'] = '<CCA_CUST_GEO_LINK>Custom setting found</a> - using your site\'s alternative Geolocation system. ' . cca_custom_lookup();  // function should set $GLOBALS['CCA_ISO_CODE'] and return $GLOBALS['cca-lookup-msg'] (used by parent of this file)
		return;
  endif;
  $GLOBALS['cca-lookup-msg'] = 'You have not <CCA_CUST_GEO_LINK>customized to use alternative Geolocation</a>. Cloudflare or Maxmind Geolite2 Country will be used.';

  // ****  try Cloudflare ****
  if ( isset($_SERVER["HTTP_CF_IPCOUNTRY"])) { // then Cloudflare Geo Country is on
		$GLOBALS['cca-lookup-msg'] .= '<br>You appear to be using Cloudflare for country identification.';
    $GLOBALS['CCA_ISO_CODE'] = strtoupper($_SERVER["HTTP_CF_IPCOUNTRY"]);  //Cloudflare GEOIP active, ensure uppercase just in case
		if ($GLOBALS["CCA_ISO_CODE"] != 'XX' && ctype_alnum($GLOBALS["CCA_ISO_CODE"]) && strlen($GLOBALS["CCA_ISO_CODE"]) == 2) return;  // valid
  	if ($GLOBALS["CCA_ISO_CODE"] != 'XX') { // then invalid CF value (XX valid and denotes unknown country)
		  $GLOBALS['cca-lookup-msg'] .= 'But CF provided an invalid Country Code. Treating as "" (unknown/default).'; // when reported append htmlspecialchars($_SERVER["HTTP_CF_IPCOUNTRY"]) . ' converted to ""'; 
		}
		$GLOBALS["CCA_ISO_CODE"] = '';
  	return;
  }
	$GLOBALS['cca-lookup-msg'] .= '<br>You do not appear to be using Cloudflare or have not configured it to provide country code.';


  // not using plugin GeoIP or Cloudflare so we need an IP Address for Maxmind	
  if (is_readable(CCA_MAXMIND_DATA_DIR . 'IPvar-custom-order.inc')) @include(CCA_MAXMIND_DATA_DIR . 'IPvar-custom-order.inc');  // user overwrite of $address_vars to use
  if ( empty($address_vars)):
    $address_vars = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
		$GLOBALS['cca-lookup-msg'] .= '<br>You are not using <CCA_CUST_IPVAR_LINK>custom settings to define which server variable(s)</a> to check for your Visitor\'s IP address. So ';
  else:
	  $GLOBALS['cca-lookup-msg'] .= '<br>You are using custom settings to <CCA_CUST_IPVAR_LINK>define which server variable(s) to check</a> for your Visitor\'s IP address';
	endif;
	$GLOBALS['cca-lookup-msg'] .= "Server Variables will be searched<br>&nbsp; in this order: '" . implode ( "', '" , $address_vars ) . "'";

  $GLOBALS['CCA_VALID_IP'] = '';
	foreach ( $address_vars as $key):
    if (! empty($_SERVER[$key])) :
      $ip = explode(',', $_SERVER[$key]); // for NON ANON proxies the first in a comma separated list is often visitor IP
      $ip[0] = trim($ip[0]); // just to be safe
      if (filter_var($ip[0], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false):
			  $GLOBALS['CCA_VALID_IP'] = $ip[0];
				$GLOBALS['cca-lookup-msg'] .= "<br>Valid IP found in '" . $key . "'; (IP Address " . htmlspecialchars($GLOBALS['CCA_VALID_IP']) . ')';
				break;
      endif;
    endif;
  endforeach;
  if ($GLOBALS['CCA_VALID_IP'] == ''):
	  $GLOBALS['cca-lookup-msg'] .= '<br>Unable to identify valid visitor IP address (or you are connected "locally" to your server)';
		return;
	endif;


  // Max Geolite2 (needs php 5.4+)
  if (version_compare(PHP_VERSION, '5.4.0') >= 0) :
    if (! class_exists('\CCAMaxMind\Db\Reader')) :
      include_once $max_script_dir . 'Reader.php';
      include_once $max_script_dir . 'Reader/Decoder.php';
      include_once $max_script_dir . 'Reader/InvalidDatabaseException.php';
      include_once $max_script_dir . 'Reader/Metadata.php';
      include_once $max_script_dir . 'Reader/Util.php';
    endif;
    $error = FALSE;
    try {
  		$reader = new \CCAMaxMind\Db\Reader(CCA_MAXMIND_DATA_DIR . CCA_MAX_FILENAME );
      $ipData = $reader->get($GLOBALS['CCA_VALID_IP']);
    } catch (Exception $e) {
      $GLOBALS['cca-lookup-msg'] .= '<br>Maxmind v2 Look-up error: ' . $e->getMessage() . '<br>Using out of date Maxmind Legacy for look-up.';
      $error = TRUE;
    }
    if ( ! $error ):
      $GLOBALS['CCA_ISO_CODE'] = empty($ipData['country']['iso_code']) ? '' :  $ipData['country']['iso_code'];
  		$GLOBALS['cca-lookup-msg'] = "Visitor country identification appears to be working correctly.<br> <br>" . $GLOBALS['cca-lookup-msg']  . "<br>Country identified using Maxmind v2";
  		return;
    endif;
  else:
    $GLOBALS['cca-lookup-msg'] .= '<br>You site is using an ancient version of PHP; Maxmind v2 requires PHP 5.4.0+.<br>Using out of date Maxmind Legacy for look-up.';
  endif;

//  Max V2 failed, fallback on Geolite legacy
  if( filter_var($GLOBALS['CCA_VALID_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) :  //then server is IPV4
    $geoIPdb = 'GeoIP.dat';
  elseif ( filter_var($GLOBALS['CCA_VALID_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) :
	  $geoIPdb = 'GeoIPv6.dat';
  else:
	  $GLOBALS['cca-lookup-msg'] .= '<br>IP fail'; 
	  return; // unable to lookup with legacy Maxmind return error msg from V2 lookup
  endif;


// Max Geolite legacy
// the same class file is packaged with CCA and Country Caching plugins we only want to load it once
  if (! class_exists('\cc_max\GeoIP')) :
    if ( ! file_exists($max_script_dir . 'cc_geoip.inc') || ! file_exists(CCA_MAXMIND_DATA_DIR . $geoIPdb) ) :
		  $GLOBALS['cca-lookup-msg'] .= '<br>Maxmind legacy not installed. Country NOT identified.';
		  return;
    endif;
		include_once($max_script_dir . 'cc_geoip.inc');
  endif;

  $gi = \cc_max\geoip_open(CCA_MAXMIND_DATA_DIR . $geoIPdb, GEOIP_STANDARD);
  if($geoIPdb == 'GeoIP.dat') :
     $GLOBALS['CCA_ISO_CODE'] = \cc_max\geoip_country_code_by_addr($gi, $GLOBALS['CCA_VALID_IP']);
  else:
    $GLOBALS['CCA_ISO_CODE'] = \cc_max\geoip_country_code_by_addr_v6($gi, $GLOBALS['CCA_VALID_IP']);
  endif;
  \cc_max\geoip_close($gi);
  return;
}  // end function "cca_run_geo_lookup"
