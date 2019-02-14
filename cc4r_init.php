<?php
/*
Plugin Name: Country Caching Extension For WP Rocket
Plugin URI: http://means.us.com
Description: Makes Country GeoLocation work with WP Rocket 
Version: 0.0.5
Author: Andrew Wrigley
Author URI: https://means.us.com/
Contributors: wrigs1, senlin
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// for developer's version update testing only (for insertion into currently installed file do not uncomment here) 
/*
require (WP_CONTENT_DIR . '/plugin-update-checker/plugin-update-checker.php');  // you wont have this
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'http://blog.XXXXXXXX.com/meta_cc4r.json',
		__FILE__,
		'country-caching-extension-for-wp-rocket'
);
*/


define('CC4R_PLUGINDIR',plugin_dir_path(__FILE__));

if( is_admin() ):
	  define('CC4R_CALLING_SCRIPT', __FILE__);
endif;
require_once 'country_cache_wpr.php';
?>