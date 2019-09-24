<?php

namespace QuickPayPayment\Models;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;
use Shopware\Models\Customer\Customer;

/**
 * @ORM\Entity
 * @ORM\Table(name="s_quickpay_payments")
 */
class QuickPayPayment extends ModelEntity
{
    /**
     * 
     * @param string $id
     * @param Customer $customer
     */
    public function __construct($id, $customer)
    {
        $this->id = $id;
        $this->customer = $customer;
        $this->createdAt = new DateTime();
        $this->paymentAccepted = false;
        $this->orderStatus = self::ORDER_WAITING;
        $this->lastCallback = null;
        $this->firstCallback = null;
    }
    
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="string")
     */
    private $id;
    
    /**
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;
    
    /**
     * @ORM\Column(type="datetime", name="first_callback", nullable=TRUE)
     */
    private $firstCallback;
    
    /**
     * @ORM\Column(type="datetime", name="last_callback", nullable=TRUE)
     */
    private $lastCallback;
    
    /**
     * @ORM\ManyToOne(targetEntity="\Shopware\Models\Customer\Customer")
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="id")
     */
    protected $customer;

    /**
     * @ORM\Column(type="boolean", name="payment_accepted")
     */
    private $paymentAccepted;
            
    /**
     * @ORM\Column(type="integer", name="order_status")
     */
    private $orderStatus;
    
    const ORDER_WAITING = 0;
    const ORDER_FINISHED = 1;
    const ORDER_FAILED = 2;
    
    /**
     * Get the QuickPay payment id
     * 
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Check wether an accepting callback was received
     * 
     * @return bool
     */
    public function isPaymentAccepted()
    {
        return $this->paymentAccepted;
    }

    /**
     * Get when the first callback was received
     * 
     * @return \DateTime|null
     */
    public function getFirstCallback() {
        return $this->firstCallback;
    }
    
    /**
     * Get when the last callback was received
     * 
     * @return \DateTime|null
     */
    public function getLastCallback() {
        return $this->lastCallback;
    }
    
    /**
     * Notify of a received callback
     * 
     * @param bool $accepted true if the payment was accepted
     */
    public function registerCallback($accepted)
    {
        $this->paymentAccepted = $accepted;
        $this->lastCallback = new DateTime();
        if($this->firstCallback === null)
        {
            $this->firstCallback = $this->lastCallback;
        }
    }
    
    /**
     * Notify that the associated order has been finished
     */
    public function registerFinishedOrder()
    {
        $this->orderStatus = self::ORDER_FINISHED;
    }
    
    /**
     * Notify that the associated order was not finished correctly
     */
    public function markAsFailed()
    {
        $this->orderStatus = self::ORDER_FAILED;
    }
    
    /**
     * Get the associated shopware customer
     * 
     * @return Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }
}
