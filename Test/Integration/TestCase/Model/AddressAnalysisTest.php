<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\AnalysisFixture;
use PostDirekt\Addressfactory\Test\Integration\TestDouble\AddressVerificationServiceStub;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Address;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Person;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Record;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

class AddressAnalysisTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $orders = [];

    /**
     * @throws \Exception
     */
    public static function createAnalysisResults(): void
    {
        self::$orders = [
            AnalysisFixture::createDeliverableAnalyzedOrder(),
            AnalysisFixture::createUndeliverableAnalyzedOrder(),
        ];
    }

    /**
     * @throws \Exception
     */
    public static function createPartialAnalysisResult(): void
    {
        self::$orders = AnalysisFixture::createMixedAnalyzedOrders();
    }

    /**
     * Test covers case where all provided addresses are already analyzed and come from db.
     *
     * assert that returned analysisResults match fixtures.
     * assert that webservice is not invoked.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @throws LocalizedException
     */
    public function allAddressesAreAnalyzed(): void
    {
        /** @var Order\Address[] $addresses */
        $addresses = [];
        foreach (self::$orders as $order) {
            $addresses[] = $order->getShippingAddress();
        }

        /** @var ServiceFactory|MockObject $mockServiceFactory */
        $mockServiceFactory = $this->getMockBuilder(ServiceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockServiceFactory
            ->expects(static::never())
            ->method('createAddressVerificationService');

        /** @var AddressAnalysis $addressAnalysis */
        $addressAnalysis = Bootstrap::getObjectManager()->create(
            AddressAnalysis::class,
            [
                'serviceFactory' => $mockServiceFactory
            ]
        );
        $result = $addressAnalysis->analyse($addresses);

        self::assertCount(2, $result);

        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        foreach ($addresses as $address) {
            $analysisResult = $resultRepo->getByAddressId((int) $address->getEntityId());
            self::assertEquals(
                $analysisResult->getCity(),
                $result[$address->getEntityId()]->getCity()
            );
        }
    }

    /**
     * Test covers case where some addresses are already analyzed and some need be analyzed from webservice.
     *
     * assert that returned analysisResult includes fixtures
     * assert that returned analysisResult includes record response from webservice
     *
     * @test
     * @magentoDataFixture createPartialAnalysisResult
     * @throws LocalizedException
     */
    public function someAddressesAreAnalyzed(): void
    {
        $addresses = [];
        foreach (self::$orders as $order) {
            $addresses[] = $order->getShippingAddress();
        }

        $person = new Person(
            'Herr',
            'Hans',
            'Muller',
            ['testCompany'],
            'testPrefix',
            'testSufix',
            'testAcademicTitle',
            'testTitleOfNobility',
            'M',
            'testPostNumber'
        );

        $address = new Address(
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
        );

        $testRecord = new Record((int) $addresses[0]->getEntityId(), $person, $address);
        $addressVerificationServiceStub = new AddressVerificationServiceStub();
        $addressVerificationServiceStub->records = [$testRecord];

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
            [
                'serviceFactory' => $mockServiceFactory
            ]
        );

        $results = $addressAnalysis->analyse($addresses);

        // assert one out of thee records was retrieved from web service
        self::assertSame(1, $addressVerificationServiceStub->getRequestedRecordsCount());

        self::assertArrayHasKey($testRecord->getRecordId(), $results, 'AnalysisResult from the API is missing from results');
        self::assertArrayHasKey($addresses[0]->getEntityId(), $results, 'AnalysisResult from the DB is missing from results');
        self::assertCount(3, $results);

        $resultOne = $results[$testRecord->getRecordId()];
        $resultTwo = $results[$addresses[0]->getEntityId()];

        self::assertEquals($testRecord->getRecordId(), $resultOne->getOrderAddressId());
        self::assertEquals($testRecord->getAddress()->getCity(), $resultOne->getCity());

        self::assertEquals($addresses[0]->getEntityId(), $resultTwo->getOrderAddressId());
        self::assertEquals($addresses[0]->getCity(), $resultTwo->getCity());
    }

    /**
     * Test covers case where some addresses are already analyzed and some need be analyzed from webservice,
     * but webservice returns an error response.
     *
     * assert that only the analysis result from our table is in the result.
     *
     * @test
     * @magentoDataFixture createPartialAnalysisResult
     * @throws LocalizedException
     */
    public function analyzeRequestFails(): void
    {
        $addresses = [];
        foreach (self::$orders as $order) {
            $addresses[] = $order->getShippingAddress();
        }

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
            [
                'serviceFactory' => $mockServiceFactory
            ]
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Service exception: %1', 'no records')->render());

        $addressAnalysis->analyse($addresses);
    }
}
