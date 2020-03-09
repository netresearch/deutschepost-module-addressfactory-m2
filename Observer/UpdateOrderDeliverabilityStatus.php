<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;

/**
 * Class UpdateOrderDeliverabilityStatus
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 *
 * @event admin_sales_order_address_update
 */
class UpdateOrderDeliverabilityStatus implements ObserverInterface
{
    /**
     * Only manually modifying addresses with one of the following deliverability statuses
     * will cause a change to the deliverability status of the order.
     *
     * For example, Orders with deliverability status "pending" or "not analysed" may be analysed later.
     * Updating the status to "address corrected" beforehand would cancel that analysis.
     */
    private const ADDRESS_CORRECTIBLE_STATUSES = [
        DeliverabilityStatus::ANALYSIS_FAILED,
        DeliverabilityStatus::UNDELIVERABLE,
        DeliverabilityStatus::POSSIBLY_DELIVERABLE,
        DeliverabilityStatus::DELIVERABLE
    ];

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
        $orderId = (int) $observer->getData('order_id');
        $previousStatus = $this->deliverableStatus->getStatus($orderId);
        if (\in_array($previousStatus, self::ADDRESS_CORRECTIBLE_STATUSES, true)) {
            $this->deliverableStatus->setStatusAddressCorrected($orderId);
        }
    }
}