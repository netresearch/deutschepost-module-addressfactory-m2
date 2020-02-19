<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * AnalysisResult ResourceModel
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class AnalysisResult extends AbstractModel
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
     * Initialize AnalysisResult resource model.
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\AnalysisResult::class);

        parent::_construct();
    }

    /**
     * @param int $orderAddressId
     */
    public function setOrderAddressId(int $orderAddressId): void
    {
        $this->setData(self::ORDER_ADDRESS_ID, $orderAddressId);
    }

    /**
     * @return int
     */
    public function getOrderAddressId(): int
    {
        return (int) $this->getData(self::ORDER_ADDRESS_ID);
    }

    /**
     * @param string[] $statusCodes
     */
    public function setStatusCodes(array $statusCodes): void
    {
        $this->setData(self::STATUS_CODE, implode(',', $statusCodes));
    }

    /**
     * @return string[]
     */
    public function getStatusCodes(): array
    {
        $result = [];
        if ($this->getData(self::STATUS_CODE)) {
            $result = explode(',', $this->getData(self::STATUS_CODE));
        }

        return $result;
    }

    /**
     * @param string $firstName
     */
    public function setFirstName(string $firstName): void
    {
        $this->setData(self::FIRST_NAME, $firstName);
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->getData(self::FIRST_NAME);
    }

    /**
     * @param string $lastName
     */
    public function setLastName(string $lastName): void
    {
        $this->setData(self::LAST_NAME, $lastName);
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->getData(self::LAST_NAME);
    }

    /**
     * @param string $city
     */
    public function setCity(string $city): void
    {
        $this->setData(self::CITY, $city);
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->getData(self::CITY);
    }

    /**
     * @param string $postalCode
     */
    public function setPostalCode(string $postalCode): void
    {
        $this->setData(self::POSTAL_CODE, $postalCode);
    }

    /**
     * @return string
     */
    public function getPostalCode(): string
    {
        return $this->getData(self::POSTAL_CODE);
    }

    /**
     * @param string $street
     */
    public function setStreet(string $street): void
    {
        $this->setData(self::STREET, $street);
    }

    /**
     * @return string
     */
    public function getStreet(): string
    {
        return $this->getData(self::STREET);
    }

    /**
     * @param string $streetNumber
     */
    public function setStreetNumber(string $streetNumber): void
    {
        $this->setData(self::STREET_NUMBER, $streetNumber);
    }

    /**
     * @return string
     */
    public function getStreetNumber(): string
    {
        return (string) $this->getData(self::STREET_NUMBER);
    }
}
