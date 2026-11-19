<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Locale\ResolverInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides the data of the logged-in customer for the "customer" object of the NeuronaptiQ API requests
 *
 * @phpstan-type CustomerData array<string, string|int>
 */
class CustomerDataProvider
{
    public const TYPE_PRIVATE = 'private';
    public const TYPE_BUSINESS = 'business';

    public const GENDER_OTHER = 'other';

    /**
     * Fields required by the API, always sent (with fallback values) even if empty
     */
    private const REQUIRED_FIELDS = ['birthyear', 'email', 'gender', 'id', 'number', 'type'];

    /**
     * Magento gender option ids mapped to API values
     */
    private const GENDER_MAP = [
        1 => 'male',
        2 => 'female',
        3 => self::GENDER_OTHER,
    ];

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var ResolverInterface
     */
    private ResolverInterface $localeResolver;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param ResolverInterface $localeResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        ResolverInterface $localeResolver,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
    }

    /**
     * Returns the customer data of the logged-in customer
     *
     * Returns null for guests or if sending customer data is disabled. The fields required by the API
     * (id, email, type, number, gender, birthyear) are always set, empty optional values are omitted.
     *
     * @return array|null
     * @phpstan-return CustomerData|null
     */
    public function getCustomerData(): ?array
    {
        if (!$this->config->isCustomerDataEnabled() || !$this->customerSession->isLoggedIn()) {
            return null;
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf('[NeuronaptiQ_Search] Customer %d could not be loaded (%s).', $customerId, $e->getMessage()),
                ['exception' => $e]
            );
            return null;
        }

        $address = $this->getPrimaryAddress($customer);
        $company = $address ? trim((string)$address->getCompany()) : '';
        $dob = (string)$customer->getDob();

        $data = [
            'birthyear' => $dob !== '' ? (int)substr($dob, 0, 4) : 0,
            'company' => $company,
            'country' => $address ? (string)$address->getCountryId() : '',
            'email' => (string)$customer->getEmail(),
            'firstname' => (string)$customer->getFirstname(),
            'gender' => self::GENDER_MAP[(int)$customer->getGender()] ?? self::GENDER_OTHER,
            'id' => (string)$customer->getId(),
            'lang' => strtolower(strtok($this->localeResolver->getLocale(), '_') ?: ''),
            'lastname' => (string)$customer->getLastname(),
            'number' => (string)$customer->getId(),
            'title' => trim((string)$customer->getPrefix()),
            'type' => $company !== '' ? self::TYPE_BUSINESS : self::TYPE_PRIVATE,
        ];

        return array_filter(
            $data,
            static fn ($value, string $key) => in_array($key, self::REQUIRED_FIELDS, true) || $value !== '',
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Returns the default billing address, falling back to the default shipping address
     *
     * @param CustomerInterface $customer
     * @return AddressInterface|null
     */
    private function getPrimaryAddress(CustomerInterface $customer): ?AddressInterface
    {
        $shipping = null;
        foreach ($customer->getAddresses() ?? [] as $address) {
            if ($address->isDefaultBilling()) {
                return $address;
            }
            if ($address->isDefaultShipping()) {
                $shipping = $address;
            }
        }

        return $shipping;
    }
}
