<?php
/**
 * See LICENSE.md for license details.
 */

namespace PostDirekt\Addressfactory\Test\Integration\TestCase\Model;

use Magento\Store\Api\Data\StoreInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\Config;

class ConfigTest extends TestCase
{
    /**
     * @magentoConfigFixture default_store postdirekt/addressfactory/mandate_name TestMandate
     * @magentoConfigFixture default_store postdirekt/addressfactory/logging 1
     * @magentoConfigFixture default_store postdirekt/addressfactory/log_level 500
     * @magentoConfigFixture default_store postdirekt/addressfactory/configuration_name TestConfigurationName
     * @magentoConfigFixture default_store postdirekt/addressfactory/hold_non_deliverable_orders 1
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_cancel_orders 1
     * @magentoConfigFixture default_store postdirekt/addressfactory/auto_update_shipping_address 1
     * @magentoConfigFixture default_store postdirekt/addressfactory/automatic_address_analysis 2
     */
    public function testGetters(): void
    {
        /** @var Config $subject */
        $subject = Bootstrap::getObjectManager()->create(Config::class);
        /** @var StoreInterface $store */
        $store = Bootstrap::getObjectManager()->create(StoreInterface::class);

        self::assertSame('TestMandate', $subject->getMandateName($store->getCode()));
        self::assertTrue($subject->isLoggingEnabled($store->getCode()));
        self::assertSame('500', $subject->getLogLevel($store->getCode()));
        self::assertSame('TestConfigurationName', $subject->getConfigurationName($store->getCode()));
        self::assertTrue($subject->isHoldNonDeliverableOrders($store->getCode()));
        self::assertTrue($subject->isAutoCancelNonDeliverableOrders($store->getCode()));
        self::assertTrue($subject->isAutoUpdateShippingAddress($store->getCode()));
        self::assertFalse($subject->isManualAnalysisOnly($store->getCode()));
        self::assertTrue($subject->isAnalysisViaCron($store->getCode()));
        self::assertFalse($subject->isAnalysisOnOrderPlace($store->getCode()));
    }

    public function testDefaults(): void
    {
        /** @var Config $subject */
        $subject = Bootstrap::getObjectManager()->create(Config::class);
        /** @var StoreInterface $store */
        $store = Bootstrap::getObjectManager()->create(StoreInterface::class);

        self::assertSame('1.0.0', $subject->getModuleVersion());
        self::assertSame('', $subject->getMandateName($store->getCode()));
        self::assertSame('400', $subject->getLogLevel($store->getCode()));
        self::assertSame('', $subject->getConfigurationName($store->getCode()));
        self::assertFalse($subject->isHoldNonDeliverableOrders($store->getCode()));
        self::assertFalse($subject->isAutoCancelNonDeliverableOrders($store->getCode()));
        self::assertFalse($subject->isAutoUpdateShippingAddress($store->getCode()));
        self::assertTrue($subject->isManualAnalysisOnly($store->getCode()));
    }
}
