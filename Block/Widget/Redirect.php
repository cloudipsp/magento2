<?php

namespace Fondy\Fondy\Block\Widget;

/**
 * Abstract class
 */
use \Magento\Framework\View\Element\Template;


class Redirect extends Template
{
    /**
     * @var \Fondy\Fondy\Model\Fondy
     */
    protected $Config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;

    /**
     * @var string
     */
    protected $_template = 'html/fondy_form.phtml';


    /**
     * @param Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Fondy\Fondy\Model\Fondy $paymentConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Fondy\Fondy\Model\Fondy $paymentConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->_orderConfig = $orderConfig;
        $this->_isScopePrivate = true;
        $this->httpContext = $httpContext;
        $this->Config = $paymentConfig;
    }


    /**
     * Get instructions text from config
     *
     * @return null|string
     */
    public function getGateUrl()
    {
        return $this->Config->getGateUrl();
    }


    /**
     * Получить сумму к оплате
     *
     * @return float|null
     */
    public function getAmount()
    {
    	$orderId = $this->_checkoutSession->getLastOrderId(); 
        if ($orderId) 
        {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
        	return $this->Config->getAmount($incrementId);
    	}
        return null;
    }


    /**
     * Получить данные формы
     *
     * @return array|null
     */
    public function getPostData()
    {
        $orderId = $this->_checkoutSession->getLastOrderId();
        if ($orderId) 
        {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
        	return $this->Config->getPostData($incrementId);
	    }
        return null;
    }


    /**
     * Получить callback URL
     *
     * @return array
     */
    public function getPayUrl()
    {

        $baseUrl = $this->getUrl("fondy/url");
        return "{$baseUrl}fondysuccess";
    }
}
