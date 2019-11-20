<?php

use Enlight_Components_Session_Namespace;
use QuickPayPayment\Components\QuickPayService;
use QuickPayPayment\Models\QuickPayPayment;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class Shopware_Controllers_Frontend_QuickPay extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /**
     * Instance of the QuickPay service
     * 
     *  @var QuickPayService $service
     */
    private $service;
    
    /**
     * Instance of the Session
     * 
     * @var Enlight_Components_Session_Namespace
     */
    private $session;
    
    public function preDispatch()
    {
        parent::preDispatch();
        $this->service = $this->get('quickpay_payment.quickpay_service');
        $this->session = $this->get('session');
    }
    
    /**
     * Redirect to gateway
     */
    public function redirectAction()
    {

        try {
            
            //Get current payment id if it exists in the session
            $paymentId = $this->session->offsetGet('quickpay_payment_id');
            
            $amount = $this->getAmount() * 100; //Convert to cents
            
            $variables = array(
                'device' => $this->Request()->getDeviceType(),
                'comment' => $this->session->offsetGet('sComment'),
                'dispatchId' => $this->session->offsetGet('sDispatch')
            );
            
            if(empty($paymentId))
            {   
                //Create new payment
                $payment = $this->service->createPayment($this->session->offsetGet('sUserId'), $this->getBasket(), $amount, $variables, $this->getCurrencyShortName());
            }
            else
            {
                //Get the payment associated with the payment id from the session
                $payment = $this->service->getPayment($paymentId);

                //Check if the payment is still in its initial state
                if($payment->getStatus() == QuickPayPayment::PAYMENT_CREATED)
                {
                    //Update existing QuickPay payment
                    $payment = $this->service->updatePayment($paymentId, $this->getBasket(), $amount, $variables);
                }
                else
                {
                    //Create new payment
                    $payment = $this->service->createPayment($this->session->offsetGet('sUserId'), $this->getBasket(), $amount, $variables, $this->getCurrencyShortName());
                }
            }
            
            $signature = $payment->getBasketSignature();
            // Check if basket has previously been persisted
            if(!empty($signature))
            {
                //delete the previously persisted basket
                $persister = $this->get('basket_persister');
                $persister->delete($signature);

            }
            //persist the current basket
            $payment->setBasketSignature($this->persistBasket());
            
            // Save ID to session
            $this->session->offsetSet('quickpay_payment_id', $payment->getId());
            
            $user = $this->getUser();
            $email = $user['additional']['user']['email'];

            //Create payment link
            $paymentLink = $this->service->createPaymentLink(
                $payment,
                $email,
                $this->getContinueUrl(),
                $this->getCancelUrl(),
                $this->getCallbackUrl()
            );
            
            $payment->setLink($paymentLink);
            Shopware()->Models()->flush($payment);
            
            //Redirect to the payment page
            $this->redirect($paymentLink);
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Handle callback
     */
    public function callbackAction()
    {
        $logger = $this->get('pluginlogger');
        
        // Prevent error from missing template
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        //Validate & save order
        $requestBody = $this->Request()->getRawBody();
        $data = json_decode($requestBody);

        //By default return error code
        $responseCode = 400;
        
        if ($data)
        {
            
            $payment = $this->service->getPayment($data->id);
            
            if($payment)
            {
                
                //Get private key & calculate checksum
                $key = Shopware()->Config()->getByNamespace('QuickPayPayment', 'private_key');
                $checksum = hash_hmac('sha256', $requestBody, $key);
                $submittedChecksum = $this->Request()->getServer('HTTP_QUICKPAY_CHECKSUM_SHA256');

                //Validate checksum
                if ($checksum === $submittedChecksum)
                {
                    
                    //Check if the test mode info matches the configured value
                    if ($this->checkTestMode($data))
                    {
                        
                        if(isset($data->variables))
                        {
                            $this->session->offsetSet('sDispatch', $data->variables->dispatchId);
                            $this->session->offsetSet('sComment', $data->variables->comment);
                        }
                        
                        $this->service->registerCallback($payment, $data);
                        
                        //Check if the payment was at least authorized
                        if($payment->getStatus() != QuickPayPayment::PAYMENT_CREATED)
                        {
                            //Make sure the order is persisted
                            $this->checkAndPersistOrder($payment);
                            
                            $this->updateOrderStatus($payment);
                        }
                        
                        $responseCode = 200;
                        
                    }
                    else
                    {
                        
                        //Wrong test mode settings were used
                        $this->service->registerTestModeViolationCallback($payment, $data);
                        if($data->test_mode)
                            $logger->warning('payment with wrong test card attempted', json_decode($requestBody, true));
                        else
                            $logger->warning('payment with real data during test mode', json_decode($requestBody, true));
                        
                    }
                }
                else
                {
                    $this->service->registerFalseChecksumCallback($payment, $data);
                    $logger->warning('Checksum mismatch', json_decode($requestBody, true));
                }
            }
            else
            {
                $logger->info('Unkown payment id', json_decode($requestBody, true));
            }
        }

        $this->Response()->setHttpResponseCode($responseCode);
    }

    /**
     * Handle payment success
     */
    public function successAction()
    {
        $paymentId = $this->session->offsetGet('quickpay_payment_id');
        
        if(empty($paymentId))
        {
            $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);    
            return;
        }

        $payment = $this->service->getPayment($paymentId);
        $this->checkAndPersistOrder($payment, true);
        
        //Remove ID from session
        $this->session->offsetUnset('quickpay_payment_id');
        
        //Redirect to finish
        $this->redirect(['controller' => 'checkout', 'action' => 'finish', 'sUniqueID' => $payment->getId()]);

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
    
    /**
     * Check if the test_mode property of the payment matches the shop configuration
     * 
     * @param mixed $payment
     * @return boolean
     */
    private function checkTestMode($payment)
    {
        //Check is test mode is enabled
        $testmode = Shopware()->Config()->getByNamespace('QuickPayPayment', 'testmode');

        //Check if test_mode property matches the configuration
        return (boolval($testmode) == boolval($payment->test_mode));
    }
    
    /**
     * Checks wether the associated order has been persisted.
     * If not the order will be saved and the temporary entries will be removed.
     * 
     * @param QuickPayPayment $payment
     * @param boolean $removeTemporaryOrder flag to remove the temporary order even if a persisted order already exists
     */
    private function checkAndPersistOrder($payment, $removeTemporaryOrder = false)
    {
        if(empty($payment->getOrderNumber()))
        {
            //Restore the temporary basket
            $this->loadBasketFromSignature($payment->getBasketSignature());

            //Finally persist the order
            $orderNumber = $this->saveOrder($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_OPEN);

            //Update the payment object
            $payment->setOrderNumber($orderNumber);
            $payment->setBasketSignature(null);

            //Save the changes
            Shopware()->Models()->flush($payment);
        }
        else if($removeTemporaryOrder)
        {
            Shopware()->Modules()->Order()->sDeleteTemporaryOrder();
            
            Shopware()->Db()->executeUpdate(
                'DELETE FROM s_order_basket WHERE sessionID=?',
                [$this->session->offsetGet('sessionId')]
            );
            
            if ($this->session->offsetExists('sOrderVariables')) {
                $variables = $this->session->offsetGet('sOrderVariables');
                $variables['sOrderNumber'] = $payment->getOrderNumber();
                $this->session->offsetSet('sOrderVariables', $variables);
            }
        }
    }
    
    /**
     * Check the payment status and update the order accordingly
     * 
     * @param QuickPayPayment $payment
     */
    private function updateOrderStatus($payment)
    {
        switch($payment->getStatus())
        {
            case QuickPayPayment::PAYMENT_FULLY_AUTHORIZED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_RESERVED);
                break;
            case QuickPayPayment::PAYMENT_PARTLY_CAPTURED:
            case QuickPayPayment::PAYMENT_FULLY_CAPTURED:
                if($payment->getAmountCaptured() >= $payment->getOrder()->getInvoiceAmount())
                {
                    $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_COMPLETELY_PAID);
                }
                else
                {
                    $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_PARTIALLY_PAID);
                }
                break;
            case QuickPayPayment::PAYMENT_CANCELLED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                break;
            case QuickPayPayment::PAYMENT_FULLY_REFUNDED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                break;
            case QuickPayPayment::PAYMENT_INVALIDATED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_REVIEW_NECESSARY);
                break;
        }
    }
    
}