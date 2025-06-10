<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestDouble;

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

    private $requestCount = 0;

    private $requestedRecordsCount = 0;

    #[\Override]
    public function openSession(string $configName, ?string $clientId = null): string
    {
        return "test";
    }

    #[\Override]
    public function closeSession(string $sessionId): void
    {
    }

    #[\Override]
    public function getRecordByAddress(
        string $postalCode = '',
        string $city = '',
        string $street = '',
        string $houseNumber = '',
        string $lastName = '',
        string $firstName = '',
        ?string $sessionId = null,
        ?string $configName = null,
        ?string $clientId = null
    ): RecordInterface {
        $this->requestCount++;

        if (empty($this->records)) {
            throw new ServiceException('no records');
        }

        return $this->records[0];
    }

    #[\Override]
    public function getRecords(
        array $records,
        ?string $sessionId = null,
        ?string $configName = null,
        ?string $clientId = null
    ): array {
        $this->requestedRecordsCount += count($records);

        if (empty($this->records)) {
            throw new ServiceException('no records');
        }

        return $this->records;
    }

    /**
     * Check how many records were sent to the web service via {@see getRecordByAddress}.
     *
     * @return int
     */
    public function getRecordByAddressRequestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * Check how many records were sent to the web service via {@see getRecords}.
     *
     * @return int
     */
    public function getRequestedRecordsCount(): int
    {
        return $this->requestedRecordsCount;
    }
}
