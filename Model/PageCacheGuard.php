<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Framework\App\Response\Http as HttpResponse;

/**
 * Keeps the current response out of the full page cache
 *
 * Used when a request falls back to the Magento search (API error or empty API result, e.g. for bots), so the
 * fallback result is not served from the cache to other visitors. The no-cache headers replace the public headers
 * set by Magento when the layout is generated (built-in cache and Varnish), and the layout is reported as not
 * cacheable (see SearchResultPageCachePlugin).
 * Also used when a new SID cookie is set (see SidManager), the full page cache would strip its Set-Cookie header.
 */
class PageCacheGuard
{
    /**
     * @var HttpResponse
     */
    private HttpResponse $response;

    /**
     * @var bool
     */
    private bool $disabled = false;

    /**
     * @param HttpResponse $response
     */
    public function __construct(HttpResponse $response)
    {
        $this->response = $response;
    }

    /**
     * Excludes the current response from the full page cache
     *
     * @return void
     */
    public function disableCaching(): void
    {
        $this->disabled = true;
        $this->response->setNoCacheHeaders();
    }

    /**
     * Checks whether the current response is excluded from the full page cache
     *
     * @return bool
     */
    public function isCachingDisabled(): bool
    {
        return $this->disabled;
    }
}
