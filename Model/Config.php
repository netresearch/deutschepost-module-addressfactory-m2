<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const CONFIG_PATH_VERSION = 'postdirekt/addressfactory/version';
    private const CONFIG_PATH_MANDATENAME = 'postdirekt/addressfactory/mandate_name';
    private const CONFIG_PATH_LOGGING = 'postdirekt/addressfactory/logging';
    private const CONFIG_PATH_LOGLEVEL = 'postdirekt/addressfactory/log_level';
    private const CONFIG_PATH_SANDBOXMODE = 'postdirekt/addressfactory/sandbox_mode';
    private const CONFIG_PATH_ADJUSTMENTSTRENGTH = 'postdirekt/addressfactory/adjustment_strength';
    private const CONFIG_PATH_CONFIGURATIONNAME = 'postdirekt/addressfactory/configuration_name';

    private const CONFIG_PATH_HOLD_NON_DELIVERABLE_ORDERS = 'postdirekt/addressfactory/hold_non_deliverable_orders';
    private const CONFIG_PATH_HOLD_AUTO_CANCEL_ORDERS = 'postdirekt/addressfactory/auto_cancel_orders';
    private const CONFIG_PATH_HOLD_AUTO_UPDATE_SHIPPING_ADDRESS = 'postdirekt/addressfactory/auto_update_shipping_address';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getModuleVersion(): string
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_VERSION);
    }

    public function isLoggingEnabled(?string $store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOGGING,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getLogLevel(?string $store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOGLEVEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isSandboxMode(?string $store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_SANDBOXMODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getConfigurationName(?string $store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_CONFIGURATIONNAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getAdjustmentStrength(?string $store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_ADJUSTMENTSTRENGTH,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getMandateName(?string $store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_MANDATENAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isHoldNonDeliverableOrders(?string $store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_HOLD_NON_DELIVERABLE_ORDERS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isAutoCancelNonDeliverableOrders(?string $store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_HOLD_AUTO_CANCEL_ORDERS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isAutoUpdateShippingAddress(?string $store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_HOLD_AUTO_UPDATE_SHIPPING_ADDRESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
