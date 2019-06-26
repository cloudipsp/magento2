<?php

namespace Fondy\Fondy\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Submit extends Action implements CsrfAwareActionInterface
{
    /** @var \Magento\Framework\View\Result\PageFactory */
    public $resultPageFactory;
    /**
     * @var \Fondy\Fondy\Block\Widget\Redirect
     */
    public $fondy;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Fondy\Fondy\Block\Widget\Redirect $fondy_form
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->fondy = $fondy_form;
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $post_data = $this->fondy->getPostData();
        $request = $this->doRequest($post_data);
        $url = json_decode($request, true);

        if (isset($url['response']['checkout_url'])) {
            return $this->_redirect->redirect($this->_response, $url['response']['checkout_url']);
        } else {
            $this->restoreCart();
        }

        return $page;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
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
            $request->setUri('https://api.fondy.eu/api/checkout/url/');
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
     * Restore cart data
     */
    public function restoreCart()
    {
        $lastQuoteId = $this->_checkoutSession->getLastQuoteId();
        if ($quote = $this->_objectManager->get('Magento\Quote\Model\Quote')->loadByIdWithoutStore($lastQuoteId)) {
            $quote->setIsActive(true)
                ->setReservedOrderId(null)
                ->save();
            $this->_checkoutSession->setQuoteId($lastQuoteId);
        }
        $message = __('Payment failed. Please try again.');
        $this->messageManager->addError($message);
    }
}
