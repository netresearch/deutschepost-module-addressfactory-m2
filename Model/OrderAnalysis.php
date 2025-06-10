<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\LocalizedException;
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

    public function __construct(
        AddressAnalysis $addressAnalysisService,
        DeliverabilityCodes $deliverabilityScoreService,
        AnalysisStatusUpdater $deliverabilityStatus,
        AddressUpdater $addressUpdater
    ) {
        $this->addressAnalysisService = $addressAnalysisService;
        $this->deliverabilityScoreService = $deliverabilityScoreService;
        $this->deliverabilityStatus = $deliverabilityStatus;
        $this->addressUpdater = $addressUpdater;
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
        } catch (LocalizedException) {
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
            if ($this->deliverabilityStatus->getStatus((int) $order->getEntityId()) === AnalysisStatusUpdater::UNDELIVERABLE) {
                // if status has been undeliverable an address correction might not fix all issues with it
                $this->deliverabilityStatus->setStatusPossiblyDeliverable((int) $order->getEntityId());
            } else {
                $this->deliverabilityStatus->setStatusAddressCorrected((int) $order->getEntityId());
            }
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
