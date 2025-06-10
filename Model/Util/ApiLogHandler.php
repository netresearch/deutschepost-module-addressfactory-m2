<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\Util;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\LogRecord;

class ApiLogHandler extends Base
{
    /**
     * @var string
     */
    private $logEnabledConfigPath;

    /**
     * @var string
     */
    private $logLevelConfigPath;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        DriverInterface $filesystem,
        string $logEnabledConfigPath,
        string $logLevelConfigPath,
        ScopeConfigInterface $scopeConfig,
        ?string $filePath = null,
        ?string $fileName = null
    ) {
        parent::__construct($filesystem, $filePath, $fileName);

        $this->logEnabledConfigPath = $logEnabledConfigPath;
        $this->logLevelConfigPath = $logLevelConfigPath;
        $this->scopeConfig = $scopeConfig;
    }

    #[\Override]
    public function isHandling(LogRecord $record): bool
    {
        $loggingEnabled = (bool) $this->scopeConfig->getValue($this->logEnabledConfigPath);
        $logLevel = (int) $this->scopeConfig->getValue($this->logLevelConfigPath);

        return $loggingEnabled && $record->level->value >= $logLevel && parent::isHandling($record);
    }
}
