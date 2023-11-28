=== Login with Vipps ===
Contributors: wphostingdev,iverok,perwilhelmsen
Tags: woocommerce,vipps,login
Requires at least: 4.9.6
Tested up to: 6.4.1
Requires PHP: 7.2
Stable tag: 1.2.7
License: MIT
License URI: https://choosealicense.com/licenses/mit/

Login with Vipps is the easiest way to sign in and create an account. No need to worry about usernames and passwords.

== Description ==
Login with Vipps offers super-easy registration and login from the leading smart-payment app in Norway with more than 3.9 million users. There is no easier login. No more usernames or passwords. Vipps, and your customers are logged in.

The users need only remember their phone number. They are also able to provide information that websites often require, like e-mail, phone numbers and addresses, from their Vipps profile with no tedious data entry.

Logg in with Vipps is fully integrated into WordPress and WooCommerce and is free for both users and webshops. The plugin is easy to modify for your own applications.

This solution is great for all websites that wants to:

 * Get more logged-in users
 * All Vipps-users can create a profile on your website with just one click, and can be logged in directly on the next visit
 * You can modify content, messaging and retrieve stored shopping carts for the user
 * Your customers can see order histories and potentially register product returns
 * More logged-in users gives you better overview and better conversion rates

== Free for consumers and webshops ==
Login with Vipps is free for both websites and end users.

== Get started ==

 * If you are a user of Vipps for E-Commerce, you can add Login with Vipps to your account on https://portal.vipps.no and find your API-keys there - see "Installation" for more details.
 * If you do not have an account with Vipps, you can order this here: https://vipps.no/produkter-og-tjenester/bedrift/innlogging-og-identifisering/logg-inn-med-vipps/#kom-i-gang . When the order has been processed, you will be notified and you will be able to retrieve the API-keys on https://portal.vipps.no . Then see "Installation" for the next steps.

== Requirements ==

 * Wordpress version 4.9 or above
 * PHP version 7.0 or above
 * Your website must have an SSL certificate and be available through HTTPS
 * OpenSSL must be configured for your PHP installation
 * For WooCommerce, the version requirement is 3.3.4 or above
 * The port 443 must be open for outward traffic on your servers firewall

== Upgrade Notice ==
Version 1.2.7: Minor fixes
Version 1.2.0: Adds support for using the phone number as "login key" and changing email addresses; cleanup
Version 1.2.1: Minor fix for Gutenberg block
Version 1.2.2: Testing on 6.1
Version 1.2.3: Fix compatibility with php 7.4
Version 1.2.4: Fix compatibility with php 8.1
Version 1.2.5: Add filter for "invalid user" message
Version 1.2.6: Removed restrictions on filtering application and action in javascript; simplifying reuse of the Login block

== Feature Highlights ==

= Fully integrated with WooCommerce =
Allows login and registration on your account pages, cart, checkout and via shortcodes. Addresses automatically synchronized with Vipps on login.

= User can register with one click  =
Login with Vipps is the easiest way to create a new account. The user can easily share high-quality data from the user's Vipps profile with the site owner. Available information includes name, email, address, phone number, and birth date. The identity of all Vipps users is verified using BankID, Norway's leading electronic ID, so rest assured that these are real people with correct name and information. (Please note: Standard WordPress does not register information other than name and email, so apart from WooCommerce, you will need to write code to use this information for your particular application.) 

= Link existing account =
Already registered users can link their current accounts when signing in with Vipps or from their account page. Users can choose to update their address information from Vipps.

= Customizable for your application =
You can use the framework of this plugin to implement other solutions that require verified users, without actually requiring login. For example, you might create a system for having users sign their comments with Vipps so as to avoid spam issues.

== Shortcodes ==
 * `[login-with-vipps text="Log in with Vipps" application="wordpress"]` - This will print out a Login with Vipps button that will log you into the given application, which by default can be either Wordpress or WooCommerce.
 * `[continue-with-vipps text="Continue with Vipps" application="wordpress"]` - This is the same, except for a different default text

== Installation ==
**If you are an existing Vipps customer**, log onto the Vipps portal https://portal.vipps.no and retreive your API keys that you will need to install Login with Vipps

