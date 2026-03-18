<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\ViewModel\Adminhtml;

use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AddressUpdater;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\DeliverabilityCodes;

class AnalysisData implements ArgumentInterface
{
    /**
     * @var AnalysisResultInterface|null
     */
    private $analysisResult;

    public function __construct(
        private Request $request,
        private DeliverabilityCodes $deliverablility,
        private AnalysisStatusUpdater $analysisStatus,
        private Url $urlBuilder,
        private AssetRepository $assetRepository,
        private OrderRepository $orderRepository,
        private AnalysisResultRepository $analysisResultRepository,
        private AddressUpdater $addressUpdater,
        private Escaper $escaper,
    ) {
    }

    public function getLogoUrl(): string
    {
        return $this->assetRepository->getUrl('PostDirekt_Addressfactory::images/logo_addressfactory.png');
    }

    public function getPerformAnalysisUrl(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl('postdirekt/analysis/analyse', ['order_id' => $orderId]);
    }

    public function getHumanReadableScore(): string
    {
        /** @var Phrase[] $scores */
        $scores = [
            DeliverabilityCodes::POSSIBLY_DELIVERABLE => __('Shipping Address Possibly Deliverable'),
            DeliverabilityCodes::DELIVERABLE => __('Shipping Address Deliverable'),
            DeliverabilityCodes::UNDELIVERABLE => __('Shipping Address Undeliverable'),
            DeliverabilityCodes::CORRECTION_REQUIRED => __('Correction Recommended'),
        ];

        return $scores[$this->getScore()]->render() ?? '';
    }

    /**
     * @return string
     */
    public function getScore(): string
    {
        $analysisResult = $this->getAnalysisResult();
        if (!$analysisResult) {
            return DeliverabilityCodes::POSSIBLY_DELIVERABLE;
        }

        $status = $this->analysisStatus->getStatus((int) $this->getOrder()->getEntityId());
        $wasAlreadyUpdated = $status === AnalysisStatusUpdater::ADDRESS_CORRECTED;

        return $this->deliverablility->computeScore($analysisResult->getStatusCodes(), $wasAlreadyUpdated);
    }

    public function getFormattedAddress(): ?string
    {
        $orderAddress = $this->getOrderAddress();
        $analysisResult = $this->getAnalysisResult();

        if (!$orderAddress || !$analysisResult) {
            return null;
        }

        /** @var string $firstName */
        $firstName = $this->escaper->escapeHtml($analysisResult->getFirstName());
        if ($orderAddress->getFirstname() !== $analysisResult->getFirstName()) {
            $firstName = "<b>$firstName</b>";
        }

        /** @var string $lastName */
        $lastName = $this->escaper->escapeHtml($analysisResult->getLastName());
        if ($orderAddress->getLastname() !== $analysisResult->getLastName()) {
            $lastName = "<b>$lastName</b>";
        }

        $street = trim(implode(' ', [$analysisResult->getStreet(), $analysisResult->getStreetNumber()]));
        $orderStreet = trim(implode(' ', $orderAddress->getStreet()));
        /** @var string $escapedStreet */
        $escapedStreet = $this->escaper->escapeHtml($street);
        if ($street !== $orderStreet) {
            $escapedStreet = "<b>$escapedStreet</b>";
        }

        /** @var string $city */
        $city = $this->escaper->escapeHtml($analysisResult->getCity());
        if ($analysisResult->getCity() !== $orderAddress->getCity()) {
            $city = "<b>$city</b>";
        }

        /** @var string $postalCode */
        $postalCode = $this->escaper->escapeHtml($analysisResult->getPostalCode());
        if ($analysisResult->getPostalCode() !== $orderAddress->getPostcode()) {
            $postalCode = "<b>$postalCode</b>";
        }

        return "<dd><span>$firstName $lastName</span></dd>
                <dd><span>$escapedStreet</span></dd>
                <dd><span>$city $postalCode</span></dd>";
    }

    public function getDetectedIssues(): array
    {
        $analysisResult = $this->getAnalysisResult();
        if (!$analysisResult) {
            return [];
        }
        return $this->deliverablility->getLabels($analysisResult->getStatusCodes());
    }

    public function showAnalysisResults(): bool
    {
        return $this->getAnalysisResult() !== null;
    }

    public function showCancelButton(): bool
    {
        $order = $this->getOrder();
        $status = $this->analysisStatus->getStatus((int) $order->getEntityId());

        return $status !== AnalysisStatusUpdater::DELIVERABLE && $order->canCancel();
    }

    public function showUnholdButton(): bool
    {
        return $this->getOrder()->canUnhold();
    }

    public function showSuggestedAddress(): bool
    {
        $analysisResult = $this->getAnalysisResult();
        $orderAddress = $this->getOrderAddress();
        if (!$analysisResult || !$orderAddress) {
            return false;
        }

        return $this->addressUpdater->addressesAreDifferent($analysisResult, $orderAddress);
    }

    public function allowAddressCorrect(): bool
    {
        $orderId = (int) $this->request->getParam('order_id');

        return $this->analysisStatus->getStatus($orderId) !== AnalysisStatusUpdater::ADDRESS_CORRECTED;
    }

    public function getManualEditUrl(): string
    {
        $address = $this->getOrderAddress();
        if (!$address) {
            return '';
        }

        return $this->urlBuilder->getUrl('sales/order/address', ['address_id' => $address->getEntityId()]);
    }

    public function getCancelOrderUrl(): string
    {
        $orderId = (int)$this->request->getParam('order_id');
        return $this->urlBuilder->getUrl('sales/*/cancel', ['order_id' => $orderId]);
    }

    public function getUnholdOrderUrl(): string
    {
        $orderId = (int)$this->request->getParam('order_id');
        return $this->urlBuilder->getUrl('sales/*/unhold', ['order_id' => $orderId]);
    }

    public function getPerformAddressAutocorrectUrl(): string
    {
        $orderId = (int) $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl('postdirekt/order_address/autocorrect', ['order_id' => $orderId]);
    }

    private function getOrderAddress(): ?OrderAddressInterface
    {
        $order = $this->getOrder();

        return $order->getShippingAddress();
    }

    private function getOrder(): Order
    {
        $orderId = (int) $this->request->getParam('order_id');
        try {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
        } catch (InputException | NoSuchEntityException) {
            throw new \RuntimeException('Could not load order. Was the order id added correctly?');
        }

        return $order;
    }

    private function getAnalysisResult(): ?AnalysisResultInterface
    {
        if ($this->analysisResult) {
            return $this->analysisResult;
        }

        $orderAddress = $this->getOrderAddress();
        if (!$orderAddress) {
            return null;
        }

        try {
            $this->analysisResult = $this->analysisResultRepository->getByAddressId((int) $orderAddress->getEntityId());
        } catch (NoSuchEntityException) {
            return null;
        }

        return $this->analysisResult;
    }
}
