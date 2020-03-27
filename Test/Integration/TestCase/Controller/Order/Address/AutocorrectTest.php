<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Controller\Order\Address;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\MockObject\MockObject;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\AnalysisFixture;
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
     * @throws \Exception
     */
    public static function createAnalysisResults(): void
    {
        self::$order = AnalysisFixture::createDeliverableAnalyzedOrder();
    }

    /**
     * @test
     * @magentoDataFixture createAnalysisResults
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 0
     *
     * @throws InputException
     * @throws NoSuchEntityException
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

        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        $analysisResult = $resultRepo->getByAddressId((int)$shippingAddress->getEntityId());

        self::assertEquals($analysisResult->getPostalCode(), $shippingAddress->getPostcode());
        self::assertEquals($analysisResult->getCity(), $shippingAddress->getCity());
        self::assertContains($analysisResult->getStreetNumber(), $analysisResult->getStreetNumber());
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
