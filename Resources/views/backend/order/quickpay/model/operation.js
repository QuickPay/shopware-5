//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.model.Operation',
{
    extend: 'Ext.data.Model',

    fields: [
        { name: 'id', type: 'int' },
        { name: 'createdAt', type: 'date' },
        { name: 'type', type: 'string' },
        { name: 'amount', type: 'int' }
    ],
    
    belongsTo: 'Shopware.apps.Order.QuickPay.model.Operation'
    
});