<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Api\Data;

/**
 * Interface AnalysisResultInterface
 */
interface AnalysisResultInterface
{
    public const ORDER_ADDRESS_ID = 'order_address_id';

    public const STATUS_CODE = 'status_codes';

    public const FIRST_NAME = 'first_name';

    public const LAST_NAME = 'last_name';

    public const CITY = 'city';

    public const POSTAL_CODE = 'postal_code';

    public const STREET = 'street';

    public const STREET_NUMBER = 'street_number';

    /**
     * @return int
     */
    public function getOrderAddressId(): int;

    /**
     * @return string[]
     */
    public function getStatusCodes(): array;

    /**
     * @return string
     */
    public function getFirstName(): string;

    /**
     * @return string
     */
    public function getLastName(): string;

    /**
     * @return string
     */
    public function getCity(): string;

    /**
     * @return string
     */
    public function getPostalCode(): string;

    /**
     * @return string
     */
    public function getStreet(): string;

    /**
     * @return string
     */
    public function getStreetNumber(): string;
}
