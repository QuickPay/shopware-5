//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.detail.Debit', {
    
    override: 'Shopware.apps.Order.view.detail.Debit',
    
    quickPaySnippets:{
        paymentId:'{s name=debit/payment_id}QuickPay Payment ID{/s}',
    },
    createTopElements:function () {
        var me = this;
        var items = me.callParent(arguments);
        items.push(me.createQuickPayIdField())
        return items;
    },
    createQuickPayIdField: function() {
        var me = this;
        var quickPayId = me.record.get('quickpay_payment_id');
        me.quickPayIdField = Ext.create('Ext.form.field.Text', {
            name:'paymentId',
            fieldLabel:me.quickPaySnippets.paymentId,
            anchor:'97.5%',
            labelWidth: 155,
            minWidth:250,
            labelStyle: 'font-weight: 700;',
            readOnly:true,
            value: quickPayId,
            hidden: quickPayId ? false : true
        });
        return me.quickPayIdField;
    }
    
});
