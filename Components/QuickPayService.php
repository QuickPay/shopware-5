<?php

namespace QuickPayPayment\Components;

use Exception;
use QuickPayPayment\Models\QuickPayPayment;
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
     * Create payment
     *
     * @param integer $userId Id of the ordering user
     * @param mixed $basket Basket of the order
     * @param string $currency Short name of the used currency
     * @param integer $amount Amount to pay in cents
     * @return \QuickPayPayment\Models\QuickPayPayment
     */
    public function createPayment($userId, $basket, $amount, $currency)
    {
        $orderId = $this->createOrderId();
        
        $parameters = [
            'currency' => $currency,
            'order_id' => $orderId
        ];
        
        //Create payment
        $paymentData = $this->request(self::METHOD_POST, '/payments', $parameters);

        //Register payment in database 
        $customer = Shopware()->Models()->find(Customer::class, $userId);
        
        $payment = new QuickPayPayment($paymentData->id, $orderId, $customer, $amount);
        
        Shopware()->Models()->persist($payment);
        Shopware()->Models()->flush($payment);
        
        return $payment;
    }

    /**
     * Create payment
     *
     * @param string $paymentId Id of the QuickPayPayment
     * @param mixed $basket The current basket
     * @param integer $amount Amount to pay in cents
     * @return \QuickPayPayment\Models\QuickPayPayment
     */
    public function updatePayment($paymentId, $basket, $amount)
    {
        $parameters = [];
        
        $resource = sprintf('/payments/%s', $paymentId);
        
        //Update payment
        $paymentData = $this->request(self::METHOD_PATCH, $resource, $parameters);

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
        /** @var \QuickPayPayment\Models\QuickPayPaymentOperation $operation */
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
                $operationsById[$operation->id] = $this->handleNewOperation($payment, $operation);
            }
            else{
                $operationsById[$operation->id]->update($operation);
            }
        }
        
        //save changes made to the operations
        Shopware()->Models()->flush($operationsById);
    }
     
    /**
     * Create a Quickpay payment operation and update the payment accordingly
     * 
     * @param QuickPayPayment $payment
     * @param mixed $data
     * @return \QuickPayPayment\Models\QuickPayPaymentOperation
     */
    public function handleNewOperation($payment, $data)
    {
        $operation = new \QuickPayPayment\Models\QuickPayPaymentOperation($payment, $data);
        switch ($operation->getType())
        {
            case 'authorize':
                $payment->addAuthorizedAmount($operation->getAmount());
                
                if($payment->getAmount() <= $payment->getAmountAuthorized())
                {
                    if($payment->getStatus() == QuickPayPayment::PAYMENT_CREATED
                        || $payment->getStatus() == QuickPayPayment::PAYMENT_ACCEPTED)
                    {
                        $payment->setStatus(QuickPayPayment::PAYMENT_FULLY_AUTHORIZED);
                    }
                }
                
                break;
            
            case 'capture':
                $payment->addCapturedAmount($operation->getAmount());

                if($payment->getAmount() <= $payment->getAmountCaptured())
                {
                    if($payment->getStatus() == QuickPayPayment::PAYMENT_FULLY_AUTHORIZED)
                    {
                        $payment->setStatus(QuickPayPayment::PAYMENT_FULLY_CAPTURED);
                    }
                }

                break;
            
            default:
                if($data->accepted && $payment->getStatus() == QuickPayPayment::PAYMENT_CREATED)
                {
                    $payment->setStatus(QuickPayPayment::PAYMENT_ACCEPTED);
                }
        }
        
        //Save updates to the payment object
        Shopware()->Models()->flush($payment);
        //Persist the new operation
        Shopware()->Models()->persist($operation);
        Shopware()->Models()->flush($operation);
        
        return $operation;
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
            'created_at' => date(),
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
            'created_at' => date(),
            'payload' => $data
        ));
    }
    
    /**
     * Create payment link
     *
     * @param QuickPayPayment $payment QuickPay payment
     * @param double $amount invoice amount of the order
     * @param string $email Mail-address of the customer
     * @param string $continueUrl redirect URL in case of success
     * @param string $cancelUrl redirect URL in case of cancellation
     * @param string $callbackUrl URL to send callback to
     *
     * @return string link for QuickPay payment
     */
    public function createPaymentLink($payment, $email, $continueUrl, $cancelUrl, $callbackUrl)
    {
        $resource = sprintf('/payments/%s/link', $payment->getId());
        $paymentLink = $this->request(self::METHOD_PUT, $resource, [
            'amount'             => $payment->getAmount(),
            'continueurl'        => $continueUrl,
            'cancelurl'          => $cancelUrl,
            'callbackurl'        => $callbackUrl,
            'customer_email'     => $email,
            'language'           => $this->getLanguageCode()
        ]);

        return $paymentLink->url;
    }

    /**
     * Perform API request
     *
     * @param string $method
     * @param $resource
     * @param array $params
     * @param bool $synchronized
     */
    private function request($method = self::METHOD_POST, $resource, $params = [], $synchronized = false)
    {
        $ch = curl_init();

        $url = $this->baseUrl . $resource;

        //Set CURL options
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => $this->getHeaders(),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => http_build_query($params, '', '&'),
        ];

        curl_setopt_array($ch, $options);

        //Get response
        $result = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

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
     * @return array
     */
    private function getHeaders()
    {
        return [
            'Authorization: Basic ' . base64_encode(':' . $this->getApiKey()),
            'Accept-Version: v10',
            'Accept: application/json'
        ];
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
