Silverstripe Contact Management Module
======================================

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silvercommerce/contact-admin/badges/quality-score.png?b=1.0)](https://scrutinizer-ci.com/g/silvercommerce/contact-admin/?branch=1.0)

Module that allows users to create, manage, group and bulk import/export
contacts.

These contacts are generic objects and so are designed to be extended on
(for example assigning to mailing lists, orders, quotes, etc).

Also adds autocomplete based filtering to the contact admin interface for easier
searching of records.

## Author
This module was created by [i-lateral](http://www.i-lateral.com).

## Installation
Install this module either by downloading and adding to:

[silverstripe-root]/contacts

Then run: http://yoursiteurl.com/dev/build/ or # sake dev/build

Or alternativly add to your project's composer.json

## Requirements
In order to use this module you will need the following Silverstripe
modules:

* gridfield-bulk-editing-tools (v2.1.1+)
* tagfield (any version)
* silverstripe-autocomplete (v3.1.0+)

## Usage
Once installed, fi you log into the admin interface, you will see a
"Contacts" button on the left. Clicking it will take you to the contact
management interface
