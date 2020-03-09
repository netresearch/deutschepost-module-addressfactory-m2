<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\ViewModel\Adminhtml;

use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\DeliverabilityCodes;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;

/**
 * Class AnalysisData
 *
 * @author   Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class AnalysisData implements ArgumentInterface
{
    /**
     * @var AnalysisResultRepository
     */
    private $analysisResultRepository;

    /**
     * @var AssetRepository
     */
    private $assetRepository;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var DeliverabilityCodes
     */
    private $deliverablility;

    /**
     * @var DeliverabilityStatus
     */
    private $deliveryStatus;

    /**
     * @var Url
     */
    private $urlBuilder;

    public function __construct(
        AnalysisResultRepository $analysisResultRepository,
        AssetRepository $assetRepository,
        Request $request,
        OrderRepository $orderRepository,
        DeliverabilityCodes $deliverablility,
        DeliverabilityStatus $deliveryStatus,
        Url $urlBuilder
    ) {
        $this->analysisResultRepository = $analysisResultRepository;
        $this->assetRepository = $assetRepository;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->deliverablility = $deliverablility;
        $this->deliveryStatus = $deliveryStatus;
        $this->urlBuilder = $urlBuilder;
    }

    public function getAnalysisResult(int $addressId): ?AnalysisResult
    {
        try {
            return $this->analysisResultRepository->getByAddressId($addressId);
        } catch (NoSuchEntityException $exception) {
            return null;
        }
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

    /**
     * @param string[] $codes
     * @return Phrase
     */
    public function getHumanReadableScore(array $codes): Phrase
    {
        $scores = [
            DeliverabilityCodes::POSSIBLY_DELIVERABLE => __('Possibly Deliverable'),
            DeliverabilityCodes::DELIVERABLE => __('Deliverable'),
            DeliverabilityCodes::UNDELIVERABLE => __('Undeliverable')
        ];

        return $scores[$this->getScore($codes)] ?? _();
    }

    /**
     * @param string[] $codes
     * @return string
     */
    public function getScore(array $codes): string
    {
        return $this->deliverablility->computeScore($codes);
    }

    public function getFormattedAddress(AnalysisResult $analysisResult): string
    {
        $orderAddress = $this->getOrderAddress();
        if (!$orderAddress) {
            return '';
        }

        $firstName = ($orderAddress->getFirstname() !== $analysisResult->getFirstName())
            ? "<b>{$analysisResult->getFirstName()}</b>" : $analysisResult->getFirstName();

        $lastName = ($orderAddress->getLastname() !== $analysisResult->getLastName())
            ? "<b>{$analysisResult->getLastName()}</b>" : $analysisResult->getLastName();

        $street = $analysisResult->getStreet() . ' ' . $analysisResult->getStreetNumber();
        $street = ($street !== implode('', $orderAddress->getStreet())) ? "<b>{$street}</b>" : $street;

        $city = ($analysisResult->getCity() !== $orderAddress->getCity())
            ? "<b>{$analysisResult->getCity()}</b>" : $analysisResult->getCity();

        $postalCode = ($analysisResult->getPostalCode() !== $orderAddress->getPostcode())
            ? "<b>{$analysisResult->getPostalCode()}</b>" : $analysisResult->getPostalCode();

        return "<dd><span>{$firstName} {$lastName}</span></dd>
                <dd><span>{$street}</span></dd>
                <dd><span>{$city} {$postalCode}</span></dd>";
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    public function getDetectedIssues(array $codes): array
    {
        return $this->deliverablility->getLabels($codes);
    }

    public function showCancelButton(): bool
    {
        $orderId = (int) $this->request->getParam('order_id');
        $order = $this->getOrder($orderId);
        if (!$order) {
            return false;
        }
        $isNotDeliverable = $this->deliveryStatus->getStatus($orderId) !== DeliverabilityStatus::DELIVERABLE;

        return $isNotDeliverable && $order->canCancel();
    }

    public function showUnholdButton(): bool
    {
        $orderId = (int) $this->request->getParam('order_id');
        $order = $this->getOrder($orderId);

        return $order ? $order->canUnhold() : false;
    }

    public function showAutoCorrectAddressButton(): bool
    {
        $orderId = (int) $this->request->getParam('order_id');

        return $this->deliveryStatus->getStatus($orderId) !== DeliverabilityStatus::ADDRESS_CORRECTED;
    }

    public function getManualEditUrl(int $addressId): string
    {
        return $this->urlBuilder->getUrl('sales/order/address', ['address_id' => $addressId]);
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

    private function getOrderAddress(): ?OrderAddressInterface
    {
        $orderId = (int) $this->request->getParam('order_id');
        $order = $this->getOrder($orderId);

        return $order ? $order->getShippingAddress() : null;
    }

    private function getOrder(int $orderId): ?OrderInterface
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (InputException | NoSuchEntityException $e) {
            return null;
        }

        return $order;
    }

    public function getPerformAddressAutocorrectUrl(): string
    {
        $orderId = (int) $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl('postdirekt/order_address/autocorrect', ['order_id' => $orderId]);
    }
}
