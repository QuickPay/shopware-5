<?php

namespace QuickPayPayment\Components;
use Exception;
use QuickPayPayment\Models\QuickPayPayment;
use QuickPayPayment\Models\QuickPayPaymentOperation;
use Shopware\Components\Logger;
use Shopware\Components\Random;
use Shopware\Models\Customer\Customer;
use function Shopware;
class QuickPayService
{
    private $baseUrl = 'https://api.quickpay.net';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_GET = 'GET';
    const METHOD_PATCH = 'PATCH';
   
    /**
     * @var Shopware\Components\Logger
     */
    private $logger;
    
    public function __construct($logger)
    {
        $this->logger = $logger;
    }
    
    public function log($level, $message, $context = [])
    {
        if(!is_array($context))
            $context = get_object_vars ($context);
        $this->logger->log($level, $message, $context);
    }
    
    /**
     * Create payment
     *
     * @param integer $userId Id of the ordering user
     * @param mixed $basket Basket of the order
     * @param string $currency Short name of the used currency
     * @param integer $amount Amount to pay in cents
     * @return \QuickPayPayment\Models\QuickPayPayment
     */
    public function createPayment($userId, $basket, $amount, $variables, $currency)
    {
        $orderId = $this->createOrderId();
        $parameters = [
            'currency' => $currency,
            'order_id' => $orderId,
            'variables' => $variables
        ];


        $this->log(Logger::DEBUG, 'payment creation requested', $parameters);
        //Create payment
        $paymentData = $this->request(self::METHOD_POST, '/payments', $parameters);
        $this->log(Logger::INFO, 'payment created', $paymentData); 
        
        //Register payment in database 
        $customer = Shopware()->Models()->find(Customer::class, $userId);
        
        $payment = new QuickPayPayment($paymentData->id, $orderId, $customer, $amount);
        
        Shopware()->Models()->persist($payment);
        Shopware()->Models()->flush($payment);
        
        



        $this->handleNewOperation($payment, (object) array(
            'type' => 'create',
            'id' => null,
            'amount' => 0,
            'created_at' => date(),
            'payload' => $paymentData
        ));
 
        return $payment;
    }
    /**
     * Get payment data for orders created with a previous version of the plugin
     * 
     * @param Shopware\Models\Order\Order $order
     */
    public function createPaymentRetroactively($order)
    {
        try {
            $paymentId = $order->getTemporaryId();
            
            $parameters = [];
            $resource = sprintf('/payments/%s', $paymentId);
            //Get payment
            $paymentData = $this->request(self::METHOD_GET, $resource, $parameters);
            
            $payment = new QuickPayPayment($paymentData->id, $paymentData->order_id, $order->getCustomer(), $paymentData->link->amount);
            
            $payment->setLink($paymentData->link->url);
            $payment->setOrderNumber($order->getNumber());
            
            Shopware()->Models()->persist($payment);
            Shopware()->Models()->flush($payment);
            $this->handleNewOperation($payment, (object) array(
                'type' => 'create',
                'id' => null,
                'amount' => 0,
                'created_at' => $paymentData->created_at,
                'payload' => $paymentData
            ));
            
            $this->registerCallback($payment, $paymentData);
            
            return $payment;
        }
        catch(Exception $e)
        {
            return null;
        }
    }
    
    /**
     * Load the payment data through the QuickPay API and update the operations
     * 
     * @param QuickPayPayment $payment
     */
    public function loadPaymentOperations($payment)
    {
        $resource = sprintf('/payments/%s', $payment->getId());
        try{
            //Get payment data
            $paymentData = $this->request(self::METHOD_GET, $resource, []);
            $this->registerCallback($payment, $paymentData);
        }
        catch (Exception $e)
        {
            
        }
    }
    
    /**
     * Update payment
     *
     * @param string $paymentId Id of the QuickPayPayment
     * @param mixed $basket The current basket
     * @param integer $amount Amount to pay in cents
     * @return \QuickPayPayment\Models\QuickPayPayment
     */
    public function updatePayment($paymentId, $basket, $amount, $variables)
    {
        $parameters = [
            'variables' => $variables
        ];
        $resource = sprintf('/payments/%s', $paymentId);
        $this->log(Logger::DEBUG, 'payment update requested', $parameters);
        //Update payment
        $paymentData = $this->request(self::METHOD_PATCH, $resource, $parameters);
        $this->log(Logger::INFO, 'payment updated', $paymentData);
        $payment = $this->getPayment($paymentId);
        //Update amount to pay
        $payment->setAmount($amount);
        Shopware()->Models()->flush($payment);
        return $payment;
    }
    
