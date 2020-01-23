//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.list.Operation',
{
    extend: 'Ext.grid.Panel',
    
    snippets: {
        columns: {
            createdAt: '{s name=operation/columns/created_at}Date{/s}',
            type: '{s name=operation/columns/type}Action{/s}',
            amount: '{s name=operation/columns/amount}Amount{/s}',
            status: '{s name=operation/columns/status}Status{/s}'
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
            test_mode_violation: '{s name=operation/types/test_mode_violation}Test mode violation{/s}',
            failed: {
                authorize: '{s name=operation/types/authorize_failed}Payment not authorized{/s}',
                capture: '{s name=operation/types/capture_failed}Payment not captured{/s}',
                cancel: '{s name=operation/types/cancel_failed}Payment not cancelled{/s}',
                refund: '{s name=operation/types/refund_failed}Payment not refunded{/s}',
            }    
        },
        status: {
            '20000': 'Approved',
            '20200': 'Waiting approval',
            '30100': '3D Secure is required',
            '40000': 'Rejected By Acquirer',
            '40001': 'Request Data Error',
            '40002': 'Authorization expired',
            '40003': 'Aborted',
            '50000': 'Gateway Error',
            '50300': 'Communications Error (with Acquirer)'
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
                header: me.snippets.columns.status,
                dataIndex: 'status',
                flex: 1,
                renderer: me.statusRenderer
            },
            {
                header: me.snippets.columns.amount,
                dataIndex: 'amount',
                flex: 1,
                renderer: me.amountRenderer
            }
        ];
    },
    
    typeRenderer: function(type, metaData, record)
    {
        var me = this;
        var status = record.get('status');
        if(!status || status == '20000')
            return me.snippets.operations[type];
        else
            return me.snippets.operations.failed[type];
    },
    
    statusRenderer: function(code)
    {
        var me = this;
        if(code)
            return me.snippets.status[code];
        else
            return '';
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