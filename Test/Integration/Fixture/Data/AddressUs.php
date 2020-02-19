<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Fixture\Data;

/**
 * Class AddressUs
 *
 * @author  Gurjit Singh <gurjit.singh@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class AddressUs implements AddressInterface
{
    public function getStreet(): string
    {
        return '3131 S Las Vegas Blvd';
    }

    public function getCity(): string
    {
        return 'Las Vegas';
    }

    public function getPostcode(): string
    {
        return '89109';
    }

    public function getCountryId(): string
    {
        return 'US';
    }

    public function getRegionId(): string
    {
        return '39';
    }
}
