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
    public function __construct(
        private OrderExtensionFactory $orderExtensionFactory,
        private AnalysisStatusRepository $analysisStatusRepo,
    ) {
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
        } catch (\Exception) {
            $extensionAttributes->setPostdirektAddressfactoryAnalysisStatus(null);
        }

        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }
}
