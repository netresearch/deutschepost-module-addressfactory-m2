<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Cron;

use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Cron\AutoProcess;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Test\Integration\Fixture\AnalysisFixture;
use PostDirekt\Addressfactory\Test\Integration\TestDouble\AddressVerificationServiceFactory;
use PostDirekt\Addressfactory\Test\Integration\TestDouble\AddressVerificationServiceStub;
use PostDirekt\Sdk\AddressfactoryDirect\Api\AddressVerificationServiceInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Api\Data\RecordInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Api\ServiceFactoryInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Address;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Person;
use PostDirekt\Sdk\AddressfactoryDirect\Service\AddressVerificationService\Record;

/**
 * Class AutoProcessTest
 *
 * @magentoAppArea crontab
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class AutoProcessTest extends TestCase
{
    /**
     * @var OrderInterface[]|Order[]
     */
    private static $pendingOrders = [];

    /**
     * @var OrderInterface[]|Order[]
     */
    private static $analyzedOrders = [];

    /**
     * Replace SDK service by stub implementation
     */
    protected function setUp()
    {
        Bootstrap::getObjectManager()->configure(
            [
                'preferences' => [
                    ServiceFactoryInterface::class => AddressVerificationServiceFactory::class,
                    AddressVerificationServiceInterface::class => AddressVerificationServiceStub::class,
                ]
            ]
        );

        parent::setUp();
    }

    /**
     * @throws \Exception
     */
    public static function createPendingOrders(): void
    {
        self::$pendingOrders = AnalysisFixture::createPendingOrders();
    }

    /**
     * @throws \Exception
     */
    public static function createAnalyzedOrders(): void
    {
        self::$analyzedOrders = AnalysisFixture::createMixedAnalyzedOrders();
    }

    /**
     * @fixme(nr): in the current cron implementation, the web service does not return multi-record responses.
     *
     * @return RecordInterface[][][]
     */
    public function webserviceResponseProvider(): array
    {
        return [
            'mixed_response' => [
                function () {
                    $records = [];
                    foreach (self::$pendingOrders as $idx => $order) {
                        if ($idx === 0) {
                            // no web service result for first order
                            continue;
                        }

                        $shippingAddress = $order->getShippingAddress();
                        if ($idx === 1) {
                            // response with undeliverable indicator
                            $records[] = new Record(
                                (int) $shippingAddress->getEntityId(),
                                new Person('', 'Uncle', 'Undeliverable'),
                                new Address('DE', '99999', '', '', '', '', 'Badminton', '', '', 'Zea Drive', '9017 A'),
                                null,
                                null,
                                null,
                                null,
                                [],
                                ['PDC050500', 'PDC040106']
                            );
                        } else {
                            // response with deliverable indicator
                            $records[] = new Record(
                                (int) $shippingAddress->getEntityId(),
                                new Person('', 'Colin', 'Correct'),
                                new Address('DE', '11111', '', '', '', '', 'Goodinborough', '', '', 'Gutenberg Ave.', '1'),
                                null,
                                null,
                                null,
                                null,
                                [],
                                ['PDC050105']
                            );
                        }
                    }

                    return $records;
                }
            ]
        ];
    }

    /**
     * Scenario: processing is set to "on order placement" (1).
     *
     * - Assert that cron analysis does not get started.
     *
     * @test
     * @magentoConfigFixture default_store postdirekt/addressfactory/automatic_address_analysis 1
     */
    public function cronDisabled(): void
    {
        $repositoryMock = $this->getMockBuilder(OrderRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repositoryMock->expects($this->never())->method('getList');

        /** @var AutoProcess $autoProcess */
        $autoProcess = Bootstrap::getObjectManager()->create(
            AutoProcess::class,
            ['orderRepository' => $repositoryMock]
        );

        $autoProcess->execute();
    }

    /**
     * Scenario: cron processing is enabled, auto-address updates are disabled, several new orders exist in the system.
     *
     * - Assert that analysis status is updated according to web service response.
     * - Assert that analysis result is persisted according to web service response.
     * - Assert that shipping addresses remain unaltered.
     *
     * @test
     * @dataProvider webserviceResponseProvider
     *
     * @magentoConfigFixture default_store postdirekt/addressfactory/automatic_address_analysis 2
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 0
     * @magentoDataFixture createPendingOrders
     *
     * @param callable $getRecords
     * @throws LocalizedException
     */
    public function pendingAddressesAreAnalyzed(callable $getRecords): void
    {
        $orders = array_merge(self::$pendingOrders, self::$analyzedOrders);

        /** @var AddressVerificationServiceStub $service */
        $service = Bootstrap::getObjectManager()->get(AddressVerificationServiceInterface::class);
        $service->records = $getRecords();

        /** @var AutoProcess $autoProcess */
        $autoProcess = Bootstrap::getObjectManager()->create(AutoProcess::class);
        $autoProcess->execute();

        /** @var AnalysisStatusRepository $statusRepository */
        $statusRepository = Bootstrap::getObjectManager()->get(AnalysisStatusRepository::class);
        /** @var AnalysisResultRepository $analysisRepository */
        $analysisRepository = Bootstrap::getObjectManager()->get(AnalysisResultRepository::class);
        /** @var OrderAddressRepositoryInterface $addressRepository */
        $addressRepository = Bootstrap::getObjectManager()->get(OrderAddressRepositoryInterface::class);

        self::assertNotEmpty(
            $service->getRequestedRecordsCount(),
            'Web service was never invoked for pending orders'
        );

        foreach ($orders as $order) {
            $orderStatus = $statusRepository->getByOrderId((int) $order->getEntityId());
            self::assertNotEquals(
                AnalysisStatusUpdater::PENDING,
                $orderStatus->getStatus(),
                'Analysis status was not updated from pending'
            );

            if ($orderStatus->getStatus() !== AnalysisStatusUpdater::ANALYSIS_FAILED) {
                $analysis = $analysisRepository->getByAddressId((int) $order->getData('shipping_address_id'));
                self::assertNotEmpty($analysis->getStatusCodes(), 'Analysis results were not persisted locally');

                $shippingAddress = $addressRepository->get($order->getData('shipping_address_id'));
                self::assertNotEquals(
                    $analysis->getFirstName(),
                    $shippingAddress->getFirstname(),
                    'Analysis results were written to shipping address despite configuration setting'
                );
                self::assertNotEquals(
                    $analysis->getLastName(),
                    $shippingAddress->getLastname(),
                    'Analysis results were written to shipping address despite configuration setting'
                );
                self::assertNotEquals(
                    $analysis->getCity(),
                    $shippingAddress->getCity(),
                    'Analysis results were written to shipping address despite configuration setting'
                );
                self::assertNotEquals(
                    $analysis->getPostalCode(),
                    $shippingAddress->getPostcode(),
                    'Analysis results were written to shipping address despite configuration setting'
                );
            }
        }
    }

    /**
     * Scenario: cron processing is enabled, auto-address updates are enabled, several new orders exist in the system.
     *
     * - Assert that shipping addresses are updated according to web service response.
     *
     * @test
     * @dataProvider webserviceResponseProvider
     *
     * @magentoConfigFixture default_store postdirekt/addressfactory/automatic_address_analysis 2
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 1
     * @magentoDataFixture createPendingOrders
     *
     * @param callable $getRecords
     * @throws LocalizedException
     */
    public function pendingAddressesAreUpdated(callable $getRecords): void
    {
        $orders = array_merge(self::$pendingOrders, self::$analyzedOrders);

        /** @var AddressVerificationServiceStub $service */
        $service = Bootstrap::getObjectManager()->get(AddressVerificationServiceInterface::class);
        $service->records = $getRecords();

        /** @var AutoProcess $autoProcess */
        $autoProcess = Bootstrap::getObjectManager()->create(AutoProcess::class);
        $autoProcess->execute();

        /** @var AnalysisStatusRepository $statusRepository */
        $statusRepository = Bootstrap::getObjectManager()->get(AnalysisStatusRepository::class);
        /** @var AnalysisResultRepository $analysisRepository */
        $analysisRepository = Bootstrap::getObjectManager()->get(AnalysisResultRepository::class);
        /** @var OrderAddressRepositoryInterface $addressRepository */
        $addressRepository = Bootstrap::getObjectManager()->get(OrderAddressRepositoryInterface::class);

        foreach ($orders as $order) {
            $orderStatus = $statusRepository->getByOrderId((int)$order->getEntityId());
            self::assertNotEquals(
                AnalysisStatusUpdater::PENDING,
                $orderStatus->getStatus(),
                'Analysis status was not updated from pending'
            );

            if ($orderStatus->getStatus() !== AnalysisStatusUpdater::ANALYSIS_FAILED) {
                $analysis = $analysisRepository->getByAddressId((int)$order->getData('shipping_address_id'));
                self::assertNotEmpty($analysis->getStatusCodes(), 'Analysis results were not persisted locally');
                $shippingAddress = $addressRepository->get($order->getData('shipping_address_id'));
                self::assertEquals(
                    $analysis->getFirstName(),
                    $shippingAddress->getFirstname(),
                    'Analysis results were not written to shipping address despite configuration setting'
                );
                self::assertEquals(
                    $analysis->getLastName(),
                    $shippingAddress->getLastname(),
                    'Analysis results were not written to shipping address despite configuration setting'
                );
                self::assertEquals(
                    $analysis->getCity(),
                    $shippingAddress->getCity(),
                    'Analysis results were not written to shipping address despite configuration setting'
                );
                self::assertEquals(
                    $analysis->getPostalCode(),
                    $shippingAddress->getPostcode(),
                    'Analysis results were not written to shipping address despite configuration setting'
                );
            }
        }
    }

    /**
     * Scenario: cron processing is enabled, several new and old orders exist in the system.
     *
     * - Assert that only addresses with "pending" status are sent to the web service.
     *
     * @test
     * @dataProvider webserviceResponseProvider
     *
     * @magentoConfigFixture default_store postdirekt/addressfactory/automatic_address_analysis 2
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 0
     * @magentoDataFixture createPendingOrders
     * @magentoDataFixture createAnalyzedOrders
     *
     * @param callable $getRecords
     */
    public function analyzedAddressesAreSkipped(callable $getRecords): void
    {
        /** @var AddressVerificationServiceStub $service */
        $service = Bootstrap::getObjectManager()->get(AddressVerificationServiceInterface::class);
        $service->records = $getRecords();

        /** @var AutoProcess $autoProcess */
        $autoProcess = Bootstrap::getObjectManager()->create(AutoProcess::class);
        $autoProcess->execute();

        /** @var AnalysisResultRepository $analysisRepository */
        $analysisRepository = Bootstrap::getObjectManager()->get(AnalysisResultRepository::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);
        $analysisResults = $analysisRepository->getList($searchCriteriaBuilder->create())->getSize();

        self::assertGreaterThan(
            $service->getRequestedRecordsCount(),
            $analysisResults,
            'More records were requested from the web service than exist locally'
        );
    }
}
