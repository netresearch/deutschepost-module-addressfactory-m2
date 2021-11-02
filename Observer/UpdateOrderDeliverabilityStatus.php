<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use Psr\Log\LoggerInterface;

class UpdateOrderDeliverabilityStatus implements ObserverInterface
{
    /**
     * @var AnalysisStatusUpdater
     */
    private $statusUpdater;

    /**
     * @var AnalysisStatusRepository
     */
    private $statusRepository;

    /**
     * @var AnalysisResultRepository
     */
    private $resultRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        AnalysisStatusUpdater $deliverableStatus,
        AnalysisStatusRepository $statusRepository,
        AnalysisResultRepository $resultRepository,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->statusUpdater = $deliverableStatus;
        $this->statusRepository = $statusRepository;
        $this->resultRepository = $resultRepository;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $orderId = $observer->getData('order_id');

        try {
            /** @var Order $order */
            $order = $this->orderRepository->get((int) $orderId);
            // no delivery for virtual orders
            if ($order->getIsVirtual()) {
                return;
            }
            $previousResult = $this->statusRepository->getByOrderId((int) $order->getId());
            if ($this->statusUpdater->isStatusCorrectable($previousResult->getStatus())) {
                $isManuallyEdited = $this->statusUpdater->setStatusManuallyEdited((int) $order->getId());
                if ($isManuallyEdited) {
                    $analysisResult = $this->resultRepository->getByAddressId(
                        (int) $order->getShippingAddress()->getId()
                    );
                    $this->resultRepository->delete($analysisResult);
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
