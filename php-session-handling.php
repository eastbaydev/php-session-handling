<?php
/*
Plugin Name: PHP Session Handling
Plugin URI: http://www.eastbaywebshop.com
Description: Provides initial integration to do Drupal-like session handling for WordPress
Version: 1.00
Author: Michael Pirog
Author URI: http://www.eastbaywebshop.com
*/

/*  Copyright (C) 2012 East Bay Development Shop  (http://www.eastbaywebshop.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/** Make sure these constants are defined */
if ( ! defined('WP_CONTENT_URL')) {
  define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
}
if ( ! defined('WP_CONTENT_DIR')) {
  define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
if ( ! defined('WP_PLUGIN_URL')) {
  define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
}
if ( ! defined('WP_PLUGIN_DIR')) {
  define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

/** Load modules */
require_once(WP_PLUGIN_DIR . '/php-session-handling/modules/sessions.php');


/** Overwrite pluggable functions */
require_once(WP_PLUGIN_DIR . '/php-session-handling/includes/plugs.php');


/**
 * 
 * Wordpress Hooks
 * 
 */

/** Set up the pantheon database on plugin activation */ 
register_activation_hook(__FILE__, 'phpSessionInstall');

/** Drop the pantheon database on plugin deactivation */
register_deactivation_hook(__FILE__, 'phpSessionUninstall');

/** Destroy session on logout */
add_action('wp_logout', 'phpSessionLogout', 100);

/** Finish PHP session handling */
add_action('wp_footer', 'phpSessionShutdown', 100)

?>
