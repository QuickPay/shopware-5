<?php

namespace QuickPayPayment\Models;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;
use Shopware\Models\Customer\Customer;

/**
 * @ORM\Entity
 * @ORM\Table(name="quickpay_payment_operations")
 */
class QuickPayPaymentOperation extends ModelEntity
{
    /**
     * @param QuickPayPayment $payment
     * @param mixed $data
     */
    public function __construct($payment, $data)
    {
        $this->payment = $payment;
        $this->createdAt = new DateTime();
        $this->update($data);
    }
    
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue
     * 
     * @var integer id of the entry
     */
    protected $id;
    
    /**
     * @ORM\ManyToOne(targetEntity="QuickPayPayment")
     * @ORM\JoinColumn(name="payment_id", referencedColumnName="id", nullable=false)
     * 
     * @var QuickPayPayment linked QuickPay payment
     */
    protected $payment;
    
    /**
     * @ORM\Column(name="operation_id", type="integer", nullable=true)
     * 
     * @var string Operation id from Quickpay
     */
    protected $operationId;
    
    /**
     * @ORM\Column(type="datetime", name="created_at")
     * 
     * @var \DateTime Date of creation
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="string", name="type")
     * 
     * @var string type of the operations
     */
    protected $type;

    /**
     * @ORM\Column(name="amount", type="integer")
     *
     * @var integer the Amount for the operation
     */
    protected $amount;
    
    /**
     * @ORM\Column(type="string", name="raw_json", length=5000)
     * 
     * @var string Raw JSON of the message
     */
    protected $rawJson;
    
    /**
     * Get the internal id of the payment operation
     * 
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Get the linked payment
     * 
     * @return QuickPayPayment
     */
    public function getPayment()
    {
        return $this->payment;
    }
    
    /**
     * Get the QuickPay payment operation id
     * 
     * @return integer
     */
    public function getOperationId()
    {
        return $this->operationId;
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
     * Get the type of the operation
     * 
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    
    /**
     * Get the amount fo the operation
     * 
     * @return integer
     */
    public function getAmount()
    {
        return $this->amount;
    }
    
    /**
     * get the raw JSON of the message
     * 
     * @return string
     */
    public function getRawJson()
    {
        return $this->rawJson;
    }
    
    /**
     * Update values based on provided data
     */
    public function update($data)
    {
        $this->operationId = $data->id;
        $this->type = $data->type;
        $this->amount = empty($data->amount) ? 0 :$data->amount;
        if($data->created_at)
        {
            $this->createdAt = DateTime::createFromFormat(DateTime::ATOM, $data->created_at);
            $this->createdAt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        $this->rawJson = json_encode($data);
    }
}
