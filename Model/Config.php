<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 *
 * @author   Gurjit Singh <gurjit.singh@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class Config
{
    private const CONFIG_PATH_VERSION = 'postdirekt/addressfactory/version';
    private const CONFIG_PATH_MANDATENAME = 'postdirekt/addressfactory/mandatename';
    private const CONFIG_PATH_LOGGING = 'postdirekt/addressfactory/logging';
    private const CONFIG_PATH_LOGLEVEL = 'postdirekt/addressfactory/loglevel';
    private const CONFIG_PATH_SANDBOXMODE = 'postdirekt/addressfactory/sandboxmode';
    private const CONFIG_PATH_ADJUSTMENTSTRENGTH = 'postdirekt/addressfactory/adjustmentstrength';
    private const CONFIG_PATH_CONFIGURATIONNAME = 'postdirekt/addressfactory/configurationname';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Config constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Obtain the module version from config.
     *
     * @return string
     */
    public function getModuleVersion(): string
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_VERSION);
    }

    /**
     * Obtain logging enabled setting from config.
     *
     * @param null $store
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
     * Obtain log level from config.
     *
     * @param null $store
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
     * Obtain sandbox mode setting from config.
     *
     * @param null $store
     * @return string
     */
    public function getSandboxMode($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_SANDBOXMODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain configuration name.
     *
     * @param null $store
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
     * Obtain adjustment strength from config.
     *
     * @param null $store
     * @return string
     */
    public function getAdjustmentStrength($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_ADJUSTMENTSTRENGTH,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain mandate name from config.
     *
     * @param null $store
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
}
