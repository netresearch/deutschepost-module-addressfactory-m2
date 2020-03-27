<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Test\Integration\Fixture\AnalysisFixture;

class OrderAnalysisTest extends TestCase
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
     * Test covers case where analyse status code for order is not deliverable and order is canceled.
     *
     * assert that order is NOT canceled
     *
     * @test
     * @magentoDataFixture createAnalysisResultsWithDeliverableStatus
     */
    public function updateShippingAddressSuccess(): void
    {
        /** @var OrderAnalysis $orderAnalysis */
        $orderAnalysis = Bootstrap::getObjectManager()->create(OrderAnalysis::class);
        /** @var AnalysisStatusRepository $statusRepository */
        $statusRepository = Bootstrap::getObjectManager()->create(AnalysisStatusRepository::class);
        /** @var AnalysisResultRepository $analysisResultRepo */
        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $shippingAddress = $order->getShippingAddress();

            $analysisResult = $analysisResultRepo->getByAddressId((int)$shippingAddress->getEntityId());

            $result = $orderAnalysis->updateShippingAddress($order, $analysisResult);
            self::assertTrue($result);
            self::assertEquals($analysisResult->getFirstName(), $shippingAddress->getFirstname());
            self::assertEquals($analysisResult->getLastName(), $shippingAddress->getLastname());
            $status = $statusRepository->getByOrderId((int)$order->getEntityId())->getStatus();
            self::assertEquals(AnalysisStatusUpdater::ADDRESS_CORRECTED, $status);
        }
    }
}
