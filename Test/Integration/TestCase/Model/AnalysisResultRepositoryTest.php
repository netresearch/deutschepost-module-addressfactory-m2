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
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;

/**
 * AnalysisResult Repository Test
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
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
                AnalysisResult::ORDER_ADDRESS_ID => (int) $order->getAddresses()[0]->getEntityId(),
                AnalysisResult::FIRST_NAME => 'Hans',
                AnalysisResult::LAST_NAME => 'Muster',
                AnalysisResult::CITY => 'Bonn',
                AnalysisResult::POSTAL_CODE => '01234',
                AnalysisResult::STREET => 'Musterstr.',
                AnalysisResult::STREET_NUMBER => '12',
                AnalysisResult::STATUS_CODE => '12345'
            ]
        ];

        /** @var AnalysisResult $analysisResult */
        $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class, $data);
        /** @var AnalysisResultRepository $repository */
        $repository = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        $repository->save($analysisResult);
        static::assertEquals($order->getAddresses()[0]->getEntityId(), $analysisResult->getOrderAddressId());

        $result =  $repository->getByAddressId((int) $order->getAddresses()[0]->getEntityId());
        static::assertEquals($result->getStreet(), $analysisResult->getStreet());
    }
}
