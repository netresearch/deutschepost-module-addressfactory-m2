<?php
/**
 * See LICENSE.md for license details.
 */

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AddressUpdater;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressDe;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressUs;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\SimpleProduct;
use PostDirekt\Addressfactory\Test\Integration\Fixture\OrderFixture;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

class AddressUpdaterTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $orders = [];

    /**
     * @var AnalysisResult[]
     */
    private static $analysisResults = [];

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
    public static function createAnalysisResults(): void
    {
        self::createOrders();

        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);

        foreach (self::$orders as $order) {
            $address = $order->getShippingAddress();
            /** @var AnalysisResult $analysisResult */
            $analysisResult = Bootstrap::getObjectManager()->create(AnalysisResult::class);
            $analysisResult->setOrderAddressId((int)$address->getEntityId());
            $analysisResult->setStreet(implode(' ', $address->getStreet()));
            $analysisResult->setLastName($address->getLastname());
            $analysisResult->setFirstName($address->getFirstname());
            $analysisResult->setCity($address->getCity());
            $analysisResult->setPostalCode($address->getPostcode());
            $analysisResult->setStatusCodes(['test']);
            $analysisResult->setStreetNumber('12');
            /** @var AnalysisResultRepository $analysisResultRepo */
            $analysisResultRepo->save($analysisResult);
            self::$analysisResults[$analysisResult->getOrderAddressId()] = $analysisResult;
        }
    }

    /**
     * Test covers case where addresses are analyzed and the order address is updated and persisted.
     *
     * assert that order address is updated with data from analysis result and is saved.
     *
     * @test
     * @magentoDataFixture createAnalysisResults
     * @throws LocalizedException
     */
    public function updateSuccess(): void
    {

        /** @var ServiceFactory|MockObject $mockServiceFactory */
        $mockServiceFactory = $this->getMockBuilder(ServiceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockServiceFactory
            ->expects(static::never())
            ->method('createAddressVerificationService');

        /** @var AnalysisResultRepository $analysisResultRepo */
        $analysisResultRepo = Bootstrap::getObjectManager()->create(AnalysisResultRepository::class);
        /** @var OrderAddressRepositoryInterface $orderAddressRepository */
        $orderAddressRepository = Bootstrap::getObjectManager()->create(OrderAddressRepositoryInterface::class);
        /** @var AddressUpdater $addressUpdater */
        $addressUpdater = Bootstrap::getObjectManager()->create(AddressUpdater::class);

        foreach (self::$orders as $order) {
            $analysisResult = $analysisResultRepo->getByAddressId($order->getShippingAddress()->getEntityId());
            $result = $addressUpdater->update(
                $analysisResult,
                $order->getShippingAddress()
            );

            self::assertTrue($result);

            $savedOrder = $orderAddressRepository->get($analysisResult->getOrderAddressId());
            self::assertEquals($savedOrder->getCity(), $analysisResult->getCity());
            self::assertEquals($savedOrder->getEntityId(), $analysisResult->getOrderAddressId());
            self::assertEquals($savedOrder->getPostcode(), $analysisResult->getPostalCode());
        }
    }

}
