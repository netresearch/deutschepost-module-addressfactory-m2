<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Plugin\Sales;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;

class OrderRepositoryPlugin
{
    /**
     * @var OrderExtensionFactory
     */
    private $orderExtensionFactory;

    /**
     * @var AnalysisStatusRepository
     */
    private $analysisStatusRepo;

    public function __construct(
        OrderExtensionFactory $orderExtensionFactory,
        AnalysisStatusRepository $analysisStatusRepo
    ) {
        $this->orderExtensionFactory = $orderExtensionFactory;
        $this->analysisStatusRepo = $analysisStatusRepo;
    }

    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        }

        try {
            $analysisStatus = $this->analysisStatusRepo->getByOrderId((int) $order->getEntityId());
            $extensionAttributes->setPostdirektAddressfactoryAnalysisStatus($analysisStatus->getStatus());
        } catch (\Exception $e) {
            $extensionAttributes->setPostdirektAddressfactoryAnalysisStatus(null);
        }

        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }
}
