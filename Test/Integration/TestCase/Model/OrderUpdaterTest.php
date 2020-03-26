<?php
/**
 * See LICENSE.md for license details.
 */

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\OrderUpdater;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressUs;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;

class OrderUpdaterTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $orders = [];

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
            $analysisResult->setStatusCodes(
                [
                    'PDC050106', //DeliverabilityScore::PERSON_NOT_DELIVERABLE
                    'PDC040106', //DeliverabilityScore::HOUSEHOLD_UNDELIVERABLE
                ]
            );
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
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $wasHeld = $orderUpdater->holdIfNonDeliverable(
                $order,
                $resultRepo->getByAddressId($order->getShippingAddressId())
            );
            self::assertTrue($wasHeld);
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
        /** @var OrderUpdater $orderUpdater */
        $orderUpdater = Bootstrap::getObjectManager()->create(OrderUpdater::class);
        /** @var AnalysisResultRepository $resultRepo */
        $resultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $wasHeld = $orderUpdater->holdIfNonDeliverable(
                $order,
                $resultRepo->getByAddressId($order->getShippingAddressId())
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
            $wasCancelled = $orderUpdater->cancelIfUndeliverable(
                $order,
                $resultRepo->getByAddressId($order->getShippingAddressId())
            );
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
                $resultRepo->getByAddressId($order->getShippingAddressId())
            );
            self::assertFalse($wasCancelled);
            self::assertFalse($order->isCanceled());
        }
    }
}
