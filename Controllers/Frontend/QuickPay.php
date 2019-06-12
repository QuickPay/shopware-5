<?php
use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_QuickPay extends \Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /**
     * Redirect to gateway
     */
    public function redirectAction()
    {
        /** @var \QuickPayPayment\Components\QuickPayService $service */
        $service = $this->container->get('quickpay_payment.quickpay_service');

        try {
            $paymentParameters = [
                'currency' => $this->getCurrencyShortName(),
            ];
            
            //Save order and grab ordernumber
            $paymentId = Shopware()->Session()->offsetGet('quickpay_payment_id');
            if(empty($paymentId))
            {
                //Create new QuickPay payment
                $orderId = $service->createOrderId();
                
                $payment = $service->createPayment($orderId, $paymentParameters);
            }
            else
            {
                //Update existing QuickPay payment
                $payment = $service->updatePayment($paymentId, $paymentParameters);
            }
            
            // Save ID to session
            Shopware()->Session()->offsetSet('quickpay_payment_id', $payment->id);
            
            $user = $this->getUser();
            $email = $user['additional']['user']['email'];

            //Create payment link
            $paymentLink = $service->createPaymentLink(
                $payment->id,
                $this->getAmount(),
                $email,
                $this->getContinueUrl(),
                $this->getCancelUrl(),
                $this->getCallbackUrl()
            );
            
            $this->redirect($paymentLink);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Handle callback
     */
    public function callbackAction()
    {
        //Validate & save order
        $responseBody = $this->Request()->getRawBody();
        $response = json_decode($responseBody);

        if ($response) {
            //Get private key & calculate checksum
            $key = Shopware()->Config()->getByNamespace('QuickPayPayment', 'private_key');
            $checksum = hash_hmac('sha256', $responseBody, $key);
            $submittedChecksum = $this->Request()->getServer('HTTP_QUICKPAY_CHECKSUM_SHA256');

            //Validate checksum
            if ($checksum === $submittedChecksum) {
                //Check if payment is accepted
                if ($response->accepted === true) {

                    //Check is test mode is enabled
                    $testmode = Shopware()->Config()->getByNamespace('QuickPayPayment', 'testmode');

                    //Cancel order if testmode is disabled and payment is test mode
                    if (!$testmode && ($response->test_mode === true)) {

                        //Set order as cancelled
                        $this->savePaymentStatus($response->order_id, $response->id, \Shopware\Models\Order\Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                        Shopware()->PluginLogger()->info("Order attempted paid with testcard while testmode was disabled");
                        return;
                    }

                    //Set order as reserved
                    $this->savePaymentStatus($response->order_id, $response->id, \Shopware\Models\Order\Status::PAYMENT_STATE_RESERVED);
                }
            } else {
                //Cancel order
                $this->savePaymentStatus($response->order_id, $response->id, \Shopware\Models\Order\Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                Shopware()->PluginLogger()->info('Checksum mismatch');
            }
        }
    }

    /**
     * Handle payment success
     */
    public function successAction()
    {
        /** @var \QuickPayPayment\Components\QuickPayService $service */
        $service = $this->container->get('quickpay_payment.quickpay_service');
        
        $paymentId = Shopware()->Session()->offsetGet('quickpay_payment_id');
        
        if(empty($paymentId))
        {
            $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);    
            return;
        }
        
        $payment = $service->getPayment($paymentId);
        if(empty($payment) || !isset($payment->order_id))
        {
            $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);    
            return;
        }
        
        $orderNumber = $this->saveOrder($payment->order_id, $payment->id, \Shopware\Models\Order\Status::PAYMENT_STATE_OPEN);
        
        $repository = Shopware()->Models()->getRepository(\Shopware\Models\Order\Order::class);
        $order = $repository->findOneBy(array(
            'number' => $orderNumber
        ));
        $order->getAttribute()->setQuickpayPaymentLink($payment->link->url);
        Shopware()->Models()->flush($order->getAttribute());
        
        //Remove ID from session
        Shopware()->Session()->offsetUnset('quickpay_payment_id');
        
        //Redirect to finish
        $this->redirect(['controller' => 'checkout', 'action' => 'finish']);

        return;
    }

    /**
     * Handle payment cancel
     */
    public function cancelAction()
    {
        $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);
    }

    /**
     * Get continue url
     *
     * @return mixed|string
     */
    private function getContinueUrl()
    {
        return $this->Front()->Router()->assemble([
            'controller' => 'QuickPay',
            'action' => 'success',
            'forceSecure' => true
        ]);
    }

    /**
     * Get cancel url
     *
     * @return mixed|string
     */
    private function getCancelUrl()
    {
        return $this->Front()->Router()->assemble([
            'controller' => 'QuickPay',
            'action' => 'cancel',
            'forceSecure' => true
        ]);
    }

    /**
     * Get callback url
     *
     * @return mixed|string
     */
    private function getCallbackUrl()
    {
        return $this->Front()->Router()->assemble([
            'controller' => 'QuickPay',
            'action' => 'callback',
            'forceSecure' => true
        ]);
    }

    /**
     * Returns a list with actions which should not be validated for CSRF protection
     *
     * @return string[]
     */
    public function getWhitelistedCSRFActions() {
        return ['callback'];
    }
}