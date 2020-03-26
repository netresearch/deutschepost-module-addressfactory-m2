<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;

class OrderUpdater
{
    /**
     * @var DeliverabilityCodes
     */
    private $deliverabilityCodes;

    /**
     * @var OrderManagementInterface
     */
    private $orderService;

    public function __construct(DeliverabilityCodes $deliverabilityCodes, OrderManagementInterface $orderService)
    {
        $this->deliverabilityCodes = $deliverabilityCodes;
        $this->orderService = $orderService;
    }

    /**
     * @param Order $order
     * @param AnalysisResult $analysisResult
     * @return bool If Order was put on hold
     */
    public function holdIfNonDeliverable(Order $order, AnalysisResult $analysisResult): bool
    {
        if (!$order->canHold()) {
            return false;
        }
        $score = $this->deliverabilityCodes->computeScore($analysisResult->getStatusCodes());
        if ($score === DeliverabilityCodes::DELIVERABLE) {
            return false;
        }

        return $this->orderService->hold((int) $order->getId());
    }

    /**
     * @param Order $order
     * @param AnalysisResult $analysisResult
     * @return bool If Order was cancelled
     */
    public function cancelIfUndeliverable(Order $order, AnalysisResult $analysisResult): bool
    {
        if (!$order->canCancel()) {
            return false;
        }
        $score = $this->deliverabilityCodes->computeScore($analysisResult->getStatusCodes());
        if ($score !== DeliverabilityCodes::UNDELIVERABLE) {
            return false;
        }

        return $this->orderService->cancel((int)$order->getId());
    }
}
