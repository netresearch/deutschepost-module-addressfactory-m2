<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;

/**
 * Class SetNewOrderDeliverabilityStatus
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 *
 * @event sales_order_save_after
 */
class SetNewOrderDeliverabilityStatus implements ObserverInterface
{
    /**
     * @var DeliverabilityStatus
     */
    private $deliverableStatus;

    public function __construct(DeliverabilityStatus $deliverableStatus)
    {
        $this->deliverableStatus = $deliverableStatus;
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getData('order');
        $status = $this->deliverableStatus->getStatus((int) $order->getEntityId());
        if ($status === DeliverabilityStatus::NOT_ANALYSED) {
            $this->deliverableStatus->setStatusPending((int) $order->getEntityId());
        }
    }
}
