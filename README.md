# Deutsche Post Direkt ADDRESSFACTORY for Magento2

## Deprecation Notice

**The Deutsche Post Direkt service ADDRESSFACTORY DIRECT itself is not affected and remains available for booking.**

Only this module for Magento 2 is being discontinued. In agreement with Deutsche Post Direkt GmbH, Netresearch ends development and support for it:

* This module is no longer offered to new customers.
* Official support for existing users of this module ends on **December 31, 2026**.
* The source code remains available on GitHub until approximately **June 2027**.

The module Addressfactory for Magento 2 allows you to automatically analyze and correct shipping addresses
in your shop system using the service of Deutsche Post Direkt.

## Requirements

* PHP >= 8.3

## Compatibility

* Magento >= 2.4.8

## Installation Instructions

Install sources:

    composer require deutschepost/module-addressfactory-m2

Enable module:

    ./bin/magento module:enable PostDirekt_Addressfactory
    ./bin/magento setup:upgrade

Flush cache and compile:

    ./bin/magento cache:flush
    ./bin/magento setup:di:compile

## Uninstallation

To unregister the carrier module from the application, run the following command:

    ./bin/magento module:uninstall --remove-data PostDirekt_Addressfactory
    composer update

This will automatically remove source files, clean up the database, update package dependencies.

## Support

In case of questions or problems, please have a look at the
[Support Portal (FAQ)](http://postdirekt.support.netresearch.de/) first.

If the issue cannot be resolved, you can contact the support team via the
[Support Portal](http://postdirekt.support.netresearch.de/) or by sending an email
to <postdirekt.support@netresearch.de>.

## License

[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

## Copyright

(c) 2023 Netresearch DTT GmbH
