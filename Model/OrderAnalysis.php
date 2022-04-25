<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;

class OrderAnalysis
{
    /**
     * @var AddressAnalysis
     */
    private $addressAnalysisService;

    /**
     * @var DeliverabilityCodes
     */
    private $deliverabilityScoreService;

    /**
     * @var AnalysisStatusUpdater
     */
    private $deliverabilityStatus;

    /**
     * @var AddressUpdater
     */
    private $addressUpdater;

    /**
     * @var OrderManagementInterface
     */
    private $orderService;

    public function __construct(
        AddressAnalysis $addressAnalysisService,
        DeliverabilityCodes $deliverabilityScoreService,
        AnalysisStatusUpdater $deliverabilityStatus,
        OrderManagementInterface $orderService,
        AddressUpdater $addressUpdater
    ) {
        $this->addressAnalysisService = $addressAnalysisService;
        $this->deliverabilityScoreService = $deliverabilityScoreService;
        $this->deliverabilityStatus = $deliverabilityStatus;
        $this->orderService = $orderService;
        $this->addressUpdater = $addressUpdater;
    }

    /**
     * @param Order[] $orders
     * @return Order[]  List of Orders that were put on hold
     * @throws LocalizedException
     *
     * @deprecated Use \PostDirekt\Addressfactory\Model\OrderUpdater::holdIfNonDeliverable instead
     */
    public function holdNonDeliverable(array $orders): array
    {
        $heldOrders = [];

        $analysisResults = $this->analyse($orders);
        foreach ($orders as $order) {
            if (!$order->canHold()) {
                continue;
            }
            $analysisResult = $analysisResults[(int) $order->getId()] ?? null;
            if (!$analysisResult) {
                continue;
            }
            $score = $this->deliverabilityScoreService->computeScore($analysisResult->getStatusCodes());
            if ($score !== DeliverabilityCodes::DELIVERABLE) {
                $this->orderService->hold((int) $order->getId());
                $heldOrders[] = $order;
            }
        }

        return $heldOrders;
    }

    /**
     * @param Order[] $orders
     * @return Order[]  List of Orders that were cancelled
     * @throws LocalizedException
     *
     * @deprecated Use \PostDirekt\Addressfactory\Model\OrderUpdater::cancelIfUndeliverable instead
     */
    public function cancelUndeliverable(array $orders): array
    {
        $cancelledOrders = [];

        $analysisResults = $this->analyse($orders);
        foreach ($orders as $order) {
            if (!$order->canCancel()) {
                continue;
            }
            $analysisResult = $analysisResults[(int) $order->getId()] ?? null;
            if (!$analysisResult) {
                continue;
            }
            $score = $this->deliverabilityScoreService->computeScore($analysisResult->getStatusCodes());
            if ($score === DeliverabilityCodes::UNDELIVERABLE) {
                $this->orderService->cancel((int) $order->getId());
                $cancelledOrders[] = $order;
            }
        }

        return $cancelledOrders;
    }

    /**
     * Get ADDRESSFACTORY DIRECT Deliverability analysis objects
     * for the Shipping Address of every given Order.
     *
     * @param Order[] $orders
     * @return AnalysisResultInterface[] Dictionary: [(int) $order->getEntityId() => AnalysisResult]
     */
    public function analyse(array $orders): array
    {
        $addresses = [];
        foreach ($orders as $order) {
            $addresses[] = $order->getShippingAddress();
        }

        try {
            $analysisResults = $this->addressAnalysisService->analyse($addresses);
        } catch (LocalizedException $exception) {
            $analysisResults = [];
        }
        $result = [];
        foreach ($orders as $order) {
            $analysisResult = $analysisResults[(int) $order->getShippingAddress()->getEntityId()] ?? null;
            $this->updateDeliverabilityStatus((int) $order->getId(), $analysisResult);
            $result[$order->getEntityId()] = $analysisResult;
        }

        return $result;
    }

    /**
     * @param Order $order
     * @param AnalysisResultInterface $analysisResult
     * @return bool
     */
    public function updateShippingAddress(Order $order, AnalysisResultInterface $analysisResult): bool
    {
        $wasUpdated = $this->addressUpdater->update($analysisResult, $order->getShippingAddress());
        if ($wasUpdated) {
            $this->deliverabilityStatus->setStatusAddressCorrected((int) $order->getEntityId());
        }

        return $wasUpdated;
    }

    private function updateDeliverabilityStatus(int $orderId, ?AnalysisResultInterface $analysisResult): void
    {
        if (!$analysisResult) {
            $this->deliverabilityStatus->setStatusAnalysisFailed($orderId);
            return;
        }

        $currentStatus = $this->deliverabilityStatus->getStatus($orderId);
        $statusCode = $this->deliverabilityScoreService->computeScore(
            $analysisResult->getStatusCodes(),
            $currentStatus === AnalysisStatusUpdater::ADDRESS_CORRECTED
        );
        switch ($statusCode) {
            case DeliverabilityCodes::DELIVERABLE:
                $this->deliverabilityStatus->setStatusDeliverable($orderId);
                break;
            case DeliverabilityCodes::POSSIBLY_DELIVERABLE:
                $this->deliverabilityStatus->setStatusPossiblyDeliverable($orderId);
                break;
            case DeliverabilityCodes::UNDELIVERABLE:
                $this->deliverabilityStatus->setStatusUndeliverable($orderId);
                break;
            case DeliverabilityCodes::CORRECTION_REQUIRED:
                $this->deliverabilityStatus->setStatusCorrectionRequired($orderId);
        }
    }
}
