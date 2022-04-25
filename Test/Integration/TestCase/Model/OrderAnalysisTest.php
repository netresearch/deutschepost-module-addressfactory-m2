<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderBuilder;

class OrderAnalysisTest extends TestCase
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
        // create two orders per type
        for ($i = 0; $i < 2; $i++) {
            self::$orders[] = OrderBuilder::anOrder()
                ->withAnalysisStatus(AnalysisStatusUpdater::DELIVERABLE)
                ->withShippingMethod('flatrate_flatrate')
                ->build();
        }
    }

    /**
     * Test address update.
     *
     * - Assert that shipping address fields are updated with analysis result.
     * - Assert that analysis status is updated.
     *
     * @test
     * @magentoDataFixture createOrders
     * @throws NoSuchEntityException
     */
    public function updateShippingAddressSuccess(): void
    {
        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(OrderAnalysis::class);
        /** @var AnalysisStatusRepository $statusRepository */
        $statusRepository = Bootstrap::getObjectManager()->create(AnalysisStatusRepository::class);
        /** @var AnalysisResultRepository $analysisResultRepository */
        $analysisResultRepository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $shippingAddress = $order->getShippingAddress();
            $analysisResult = $analysisResultRepository->getByAddressId((int)$shippingAddress->getEntityId());

            $isUpdated = $orderAnalysis->updateShippingAddress($order, $analysisResult);
            self::assertTrue($isUpdated);

            self::assertEquals($analysisResult->getFirstName(), $shippingAddress->getFirstname());
            self::assertEquals($analysisResult->getLastName(), $shippingAddress->getLastname());
            $status = $statusRepository->getByOrderId((int)$order->getEntityId())->getStatus();
            self::assertEquals(AnalysisStatusUpdater::ADDRESS_CORRECTED, $status);
        }
    }
}
