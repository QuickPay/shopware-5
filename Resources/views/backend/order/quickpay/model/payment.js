//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.model.Payment',
{
    extend: 'Ext.data.Model',

    fields: [
        { name: 'id', type: 'string' },
        { name: 'createdAt', type: 'date' },
        { name: 'status', type: 'int' },
        { name: 'orderNumber', type: 'string' },
        { name: 'orderId', type: 'string' },
        { name: 'link', type: 'string' },
        { name: 'amount', type: 'int' },
        { name: 'amountAuthorized', type: 'int' },
        { name: 'amountCaptured', type: 'int' }
    ],
    
    hasMany: { model: 'Shopware.apps.Order.QuickPay.model.Operation', name: 'operations' },

    proxy: {
        type: 'ajax',

        api: {
            read:'{url controller="QuickPay" action="detail"}',
        },

        reader: {
            type: 'json',
            root: 'data',
            messageProperty: 'message'
        }
    },
});