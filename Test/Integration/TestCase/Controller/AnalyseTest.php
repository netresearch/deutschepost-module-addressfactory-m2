<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Controller;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\MockObject\MockObject;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;
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
    private static $order;

    /**
     * @var AnalysisResult
     */
    private static $analysisResult;

    /**
     * Create order fixture for DE recipient address.
     *
     * @throws \Exception
     */
    private static function createOrders(): void
    {
        $shippingMethod = 'flatrate_flatrate';
        self::$order = OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod);
    }

    /**
     * @throws \Exception
     */
    public static function createAnalysisResults(): void
    {
        self::createOrders();

        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        $address = self::$order->getShippingAddress();
        /** @var AnalysisResult $analysisResult */
        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
        $analysisResult->setOrderAddressId((int)$address->getEntityId());
        $analysisResult->setStreet('Musterstr.');
        $analysisResult->setLastName('Mumpitz');
        $analysisResult->setFirstName('Jochen');
        $analysisResult->setCity('Görlitz');
        $analysisResult->setPostalCode('02345');
        $analysisResult->setStatusCodes(['PDC050106', 'PDC040106']);
        $analysisResult->setStreetNumber('12');
        /** @var AnalysisResultRepository $analysisResultRepo */
        $analysisResultRepo->save($analysisResult);
        self::$analysisResult = $analysisResult;
    }

    /**
     * Test covers case where all orders that are undeliverable are canceled.
     *
     * assert that order is canceled.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/hold_non_deliverable_orders 1
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
        $this->getRequest()->setParam('order_id', self::$order->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$order->getId());
        self::assertFalse($order->canHold());
        self::assertEquals(Order::STATE_HOLDED, $order->getState());
    }

    /**
     * Test covers case where all orders that are undeliverable are not on hold.
     *
     * assert that order is not on hold.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/hold_non_deliverable_orders 0
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
        $this->getRequest()->setParam('order_id', self::$order->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        $order = $orderRepository->get((int) self::$order->getId());
        self::assertTrue($order->canHold());
        self::assertEquals(Order::STATE_NEW, $order->getState());
    }

    /**
     * Test covers case where all orders that are undeliverable are canceled.
     *
     * assert that order is canceled.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_cancel_orders 1
     */
    public function autoCancelNonDeliverableOrdersSuccess()
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
        $this->getRequest()->setParam('order_id', self::$order->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        /** @var OrderInterface $order */
        $order = $orderRepository->get((int) self::$order->getId());
        self::assertFalse($order->canCancel());
        self::assertEquals(Order::STATE_CANCELED, $order->getState());
    }

    /**
     * Test covers case where all orders that are undeliverable are not canceled.
     *
     * assert that order is not canceled.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_cancel_orders 0
     */
    public function autoCancelNonDeliverableOrdersWithDisabledConfig()
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
        $this->getRequest()->setParam('order_id', self::$order->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        /** @var OrderInterface $order */
        $order = $orderRepository->get((int) self::$order->getId());
        self::assertTrue($order->canCancel());
        self::assertEquals(Order::STATE_NEW, $order->getState());
    }

    /**
     * Test covers case where the order shipping address is updated.
     *
     * assert that order address is updated with analyse result address data.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 1
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
        $this->getRequest()->setParam('order_id', self::$order->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        /** @var OrderInterface $order */
        $order = $orderRepository->get((int) self::$order->getId());
        /** @var OrderAddressInterface $shippingAddress */
        $shippingAddress = $order->getShippingAddress();
        self::assertEquals(self::$analysisResult->getPostalCode(), $shippingAddress->getPostcode());
        self::assertEquals(self::$analysisResult->getCity(), $shippingAddress->getCity());
        self::assertContains(self::$analysisResult->getStreetNumber(), self::$analysisResult->getStreetNumber());
    }

    /**
     * Test covers case where the order shipping address is not updated.
     *
     * assert that order address is not updated with analyse result address data.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 0
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
        $this->getRequest()->setParam('order_id', self::$order->getId());

        $this->dispatch($this->uri);

        /** @var OrderRepository $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepository::class);
        // this is needed because Magento explicitly loads and saves the order
        /** @var OrderInterface $order */
        $order = $orderRepository->get((int) self::$order->getId());
        /** @var OrderAddressInterface $shippingAddress */
        $shippingAddress = $order->getShippingAddress();
        self::assertNotEquals(self::$analysisResult->getPostalCode(), $shippingAddress->getPostcode());
        self::assertNotEquals(self::$analysisResult->getCity(), $shippingAddress->getCity());
        self::assertNotContains(self::$analysisResult->getStreetNumber(), $shippingAddress->getStreet());
    }


    /**
     * @magentoDataFixture createAnalysisResults
     */
    public function testAclHasAccess()
    {
        $this->getRequest()->setParam('order_id', self::$order->getId());
        parent::testAclHasAccess();
    }
}
