<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Model\OrderUpdater;
use Psr\Log\LoggerInterface;

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
        /** @var Order $order */
        $order = $observer->getData('order') ?? $observer->getData('address')->getOrder();

        if ($order->getShippingAddress() === null) {
            // Order is virtual or broken
            return;
        }

        if ($order->getShippingAddress()->getCountryId() !== 'DE') {
            // Only process german shipping addresses
            return;
        }

        $storeId = (string) $order->getStoreId();
        if ($this->config->isManualAnalysisOnly($storeId)) {
            // Manual analysis is not handled
            return;
        }

        $orderId = (int)$order->getEntityId();
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
            if ($this->config->isHoldNonDeliverableOrders($storeId)) {
                $this->orderUpdater->holdIfNonDeliverable($order, $analysisResult);
            }
            if ($this->config->isAutoCancelNonDeliverableOrders($storeId)) {
                $this->orderUpdater->cancelIfUndeliverable($order, $analysisResult);
            }
            if ($this->config->isAutoUpdateShippingAddress($storeId)) {
                $this->analyseService->updateShippingAddress($order, $analysisResult);
            }
        }
    }
}
