<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// determine whether a normal or multisite settings form link is required

$cc_networkadmin = is_network_admin() ? 'network_admin_' : '';

// Add settings link on Dashboard->plugins page
add_filter( $cc_networkadmin . 'plugin_action_links_' . plugin_basename( CC4R_CALLING_SCRIPT ), 'cc4r_add_sitesettings_link' );
function cc4r_add_sitesettings_link( $links ) {
	if (is_multisite()):
	    $admin_suffix = 'network/settings.php?page=' . CC4R_SETTINGS_SLUG;
#	    $admin_suffix = 'network/admin.php?page=' . CC4R_SETTINGS_SLUG;
	else:
	    $admin_suffix = 'options-general.php?page=' . CC4R_SETTINGS_SLUG;
	endif;
	return array_merge(array('settings' => '<a href="' . admin_url($admin_suffix) . '">' . __( 'Country Caching Settings', 'cc4r' ) . '</a>'), $links	);
}


// ensure CSS for dashboard forms is sent to browser
add_action( 'admin_enqueue_scripts', 'cc4r_load_custom_wp_admin_style' );

function cc4r_load_custom_wp_admin_style() {
    if( ! wp_script_is( 'cc4r_admin', 'enqueued' ) ) {
	    wp_register_style( 'cc4r_admin', plugins_url( 'css/admin.css', __FILE__ ), false, '0.0.3' );
	    wp_enqueue_style( 'cc4r_admin' );
	}
}


// WP only shows messages on CC settings page
// so to automatically display admin notice messages when using the add_menu_page (like WP does for add_options_page 's)
function cc4r_admin_notices_action() {  // unlike add_options_page when using add_menu_page the settings api does not automatically display these messages
    settings_errors( 'cc4r_group' );
}
if (is_multisite()) add_action( 'network_admin_notices', 'cc4r_admin_notices_action' );



// return permissions of a directory or file as a 4 character "octal" string
function cc4r_return_permissions($item) {
 clearstatcache(true, $item);
 $item_perms = @fileperms($item);
return empty($item_perms) ? '' : substr(sprintf('%o', $item_perms), -4);
}

// INSTANTIATE OBJECT
$cc4r_settings_page = new CC4RcountryCache();

/*===============================================
CLASS FOR SETTINGS FORM AND GENERATION OF ADD-ON SCRIPT
================================================*/
class CC4RcountryCache {   // everything beyond this point this class
//======================
//  private $ccInst;
  public $options = array();
  public $user_type;
  public $submit_action;

  public function __construct() {
	register_activation_hook(CC4R_CALLING_SCRIPT, array( $this, 'CC4R_activate' ) );
	register_deactivation_hook(CC4R_CALLING_SCRIPT, array( $this, 'CC4R_deactivate'));

    $this->options = CC4R_options::$options;
    if (is_multisite()):
        $this->is_plugin_update = get_site_option( 'CC4R_VERSION_UPDATE' );
    else:
        $this->is_plugin_update = get_option( 'CC4R_VERSION_UPDATE' );
    endif;

// this and maxmind update script may need modification for multisite option set/get
$this->maxmind_status = get_option('cc_maxmind_status', array()); // Maxmind is used by other plugins so we store its status in an option

// set-up settings menu for single or multisite
    if (is_multisite() ) :
        $this->user_type = 'manage_network_options';
        add_action( 'network_admin_menu', array( $this, 'add_plugin_page' ) );
    	$this->submit_action = "../options.php";
    else:
    	 $this->user_type = 'manage_options';
         add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
    	 $this->submit_action = "options.php";
    endif;
    add_action( 'admin_init', array( $this, 'page_init' ) );

/* v0.0.4 pre multisite changes
    add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
    add_action( 'admin_init', array( $this, 'page_init' ) );
*/

  } // end construct


	// REMOVE EXTENSION SCRIPT ON DEACTIVATION
  public function CC4R_deactivate()   {
	$this->options['is_activated'] = '0';
    CC4R_options::update_options($this->options);
    remove_filter( 'rocket_cache_dynamic_cookies', 'rocket_add_cc4r_cookie', 999 );
    remove_filter( 'rocket_cache_mandatory_cookies', 'rocket_add_cc4r_cookie', 999 );
    remove_filter( 'rocket_htaccess_mod_rewrite', 'rocket_cc4r_alter_rewrite',999 );
    remove_filter( 'before_rocket_htaccess_rules', 'rocket_add_cc4r_early_htaccess', 999 );

// zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz
    if (function_exists('flush_rocket_htaccess')):  // just in case; whilst helper not in Rocket core
        // Update the WP Rocket rules on the .htaccess file.
        flush_rocket_htaccess();
        // Regenerate the config file.
        rocket_generate_config_file();
        // Clear WP Rocket cache
        rocket_clean_domain();
    endif;

  }


	//  ACTIVATION/RE-ACTIVATION
  public function CC4R_activate() {
	$this->options['initial_message'] = '';
	if ( $this->options['cc_enabled'] && ! $this->ensure_maxfile_present() ) :
	    if(empty($_SERVER["HTTP_CF_IPCOUNTRY"])): $this->options['initial_message'] .= __( 'There was a problem installing new Maxmind2 mmdb file. Click the "CC Info" tab for more info.<br>', 'cc4r' ); endif;
	endif;
	$this->options['is_activated'] = '1';
    CC4R_options::update_options($this->options);
  }  //  END CC4R_activate()


  // Add Country Caching options page to Dashboard
  public function add_plugin_page() {
    if ( ! is_multisite() ) :
        add_options_page(
            __( 'Country Caching Settings', 'cc4r' ), /* html title tag */
            __( 'WP Rocket Country Caching', 'cc4r' ), // title (shown in dashboard menu).
            'manage_options',  // min user authority
            CC4R_SETTINGS_SLUG, // page url slug
            array( $this, 'create_cc4r_site_admin_page' )  //  function/method to display settings menu
        );
    else:
        add_menu_page(
            __( 'Country Caching Settings', 'cc4r' ), /* html title tag */
            __( 'WP Rocket Country Caching', 'cc4r' ), // title (shown in dash->Settings).
            $this->user_type, // 'manage_options', // min user authority
            CC4R_SETTINGS_SLUG, // page url slug
            array( $this, 'create_cc4r_site_admin_page' ), //  function/method to display settings menu
            'dashicons-admin-plugins' );
    endif;
  }



  // Register and add settings
  public function page_init() {
    register_setting(
      'cc4r_group', // group the field is part of
      'cc4r_caching_options',  // option prefix to name of field
	  array( $this, 'sanitize' )
    );
  }


  // callback func specified in add_options_page func
  // THE SETTINGS FORM FRAMEWORK (renders setting form tabs and call methods for current tab contents)
  public function create_cc4r_site_admin_page() {
    // if site is not using Cloudflare GeoIP warn if Maxmind data is not installled
    if ( empty($_SERVER["HTTP_CF_IPCOUNTRY"]) && ! file_exists(CCA_MAXMIND_DATA_DIR . CCA_MAX_FILENAME) ) :
	  $this->options['initial_message'] .= __( 'Maxmind country look-up data "GeoLite2-Country.mmdb" needs to be installed. It will be installed automatically if the "Enable CC" check box is checked and you save your settings. This may take a few seconds.', 'cc4r' ) . '<br>';
	  if (file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat') || file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIPv6.dat') ) :
	    $this->options['initial_message'] .= __( 'Out of date Maxmind Legacy files were were installed by an earlier CC version and will continue to be used until you re-save your your settings.', 'cc4r' ) . '<br>';
	  endif;
    endif;

