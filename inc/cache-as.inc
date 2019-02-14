<?php
function cc_get_iso() {
  switch (CC4R_options::$options['geo_method']) :
    case 'CDN-AmzCf' :
      $visitorISO = isset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY']) ? $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] : 'Error';
	  // (assumed server var) for info on HTTP Header to $_SERVER[] conversion see http://cmyker.blogspot.com/2012/10/how-to-dump-http-headers-with-php.html
      break;
    case 'CDN-Clf' :
      $visitorISO = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : 'Error';
      break;
    case 'CDN-other' :
  	  $cdn = CC4R_options::$options['CDN_geo_svar'];
      $visitorISO = isset($_SERVER[$cdn]) ? $_SERVER[$cdn] : 'Error';
      break;
  	case 'other_geo' :
  	  $visitorISO = apply_filters( 'cc_use_other_geoip', 'Error' );
  	  break;
  	case 'CCA' :
      	if (! function_exists('cca_run_geo_lookup') ) include CC4R_MAXMIND_DIR . 'cca_lookup_ISO.inc';
      	if ( ! isset($GLOBALS['CCA_ISO_CODE']) || empty($GLOBALS['cca-lookup-msg'])) : // then lookup has not already been done by another CCA plugin
    	  if (! function_exists('cca_run_geo_lookup') ) :
    		   include CC4R_MAXMIND_DIR . 'cca_lookup_ISO.inc';
    	  endif;
      	  cca_run_geo_lookup(CC4R_MAXMIND_DIR); // sets global CCA_ISO_CODE and status msg
      	endif;
      	$lookupMsg = str_replace('<CCA_CUST_IPVAR_LINK>', CCA_CUST_IPVAR_LINK, $GLOBALS['cca-lookup-msg']);
      	$lookupMsg = str_replace('<CCA_CUST_GEO_LINK>', CCA_CUST_GEO_LINK, $lookupMsg);
        $visitorISO = isset($GLOBALS['CCA_ISO_CODE']) ? $GLOBALS['CCA_ISO_CODE'] : 'Error';
  endswitch;

  if ( ctype_alnum($visitorISO) && strlen($visitorISO) > 1 && strlen($visitorISO) < 4 ) :
      $visitorISO = strtoupper($visitorISO); // just in case
	  return $visitorISO;  // valid 2 char ISO code or 3 char oddity identifier
  endif;
  if ( $visitorISO == 'Error' ) : return  $visitorISO; endif;
  return 'unk'; //  unknown locale
}


function cc_cache_as($visitorISO) {
  if ($visitorISO == 'Error' ):
    return 'Error'; // (not for caching)
  endif;

// if unknown country maybe return unk for sep caching ??

  if ( ( empty(CC4R_options::$options['cache_iso_cc']) && empty(CC4R_options::$options['use_group']) ) || in_array( $visitorISO, explode(',' , CC4R_options::$options['cache_iso_cc']) ) ):
	  return $visitorISO;  // cache this country individually (either there's no settings to limit caching; or this country is specified for individual caching
  endif;

  if ( CC4R_options::$options['use_group'] && in_array( $visitorISO, explode(',' , CC4R_options::$options['my_ccgroup']) ) ):
	  return 'cc_grp';
  endif;

  #MODIFICATION NEEDED so not "std" if specific country setting empty also check htaccess
  if ( empty(CC4R_options::$options['cache_iso_cc']) ) return $visitorISO;
  return 'std';  // i.e for main country and default content for any other unspecified country
}


function set_cc4r_cookie() {
  if (isset($options['cookie_old']) && isset($_COOKIE[CC4R_options::$options['cookie_old']])):  // avoid build-up of obsolete cookies during testing
    $secure = CC4R_options::$options['ssl_cookie'];
    setcookie( CC4R_options::$options['cookie_old'] , '' , time() - 90 * 86400, '/', COOKIE_DOMAIN, $secure, TRUE );
  endif;
  if( isset($_COOKIE[CC4R_options::$options['cookie_name']])) return;
  $visitorISO = cc_get_iso();
  if ($visitorISO == 'Error' ) return FALSE;  // don't set cookie i.e. don't cache
  $ccISO = cc_cache_as($visitorISO);
  $secure = CC4R_options::$options['ssl_cookie'];
  setcookie( CC4R_options::$options['cookie_name'] , $ccISO, time() + 90 * 86400, '/', COOKIE_DOMAIN, $secure, TRUE );
}
add_action('init', 'set_cc4r_cookie');


function cc4r_httphdr_msg() {
  $msg = '';
  if (CC4R_options::$options['geo_method'] == 'CDN-other' && CC4R_options::$options['CDN_geo_svar'] != CC4R_options::$options['CDN_geo_httphdr'] ) :
      if (function_exists('getallheaders') ):
          $httphdr_array = getallheaders();
          $hdr_name = CC4R_options::$options['CDN_geo_httphdr'];
          if ( array_key_exists( $hdr_name ,$httphdr_array ) ) :
              $hdr_value = wp_kses( $httphdr_array[$hdr_name] , array() );
              $msg .=  __( 'HTTP Header detected: ', 'cc4r' ) . $hdr_name . ' => ' . $hdr_value;
              if ( ! ctype_alnum($hdr_value) || strlen($visitorISO) != 2 ) :
                  $msg .= ' ' . __( 'This is NOT a valid country code.', 'cc4r' );
              endif;
          else:
              $msg .= ' ' . __( 'WARNING: Http Header' , 'cc4r' ) . ' ' . $hdr_name . __( ' not detected' , 'cc4r' );
          endif;
          $msg .= PHP_EOL;
      endif;
  endif; 
  return $msg;
}


