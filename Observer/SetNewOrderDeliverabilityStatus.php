<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Model\OrderUpdater;
use Psr\Log\LoggerInterface;

/**
 * Class SetNewOrderDeliverabilityStatus
 *
 * @event sales_order_save_after
 */
class SetNewOrderDeliverabilityStatus implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var OrderAnalysis
     */
    private $analyseService;

    /**
     * @var AnalysisStatusUpdater
     */
    private $deliverabilityStatus;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        OrderAnalysis $analyseService,
        AnalysisStatusUpdater $deliverabilityStatus,
        LoggerInterface $logger,
        OrderUpdater $orderUpdater
    ) {
        $this->config = $config;
        $this->analyseService = $analyseService;
        $this->deliverabilityStatus = $deliverabilityStatus;
        $this->logger = $logger;
        $this->orderUpdater = $orderUpdater;
    }

    public function execute(Observer $observer): void
    {
        /** @var Address $address */
        $address = $observer->getData('address');

        // only handle new addresses
        if ((!$address || !$address->isObjectNew()) ) {
            return;
        }

        // only handle shipping address for german shipping addresses
        if ($address->getAddressType() !== Address::TYPE_SHIPPING || $address->getCountryId() !== 'DE') {
            return;
        }

        $order = $address->getOrder();
        $orderId = (int) $order->getEntityId();
        $storeId = (string) $order->getStoreId();
        if ($this->config->isManualAnalysisOnly($storeId)) {
            $this->deliverabilityStatus->setStatusNotAnalyzed($orderId);
            return;
        }

        $status = $this->deliverabilityStatus->getStatus($orderId);
        if ($status !== AnalysisStatusUpdater::NOT_ANALYSED) {
            // The order already has been analysed
            return;
        }

        if ($this->config->isAnalysisViaCron($storeId)) {
            // Pending status means the cron will pick up the order
            $this->deliverabilityStatus->setStatusPending($orderId);
        }

        if ($this->config->isAnalysisOnOrderPlace($storeId)) {
            $analysisResults = $this->analyseService->analyse([$order]);
            $analysisResult = $analysisResults[$orderId];
            if (!$analysisResult) {
                $this->logger->error(
                    sprintf('ADDRESSFACTORY DIRECT: Order %s could not be analysed', $order->getIncrementId())
                );
                return;
            }

            $isCanceled = false;
            if ($this->config->isAutoCancelNonDeliverableOrders($storeId)) {
                $isCanceled = $this->orderUpdater->cancelIfUndeliverable($order, $analysisResult);
            }

            if ($this->config->isAutoUpdateShippingAddress($storeId)) {
                $this->analyseService->updateShippingAddress($order, $analysisResult);
            }

            if (!$isCanceled && $this->config->isHoldNonDeliverableOrders($storeId)) {
                $this->orderUpdater->holdIfNonDeliverable($order, $analysisResult);
            }
        }
    }
}
