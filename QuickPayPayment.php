<?php
namespace QuickPayPayment;

use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models\Payment\Payment;
use QuickPayPayment\Models\QuickPayPayment as PaymentModel;

class QuickPayPayment extends Plugin
{
    /**
     * Install plugin
     *
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $options = [
            'name' => 'quickpay_payment',
            'description' => 'QuickPay',
            'action' => 'QuickPay/redirect',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<div id="payment_desc">'
                . '  Pay using the QuickPay payment service provider.'
                . '</div>'
        ];

        $installer->createOrUpdate($context->getPlugin(), $options);
        
        $this->createTables();
        
        $this->createAttributes();
    }

    /**
     * Update plugin
     * 
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context)
    {
        $this->createAttributes();

        $this->createTables();

    }
    
    /**
     * Uninstall plugin
     *
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        
        if(!$context->keepUserData())
        {
            $this->removeAttributes();

            $this->removeTables();
        }
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
    }

    /**
     * Activate plugin
     *
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
    }

    /**
     * Change active flag
     *
     * @param Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $em = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $em->flush();
    }
    
    /**
     * Create or update all Attributes
     * 
     */
    private function createAttributes()
    {
                
        $crud = $this->container->get('shopware_attribute.crud_service');
        $crud->update('s_order_attributes', 'quickpay_payment_link', 'string', array(
            'displayInBackend' => true,
            'label' => 'QuickPay payment link'
        ), null, false, 'NULL');
        
        Shopware()->Models()->generateAttributeModels(
            array('s_order_attributes')
        );
        
    }
    
    /**
     * Remove all attributes
     */
    private function removeAttributes()
    {
        $crud = $this->container->get('shopware_attribute.crud_service');
        try {
            $crud->delete('s_order_attributes', 'quickpay_payment_link');
        } catch (\Exception $e) {
        }
        Shopware()->Models()->generateAttributeModels(
            array('s_order_attributes')
        );        
    }
    
    /**
     * Create all tables
     */
    private function createTables()
    {
        /** @var ModelManager $entityManager */
        $entityManager = $this->container->get('models');
        
        $tool = new SchemaTool($entityManager);
        
        $classMetaData = [
            $entityManager->getClassMetadata(PaymentModel::class)
        ];
        
        $tool->updateSchema($classMetaData, true);
    }
    
    /**
     * Remove all tables
     */
    private function removeTables()
    {
        /** @var ModelManager $entityManager */
        $entityManager = $this->container->get('models');
        
        $tool = new SchemaTool($entityManager);
        
        $classMetaData = [
            $entityManager->getClassMetadata(PaymentModel::class)
        ];
        
        $tool->dropSchema($classMetaData);
    }

}