function cc4r_display_cache_test() {
  if ( empty(CC4R_options::$options['cc_enabled']) ) return '<br><hr><br><h3>' . __( 'Country Caching is not enabled', 'cc4r' ) . '</h3><br><hr>';
  $visitorISO = cc_get_iso();
  if ( $visitorISO == "Error") :
	  return '<br><hr><h2>' . __( 'CC Testing', 'cc4r' ) . '</h2><p><span style="color:red">' . __( 'Either you have not connected your GeoIP to <i>CC 4 WPR</i> or it has returned an invalid code', 'cc4r' ) . '</span></p><br><hr>';
  endif;
  $msg = '';
  if ( ! function_exists('rocket_clean_user') ) :
    $msg = '<br><hr><b>' . __( 'WP Rocket MUST BE ENABLED (info below is how your page would be cached <u>IF</u> Rocket was working)', 'cc4r' ) . '</b>';
  endif;
  $curCookie = empty($_COOKIE[CC4R_options::$options['cookie_name']]) ? '' : $_COOKIE[CC4R_options::$options['cookie_name']];
  $msg .= '<br><hr><p><b><u>' . __( 'CC Testing', 'cc4r' ) . '</u></b><br>';
  $msg .= cc4r_httphdr_msg() . '</p>';
  $msg .= '<p>' . sprintf( __( 'This page is being visited, or was previously cached by a visitor from: %s', 'cc4r' ), '"<span style="color:brown">' . $visitorISO . '</span>"' )  . '</p>';
  $msg .= '<p>' . sprintf( __( 'Page cached as %s', 'cc4r' ), '"<span style="color:brown">' . cc_cache_as( $visitorISO ) . '</span>"' ) . '</p>';
  $msg .= '<p>' . sprintf( __( 'Time cached (or time of this visit): %s', 'cc4r' ), '<span style="color:brown">' . date( 'H:i:s' ) . '</span>' ) . '</p>';
  $msg .= '<br><br>' . sprintf( __( 'Before this request cookie was set as %s.', 'cc4r' ), '<span style="color:brown">"' . $curCookie . '"</span>' ) . '<br>';
  $msg .= '<p>' . sprintf( __( 'N.B. If you have not opted to use a CDN/Server provided country, then if you wish to now test as a new site visitor you will have to use your Browser\'s Developer Tools to delete the Cookie named "%s".', 'cc4r' ), CC4R_options::$options['cookie_name'] ) . '</p>';
  $msg .= '<br><hr><br>';
  return $msg;
}
add_shortcode( 'cc4r_test_msg', 'cc4r_display_cache_test' );


function cc4r_test_to_meta() {
  $yesno = empty(CC4R_options::$options['cc_enabled'] )  ? "is NOT" : 'is';
  $curCookie = empty($_COOKIE[CC4R_options::$options['cookie_name']]) ? '' : $_COOKIE[CC4R_options::$options['cookie_name']];
  $visitorISO = cc_get_iso();
?>
<meta name="cc4r:testing" content="Country Caching <?php echo $yesno; ?> enabled.
<?php
	if ( ! function_exists('rocket_clean_user') ) {
		echo __( 'Country Caching is not working: WP Rocket MUST BE ENABLED', 'cc4r' );
	} elseif ( $visitorISO != "Error") {
		printf( ' ' . __( 'When cached (or this visit if not yet cached):%6$s CC Testing: This page is being visited, or was previously cached by a visitor from: %1$s.%6$s Page cached as: %2$s. Time cached (or time of this visit): %3$s. Before this request cookie was set as: %4$s.%6$s N.B. If you have not opted to use a CDN/Server provided country, then:%6$s if you now wish to test as a new visitor then close and open a new private browser window or use your Browser\'s Developer Tools to delete the Cookie named %5$s.' . PHP_EOL , 'cc4r' ),
			$visitorISO,
			cc_cache_as( $visitorISO ),
			date( 'H:i:s' ),
			$curCookie,
			CC4R_options::$options['cookie_name'],
			PHP_EOL . ' '
		);
		echo cc4r_httphdr_msg();
	} else {
		echo __( 'CC4R geolocation reports error: Either you have not connected your GeoIP to CC 4 WPR, or it has returned an invalid code', 'cc4r' );
	} //endif ( CC4R_WPRC_ENABLED )
?>
"> <!-- //end meta name="cc4r:testing" -->
<?php
}
if ( ! empty(CC4R_options::$options['test_mode']) ) add_action( 'wp_head', 'cc4r_test_to_meta' );