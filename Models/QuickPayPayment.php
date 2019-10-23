<?php

namespace QuickPayPayment\Models;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;

/**
 * @ORM\Entity
 * @ORM\Table(name="quickpay_payments")
 */
class QuickPayPayment extends ModelEntity
{
    /**
     * 
     * @param string $paymentId
     * @param string orderId
     * @param Customer $customer
     * @param integer $amount
     */
    public function __construct($paymentId, $orderId, $customer, $amount)
    {
        $this->id = $paymentId;
        $this->orderId = $orderId;
        $this->orderNumber = null;
        $this->customer = $customer;
        $this->createdAt = new DateTime();
        $this->status = self::PAYMENT_CREATED;
        $this->amount = $amount;
        $this->amountAuthorized = 0;
        $this->amountCaptured = 0;
        $this->basketSignature = null;
    }
    
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="string")
     * 
     * @var string Id of the payment
     */
    protected $id;
    
    /**
     * @ORM\Column(type="datetime", name="created_at")
     * 
     * @var \DateTime Date of creation
     */
    protected $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="Shopware\Models\Customer\Customer")
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="id", nullable=false)
     * 
     * @var Customer Customer linked to the order
     */
    protected $customer;

    /**
     * @ORM\Column(type="integer", name="status")
     * 
     * @var integer Status of the payment
     */
    protected $status;
    
    const PAYMENT_CREATED = 0;
    const PAYMENT_ACCEPTED = 1;
    const PAYMENT_FULLY_AUTHORIZED = 5;
    const PAYMENT_CAPTURE_REQUESTED = 10;
    const PAYMENT_FULLY_CAPTURED = 15;
    const PAYMENT_CANCEL_REQUSTED = 20;
    const PAYMENT_CANCELLED = 25;
    const PAYMENT_REFUND_REQUSTED = 30;
    const PAYMENT_REFUNDED = 35;
    
    /**
     * @ORM\Column(name="order_number", nullable=true)
     * 
     * @var string The number of the linked order
     */
    protected $orderNumber;
    
    /**
     * @ORM\Column(name="order_id", type="string")
     * 
     * @var string The Id used by QuickPay as order Id
     */
    protected $orderId;
    
    /**
     * @ORM\Column(name="link", type="string", nullable=true)
     * 
     * @var string link for the payment
     */
    protected $link;
    
    /**
     * @ORM\Column(name="amount", type="integer")
     * 
     * @var integer Amount to pay
     */
    protected $amount;
    
    /**
     * @ORM\Column(name="amount_authorized", type="integer")
     * 
     * @var integer Amount authorized through QuickPay
     */
    protected $amountAuthorized;

    /**
     * @ORM\Column(name="amount_captured", type="integer")
     *
     * @var integer Amount captured through QuickPay
     */
    protected $amountCaptured;
    
    /**
     * @ORM\Column(name="basket_signature", type="string", nullable=true)
     * 
     * @var string signature of the temporary basket
     */
    protected $basketSignature;
    
    /**
     * @ORM\OneToMany(targetEntity="QuickPayPaymentOperation", mappedBy="payment", cascade={"persist"})
     * 
     * @var array List of operations
     */
    protected $operations;
    
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
     * Get the order id used by QuickPay
     * 
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }
    
    /**
     * Get the date of creation
     * 
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
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
    
    /**
     * Get the status of the payment
     * 
     * @return integer
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Set the status of the payment
     * 
     * @param integer $status
     */
    public function setStatus($status)
    {
    $this->status = $status;
    }
    
    /**
     * Get the linked order
     * 
     * @return string|null
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }
    
    /**
     * get the object of the linked order
     * 
     * @return Order
     */
    public function getOrder()
    {
        if($this->orderNumber == null)
        {
            return null;
        }
        
        $rep = Shopware()->Models()->getRepository(Order::class);
        return $rep->findOneBy(['ordernumber' => $this->orderNumber]);
    }
    
    /**
     * Set the number of the linked order
     * 
     * @param string $orderNumber
     */
    public function setOrderNumber($orderNumber)
    {
        $this->orderNumber = $orderNumber;
    }
    
    /**
     * Get the amount authorized to pay
     * 
     * @return integer amount in cents
     */
    public function getAmount()
    {
        return $this->amount;
    }
    
    /**
     * Set the amount to pay
     * 
     * @param integer $amount Amount to pay in cents
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }
    
    /**
     * Get the amount authorized through Quickpay
     * 
     * @return integer amount in cents
     */
    public function getAmountAuthorized()
    {
        return $this->amountAuthorized;
    }
    
    /**
     * Get the amount captured through Quickpay
     * 
     * @return integer amount in cents
     */
    public function getAmountCaptured()
    {
        return $this->amountCaptured;
    }
    
    /**
     * Get the signature of the temporary basket
     * 
     * @return string signature of the basket
     */
    public function getBasketSignature()
    {
        return $this->basketSignature;
    }
    
    /**
     * Set the signature of the temporary basket
     * 
     * @param string $signature the signature of the basket
     */
    public function setBasketSignature($signature)
    {
        $this->basketSignature = $signature;
    }
    
    /**
     * Get the List of linked operations
     * 
     * @return array operations for the payment
     */
    public function getOperations()
    {
        return $this->operations;
    }
    
    /**
     * Get the payment link
     * 
     * @return string
     */
    public function getLink()
    {
        return $this->link;
    }
    
    /**
     * Set the payment link
     * 
     * @param string $link link to pay via QuickPay
     */
    public function setLink($link)
    {
        $this->link = $link;
    }
    
    /**
     * Add value to the amount of authorized payments
     * 
     * @param integer $amount the authorized amount
     */
    public function addAuthorizedAmount($amount)
    {
        $this->amountAuthorized += $amount;
    }
    
    /**
     * Add value to the amount of captured payments
     * 
     * @param integer $amount the captured amount
     */
    public function addCapturedAmount($amount)
    {
        $this->amountCaptured += $amount;
    }
}
