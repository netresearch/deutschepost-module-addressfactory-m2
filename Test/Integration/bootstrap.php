<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

/**
 * Backport of the Magento 2.4.9 fix for Transaction::_startTransaction().
 *
 * Magento 2.4.8's TestFramework Transaction class calls PHPUnit 9 APIs
 * (getTestResultObject, PHPUnit\Framework\Warning) removed in PHPUnit 10.
 * When a warning occurs during transaction setup, the error is silently
 * swallowed, leaving transparent transactions leaked and corrupting
 * subsequent tests.
 *
 * This bootstrap intercepts the Transaction class autoload and provides
 * the clean 2.4.9 version that removes the broken error handling.
 *
 * @see https://github.com/magento/magento2/issues/39463
 * Remove after upgrading to Magento 2.4.9+.
 */
spl_autoload_register(static function (string $class): void {
    if ($class === 'Magento\TestFramework\Event\Transaction') {
        require __DIR__ . '/Polyfill/Transaction.php';
    }
}, true, true);

require __DIR__ . '/../../../../../dev/tests/integration/framework/bootstrap.php';
