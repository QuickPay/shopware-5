//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.list.Operation',
{
    extend: 'Ext.grid.Panel',
    
    snippets: {
        columns: {
            createdAt: '{s name=operation/columns/created_at}Date{/s}',
            type: '{s name=operation/columns/type}Action{/s}',
            amount: '{s name=operation/columns/amount}Amount{/s}',
        },
        operations: {
            create: '{s name=operation/types/create}Payment created{/s}',
            authorize: '{s name=operation/types/authorize}Payment autorized{/s}',
            capture: '{s name=operation/types/capture}Payment captured{/s}',
            capture_request: '{s name=operation/types/capture_request}Payment capture requested{/s}',
            refund: '{s name=operation/types/refund}Payment refunded{/s}',
            refund_request: '{s name=operation/types/refund_request}Payment refund requested{/s}',
            cancel: '{s name=operation/types/cancel}Payment cancelled{/s}',
            cancel_request: '{s name=operation/types/cancel_request}Payment cancel requested{/s}',
            checksum_failure: '{s name=operation/types/checksum_failure}Callback checksum failure{/s}',
            test_mode_violation: '{s name=operation/types/test_mode_violation}Test mode violation{/s}'
        }
    },
    
    initComponent: function()
    {
        var me = this;

        me.columns = me.getColumns();

        me.callParent(arguments);
    },
    
    getColumns: function()
    {
        var me = this;

        return [
            {
                header: me.snippets.columns.createdAt,
                dataIndex: 'createdAt',
                flex: 1,
                renderer: me.dateRenderer
            },
            {
                header: me.snippets.columns.type,
                dataIndex: 'type',
                flex: 1,
                renderer: me.typeRenderer
            },
            {
                header: me.snippets.columns.amount,
                dataIndex: 'amount',
                flex: 1,
                renderer: me.amountRenderer
            }
        ];
    },
    
    typeRenderer: function(type)
    {
        var me = this;
        return me.snippets.operations[type];
    },
    
    dateRenderer: function(date)
    {
        if ( date === Ext.undefined ) {
            return date;
        }

        return Ext.util.Format.date(date) + ' ' + Ext.util.Format.date(date, timeFormat);    },
    
    amountRenderer: function(amount)
    {
        return (amount !== 0 ? (amount / 100.0).toFixed(2) : '');
    }
});