   // render settings form
?>  <div class="wrap cca-cachesettings">
      <div id="icon-themes" class="icon32"></div>
      <h2><?php _e( 'WP Rocket Country Caching', 'cc4r' ); ?></h2>
<?php
    if (!empty($this->options['initial_message'])) echo '<div class="cca-msg">' . $this->options['initial_message'] . '</div>';
    $this->options['initial_message'] = '';
	$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'WPRC';
?>
      <h2 class="nav-tab-wrapper">
         <a href="?page=<?php echo CC4R_SETTINGS_SLUG ?>&tab=WPRC" class="nav-tab <?php echo $active_tab == 'WPRC' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Cache Settings', 'cc4r' ); ?></a>
				 <a href="?page=<?php echo CC4R_SETTINGS_SLUG ?>&tab=Geo" class="nav-tab <?php echo $active_tab == 'Geo' ? 'nav-tab-active' : ''; ?>"><?php _e( 'GeoIP Settings', 'cc4r' ); ?></a>
				 <a href="?page=<?php echo CC4R_SETTINGS_SLUG ?>&tab=CNusers" class="nav-tab <?php echo $active_tab == 'CNusers' ? 'nav-tab-active' : ''; ?>"><?php _e( 'EU only Cookie Notice', 'cc4r' ); ?></a>
         <a href="?page=<?php echo CC4R_SETTINGS_SLUG ?>&tab=Configuration" class="nav-tab <?php echo $active_tab == 'Configuration' ? 'nav-tab-active' : ''; ?>"><?php _e( 'CC Info', 'cc4r' ); ?></a>
      </h2>

<form method="post" action="<?php echo $this->submit_action; ?>">  
<!--      <form method="post" action="options.php"> -->

<?php
    settings_fields( 'cc4r_group' );
  	if( $active_tab == 'Configuration' ) :
  	    $this->render_config_panel();
   	elseif ($active_tab == 'Geo'):
   	    $this->render_geo_panel();
   	elseif ($active_tab == 'CNusers'):
   	    $this->render_cookienotice_panel();
  	else :
  	    $this->render_wprc_panel();
  	endif;
?>

<input type="hidden" name="cc4r_caching_options[action]" value="<?php echo $active_tab; ?>" />


