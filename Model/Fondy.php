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
    protected $_isGateway = true;
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
        array $data = [])
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
    public function getAmount($orderId)
    {
        return $this->getOrder($orderId)->getGrandTotal();
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
     * Получить код используемой валюты по orderId заказа
     *
     * @param $orderId
     * @return null|string
     */
    public function getCurrencyCode($orderId)
    {
        return $this->getOrder($orderId)->getBaseCurrencyCode();
    }


    /**
     * Set order state and status
     * (Этот метод вызывается при нажатии на кнопку "Place Order")
     *
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     * @return void
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }


    /**
     * Check whether payment method can be used with selected shipping method
     * (Проверка возможности доставки)
     *
     * @param string $shippingMethod
     * @return bool
     */
    protected function isCarrierAllowed($shippingMethod)
    {
        return strpos($this->getConfigData('allowed_carrier'), $shippingMethod) !== false;
    }


    /**
     * Check whether payment method can be used
     * (Проверка на доступность метода оплаты)
     *
     * @param CartInterface|null $quote
     * @return bool
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
     * Получить адрес платежного шлюза
     *
     * @return string
     */
    public function getGateUrl()
    {
        return $this->_gateUrl;
    }


    /**
     * Получить код проверки целостности данных из конфигурации
     *
     * @return mixed
     */
    public function getDataIntegrityCode()
    {
        return $this->_encryptor->decrypt($this->getConfigData('FONDY_SECRET_KEY'));
    }


    /**
     * Получить массив параметр для формы оплаты
     *
     * @param $orderId
     * @return array
     */
    public function getPostData($orderId)
    {
        $postData = array(
            'order_id' => $orderId . "#" . time(),
            'merchant_id' => $this->getConfigData("FONDY_MERCHANT_ID"),
            'amount' => round(number_format($this->getAmount($orderId), 2, '.', '') * 100),
            'order_desc' => __("Pay order №") . $orderId,
            'product_id' => 'Fondy',
            'server_callback_url' => $this->urlBuilder->getUrl('fondy/url/fondysuccess'),
            'response_url' => $this->urlBuilder->getUrl('checkout/onepage/success'),
            'currency' => $this->getCurrencyCode($orderId)
        );
        if ($this->getConfigData("invoice_before_fraud_review")) {
            $postData['preauth'] = "Y";
        }

        $sign = $this->getSignature($postData, $this->getDataIntegrityCode());
        $postData['signature'] = $sign;

        return $postData;
    }

    /**
     * Проверить данные ответного запроса (callback URL)
     *
     * @param $response
     * @return bool
     */
    private function checkFondyResponse($response)
    {
        $this->_logger->debug("checking parameters");
        foreach (
            [
                "order_id",
                "order_status",
                "signature"
            ]
            as $param) {
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
     * Вызывается при запросе callback URL со стороны Fondy
     *
     * @param $responseData
     */
    public function processResponse($responseData)
    {
        if (empty($responseData))
            die('No Data');
        if ($responseData['product_id'] == 'FondyDirect') {
            $this->_code = 'fondy_direct';
        } elseif ($responseData['product_id'] == 'Fondy') {
            $this->_code = 'fondy';
        } else {
            die(__("FAIL"));
        }
        $debugData = ['response' => $responseData];
        $this->_logger->debug("processResponse", $debugData);

        if ($this->checkFondyResponse($responseData)) {
            list($orderId,) = explode('#', $responseData['order_id']);
            $order = $this->getOrder($orderId);
            $state = $order->getStatus();
            if (!empty($state) && $order && ($this->_processOrder($order, $responseData) === true)) {
                die(__("Ok"));
            }else{
                die(__("FAIL NO ORDER DEBIT METHOD"));
            }
        }
        die(__("FAIL"));
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
            if (round($order->getGrandTotal() * 100) != $response["actual_amount"]) {
                $this->_logger->debug("_processOrder: amount mismatch, order FAILED");
                return false;
            }
            if ($response["order_status"] == 'approved') {
                $this->createTransaction($order, $response);
                $order
                    ->setState($this->getConfigData("order_status"))
                    ->setStatus($order->getConfig()->getStateDefaultStatus($this->getConfigData("order_status")))
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
        }
    }

    /**
     * @param string $field
     * @param null $storeId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
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