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
use Magento\Sales\Api\Data\OrderInterface;
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
    private $deliverableStatus;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        OrderAnalysis $analyseService,
        DeliverabilityStatus $deliverableStatus,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->analyseService = $analyseService;
        $this->deliverableStatus = $deliverableStatus;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getData('address')->getOrder();
        $storeId = (string) $order->getStoreId();
        $status = $this->deliverableStatus->getStatus((int)$order->getEntityId());
        if ($this->config->isManualAnalysisOnly($storeId) || $status !== DeliverabilityStatus::NOT_ANALYSED) {
            return;
        }

        if ($this->config->isAnalysisViaCron($storeId)) {
            $this->deliverableStatus->setStatusPending((int) $order->getEntityId());
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
                $this->deliverableStatus->setStatusAnalysisFailed((int) $order->getEntityId());
            }
        }
    }
}
