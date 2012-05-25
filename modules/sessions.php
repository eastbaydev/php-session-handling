<?php

/**
* @file
* User session handling functions.
*/

global $session_db_version;
$session_db_version = '1.0';


/**
 * 
 * Initialize the session handler, starting a session if needed.
 * 
 */
function phpSessionInitialize() {
  // assuming COOKIE_DOMAIN is defined correctly in wp-config.php
  $session_name = COOKIE_DOMAIN;
  
  // To prevent session cookies from being hijacked, a user can configure the
  // SSL version of their website to only transfer session cookies via SSL by
  // using PHP's session.cookie_secure setting. The browser will then use two
  // separate session cookies for the HTTPS and HTTP versions of the site. So we
  // must use different session identifiers for HTTPS and HTTP to prevent a
  // cookie collision.
  if (ini_get('session.cookie_secure')) {
    $session_name .= 'SSL';
  }
  
  // Per RFC 2109, cookie domains must contain at least one dot other than the
  // first. For hosts such as 'localhost' or IP Addresses we don't set a cookie domain.
  if (count(explode('.', COOKIE_DOMAIN)) > 2 && !is_numeric(str_replace('.', '', COOKIE_DOMAIN))) {
    ini_set('session.cookie_domain', COOKIE_DOMAIN);
  }
  
  // Use httponly session cookies.
  ini_set('session.cookie_httponly', '1');
    
  session_name('SESS'. md5($session_name));
  
  session_set_save_handler(
  	'phpSessionOpen', 
  	'phpSessionClose', 
  	'phpSessionRead', 
  	'phpSessionWrite', 
  	'phpSessionDestroySID', 
  	'phpSessionGC'
  );
  
  if (isset($_COOKIE[session_name()])) {
    // If a session cookie exists, initialize the session. Otherwise the
    // session is only started on demand in drupal_session_commit(), making
    // anonymous users not use a session cookie unless something is stored in
    // $_SESSION. This allows HTTP proxies to cache anonymous pageviews.
    phpSessionStart();
  }
  else {
    // Set a session identifier for this request. This is necessary because
    // we lazyly start sessions at the end of this request, and some
    // processes (like drupal_get_token()) needs to know the future
    // session ID in advance.
    session_id(md5(uniqid('', TRUE)));
  }
}


/**
*
* Write the session, and open one if needed.
* 
*/
function phpSessionShutdown() {
  phpSessionCommit();
}


/**
*
* Remove session on logout
*
*/
function phpSessionLogout() {
  phpSessionDestroy();
}


/**
*
* Needed for PHP's session_set_save_handler
* 
* @param string $save_path
* @param string $session_name
*
*/
function phpSessionOpen($save_path, $session_name) {
  return TRUE;
}


/**
*
* Needed for PHP's session_set_save_handler
*
*/
function phpSessionClose() {
  return TRUE;
}


/**
*
* Reads an entire session from the database
*
* @param string $key The session ID of the session to retrieve.
* @return The user's session, or an empty string if no session exists.
*
*/
function phpSessionRead($key) {
  global $wpdb;
  
  // Write and Close handlers are called after destructing objects since PHP 5.0.5
  // Thus destructors can use sessions but session handler can't use objects.
  // So we are moving session closure before destructing objects.
  register_shutdown_function('session_write_close');
  
  // Handle the case of first time visitors and clients that don't store cookies (eg. web crawlers).
  if (!isset($_COOKIE[session_name()])) {
    return '';
  }
  
  // Otherwise, if the session is still active, we have a record of the client's session in the database.
  $user = $wpdb->get_row(
    $wpdb->prepare(
  		"SELECT user.*, session.* FROM " . $wpdb->users . "
        	user INNER JOIN " . $wpdb->prefix . "session session 
  				ON user.ID = session.ID
        	WHERE session.sid = '%s'", $key
    )
  );
  
    // We found the client's session record and they are an authenticated,
  // active user.
  if ($user && $user->ID > 0 && $user->user_status == 0) {
    return $user->session;
  }
  // We didn't find the client's record (session has expired), or they are
  // blocked, or they are an anonymous user.
  else {
    return '';
  }
}


/**
 *
 * Writes an entire session to the database
 *
 * @param string $key The session ID of the session to write to.
 * @param string $value Session data to write as a serialized string.
 * @return TRUE, always
 *
 */
