<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Plugin;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Layout;
use NeuronaptiQ\Search\Model\Config;
use NeuronaptiQ\Search\Model\PageCacheGuard;

/**
 * Excludes responses from the full page cache that must not be served to other visitors
 *
 * - Search result pages of logged-in customers when customer data is sent: the results may be personalized for
 *   the customer, but the full page cache only varies by customer group. Marking the layout as not cacheable also
 *   prevents Magento from depersonalizing the customer session before the search is executed, so the customer
 *   data is available for the API request.
 * - Responses that fell back to the Magento search (see PageCacheGuard).
 */
class SearchResultPageCachePlugin
{
    private const SEARCH_RESULT_ACTION = 'catalogsearch_result_index';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var PageCacheGuard
     */
    private PageCacheGuard $pageCacheGuard;

    /**
     * @var HttpContext
     */
    private HttpContext $httpContext;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @param Config $config
     * @param HttpContext $httpContext
     * @param HttpRequest $request
     * @param PageCacheGuard $pageCacheGuard
     */
    public function __construct(
        Config $config,
        HttpContext $httpContext,
        HttpRequest $request,
        PageCacheGuard $pageCacheGuard
    ) {
        $this->config = $config;
        $this->httpContext = $httpContext;
        $this->request = $request;
        $this->pageCacheGuard = $pageCacheGuard;
    }

    /**
     * Marks the layout as not cacheable after a fallback and search result pages of logged-in customers
     *
     * @param Layout $subject
     * @param bool $result
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsCacheable(Layout $subject, bool $result): bool
    {
        if (!$result || $this->pageCacheGuard->isCachingDisabled()) {
            return false;
        }

        if ($this->request->getFullActionName() !== self::SEARCH_RESULT_ACTION) {
            return $result;
        }

        if (!$this->config->isSearchEnabled() || !$this->config->isCustomerDataEnabled()) {
            return $result;
        }

        return !$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }
}
