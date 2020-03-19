<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Fixture;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;
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

        /** @var DeliverabilityStatus $statusManagement */
        $statusManagement = Bootstrap::getObjectManager()->get(DeliverabilityStatus::class);

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
     * @throws \Exception
     */
    public static function createAnalyzedOrders(): array
    {
        $shippingMethod = 'flatrate_flatrate';
        $orders = [
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod),
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod),
            OrderFixture::createOrder(new AddressDe(), [new SimpleProduct()], $shippingMethod)
        ];

        /** @var DeliverabilityStatus $statusManagement */
        $statusManagement = Bootstrap::getObjectManager()->get(DeliverabilityStatus::class);
        /** @var AnalysisResultRepository $repository */
        $repository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        // no response received from web service, no analysis result to create.
        $statusManagement->setStatusAnalysisFailed((int) $orders[0]->getEntityId());

        // save analysis result and status for order #1
        /** @var AnalysisResult $analysisResult */
        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
        $analysisResult->setOrderAddressId((int) $orders[1]->getData('shipping_address_id'));
        $analysisResult->setStreet('Zea Drive');
        $analysisResult->setStreetNumber('9017 A');
        $analysisResult->setFirstName('Uncle');
        $analysisResult->setLastName('Undeliverable');
        $analysisResult->setCity('Badminton');
        $analysisResult->setPostalCode('99999');
        $analysisResult->setStatusCodes(['PDC050500', 'PDC040106']);
        $repository->save($analysisResult);
        $statusManagement->setStatusUndeliverable((int) $orders[1]->getEntityId());

        // save analysis result and status for order #2
        /** @var AnalysisResult $analysisResult */
        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
        $analysisResult->setOrderAddressId((int) $orders[2]->getData('shipping_address_id'));
        $analysisResult->setStreet('Gutenberg Ave.');
        $analysisResult->setStreetNumber('1');
        $analysisResult->setFirstName('Colin');
        $analysisResult->setLastName('Correct');
        $analysisResult->setCity('Goodinborough');
        $analysisResult->setPostalCode('11111');
        $analysisResult->setStatusCodes(['PDC050105']);
        $repository->save($analysisResult);
        $statusManagement->setStatusDeliverable((int) $orders[2]->getEntityId());

        return $orders;
    }
}
