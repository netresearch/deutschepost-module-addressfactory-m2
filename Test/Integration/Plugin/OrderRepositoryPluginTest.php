<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Plugin;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

class OrderRepositoryPluginTest extends TestCase
{
    public const STATUS = 'possibly_deliverable';
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
    public static function createOrders(): void
    {
        $shippingMethod = 'flatrate_flatrate';
        self::$orders = [
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod),
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod)
        ];
    }

    public static function createAnalysisResults()
    {
        self::createOrders();
        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        foreach (self::$orders as $order) {
            $address = $order->getShippingAddress();
            /** @var AnalysisResultInterface $analysisResult */
            $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResultInterface::class);
            $analysisResult->setData(AnalysisResultInterface::ORDER_ADDRESS_ID, (int) $address->getEntityId());
            $analysisResult->setData(AnalysisResultInterface::STREET, implode(' ', $address->getStreet()));
            $analysisResult->setData(AnalysisResultInterface::LAST_NAME, $address->getLastname());
            $analysisResult->setData(AnalysisResultInterface::FIRST_NAME, $address->getFirstname());
            $analysisResult->setData(AnalysisResultInterface::CITY, $address->getCity());
            $analysisResult->setData(AnalysisResultInterface::POSTAL_CODE, $address->getPostcode());
            $analysisResult->setData(AnalysisResultInterface::STATUS_CODE, implode(',', [self::STATUS]));
            $analysisResult->setData(AnalysisResultInterface::STREET_NUMBER, '11');
            /** @var AnalysisResultRepository $analysisResultRepo */
            $analysisResultRepo->save($analysisResult);
            self::$analysisResults[$analysisResult->getOrderAddressId()] = $analysisResult;
        }
    }

    /**
     * @test
     * @magentoDataFixture createAnalysisResults
     */
    public function extensionAttributesAreManifested()
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
        $orderAnalysis->analyse(self::$orders);
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepositoryInterface::class);
        foreach (self::$orders as $order) {
            $processed = $orderRepository->get($order->getEntityId());
            $extAttrStatus = $processed->getExtensionAttributes()
                                       ->getPostdirektAddressfactoryAnalysisStatus();
            self::assertEquals(self::STATUS, $extAttrStatus);
        }
    }
}
