<?php
if ( ! defined( 'ABSPATH' ) ) exit;

//********************************************************
// SET CONSTANTS
//********************************************************
define('CC4R_DOCUMENTATION', 'https://wptest.means.us.com/country-geolocation-wp-rocket/');
define('CC4R_UPGRADE_MSG', "");
define('CC4R_SETTINGS_SLUG', 'cc4r-cache-settings');
//define('CC4R_HELPER_VERSION','0.1.0' );
define('CC4R_MAXMIND_DIR', CC4R_PLUGINDIR . 'maxmind/');
define('CC4R_EU_GROUP','BE,BG,CZ,DK,DE,EE,IE,GR,ES,FR,HR,IT,CY,LV,LT,LU,HU,MT,NL,AT,PL,PT,RO,SI,SK,FI,SE,EU,GB' );

  if ( function_exists('rocket_clean_user') ) {
define('CC4R_WPRC_ENABLED',TRUE);
  } else {
define('CC4R_WPRC_ENABLED',FALSE);
  }

//  **** CONSTANTS SHARED WITH OTHER PLUGINS ****
if (!defined('CCA_MAXMIND_DATA_DIR')) define('CCA_MAXMIND_DATA_DIR', WP_CONTENT_DIR . '/cca_maxmind_data/');
if (!defined('CCA_MAX_FILENAME')) define('CCA_MAX_FILENAME', 'GeoLite2-Country.mmdb');
if (!defined('CCA_CUST_IPVAR_LINK')) define('CCA_CUST_IPVAR_LINK', '<a href="//wptest.means.us.com/cca-customize-server-var-lookup/" target="_blank">');
if (!defined('CCA_CUST_GEO_LINK')) define('CCA_CUST_GEO_LINK', '<a href="//wptest.means.us.com/cca-customizing-country-lookup/" target="_blank">');

// plugin version checking
add_action( 'admin_init', 'cc4r_version_mangement' );
function cc4r_version_mangement(){
  $plugin_info = get_plugin_data( CC4R_CALLING_SCRIPT , false, false );  // switch to this line if this function is used from an include
  if ( is_multisite() ) :
    $last_script_ver = get_site_option( 'CC4R_VERSION' );
  else:
    $last_script_ver = get_option('CC4R_VERSION');
  endif;

  if (empty($last_script_ver)):
    // its a new install
    if ( is_multisite() ) :
        update_site_option('CC4R_VERSION', $plugin_info['Version']);
    else:
        update_option('CC4R_VERSION', $plugin_info['Version']);
    endif;
  else:
    $version_status = version_compare( $plugin_info['Version'] , $last_script_ver);
    // can test if script is later {1}, or earlier {-1} than the previous installed e.g. if ($version_status > 0 &&  version_compare( "0.6.3" , $last_script_ver )  > 0) :
    if ($version_status != 0):
        if ( is_multisite() ) :
            update_site_option('CC4R_VERSION_UPDATE', true);
            update_site_option('CC4R_VERSION', $plugin_info['Version']);
        else:
            update_option('CC4R_VERSION_UPDATE', true);update_option('CC4R_VERSION', $plugin_info['Version']);
        endif;
    endif;
  endif;

  if (get_option('CC4R_VERSION_UPDATE') || get_site_option('CC4R_VERSION_UPDATE') ) :   // set just now, or previously set and not yet unset by plugin
    if (is_multisite()):
        add_action( 'network_admin_notices', 'cc4r_upgrade_notice' );
    else:
        add_action( 'admin_notices', 'cc4r_upgrade_notice' );
    endif;
  endif;
}


// add_actiom applied by  version check
function cc4r_upgrade_notice(){
	if ( empty(CC4R_UPGRADE_MSG) ) return;
	if (is_multisite()):
	   $admin_suffix = 'network/admin.php?page=' . CC4R_SETTINGS_SLUG;
	else:
	    $admin_suffix = 'admin.php?page=' . CC4R_SETTINGS_SLUG;
	endif;
	echo '<div class="notice notice-success"><p>' . CC4R_UPGRADE_MSG . ' <a href="' . admin_url($admin_suffix) . '">' . __( 'Dismiss message and go to settings.', 'cc4r' ) . '</a></p></div>';
}


require CC4R_PLUGINDIR . 'classes/cc-settings.class.inc';
CC4R_options::init();

include_once(CC4R_PLUGINDIR . 'inc/cache-as.inc');
include_once(CC4R_PLUGINDIR . 'helper/cca-country-caching.php');  // rocket helper

if ( ! is_admin() ) :
    add_action( 'init',  'set_cc4r_cookie');
else:
    if ( ! class_exists('CCAmaxmindUpdate') ) : include(CC4R_PLUGINDIR . 'inc/update_maxmind.php'); endif;
    include_once(CC4R_PLUGINDIR . 'inc/cc4r_settings_form.php');
endif;