    /**
     * Get payment by id
     * 
     * @param integer $paymentId Id of the current basket
     * @return \QuickPayPayment\Models\QuickPayPayment
     */
    public function getPayment($paymentId)
    {
        /** @var QuickPayPayment $payment */
        $payment = Shopware()->Models()->find(QuickPayPayment::class, $paymentId);
        if(empty($payment))    
            return null;
        
        return $payment;
    }
    
    /**
     * Register a callback
     * 
     * @param QuickPayPayment $payment the linked payment object
     * @param mixed $data data contained in the request body
     */
    public function registerCallback($payment, $data)
    {
        $operations = $payment->getOperations();
        //Sort Operations by Id
        $operationsById = array();
        /** @var QuickPayPaymentOperation $operation */
        foreach($operations as $operation)
        {
            if($operation->getOperationId() != null)
                $operationsById[$operation->getOperationId()] = $operation;
        }
        //update operations with data from the callback
        foreach($data->operations as $operation)
        {
            if(!isset($operationsById[$operation->id]))
            {
                $operationsById[$operation->id] = $this->handleNewOperation($payment, $operation, false);
            }
            else{
                $operationsById[$operation->id]->update($operation);
            }
        }
        //save changes made to the operations
        Shopware()->Models()->flush($operationsById);
        
        $this->updateStatus($payment);
    }
    /**
     * Create a Quickpay payment operation
     * 
     * @param QuickPayPayment $payment
     * @param mixed $data
     * @param boolean $updateStatus
     * @return QuickPayPaymentOperation
     */
    public function handleNewOperation($payment, $data, $updateStatus = true)
    {
        $operation = new QuickPayPaymentOperation($payment, $data);

        //Persist the new operation
        Shopware()->Models()->persist($operation);
        Shopware()->Models()->flush($operation);

        if($updateStatus)
        {
            $this->updateStatus($payment);
        }
        return $operation;
    }
            
    /**
     * Update the status of the QuickPay payment according to the operations
     * 
     * @param QuickPayPayment $payment
     */
    public function updateStatus($payment)
    {
        $amount = $payment->getAmount();
        $amountAuthorized = 0;
        $amountCaptured = 0;
        $amountRefunded = 0;
        $status = QuickPayPayment::PAYMENT_CREATED;
        $repository = Shopware()->Models()->getRepository(QuickPayPaymentOperation::class);
        $operations = $repository->findBy(['payment' => $payment], ['createdAt' => 'ASC', 'id' => 'ASC']);
        
        
        /** @var QuickPayPaymentOperation $operation */
        foreach($operations as $operation)
        {
            
            switch ($operation->getType())
            {
                case 'authorize':
                    if($operation->isSuccessfull())
                    {
                        $amountAuthorized += $operation->getAmount();
                        if($amount <= $amountAuthorized)
                        {
                            $status = QuickPayPayment::PAYMENT_FULLY_AUTHORIZED;
                        }
                    }
                    break;
                case 'capture_request':
                    
                    $status = QuickPayPayment::PAYMENT_CAPTURE_REQUESTED;
                    break;
                case 'capture':
                    if($operation->isSuccessfull())
                    {
                        $amountCaptured += $operation->getAmount();
                        if($amount <= $amountCaptured)
                        {
                            $status = QuickPayPayment::PAYMENT_FULLY_CAPTURED;
                        }
                        else
                        {
                            $status = QuickPayPayment::PAYMENT_PARTLY_CAPTURED;
                        }
                    }
                    else if($operation->isFinished())
                    {
                        if($amountCaptured > 0)
                        {
                            $status = QuickPayPayment::PAYMENT_PARTLY_CAPTURED;
                        }
                        else
                        {
                            $status = QuickPayPayment::PAYMENT_FULLY_AUTHORIZED;
                        }
                    }
                    break;
                case 'cancel_request':
                    $status = QuickPayPayment::PAYMENT_CANCEL_REQUSTED;
                    break;
                case 'cancel':
                    if($operation->isSuccessfull())
                    {
                        $status = QuickPayPayment::PAYMENT_CANCELLED;
                    }
                    else if($operation->isFinished())
                    {
                        $status = QuickPayPayment::PAYMENT_FULLY_AUTHORIZED;
                    }
                    break;
                case 'refund_request':
                    $status = QuickPayPayment::PAYMENT_REFUND_REQUSTED;
                    break;
                case 'refund':
                    if($operation->isSuccessfull())
                    {
                        $amountRefunded += $operation->getAmount();
                        if($amountCaptured <= $amountRefunded)
                        {
                            $status = QuickPayPayment::PAYMENT_FULLY_REFUNDED;
                        }
                        else
                        {
                            $status = QuickPayPayment::PAYMENT_PARTLY_REFUNDED;
                        }
                    }
                    else
                    {
                        if($amountRefunded > 0)
                        {
                            $status = QuickPayPayment::PAYMENT_PARTLY_REFUNDED;
                        }
                        else
                        {
                            if($amountCaptured < $amount)
                            {
                                $status = QuickPayPayment::PAYMENT_PARTLY_CAPTURED;
                            }
                            else
                            {
                                $status = QuickPayPayment::PAYMENT_FULLY_CAPTURED;
                            }
                        }
                    }
                    break;
                case 'checksum_failure':
                case 'test_mode_violation':
                    $status = QuickPayPayment::PAYMENT_INVALIDATED;
                    break;
                default:
                    break;
            }
        }
        $payment->setAmountAuthorized($amountAuthorized);
        $payment->setAmountCaptured($amountCaptured);
        $payment->setAmountRefunded($amountRefunded);
        $payment->setStatus($status);
        //Save updates to the payment object
        Shopware()->Models()->flush($payment);
    }
    
