<?php
namespace Fondy\Fondy\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Symfony\Component\Config\Definition\Exception\Exception;

class FondySuccess extends Action
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
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        parent::__construct($context);
    }


    /**
     * Load the page defined
     *
     * @return \Magento\Framework\View\Result\Page
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
        $result->setData(['Result' => $response]);
        return $result;
    }

}
