<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Controller;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\MockObject\MockObject;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderBuilder;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

/**
 * @magentoAppArea adminhtml
 */
class AnalyseTest extends AbstractBackendController
{
    /**
     * The resource used to authorize action
     *
     * @var string
     */
    protected $resource = 'Magento_Sales::actions_edit';

    /**
     * The uri at which to access the controller
     *
     * @var string
     */
    protected $uri = 'backend/postdirekt/analysis/analyse';

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
     * Test covers case where status is updated for undeliverable orders (according to configuration).
     *
     * - Assert that order is put on hold.
     *
     * @test
     * @magentoDataFixture createOrders
     * @magentoConfigFixture default_store postdirekt/addressfactory/hold_non_deliverable_orders 1
     *
     * @throws LocalizedException
     */
    public function holdNonDeliverableOrderSuccess(): void
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

        Bootstrap::getObjectManager()->addSharedInstance($addressAnalysis, AddressAnalysis::class);
        $this->getRequest()->setParam('order_id', self::$undeliverableOrder->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$undeliverableOrder->getId());
        self::assertFalse($order->canHold());
        self::assertEquals(Order::STATE_HOLDED, $order->getState());
    }

    /**
     * Test covers case where status is not updated for undeliverable orders (according to configuration).
     *
     * - Assert that order is not on hold.
     *
     * @test
     * @magentoDataFixture createOrders
     * @magentoConfigFixture default_store postdirekt/addressfactory/hold_non_deliverable_orders 0
     *
     * @throws LocalizedException
     */
    public function holdNonDeliverableOrderWithDisabledConfig(): void
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

        Bootstrap::getObjectManager()->addSharedInstance($addressAnalysis, AddressAnalysis::class);
        $this->getRequest()->setParam('order_id', self::$undeliverableOrder->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$undeliverableOrder->getId());
        self::assertTrue($order->canHold());
        self::assertEquals(Order::STATE_NEW, $order->getState());
    }

    /**
     * Test covers case where all orders that are undeliverable are cancelled (according to configuration).
     *
     * @test
     * @magentoDataFixture createOrders
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_cancel_orders 1
     *
     * @throws LocalizedException
     */
    public function autoCancelNonDeliverableOrdersSuccess(): void
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

        Bootstrap::getObjectManager()->addSharedInstance($addressAnalysis, AddressAnalysis::class);
        $this->getRequest()->setParam('order_id', self::$undeliverableOrder->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$undeliverableOrder->getId());
        self::assertFalse($order->canCancel());
        self::assertEquals(Order::STATE_CANCELED, $order->getState());
    }

    /**
     * Test covers case where all orders that are undeliverable are not cancelled (according to configuration).
     *
     * @test
     * @magentoDataFixture createOrders
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_cancel_orders 0
     *
     * @throws LocalizedException
     */
    public function autoCancelNonDeliverableOrdersWithDisabledConfig(): void
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

        Bootstrap::getObjectManager()->addSharedInstance($addressAnalysis, AddressAnalysis::class);
        $this->getRequest()->setParam('order_id', self::$undeliverableOrder->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$undeliverableOrder->getId());
        self::assertTrue($order->canCancel());
        self::assertEquals(Order::STATE_NEW, $order->getState());
    }

    /**
     * Test covers case where the order shipping address is updated.
     *
     * - Assert that order address is updated with analysis result address data.
     *
     * @test
     * @magentoDataFixture createOrders
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 1
     *
     * @throws LocalizedException
     */
    public function autoUpdateShippingAddressSuccess(): void
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

        Bootstrap::getObjectManager()->addSharedInstance($addressAnalysis, AddressAnalysis::class);
        $this->getRequest()->setParam('order_id', self::$deliverableOrder->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$deliverableOrder->getId());
        $shippingAddress = $order->getShippingAddress();

        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        $analysisResult = $resultRepo->getByAddressId((int) $shippingAddress->getEntityId());

        self::assertEquals($analysisResult->getPostalCode(), $shippingAddress->getPostcode());
        self::assertEquals($analysisResult->getCity(), $shippingAddress->getCity());
    }

    /**
     * Test covers case where the order shipping address is not updated.
     *
     * - Assert that order address is not updated with analysis result address data.
     *
     * @test
     * @magentoDataFixture createOrders
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 0
     *
     * @throws LocalizedException
     */
    public function autoUpdateShippingAddressWithDisabledConfig(): void
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

        Bootstrap::getObjectManager()->addSharedInstance($addressAnalysis, AddressAnalysis::class);
        $this->getRequest()->setParam('order_id', self::$deliverableOrder->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$deliverableOrder->getId());
        $shippingAddress = $order->getShippingAddress();

        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        $analysisResult = $resultRepo->getByAddressId((int) $shippingAddress->getEntityId());

        self::assertNotEquals($analysisResult->getPostalCode(), $shippingAddress->getPostcode());
        self::assertNotEquals($analysisResult->getCity(), $shippingAddress->getCity());
        self::assertNotContains($analysisResult->getStreetNumber(), $shippingAddress->getStreet());
    }


    /**
     * @magentoDataFixture createOrders
     */
    public function testAclHasAccess()
    {
        $this->getRequest()->setParam('order_id', self::$deliverableOrder->getId());
        parent::testAclHasAccess();
    }
}
