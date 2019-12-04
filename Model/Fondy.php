<?php

namespace Fondy\Fondy\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order;

/**
 * Class Fondy
 * @package Fondy\Fondy\Model
 */
class Fondy extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;
    /**
     * @var bool
     */
    protected $_isGateway = false;
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'fondy';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;
    /**
     * Sidebar payment info block
     *
     * @var string
     */
    protected $_infoBlockType = 'Magento\Payment\Block\Info\Instructions';

    protected $_gateUrl = "https://api.fondy.eu/api/checkout/redirect/";

    protected $_encryptor;

    protected $orderFactory;

    protected $urlBuilder;

    protected $_transactionBuilder;

    protected $_logger;

    protected $_canUseCheckout = true;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $builderInterface,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->orderFactory = $orderFactory;
        $this->urlBuilder = $urlBuilder;
        $this->_transactionBuilder = $builderInterface;
        $this->_encryptor = $encryptor;
        parent::__construct($context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data);
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/fondy.log');
        $this->_logger = new \Zend\Log\Logger();
        $this->_logger->addWriter($writer);
        $this->_gateUrl = 'https://api.fondy.eu/api/checkout/redirect/';
    }


    /**
     * Получить объект Order по его orderId
     *
     * @param $orderId
     * @return Order
     */
    protected function getOrder($orderId)
    {
        return $this->orderFactory->create()->loadByIncrementId($orderId);
    }


    /**
     * Получить сумму платежа по orderId заказа
     *
     * @param $orderId
     * @return float
     */
    public function getAmount($order)
    {
        return $order->getGrandTotal();
    }

    public function getSignature($data, $password, $encoded = true)
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

    /**
     * Получить идентификатор клиента по orderId заказа
     *
     * @param $orderId
     * @return int|null
     */
    public function getCustomerId($orderId)
    {
        return $this->getOrder($orderId)->getCustomerId();
    }


    /**
     * Получить код используемой валюты по $order
     *
     * @param $order
     * @return null|string
     */
    public function getCurrencyCode($order)
    {
        return $order->getBaseCurrencyCode();
    }

    /**
     * Get Merchant Data string
     *
     * @param $order
     * @return mixed
     */
    public function getMerchantDataString($order)
    {
        $addData = $order->getBillingAddress()->getData();
        if(!$addData){
            $addData = $order->getShippigAddress()->getData();
        }

        $addInfo = [
            'Fullname' => $addData['firstname'] . ' ' . $addData['middlename'] . ' ' . $addData['lastname']
        ];
        return $addInfo;
    }

    /**
     * Get Reservation Data string
     *
     * @param $order
     * @return mixed
     */
    public function getReservDataString($order)
    {
        $addData = $order->getBillingAddress()->getData();
        if(!$addData){
            $addData = $order->getShippigAddress()->getData();
        }

        $skuString = '';
        try {
            $orderItems = $order->getAllVisibleItems();
            $countItems = count($orderItems);
            $i = 0;
            foreach ($orderItems as $key => $orderItem) {
                $sku = $orderItem->getData()['sku'];
                if ($countItems > 1) {
                    $skuString .= ++$i === $countItems ? $sku . '' : $sku . ', ';
                } else {
                    $skuString .= $sku;
                }
            }
        } catch (Exception $e) {
            $skuString = "No sku";
            $this->_logger->debug("Cant get products sku");
        }

        $addInfo = [
            'customer_zip' => isset($addData['postcode']) ? $addData['postcode'] : '',
            'customer_name' => $addData['firstname'] . ' ' . $addData['middlename'] . ' ' . $addData['lastname'],
            'customer_address' => isset($addData['street']) ? $addData['street'] : '',
            'customer_state' => isset($addData['region_id']) ? $addData['region_id'] : '',
            'customer_country' => isset($addData['country_id']) ? $addData['country_id'] : '',
            'phonemobile' => isset($addData['telephone']) ? $addData['telephone'] : '',
            'account' => isset($addData['email']) ? $addData['email'] : '',
            'products_sku' => $skuString
        ];

        try {
            $addInfo['Shipping total'] = number_format($order->getShippingAmount(), 2, '.', '');
        } catch (Exception $e) {
            $this->_logger->debug("Can't get products shipping price");
        }

        return $addInfo;
    }


    /**
     * Check whether payment method can be used with selected shipping method
     * @param $shippingMethod
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function isCarrierAllowed($shippingMethod)
    {
        $allowedConfig = $this->getConfigData('allowed_carrier');

        if ($allowedConfig == '' || !$allowedConfig) {
            return true;
        }

        $allow = explode(',', $allowedConfig);
        foreach ($allow as $v) {
            if (preg_match("/{$v}/i", $shippingMethod)) {
                return true;
            }
        }

        return strpos($allowedConfig, $shippingMethod) !== false;
    }


    /**
     * Check whether payment method can be used
     * @param CartInterface|null $quote
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }
        return parent::isAvailable($quote) && $this->isCarrierAllowed(
                $quote->getShippingAddress()->getShippingMethod()
            );
    }


    /**
     * @return string
     */
    public function getGateUrl()
    {
        return $this->_gateUrl;
    }


    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDataIntegrityCode()
    {
        return $this->_encryptor->decrypt($this->getConfigData('FONDY_SECRET_KEY'));
    }


    /**
     * Get form array
     * @param $orderId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPostData($orderId)
    {
        $order = $this->getOrder($orderId);
        $merchant_data = $this->getMerchantDataString($order);
        $reservation_data = $this->getReservDataString($order);
        $email = $order->getCustomerEmail();
        $postData = array(
            'order_id' => $orderId . "#" . time(),
            'merchant_id' => $this->getConfigData("FONDY_MERCHANT_ID"),
            'amount' => round(
                number_format($this->getAmount($order), 2, '.', '') * 100
            ),
            'order_desc' => __("Pay order №") . $orderId,
            'sender_email' => $email,
            'product_id' => 'Fondy',
            'server_callback_url' => $this->urlBuilder->getUrl('fondy/url/fondysuccess'),
            'response_url' => $this->urlBuilder->getUrl('fondy/url/fondyresponse'),
            'currency' => $this->getCurrencyCode($order)
        );
        if (!empty($merchant_data)) {
            $postData['merchant_data'] = json_encode(array($merchant_data));
            $reservation_data['order_id'] = $orderId;
            $reservation_data['order_total'] = number_format($this->getAmount($order), 2, '.', '');
            $postData['reservation_data'] = base64_encode(json_encode($reservation_data));
        }

        if ($this->getConfigData("invoice_before_fraud_review")) {
            $postData['preauth'] = "Y";
        }

        $sign = $this->getSignature($postData, $this->getDataIntegrityCode());
        $postData['signature'] = $sign;

        return $postData;
    }

    /**
     * Checking callback data
     * @param $response
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function checkFondyResponse($response)
    {
        $this->_logger->debug("checking parameters");
        foreach (["order_id", "order_status", "signature"] as $param) {
            if (!isset($response[$param])) {
                $this->_logger->debug("Pay URL: required field \"{$param}\" is missing");
                return false;
            }
        }
        $settings = array(
            'merchant_id' => $this->getConfigData("FONDY_MERCHANT_ID"),
            'secret_key' => $this->getDataIntegrityCode()
        );
        $validated = $this->isPaymentValid($settings, $response);
        if ($validated === true) {
            $this->_logger->debug("Responce - OK");
            return true;
        } else {
            $this->_logger->debug($validated);
            return false;
        }

    }

    /**
     * @param $responseData
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processResponse($responseData)
    {
        if ($responseData['product_id'] == 'FondyDirect') {
            $this->_code = 'fondy_direct';
        } elseif ($responseData['product_id'] == 'Fondy') {
            $this->_code = 'fondy';
        } else {
            return 'FAIL';
        }
        $debugData = ['response' => $responseData];
        $this->_logger->debug("processResponse", $debugData);

        if ($this->checkFondyResponse($responseData)) {

            list($orderId,) = explode('#', $responseData['order_id']);
            $order = $this->getOrder($orderId);
            $state = $order->getStatus();

            if (!empty($state) && $order && ($this->_processOrder($order, $responseData) === true)) {
                return 'OK';
            } else {
                return 'FAIL';
            }
        }
        return 'FAIL';
    }

    /**
     * Метод вызывается при вызове callback URL
     *
     * @param Order $order
     * @param mixed $response
     * @return bool
     */
    protected function _processOrder(Order $order, $response)
    {
        $this->_logger->debug("_processFondy",
            [
                "\$order" => $order,
                "\$response" => $response
            ]);
        try {
            if (round($order->getGrandTotal() * 100) != $response["amount"]) {
                $this->_logger->debug("_processOrder: amount mismatch, order FAILED");
                return false;
            }

            if ($response["order_status"] == 'approved') {
                $this->createTransaction($order, $response);
                $order_status = $this->getConfigData("order_status");
                if ($order_status == 'pending') {
                    //Preevent incorrect status
                    $order_status = 'processing';
                }
                $order->addStatusHistoryComment("Fondy payment id: " . $response['payment_id']);
                $order->addStatusHistoryComment("Fondy order time: " . $response['order_time']);
                $order
                    ->setState($order_status)
                    ->setStatus($order->getConfig()->getStateDefaultStatus($order_status))
                    ->save();
                $this->_logger->debug("_processOrder: order state changed:" . $this->getConfigData("order_status"));
                $this->_logger->debug("_processOrder: order data saved, order OK");
            } else {
                $order
                    ->setState(Order::STATE_CANCELED)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED))
                    ->save();

                $this->_logger->debug("_processOrder: order state not STATE_CANCELED");
                $this->_logger->debug("_processOrder: order data saved, order not approved");
            }
            return true;
        } catch (\Exception $e) {
            $this->_logger->debug("_processOrder exception", $e->getTrace());
            return false;
        }
    }

    public function isPaymentValid($fondySettings, $response)
    {
        if ($fondySettings['merchant_id'] != $response['merchant_id']) {
            return 'An error has occurred during payment. Merchant data is incorrect.';
        }
        if ($response['order_status'] == 'declined') {
            return 'An error has occurred during payment. Order is declined.';
        }

        $responseSignature = $response['signature'];
        if (isset($response['response_signature_string'])) {
            unset($response['response_signature_string']);
        }
        if (isset($response['signature'])) {
            unset($response['signature']);
        }
        if ($this->getSignature($response, $fondySettings['secret_key']) != $responseSignature) {
            return 'An error has occurred during payment. Signature is not valid.';
        }
        return true;
    }

    /**
     * @param null $order
     * @param array $paymentData
     * @return mixed
     */
    public function createTransaction($order = null, $paymentData = array())
    {
        try {
            //get payment object from order object
            $payment = $order->getPayment();

            $payment->setLastTransId($paymentData['payment_id']);
            $payment->setTransactionId($paymentData['payment_id']);
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
            );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);
            //get the object of builder class
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['payment_id'])
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
                )
                ->setFailSafe(true)
                //build method creates the transaction and returns the object
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();

        } catch (\Exception $e) {
            $this->_logger->debug("_processOrder exception", $e->getTrace());
            return false;
        }
    }

    /**
     * @param string $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        if (null === $storeId) {
            $storeId = $storeManager->getStore()->getStoreId();
        }
        $path = 'payment/' . $this->_code . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
