<?php

//********************************************************
// Class for WPR Caching Options
//********************************************************
class CC4R_options { // cc4r-options to get/set options 
  private static $default_settings = array(
//	  'activation_status' => 'new',
        'is_activated' => '0', // false
        'cc_enabled' => '0',
        'cookie_name' => 'cc4r_geo',
        'ssl_cookie' => '0',
        'geo_method' => 'CCA',
#		'CDN_geo_hdr' =>'',
		'CDN_geo_svar' =>'',
		'CDN_geo_httphdr' =>'',
		'cache_iso_cc' => '',  # countries individually cached
		'my_ccgroup' => '',
		'use_group' => '0',  // stored as '0';
		'CCAsync' => '0',
		'only4CN' => '0',
		'diagnostics' => FALSE,
		'initial_message'=> ''
	);
  private static $current_settings = array();
  public static $options = array();

  public static $ccax_options = array();
	

  // check for multisite use update_site_option( $option, $value ); get_site_option( $option ) (returns false if option not set)

  public static function init() {
  // https://codex.wordpress.org/Function_Reference/wp_parse_args  to create/merge in defaults
    if ( ! empty(self::$current_settings ) ) return TRUE;

    self::$ccax_options = get_option( 'ccax_options' );
    self::$default_settings['my_ccgroup'] = CC4R_EU_GROUP;

    // on Multisite settings are stored in "site_options" and shared by all its sites
    if ( is_multisite() ) :
		    $retrieved_settings = get_site_option( 'cc4r_caching_options' );
    else:
        $retrieved_settings = get_option( 'cc4r_caching_options' );
    endif;

    if (! $retrieved_settings ) :
	    self::$options = self::$default_settings;
	    self::save_options(); // update option using latest_settings
		  return TRUE;
	  endif;

    self::$options = wp_parse_args( $retrieved_settings, self::$default_settings); // add new default elements (if any) to gotten options
    if ( self::$options['CCAsync'] && !empty(self::$ccax_options) && !empty(self::$ccax_options['EU_ccodes']) ) :
        self::$options['my_ccgroup'] = self::$ccax_options['EU_ccodes'];
    endif;

    if (self::$options != $retrieved_settings) :  // then option needs updating to include new default/CCA elements
	    self::save_options();
    endif;
//    self::$current_settings = self::$options;
    return TRUE;
  }


  public static function is_enabled() {
	  if (! empty(self::$options['cc_enabled']) ) return TRUE;
	  return FALSE;
  }


  public static function reset($doSave = FALSE) {
	  self::$options = self::$default_settings;
    self::$options['is_activated'] = '1';
    self::$options['my_ccgroup'] = CC4R_EU_GROUP;
//	  self::$current_settings = self::$options;
	  if ($doSave) self::save_options();
  }


  public static function save_options() {
    if ( is_multisite() ) :
		  update_site_option( 'cc4r_caching_options', self::$options);
		else:
	    update_option( 'cc4r_caching_options', self::$options);
    endif;
    self::$current_settings = self::$options;  // prob never needed
  }


  public static function get_geohdr_name() {
    if ( self::$options['geo_method'] == 'CCA' && isset($_SERVER['HTTP_CF_IPCOUNTRY']) ) return 'CF-IPCountry';
    if ( self::$options['geo_method'] == 'CDN-Clf' ) return 'CF-IPCountry';
    if ( self::$options['geo_method'] == 'CDN-AmzCf' ) return 'CloudFront-Viewer-Country';
    if ( self::$options['geo_method'] == 'CDN-other' && ! empty( self::$options['CDN_geo_httphdr']) ) return self::$options['CDN_geo_httphdr'];
    return FALSE;
  }


