<?php

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Unit\Model;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AddressUpdater;

class AddressUpdaterTest extends TestCase
{
    private AddressUpdater $subject;

    #[\Override]
    protected function setUp(): void
    {
        $orderAddressRepository = $this->createMock(OrderAddressRepositoryInterface::class);
        $this->subject = new AddressUpdater($orderAddressRepository);
    }

    #[Test]
    public function identicalAddressesAreNotConsideredDifferent(): void
    {
        $analysisResult = $this->createMock(AnalysisResultInterface::class);
        $analysisResult->method('getFirstName')->willReturn('Max');
        $analysisResult->method('getLastName')->willReturn('Mustermann');
        $analysisResult->method('getStreet')->willReturn('Hauptstr');
        $analysisResult->method('getStreetNumber')->willReturn('5');
        $analysisResult->method('getCity')->willReturn('Berlin');
        $analysisResult->method('getPostalCode')->willReturn('10115');

        $orderAddress = $this->createMock(OrderAddressInterface::class);
        $orderAddress->method('getFirstname')->willReturn('Max');
        $orderAddress->method('getLastname')->willReturn('Mustermann');
        $orderAddress->method('getStreet')->willReturn(['Hauptstr 5']);
        $orderAddress->method('getCity')->willReturn('Berlin');
        $orderAddress->method('getPostcode')->willReturn('10115');

        self::assertFalse(
            $this->subject->addressesAreDifferent($analysisResult, $orderAddress),
            'Identical addresses should not be considered different'
        );
    }

    #[Test]
    public function multiLineStreetIsComparedCorrectly(): void
    {
        $analysisResult = $this->createMock(AnalysisResultInterface::class);
        $analysisResult->method('getFirstName')->willReturn('Max');
        $analysisResult->method('getLastName')->willReturn('Mustermann');
        $analysisResult->method('getStreet')->willReturn('Hauptstr');
        $analysisResult->method('getStreetNumber')->willReturn('5');
        $analysisResult->method('getCity')->willReturn('Berlin');
        $analysisResult->method('getPostalCode')->willReturn('10115');

        $orderAddress = $this->createMock(OrderAddressInterface::class);
        $orderAddress->method('getFirstname')->willReturn('Max');
        $orderAddress->method('getLastname')->willReturn('Mustermann');
        $orderAddress->method('getStreet')->willReturn(['Hauptstr', '5']);
        $orderAddress->method('getCity')->willReturn('Berlin');
        $orderAddress->method('getPostcode')->willReturn('10115');

        self::assertFalse(
            $this->subject->addressesAreDifferent($analysisResult, $orderAddress),
            'Multi-line street matching analysis result should not be considered different'
        );
    }

    #[Test]
    public function differentAddressesAreDetected(): void
    {
        $analysisResult = $this->createMock(AnalysisResultInterface::class);
        $analysisResult->method('getFirstName')->willReturn('Max');
        $analysisResult->method('getLastName')->willReturn('Mustermann');
        $analysisResult->method('getStreet')->willReturn('Hauptstr');
        $analysisResult->method('getStreetNumber')->willReturn('5');
        $analysisResult->method('getCity')->willReturn('München');
        $analysisResult->method('getPostalCode')->willReturn('80331');

        $orderAddress = $this->createMock(OrderAddressInterface::class);
        $orderAddress->method('getFirstname')->willReturn('Max');
        $orderAddress->method('getLastname')->willReturn('Mustermann');
        $orderAddress->method('getStreet')->willReturn(['Hauptstr 5']);
        $orderAddress->method('getCity')->willReturn('Berlin');
        $orderAddress->method('getPostcode')->willReturn('10115');

        self::assertTrue(
            $this->subject->addressesAreDifferent($analysisResult, $orderAddress),
            'Different addresses should be detected'
        );
    }
}
