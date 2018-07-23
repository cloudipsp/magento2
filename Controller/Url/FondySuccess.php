<?php

namespace Fondy\Fondy\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;

class FondySuccess extends Action
{
    /** @var \Magento\Framework\View\Result\PageFactory */
    protected $resultPageFactory;


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        $this->resultPageFactory = $resultPageFactory;
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
            if(empty($callback))
                die();
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
        $paymentMethod->processResponse($data);
    }

}
