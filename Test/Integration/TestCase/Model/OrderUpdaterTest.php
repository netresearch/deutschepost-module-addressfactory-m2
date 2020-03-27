<?php
/**
 * See LICENSE.md for license details.
 */

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\OrderUpdater;
use PostDirekt\Addressfactory\Test\Integration\Fixture\AnalysisFixture;

class OrderUpdaterTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $orders = [];

    public static function createAnalysisResultsWithUndeliverableStatus(): void
    {
        self::$orders = [
            AnalysisFixture::createUndeliverableAnalyzedOrder(),
            AnalysisFixture::createUndeliverableAnalyzedOrder(),
        ];
    }

    public static function createAnalysisResultsWithDeliverableStatus(): void
    {
        self::$orders = [
            AnalysisFixture::createDeliverableAnalyzedOrder(),
            AnalysisFixture::createDeliverableAnalyzedOrder(),
        ];
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
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $wasHeld = $orderUpdater->holdIfNonDeliverable(
                $order,
                $resultRepo->getByAddressId((int) $order->getShippingAddressId())
            );
            self::assertTrue($wasHeld);
            self::assertEquals(Order::STATE_HOLDED, $order->getState());
        }
    }

    /**
     * Test covers case where analyse status code for order is deliverable.
     *
     * assert that order status is NOT on hold
     *
     * @test
     * @magentoDataFixture createAnalysisResultsWithDeliverableStatus
     */
    public function holdNonDeliverableNotApplicable(): void
    {
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $wasHeld = $orderUpdater->holdIfNonDeliverable(
                $order,
                $resultRepo->getByAddressId((int) $order->getShippingAddressId())
            );
            self::assertFalse($wasHeld);
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
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $analysisResult = $resultRepo->getByAddressId((int) $order->getData('shipping_address_id'));
            $wasCancelled = $orderUpdater->cancelIfUndeliverable($order, $analysisResult);

            self::assertTrue($wasCancelled);
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
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $wasCancelled = $orderUpdater->cancelIfUndeliverable(
                $order,
                $resultRepo->getByAddressId((int) $order->getShippingAddressId())
            );
            self::assertFalse($wasCancelled);
            self::assertFalse($order->isCanceled());
        }
    }
}
