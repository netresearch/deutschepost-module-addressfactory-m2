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
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Test\Integration\Fixture\AnalysisFixture;
use PostDirekt\Sdk\AddressfactoryDirect\Service\ServiceFactory;

class AddressUpdaterTest extends TestCase
{
    /**
     * @var Order[]
     */
    private static $orders = [];

    /**
     * @throws \Exception
     */
    public static function createAnalysisResults(): void
    {
        self::$orders = [
            AnalysisFixture::createDeliverableAnalyzedOrder(),
            AnalysisFixture::createUndeliverableAnalyzedOrder()
        ];
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
        $analysisResultRepo = Bootstrap::getObjectManager()->get(AnalysisResultRepository::class);
        /** @var OrderAddressRepositoryInterface $orderAddressRepository */
        $orderAddressRepository = Bootstrap::getObjectManager()->get(OrderAddressRepositoryInterface::class);
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
