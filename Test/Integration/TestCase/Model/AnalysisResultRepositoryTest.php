<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;

class AnalysisResultRepositoryTest extends TestCase
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
    public function saveAnalysisResultAndGetByAddressIdSuccess(): void
    {
        /** @var Order $order */
        $order = self::$order;

        $data = ['data' => [
                AnalysisResultInterface::ORDER_ADDRESS_ID => (int) $order->getAddresses()[0]->getEntityId(),
                AnalysisResultInterface::FIRST_NAME => 'Hans',
                AnalysisResultInterface::LAST_NAME => 'Muster',
                AnalysisResultInterface::CITY => 'Bonn',
                AnalysisResultInterface::POSTAL_CODE => '01234',
                AnalysisResultInterface::STREET => 'Musterstr.',
                AnalysisResultInterface::STREET_NUMBER => '12',
                AnalysisResultInterface::STATUS_CODE => '12345'
            ]
        ];

        /** @var AnalysisResultInterface $analysisResult */
        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResultInterface::class, $data);
        /** @var AnalysisResultRepository $repository */
        $repository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        $repository->save($analysisResult);
        static::assertEquals($order->getAddresses()[0]->getEntityId(), $analysisResult->getOrderAddressId());

        $result =  $repository->getByAddressId((int) $order->getAddresses()[0]->getEntityId());
        static::assertEquals($result->getStreet(), $analysisResult->getStreet());
    }
}
