Find unused Joomla language constants
=====================================

This is a simple one file solution that will scan your joomla project for unused language constants. It can help you reduce clutter and make translating into new languages faster/easier/cheaper.

This works for both Joomla core and any 3rd party component, plugin or module.

Simply download the main file and put it in the root of your Joomla site and access it in your browser.

Feedback is very welcome.

CLI Use
=====================================

	php findunusedconstantscli.php --file=<file path>

Example of file path:

	language/en-GB/en-GB.com_redshop.ini

or use comma seperated path

	language/en-GB/en-GB.com_redshop.ini,language/en-GB/en-GB.com_redshop.sys.ini
