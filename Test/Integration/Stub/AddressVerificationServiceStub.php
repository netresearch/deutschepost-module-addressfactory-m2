<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Stub;

use PostDirekt\Sdk\AddressfactoryDirect\Api\AddressVerificationServiceInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Api\Data\RecordInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Exception\ServiceException;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Record;

class AddressVerificationServiceStub implements AddressVerificationServiceInterface
{
    /**
     * @var Record[]
     */
    public $records = [];

    private $getRecordsCalled = false;

    private $getRecordByAddressCalled = true;


    public function openSession(string $configName, string $clientId = null): string
    {
        return "test";
    }

    public function closeSession(string $sessionId): void
    {
        return;
    }

    public function getRecordByAddress(
        string $postalCode = '',
        string $city = '',
        string $street = '',
        string $houseNumber = '',
        string $lastName = '',
        string $firstName = '',
        string $sessionId = null,
        string $configName = null,
        string $clientId = null
    ): RecordInterface {
        if (empty($this->records)) {
            throw new ServiceException('no records');
        }

        $this->getRecordByAddressCalled = true;
        return $this->records[0];
    }

    public function getRecords(
        array $records,
        string $sessionId = null,
        string $configName = null,
        string $clientId = null
    ): array {
        if (empty($this->records)) {
            throw new ServiceException('no records');
        }

        $this->getRecordsCalled = true;
        return $this->records;
    }

    public function isGetRecordsCalled(): bool
    {
        return $this->getRecordsCalled;
    }

    public function isGetRecordByAddressCalled(): bool
    {
        return $this->getRecordByAddressCalled;
    }
}
