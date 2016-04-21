<?php
/**
 * Currency rate import model (From fixer.io)
 */
namespace Philwinkle\Fixerio\Model;

class Import extends \Magento\Directory\Model\Currency\Import\AbstractImport
{
    /**
     * @var string
     */
    const CURRENCY_CONVERTER_URL = 'http://api.fixer.io/latest?base=%1$s&symbols=%2$s';

    /**
     * HTTP client
     *
     * @var \Magento\Framework\HTTP\ZendClient
     */
    protected $_httpClient;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($currencyFactory);
        $this->_scopeConfig = $scopeConfig;
        $this->_httpClient = new \Magento\Framework\HTTP\ZendClient();
    }

    /**
     * @param string $currencyFrom
     * @param string $currencyTo
     * @param int $retry
     * @return float|null
     */
    protected function _convert($currencyFrom, $currencyTo, $retry = 0)
    {
        $url = sprintf(self::CURRENCY_CONVERTER_URL, $currencyFrom, $currencyTo);

        try {
            $response = $this->_httpClient->setUri(
                $url
            )->setConfig(
                [
                    'timeout' => $this->_scopeConfig->getValue(
                        'currency/fixerio/timeout',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                ]
            )->request(
                'GET'
            )->getBody();

            $converted = json_decode($response);
            $rate = $converted->rates->$currencyTo;

            if (!$rate) {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
                return null;
            }

            //support for preserving the precision
            if (function_exists('bcadd')) {
                return bcadd($rate, '0', 12);
            }

            return (double)$xml;
        } catch (\Exception $e) {
            if ($retry == 0) {
                $this->_convert($currencyFrom, $currencyTo, 1);
            } else {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
                return null;
            }
        }
    }
}
