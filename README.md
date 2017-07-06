# Gravity Forms Form Integrator

An extension for the [Gravity Forms](http://www.gravityforms.com/) WordPress plugin.

This extension provides an admin interface that allows the user to create 'feeds' that will asynchronously submit data from a gravity form submission to a 3rd party URL.

It is designed to 'mock' form submissions by POST-ing the data as a url encoded form request. The admin interface also provides a section where key/value data can be provided to send along with the request - this is to mock the inclusion of data in hidden fields, which is pretty common for services such as salesforce. There is the added benefit of these values never being exposed to the front-end user as well.

A single gravity forms submission can post to multiple external services by having multiple feeds, each of which can have it's own settings, selection of values to send etc

## Installation

Manual WordPress plugin install
- ensure gravity forms is installed and active on your WordPress site
- download the [latest release](https://github.com/framedigital/gravityforms-form-integrator/releases) as a zip
- install the plugin using the WordPress admin
- activate and configure the feeds

*Note: You will not recieve any updates as they are released when installing via this method*

WordPress install via Github Updater
- This plugin has the correct headers to be installed via Andy Fragen's wonderful [Github Updater Plugin](https://github.com/afragen/github-updater)
- Follow the Github Updater instructions to install
- When new tagged releases are published, the Github Updater plugin will allow you to upgrade via the normal process in wp-admin

## Composer Installation :tada:
 - This plugin is published via packagist, and has the type "wordpress-plugin" set to facilitate installation via composer/installers
 - `composer require framecreate/gravityforms-form-integrator`
 - Pat yourself on the back for being a PHP developer not stuck in 2006 :nail-care:
 
 ## Usage
 
 ### Admin interface
 
 After activation there will be a new item in the settings menu called 'Form Integrator'
 ![New Settings Item in GF settings Menu](https://cdn-img-one.frame.hosting/form-plugin-docs/form-integrator-form-settings-1.jpg?w=1200)

 Create a feed and you will be presented with the main settings page
 
 Conditionals
 * You can choose whether or not the feed runs on a given submission depending on the value or a Gravity Form field
 * Only one conditional field per feed is supported (for now)
 
 Dynamic Fields
 * You can define any number of name / gravity form field value combinations
 * In the textbox you place the 'name' that the parameter should be sent to the external service as
 * Using the select box you pick a corresponding Gravity Form field, the value of this field when submitted will be sent
 * The name in the textbox should correspond to the HTML 'name' attr of the input if you are mocking a form submission
 * Everything is sent as a URL encoded HTTP POST so it's possible to talk to non-form based endpoints that accept args via a query string
 
 Extra Data
 * Designed for 'static' values that don't require user input
 * Use for any 'type=hidden' inputs on your forms that don't require dynamic population (by query string or js)
 * Means you don't need 5-15 extra hidden fields in your Gravity Form when integrating with multiple services
 * Be sure that you're posting to a secure endpoint (via https) before including and sensitive data, keep in mind it's all send in clear text
 
 ![Main Settings page for form integrator](https://cdn-img-one.frame.hosting/form-plugin-docs/form-integrator-form-settings-2.jpg?w=1200)
 
 ### Environments && Enabling Async Processing
 
 The plugin will exhibit some extra behaviour is a constant names `WP_ENV` is defined
 - if `WP_ENV` is defined, and NOT `live` or `production` then each feed will dump its values on screen for debug on submit *in addition to sending the request*
 - if `WP_ENV` is defined, and IS `live` or `production` then the feeds will utilise asynchronous processing - this drastically speeds things up for the end user experience
 
 In order to utilise the Async feed processing features you must define a `WP_ENV` constant, and the value must be either `live` or `production`
 
 ### Managing Multiple feeds
 
 A single form can have multiple feeds to submit to multiple external services, or multiple conditional feeds etc
 
 Feeds can be disabled while keeping their settings intact - useful if they're not ready for primetime or the feed is seasonal
 
  ![Enable / Disable feeds via the admin](https://cdn-img-one.frame.hosting/form-plugin-docs/form-integrator-form-settings-3.jpg?w=1200)

**Cloning Feeds across forms**

There isn't currently any copy/clone feed action, so if you need to configure a single external service to work across many forms it can get a bit tedious

Each feed's settings are stored in a single row in the `%%Your DB Prefix%%_gf_addon_feed` table, it's pretty easy to duplicate the rows and just change the `form_id` value to match a different form.

The feed settings are all JSON, so you can edit them without having to worry about PHP Serialized data woes
 
## Filters & Extending this plugin

The plugin has 2 main filters - these allow you to manipulate the data before it is submitted to the external service

Example use case: converting Gravity Forms checkbox or multi-select fields into a format salesforce will understand

 ````php
 <?php
 
 add_filter('gf_form_integrator_modify_dynamic_field_value', 'myFormIntegratorFilter', 10, 7);
 
 // This filter is applied to each dynamic field map pairing before it's added to the array
 function myFormIntegratorFilter(  $fieldValue, $fieldName, $fieldObject, $formIntegratorObject, $gf_feedArray, $gf_entryArray, $gf_formArray ){
    // Do Stuff here
    return $fieldValue;
 };
 
 // Even though filters technically shouldn't cause side effects, you can add additional items to the array via 
 // $formIntegratorObject->_postDataValues['my_extra_key'] = 'my_extra_value'
 
 // return false to prevent this value from being added to the 'postDataValues' array
 ````
 
 There is also a filter called just before all the values are sent, containing the array of all data to be sent in the POST request
 
  ````php
  <?php
  
  add_filter('gf_form_integrator_modify_values_pre_submit', 'myFormIntegratorArrayFilter', 10, 4);
  
  // This filter is applied once right before the POST request is made
  function myFormIntegratorFilter(  $arrayOfData, $gf_feedArray, $gf_entryArray, $gf_formArray ){
     // Do Stuff here
     return $arrayOfData;
  };
  
   // return false to prevent the submission of ALL VALUES to the service (cancel the whole thing)
   // use with caution, implement logging in your filter if you are going to short circuit things (add a note or something)
  
  ````
 
## Tips
 
 Do your validation using Gravity Forms - the feed only gets processed for valid submissions
 
 If you are submitting to Pardot, be aware that you need to configure a SEPERATE feed for Salesforce web2lead. 
 
 Pardot passes these values on after submission using some heaps hacky JS, as we're making this request programatically there's no browser to run this JS!
  
## Release Notes:

* 1.0: initial release
* 2.0: added dynamic field maps
* 2.1: remove unnecessary boilerplate
* 2.2: add github updater headers
* 2.2.1: fix logger class issues by commenting out logger
* 2.3.0: Update to improve usage across projects
    - Async form processing to speed up submissions
    - Remove a few remaining boilerplate references
    - Add a simple (single) conditional check to the feed activation
* 2.3.1: customize field values via filters
 	- Change async to only in production for easier debugging with xdebug
 	- add filter to allow value changes, pave the way for proper multi-list working with salesforce
* 2.3.2: Allow filter to manipulate all postData values through object instance
* 2.3.3: Bug fixes to checkbox values
* 2.3.4: Add additional filter before final values submission to increase flexibility
* 2.3.5: Enable disabling of the whole request via a filter for more complex condition

