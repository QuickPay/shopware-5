//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.store.Payment',
{
    extend: 'Ext.data.Store',
    model: 'Shopware.apps.Order.QuickPay.model.Payment',
    storeId: 'quickpay-payment-store',
    pageSize: 10,
    autoLoad: false,
    sorters: [
        {
            property: 'createdAt',
            direction: 'DESC'
        }
    ],
    proxy: {
        type: 'ajax',
        url: '{url controller="QuickPay" action="list"}',
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
