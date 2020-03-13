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
     * @return Order[]  List of Orders that were put on hold
     * @throws LocalizedException
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
     * Get ADDRESSFACTORY DIRECT Deliverability analysis objects
     * for the Shipping Address of every given Order.
     *
     * @param Order[] $orders
     * @return AnalysisResult[] Dictionary: [(int) $order->getEntityId() => AnalysisResult]
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
        } catch (LocalizedException $exception) {
            foreach ($orders as $order) {
                $this->deliverabilityStatus->setStatusAnalysisFailed((int)$order->getId());
            }
            throw $exception;
        }
        $result = [];
        foreach ($orders as $order) {
            $analysisResult = $analysisResults[(int) $order->getShippingAddress()->getEntityId()] ?? null;
            $this->updateDeliverabilityStatus((int) $order->getId(), $analysisResult);
            $result[$order->getEntityId()] = $analysisResult;
        }

        return $result;
    }

    private function updateDeliverabilityStatus(int $orderId, ?AnalysisResult $analysisResult): void
    {
        if (!$analysisResult) {
            $this->deliverabilityStatus->setStatusAnalysisFailed($orderId);
            return;
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
