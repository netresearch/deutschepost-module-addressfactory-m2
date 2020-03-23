<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
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
     * @var DeliverabilityStatus
     */
    private $deliverabilityStatus;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        OrderAnalysis $analyseService,
        DeliverabilityStatus $deliverabilityStatus,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->analyseService = $analyseService;
        $this->deliverabilityStatus = $deliverabilityStatus;
        $this->logger = $logger;
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
        if ($status !== DeliverabilityStatus::NOT_ANALYSED) {
            // The order already has been analysed
            return;
        }

        if ($this->config->isAnalysisViaCron($storeId)) {
            // Pending status means the cron will pick up the order
            $this->deliverabilityStatus->setStatusPending($orderId);
        }

        if ($this->config->isAnalysisOnOrderPlace($storeId)) {
            try {
                $this->analyseService->analyse([$order]);
                if ($this->config->isHoldNonDeliverableOrders($storeId)) {
                    $this->analyseService->holdNonDeliverable([$order]);
                }
                if ($this->config->isAutoCancelNonDeliverableOrders($storeId)) {
                    $this->analyseService->cancelUndeliverable([$order]);
                }
                if ($this->config->isAutoUpdateShippingAddress($storeId)) {
                    $this->analyseService->updateShippingAddress([$order]);
                }
            } catch (LocalizedException|CouldNotSaveException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }
}
