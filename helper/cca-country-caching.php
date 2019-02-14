<?php
defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

if ( class_exists( 'CC4R_options' )  && CC4R_options::init() ):
    add_filter( 'rocket_cache_mandatory_cookies' , 'rocket_add_cc4r_cookie' , 999 );
    add_filter( 'rocket_cache_dynamic_cookies', 'rocket_add_cc4r_cookie', 999 );
    add_filter( 'before_rocket_htaccess_rules', 'rocket_add_cc4r_early_htaccess', 999 );
    add_filter( 'rocket_htaccess_mod_rewrite', 'rocket_cc4r_alter_rewrite', 999);

    add_action( 'activate_country-caching-for-wp-rocket/cc4r_init.php', 'rocket_cc4r_flush', 11 );
    add_action( 'activate_category-country-aware/cca_init.php', 'rocket_cc4r_flush', 11 );  // cca_textwidget.php  cca_init.php
    add_action( 'deactivate_category-country-aware/cca_init.php', 'rocket_cc4r_flush', 11 ); // cca_textwidget.php
    add_action( 'deactivate_cookie-notice/cookie-notice.php',  'rocket_cc4r_cn_deact', 999 );

    add_action( 'update_option_ccax_options', 'rocket_after_update_ccax_options', 12, 2 );  // no need for _site? individ site settings?
// currently done by CC's own deactivation script uncomment this when helper added to rocket core code
#    add_action( 'deactivate_country-caching-for-wp-rocket/cc4r_init.php', 'rocket_country_caching_disabled', 11 );

    if ( ! is_multisite() ) :
        add_action( 'update_option_cc4r_caching_options', 'rocket_after_update_cc4r_caching_options', 10, 2 );
    else:
        add_action( 'update_site_option_cc4r_caching_options', 'rocket_after_update_cc4r_caching_options', 10, 2 );  // maybe unnecessary
    endif;
endif;

/**
 * Return the cookie name set by Country Caching plugin
 *
 * @param $cookies array List of mandatory or dynamic cookies
 * @return array List of mandatory or dynamic cookies with the cc4r_geo cookie appended
 */
function rocket_add_cc4r_cookie( $cookies ) {
    if ( CC4R_options::init()  && CC4R_options::is_enabled() ) :
        $cookies[] = CC4R_options::$options['cookie_name'];
    endif;
    return $cookies;
}


// when CC plugin's options are changed
function rocket_after_update_cc4r_caching_options( $old_value, $value ) {
    $modify_rocket = FALSE;
    if ( empty($old_value['cc_enabled']) != empty($value['cc_enabled']) ) :
        $modify_rocket = TRUE;
    elseif ( empty( $old_value['use_group'] ) != empty( $value['use_group'] ) ) :
        $modify_rocket = TRUE;
    elseif ( empty( $old_value['ht_rewrite'] ) != empty( $value['ht_rewrite'] ) ) :
        $modify_rocket = TRUE;
    elseif ( isset( $old_value['cache_iso_cc'], $value['cache_iso_cc'] ) && ( $old_value['cache_iso_cc'] != $value['cache_iso_cc'] )) :
        $modify_rocket = TRUE;
    elseif ( isset( $old_value['my_ccgroup'], $value['my_ccgroup'] ) && ( $old_value['my_ccgroup'] != $value['my_ccgroup'] )) :
        $modify_rocket = TRUE;
    elseif ( isset( $old_value['geo_method'], $value['geo_method'] ) && ( $old_value['geo_method'] != $value['geo_method'] )) :
        $modify_rocket = TRUE;
#    elseif ( isset( $old_value['CDN_geo_hdr'], $value['CDN_geo_hdr'] ) && ( $old_value['CDN_geo_hdr'] != $value['CDN_geo_hdr'] )) :
        $modify_rocket = TRUE;
    elseif ( isset( $old_value['CDN_geo_svar'], $value['CDN_geo_svar'] ) && ( $old_value['CDN_geo_svar'] != $value['CDN_geo_svar'] )) :
        $modify_rocket = TRUE;
    elseif ( isset( $old_value['CDN_geo_httphdr'], $value['CDN_geo_httphdr'] ) && ( $old_value['CDN_geo_httphdr'] != $value['CDN_geo_httphdr'] )) :
        $modify_rocket = TRUE;
    endif;
    if ( $modify_rocket && empty( $value['cc_enabled'] ) ): 
        rocket_cc4r_disabled();
    elseif ($modify_rocket && ! empty( $value['cc_enabled'] ) ):
        rocket_cc4r_flush();
    endif;
}


// CC & CCA country group can be synchronised so change in CCA may need a flush
function rocket_after_update_ccax_options( $old_value, $value ){
    if ( empty($old_value['only_EU_cookie']) == empty($value['only_EU_cookie']) && isset( $old_value['EU_ccodes'], $value['EU_ccodes'] ) && $old_value['EU_ccodes'] == $value['EU_ccodes']) :
        return;
    endif;
    rocket_cc4r_flush();
}



// used by de/activate and option update functions
function rocket_cc4r_flush() {
    if (function_exists('flush_rocket_htaccess')):  // just in case whilst helper not in core
        // Update the WP Rocket rules on the .htaccess file.
        if ( function_exists('get_home_path')) flush_rocket_htaccess(); // if get_home_path function is not loaded Rocket code would 500 error on dashboard
        // Regenerate the config file.
        rocket_generate_config_file();
        // Clear WP Rocket cache
        rocket_clean_domain();
    endif;
}



function rocket_cc4r_cn_deact() {
    $GLOBALS['CC4R_CN_DEACT'] = TRUE;  // until deactivation finishes CN's class continues to exist so CC4R neeeds something else to identify it is being deactivated
    remove_filter( 'rocket_htaccess_mod_rewrite', 'rocket_cc4r_alter_rewrite' );
    remove_filter( 'before_rocket_htaccess_rules', 'rocket_add_cc4r_early_htaccess' );
    rocket_cc4r_flush();
}


function rocket_cc4r_disabled() {
    remove_filter( 'rocket_cache_dynamic_cookies', 'rocket_add_cc4r_cookie' );
    remove_filter( 'rocket_cache_mandatory_cookies', 'rocket_add_cc4r_cookie' );
    remove_filter( 'rocket_htaccess_mod_rewrite', 'rocket_cc4r_alter_rewrite' );
    remove_filter( 'before_rocket_htaccess_rules', 'rocket_add_cc4r_early_htaccess' );
    rocket_cc4r_flush();
}


function rocket_add_cc4r_early_htaccess($early) {
    if ( CC4R_options::init()  && CC4R_options::is_enabled() ) :
       return CC4R_options::do_htaccess_cookie() . $early;
    endif;
    return $early;
}


function rocket_cc4r_alter_rewrite($rules) {
    if ( CC4R_options::init()  && CC4R_options::is_enabled() ):
        if ( ! CC4R_options::$options['ht_rewrite'] ) :
            return FALSE;
        elseif ( strpos( $rules , 'ENV:cc4wprCache') === FALSE ) :
            return CC4R_options::do_htaccess_rewrite($rules);
        endif;
    endif;
    return $rules;
}

