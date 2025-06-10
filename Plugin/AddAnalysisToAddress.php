<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Plugin;

use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderAddressExtensionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\ShippingAssignment;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;

class AddAnalysisToAddress
{
    /**
     * @var AnalysisResultRepository
     */
    private $analysisRepository;

    /**
     * @var OrderExtensionFactory
     */
    private $orderExtensionFactory;

    /**
     * @var OrderAddressExtensionFactory
     */
    private $orderAddressExtensionFactory;

    /**
     * @var JoinProcessorInterface
     */
    protected $extensionAttributesJoinProcessor;

    public function __construct(
        AnalysisResultRepository $analysisRepository,
        OrderExtensionFactory $orderExtensionFactory,
        OrderAddressExtensionFactory $orderAddressExtensionFactory,
        JoinProcessorInterface $extensionAttributesJoinProcessor
    ) {
        $this->analysisRepository = $analysisRepository;
        $this->orderExtensionFactory = $orderExtensionFactory;
        $this->orderAddressExtensionFactory = $orderAddressExtensionFactory;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
    }

    public function afterGet(
        OrderRepositoryInterface $repository,
        OrderInterface $order
    ): OrderInterface {
        $orderExtensionAttributes = $order->getExtensionAttributes();
        if ($orderExtensionAttributes === null) {
            $orderExtensionAttributes = $this->orderExtensionFactory->create();
        }

        if (!$orderExtensionAttributes->getShippingAssignments()) {
            return $order;
        }
        /** @var ShippingAssignment $shippingAssignments */
        $shippingAssignments = current($orderExtensionAttributes->getShippingAssignments());
        $address = $shippingAssignments->getShipping()->getAddress();

        if (!$address) {
            return $order;
        }

        $addressExtensionAttributes = $address->getExtensionAttributes();
        if (!$addressExtensionAttributes) {
            $addressExtensionAttributes = $this->orderAddressExtensionFactory->create();
        }

        try {
            $analysisResults = $this->analysisRepository->getByAddressId((int) $address->getEntityId());
            $addressExtensionAttributes->setPostdirektAddressfactoryAnalysisResult($analysisResults);
        } catch (\Exception) {
            return $order;
        }

        $address->setExtensionAttributes($addressExtensionAttributes);
        $shippingAssignments->getShipping()->setAddress($address);
        $orderExtensionAttributes->setShippingAssignments([$shippingAssignments]);

        return $order;
    }
}
