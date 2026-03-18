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
    public function __construct(
        DriverInterface $filesystem,
        private string $logEnabledConfigPath,
        private string $logLevelConfigPath,
        private ScopeConfigInterface $scopeConfig,
        ?string $filePath = null,
        ?string $fileName = null
    ) {
        parent::__construct($filesystem, $filePath, $fileName);
    }

    #[\Override]
    public function isHandling(LogRecord $record): bool
    {
        $loggingEnabled = (bool) $this->scopeConfig->getValue($this->logEnabledConfigPath);
        $logLevel = (int) $this->scopeConfig->getValue($this->logLevelConfigPath);

        return $loggingEnabled && $record->level->value >= $logLevel && parent::isHandling($record);
    }
}
