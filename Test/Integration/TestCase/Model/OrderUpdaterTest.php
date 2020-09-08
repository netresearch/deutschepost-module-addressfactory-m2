<?php
/**
 * See LICENSE.md for license details.
 */

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\OrderUpdater;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderBuilder;

class OrderUpdaterTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $deliverableOrders;

    /**
     * @var Order[]
     */
    private static $undeliverableOrders;

    /**
     * @throws \Exception
     */
    public static function createOrders(): void
    {
        // reset previously created orders
        self::$deliverableOrders = [];
        self::$undeliverableOrders = [];

        // create two orders per type
        for ($i = 0; $i < 2; $i++) {
            $deliverableOrder = OrderBuilder::anOrder()
                ->withAnalysisStatus(AnalysisStatusUpdater::DELIVERABLE)
                ->withShippingMethod('flatrate_flatrate')
                ->build();

            $undeliverableOrder = OrderBuilder::anOrder()
                ->withAnalysisStatus(AnalysisStatusUpdater::UNDELIVERABLE)
                ->withShippingMethod('flatrate_flatrate')
                ->build();

            self::$deliverableOrders[$deliverableOrder->getEntityId()] = $deliverableOrder;
            self::$undeliverableOrders[$undeliverableOrder->getEntityId()] = $undeliverableOrder;
        }
    }

    /**
     * Change order status to "on hold" if applicable.
     *
     * - assert that order status is changed for undeliverable addresses
     * - assert that order status remains the same for deliverable addresses
     *
     * @test
     * @magentoDataFixture createOrders
     */
    public function updateStatus(): void
    {
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        $orders = self::$deliverableOrders + self::$undeliverableOrders;
        foreach ($orders as $orderId => $order) {
            $orderState = $order->getState();

            $analysisResult = $resultRepository->getByAddressId((int) $order->getData('shipping_address_id'));
            $isUpdated = $orderUpdater->holdIfNonDeliverable($order, $analysisResult);

            if (isset(self::$undeliverableOrders[$orderId])) {
                self::assertTrue($isUpdated);
                self::assertEquals(Order::STATE_HOLDED, $order->getState());
            } else {
                self::assertFalse($isUpdated);
                self::assertEquals($orderState, $order->getState());
            }
        }
    }

    /**
     * Cancel order if applicable.
     *
     * - assert that order with undeliverable address is cancelled
     * - assert that order deliverable address remains open
     *
     * @test
     * @magentoDataFixture createOrders
     */
    public function cancelOrder(): void
    {
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        $orders = self::$deliverableOrders + self::$undeliverableOrders;
        foreach ($orders as $orderId => $order) {
            $analysisResult = $resultRepository->getByAddressId((int) $order->getData('shipping_address_id'));
            $wasCancelled = $orderUpdater->cancelIfUndeliverable($order, $analysisResult);

            if (isset(self::$undeliverableOrders[$orderId])) {
                self::assertTrue($wasCancelled);
                self::assertTrue($order->isCanceled());
            } else {
                self::assertFalse($wasCancelled);
                self::assertFalse($order->isCanceled());
            }
        }
    }
}
