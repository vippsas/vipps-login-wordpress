<!-- START_METADATA
---
title: Vipps Login for WordPress and WooCommerce
sidebar_position: 1
pagination_next: null
pagination_prev: null
---
END_METADATA -->

# Vipps Login for WordPress and WooCommerce

![Support and development by WP Hosting ](./docs/images/wphosting.svg#gh-light-mode-only)![Support and development by WP Hosting](./docs/images/wphosting_dark.svg#gh-dark-mode-only)

![Vipps](./docs/images/vipps.png) *Available for Vipps.*

![MobilePay](./docs/images/mp.png) *Will be available for MobilePay at some point, but the date hasn't been determined.*


*This plugin is built and maintained by [WP Hosting](https://www.wp-hosting.no/) and is hosted on [GitHub](https://github.com/vippsas/vipps-login-wordpress) and at [Login with Vipps on WordPress](https://wordpress.org/support/plugin/login-with-vipps/).*

<!-- START_COMMENT -->
💥 Please use the plugin pages on [https://developer.vippsmobilepay.com](https://developer.vippsmobilepay.com/docs/plugins/). 💥
<!-- END_COMMENT -->

*Login with Vipps* is the easiest way to sign in and create an account. No need to worry about usernames and passwords. All you need to sign in is your phone number. Vipps, and you are logged in. Fully integrated with WooCommerce. Easy to customize for your own applications.

Vipps is the leading smart payment app in Norway, used by more than 75% of Norwegians and tops the list of most positive word of mouth for any brand.

*Login with Vipps* suits all websites that need users to sign in and want to tailor content and dialogue with their customers.

* Anyone with Vipps can create a profile on your website with just one click and can be directly signed in at the next visit.
* Your customers get what they want faster
* You get more people signed in, leading to more insight and increased conversion.

## Requirements

* WordPress version 4.9 or above
* PHP version 7.0 or above
* Your website must have an SSL certificate and be available through HTTPS
* OpenSSL must be configured for your PHP installation
* For WooCommerce, the version requirement is 3.3.4 or above
* The port 443 must be open for outward traffic on your servers firewall

## Feature Highlights

### Fully integrated with WooCommerce

Allows login and registration on your account pages, cart, checkout and via shortcodes. Addresses automatically synchronized with Vipps on login.

### No need for usernames and passwords

Users don't need to worry about forgetting usernames and passwords. All they need to sign in is their phone number. For an even smoother sign in experience, the user can choose to be remembered in the browser, enabling automatic sign-ins for later visits.

### User can register with one click

Login with Vipps is the easiest way to create a new account. Sharing high-quality data from the user's Vipps profile with the site owner. Available information includes name, email, address, phone number, and birthdate. The identity of all Vipps users is verified using BankID, Norway's leading electronic ID, so rest assured that these are real people with correct name and information.  (Please note: Standard WordPress does not register information other than name and email, so apart from WooCommerce, you will need to write code to use this information for your particular application.)

### Link existing account

Already registered users can link their current accounts when signing in with Vipps or from their account page. Users can choose to update their address information from Vipps.

### Fully integrated with WooCommerce and available for all relevant areas of your store

Login with Vipps can be added to all relevant pages in your web shop: login, registration, cart, and checkout pages.

### Free of charge

Login with Vipps is free of charge for site owners and end-users. Merchants that already use Vipps online payment can add Sign in with Vipps to their account at [https://portal.vipps.no](https://portal.vipps.no). New site owners need to set up an agreement with Vipps to use the service. This can be ordered [here](https://portal.vipps.no/login).

### Customizable for your application

You can use the framework of this plugin to implement other signed actions, such as submitting data with verified identities, without requiring the user to login.

## Shortcodes

* `[login-with-vipps text="Log in with Vipps" application="wordpress"]` - This will print out a *Login with Vipps* button that will log you into the given application, which by default can be either WordPress or WooCommerce.
* `[continue-with-vipps text="Continue with Vipps" application="wordpress"]` - This is the same, except for a different default text.

## Customizing the Plugin

To use 'Continue with Vipps' in your application, there are two levels of customizations available, except for a mass of filters and hooks.

### Adding another 'application' to log into

Logging into basic WordPress and into an application like WooCommerce is different in the details, especially in regard to what page to redirect to (the profile page, or your account page, or maybe the checkout page), with handling of user data (for WooCommerce you want to update the users' address) and for error handling.  For your own application, you may well have other actions you want done after new user registration, logins etc. We aim to provide support for as many applications as possible in time, but to create your own, these are the main steps:

* Define your application with a name. It should be a simple slug, like `wordpress` or `woocommerce`.
* Create your login button, and make it call the supplied JavaScript function `login_with_vipps` with your application name as argument.
* To customize, you can modify several filters and hooks. The most important would be:
* `continue_with_vipps_error_*your application*_login_redirect`. This takes and returns an error-page redirect, the error string, and the login session data as an array. You can here return your own error page.
* `continue_with_vipps_before_*your application*_login_redirect`. This takes your logged-in user and a session (which can be called as an array) and is called right before the user is redirected. This would be a good place to add a filter to `login_redirect`, for instance.
* Filter `continue_with_vipps_*your application*_users_can_register`. This takes a truth value, an array of [userinfo from Vipps](https://developer.vippsmobilepay.com/api/userinfo/), and a session. This should return `true` only if you allow the user to register.
* Filter `continue_with_vipps_*your application*_create_userdata`. For newly registered users, this takes an array to be passed to `wp_update_user`, an array of [userinfo from Vipps](https://developer.vippsmobilepay.com/api/userinfo/), and a session. You can here add your extra meta fields.
* Filter `continue_with_vipps_*your application*_allow_login`. This takes a truth value, a user object, [userinfo from Vipps](https://developer.vippsmobilepay.com/api/userinfo/), and a session. This returns `true` only if the user is allowed to log in.

### Adding another 'action' apart from logging in

You may want to do other things than logging in with the users' confirmed Vipps identity, and this plugin absolutely allows this. This might be submissions of comments, reviews and so forth without requiring logins, or even just as a convenient way of letting users input their address.

These are the main steps:

* Define your own action, like `submit address`.
* Create your button. The handler should call the static method `ContinueWithVipps::getAuthRedirect($action)`. You can also provide an array of session data which will be available in your handlers, and restrict the scope of the data to retrieve from Vipps. The return value is a URL to which you should redirect your user.
* Create your success handler.
* Create your error handler. This should be hooked to `continue_with_vipps_error_*your action*`. It will receive an error string, a description of the error, sometimes an error hint, and the contents of your session (which will no longer be active). You will need to redirect to your error page here and show your user the error. The redirect is important, you should not output content in this action.
* Create your success handler. This should be hooked to `continue_with_vipps_*your action*`. It will receive an array of [userinfo from Vipps](https://developer.vippsmobilepay.com/api/userinfo/), and a live session. This handler too should end with a redirect to your success page.

The rest is a simple matter of programming.
