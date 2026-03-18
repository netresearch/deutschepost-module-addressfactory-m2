<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order\Address;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use Psr\Log\LoggerInterface;

/**
 * Update analysis status to "manually edited" and discard the analysis result.
 *
 * Observer must only perform action if the address was actually edited
 * manually, not when the address gets auto-updated during address analysis.
 */
class UpdateOrderDeliverabilityStatus implements ObserverInterface
{
    public function __construct(
        private RequestInterface $request,
        private OrderAddressRepositoryInterface $orderAddressRepository,
        private AnalysisStatusUpdater $statusUpdater,
        private AnalysisStatusRepository $statusRepository,
        private AnalysisResultRepository $resultRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function execute(Observer $observer): void
    {
        $addressId = $this->request->getParam('address_id');
        if (!$addressId) {
            return;
        }

        $address = $this->orderAddressRepository->get((int) $addressId);
        if ($address->getAddressType() !== Address::TYPE_SHIPPING || $address->getCountryId() !== 'DE') {
            // not a German shipping address
            return;
        }

        try {
            /** @var \Magento\Sales\Model\Order\Address $address */
            $order = $address->getOrder();
            $previousResult = $this->statusRepository->getByOrderId((int) $order->getId());
            if ($previousResult->getStatus()) {
                $isManuallyEdited = $this->statusUpdater->setStatusManuallyEdited((int) $order->getId());
                if ($isManuallyEdited) {
                    $analysisResult = $this->resultRepository->getByAddressId((int) $address->getEntityId());
                    $this->resultRepository->delete($analysisResult);
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
