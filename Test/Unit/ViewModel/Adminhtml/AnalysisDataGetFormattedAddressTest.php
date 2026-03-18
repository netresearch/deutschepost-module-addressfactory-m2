<?php

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Unit\ViewModel\Adminhtml;

use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AddressUpdater;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\DeliverabilityCodes;
use PostDirekt\Addressfactory\ViewModel\Adminhtml\AnalysisData;

class AnalysisDataGetFormattedAddressTest extends TestCase
{
    #[Test]
    public function htmlSpecialCharsInAddressDataAreEscaped(): void
    {
        $maliciousName = '<script>alert("xss")</script>';

        $analysisResult = $this->createMock(AnalysisResultInterface::class);
        $analysisResult->method('getFirstName')->willReturn($maliciousName);
        $analysisResult->method('getLastName')->willReturn('Normal');
        $analysisResult->method('getStreet')->willReturn('Hauptstr');
        $analysisResult->method('getStreetNumber')->willReturn('5');
        $analysisResult->method('getCity')->willReturn('Berlin');
        $analysisResult->method('getPostalCode')->willReturn('10115');
        $analysisResult->method('getStatusCodes')->willReturn([]);

        $orderAddress = $this->createMock(OrderAddressInterface::class);
        $orderAddress->method('getFirstname')->willReturn('Different');
        $orderAddress->method('getLastname')->willReturn('Normal');
        $orderAddress->method('getStreet')->willReturn(['Hauptstr 5']);
        $orderAddress->method('getCity')->willReturn('Berlin');
        $orderAddress->method('getPostcode')->willReturn('10115');
        $orderAddress->method('getEntityId')->willReturn(1);

        $order = $this->createMock(Order::class);
        $order->method('getShippingAddress')->willReturn($orderAddress);
        $order->method('getEntityId')->willReturn(1);

        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturn('1');

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('get')->willReturn($order);

        $analysisResultRepository = $this->createMock(AnalysisResultRepository::class);
        $analysisResultRepository->method('getByAddressId')->willReturn($analysisResult);

        $viewModel = new AnalysisData(
            $request,
            new DeliverabilityCodes(),
            $this->createMock(AnalysisStatusUpdater::class),
            $this->createMock(Url::class),
            $this->createMock(AssetRepository::class),
            $orderRepository,
            $analysisResultRepository,
            new AddressUpdater($this->createMock(OrderAddressRepositoryInterface::class)),
            new Escaper(),
        );

        $html = $viewModel->getFormattedAddress();

        self::assertNotNull($html);
        self::assertStringNotContainsString('<script>', $html, 'Script tags must be escaped in output');
        self::assertStringContainsString('&lt;script&gt;', $html, 'HTML special chars must be escaped');
    }
}
