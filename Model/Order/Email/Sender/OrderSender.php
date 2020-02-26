<?php

namespace Fondy\Fondy\Model\Order\Email\Sender;

use Magento\Sales\Model\Order;

class OrderSender extends \Magento\Sales\Model\Order\Email\Sender\OrderSender {

    /**
     * Sends order email to the customer.
     *
     * Email will be sent immediately in two cases:
     *
     * - if asynchronous email sending is disabled in global settings
     * - if $forceSyncMode parameter is set to TRUE
     *
     * Otherwise, email will be sent later during running of
     * corresponding cron job.
     *
     * @param Order $order
     * @param bool $forceSyncMode
     * @return bool
     * @throws \Exception
     */
    public function send(Order $order, $forceSyncMode = false, $forceSend = false)
    {
        $payment = $order->getPayment()->getMethodInstance()->getCode();

        if ($payment == 'fondy' && !$forceSend) {
            return false;
        }

        $order->setSendEmail($this->identityContainer->isEnabled());

        if (!$this->globalConfig->getValue('sales_email/general/async_sending') || $forceSyncMode) {
            if ($this->checkAndSend($order)) {
                $order->setEmailSent(true);
                $this->orderResource->saveAttribute($order, ['send_email', 'email_sent']);
                return true;
            }
        } else {
            $order->setEmailSent(null);
            $this->orderResource->saveAttribute($order, 'email_sent');
        }

        $this->orderResource->saveAttribute($order, 'send_email');

        return false;
    }
}
