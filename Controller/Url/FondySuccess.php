<?php

namespace Fondy\Fondy\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class FondySuccess extends Action implements CsrfAwareActionInterface
{
    /** @var \Magento\Framework\View\Result\PageFactory */
    protected $resultPageFactory;
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * FondySuccess constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
     *
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        parent::__construct($context);
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * Load the page defined
     */
    public function execute()
    {
        //get request data
        $data = $this->getRequest()->getPostValue();
        if (empty($data)) {
            $callback = json_decode(file_get_contents("php://input"));
            if (empty($callback))
                throw new Exception(__('Request Parameter is not matched.'));
            $data = array();
            foreach ($callback as $key => $val) {
                $data[$key] = $val;
            }
        }
        /**
         * $paymentMethod
         */
        $model = 'Fondy\Fondy\Model\Fondy';
        $paymentMethod = $this->_objectManager->create($model);
        $response = $paymentMethod->processResponse($data);
        $result = $this->jsonResultFactory->create();
        $result->setData(['result' => $response]);
        return $result;
    }

}
