//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.controller.Batch',
{
	override: 'Shopware.apps.Order.controller.Batch',	

	/**
	 *
	 */
	prepareStoreProxy: function(store, values) {
            this.callParent(arguments);
            
            var extraParams = store.getProxy().extraParams;
            extraParams.quickpayAction = values.quickpayAction;
            
            return store;
	}
});