  public static function do_htaccess_cookie() {
    if (! self::$options['cc_enabled'] ) : // just in case
        return '';
    endif;
    $CNactive = ( class_exists( 'Cookie_Notice' ) && empty($GLOBALS['CC4R_CN_DEACT']) );  // if not ck for actived will still run on wpr flush when CN deactivated
    $euOnlyCN = ( $CNactive && function_exists( 'cca_is_EU' ) && ! empty(self::$ccax_options['only_EU_cookie']) && ! empty(self::$ccax_options['EU_ccodes'] ) );
    $co_name = CC4R_options::$options['cookie_name'];
    $geohdr = self::get_geohdr_name();
    if ( empty($geohdr) ) :
        if (self::$options['ht_rewrite'] ) :
            $early = '<IfModule mod_setenvif.c>' . PHP_EOL;
            if ( $CNactive ) :
                $early .= 'SetEnvIfNoCase Cookie cookie_notice_accepted=(.+) CNaccept=$1' . PHP_EOL;
            endif;
            $early .= 'SetEnvIfNoCase Cookie ' . $co_name . '=(cc_grp|std|[A-Z0-9]{2})(,|\'|;|$) cc4wprCache=$1' . PHP_EOL .
              '</IfModule>' . PHP_EOL;
            return $early;
        endif;
        return '# no geo hdr' . PHP_EOL;
    endif;

/*
# need to contact wp-rocket.me re CN helper and mod rewrite
# test cn without CCA
$cn_for_eu = FALSE;
if ( class_exists( 'Cookie_Notice' ) && empty($GLOBALS['CC4R_CN_DEACT']) && function_exists( 'cca_is_EU' ) && get_option('cookie_notice_options') && ! empty(self::$ccax_options['only_EU_cookie']) && ! empty(self::$ccax_options['EU_ccodes'] ) ) :
   $cn_for_eu = TRUE;
endif;
*/
    // htaccess for Server/CDN provided country
    $secure = CC4R_options::$options['ssl_cookie'];
	$early = '<IfModule mod_setenvif.c>' . PHP_EOL;
    $coDomain = defined(COOKIE_DOMAIN) ? constant("COOKIE_DOMAIN") : '%{HTTP_HOST}';
	if ( self::$options['cache_iso_cc'] ) :
   		$early .= 'SetEnvIfNoCase ' . $geohdr . ' ^[A-Z0-9]{2}$ cc4wprCache=std' . PHP_EOL;
    else:
        $early .= 'SetEnvIfNoCase ' . $geohdr . ' ^([A-Z0-9]{2})$ cc4wprCache=$1' . PHP_EOL;
    endif;
    if ( self::$options['use_group'] && self::$options['use_group'] ) :
        $grp = str_replace(',' , '|', self::$options['my_ccgroup']);
        $early .= 'SetEnvIfNoCase ' . $geohdr . ' ^(' . $grp . ')$ cc4wprCache=cc_grp' . PHP_EOL;
    endif;
    if ( self::$options['cache_iso_cc'] ) :
        $individ = str_replace(',' , '|', self::$options['cache_iso_cc']);
        $early .= 'SetEnvIfNoCase ' . $geohdr . ' ^(' . $individ . ')$ cc4wprCache=$1' . PHP_EOL;
    endif;

    if ($CNactive  && self::$options['ht_rewrite']) :
        $early .= 'SetEnvIfNoCase Cookie cookie_notice_accepted=(.+) CNaccept=$1' . PHP_EOL;
    endif;

// test cn without CCA
#if ($cn_for_eu && self::$options['ht_rewrite'] ) :
    if ( $euOnlyCN  ) :
        $grp = str_replace(',' , '|', self::$ccax_options['EU_ccodes']);
        $early .= 'SetEnv cc4rCNck true' . PHP_EOL;
        $early .= 'SetEnvIfNoCase ' . $geohdr . ' ^(' . $grp . ')$ cc4rCNck=false' . PHP_EOL;
        $early .= 'SetEnvIfNoCase cc4rCNck true CNaccept=true' . PHP_EOL;
    endif;


    $early .= '  <IfModule mod_rewrite.c>' . PHP_EOL .
       '  RewriteEngine On' . PHP_EOL .
       '  RewriteCond %{ENV:cc4wprCache} .+' . PHP_EOL .
       '  RewriteRule ^ "-" [CO=' . $co_name . ':%{ENV:cc4wprCache}:' . $coDomain . ':144000:/:' . $secure . ':HttpOnly]'  . PHP_EOL;
       
    if ( $CNactive  && self::$options['ht_rewrite']) :
        $early .= '  RewriteCond %{ENV:cc4rCNck} true' . PHP_EOL .
          '  RewriteRule ^ "-" [CO=cookie_notice_accepted:true:' . $coDomain . ':144000:/:0:0]'  . PHP_EOL;
    endif;
    
    $early .=
       '  </IfModule>'  . PHP_EOL .
       '</IfModule>' . PHP_EOL;
    return $early;

// n.b. CO= in htaccess with domain param left empty seems to set transient cookie visible to PHP but not passed to browser
  }


  public static function do_htaccess_rewrite($rules) {
    if (! self::$options['cc_enabled'] || ! self::$options['ht_rewrite'] ) : // just in case
        return $rules;
    endif;      

    $replaceWith = '}-%{ENV:cc4wprCache}.html';
    if ( class_exists( 'Cookie_Notice') && empty($GLOBALS['CC4R_CN_DEACT'])  ) :
       $replaceWith = '}-%{ENV:CNaccept}-%{ENV:cc4wprCache}.html';
    endif;
    
    $suf_pos = strrpos($rules, 'RewriteCond');
    if ($suf_pos === false) return $rules;
    $prefix = substr ( $rules, 0, $suf_pos);
    $suffix = substr ( $rules, $suf_pos );
    $suffix = str_replace('}.html', $replaceWith, $suffix);
    $suffix = 'RewriteCond %{ENV:cc4wprCache} .+' . PHP_EOL . $suffix;
    if ( class_exists( 'Cookie_Notice') && empty($GLOBALS['CC4R_CN_DEACT']) ):
       $suffix = 'RewriteCond %{ENV:CNaccept} .+' . PHP_EOL . $suffix;
    endif;   
    
    return $prefix . $suffix;
  }


  public static function rewrite_enabled() {
	  if ( self::$options['cc_enabled'] && self::$options['ht_rewrite'] ) return TRUE;
		return FALSE;
	}


  public static function update_options($changedSettings) {
	  self::$options = wp_parse_args( $changedSettings, self::$options);
	  self::save_options();
  }

} // end class CC4R_options