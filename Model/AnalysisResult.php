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
     * @return int
     */
    public function getOrderAddressId(): int
    {
        return (int) $this->getData(self::ORDER_ADDRESS_ID);
    }

    /**
     * @return string
     */
    public function getStatusCodes(): string
    {
        return $this->getData(self::STATUS_CODE);
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->getData(self::FIRST_NAME);
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->getData(self::LAST_NAME);
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->getData(self::CITY);
    }

    /**
     * @return string
     */
    public function getPostalCode(): string
    {
        return $this->getData(self::POSTAL_CODE);
    }

    /**
     * @return string
     */
    public function getStreet(): string
    {
        return $this->getData(self::STREET);
    }

    /**
     * @return string
     */
    public function getStreetNumber(): string
    {
        return (string) $this->getData(self::STREET_NUMBER);
    }
}
