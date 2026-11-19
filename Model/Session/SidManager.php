<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Session;

use Magento\Framework\Session\Config\ConfigInterface as SessionConfig;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use NeuronaptiQ\Search\Model\PageCacheGuard;
use Psr\Log\LoggerInterface;

/**
 * Manages storage and retrieval of the NeuronaptiQ Search Session ID (SID) in a session cookie
 *
 * The SID is not stored in the Magento session: on cacheable pages (e.g. the search result page) Magento
 * depersonalizes and closes the session before the search runs, so the SID would be lost there.
 *
 * A response that sets a new SID cookie is excluded from the full page cache: the built-in cache and Varnish strip
 * Set-Cookie headers from cacheable responses (e.g. search result pages of popular search terms), so the visitor
 * would start a new session in the search service with every such page.
 */
class SidManager
{
    public const COOKIE_NAME = 'neuronaptiq_sid';

    /**
     * Format of the SID as defined by the API
     */
    private const SID_PATTERN = '/^[a-f0-9]{24}$/';

    /**
     * @var CookieManagerInterface
     */
    private CookieManagerInterface $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private CookieMetadataFactory $cookieMetadataFactory;

    /**
     * @var SessionConfig
     */
    private SessionConfig $sessionConfig;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var PageCacheGuard
     */
    private PageCacheGuard $pageCacheGuard;

    /**
     * SID of the current request, set cookies are only readable with the next request
     *
     * @var string|null
     */
    private ?string $sid = null;

    /**
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionConfig $sessionConfig
     * @param LoggerInterface $logger
     * @param PageCacheGuard $pageCacheGuard
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SessionConfig $sessionConfig,
        LoggerInterface $logger,
        PageCacheGuard $pageCacheGuard
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionConfig = $sessionConfig;
        $this->logger = $logger;
        $this->pageCacheGuard = $pageCacheGuard;
    }

    /**
     * Retrieve the stored SID, or empty string if not present or invalid
     *
     * @return string
     */
    public function getSid(): string
    {
        if ($this->sid === null) {
            $cookieSid = (string)$this->cookieManager->getCookie(self::COOKIE_NAME, '');
            $this->sid = $this->isValid($cookieSid) ? $cookieSid : '';
        }

        return $this->sid;
    }

    /**
     * Persist the returned SID in the session cookie
     *
     * The response is excluded from the full page cache, otherwise the cookie would not reach the browser.
     *
     * @param string $sid
     * @return void
     */
    public function setSid(string $sid): void
    {
        $cleanSid = trim($sid);
        if (!$this->isValid($cleanSid) || $cleanSid === $this->getSid()) {
            return;
        }

        $this->sid = $cleanSid;

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath($this->sessionConfig->getCookiePath())
            ->setHttpOnly(true)
            ->setSecure($this->sessionConfig->getCookieSecure())
            ->setSameSite('Lax');
        $domain = (string)$this->sessionConfig->getCookieDomain();
        if ($domain !== '') {
            $metadata->setDomain($domain);
        }

        try {
            $this->cookieManager->setPublicCookie(self::COOKIE_NAME, $cleanSid, $metadata);
            $this->pageCacheGuard->disableCaching();
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf('[NeuronaptiQ_Search] SID cookie could not be set (%s).', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    /**
     * Discards the SID, the next request starts a new session in the search service
     *
     * Used on customer logout: otherwise the searches, carts and orders after the logout (e.g. as guest or as
     * another customer) would end up in the session of the previous customer.
     *
     * @return void
     */
    public function reset(): void
    {
        if ($this->getSid() === '' && $this->cookieManager->getCookie(self::COOKIE_NAME) === null) {
            return;
        }

        $this->sid = '';

        $metadata = $this->cookieMetadataFactory->createCookieMetadata()
            ->setPath($this->sessionConfig->getCookiePath());
        $domain = (string)$this->sessionConfig->getCookieDomain();
        if ($domain !== '') {
            $metadata->setDomain($domain);
        }

        try {
            $this->cookieManager->deleteCookie(self::COOKIE_NAME, $metadata);
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf('[NeuronaptiQ_Search] SID cookie could not be deleted (%s).', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    /**
     * Checks the SID format
     *
     * @param string $sid
     * @return bool
     */
    private function isValid(string $sid): bool
    {
        return (bool)preg_match(self::SID_PATTERN, $sid);
    }
}