**If you are a new Vipps customer**, apply for an account [here](https://vipps.no/signup/logginnmedvipps) - before downloading the plugin. Choose "Integration through your partner", or if you don't know your partner, choose "direct integration" and fill out the form. When your application is approved, you will receive the API keys that you will need to install the plugin.

 1. Install the plugin using WordPress' [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory.
 2. Activate the plugin through the \'Plugins\' screen in WordPress.
 3. From the [Vipps portal](https://portal.vipps.no), get your Client ID and Client Secret and add them to the Settings-page for this plugin (see screenshot 1)
 4. Note your callback URL from the plugins settings-page and add that to the Vipps Portal (see screenshot 2)
 5. Modify the options as needed 

== Screenshots ==
1. Retrieving your Client ID and Client Secret from the [Vipps Portal](https://portal.vipps.no)
2. Registerering your redirect URI 
3. Activating Login with Vipps at the [Vipps Portal](https://portal.vipps.no)

== Customizing the Plugin ==
To use 'Continue with Vipps' in your application, there are two levels of customizations available, except for a mass of filters and hooks.

= Adding another 'application' to log into =
Logging into basic Wordpress and into an application like WooCommerce is different in the details, especially with regards to 
  * what page to redirect to (the profile page, or your account page, or maybe the checkout page), 
  * handling of user data (for WooCommerce you want to update the users' address) 
  * and for error handling.  

For your own application, you may well have other actions you want done after new user registration, logins etc. We aim to provide support for as many applications as possible in time, but to create your own, these are the main steps:

  * Define your application with a name. It should be a simple slug, like 'wordpress' or 'woocommerce'
  * Create your login button, and make it call the supplied Javascript function "login_with_vipps" with your application name as argument.
  * To customize, you can now modify several filters and hooks, the most important of which would be:
  * 'continue_with_vipps_error_*your application*_login_redirect'. This takes and returns an error-page redirect, the error string, and the login session data as an array. You can here return your own error page.
  * 'continue_with_vipps_before_*your application*_login_redirect'. This takes your logged-in user and a session (which can be called as an array) and is called right before the user is redirected. This would be a good place to add a filter to 'login_redirect' for instance.
  * Filter 'continue_with_vipps_*your application*_users_can_register'. Takes a truth value, an array of userinfo from Vipps and a session, and should return true only if you allow the user to register
  * Filter 'continue_with_vipps_*your application*_create_userdata'. For newly registered users, takes an array to be passed to wp_update_user, an array of userinfo from Vipps, and a session. You can here add your extra meta fields
  * Filter 'continue_with_vipps_*your application*_allow_login'. Takes a truth value, a user object, userinfo from Vipps and a session, and returns true only if the user is allowed to log in

= Adding another 'action' apart from logging in =
You may want to do other things than logging in with the users' confirmed Vipps identity, and this plugin absolutely allows this. This might be submisssions of comments, reviews and so forth without requiring logins, or even just as a convenient way of letting users input their address. 

These are the main steps:

 * Define your own action, like 'submitaddress'.
 * Create your button. The handler should call the static method `ContinueWithVipps::getAuthRedirect($action)` (you can also provide an array of sessiondata which will be available in your handlers, and restrict the scope of the data to retrieve from Vipps. The return value is an URL to which you should redirect your user.
 * Create your error handler. This should be hooked to 'continue_with_vipps_error_*your action*'. It will receive an error string, a description of the error, sometimes an error hint, and the contents of your session (which will no longer be active). You will need to redirect to your error page here, and show your user the error. The redirect is important, you should not output content in this action.
 * Create your succes handler. This should be hooked to 'continue_with_vipps_*your action*'. It will receive an array of user information from Vipps, and a live session. This handler too should end with a redirect to your success page. 

The rest is a simple matter of programming.

== Changelog ==
= 2023.11.28 Version 1.2.7 =
Fix some 8.2 deprecations

= 2023.05.15 Version 1.2.6 =
Small bugfixes, removal on some restrictions to aid implementation of non-login applications

= 2023.03.27 Version 1.2.5 =

= 2023.02.08 Version 1.2.4 =
* Add filter for the error message when the user is invalid

= 2023.01.09 Version 1.2.3 =
* Fix deprection warning under php8.1

= 2022.10.27 Version 1.2.3 =
* Fix compatibility with 7.4

= 2022.10.26 Version 1.2.2 =
* Testing for 6.1.0

= 2022.06.13 Version 1.2.1 =
* Small fix for Gutenberg blocks

= 2022.04.25 Version 1.2.0 =
* Add support for using the phone number as Vipps ID for users
* Removes old "verify your email account" code as it was not future-proof. Filters allow developers to reimplement this if neccessary
* Fix CSS to be more independent of certain themes

= 2022.03.18 Version 1.1.21 =
* Removed the api_version_2 scope

= 2022.01.28 Version 1.1.20 =
* Add filter `login_with_vipps_openid_scope ( $scope, $action, $sessiondata)` to allow developers to ask for e.g. birthDate. The filter will always receive an array.

= 2021.12.20 Version 1.1.19 =
* Fix COOKIEPATH on multisite installs where it isn't set.

= 2021.12.13 Version 1.1.18 =
* Made 'login_with_vipps_woo_login_redirect' get access to the login session
* Created a javascript hook for people customizing login

= 2021.12.09 Version 1.1.17 =
* Add suppression of more than one call to the login process

= 2021.12.01 Version 1.1.16 =
* For some reason, a bug where the blogversion was *outputed* didn't kick in on our systems, but broke login for some users. This version restores normal operations.

= 2021.11.18 Version 1.1.15 =
* Versioning headers sent to Vipps to aid debugging

= 2021.11.10 Version 1.1.14 =
* Slight improvement in programmability of the login function for those who want to extend the plugin

= 2021.10.04 Version 1.1.13 =
* Improved texts for how to use the plugin

= 2021.09.05 Version 1.1.12 =
* Improved support for WPML
* Support for running against Vipps' test/dev server using filters

= 2021.06.16 Version 1.1.11 =
* CSS Tweaks

= 2021.06.09 Version 1.1.10 =
* Testing for WP 5.8

= 2021.05.31 Version 1.1.9 =
* Get-started banner for users that have not completed configuration
* Remove the old 'verify email account feature' 

= 2021.04.27 Version 1.1.8 =
* Fix the email confirmations - this will be removed in a future version however

= 2021.04.26 Version 1.1.7 =
* Ensure the 'woocommerce_created_customer' hook is called correctly when registering on WooCommerce - this fixes the bug where user registration emails weren't sent.

= 2021.04.19 Version 1.1.6 =
* Tested for latest versions of WP and Woo

= 2021.03.23 Version 1.1.5 =
* Handle deprecation of the 'sid' field of the userinfo

= 2021.03.22 Version 1.1.4 =
* Bugfix

= 2021.03.01 Version 1.1.3 =
* Bugfix

= 2021.01.18 Version 1.1.2 =
* Supports _requiring_ certain users, roles or everybody to use Vipps to log in or to confirm their login

= 2020.12.30 Version 1.1.1 =
* Fix bug causing output when WP_DEBUG is on. Thanks to @horgster on wp.org for reporting.

= 2020.12.21 Version 1.1.0 =
* Now uses version 2 of the Vipps Login Api and provides a Gutenberg block for a "Login with Vipps" button

= 2020.12.14 Version 1.0.13 =
* Versions tested on WP 5.6 and WC 4.8.0

= 2020.11.24 Version 1.0.12 =
* Version tested on WP 5.5.3 and WC 4.7.0

= 2020.10.19 Version 1.0.11 =
* If activated when Checkout with Vipps for WooCommerce is installed, configure that plugin to create users when using Express Checkout

= 2020.09.28 Version 1.0.10 =
* Make new Woo accounts created by Vipps login count as "Authorized" for All-in-one WP security; improved configuration options

= 2020.06.29 Version 1.0.9 =
* Fixed misspelled shortcode name, version update

= 2020.06.07 Version 1.0.8 =
* Made user confirmation optional and off by default, since this is now handled by Vipps

= 2019.12.06 Version 1.0.7 =
* Stylesheet fixes

= 2019.12.06 Version 1.0.6 =
* Added a filter 'login_with_vipps_update_address_info' which returns whether or not to update the address info for a user. Takes the current truth value, the customer object, and userinfo from Vippss.

= 2019.12.06 Version 1.0.5 =
* Added a 30s leeway to the JWT verifier, and made it so logging will go to the system log for Woo installations as well

= 2019.12.06 Version 1.0.4 =
* Conflicts with certain plugins that check for 'code' and 'state' in the parse_request hook fixed by deleting these if we are handling Vipps returns

= 2019.12.06 Version 1.0.3 =
* Change named of session key, to be compatible out-of-the-box with wpengine. Thanks to Sondre @ NattogDag for help with debugging
* Added convenience filters 'login_with_vipps_woo_error_redirect' and 'login_with_vipps_woo_login_redirect' to handle redirects on error and success for WooCommerce in particular

= 2019.12.06 Version 1.0.2 =
* Made account title filter more forgiving

= 2019.11.29 Version 1.0.1 =

= 1.0 =
v1.0.0 First release


