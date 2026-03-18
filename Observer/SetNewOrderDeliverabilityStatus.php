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

class SetNewOrderDeliverabilityStatus implements ObserverInterface
{
    public function __construct(
        private Config $config,
        private OrderAnalysis $analyseService,
        private AnalysisStatusUpdater $deliverabilityStatus,
        private LoggerInterface $logger,
        private OrderUpdater $orderUpdater,
    ) {
    }

    #[\Override]
    public function execute(Observer $observer): void
    {
        /** @var Address $address */
        $address = $observer->getData('address');

        if (!$address) {
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