function phpSessionWrite($key, $value) {
  global $wpdb;
  global $current_user;

  // If saving of session data is disabled or if the client doesn't have a session,
  // and one isn't being created ($value), do nothing. This keeps crawlers out of
  // the session table. This reduces memory and server load, and gives more useful
  // statistics.
  if (!phpSessionSaveSession() || ($current_user->ID == 0 && empty($_COOKIE[session_name()]) && empty($value))) {
    return TRUE;
  }

  $affected = $wpdb->update(
    $wpdb->prefix . 'session',
    array(
      	'ID' => $current_user->ID,
        'cache' => 0,
        'hostname' => ip_address(),
        'session' => $value,
        'timestamp' => time()
    ),
    array('sid' => $key),
    array(
      	'%d',
      	'%d',
      	'%s',
      	'%s',
      	'%d'
    ),
    array('%s')
  );

  if ($affected == 0) {
    // If this query fails, another parallel request probably got here first.
    // In that case, any session data generated in this request is discarded.
    @$wpdb->insert(
      $wpdb->prefix . 'session',
        array(
            'ID' => $current_user->ID, 
          	'sid' => $key,
            'hostname' => ip_address(),
            'timestamp' => time(),
            'cache' => 0,
            'session' => $value
        ),
        array(
          	'%d',
          	'%s',
          	'%s',
          	'%d',
          	'%d',
          	'%s'
        )
      );
  }

  return TRUE;
}


/**
 *
 * Called when an anonymous user becomes authenticated or vice-versa.
 * 
 * @param int $expiration The lifetime of the cookie
 * @param boolean $secure Use secure connection or not
 *
 */
function phpSessionRegenerate($lifetime, $secure) {
  global $wpdb;
  global $current_user;

  // Set the session cookie "httponly" flag to reduce the risk of session
  // stealing via XSS.
  extract(session_get_cookie_params());
  
  // @todo hardcoded for testing
  //$path = '/blog/';
  
  if (version_compare(PHP_VERSION, '5.2.0') === 1) {
    session_set_cookie_params($lifetime, $path, $domain, $secure, TRUE);
  }
  else {
    session_set_cookie_params($lifetime, $path, $domain, $secure);
  }
  
  if (phpSessionStarted()) {
    $old_session_id = session_id();
    session_regenerate_id();
  }
  else {
    // Start the session when it doesn't exist yet.
    // Preserve the logged in user, as it will be reset to anonymous
    // by _sess_read.
    //@todo still need to build the user object for WP
    $account = $current_user;
    phpSessionStart();
    $current_user = $account;
  }

  if (isset($old_session_id)) {
    $wpdb->update(
      $wpdb->prefix . 'session',
      array('sid' => session_id()),
      array('sid' => $old_session_id),
      array('%s'),
      array('%s')
    );
  }
}


/**
 *
 * Counts how many users have sessions. Can count either anonymous sessions or authenticated sessions.
 *
 * @param int $timestamp A Unix timestamp representing a point of time in the past.
 * @param boolean $anonymous TRUE counts only anonymous users. FALSE counts only authenticated users.
 * @return int The number of users with sessions.
 *
 */
function phpSessionCount($timestamp = 0, $anonymous = true) {
  global $wpdb;
  $query = $anonymous ? " AND ID = 0" : " AND ID > 0";
  
  return $wpdb->get_var(
    $wpdb->prepare(
    	"SELECT COUNT(sid) AS count
    		FROM " . $wpdb->prefix . "session
    		WHERE timestamp >= %d" . $query, $timestamp 
    )
  );
}


/**
 *
 * Called by PHP session handling with the PHP session ID to end a user's session.
 * 
 * @param string $sid The session id
 *
 */
function phpSessionDestroySID($sid) {
  global $wpdb;
  
  $wpdb->query(
    $wpdb->prepare(
      "DELETE 
      	FROM " . $wpdb->prefix . "session
      	WHERE sid = '%s'", $sid
    )
  );
  
  // If the session ID being destroyed is the one of the current user,
  // clean-up his/her session data and cookie.
  
  // Look into this later
  /*
  if ($sid == session_id()) {
    global $current_user;
   
    // Reset $_SESSION and $user to prevent a new session from being started
    // in pantheonSessionCommit()
    $_SESSION = array();
    wp_set_current_user(0);
  
    // Unset the session cookie.
    if (isset($_COOKIE[session_name()])) {
      $params = session_get_cookie_params();
  
      if (version_compare(PHP_VERSION, '5.2.0') === 1) {
        setcookie(session_name(), '', $_SERVER['REQUEST_TIME'] - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
      }
      else {
        setcookie(session_name(), '', $_SERVER['REQUEST_TIME'] - 3600, $params['path'], $params['domain'], $params['secure']);
      }
      unset($_COOKIE[session_name()]);
    }
  }
  */
}


