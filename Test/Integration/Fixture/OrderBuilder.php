<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Fixture;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Sales\OrderBuilder as TddWizard;

/**
 * Builder to be used by fixtures
 */
class OrderBuilder
{
    /**
     * @var string
     */
    private $status;

    public function __construct(
        private TddWizard $builder,
        private AnalysisResultRepository $resultRepository,
        private AnalysisStatusUpdater $statusUpdater
    ) {
        $this->status = '';
    }

    public static function anOrder(?ObjectManagerInterface $objectManager = null): OrderBuilder
    {
        if ($objectManager === null) {
            $objectManager = Bootstrap::getObjectManager();
        }

        $address = AddressBuilder::anAddress()
            ->withCity('Berlin')
            ->withPostcode('10115')
            ->withStreet('Friedrichstrasse 1')
            ->withTelephone('+49 30 1234567')
            ->asDefaultBilling()
            ->asDefaultShipping();

        return new self(
            TddWizard::anOrder()->withCustomer(CustomerBuilder::aCustomer()->withAddresses($address)),
            $objectManager->create(AnalysisResultRepository::class),
            $objectManager->create(AnalysisStatusUpdater::class)
        );
    }

    public function withProducts(ProductBuilder ...$productBuilders): OrderBuilder
    {
        $builder = clone $this;
        $builder->builder = $builder->builder->withProducts(...$productBuilders);

        return $builder;
    }

    public function withCustomer(CustomerBuilder $customerBuilder): OrderBuilder
    {
        $builder = clone $this;
        $builder->builder = $builder->builder->withCustomer($customerBuilder);

        return $builder;
    }

    public function withCart(CartBuilder $cartBuilder): OrderBuilder
    {
        $builder = clone $this;
        $builder->builder = $builder->builder->withCart($cartBuilder);

        return $builder;
    }

    public function withShippingMethod(string $shippingMethod): OrderBuilder
    {
        $builder = clone $this;
        $builder->builder = $builder->builder->withShippingMethod($shippingMethod);

        return $builder;
    }

    public function withPaymentMethod(string $paymentMethod): OrderBuilder
    {
        $builder = clone $this;
        $builder->builder = $builder->builder->withPaymentMethod($paymentMethod);

        return $builder;
    }

    public function withAnalysisStatus(string $status): OrderBuilder
    {
        $builder = clone $this;
        $builder->status = $status;

        return $builder;
    }

    /**
     * @return OrderInterface
     * @throws \Exception
     */
    public function build(): OrderInterface
    {
        $order = $this->builder->build();

        switch ($this->status) {
            case AnalysisStatusUpdater::ANALYSIS_FAILED:
                $this->statusUpdater->setStatusAnalysisFailed((int)$order->getEntityId());
                break;
            case AnalysisStatusUpdater::DELIVERABLE:
                $shippingAddress = $order->getShippingAddress();
                $analysisResult = Bootstrap::getObjectManager()->create(
                    AnalysisResultInterface::class,
                    [
                        'data' => [
                            AnalysisResultInterface::ORDER_ADDRESS_ID => (int)$shippingAddress->getEntityId(),
                            AnalysisResultInterface::FIRST_NAME => 'Colin',
                            AnalysisResultInterface::LAST_NAME => 'Correct',
                            AnalysisResultInterface::CITY => 'Goodinborough',
                            AnalysisResultInterface::POSTAL_CODE => '11111',
                            AnalysisResultInterface::STREET => 'Gutenberg Ave.',
                            AnalysisResultInterface::STREET_NUMBER => '1',
                            AnalysisResultInterface::STATUS_CODE => 'PDC050105',
                        ]
                    ]
                );
                $this->resultRepository->save($analysisResult);
                $this->statusUpdater->setStatusDeliverable((int)$order->getEntityId());
                break;
            case AnalysisStatusUpdater::UNDELIVERABLE:
                $shippingAddress = $order->getShippingAddress();
                $analysisResult = Bootstrap::getObjectManager()->create(
                    AnalysisResultInterface::class,
                    [
                        'data' => [
                            AnalysisResultInterface::ORDER_ADDRESS_ID => (int)$shippingAddress->getEntityId(),
                            AnalysisResultInterface::FIRST_NAME => 'Uncle',
                            AnalysisResultInterface::LAST_NAME => 'Undeliverable',
                            AnalysisResultInterface::CITY => 'Badminton',
                            AnalysisResultInterface::POSTAL_CODE => '99999',
                            AnalysisResultInterface::STREET => 'Zea Drive',
                            AnalysisResultInterface::STREET_NUMBER => '9017 A',
                            AnalysisResultInterface::STATUS_CODE => 'BAC000111',
                        ]
                    ]
                );
                $this->resultRepository->save($analysisResult);
                $this->statusUpdater->setStatusUndeliverable((int)$order->getEntityId());
                break;
            default:
                $this->statusUpdater->setStatusPending((int)$order->getEntityId());
        }

        return $order;
    }
}
