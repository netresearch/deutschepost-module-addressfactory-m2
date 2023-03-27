# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## 1.4.1

### Fixed

- Prevent analysis actions on billing address updates.
- Consider website level configuration settings during cron analysis.
- Change deliverability status from _Undeliverable_ to _Possibly deliverable_ if address
  was (partially) corrected during address analysis.
- Replace redundant and confusing mass actions by one configurable mass action.
- Initialize orders with _Not analysed_ status if _No Automatic Analysis_ is configured.
- The configuration setting _automatically cancel_ now has higher priority than
  _automatically put on hold_.
- Display status value _Manually edited_ in admin panel orders grid.

## 1.4.0

### Added

- Compatibility for Magento 2.4.5.

## 1.3.0

### Added

- Support Magento 2.4.4

## 1.2.0

### Added

- Ability to re-check the shipping address after manually editing it.
- Support for PostOffice, ParcelStation and Bulkreceiver addresses.

## 1.1.2

### Fixed

- Fix typo, contributed by [@sprankhub](https://github.com/sprankhub) via [PR #2](https://github.com/netresearch/deutschepost-module-addressfactory-m2/pull/2)

## 1.1.1

### Fixed

- Prevent ambiguous column error when applying the `status` filter to the order collection.

## 1.1.0

### Fixed

- addresses with no house number are now marked undeliverable
- addresses that are not correctable are now marked accordingly

### Changed

- expand module configuration field comments
- improve translations

### Added

- support Magento 2.4

### Removed

- support for Magento 2.2

## 1.0.0

- Initial release
