A plugin for integrating gravity forms with 3rd party API's (salesforce etc)


##Release Notes:

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

