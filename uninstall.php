<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();
delete_option('CC4R_VERSION');
delete_option('CC4R_VERSION_UPDATE');
delete_option('cc4r_caching_options');

if ( function_exists('delete_site_option') ) :
    delete_site_option('CC4R_VERSION');
    delete_site_option('CC4R_VERSION_UPDATE');
    delete_site_option('cc4r_caching_options');
endif;