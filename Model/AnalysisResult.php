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
    #[\Override]
    protected function _construct(): void
    {
        $this->_init(ResourceModel\AnalysisResult::class);

        parent::_construct();
    }

    /**
     * @return int
     */
    #[\Override]
    public function getOrderAddressId(): int
    {
        return (int) $this->getData(self::ORDER_ADDRESS_ID);
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getStatusCodes(): array
    {
        $result = [];
        if ($this->getData(self::STATUS_CODE)) {
            $result = explode(',', (string) $this->getData(self::STATUS_CODE));
        }

        return $result;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getFirstName(): string
    {
        return $this->getData(self::FIRST_NAME);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getLastName(): string
    {
        return $this->getData(self::LAST_NAME);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getCity(): string
    {
        return $this->getData(self::CITY);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getPostalCode(): string
    {
        return $this->getData(self::POSTAL_CODE);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getStreet(): string
    {
        return $this->getData(self::STREET);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getStreetNumber(): string
    {
        return (string) $this->getData(self::STREET_NUMBER);
    }
}
