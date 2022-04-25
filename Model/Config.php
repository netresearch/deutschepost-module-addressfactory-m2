<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PostDirekt\Addressfactory\Model\Config\Source\AutomaticOptions;

class Config
{
    private const CONFIG_PATH_VERSION = 'postdirekt/addressfactory/version';
    private const CONFIG_PATH_MANDATENAME = 'postdirekt/addressfactory/mandate_name';
    private const CONFIG_PATH_CONFIGURATIONNAME = 'postdirekt/addressfactory/configuration_name';

    private const CONFIG_PATH_HOLD_NON_DELIVERABLE_ORDERS = 'postdirekt/addressfactory/hold_non_deliverable_orders';
    private const CONFIG_PATH_HOLD_AUTO_CANCEL_ORDERS = 'postdirekt/addressfactory/auto_cancel_orders';
    private const CONFIG_PATH_HOLD_AUTO_UPDATE_SHIPPING_ADDRESS = 'postdirekt/addressfactory/auto_update_shipping_address';
    private const CONFIG_PATH_AUTOMATIC_ADDRESS_ANALYSE = 'postdirekt/addressfactory/automatic_address_analysis';

    public const CONFIG_PATH_LOGGING = 'postdirekt/addressfactory/logging';
    public const CONFIG_PATH_LOGLEVEL = 'postdirekt/addressfactory/log_level';
    public const CONFIG_PATH_AUTO_VALIDATE_MANUALLY_EDITED = 'postdirekt/addressfactory/auto_validate_manual_edited';

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

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isLoggingEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOGGING,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return string
     */
    public function getLogLevel($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOGLEVEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return string
     */
    public function getConfigurationName($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_CONFIGURATIONNAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return string
     */
    public function getMandateName($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_MANDATENAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isHoldNonDeliverableOrders($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_HOLD_NON_DELIVERABLE_ORDERS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isAutoCancelNonDeliverableOrders($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_HOLD_AUTO_CANCEL_ORDERS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isAutoUpdateShippingAddress($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_HOLD_AUTO_UPDATE_SHIPPING_ADDRESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return string
     */
    private function getAutoAddressAnalysis($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_AUTOMATIC_ADDRESS_ANALYSE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isManualAnalysisOnly($store = null): bool
    {
        return AutomaticOptions::NO_AUTOMATIC_ANALYSIS === $this->getAutoAddressAnalysis($store);
    }

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isAnalysisViaCron($store = null): bool
    {
        return AutomaticOptions::ANALYSIS_VIA_CRON === $this->getAutoAddressAnalysis($store);
    }

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isAnalysisOnOrderPlace($store = null): bool
    {
        return AutomaticOptions::ON_ORDER_PLACE === $this->getAutoAddressAnalysis($store);
    }

    /**
     * @param string|int|null $store
     * @return bool
     */
    public function isAutoValidateManuallyEdited($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_AUTO_VALIDATE_MANUALLY_EDITED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