      </form>
    </div>
<?php
//    CC4R_options::update_options($this->options);  // returned from santize method
  }  //  END create_cc4r_site_admin_page()


  // render the Settings Form Tab for building the WP Rocket helper script
  public function render_wprc_panel() {
    if (!empty($this->is_plugin_update)) :   // we've retrieved its value and displayed msg; re-set option to false
        if (is_multisite()):
            update_site_option('CC4R_VERSION_UPDATE', false);
        else:
            update_option('CC4R_VERSION_UPDATE', false);
        endif;
    endif;
    
	$this->is_plugin_update = FALSE;
	?>
	<div class="cca-brown"><p><?php echo $this->cc4r_wprc_status();?></p></div>
    <hr><h3><?php _e( 'Enable Country Caching for WP Rocket', 'cc4r' ); ?></h3>
	<p><input type="checkbox" id="cc4r_use_cc4r_wprc" name="cc4r_caching_options[cc_enabled]" <?php checked(!empty($this->options['cc_enabled']));?>><label for="cc4r_use_cc4r_wprc">
	<?php _e( 'Enable Country Caching', 'cc4r' ); ?></label></p>

	<p class="cca-indent20"> &nbsp; <input type="checkbox" id="cc4r_ssl_cookie" name="cc4r_caching_options[ssl_cookie]" <?php
      if (! empty($this->options['ssl_cookie']) ) echo 'checked="checked"'; _e( '> SSL Cookie. <i>Enable this if site is "HTTPS" only. Otherwise MUST be left unchecked.</i>', 'cc4r' ); ?></p>

	<p class="cca-indent20"> &nbsp; <input type="checkbox" id="cc4r_ht_rewrite" name="cc4r_caching_options[ht_rewrite]" <?php
      if (! empty($this->options['ht_rewrite']) ) echo 'checked="checked"'; printf( __( '> Modify Rocket rewrite for improved performance. See <a href="%s" target="_blank" rel="noopener">Performance</a>.', 'cc4r' ), CC4R_DOCUMENTATION . '#perf' ); ?>
    </p>

    <hr><h3><?php _e( 'Optional Settings to reduce caching overheads', 'cc4r' ); ?></h3>


	<p>
		<strong><?php _e( 'Use same standard cache for most countries, EXCEPT:', 'cc4r' ); ?></strong>
	</p>

	<p class="cca-indent20">
		<?php
		printf(
			__( 'Only create unique cache files for these <a href="%s" target="_blank">country codes</a>:', 'cc4r' ),
				'https://www.iso.org/obp/ui/#search/code/'
		);
		?>
		<input name="cc4r_caching_options[cache_iso_cc]" type="text" value="<?php echo $this->options['cache_iso_cc']; ?>" />
		<?php echo '<i>(' . __( 'e.g.', 'cc4r' ) . ' "CA,DE,AU")</i>'; ?>
	</p>

	<p class="cca-indent20"><?php _e( 'If left empty a cached page would be generated for every country from which you\'ve had one or more visitors.', 'cc4r' );?><br>
	<i><?php _e('Example 1: if you set the field to "CA,AU", separate cache will only be created for Canada and for Australia; "standard" page cache will be used for all other visitors.');?></i></p>

	<br><p><b><?php _e( 'Create a single cache for this group of countries', 'cc4r' ); ?></b></p>
	<p class="cca-indent20"><input type="checkbox" id="cc4r_use_group" name="cc4r_caching_options[use_group]" <?php checked(!empty($this->options['use_group']));?>><label for="cc4r_use_group">
<?php _e( 'Enable shared caching for this group:', 'cc4r' ); ?></label></p>
<?php if (empty($this->options['my_ccgroup'])):
		  $this->options['my_ccgroup'] = CC4R_EU_GROUP;
	  endif;
?>
	  <div class="cca-indent20">
  	    <input id="cc4r_my_ccgroup" name="cc4r_caching_options[my_ccgroup]" type="text" style="width:600px !important" value="<?php echo $this->options['my_ccgroup']; ?>" />
  		  <br>
  		  <?php _e( 'Replace with your own list. (Initially contains European Union countries, but no guarantee it is accurate.)', 'cc4r' );  ?>
	  </div>

	  <p><i><?php _e( 'Example 2: You display custom content to visitors from Mexico & Canada, all other visitors are served your default "US content". Additionally you display a cookie notice for EU visitors.', 'cc4r' ); ?>
<br><?php _e( '<b>To cache:</b> set the plugin to separately cache "MX,CA", ensure the group box contains all EU countries; and enable shared caching.', 'cc4r' ); ?></i></p>

      <p><i>
	  <?php
		  _e( 'Example 3: You only <b>want 2 separate caches <u>one for Group and one for NOT Group</u></b> e.g. EU and non-EU: ', 'cc4r' );
		  _e( '<b><u>insert "AX" in the "create unique cache" box</u></b>, ensure group box contains all EU codes, and enable shared caching.', 'cc4r' );
		  _e( '<br>Result: one cache for EU visitors, a cache for AX (if you ever get a visitor from Aland Islands), ', 'cc4r' );
		  _e( 'and one standard cache seen by your non-EU visitors.', 'cc4r' );
	  ?>
	  </i></p>

	<input type="hidden" id="cc4r_geoip_action" name="cc4r_caching_options[action]" value="WPRC" />
<?php
   submit_button( __( 'Save these settings', 'cc4r' ), 'primary', 'submit', TRUE, array( 'class' => 'cc4r-save-button' ) );

    if( $this->options['cc_enabled'] ):
	  _e('<br><p><i>This plugin includes GeoLite data created by MaxMind, available from <a href="http://www.maxmind.com">http://www.maxmind.com</a>.</i></p>');
    endif;
  }  // END function render_wprc_panel



	// render panel to select Geo Lookup system
	public function render_geo_panel() {
?>
		<h3 class="cap"><?php _e( 'To ensure accurate messages on this page view it over the internet.', 'cc4r' ); ?></h3>
		<p class="cca-brown">
			<i><b>
			<?php _e( 'Do not view via a direct server/intRAnet connection with a private/reserved IP address (treated as invalid/unknown); or that bypasses intermediate servers like Cloudflare.', 'cc4r' ); ?>
			</b></i>
		</p>

		<input type="hidden" id="cc4r_geoip_action" name="cc4r_caching_options[action]" value="Geo" />

		<hr />

		<h3><?php _e( 'Identify Visitor using:', 'cc4r' ); ?></h3>

		<p>
			<input type="radio" id="cc4r_geocca" name="cc4r_caching_options[geo_method]" value="CCA" <?php checked($this->options['geo_method'],'CCA');?>>
			<label for="cc4r_geocca"><?php _e( 'Use Maxmind look-up file installed by this plugin (if no Cloudflare header). Works with "all" servers.', 'cc4r' ); ?></label>
			<br>
		</p>

		<p>
			<input type="radio" id="cc4r_cdnclf" name="cc4r_caching_options[geo_method]" value="CDN-Clf" <?php checked($this->options['geo_method'],'CDN-Clf');?>>
			<label for="cc4r_cdnclf"><?php _e( 'Cloudflare (used by many WP Country plugins).', 'cc4r' ); ?></label>
			<br>
		</p>

		<p>
		<?php if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) :
			echo  '<i class="cca-indent20 cca-green">' . __( 'Cloudfare Country Header detected', 'cc4r' ) . '</i>';

		else:

			echo '<p class="cca-indent20 cca-brown"><i>';

			printf( __( 'Header not detected. Either 1. you not using Cloudflare; or 2. you\'ve not <a href="%s" rel="noopener" target="_blank">set CF to provide Country Headers</a>;', 'cc4r' ) . '<br> ',
				'https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-Cloudflare-IP-Geolocation-do'
			);

			echo __( '&nbsp; or 3. you\'re currently using a local connection bypassing the CDN.', 'cc4r' );

			echo '</i><br></p>';
		endif;
		?>
		</p>

		<p>
			<input type="radio" id="cc4r_cdnamz" name="cc4r_caching_options[geo_method]" value="CDN-AmzCf" <?php checked($this->options['geo_method'],'CDN-AmzCf');?>>
			<label for="cc4r_cdnamz"><?php _e( 'Amazon Cloudfront.', 'cc4r' ); ?></label>
			<br>
		</p>

		<?php if (isset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'])) :

			echo  '<i class="cca-indent20 cca-green">' . __( '(Amazon Cloudfront Country Header detected)', 'cc4r' ) . '</i>';

		else:

			echo '<p class="cca-indent20 cca-brown"><i>';

			printf( __( 'Header not detected. Either 1. you not using Amazon CF; or 2. you\'ve not <a href="%s" rel="noopener" target="_blank">set it to provide Country Headers</a>;', 'cc4r' ) . '<br>',
				'https//aws.amazon.com/blogs/aws/enhanced-cloudfront-customization/'
			);
			echo __( '&nbsp; or 3. you\'re currently using a local connection bypassing the CDN.', 'cc4r' );

			echo '</i><br></p>';
		endif;
		?>

		<p>
			<input type="radio" id="cc4r_cdnother" name="cc4r_caching_options[geo_method]" value="CDN-other" <?php checked($this->options['geo_method'],'CDN-other');?>>
			<label for="cc4r_cdnother">
			<?php
				_e( 'Other CDN or server that provides ISO Country Code in $_SERVER variable and HTTP Header. ', 'cc4r' );
				printf( 
					__( '<a href="%s" rel="noopener" target="_blank">(See Guide)</a>', 'cc4r' ),
					CC4R_DOCUMENTATION . '#geohdr'
				)
			?>
			</label>
		</p>

		<div class="cca-indent20"><p>
<!-- CDN_geo_hdr  changed to CDN_geo_svar or CDN_geo_httphdr as applicable throughout code -->
			&nbsp; <input name="cc4r_caching_options[CDN_geo_svar]"  type="text" value="<?php echo empty($this->options['CDN_geo_svar'])?'':$this->options['CDN_geo_svar']; ?>" />
			<?php
				_e( '$_SERVER variable name provided by CDN/server e.g. Apache & Litespeed servers often use <i>GEOIP_COUNTRY_CODE</i>. This variable is used in PHP/WordPress code.', 'cc4r' );
				if ( ! empty( $this->options['CDN_geo_svar'] ) ) :
					if ( isset($_SERVER[$this->options['CDN_geo_svar']])) :
						echo '<br><i class="cca-green">' . __( 'Variable detected.', 'cc4r' ) . '</i>';
					else:
						echo '<br><i class="cca-brown">' . __( 'Variable not detected.', 'cc4r' ) . '</i>';
					endif;
				endif;
			?>
		</p></div>
		<div class="cca-indent20"><p>
		<?php
		_e( 'and if different a different name is used in HTTP header:', 'cc4r' );
		?>
			</p><p></p>&nbsp; <input name="cc4r_caching_options[CDN_geo_httphdr]"  type="text" value="<?php echo empty($this->options['CDN_geo_httphdr'])?'':$this->options['CDN_geo_httphdr']; ?>" />
			<?php
				_e( 'HTTP header name provided by CDN/server. e.g. Apache & Litespeed often use <i>GEOIP_COUNTRY_CODE</i> This variable is used in .htaccess for faster page serving', 'cc4r' );
				if ( ! empty( $this->options['CDN_geo_httphdr'] ) && function_exists('getallheaders') ) :
				    if ( array_key_exists( $this->options['CDN_geo_httphdr'] , getallheaders()) ) : echo '<br><i class="cca-green">' . __( 'HTTP header detected.', 'cc4r' ) . '</i>'; endif;
				endif;
			?>
		</p></div>
		<p>
			<input type="radio" id="cc4r_othergeo" name="cc4r_caching_options[geo_method]" value="other_geo" <?php checked($this->options['geo_method'],'other_geo');?>>
			<label for="cc4r_othergeo">

			<?php
				_e( 'Use filter to connect to the geolocation used by another WP Country plugin. ', 'cc4r' );

				printf(
					__( '<a href="%s" rel="noopener" target="_blank">(Instructions)</a>', 'cc4r' ),
						CC4R_DOCUMENTATION . '#geoconf'
				);
			?>
			</label>
		</p>

		<p>
			<?php
				$filter_check = apply_filters( 'cc_use_other_geoip', FALSE );

				if ( !$filter_check) :

					echo '<i class="cca-brown">Note: no filter is activated at present. See instructions</i>';

				elseif ( ctype_alnum($filter_check) && strlen($filter_check) == 2 ):

					echo '<i class="cca-indent20 cca-green">' . __( 'An active filter providing a valid country code has been detected.', 'cc4r' ) . '</i>';

				else:

					printf( '<i class="cca-indent20 cca-green">' . __( 'An active filter was detected but it is returning an invalid code. (%s)', 'cc4r' ) . '</i>',
						esc_html( $filter_check )
					);

				endif;
			?>

		</p>

	    <hr />
		<?php
			submit_button( __( 'Save these settings', 'cc4r' ), 'primary', 'submit', TRUE, array( 'class' => 'cc4r-save-button' ) );
	} // END function render_geo_panel

	// render info panel for cookie notice users
	public function render_cookienotice_panel() {

		echo '<input type="hidden" id="cc4r_geoip_action" name="cc4r_caching_options[action]" value="CNusers" />';

		echo '<hr>';

		printf(
			'<h3>' . __( 'If you are using the <a href="%s" rel="noopener" target="_blank">Category Country Aware (CCA)</a> plugin:', 'cc4r' ) . '</h3>',
				'https://wptest.means.us.com/cca-plugin-how-to-guide/'
		);

	?>

		<p>
			 &nbsp; <input type="checkbox" id="cc4r_CCAsync" name="cc4r_caching_options[CCAsync]" <?php if (! empty($this->options['CCAsync']) ) echo 'checked="checked"'; ?>>
			 <label for="cc4r_CCAsync"> <?php _e( 'Synchronise with CCA (<i>recommended</i>)', 'cc4r' ); ?></label>
		<p>

		<p class="cca-indent20"><i>
			<?php _e( 'If this option is selected and you have enabled CC Group caching then the list of countries will always be set and updated from the Group/"EU" country list you defined in the CCA plugin.', 'cc4r' ); ?>
		</i></p>

		<hr>

		<?php
		printf(
			'<h3>' . __( 'If you have <a href="%1$s" rel="noopener" target="_blank">set the CCA plugin</a> to
		prevent <a href="%2$s" rel="noopener" target="_blank">Cookie Notice</a> running for non EU countries:', 'cc4r' ) . '</h3>',
				'https://wptest.means.us.com/european-cookie-law-bar/',
				'https://wordpress.org/plugins/cookie-notice/'
		);

		?>

		<p>
			&nbsp; <input type="checkbox" id="cc4r_only4CN" name="cc4r_caching_options[only4CN]" <?php if (! empty($this->options['only4CN']) ) echo 'checked="checked"'; ?>>
			<label for="cc4r_only4CN"> <?php _e( 'Auto configure Country Caching ( I am <u>ONLY</u> using Country Geolocation for EU only Cookie Notice )', 'cc4r' ); ?></label>
		<p>

		<p class="cca-indent20"><i>
			<?php _e( 'This sets CC\'s Cache Settings for you, and switches on CCA synchronisation.', 'cc4r' ); ?>
		</i></p>

		<?php
		printf(
			'<p>' . __( 'If you are using Country Geolocation for Cookie Notice <u>AND</u> other country specific content then <a href="%s" rel="noopener" target="_blank">see these examples of CC Cache Settings</a>.', 'cc4r' ) . '</p>',
				CC4R_DOCUMENTATION . '#ohead'
		);

		echo '<hr>';

		submit_button( __( 'Save these settings', 'cc4r' ), 'primary', 'submit', TRUE, array( 'class' => 'cc4r-save-button' ) );
	}  //  END function render_cookienotice_panel



	// render tab panel for monitoring and diagnostic information
	public function render_config_panel() {
	    $reset_info_settings = FALSE;
		echo '<input type="hidden" id="cc4r_geoip_action" name="cc4r_caching_options[action]" value="Configuration" />'; ?>

		<?php
		printf( '<p class="cca-brown">' . __( 'View the <a href="%s" rel="noopener" target="_blank">Country Caching Guide</a>.', 'cc4r' ) . '</p>',
			CC4R_DOCUMENTATION
		);
		?>

		<p>
			<strong class="cap"><?php _e( 'Ensure you view this page over the internet.', 'cc4r' ); ?></strong><br>
			<i class="cca-brown"><?php _e( 'Do not view via a direct server/intRAnet connection with a private/reserved IP address (treated as invalid/unknown); or that bypasses intermediate servers like Cloudflare.', 'cc4r' ); ?></i>
		</p>

		<hr>

		<h3><?php _e( 'Problem Fixing', 'cc4r' ); ?></h3>

		<p>
			<input id="cc4r_force_reset" name="cc4r_caching_options[force_reset]" type="checkbox"/>
			<label for="cc4r_force_reset"><?php _e( 'Reset CCA Country Caching to initial values.', 'cc4r' );?></label>
		</p>

		<?php submit_button( __( 'Reset Now', 'cc4r' ), 'primary', 'submit', TRUE, array( 'class' => 'cc4r-save-button' ) );?>

		<hr>

		<h3><?php _e( 'Testing', 'cc4r' ); ?></h3>
		<p>
			<input id="cc4r_test_mode" name="cc4r_caching_options[test_mode]" type="checkbox" <?php checked(!empty($this->options['test_mode']));?>>
			<label for="cc4r_test_mode">
			<?php
			// Translators: this prints <head> in code wrappers
			printf( __( 'Provide caching information in HTML source (<i>see meta tag "cc4r:testing" in %s section</i>)', 'cc4r' ),
				'<code>&lt;head&gt;</code>'
			);
			?>
			</label>
		</p>
		<?php
		// Translators: this prints the actual shortcode
		printf( '<p>' . __( 'N.B. You can also use shortcode %s to display caching information on selected pages or posts.', 'cc4r' ) . '</p>',
			'<code>[cc4r_test_msg]</code>'
		);
		?>
		<hr>


		<h3><?php _e( 'GeoIP Information and Status:', 'cc4r' ); ?></h3>
		<p>
			<input type="checkbox" id="cc4r_geoip_info" name="cc4r_caching_options[geoip_data]" >
			<label for="cc4r_geoip_info"><?php _e( 'Display GeoIP data', 'cc4r' ); ?></label>
		</p>
		<?php
		// display GeoIP info
		if (! empty($this->options['geoip_data']) ) :
		    $this->options['geoip_data'] = '';
		    $this->get_geo_status();
		    $reset_info_settings = TRUE;
		endif;
		?>
		<hr>


		<h3><?php _e( 'List $_SERVER variables on this site:', 'cc4r' ); ?></h3>
		<p>
			<input type="checkbox" id="cc4r_svar_info" name="cc4r_caching_options[svar_data]" >
			<label for="cc4r_svar_info"><?php _e( 'List $_SERVER variables', 'cc4r' ); ?></label>
		</p>
		<?php
		// display $_SERVER vars
		if (! empty($this->options['svar_data']) ) :
		    $this->options['svar_data'] = '';
		    while (list($var,$value) = each ($_SERVER)) { echo "$var => $value<br>";   }
		    $reset_info_settings = TRUE;
		endif;
		?>
		<hr>


		<h3><?php _e( 'List HTTP request headers passed to this site:', 'cc4r' ); ?></h3>
		<p>
			<input type="checkbox" id="cc4r_hdr_info" name="cc4r_caching_options[hdr_data]" >
			<label for="cc4r_hdr_info"><?php _e( 'List HTTP headers', 'cc4r' ); ?></label>
		</p>
		<?php
		// display HTTP request hdrs
		if (! empty($this->options['hdr_data']) && function_exists('getallheaders') ) :
		    $this->options['hdr_data'] = '';
		    foreach (getallheaders() as $name => $value) { echo "$name => $value<br>";}
		    $reset_info_settings = TRUE;
		endif;
		?>
		<hr>

		<h3><?php _e( 'Information useful for support requests:', 'cc4r' ); ?></h3>
		<p>
			<input type="checkbox" id="cc4r_diagnostics" name="cc4r_caching_options[diagnostics]">
			<label for="cc4r_diagnostics"><?php _e( 'List plugin values/Maxmind Health/File Permissions', 'cc4r' ); ?></label>
		</p>
		<?php
		if ($this->options['diagnostics']) :
			$this->options['diagnostics'] = '';
			$reset_info_settings = TRUE;
			echo '<h4><u>' . __( 'WP Rocket Status:', 'cc4r' ) . '</u></h4>';
			echo '<div class="cca-brown">' . $this->cc4r_wprc_status() . '</div>';
			echo '<h4><u>' . __( 'Software Versions:', 'cc4r' ) . '</u></h4>';

			printf( __( 'PHP: %s', 'cc4r' ), phpversion() );
			printf( '<br>' . __( 'WP Rocket: %s', 'cc4r' ), $this->wpr_activated_version() );

            if (is_multisite()):
            			printf( '<br>' . __( 'CC for WP Rocket: %s', 'cc4r' ), get_site_option('CC4R_VERSION') );
            else:
            			printf( '<br>' . __( 'CC for WP Rocket: %s', 'cc4r' ), get_option('CC4R_VERSION') );
            endif;

			echo '<h4><u>' . __( 'Constants:', 'cc4r' ) . '</u></h4>';
			echo '<h4><u>' . __( 'Variables:', 'cc4r' ) . '</u></h4>';
			$esc_options = esc_html(print_r($this->options, TRUE ));  // option values from memory there is a slim chance stored values will differ
			echo '<span class="cca-brown">' . __( 'Current setting values: ', 'cc4r' ) . '</span>' . str_replace ( '[' , '<br> [' , print_r($esc_options, TRUE ));

			echo '<hr><h4><u>' . __( 'Maxmind Data status:', 'cc4r' ) . '</u></h4>';

			if (file_exists(CCA_MAXMIND_DATA_DIR)):

				echo __( 'Maxmind Directory: "', 'cc4r' ) . CCA_MAXMIND_DATA_DIR . '"<br>';

				if (file_exists(CCA_MAXMIND_DATA_DIR . CCA_MAX_FILENAME)):

					printf( __( 'File "%1$s" last successfully updated: %2$s', 'cc4r' ) . '<br>',
						CCA_MAX_FILENAME,
						date( 'F d Y H:i:s.',filemtime(CCA_MAXMIND_DATA_DIR . CCA_MAX_FILENAME))
					);


				else:

					echo '<span class="cca-brown">';

					printf( __( 'Maxmind look-up file "%s" could not be found.', 'cc4r' ),
						CCA_MAX_FILENAME
					);

					if (file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat') || file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIPv6.dat')):

						echo __( 'Out of date Maxmind Legacy files have been found and will be used for geolocation.', 'cc4r' ) . '<br>';

					else:

						echo __( 'Maxmind geolocation will not be functioning.', 'cc4r' ) . '<br>';

					endif;

					echo __( 'Ensure "Enable CC Country Caching add-on" is checked ("Comet Cache" tab) and then save settings', 'cc4r' );

					echo '</span><br>';

				endif;

			else:

				echo '<span class="cca-brown">';

				printf( __( 'The Maxmind Directory ("%s") does not exist. Maxmind Country GeoLocation will not be working.', 'cc4r' ),
					CCA_MAXMIND_DATA_DIR
				);

				echo '</span><br>';

			endif;

			if (! empty($this->maxmind_status['health'] ) && $this->maxmind_status['health'] != 'ok'):

				printf( '<p>' . __( 'The last update process reported a problem: %s', 'cc4r' ) . '</p>',
					'<span class="cca-brown">' . $this->maxmind_status['result_msg'] . '</span>'
				);

			endif;

			echo '<hr>';

			$esc_options = esc_html(print_r($this->maxmind_status, TRUE ));  // option values from memory there is a slim chance stored values will differ
			echo '<span class="cca-brown">' . __( 'Current values: ', 'cc4r' ) . '</span>' . str_replace ( '[' , '<br> [' , print_r($esc_options, TRUE ));

		endif;

        // ensures info is not left displayed on revisits to this settings tab
        if ( $reset_info_settings ) :
            CC4R_options::update_options($this->options);
        endif;

		submit_button( __( 'Display Information', 'cc4r' ), 'primary', 'submit', TRUE, array( 'class' => 'cc4r-save-button' ) );
	}   // END function render_config_panel



  // validate and save settings fields changes
  public function sanitize( $input ) {
    if ( ! $this->options['is_activated'] ) return $this->options; // activation hook carries out its own "sanitizing"

    if ( $this->options['geo_method'] == "CCA" && isset($_SERVER['HTTP_CF_IPCOUNTRY']) ) $this->options['geo_method'] = "CDN-Clf";
	$input['action'] = empty($input['action']) ? '' : strip_tags($input['action']);
    // initialize messages
	$settings_msg = '';
	$msg_type = 'updated';

    // PROCESS config TAB INPUT
    if ($input['action'] == 'Configuration') :
        $this->options['test_mode'] = empty($input['test_mode']) ? FALSE : TRUE;
        // reset settings options
  		if (! empty($input['force_reset']) ) :
		    $this->options = array('is_activated'=>'1');
		    $msg_part = __( 'Country Caching has been reset to none.', 'cc4r' ) . '<br>';
  			$settings_msg = $msg_part . $settings_msg;
  			add_settings_error('cc4r_group',esc_attr( 'settings_updated' ), __($settings_msg),	$msg_type	);
  			return $this->options;
  		endif;
        $this->options['diagnostics'] = empty($input['diagnostics']) ? FALSE : TRUE;
        $this->options['geoip_data'] = empty($input['geoip_data']) ? FALSE : TRUE;
        $this->options['svar_data'] = empty($input['svar_data']) ? FALSE : TRUE;
        $this->options['hdr_data'] = empty($input['hdr_data']) ? FALSE : TRUE;
        if (is_multisite()):
            CC4R_options::update_options($this->options);
            return;
        endif;
        return $this->options;
    endif;


    // PROCESS geo systems TAB INPUT
    if ($input['action'] == 'Geo') :
        $valid_geos = array('CCA','CDN-Clf','CDN-AmzCf','CDN-other','other_geo');
        $alnum_svar = str_replace(array('-','_'), '', $input['CDN_geo_svar']);  // previously CDN_geo_hdr
        $alnum_hdr = str_replace(array('-','_'), '', $input['CDN_geo_httphdr']);
        $error_detected = FALSE;
        if (empty($input['geo_method']) || ! in_array($input['geo_method'], $valid_geos) ) :
            $msg_type = 'error';
            $settings_msg = __( 'Not updated: something\'s amiss radio button\'s provided an empty or invalid selection.', 'cc4r' ) . '<br>';
        elseif ( $input['geo_method'] == 'CDN-other' && ( empty($input['CDN_geo_svar']) || empty($input['CDN_geo_httphdr']) ) ) :
            $msg_type = 'error';
            $settings_msg = __( 'Not updated: You must specify both the variable and header names as provided by the CDN/server.', 'cc4r' ) . '<br>';
        elseif ( $input['geo_method'] == 'CDN-other' && ( ! ctype_alnum($alnum_svar) || ! ctype_alnum($alnum_hdr) ) ) :
            $msg_type = 'error';
            $settings_msg = __( 'Not updated: The Http Header or Server variable name you entered is invalid. Allowed characters: alpahabetic, numeric, "-" and (for HTTP headers only) "_"', 'cc4r' ) . '<br>';
        else:
            $this->options['geo_method'] = $input['geo_method'];
            if ( $this->options['geo_method'] == "CCA" && isset($_SERVER['HTTP_CF_IPCOUNTRY']) ): $this->options['geo_method'] = "CDN-Clf"; endif;
            $this->options['CDN_geo_svar'] = $input['CDN_geo_svar'];
            $this->options['CDN_geo_httphdr'] = $input['CDN_geo_httphdr'];
            $settings_msg = __( 'Settings updated', 'cc4r' ) . '<br>';
        endif;

        add_settings_error('cc4r_group',esc_attr( 'settings_updated' ), $settings_msg, $msg_type );
    // UPDATE SETTINGS OPTIONS
        if (is_multisite()):
            CC4R_options::update_options($this->options);
            return;
        endif;
        return $this->options;

    endif;


    // PROCESS CN/CCA TAB INPUT
    if ($input['action'] == 'CNusers') :
        $settings_msg = __( 'Settings updated', 'cc4r' ) . '<br>';
        $msg_type = 'updated';
        $this->options['CCAsync'] = empty($input['CCAsync']) ? '0' : '1';
        $this->options['only4CN'] = '0';
        if (! empty($input['only4CN']) ) :
           if ( ! class_exists( 'Cookie_Notice') || empty(CC4R_options::$ccax_options['only_EU_cookie']) || empty(CC4R_options::$ccax_options['EU_ccodes']) ) :
               $msg_type = 'error';
               $settings_msg = __( 'NOT updated: Either Cookie Notice plugin is not activated, or, you have not set the "EU only Cookie Notice" option in CCA Settings.', 'cc4r' ) . '<br>';
            else:
                $this->options['only4CN'] = '1';
                $this->options['CCAsync'] = '1';
                $this->options['cache_iso_cc'] = 'XX';
                $this->options['use_group'] = '1';
                $this->options['my_ccgroup'] = CC4R_options::$ccax_options['EU_ccodes'];
            endif;
        endif;
        add_settings_error('cc4r_group',esc_attr( 'settings_updated' ), $settings_msg, $msg_type );

        if (is_multisite()):
            CC4R_options::update_options($this->options);
            return;
        endif;
        return $this->options;
    endif;


//  RETURN IF INPUT IS NOT FROM "WPRC" TAB (The WPRC tab should be the only one not sanitized at this point).
    if ($input['action'] != 'WPRC'): return $this->options; endif;

// Process CACHE SETTINGS TAB INPUT

	// take opportunity to housekeep
	$this->remove_obsolete_settings();

	// prepare input for processing
    $now_enabled = empty($input['cc_enabled']) ? FALSE : TRUE;
//	$new_mode = empty($input['caching_mode']) ? 'none' : 'WPRC';
	$cache_iso_cc = empty($input['cache_iso_cc']) ? '' : strtoupper(trim($input['cache_iso_cc']));
	$my_ccgroup = empty($input['my_ccgroup']) ? '' : strtoupper(trim($input['my_ccgroup']));
	$use_group = empty($input['use_group'] ) ? '0' : '1';
	$cache_iso_cc = empty($input['cache_iso_cc']) ? '' : strtoupper(trim($input['cache_iso_cc']));
	$error_iso_detected = FALSE;
	if ($this->is_valid_ISO_list($cache_iso_cc)):
		  $error_iso_cc = FALSE;
	else:
		  $error_iso_cc = TRUE;
		  $error_iso_detected = TRUE;
	endif;
	if ( empty($my_ccgroup) ):
		  $my_ccgroup = CC4R_EU_GROUP;
		  $use_group = '0';
	elseif ($this->is_valid_ISO_list($my_ccgroup)):
		  $error_iso_group = FALSE;
	else:
		  $use_group = '0';
		  $error_iso_group = TRUE;
		  $error_iso_detected = TRUE;
		  if ($error_iso_cc): $error_iso_both = TRUE; endif;
	endif;

	// user is trying to enable country caching without activated WP Rocket plugin!
    if ( $now_enabled && ! $this->wpr_activated_version() ) :
        add_settings_error('cc4r_group',esc_attr( 'settings_updated' ),
            __(' ERROR: WP Rocket has to be installed <u>and activated</u> before configuring Country Caching.', 'cc4r' ) . '<br>', // .
//		     __("N.B. you can still configure Country Caching if you have temporarilly disabled caching via Rocket's own settings", 'cc4r'),
            'error'
        );
        if (is_multisite()):
            CC4R_options::update_options($this->options);
            return;
        endif;
        return $this->options;
    endif;

	// user is not enabling country caching and it wasn't previously enabled
    if ( ! $now_enabled && ! $this->options['cc_enabled'] ) :
		if (! $error_iso_detected):
		    $this->options['cache_iso_cc'] = $cache_iso_cc;
			$this->options['my_ccgroup'] = $my_ccgroup;
			$this->options['use_group'] = $use_group;
			$this->options['ssl_cookie'] = empty($input['ssl_cookie'] ) ? '0' : '1';
			$this->options['ht_rewrite'] = empty($input['ht_rewrite']) ? '0' : '1';
            $settings_msg = __( 'Any changes to settings have been updated; HOWEVER you have NOT ENABLED country caching.', 'cc4r' ) .  '<br>';
		else :
            $settings_msg .= __( 'Not updated (invalid country entry found); also you did not enable country caching.', 'cc4r' ) . '<br>';
		endif;
		add_settings_error('cc4r_group',esc_attr( 'settings_updated' ), $settings_msg, 'error' );
        if (is_multisite()):
            CC4R_options::update_options($this->options);
            return;
        endif;
        return $this->options;
	endif;

	$msg_part = '';

	// user is changing to OPTION "NONE" we are disabling country caching and need to remove the WPRC add-on script
	if ( ! $now_enabled ) :
	    $msg_part = __( 'Country caching has been disabled.', 'cc4r' ) . '<br>';
        if (  ! $error_iso_cc ) :
             $this->options['cache_iso_cc'] = $cache_iso_cc;
        endif;
        if ( ! $error_iso_group ) :
            $this->options['my_ccgroup'] = $my_ccgroup;
		    $this->options['use_group'] = $use_group;
        endif;
        $this->options['ssl_cookie'] = empty($input['ssl_cookie'] ) ? '0' : '1';
        $this->options['ht_rewrite'] = empty($input['ht_rewrite']) ? '0' : '1';
		$this->options['cc_enabled'] = '0';
		$settings_msg = $msg_part . $settings_msg;

	// check if user has submitted option to enable country caching, but the input comma separated Country Code list is invalid
    elseif ( $now_enabled  && $error_iso_detected ):
		$settings_msg .= __( 'WARNING: Settings have NOT been changed. Country Code text box error (it must be empty or contain 2 character ISO country codes separated by commas).', 'cc4r' ) . '<br>';
		add_settings_error('cc4r_group',esc_attr( 'settings_updated' ), $settings_msg, 'error'	);

        if (is_multisite()):
            CC4R_options::update_options($this->options);
            return;
        endif;
		return $this->options;

	// user has opted for country caching "enabled" and has provided a valid list of country codes
	elseif ( $now_enabled ) :
  		 // Country Caching has been enabled; ensure Maxmind files are installed if needed
  	    if ( ! $this->ensure_maxfile_present() ) :
  	        if ( empty($_SERVER["HTTP_CF_IPCOUNTRY"]) ) :
  			    $settings_msg = $settings_msg . '<br>' . __( 'No changes made. Maxmind mmdb file is missing and could not be installed.', 'cc4r' ) . '<br>' . $this->maxmind_status['result_msg'];
  			    add_settings_error('cc4r_group',esc_attr( 'settings_updated' ), __($settings_msg), 'error');
                if (is_multisite()):
                    CC4R_options::update_options($this->options);
                    return;
                endif;
  		        return $this->options;
  			endif;
        endif;

        // cache settings changed visitor may have incorrect cookie so now use a different cookie name
        if ( $this->options['cache_iso_cc'] != $cache_iso_cc || $this->options['my_ccgroup'] != $my_ccgroup  || $this->options['use_group'] != $use_group ) :
            $this->options['cookie_old'] = $this->options['cookie_name'];
            $this->options['cookie_name'] = 'cc4r_geo' . substr( (string)time(), -3);  // append last 3 chars of timestamp to set/make WPR look for different cookie
        endif;
		$this->options['cache_iso_cc'] = $cache_iso_cc;
		$this->options['my_ccgroup'] = $my_ccgroup;
		$this->options['use_group'] = $use_group;
		$this->options['ssl_cookie'] = empty($input['ssl_cookie'] ) ? '0' : '1';
        $this->options['ht_rewrite'] = empty($input['ht_rewrite']) ? '0' : '1';
		$this->options['cc_enabled'] = '1';
		$msg_part = __( 'Settings have been updated and country caching is enabled for WP Rocket.', 'cc4r' ) . '<br>';
		$settings_msg = $msg_part . $settings_msg;
    endif;

	if ($settings_msg != '') :
        add_settings_error('cc4r_group',esc_attr( 'settings_updated' ), __($settings_msg),	$msg_type	);
    endif;

    if (is_multisite()):
        CC4R_options::update_options($this->options);
        return;
    endif;
	return $this->options;
  }  	// END OF SANITIZE FUNCTION


  function ensure_maxfile_present($doEmail = FALSE) {
	if (! file_exists(CCA_MAXMIND_DATA_DIR . CCA_MAX_FILENAME) || @filesize(CCA_MAXMIND_DATA_DIR . CCA_MAX_FILENAME) < 8000 ) :
		$do_max = new CCAmaxmindUpdate();
		$bool_success = $do_max->save_maxmind($doEmail); // if method argument is true then email will be sent on failure
		$this->maxmind_status = $do_max->get_max_status();
		unset($do_max);
		return $bool_success;
	endif;
	return TRUE;
  }


  function cc4r_wprc_status() {
    if ( ! $this->wpr_activated_version() ) :
        $wprc_running = __( 'It looks like WP Rocket is not currently activated on your site.', 'cc4r' ) . '<br>';
  	else:
  		$wprc_running = __( 'It looks like WP Rocket is installed and activated on your site.', 'cc4r' ) . '<br>';
    endif;
    if (! empty($_SERVER["HTTP_CF_IPCOUNTRY"]) ) :
  	    $geoip_used = __('Cloudflare data is being used for GeoIP	');
  	elseif ( ! file_exists(CCA_MAXMIND_DATA_DIR . CCA_MAX_FILENAME) || $this->maxmind_status['health'] == 'fail') :
  		$geoip_used = __( 'There is a problem with GeoIP - see the "CC Info" tab. Re-saving settings (assuming "enable country caching" is checked) may solve this problem.', 'cc4r' );
  	else:
  		$geoip_used = '';
  	endif;
  	if (empty($this->options['cache_iso_cc'])) :
  	    $opto = '<br>' . __( 'To fully optimize performance you should limit the countries that are individually cached.', 'cc4r' );
  	else: $opto = '';
  	endif;
  	if ($this->options['cc_enabled']):
  	    if (! $this->wpr_activated_version() ) :
  			$wprc_status = $wprc_running;  // notify user WPRC is deactivated
		else:
			$wprc_status = $wprc_running . __( 'Country caching set up looks okay.', 'cc4r' ) . '<br>';
			$wprc_status .= $opto . $geoip_used;
  		endif;
  	else:  // user has not checked to enable WPRC country caching
  		$wprc_status =  $wprc_running . __( 'N.B. You have not enabled Rocket country caching.', 'cc4r' ) . '<br>';
  	endif;

  	return $wprc_status;
  }    // END OF cc4r_wprc_status FUNCTION

  function wpr_activated_version() {
    if ( defined ('WP_ROCKET_VERSION')) return WP_ROCKET_VERSION;
		return '';
  }

  function is_valid_ISO_list($list) {
    if ( $list != '') :
  	    $codes = explode(',' , $list);
  		foreach ($codes as $code) :
  		   if ( ! ctype_alpha($code) || strlen($code) != 2) :
     		   return FALSE;
  		   endif;
  		endforeach;
  	endif;
	return TRUE;
  }


  function get_geo_status() {

    if ( $this->options['geo_method'] == 'CCA') :
        $this->cca_geo_status();

    elseif ( $this->options['geo_method'] == 'CDN-Clf') :
        echo __( 'Selected Lookup method: <i>Cloudflare</i>', 'cc4r' ) . '<br>';
        if ( isset($_SERVER['HTTP_CF_IPCOUNTRY']) ) :

            printf( __( 'Identifies you as from: "%s"', 'cc4r' ) . '<br>',
            	wp_kses( $_SERVER['HTTP_CF_IPCOUNTRY'] , array() )
            );

        else:
            echo '<span class="cca-brown">' . __( 'Warning - Cloudflare Header not detected.', 'cc4r' ) . '</span><br>';
        endif;

    elseif ( $this->options['geo_method'] == 'CDN-AmzCf') :

		echo __( 'Selected Lookup method: <i>Amazon Cloudfront</i>', 'cc4r' ) . '<br>';

        if ( isset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY']) ) :

            printf( __( 'Identifies you as from: "%s"', 'cc4r' ) . '<br>',
            	wp_kses( $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] , array() )
            );

        else:

            echo '<span class="cca-brown">' . __( 'Warning - Amazon Header not detected.', 'cc4r' ) . '</span><br>';

        endif;

    elseif ( $this->options['geo_method'] == 'CDN-other') :

        echo __( 'Selected Lookup method: <i>CDN/server provided header</i>', 'cc4r' ) . '<br>';

        if (! empty($this->options['CDN_geo_svar'])) :

            $cdn = $this->options['CDN_geo_svar'];

            printf( __( 'Specified header "%s"', 'cc4r' ) . '<br>',
            	wp_kses( $this->options['CDN_geo_svar'] , array() )
            );

            if ( isset($_SERVER[$cdn]) ) :

	            printf( __( 'Identifies you as from: "%s"', 'cc4r' ) . '<br>',
	            	wp_kses( $_SERVER[$cdn] , array() )
	            );

            else:

                echo '<span class="cca-brown">' . __( 'Warning - specified Header not detected.', 'cc4r' ) . '</span><br>';

			endif;

        endif;

    elseif ( $this->options['geo_method'] == 'other_geo') :

        echo __( 'Selected Lookup method: <i>geolocation connection for other WP Plugin</i>', 'cc4r' ) . '<br>';

        $locale = apply_filters( 'cc_use_other_geoip', FALSE);

        if ($locale !== FALSE) :

            printf( __( 'Identifies you as from: "%s"', 'cc4r' ) . '<br>',
            	wp_kses( $locale , array() )
            );

        else:

            echo '<span class="cca-brown">' . __( 'Warning - there is no active filter to provide country code.', 'cc4r' ) . '</span><br>';

        endif;

    else:

        echo '<span class="cca-brown">' . __( 'Warning - setting to select geo system is unrecognised.', 'cc4r' ) . '</span><br>';

    endif;

    $cc_locale = cc_get_iso();
    $cache_as = cc_cache_as($cc_locale);
    $cache_as = ($cache_as == 'Error') ? __( 'NOT CACHED due to error', 'cc4r' ) : '"{page}-' . $cache_as . '"';


    printf( '<p>' . __( 'CC modified ISO = "%1$s", when caching, cache as: %2$s', 'cc4r' ) . '</p>',
	    $cc_locale,
	    $cache_as
    );

    echo '<br>';

    echo '<hr>';

    echo '<p><b><u>' . __( 'Your Server\'s IP Address Variables (for info only):', 'cc4r' ) . '</u></b></p>';

	echo '<hr><p><b>' . __( 'Cloudflare Country Variable:', 'cc4r' ) . '<b>';

    if ( ! empty($_SERVER["HTTP_CF_IPCOUNTRY"])):

        echo '"' . $_SERVER["HTTP_CF_IPCOUNTRY"] . '"</p>';

    else:

        echo __( 'Not found', 'cc4r' ) . '</p>';

    endif;

    echo '<hr><p><b>' . __( 'Amazon Cloudfront Country Variable:', 'cc4r' ) . '</b>';

    if ( ! empty($_SERVER["HTTP_CLOUDFRONT_VIEWER_COUNTRY"])):

        echo '"' . wp_kses( $_SERVER["HTTP_CLOUDFRONT_VIEWER_COUNTRY"] , array() ) . '"</p>';

    else:

        echo __( 'Not found' , 'cc4r' ) . '</p>';

    endif;

    foreach (array('REMOTE_ADDR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED','HTTP_CF_CONNECTING_IP') as $key):

        echo '<hr><p><b>' . $key . '<b>: ';

  	    if (empty($_SERVER[$key])):

  	        echo __( 'is empty or not set', 'cc4r' ) . '</p>';

  	        continue;

  	    endif;

        $possIP = $_SERVER[$key];

        echo htmlspecialchars($possIP);

  	    $ip = explode(',', $possIP);

        if (count($ip) > 1):  // its a comma separated list of enroute IPs

  	        echo '<br>&nbsp;&nbsp;' . __( 'a check of the first item indicates', 'cc4r' );

        endif;

        if ($ip[0] != '127.0.0.1' && filter_var(trim($ip[0]), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) !== false) :

  	        echo '<i>' . __( ' it appears to be a valid IP address', 'cc4r' ) . '</i>';

        else :

  	        echo '<i>' . __( ' it look like an invalid or reserved IP address', 'cc4r' ) . '</i>';

  	    endif;

    endforeach;

    echo '<br><hr><hr>';

  }


	function cca_geo_status() {
		if (! function_exists('cca_run_geo_lookup') ) :
			include CC4R_MAXMIND_DIR . 'cca_lookup_ISO.inc';
		endif;

		if ( ! isset($GLOBALS['CCA_ISO_CODE']) || empty($GLOBALS['cca-lookup-msg'])) : // then lookup has not already been done by another plugin
			cca_run_geo_lookup(CC4R_MAXMIND_DIR); // sets global CCA_ISO_CODE and status msg
		endif;

		// as GLOBALS can be set by any process we need to sanitize/format before use
		if (! ctype_alnum($GLOBALS["CCA_ISO_CODE"]) || ! strlen($GLOBALS["CCA_ISO_CODE"]) == 2) :
			$_SERVER["CCA_ISO_CODE"] = "";
		endif;

		$lookupMsg = str_replace('<CCA_CUST_IPVAR_LINK>', CCA_CUST_IPVAR_LINK, $GLOBALS['cca-lookup-msg']);
		$lookupMsg = str_replace('<CCA_CUST_GEO_LINK>', CCA_CUST_GEO_LINK, $lookupMsg);

		// Translators: 1. country code; 2. message
		printf( '<p class="cca-brown">' . __( 'You appear to be located in <i>(or CCA preview mode is)</i> <b>"%1$s"</b><br>%2$s', 'cc4r' ) . '</p>',
		$GLOBALS["CCA_ISO_CODE"],
		$lookupMsg
		);
	}


  function remove_obsolete_settings() {
    // e.g.
    //    wp_clear_scheduled_hook( 'country_caching_check_wprc' );   // no longer used in vers post Apr 2018
    //    unset($this->options['override_tab']);  // no longer used in vers post Apr 2018
  }

} // END CLASS
