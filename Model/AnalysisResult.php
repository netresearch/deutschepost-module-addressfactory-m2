<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Model\AbstractModel;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;

class AnalysisResult extends AbstractModel implements AnalysisResultInterface
{
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
