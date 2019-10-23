<?php

namespace QuickPayPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;

class Backend implements SubscriberInterface
{
    /**
     *  @var Container $container
     */
    protected $container;

    protected $pluginDirectory;
    
    /**
     * @param DIContainer $container
     */
    public function __construct(Container $container, $pluginDirectory)
    {
        $this->container = $container;
        $this->pluginDirectory = $pluginDirectory;
    }
    
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            "Enlight_Controller_Action_PostDispatchSecure_Backend_Order" => "onPostDispatchBackendOrder"
        ];
    }
    
    /**
     * onPostDispatchBackendOrder:
     *
     * @access public
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchBackendOrder(\Enlight_Event_EventArgs $args)
    {

        /** @var \Shopware_Controllers_Backend_Customer $controller */
        $controller = $args->getSubject();

        $view = $controller->View();
        $request = $controller->Request();
        
        $view->addTemplateDir($this->pluginDirectory . '/Resources/views');

        switch ($request->getActionName())
        {
            case 'index' :
                $view->extendsTemplate('backend/order/quickpay/app.js');
                break;
            case 'load' :
                $view->extendsTemplate("backend/order/quickpay/model/order_fields.js");
                break;
            
            case "getList":
                $arrAssignedData = $view->getAssign('data');
                $db = Shopware()->Db();

                foreach($arrAssignedData as $key => $order) {
                    $row = $db->fetchRow('SELECT id as quickpay_payment_id, status as quickpay_payment_status, amount_authorized as quickpay_amount_authorized, amount_captured as quickpay_amount_captured FROM quickpay_payments WHERE order_number = ?', [$order["number"]], \Zend_Db::FETCH_ASSOC);
                    if($row)
                    {
                        $arrAssignedData[$key] = array_merge($arrAssignedData[$key], $row);    
                    }
                    
                }

                $view->data = $arrAssignedData;
                break;
        }
    }
}
