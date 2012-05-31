=== PHP Session Handling ===
Contributors: mikepirog
Donate link: http://www.eastbaywebshop.com
Tags: drupal, pantheon, performance, session management
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 1.2

A full, but still alpha, implementation of custom PHP session handlers for WordPress.

== Description ==

This plugin alters WordPress to use a session based system that is Drupal-like and powered by PHP's 
native session handling.

We think that ultimately, READ: after this plugin is production ready, this approach to session handling will be 
more secure and performative than the default WordPress approach. We encourage you to download and help further
the development of this plugin, either by posting an issue, testing, or providing patches.

<strong>This plugin is still very much in alpha release and should not yet be used in a production environment.</strong>

This plugin was sponsored by <a href="http://www.eastbaywebshop.com">East Bay Development</a> and 
<a href="http://www.startupers.com">Startupers.com</a>. 

== Installation ==

The normal WordPress installation method should be used. However there are two notable exceptions.

1. If you are using a reverse proxy like Varnish it is possible that WordPress's default user cookies are being
stripped. If this is the case, please review your VCL file. You may need to configure this file to allow the needed
cookies.

2. When you activate this plugin through the admin interface you will be immediately logged out as the session 
system is switching. You need only log back in.  At some point we will want to provide a seemless transition.

== Changelog ==

= 1.2 = 
* Updated documentation

= 1.1 = 
* just a little git-svn test

= 1.0 =
* First "release". Basic session handling replacement.

== Upgrade Notice ==
