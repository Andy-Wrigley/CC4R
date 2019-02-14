=== Country Caching For WP Rocket ===
Contributors: wrigs1, senlin
Tags: caching, WP Rocket, Rocket Cache, country, GeoIP, Maxmind, geolocation, cache
Requires at least: 4.1
Tested up to: 5.0.3
Requires PHP: 5.4 or later
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extends WP Rocket to cache by page/visitor country instead of just page. Solves "wrong country content" Geo-Location issues.

== Description ==

Allows WP Rocket to display the correct page/widget content for a visitor's country when you are using geo-location.

This plugin provides a Helper Script and user configurable settings that enables WP Rocket to create separate snapshots (cache) for each page based on country location.

Separate snapshots can be restricted to specific countries.  E.g. if you are based in the US but customize some content for Canadian or Mexican visitors, you can restrict separate caching to CA & MX visitors; and all other visitors will see the same cached ("US") content.

You can also specify a single snapshot for a group of countries e.g. all European Union countries.

It works for both normal Wordpress and Multisite however on Multisite the same country caching settings are shared by all sites.

More info in [the user guide]( https://wptest.means.us.com/country-geolocation-wp-rocket/ )

**Identification of visitor country for caching**

Choice of Cloudflare, Amazon Cloudfront (not tested), Server header/variable, other plugin, or Maxmind (when the plugin is first enabled it uploads GeoLite2 data created by MaxMind, available from http://www.maxmind.com ). Cloudflare works with any PHP version, but Maxmind Geolite2 requires PHP 5.4 or later. *It is also possible to connect a different GeoLocation sytem of your choice (see documentation).*

If you use Cloudflare and have "switched on" their GeoLocation option ( see [Cloudflare's  instructions](https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do- ) ) then it will be used to identify visitor country.  If not, then the Maxmind Country Database will be used.

**Updating** (If not using Cloudflare for country) The installed Maxmind Country/IP data file will lose accuracy over time.  To automate a monthly update of this file, install and enable the [Category Country Aware (CCA) plugin](https://wordpress.org/plugins/category-country-aware/ ) (Country Caching and the Cataegory Country Aware (CCA) plugins use the same Maxmind data file in the same folder and the CCA plugin includes code for its update). The CCA plugin has many other features and functionality you may find useful. Alternatively you can manually update (FAQ below).

== Credits ==

Many thanks to Pieter Bos ( https://bohanintl.com/  github: https://github.com/senlin/ , wordpress.org: https://profiles.wordpress.org/senlin/) for i18n internationalisation work on this plugin. 

== ADVICE ==

I don't recommend you use ANY Caching plugin UNLESS you know how to use an FTP program (e.g. Filezilla). Caching plugins can cause "white screen" problems for some users. 
Sometimes the only solution is to manually delete files using FTP or OS command line.


== Installation ==

Install Country Caching plugin in normal way. Then go to "Dashboard->Settings->WP Rocket Country Caching". Check the "*Enable Country Caching" box, and save settings.

See guide: https://wptest.means.us.com/country-geolocation-wp-rocket/


== Frequently Asked Questions ==

= Where can I find support/additional documentation =

Support questions should be posted on Wordpress.Org<br />
Additional documentation [is provided here]( http://wptest.means.us.com/country-geolocation-wp-rocket/ )


= How do I know its working =

See [these checks](http://wptest.means.us.com/country-geolocation-wp-rocket/#test).

= How do I keep the Maxmind country/IP range data up to date =

Automatically: install the [Category Country Aware plugin](https://wordpress.org/plugins/category-country-aware/ ) from Wordpress.Org and enable its settings; it will update your Maxmind data every "month".

Manually: monthly/whatever; download "GeoLite2-Country.tar.gz" from [Maxmind](https://dev.maxmind.com/geoip/geoip2/geolite2/ ) and extract the file "GeoLite2-Country.mmdb" and upload it to your servers "/wp-content/cca_maxmind_data/" directory.

= Will it work on Multisites =

Yes, but it will be the same for all blogs (you can't have it on for Blog A, and off for Blog B).

On MultiSites, the Rocket Country Caching settings menu will be visible on the Network Admin Dashboard (only).


= How do I stop/remove Country Caching =

Temporarilly: uncheck "Enable Country Caching" in the plugin's settings.

Permanently: deactivate then delete plugin via Dashboard in usual way. Then go to WP Rocket settings and clear cache.


== Screenshots ==

1. Simple set up. Dashboard->WPSC Country Caching


== Changelog ==

= 0.0.5 =

First published version

== Upgrade Notice ==

= 0.0.5 =

First published version

== License ==

This program is free software licensed under the terms of the [GNU General Public License version 2](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html) as published by the Free Software Foundation.

In particular please note the following:

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.