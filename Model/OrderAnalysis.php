<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;

/**
 * OrderAnalysis
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
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
     * @var DeliverabilityStatus
     */
    private $deliverabilityStatus;

    /**
     * @var OrderManagementInterface
     */
    private $orderService;

    public function __construct(
        AddressAnalysis $addressAnalysisService,
        DeliverabilityCodes $deliverabilityScoreService,
        DeliverabilityStatus $deliverabilityStatus,
        OrderManagementInterface $orderService
    ) {
        $this->addressAnalysisService = $addressAnalysisService;
        $this->deliverabilityScoreService = $deliverabilityScoreService;
        $this->deliverabilityStatus = $deliverabilityStatus;
        $this->orderService = $orderService;
    }

    /**
     * @param Order[] $orders
     * @throws LocalizedException
     */
    public function holdNonDeliverable(array $orders): void
    {
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
            }
        }
    }

    /**
     * @param Order[] $orders
     * @throws LocalizedException
     */
    public function cancelUndeliverable(array $orders): void
    {
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
            }
        }
    }

    /**
     * @param Order[] $orders
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function updateShippingAddress(array $orders): void
    {
        $addresses = [];
        foreach ($orders as $order) {
            $addresses[] = $order->getShippingAddress();
        }
        try {
            $this->addressAnalysisService->update($addresses);
            foreach ($orders as $order) {
                $this->deliverabilityStatus->setStatusAddressCorrected((int) $order->getId());
            }
        } catch (LocalizedException|CouldNotSaveException $exception) {
            foreach ($orders as $order) {
                $this->deliverabilityStatus->setStatusAnalysisFailed((int) $order->getId());
            }
            throw $exception;
        }
    }

    /**
     * Get Addressfactory Analysis results for the shipping address of every given Order
     * and return as an Array with order entity ids for keys.
     *
     * @param Order[] $orders
     * @return AnalysisResult[]
     * @throws LocalizedException
     */
    public function analyse(array $orders): array
    {
        $addresses = [];
        foreach ($orders as $order) {
            $addresses[] = $order->getShippingAddress();
        }

        try {
            $analysisResults = $this->addressAnalysisService->analyze($addresses);

            $result = [];
            foreach ($orders as $order) {
                $analysisResult = $analysisResults[(int) $order->getShippingAddressId()] ?? null;
                $this->setStatus((int) $order->getId(), $analysisResult);
                $result[$order->getEntityId()] = $analysisResult;
            }

            return $result;
        } catch (LocalizedException $exception) {
            foreach ($orders as $order) {
                $this->deliverabilityStatus->setStatusAnalysisFailed((int) $order->getId());
            }
            throw $exception;
        }
    }

    private function setStatus(int $orderId, ?AnalysisResult $analysisResult): void
    {
        if (!$analysisResult) {
            $this->deliverabilityStatus->setStatusAnalysisFailed($orderId);
        }

        $statusCode = $this->deliverabilityScoreService->computeScore($analysisResult->getStatusCodes());
        switch ($statusCode) {
            case DeliverabilityCodes::DELIVERABLE:
                $this->deliverabilityStatus->setStatusDeliverable($orderId);
                break;
            case DeliverabilityCodes::POSSIBLY_DELIVERABLE:
                $this->deliverabilityStatus->setStatusPossiblyDeliverable($orderId);
                break;
            case DeliverabilityCodes::UNDELIVERABLE:
                $this->deliverabilityStatus->setStatusUndeliverable($orderId);
        }
    }
}
