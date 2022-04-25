<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderBuilder;
use PostDirekt\Addressfactory\Test\Integration\TestDouble\AddressVerificationServiceStub;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Address;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Person;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Record;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

class AddressAnalysisTest extends TestCase
{
    /**
     * @var Order
     */
    private static $deliverableOrder;

    /**
     * @var Order
     */
    private static $undeliverableOrder;

    /**
     * @var Order
     */
    private static $orderWithFailedAnalysis;

    /**
     * @throws \Exception
     */
    public static function createOrders(): void
    {
        self::$deliverableOrder = OrderBuilder::anOrder()
            ->withAnalysisStatus(AnalysisStatusUpdater::DELIVERABLE)
            ->withShippingMethod('flatrate_flatrate')
            ->build();

        self::$undeliverableOrder = OrderBuilder::anOrder()
            ->withAnalysisStatus(AnalysisStatusUpdater::UNDELIVERABLE)
            ->withShippingMethod('flatrate_flatrate')
            ->build();

        self::$orderWithFailedAnalysis = OrderBuilder::anOrder()
            ->withAnalysisStatus(AnalysisStatusUpdater::ANALYSIS_FAILED)
            ->withShippingMethod('flatrate_flatrate')
            ->build();
    }

    /**
     * Test covers case where all provided addresses are already analyzed and come from db.
     *
     * assert that returned analysisResults match fixtures.
     * assert that webservice is not invoked.
     *
     * @test
     * @magentoDataFixture createOrders
     * @throws LocalizedException
     */
    public function analysisResultFromDb(): void
    {
        $orders = [self::$deliverableOrder, self::$undeliverableOrder];

        /** @var OrderAddressInterface[] $addresses */
        $addresses = array_map(
            function (Order $order) {
                return $order->getShippingAddress();
            },
            $orders
        );

        /** @var ServiceFactory|MockObject $mockServiceFactory */
        $mockServiceFactory = $this->getMockBuilder(ServiceFactory::class)->disableOriginalConstructor()->getMock();
        $mockServiceFactory->expects(static::never())->method('createAddressVerificationService');

        /** @var AddressAnalysis $addressAnalysis */
        $addressAnalysis = Bootstrap::getObjectManager()->create(
            AddressAnalysis::class,
            ['serviceFactory' => $mockServiceFactory]
        );

        $result = $addressAnalysis->analyse($addresses);

        self::assertCount(count($addresses), $result, 'Number of analysis results does not match requested addresses');

        /** @var AnalysisResultRepository $resultRepository */
        $resultRepository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        foreach ($addresses as $address) {
            $analysisResult = $resultRepository->getByAddressId((int) $address->getEntityId());
            self::assertEquals($analysisResult->getCity(), $result[$address->getEntityId()]->getCity());
        }
    }

    /**
     * Test covers case where some addresses are already analyzed and some need be analyzed from webservice.
     *
     * - Assert that returned analysis result includes fixture from db.
     * - Assert that returned analysis result includes record response from web service.
     *
     * @test
     * @magentoDataFixture createOrders
     *
     * @throws LocalizedException
     */
    public function analysisResultFromDbAndWs(): void
    {
        $orders = [self::$deliverableOrder, self::$orderWithFailedAnalysis];

        $wsRecord = new Record(
            (int) self::$orderWithFailedAnalysis->getData('shipping_address_id'),
            new Person(
                'Herr',
                'Hans',
                'Muller',
                ['testCompany'],
                'testPrefix',
                'testSuffix',
                'testAcademicTitle',
                'testTitleOfNobility',
                'M',
                'testPostNumber'
            ),
            new Address(
                'Deutschland',
                '53114',
                'NRW',
                'testRegion',
                'testDistrict',
                'testMunicipality',
                'Bonn',
                'testCityAddition',
                'testUrbanDistrict',
                'Sträßchenweg',
                '10',
                'A',
                'testAddressAddition',
                'testDeliveryInstruction'
            )
        );

        /** @var OrderAddressInterface[] $addresses */
        $addresses = array_map(
            function (Order $order) {
                return $order->getShippingAddress();
            },
            $orders
        );

        $addressVerificationServiceStub = new AddressVerificationServiceStub();
        $addressVerificationServiceStub->records = [$wsRecord];

        /** @var ServiceFactory|MockObject $mockServiceFactory */
        $mockServiceFactory = $this->getMockBuilder(ServiceFactory::class)
                                   ->disableOriginalConstructor()
                                   ->getMock();
        $mockServiceFactory
            ->expects(static::once())
            ->method('createAddressVerificationService')
            ->willReturn($addressVerificationServiceStub);

        /** @var AddressAnalysis $addressAnalysis */
        $addressAnalysis = Bootstrap::getObjectManager()->create(
            AddressAnalysis::class,
            ['serviceFactory' => $mockServiceFactory]
        );

        $result = $addressAnalysis->analyse($addresses);

        $requestedRecordsCount = $addressVerificationServiceStub->getRequestedRecordsCount();
        self::assertSame(1, $requestedRecordsCount, 'Number of results retrieved from web service does not match.');
        self::assertCount(count($addresses), $result, 'Total number of results does not match requested addresses');

        foreach ($addresses as $address) {
            self::assertArrayHasKey($address->getEntityId(), $result);
            $addressResult = $result[$address->getEntityId()];

            if ((int) $address->getEntityId() === $wsRecord->getRecordId()) {
                // compare result with ws data if analyzed just now
                self::assertEquals($addressResult->getOrderAddressId(), $wsRecord->getRecordId());
                self::assertEquals($addressResult->getCity(), $wsRecord->getAddress()->getCity());
            } else {
                // compare result with fixture address if already analyzed before
                self::assertEquals($addressResult->getOrderAddressId(), $address->getEntityId());
            }
        }
    }

    /**
     * Test web service failure.
     *
     * Test covers case where some addresses are already analyzed
     * and some need be analyzed from webservice, but webservice
     * returns an error response.
     *
     * @test
     * @magentoDataFixture createOrders
     */
    public function analyzeRequestFails(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Service exception: %1', 'no records')->render());

        $orders = [self::$deliverableOrder, self::$undeliverableOrder, self::$orderWithFailedAnalysis];

        /** @var OrderAddressInterface[] $addresses */
        $addresses = array_map(
            function (Order $order) {
                return $order->getShippingAddress();
            },
            $orders
        );

        $addressVerificationServiceStub = new AddressVerificationServiceStub();
        $addressVerificationServiceStub->records = [];

        /** @var ServiceFactory|MockObject $mockServiceFactory */
        $mockServiceFactory = $this->getMockBuilder(ServiceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockServiceFactory
            ->expects(static::once())
            ->method('createAddressVerificationService')
            ->willReturn($addressVerificationServiceStub);

        /** @var AddressAnalysis $addressAnalysis */
        $addressAnalysis = Bootstrap::getObjectManager()->create(
            AddressAnalysis::class,
            ['serviceFactory' => $mockServiceFactory]
        );

        $addressAnalysis->analyse($addresses);
    }
}
