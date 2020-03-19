<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressUs;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;
use PostDirekt\Addressfactory\Test\Integration\TestDouble\AddressVerificationServiceStub;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Address;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Person;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Record;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

/**
 * AddressAnalysisTest
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class AddressAnalysisTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $orders = [];

    /**
     * @var AnalysisResult[]
     */
    private static $analysisResults = [];

    /**
     * Create order fixture for DE recipient address.
     *
     * @throws \Exception
     */
    private static function createOrders(): void
    {
        $shippingMethod = 'flatrate_flatrate';
        self::$orders = [
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod),
            OrderFixture::createOrder(new AddressUs(), [new SimpleProduct()], $shippingMethod),
        ];
    }

    /**
     * @throws \Exception
     */
    public static function createAnalysisResults(): void
    {
        self::createOrders();

        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $address = $order->getShippingAddress();
            /** @var AnalysisResult $analysisResult */
            $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
            $analysisResult->setOrderAddressId((int)$address->getEntityId());
            $analysisResult->setStreet(implode(' ', $address->getStreet()));
            $analysisResult->setLastName($address->getLastname());
            $analysisResult->setFirstName($address->getFirstname());
            $analysisResult->setCity($address->getCity());
            $analysisResult->setPostalCode($address->getPostcode());
            $analysisResult->setStatusCodes(['test']);
            $analysisResult->setStreetNumber('12');
            /** @var AnalysisResultRepository $analysisResultRepo */
            $analysisResultRepo->save($analysisResult);
            self::$analysisResults[$analysisResult->getOrderAddressId()] = $analysisResult;
        }
    }

    /**
     * @throws \Exception
     */
    public static function createPartialAnalysisResult(): void
    {
        self::createOrders();

        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        $order = self::$orders[0];

        $address = $order->getShippingAddress();
        /** @var AnalysisResult $analysisResult */
        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
        $analysisResult->setOrderAddressId((int)$address->getEntityId());
        $analysisResult->setStreet(implode(' ', $address->getStreet()));
        $analysisResult->setLastName($address->getLastname());
        $analysisResult->setFirstName($address->getFirstname());
        $analysisResult->setCity($address->getCity());
        $analysisResult->setPostalCode($address->getPostcode());
        $analysisResult->setStatusCodes(['test']);
        $analysisResult->setStreetNumber('12');
        /** @var AnalysisResultRepository $analysisResultRepo */
        $analysisResultRepo->save($analysisResult);
        self::$analysisResults[$analysisResult->getOrderAddressId()] = $analysisResult;
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
        $result = $addressAnalysis->analyze($addresses);

        self::assertCount(2, $result);

        foreach ($result as $analysisResult) {
            self::assertEquals(
                self::$analysisResults[$analysisResult->getOrderAddressId()]->getCity(),
                $analysisResult->getCity()
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

        $testRecord = new Record((int) $addresses[1]->getEntityId(), $person, $address);
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

        $results = $addressAnalysis->analyze($addresses);

        // assert one out of two records were retrieved from web service
        self::assertSame(1, $addressVerificationServiceStub->getRequestedRecordsCount());

        self::assertArrayHasKey($testRecord->getRecordId(), $results, 'AnalysisResult from the API is missing from results');
        self::assertArrayHasKey($addresses[0]->getEntityId(), $results, 'AnalysisResult from the DB is missing from results');
        self::assertCount(2, $results);

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
        $result = $addressAnalysis->analyze($addresses);
    }

    /**
     * Test covers case where addresses are analyzed and the order address is updated and persisted.
     *
     * assert that order address is updated with data from analysis result and is saved.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @throws LocalizedException
     */
    public function updateSuccess(): void
    {
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
        $result = $addressAnalysis->update($addresses);
        /** @var OrderAddressRepositoryInterface $orderAddressRepository */
        $orderAddressRepository = Bootstrap::getObjectManager()->create(OrderAddressRepositoryInterface::class);
        foreach ($result as $analysisResult) {
            $savedOrder = $orderAddressRepository->get($analysisResult->getOrderAddressId());
            self::assertEquals($savedOrder->getCity(), $analysisResult->getCity());
            self::assertEquals($savedOrder->getEntityId(), $analysisResult->getOrderAddressId());
            self::assertEquals($savedOrder->getPostcode(), $analysisResult->getPostalCode());
        }
    }

    /**
     * Test covers case where addresses are analyzed and the order address is updated and a error occurs during save.
     *
     * assert that order address repository save throws exception.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     */
    public function updateFailed(): void
    {
        $addresses = array_map(
            static function (Order $order) {
                return $order->getShippingAddress();
            },
            self::$orders
        );

        /** @var ServiceFactory|MockObject $mockServiceFactory */
        $mockServiceFactory = $this->getMockBuilder(ServiceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockServiceFactory
            ->expects(static::never())
            ->method('createAddressVerificationService');

        /** @var OrderAddressRepositoryInterface|MockObject $orderAddressRepositoryMock */
        $orderAddressRepositoryMock = $this->getMockBuilder(OrderAddressRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderAddressRepositoryMock
            ->expects(static::once())
            ->method('save')
            ->willThrowException(new CouldNotSaveException(__("The order address couldn't be saved.")));

        /** @var AddressAnalysis $addressAnalysis */
        $addressAnalysis = Bootstrap::getObjectManager()->create(
            AddressAnalysis::class,
            [
                'serviceFactory' => $mockServiceFactory,
                'orderAddressRepository' => $orderAddressRepositoryMock
            ]
        );

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage("The order address couldn't be saved.");

        $addressAnalysis->update($addresses);
    }
}