/**
 *
 * End a specific user's session
 *
 * @param string $id The user id
 *
 */
function phpSessionDestroyID($id) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
      "DELETE 
      	FROM " . $wpdb->prefix . "session
      	WHERE ID = %d", $id
    )
  );
}


/**
 *
 * Does php table garbage cleaning via php_value session.gc_maxlifetime
 *
 * @param int $lifetime The cuttoff time
 * @return TRUE
 *
 */
function phpSessionGC($lifetime) {
  global $wpdb;
  // Be sure to adjust 'php_value session.gc_maxlifetime' to a large enough
  // value. For example, if you want user sessions to stay in your database
  // for three weeks before deleting them, you need to set gc_maxlifetime
  // to '1814400'. At that value, only after a user doesn't log in after
  // three weeks (1814400 seconds) will his/her session be removed.
  $wpdb->query(
    $wpdb->prepare(
      "DELETE 
      	FROM " . $wpdb->prefix . "session
      	WHERE timestamp < %d", time() - $lifetime
    )
  );

  return TRUE;
}


/**
 *
 * Determine whether to save session data of the current request.
 *
 * @param boolean $status Disables writing of session data when FALSE, (re-)enables writing when TRUE.
 * @return boolean FALSE if writing session data has been disabled. Otherwise, TRUE.
 *
 */
function phpSessionSaveSession($status = NULL) {
  static $save_session = TRUE;
  if (isset($status)) {
    $save_session = $status;
  }
  return ($save_session);
}


/**
 * 
 * Return whether a session has been started.
 * 
 */
function phpSessionStarted($set = NULL) {
  static $session_started = FALSE;
  if (isset($set)) {
    $session_started = $set;
  }
  return $session_started && session_id();
}



/**
 * 
 * Forcefully start a session, preserving already set session data.
 * 
 */
function phpSessionStart() {
  if (!phpSessionStarted()) {
    // Save current session data before starting it, as PHP will destroy it.
    $session_data = isset($_SESSION) ? $_SESSION : NULL;
    session_start();
    phpSessionStarted(TRUE);
    // Restore session data.
    if (!empty($session_data)) {
      $_SESSION += $session_data;
    }
  }
}


/**
*
* Destroys all data registered to a session.
*
*/
function phpSessionDestroy() {
  session_destroy();

  // Workaround PHP 5.2 fatal error "Failed to initialize storage module".
  // @see http://bugs.php.net/bug.php?id=32330
  session_set_save_handler(
  	'phpSessionOpen', 
  	'phpSessionClose', 
  	'phpSessionRead', 
  	'phpSessionWrite', 
  	'phpSessionDestroySID', 
  	'phpSessionGC'
  );
}


/**
 * Commit the current session, if necessary.
 *
 * If an anonymous user already has an empty session, destroy it.
 * 
 */
function phpSessionCommit() {
  if (empty($_SESSION)) {
    if (phpSessionStarted() && phpSessionSaveSession()) {
      // Destroy empty anonymous sessions.
      phpSessionDestroy();
    }
  }
  else if (phpSessionSaveSession()) {
    if (!phpSessionStarted()) {
      phpSessionStart();
    }
    
    // Write the session data.
    session_write_close();
  }
}


/**
 * 
 * 
 * Drops the wp_session table on plugin deactivation
 * 
 */
function phpSessionUninstall() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'session';

  $wpdb->query("DROP TABLE IF EXISTS $table_name");
}


/**
 * 
 * Creates the wp_session table on plugin activation
 *
 */
function phpSessionInstall() {
  global $wpdb;
  global $sessions_db_version;

  $table_name = $wpdb->prefix . 'session';

  $sql = "CREATE TABLE IF NOT EXISTS $table_name (
    ID bigint(20) unsigned NOT NULL,
    sid varchar(64) NOT NULL DEFAULT '',
    hostname varchar(128) NOT NULL DEFAULT '',
    timestamp int(11) NOT NULL DEFAULT '0',
    cache int(11) NOT NULL DEFAULT '0',
    session longtext,
    PRIMARY KEY (sid),
    KEY timestamp (timestamp),
    KEY ID (ID)
	);";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);

  add_option('session_db_version', $session_db_version);
}


/**
 * If Drupal is behind a reverse proxy, we use the X-Forwarded-For header
 * instead of $_SERVER['REMOTE_ADDR'], which would be the IP address
 * of the proxy server, and not the client's.
 *
 * @return
 *   IP address of client machine, adjusted for reverse proxy.
 */
function ip_address() {
  static $ip_address = NULL;

  if (!isset($ip_address)) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
  }

  return $ip_address;
}

?>