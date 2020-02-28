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
use Magento\Sales\Model\OrderRepository;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\DeliverabilityCodes;

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
     * @var Url
     */
    private $urlBuilder;

    public function __construct(
        AnalysisResultRepository $analysisResultRepository,
        AssetRepository $assetRepository,
        Request $request,
        OrderRepository $orderRepository,
        DeliverabilityCodes $deliverablility,
        Url $urlBuilder
    ) {
        $this->analysisResultRepository = $analysisResultRepository;
        $this->assetRepository = $assetRepository;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->deliverablility = $deliverablility;
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

    public function getHumanReadableScore(array $codes): Phrase
    {
        $scores = [
            DeliverabilityCodes::POSSIBLY_DELIVERABLE => __('Possibly Deliverable'),
            DeliverabilityCodes::DELIVERABLE => __('Deliverable'),
            DeliverabilityCodes::UNDELIVERABLE => __('Undeliverable')
        ];

        return $scores[$this->getScore($codes)] ?? _();
    }

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

    public function getDetectedIssues(array $codes): array
    {
        return $this->deliverablility->getLabels($codes);
    }

    private function getOrderAddress(): ?OrderAddressInterface
    {
        $orderId = (int)$this->request->getParam('order_id');
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (InputException | NoSuchEntityException $e) {
            return null;
        }

        return $order->getShippingAddress();
    }
}
