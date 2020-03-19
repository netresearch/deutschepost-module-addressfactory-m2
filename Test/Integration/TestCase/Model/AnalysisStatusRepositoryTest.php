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
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;

/**
 * Class AnalysisStatusRepositoryTest
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class AnalysisStatusRepositoryTest extends TestCase
{
    protected static $order;

    /**
     * Create order fixture for DE recipient address.
     *
     * @throws \Exception
     */
    public static function createOrder(): void
    {
        $shippingMethod = 'flatrate_flatrate';
        self::$order = OrderFixture::createOrder(
            new AddressDe(),
            [new SimpleProduct()],
            $shippingMethod
        );
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
        /** @var Order $order */
        $order = self::$order;

        $data = ['data' => [
            AnalysisStatus::ORDER_ID => $order->getEntityId(),
            AnalysisStatus::STATUS => 'anyStatus'
        ]];

        /** @var AnalysisStatus $analysisStatus */
        $analysisStatus = Bootstrap::getObjectManager()->create(AnalysisStatus::class, $data);
        /** @var AnalysisStatusRepository $repository */
        $repository = Bootstrap::getObjectManager()->create(AnalysisStatusRepository::class);
        $repository->save($analysisStatus);
        static::assertEquals($order->getEntityId(), $analysisStatus->getOrderId());
        $result = $repository->getByOrderId((int) $order->getEntityId());
        static::assertEquals($result->getOrderId(), $analysisStatus->getOrderId());
    }
}
