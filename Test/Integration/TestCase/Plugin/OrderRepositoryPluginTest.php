<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Plugin;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AddressAnalysis;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderBuilder;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

class OrderRepositoryPluginTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $orders = [];

    /**
     * @throws \Exception
     */
    public static function createOrders(): void
    {
        for ($i = 0; $i < 2; $i++) {
            self::$orders[] = OrderBuilder::anOrder()
                ->withAnalysisStatus(AnalysisStatusUpdater::DELIVERABLE)
                ->withShippingMethod('flatrate_flatrate')
                ->build();
        }
    }

    /**
     * @test
     * @magentoDataFixture createOrders
     */
    public function extensionAttributesAreManifested(): void
    {
        /** @var ServiceFactory|MockObject $mockServiceFactory */
        $mockServiceFactory = $this->getMockBuilder(ServiceFactory::class)->disableOriginalConstructor()->getMock();
        $mockServiceFactory->expects(static::never())->method('createAddressVerificationService');

        /** @var AddressAnalysis $addressAnalysis */
        $addressAnalysis = Bootstrap::getObjectManager()->create(
            AddressAnalysis::class,
            ['serviceFactory' => $mockServiceFactory]
        );

        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(
            OrderAnalysis::class,
            ['addressAnalysisService' => $addressAnalysis]
        );
        $orderAnalysis->analyse(self::$orders);

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->create(OrderRepositoryInterface::class);
        foreach (self::$orders as $order) {
            $processed = $orderRepository->get($order->getEntityId());
            $extAttrStatus = $processed->getExtensionAttributes()->getPostdirektAddressfactoryAnalysisStatus();
            self::assertEquals(AnalysisStatusUpdater::DELIVERABLE, $extAttrStatus);
        }
    }
}
