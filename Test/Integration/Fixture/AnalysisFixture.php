<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Fixture;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;

class AnalysisFixture
{
    /**
     * Create orders which were just placed, not analysed yet.
     *
     * @return OrderInterface[]
     * @throws \Exception
     */
    public static function createPendingOrders(): array
    {
        $shippingMethod = 'flatrate_flatrate';
        $orders = [
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod),
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod),
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod)
        ];

        /** @var AnalysisStatusUpdater $statusManagement */
        $statusManagement = Bootstrap::getObjectManager()->get(AnalysisStatusUpdater::class);

        foreach ($orders as $order) {
            $statusManagement->setStatusPending((int) $order->getEntityId());
        }

        return $orders;
    }

    /**
     * Create orders which were already sent to the webservice and returned different results.
     *
     * Note: currently fixed results (failed, undeliverable, deliverable) are created.
     * Method can be parametrized if required.
     *
     * @return OrderInterface[]
     */
    public static function createMixedAnalyzedOrders(): array
    {
        return [
            self::createAnalysisFailedOrder(),
            self::createUndeliverableAnalyzedOrder(),
            self::createDeliverableAnalyzedOrder(),
        ];
    }

    public static function createAnalysisFailedOrder(): OrderInterface
    {
        /** @var AnalysisStatusUpdater $statusManagement */
        $statusManagement = Bootstrap::getObjectManager()->get(AnalysisStatusUpdater::class);

        $shippingMethod = 'flatrate_flatrate';
        $order = OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod);

        // no response received from web service, no analysis result to create for order #0.
        $statusManagement->setStatusAnalysisFailed((int)$order->getEntityId());

        return $order;
    }


    public static function createDeliverableAnalyzedOrder(): OrderInterface
    {
        /** @var AnalysisStatusUpdater $statusManagement */
        $statusManagement = Bootstrap::getObjectManager()->get(AnalysisStatusUpdater::class);
        /** @var AnalysisResultRepository $repository */
        $repository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        $shippingMethod = 'flatrate_flatrate';
        $order = OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod);
        // save analysis result and status for order #2
        $data = ['data' => [
            AnalysisResultInterface::ORDER_ADDRESS_ID => (int)$order->getDatA('shipping_address_id'),
            AnalysisResultInterface::FIRST_NAME => 'Colin',
            AnalysisResultInterface::LAST_NAME => 'Correct',
            AnalysisResultInterface::CITY => 'Goodinborough',
            AnalysisResultInterface::POSTAL_CODE => '11111',
            AnalysisResultInterface::STREET => 'Gutenberg Ave.',
            AnalysisResultInterface::STREET_NUMBER => '1',
            AnalysisResultInterface::STATUS_CODE => 'PDC050105',
        ]];

        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResultInterface::class, $data);
        $repository->save($analysisResult);
        $statusManagement->setStatusDeliverable((int)$order->getEntityId());

        return $order;
    }

    public static function createUndeliverableAnalyzedOrder(): OrderInterface
    {
        /** @var AnalysisStatusUpdater $statusManagement */
        $statusManagement = Bootstrap::getObjectManager()->get(AnalysisStatusUpdater::class);
        /** @var AnalysisResultRepository $repository */
        $repository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        $order = OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], 'flatrate_flatrate');
        $data = ['data' => [
            AnalysisResultInterface::ORDER_ADDRESS_ID => (int)$order->getData('shipping_address_id'),
            AnalysisResultInterface::FIRST_NAME => 'Uncle',
            AnalysisResultInterface::LAST_NAME => 'Undeliverable',
            AnalysisResultInterface::CITY => 'Badminton',
            AnalysisResultInterface::POSTAL_CODE => '99999',
            AnalysisResultInterface::STREET => 'Zea Drive',
            AnalysisResultInterface::STREET_NUMBER => '9017 A',
            AnalysisResultInterface::STATUS_CODE => 'BAC000111',
        ]
        ];
        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResultInterface::class, $data);
        $repository->save($analysisResult);

        $statusManagement->setStatusUndeliverable((int)$order->getEntityId());

        return $order;
    }
}
