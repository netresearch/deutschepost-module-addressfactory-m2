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
     * @var OrderManagementInterface
     */
    private $orderService;

    public function __construct(
        AddressAnalysis $addressAnalysisService,
        DeliverabilityCodes $deliverabilityScoreService,
        OrderManagementInterface $orderService
    ) {
        $this->addressAnalysisService = $addressAnalysisService;
        $this->deliverabilityScoreService = $deliverabilityScoreService;
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
                $this->orderService->hold($order->getId());
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
                $this->orderService->cancel($order->getId());
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

        $this->addressAnalysisService->update($addresses);
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

        $analysisResults = $this->addressAnalysisService->analyze($addresses);

        $result = [];
        foreach ($orders as $order) {
            $analysisResult = $analysisResults[(int) $order->getShippingAddressId()] ?? null;
            if ($analysisResult) {
                $result[$order->getEntityId()] = $analysisResult;
            }
        }

        return $result;
    }
}
