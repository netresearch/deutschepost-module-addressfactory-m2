<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class AnalysisStatus
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class AnalysisStatus extends AbstractModel
{
    public const ORDER_ID = 'order_id';
    public const STATUS = 'status';

    protected function _construct()
    {
        $this->_init(ResourceModel\AnalysisStatus::class);
        parent::_construct();
    }

    public function getOrderId():int
    {
        return (int) $this->getData(self::ORDER_ID);
    }

    public function setOrderId(int $orderId): void
    {
        $this->setData(self::ORDER_ID, $orderId);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): void
    {
        $this->setData(self::STATUS, $status);
    }
}