    /**
     * Register a callback containing a bad checksum
     * 
     * @param QuickPayPayment $payment the linked payment object
     * @param mixed $data data contained in the request body
     */
    public function registerFalseChecksumCallback($payment, $data)
    {        
        $this->handleNewOperation($payment, (object) array(
            'type' => 'checksum_failure',
            'id' => null,
            'amount' => 0,
            'payload' => $data
        ));
    }
    
    /**
     * Register a callback containing wrong test mode settings
     * 
     * @param QuickPayPayment $payment the linked payment object
     * @param mixed $data data contained in the request body
     */
    public function registerTestModeViolationCallback($payment, $data)
    {
        $this->handleNewOperation($payment, (object) array(
            'type' => 'test_mode_violation',
            'id' => null,
            'amount' => 0,
            'payload' => $data
        ));
    }
    
    /**
     * Create payment link
     *
     * @param QuickPayPayment $payment QuickPay payment
     * @param string $paymentMethod
     * @param double $amount invoice amount of the order
     * @param string $email Mail-address of the customer
     * @param string $continueUrl redirect URL in case of success
     * @param string $cancelUrl redirect URL in case of cancellation
     * @param string $callbackUrl URL to send callback to
     *
     * @return string link for QuickPay payment
     */
    public function createPaymentLink($payment,$PaymentMethods, $email, $continueUrl, $cancelUrl, $callbackUrl)
    {
        $resource = sprintf('/payments/%s/link', $payment->getId());
        $parameters = [
            'amount'             => $payment->getAmount(),
            'continueurl'        => $continueUrl,
            'cancelurl'          => $cancelUrl,
            'callbackurl'        => $callbackUrl,
            'customer_email'     => $email,
            'language'           => $this->getLanguageCode(),
            'payment_methods'    => $PaymentMethods
        ];
        $this->log(Logger::DEBUG, 'payment link creation requested', $parameters);
        $paymentLink = $this->request(self::METHOD_PUT, $resource, $parameters);
        $this->log(Logger::INFO, 'payment link created', $paymentLink);
        return $paymentLink->url;
    }
    /**
     * send a capture request to the QuickPay API
     * 
     * @param QuickPayPayment $payment
     * @param integer $amount
     */
    public function requestCapture($payment, $amount)
    {
        if($payment->getStatus() != QuickPayPayment::PAYMENT_FULLY_AUTHORIZED
            && $payment->getStatus() != QuickPayPayment::PAYMENT_PARTLY_CAPTURED)
        {
            throw new Exception('Invalid payment state');
        }
        if($amount <= 0 || $amount > $payment->getAmountAuthorized() - $payment->getAmountCaptured())
        {
            throw new Exception('Invalid amount');
        }
        $operation = $this->handleNewOperation($payment, (object) array(
            'type' => 'capture_request',
            'id' => null,
            'amount' => $amount
        ));
        try
        {
            $resource = sprintf('/payments/%s/capture', $payment->getId());
            $this->log(Logger::DEBUG, 'payment capture requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, [
                    'amount' => $amount
                ], 
                [
                    'QuickPay-Callback-Url' => Shopware()->Front()->Router()->assemble([
                        'controller' => 'QuickPay',
                        'action' => 'callback',
                        'forceSecure' => true,
                        'module' => 'frontend'
                    ])
                ]);
            $this->log(Logger::INFO, 'payment captured', $paymentData);
        }
        catch (Exception $e)
        {
            Shopware()->Models()->remove($operation);
            Shopware()->Models()->flush($operation);
            
            $this->log(Logger::Error, 'exception during capture', ['message' => $ex->getMessage()]);
            
            throw $e;
        }
    }
    /**
     * send a capture request to the QuickPay API
     * 
     * @param QuickPayPayment $payment
     */
    public function requestCancel($payment)
    {
        if($payment->getStatus() != QuickPayPayment::PAYMENT_FULLY_AUTHORIZED
            && $payment->getStatus() != QuickPayPayment::PAYMENT_CREATED
            && $payment->getStatus() != QuickPayPayment::PAYMENT_ACCEPTED)
        {
            throw new Exception('Invalid payment state');
        }
        if($payment->getAmountCaptured() > 0)
        {
            throw new Exception('Payment already (partly) captured');
        }
        $operation = $this->handleNewOperation($payment, (object) array(
            'type' => 'cancel_request',
            'id' => null,
            'amount' => 0
        ));
        try
        {
            $resource = sprintf('/payments/%s/cancel', $payment->getId());
            $this->log(Logger::DEBUG, 'payment cancellation requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, [], 
                [
                    'QuickPay-Callback-Url' => Shopware()->Front()->Router()->assemble([
                        'controller' => 'QuickPay',
                        'action' => 'callback',
                        'forceSecure' => true,
                        'module' => 'frontend'
                    ])
                ]);
            $this->log(Logger::DEBUG, 'payment canceled', $paymentData);
            
        } catch (Exception $ex) {
            Shopware()->Models()->remove($operation);
            Shopware()->Models()->flush($operation);
            
            $this->log(Logger::Error, 'exception during cancellation', ['message' => $ex->getMessage()]);
            
            throw $e;
        }        
    }
    /**
     * send a capture request to the QuickPay API
     * 
     * @param QuickPayPayment $payment
     * @param integer $amount
     */
    public function requestRefund($payment, $amount)
    {
        if($payment->getStatus() != QuickPayPayment::PAYMENT_FULLY_CAPTURED
            && $payment->getStatus() != QuickPayPayment::PAYMENT_PARTLY_CAPTURED
            && $payment->getStatus() != QuickPayPayment::PAYMENT_PARTLY_REFUNDED)
        {
            throw new Exception('Invalid payment state');
        }
        if($amount <= 0 || $amount > $payment->getAmountCaptured() - $payment->getAmountRefunded())
        {
            throw new Exception('Invalid amount');
        }
        $operation = $this->handleNewOperation($payment, (object) array(
            'type' => 'refund_request',
            'id' => null,
            'amount' => $amount
        ));
        try
        {
            
            $resource = sprintf('/payments/%s/refund', $payment->getId());
            $this->log(Logger::DEBUG, 'payment refund requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, [
                    'amount' => $amount
                ], 
                [
                    'QuickPay-Callback-Url' => Shopware()->Front()->Router()->assemble([
                        'controller' => 'QuickPay',
                        'action' => 'callback',
                        'forceSecure' => true,
                        'module' => 'frontend'
                    ])
                ]);
            $this->log(Logger::DEBUG, 'payment refunded', $paymentData);
        } catch (Exception $ex) {
            Shopware()->Models()->remove($operation);
            Shopware()->Models()->flush($operation);
            
            $this->log(Logger::Error, 'exception during refund', ['message' => $ex->getMessage()]);
            
            throw $e;
        }
        
    }
    
    /**
     * Perform API request
     *
     * @param string $method
     * @param $resource
     * @param array $params
     * @param bool $headers
     */
    private function request($method = self::METHOD_POST, $resource, $params = [], $headers = [])
    {
        $ch = curl_init();
        $url = $this->baseUrl . $resource;
        //Set CURL options
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => $this->getHeaders($headers),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => http_build_query($params, '', '&'),
        ];
        curl_setopt_array($ch, $options);
        $this->log(Logger::DEBUG, 'request sent', $options);
        //Get response
        $result = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log(Logger::DEBUG, 'request finished', ['code' => $responseCode, 'response' => $result]);
        curl_close($ch);
        //Validate reponsecode
        if (! in_array($responseCode, [200, 201, 202])) {
            throw new Exception('Invalid gateway response ' . $result);
        }
        $response = json_decode($result);
        //Check for JSON errors
        if (! $response || (json_last_error() !== JSON_ERROR_NONE)) {
            throw new Exception('Invalid json response');
        }
        return $response;
    }
    /**
     * Get CURL headers
     *
     * @param array $headers list of additional headers
     * @return array
     */
    private function getHeaders($headers)
    {
        $result = [
            'Authorization: Basic ' . base64_encode(':' . $this->getApiKey()),
            'Accept-Version: v10',
            'Accept: application/json'
        ];
        foreach ($headers as $key => $value)
        {
            $result[] = $key. ': '. $value;
        }
        return $result;
    }
    /**
     * Get API key from config
     *
     * @return mixed
     */
    private function getApiKey()
    {
        return Shopware()->Config()->getByNamespace('QuickPayPayment', 'public_key');
    }
    /**
     * Get language code
     *
     * @return string
     */
    private function getLanguageCode()
    {
        $locale = Shopware()->Shop()->getLocale()->getLocale();
        return substr($locale, 0, 2);
    }
    
    /**
     * Creates a unique order id
     * 
     * @return string
     */
    public function createOrderId()
    {
        return Random::getAlphanumericString(20);
    }
    
    
}
