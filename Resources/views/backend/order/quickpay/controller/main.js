//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.controller.Main',
{
	override: 'Shopware.apps.Order.controller.Main',	
	/**
	 *
	 */
	showOrder: function(record) {
            var me = this;
            var detailController = me.getController('Detail');
	    detailController.loadQuickpayPayment(record.get('quickpay_payment_id'))
            me.callParent(arguments);
	}
});
