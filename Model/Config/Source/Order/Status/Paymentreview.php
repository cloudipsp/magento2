<?php

namespace Fondy\Fondy\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;

/**
 * Order Status source model
 */
class Paymentreview extends Status
{
    /**
     * @var string[]
     */
    protected $_stateStatuses = [Order::STATE_PAYMENT_REVIEW];

    
}
