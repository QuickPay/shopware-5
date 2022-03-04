<?php
/*
 * created on 26/02/2020 :  by  -  akshay Nihare 
 * https://github.com/akshaynikhare
 * 
 */
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
     * @ORM\Column(type="string", name="status", nullable=true)
     * 
     * @var string status of the operation
     */
    protected $status;    
    
    const PAYMENT_OPERATION_APPROVED = '20000';
    const PAYMENT_OPERATION_WAITING_APPROVAL = '20200';
    const PAYMENT_OPERATION_3D_SECURE_REQUIRED = '30100';
    const PAYMENT_OPERATION_REJECTED_BY_ACQUIRER = '40000';
    const PAYMENT_OPERATION_DATA_ERROR = '40001';
    const PAYMENT_OPERATION_AUTHORIZATION_EXPIRED = '40002';
    const PAYMENT_OPERATION_ABORTED = '40003';
    const PAYMENT_OPERATION_GATEWAY_ERROR = '50000';
    const PAYMENT_OPERATION_COMMUNICATIONS_ERROR = '50300';
    


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
     * Get the type of the operation
     * 
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    
    /**
     * Get the status of the operation
     * 
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Checks wether the operation was successfully
     * 
     * @return boolean
     */
    public function isSuccessfull()
    {
        return $this->status == self::PAYMENT_OPERATION_APPROVED;
    }
    
    /**
     * Checks wether the operation was finished
     * 
     * @return boolean
     */
    public function isFinished()
    {
        return array_search($this->status, [
            self::PAYMENT_OPERATION_WAITING_APPROVAL,
            self::PAYMENT_OPERATION_3D_SECURE_REQUIRED,
            
        ]) === false;
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
        $this->status = $data->qp_status_code;
        $this->amount = empty($data->amount) ? 0 :$data->amount;
        if($data->created_at)
        {
            $this->createdAt = DateTime::createFromFormat(DateTime::ATOM, $data->created_at);
            $this->createdAt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        $this->rawJson = json_encode($data);
    }
}
