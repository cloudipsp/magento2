<?php

namespace Fondy\Fondy\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;

class FondySuccess extends Action
{
    /** @var \Magento\Framework\View\Result\PageFactory  */
    protected $resultPageFactory;


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
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
        //load model
		
        /* @var $paymentMethod \Magento\Authorizenet\Model\DirectPost */
        $paymentMethod = $this->_objectManager->create('Fondy\Fondy\Model\Fondy');

        //get request data
        $data = $this->getRequest()->getPostValue();
        if (empty($data)) {
            $callback = json_decode(file_get_contents("php://input"));
            $data = array();
            foreach ($callback as $key => $val) {
                $data[$key] = $val;
            }
        }

        $paymentMethod->processResponse($data);
        //return $this->resultPageFactory->create();
    }

}
