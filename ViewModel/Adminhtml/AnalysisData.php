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
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;

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
     * @var AnalysisStatusUpdater
     */
    private $deliveryStatus;

    /**
     * @var Url
     */
    private $urlBuilder;

    /**
     * @var AnalysisResult|null
     */
    private $analysisResult;

    public function __construct(
        AnalysisResultRepository $analysisResultRepository,
        AssetRepository $assetRepository,
        Request $request,
        OrderRepository $orderRepository,
        DeliverabilityCodes $deliverablility,
        AnalysisStatusUpdater $deliveryStatus,
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
     * @return Phrase
     */
    public function getHumanReadableScore(): string
    {
        /** @var Phrase[] $scores */
        $scores = [
            DeliverabilityCodes::POSSIBLY_DELIVERABLE => __('Possibly Deliverable'),
            DeliverabilityCodes::DELIVERABLE => __('Deliverable'),
            DeliverabilityCodes::UNDELIVERABLE => __('Undeliverable')
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

        return $this->deliverablility->computeScore($analysisResult->getStatusCodes());
    }

    public function getFormattedAddress(): ?string
    {
        $orderAddress = $this->getOrderAddress();
        $analysisResult = $this->getAnalysisResult();

        if (!$orderAddress || !$analysisResult) {
            return null;
        }

        $firstName = ($orderAddress->getFirstname() !== $analysisResult->getFirstName())
            ? "<b>{$analysisResult->getFirstName()}</b>" : $analysisResult->getFirstName();

        $lastName = ($orderAddress->getLastname() !== $analysisResult->getLastName())
            ? "<b>{$analysisResult->getLastName()}</b>" : $analysisResult->getLastName();

        $street = trim(implode(' ', [$analysisResult->getStreet(), $analysisResult->getStreetNumber()]));
        $orderStreet = trim(implode('', $orderAddress->getStreet()));

        $street = ($street !== $orderStreet) ? "<b>{$street}</b>" : $street;

        $city = ($analysisResult->getCity() !== $orderAddress->getCity())
            ? "<b>{$analysisResult->getCity()}</b>" : $analysisResult->getCity();

        $postalCode = ($analysisResult->getPostalCode() !== $orderAddress->getPostcode())
            ? "<b>{$analysisResult->getPostalCode()}</b>" : $analysisResult->getPostalCode();

        return "<dd><span>{$firstName} {$lastName}</span></dd>
                <dd><span>{$street}</span></dd>
                <dd><span>{$city} {$postalCode}</span></dd>";
    }

    /**
     * @return string[]
     */
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
        $status = $this->deliveryStatus->getStatus((int) $order->getEntityId());
        if (!$order) {
            return false;
        }

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

        return $this->areDifferent($this->getOrderAddress(), $analysisResult);
    }

    public function allowAddressCorrect(): bool
    {
        $orderId = (int) $this->request->getParam('order_id');

        return $this->deliveryStatus->getStatus($orderId) !== AnalysisStatusUpdater::ADDRESS_CORRECTED;
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

    private function getOrder(): OrderInterface
    {
        $orderId = (int) $this->request->getParam('order_id');
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (InputException | NoSuchEntityException $e) {
            throw new \RuntimeException('Could not load order. Was the order id added correctly?');
        }

        return $order;
    }

    private function getAnalysisResult(): ?AnalysisResult
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
        } catch (NoSuchEntityException $exception) {
            return null;
        }

        return $this->analysisResult;
    }

    private function areDifferent(OrderAddressInterface $orderAddress, AnalysisResult $analysisResult): bool
    {
        $street = trim(implode(' ', [$analysisResult->getStreet(), $analysisResult->getStreetNumber()]));
        $orderStreet = trim(implode('', $orderAddress->getStreet()));

        return ($orderAddress->getFirstname() !== $analysisResult->getFirstName() ||
            $orderAddress->getLastname() !== $analysisResult->getLastName() ||
            $orderAddress->getCity() !== $analysisResult->getCity() ||
            $orderAddress->getPostcode() !== $analysisResult->getPostalCode() ||
            $street !== $orderStreet);
    }
}
