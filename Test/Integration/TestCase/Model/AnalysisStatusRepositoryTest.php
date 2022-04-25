<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;
use PostDirekt\Addressfactory\Model\AnalysisStatus;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderBuilder;

class AnalysisStatusRepositoryTest extends TestCase
{
    /**
     * @var Order
     */
    private static $order;

    /**
     * Create order fixture for DE recipient address.
     *
     * @throws \Exception
     */
    public static function createOrder(): void
    {
        self::$order = OrderBuilder::anOrder()->withShippingMethod('flatrate_flatrate')->build();
    }

    /**
     * @test
     * @magentoDataFixture createOrder
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function saveAnalysisStatusAndGetByOrderId(): void
    {
        $data = ['data' => [
            AnalysisStatus::ORDER_ID => self::$order->getEntityId(),
            AnalysisStatus::STATUS => 'anyStatus'
        ]];

        /** @var AnalysisStatus $analysisStatus */
        $analysisStatus = Bootstrap::getObjectManager()->create(AnalysisStatus::class, $data);
        /** @var AnalysisStatusRepository $repository */
        $repository = Bootstrap::getObjectManager()->create(AnalysisStatusRepository::class);
        $repository->save($analysisStatus);
        static::assertEquals(self::$order->getEntityId(), $analysisStatus->getOrderId());
        $result = $repository->getByOrderId((int) self::$order->getEntityId());
        static::assertEquals($result->getOrderId(), $analysisStatus->getOrderId());
    }
}
