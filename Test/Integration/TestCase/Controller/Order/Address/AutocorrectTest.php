<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Controller\Order\Address;

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
class AutocorrectTest extends AbstractBackendController
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
    protected $uri = 'backend/postdirekt/order_address/autocorrect';

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
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 0
     *
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function autocorrectAddressSuccess()
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
        /** @var OrderInterface $order */
        $order = $orderRepository->get((int) self::$order->getId());
        /** @var OrderAddressInterface $shippingAddress */
        $shippingAddress = $order->getShippingAddress();
        self::assertEquals(self::$analysisResult->getPostalCode(), $shippingAddress->getPostcode());
        self::assertEquals(self::$analysisResult->getCity(), $shippingAddress->getCity());
        self::assertContains(self::$analysisResult->getStreetNumber(), self::$analysisResult->getStreetNumber());
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
