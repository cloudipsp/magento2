<?php

namespace Fondy\Fondy\Api;
/**
 * Class Token
 * @package Fondy\Fondy\Api
 */
class Token implements ApiInterface
{
    /** @var \Magento\Framework\Encryption\EncryptorInterface */
    public $encryptor;

    /** @var \Psr\Log\LoggerInterface */
    public $logger;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    public $scopeConfig;

    /** @var \Magento\Quote\Model\QuoteRepository */
    public $quoteRepository;

    /** @var \Magento\Quote\Model\QuoteIdMaskFactory */
    public $quoteIdMaskFactory;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    public $urlBuilder;
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     *
     */
    public $resultJsonFactory;
    /**
     * @var \Magento\Payment\Helper\Data
     */
    private $paymentData;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_config;

    /**
     * Token constructor.
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Fondy\Fondy\Model\FondyDirect $fondyConfig
     */
    public function __construct(
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Fondy\Fondy\Model\FondyDirect $fondyConfig
    )
    {
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentData = $paymentData;
        $this->_checkoutSession = $checkoutSession;
        $this->_config = $fondyConfig->getConfig();
    }

    /**
     * @inheritdoc
     */
    public function getToken($cartId, $method)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
        $store = $objectManager->get('Magento\Framework\Locale\Resolver');

        $grandTotal = $cart->getQuote()->getGrandTotal();
        $calcAmount = round(number_format($grandTotal, 2, '.', '') * 100);
        $lang = explode('_', $store->getLocale());
        $orderId = $this->_checkoutSession->getQuote()->getReservedOrderId();
        if (empty($orderId)) {
            $this->_checkoutSession->getQuote()->reserveOrderId()->save();
            $orderId = $this->_checkoutSession->getQuote()->getReservedOrderId();
        }
        $sessionToken = $this->_checkoutSession->getTestData();
        
        if (isset($sessionToken['fondy_token']) and
            isset($sessionToken['fondy_amount']) and
            $sessionToken['fondy_amount'] == $calcAmount
        ) {
            $response = [
                'response' => [
                    'response_status' => 'success',
                    'token' => $sessionToken['fondy_token']
                ]
            ];
            return json_encode($response);
        }
        $addData = $cart->getQuote()->getBillingAddress()->getData();
        $addInfo = [
            'email' => $addData['email'],
            'firstname' => $addData['firstname'],
            'middlename' => $addData['middlename'],
            'lastname' => $addData['lastname'],
            'company' => $addData['company'],
            'street' => $addData['street'],
            'city' => $addData['city'],
            'region' => $addData['region']
        ];

        try {
            $decrypted_key = $this->encryptor->decrypt($this->_config['secret_key']);
            $merchant_id = $this->_config['merchant_id'];
            $requestData = [
                'order_id' => $orderId . "#" . time(),
                'merchant_id' => $merchant_id,
                'amount' => $calcAmount,
                'merchant_data' => json_encode($addInfo),
                'lang' => $lang[0],
                'product_id' => 'FondyDirect',
                'order_desc' => __("Pay order №") . $orderId,
                'server_callback_url' => $this->urlBuilder->getUrl('fondy/url/fondysuccess'),
                'response_url' => $this->urlBuilder->getUrl('checkout/onepage/success'),
                'currency' => $this->_checkoutSession->getQuote()->getCurrency()->getBaseCurrencyCode()
            ];
            $sign = $this->getSignature($requestData, $decrypted_key);
            $requestData['signature'] = $sign;

            $answer = $this->doRequest($requestData);

            $token = json_decode($answer, TRUE);

            if (isset($token['response']['token'])) {
                $this->_checkoutSession->setTestData(
                    [
                        'fondy_token' => $token['response']['token'],
                        'fondy_amount' => $requestData['amount']
                    ]
                );
            }
            return $answer;

        } catch (\Exception $e) {
            $this->logger->error(__('Payment capturing error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Magento\Framework\Validator\Exception
     */
    private function doRequest($data)
    {
        try {
            $httpHeaders = new \Zend\Http\Headers();
            $httpHeaders->addHeaders([
                'User-Agent' => 'Magento 2 CMS',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]);
            $request = new \Zend\Http\Request();
            $request->setHeaders($httpHeaders);
            $request->setUri('https://api.fondy.eu/api/checkout/token/');
            $request->setMethod(\Zend\Http\Request::METHOD_POST);

            $params = json_encode(['request' => $data]);
            $request->setContent($params);

            $client = new \Zend\Http\Client();
            $options = [
                'adapter' => 'Zend\Http\Client\Adapter\Curl',
                'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
                'maxredirects' => 1,
                'timeout' => 30
            ];
            $client->setOptions($options);

            $response = $client->send($request);

            return $response->getBody();

        } catch (\Exception $e) {
            $this->logger->error(__('Payment capturing error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }

    }

    /**
     * @param $data
     * @param $password
     * @param bool $encoded
     * @return string
     */
    private function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);
        $str = $password;
        foreach ($data as $k => $v) {
            $str .= '|' . $v;
        }
        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }
}