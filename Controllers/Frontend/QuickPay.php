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
            $user = $this->getUser();
            $billing = $user['billingaddress'];

            $paymentId = $this->createPaymentUniqueId();
            $token = $service->createPaymentToken($this->getAmount(), $billing['customernumber']);

            //Save order and grab ordernumber
            $orderNumber = $this->saveOrder($paymentId, $token, \Shopware\Models\Order\Status::PAYMENT_STATE_OPEN);

            //Save orderNumber to session
            Shopware()->Session()->offsetSet('quickpay_order_id', $orderNumber);
            Shopware()->Session()->offsetSet('quickpay_order_token', $token);

            $paymentParameters = [
                'order_id' => $orderNumber,
                'currency' => $this->getCurrencyShortName(),
                'variables' => [
                    'payment_id' => $paymentId,
                    'token' => $token
                ],
            ];

            //Create payment
            $payment = $service->createPayment($paymentParameters);

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

            $repository = Shopware()->Models()->getRepository(\Shopware\Models\Order\Order::class);
            $order = $repository->findOneBy(array(
                'number' => $orderNumber
            ));
            $order->getAttribute()->setQuickpayPaymentLink($paymentLink);
            Shopware()->Models()->flush($order->getAttribute());

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
                        $this->savePaymentStatus($response->variables->payment_id, $response->variables->token, \Shopware\Models\Order\Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                        Shopware()->PluginLogger()->info("Order attempted paid with testcard while testmode was disabled");
                        return;
                    }

                    //Set order as reserved
                    $this->savePaymentStatus($response->variables->payment_id, $response->variables->token, \Shopware\Models\Order\Status::PAYMENT_STATE_RESERVED);
                }
            } else {
                //Cancel order
                $this->savePaymentStatus($response->variables->payment_id, $response->variables->token, \Shopware\Models\Order\Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                Shopware()->PluginLogger()->info('Checksum mismatch');
            }
        }
    }

    /**
     * Handle payment success
     */
    public function successAction()
    {
        //Redirect to finish
        $this->redirect(['controller' => 'checkout', 'action' => 'finish']);

        return;
    }

    /**
     * Handle payment cancel
     */
    public function cancelAction()
    {
        $this->redirect(['controller' => 'checkout', 'action' => 'cancel']);
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