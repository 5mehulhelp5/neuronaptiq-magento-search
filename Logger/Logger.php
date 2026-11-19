<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Logger;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Logger of the module: writes to the own log file or to the Magento logger, depending on the configuration
 *
 * - Own log file (default): var/log/neuronaptiq.log (virtual type NeuronaptiQ\Search\Logger\FileLogger)
 * - Magento logger: system.log/debug.log and all handlers configured for the Magento logger, e.g. on cloud
 *   setups that forward the Magento logs to a central log service
 *
 * The switch is read from the default scope (config path neuronaptiq_search/general/separate_log_file).
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    public const XML_PATH_SEPARATE_LOG_FILE = 'neuronaptiq_search/general/separate_log_file';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $magentoLogger;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $fileLogger;

    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $magentoLogger
     * @param LoggerInterface $fileLogger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $magentoLogger,
        LoggerInterface $fileLogger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->magentoLogger = $magentoLogger;
        $this->fileLogger = $fileLogger;
    }

    /**
     * @inheritdoc
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
    }

    /**
     * Returns the logger selected by the configuration
     *
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = $this->scopeConfig->isSetFlag(self::XML_PATH_SEPARATE_LOG_FILE)
                ? $this->fileLogger
                : $this->magentoLogger;
        }

        return $this->logger;
    }
}
