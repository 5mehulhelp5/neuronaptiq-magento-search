<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Origin of the API requests: plugin vendor, plugin version and shop version
 *
 * Sent as "origin" with search, suggest, cross-selling and tracking requests and stored in the session of the
 * search service. The plugin version is read from the module's own composer.json (Magento's PackageInfo would
 * read the composer.json of every module), the shop version comes from Magento's product metadata (cached by
 * Magento). Values that cannot be determined are sent as empty string: the API requires all three fields as
 * strings and rejects null with HTTP 422, which would switch off the whole search.
 *
 * @phpstan-type Origin array{pluginVendor: string, pluginVersion: string, shopVersion: string}
 */
class OriginProvider
{
    public const PLUGIN_VENDOR = 'neuronaptiq';

    private const MODULE_NAME = 'NeuronaptiQ_Search';

    /**
     * @var ComponentRegistrarInterface
     */
    private ComponentRegistrarInterface $componentRegistrar;

    /**
     * @var File
     */
    private File $file;

    /**
     * @var Json
     */
    private Json $json;

    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var array|null
     * @phpstan-var Origin|null
     */
    private ?array $origin = null;

    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param File $file
     * @param Json $json
     * @param ProductMetadataInterface $productMetadata
     * @param LoggerInterface $logger
     */
    public function __construct(
        ComponentRegistrarInterface $componentRegistrar,
        File $file,
        Json $json,
        ProductMetadataInterface $productMetadata,
        LoggerInterface $logger
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->file = $file;
        $this->json = $json;
        $this->productMetadata = $productMetadata;
        $this->logger = $logger;
    }

    /**
     * Returns the origin object of the API requests
     *
     * @return array
     * @phpstan-return Origin
     */
    public function getOrigin(): array
    {
        if ($this->origin === null) {
            $this->origin = [
                'pluginVendor' => self::PLUGIN_VENDOR,
                'pluginVersion' => $this->readPluginVersion(),
                'shopVersion' => $this->readShopVersion(),
            ];
        }

        return $this->origin;
    }

    /**
     * Reads the version from the module's composer.json
     *
     * @return string
     */
    private function readPluginVersion(): string
    {
        try {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);
            if ($path === null) {
                return '';
            }

            $composer = $this->json->unserialize($this->file->fileGetContents($path . '/composer.json'));
            $version = is_array($composer) ? ($composer['version'] ?? null) : null;

            return is_string($version) ? $version : '';
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf('[NeuronaptiQ_Search] Plugin version could not be read (%s).', $e->getMessage())
            );

            return '';
        }
    }

    /**
     * Reads the Magento version, e.g. "2.4.8-p5"
     *
     * @return string
     */
    private function readShopVersion(): string
    {
        try {
            return (string)$this->productMetadata->getVersion();
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf('[NeuronaptiQ_Search] Shop version could not be read (%s).', $e->getMessage())
            );

            return '';
        }
    }
}
