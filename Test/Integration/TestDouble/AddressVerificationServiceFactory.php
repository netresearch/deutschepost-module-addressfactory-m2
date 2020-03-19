<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestDouble;

use Magento\TestFramework\Helper\Bootstrap;
use PostDirekt\Sdk\AddressfactoryDirect\Api\AddressVerificationServiceInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Api\ServiceFactoryInterface;
use Psr\Log\LoggerInterface;

class AddressVerificationServiceFactory implements ServiceFactoryInterface
{
    /**
     * Instead of the original SDK's address verification service, return whatever was configured via di.
     *
     * @param string $username
     * @param string $password
     * @param LoggerInterface $logger
     * @param bool $sandboxMode
     * @return AddressVerificationServiceInterface
     */
    public function createAddressVerificationService(
        string $username,
        string $password,
        LoggerInterface $logger,
        bool $sandboxMode = false
    ): AddressVerificationServiceInterface {
        return Bootstrap::getObjectManager()->get(AddressVerificationServiceInterface::class);
    }
}
