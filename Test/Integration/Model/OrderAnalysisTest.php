<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Model;

use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressUs;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

class OrderAnalysisTest extends TestCase
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
    public static function createAnalysisResultsWithUndeliverableStatus(): void
    {
        self::createOrders();

        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $address = $order->getShippingAddress();
            /** @var AnalysisResult $analysisResult */
            $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
            $analysisResult->setOrderAddressId((int)$address->getEntityId());
            $analysisResult->setStreet(implode(' ', $address->getStreet()));
            $analysisResult->setLastName('Lustig');
            $analysisResult->setFirstName('Peter');
            $analysisResult->setCity($address->getCity());
            $analysisResult->setPostalCode($address->getPostcode());
            $analysisResult->setStatusCodes([
                'PDC050106', //DeliverabilityScore::PERSON_NOT_DELIVERABLE
                'PDC040106', //DeliverabilityScore::HOUSEHOLD_UNDELIVERABLE
            ]);
            $analysisResult->setStreetNumber('12');
            /** @var AnalysisResultRepository $analysisResultRepo */
            $analysisResultRepo->save($analysisResult);
        }
    }

    /**
     * @throws \Exception
     */
    public static function createAnalysisResultsWithDeliverableStatus(): void
    {
        self::createOrders();

        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $address = $order->getShippingAddress();
            /** @var AnalysisResult $analysisResult */
            $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
            $analysisResult->setOrderAddressId((int)$address->getEntityId());
            $analysisResult->setStreet(implode(' ', $address->getStreet()));
            $analysisResult->setLastName('Lustig');
            $analysisResult->setFirstName('Peter');
            $analysisResult->setCity($address->getCity());
            $analysisResult->setPostalCode($address->getPostcode());
            $analysisResult->setStatusCodes(
                ['PDC050105'] // DeliverabilityScore::PERSON_DELIVERABLE
            );
            $analysisResult->setStreetNumber('12');
            /** @var AnalysisResultRepository $analysisResultRepo */
            $analysisResultRepo->save($analysisResult);
            self::$analysisResults[$analysisResult->getOrderAddressId()] = $analysisResult;
        }
    }

    /**
     * Test covers case where analyse status code for order is not deliverable.
     *
     * assert that order status is on hold
     *
     * @test
     * @magentoDataFixture createAnalysisResultsWithUndeliverableStatus
     */
    public function holdNonDeliverableSuccess(): void
    {
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

        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(
            OrderAnalysis::class,
            [
                'addressAnalysisService' => $addressAnalysis
            ]
        );

        $orderAnalysis->holdNonDeliverable(self::$orders);

        foreach (self::$orders as $order) {
            self::assertEquals(Order::STATE_HOLDED, $order->getState());
        }
    }

    /**
     * Test covers case where analyse status code for order is not deliverable.
     *
     * assert that order status is on hold
     *
     * @test
     * @magentoDataFixture createAnalysisResultsWithDeliverableStatus
     */
    public function holdNonDeliverableNotApplicable(): void
    {
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

        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(
            OrderAnalysis::class,
            [
                'addressAnalysisService' => $addressAnalysis
            ]
        );

        $orderAnalysis->holdNonDeliverable(self::$orders);

        foreach (self::$orders as $order) {
            self::assertNotEquals(Order::STATE_HOLDED, $order->getState());
        }
    }

    /**
     * Test covers case where analyse status code for order is not deliverable and order is canceled.
     *
     * assert that order is canceled
     *
     * @test
     * @magentoDataFixture createAnalysisResultsWithUndeliverableStatus
     */
    public function cancelUndeliverableSucccess(): void
    {
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

        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(
            OrderAnalysis::class,
            [
                'addressAnalysisService' => $addressAnalysis
            ]
        );

        $orderAnalysis->cancelUndeliverable(self::$orders);

        foreach (self::$orders as $order) {
            self::assertTrue($order->isCanceled());
        }
    }

    /**
     * Test covers case where analyse status code for order is not deliverable and order is canceled.
     *
     * assert that order is NOT canceled
     *
     * @test
     * @magentoDataFixture createAnalysisResultsWithDeliverableStatus
     */
    public function cancelUndeliverableNotApplicable(): void
    {
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

        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(
            OrderAnalysis::class,
            [
                'addressAnalysisService' => $addressAnalysis
            ]
        );

        $orderAnalysis->cancelUndeliverable(self::$orders);
        $statusRepository = Bootstrap::getObjectManager()->create(AnalysisStatusRepository::class);
        foreach (self::$orders as $order) {
            $status = $statusRepository->getByOrderId((int)$order->getEntityId())->getStatus();
            self::assertFalse($order->isCanceled());
            self::assertEquals(DeliverabilityStatus::DELIVERABLE, $status);
        }
    }

    /**
     * Test covers case where analyse status code for order is not deliverable and order is canceled.
     *
     * assert that order is NOT canceled
     *
     * @test
     * @magentoDataFixture createAnalysisResultsWithDeliverableStatus
     */
    public function updateShippingAddressSuccess(): void
    {
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

        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(
            OrderAnalysis::class,
            [
                'addressAnalysisService' => $addressAnalysis
            ]
        );

        $orderAnalysis->updateShippingAddress(self::$orders);

        $statusRepository = Bootstrap::getObjectManager()->create(AnalysisStatusRepository::class);

        foreach (self::$orders as $order) {
            $shippingAddress = $order->getShippingAddress();
            if (isset(self::$analysisResults[$shippingAddress->getId()])) {
                $analysisResult = self::$analysisResults[$shippingAddress->getId()];
                self::assertEquals($analysisResult->getFirstName(), $shippingAddress->getFirstname());
                self::assertEquals($analysisResult->getLastName(), $shippingAddress->getLastname());
                $status = $statusRepository->getByOrderId((int)$order->getEntityId())->getStatus();
                self::assertEquals(DeliverabilityStatus::ADDRESS_CORRECTED, $status);
            }
        }
    }
}
