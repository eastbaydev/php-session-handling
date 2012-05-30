<?php

/**
 * @file
 * Overwrite pluggable functions
 */


if ( !function_exists('wp_set_auth_cookie') ) :
/**
 * Sets the authentication cookies based User ID.
 *
 * The $remember parameter increases the time that the cookie will be kept. The
 * default the cookie is kept without remembering is two days. When $remember is
 * set, the cookies will be kept for 14 days or two weeks.
 * 
 * We are using PHP session based handler now so don't need to set the WPcookies. 
 * Tentatively removing auth cookie action as well
 * 
 * @since 2.5
 *
 * @param int $user_id User ID
 * @param bool $remember Whether to remember the user
 */
function wp_set_auth_cookie($user_id, $remember = false, $secure = '') {
  if ( !$remember ) {
    $lifetime = 0;
  }
  if ( '' === $secure ) {
    $secure = is_ssl();
  }
  $secure = apply_filters('secure_auth_cookie', $secure, $user_id);
  wp_set_current_user($user_id);
  phpSessionRegenerate($lifetime, $secure);
  
}
endif;


if ( !function_exists('wp_parse_auth_cookie') ) :
/**
 * Parse a cookie into its components
 * 
 * Simply returns the session cookie
 * 
 * @since 2.7
 *
 * @param string $cookie
 * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
 * @return cookie
 */
function wp_parse_auth_cookie($cookie = '', $scheme = '') {
  phpSessionInitialize();
  if ( empty($_COOKIE[session_name()]) ) {
    return false;
  }
  return $_COOKIE[session_name()];
}
endif;


if ( !function_exists('wp_validate_auth_cookie') ) :
/**
 * Validates authentication cookie.
*
* The checks include making sure that the authentication cookie is set and
* pulling in the contents (if $cookie is not used).
*
* Makes sure the cookie is not expired. Verifies the hash in cookie is what is
* should be and compares the two.
*
* @since 2.5
*
* @param string $cookie Optional. If used, will validate contents instead of cookie's
* @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
* @return bool|int False if invalid cookie, User ID if valid.
*/
function wp_validate_auth_cookie($cookie = '', $scheme = '') {  
  if ( ! $cookie = wp_parse_auth_cookie($cookie, $scheme) ) {
    do_action('auth_cookie_malformed', $cookie, $scheme);
    return false;
  }

  // Allow a grace period for POST and AJAX requests
  if ( defined('DOING_AJAX') || 'POST' == $_SERVER['REQUEST_METHOD'] )
    $expired += 3600;
  
  global $wpdb;
  
  $user = $wpdb->get_row(
    $wpdb->prepare(
    	"SELECT user.*, session.* FROM " . $wpdb->users . " 
      	user INNER JOIN " . $wpdb->prefix . "session session 
      	ON user.ID = session.ID
      	WHERE session.sid = '%s'", $cookie
    )
  );
   
  // We found the client's session record and they are an authenticated,
  // active user.
  if ($user && $user->ID > 0 && $user->current_status == 0) {
    //@todo need to investigate what USER looks like
  }
  // We didn't find the client's record (session has expired), or they are
  // blocked, or they are an anonymous user.
  else {
     $user = wp_set_current_user(0);
  }

  do_action('auth_cookie_valid', $cookie_elements, $user);

  return $user->ID;
}
endif;


?>