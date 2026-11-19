<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Logger\Handler;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Writes the module's log records to var/log/neuronaptiq.log
 *
 * Debug records (e.g. API requests and responses) are written like Magento's debug.log: only when debug logging is
 * enabled (`bin/magento setup:config:set --enable-debug-logging=true`, default: on except in production mode).
 */
class FileHandler extends Base
{
    /**
     * Deployment config path of Magento's debug logging switch
     */
    private const CONFIG_PATH_DEBUG_LOGGING = 'dev/debug/debug_logging';

    /**
     * @var string
     */
    protected $fileName = '/var/log/neuronaptiq.log';

    /**
     * @var int
     */
    protected $loggerType = \Monolog\Logger::DEBUG;

    /**
     * @var State
     */
    private State $state;

    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    /**
     * @param DriverInterface $filesystem
     * @param State $state
     * @param DeploymentConfig $deploymentConfig
     * @param string|null $filePath
     * @param string|null $fileName
     */
    public function __construct(
        DriverInterface $filesystem,
        State $state,
        DeploymentConfig $deploymentConfig,
        ?string $filePath = null,
        ?string $fileName = null
    ) {
        parent::__construct($filesystem, $filePath, $fileName);
        $this->state = $state;
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * Handles all records from info level, debug records only with debug logging enabled
     *
     * @param LogRecord $record
     * @return bool
     */
    public function isHandling(LogRecord $record): bool
    {
        if ($record->level->isLowerThan(Level::Info) && !$this->isDebugLoggingEnabled()) {
            return false;
        }

        return parent::isHandling($record);
    }

    /**
     * Checks Magento's debug logging switch, like the handler of Magento's debug.log
     *
     * @return bool
     */
    private function isDebugLoggingEnabled(): bool
    {
        if (!$this->deploymentConfig->isAvailable()) {
            return true;
        }

        $value = $this->deploymentConfig->get(self::CONFIG_PATH_DEBUG_LOGGING);
        if ($value === null) {
            return $this->state->getMode() !== State::MODE_PRODUCTION;
        }

        return (bool)$value;
    }
}
