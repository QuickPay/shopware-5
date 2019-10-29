//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.QuickPay.view.detail.QuickPay',
{
    extend: 'Ext.container.Container',
    
    alias: 'widget.order-quickpay-panel',

    cls: Ext.baseCSSPrefix + 'order-quickpay-panel',
    
    id: 'order-quickpay-panel',

    padding: 10,
    
    snippets: {
        title: '{s name=order/tab/title}QuickPay{/s}',
        captureButtonText: '{s name=order/capture_button/text}Capture{/s}',
        cancelButtonText: '{s name=order/cancel_button/text}Cancel{/s}',
        refundButtonText: '{s name=order/refund_button/text}Refund{/s}',
        reloadButtonText: '{s name=order/reload_button/text}Reload{/s}',
        gridTitle: '{s name=order/grid/title}Payment History{/s}',
    },

    initComponent: function() {
        var me = this;

        me.operationStore = Ext.create('Ext.data.Store', { model: 'Shopware.apps.Order.QuickPay.model.Operation' });

        me.title = me.snippets.title;

        me.registerEvents();

        me.items = [
            me.createToolbar(),
            me.createGrid()
        ];
        
        me.quickpayPaymentStore.on('paymentUpdate', me.onPaymentUpdate, me);
                
        me.callParent(arguments);
        
        me.update();
    
    },
    
    beforeDestroy: function()
    {
        var me = this;
        
        me.operationStore.clearData();
        
        me.quickpayPaymentStore.un('paymentUpdate', me.onPaymentUpdate, me);
        
        me.callParent(arguments);
    },
    
    registerEvents: function()
    {
        this.addEvents(
            'showCaptureConfirmWindow',
            'showCancelConfirmWindow',
            'showRefundConfirmWindow',
            'reload'
        );
    },

    getPayment: function()
    {
        var me = this;
        
        return me.quickpayPaymentStore.getById(me.record.get('quickpay_payment_id'));
    },

    getPaymentData: function()
    {
        var me = this;
        
        var payment = me.getPayment();
        
        return {
            id: payment.get('id'),
            amountAuthorized: payment.get('amountAuthorized'),
            amountCaptured: payment.get('amountCaptured'),
            amountRefunded: payment.get('amountRefunded')
        };
    },

    onPaymentUpdate: function(id)
    {
        var me = this;
        
        if(id === me.record.get('quickpay_payment_id'))
        {
            me.update();
        }
    },

    createToolbar: function()
    {
        var me = this;

        var status = 9;//me.record.raw.quickpay.status * 1;

        me.captureButton = Ext.create('Ext.button.Button', {
            iconCls: 'sprite-tick',
            text: me.snippets.captureButtonText,
            action: 'capturePayment',
            disabled: status !== 5, //Only fully authorized
            handler: function() {
                me.fireEvent('showCaptureConfirmWindow', me.getPaymentData(), me.record, me);
            }
        });

        me.cancelButton = Ext.create('Ext.button.Button', {
            iconCls: 'sprite-cross',
            text: me.snippets.cancelButtonText,
            action: 'cancelPayment',
            disabled: status >= 10, //Capture not yet requested
            handler: function() {
                me.fireEvent('showCancelConfirmWindow', me.getPaymentData(), me.record, me);
            }
        });

        me.refundButton = Ext.create('Ext.button.Button', {
            iconCls: 'sprite-arrow-return-180-left',
            text: me.snippets.refundButtonText,
            action: 'refundPayment',
            disabled: status !== 15, //only fully captured
            handler: function() {
                me.fireEvent('showRefundConfirmWindow', me.getPaymentData(), me.record, me);
            }
        });
        
        me.reloadButton = Ext.create('Ext.button.Button', {
            iconCls: 'sprite-arrow-circle-135-left',
            text: me.snippets.reloadButtonText,
            action: 'reloadPayment',
            handler: function() {
                me.reload();
            }
        });

        me.toolbar = Ext.create('Ext.toolbar.Toolbar', {
            dock: 'top',
            ui: 'shopware-ui',
            margin: '0 0 10px 0',
            style: {
                padding: 0,
                backgroundColor: 'transparent'
            },
            items: [
                me.captureButton,
                me.cancelButton,
                me.refundButton,
                me.reloadButton
            ]
        });
        
        return me.toolbar;
    },

    operationFinished(operation, cancelled)
    {
        var me = this;
        
        me.reload();
    },

    reload: function()
    {
        var me = this;
        
        me.fireEvent('reload', me.record.get('quickpay_payment_id'));
    },

    createGrid: function()
    {
        var me = this;
        me.grid = Ext.create('Shopware.apps.Order.QuickPay.view.list.Operation', {
            store: me.operationStore,
            record: me.record,
            minHeight: 250,
            maxHeight: 300,
            minWidth: 250,
            region: 'center',
            title: me.snippets.gridTitle,
            style: {
                'margin-bottom': '10px'
            }
        });
        
        return me.grid;
    },

    update: function()
    {
        var me = this;
        
        var payment = me.getPayment();
        
        if(!payment)
        {
            me.setDisabled(true);
        }
        else
        {
            me.setDisabled(false);
            me.updateToolbar(payment);
            me.updateGrid(payment);
        }
    },

    updateToolbar: function(payment)
    {
        var me = this;
        
        var status = payment.get('status');
        
        me.captureButton.setDisabled(status !== 5 && status !== 12); //Only FULLY_AUTHORIZED or PARTLY_CAPTURED
        me.cancelButton.setDisabled(status >= 10); //No capture requested yet
        me.refundButton.setDisabled(status !== 12 && status !== 15 && status !== 32); //Only PARTLY_CAPTURED, FULLY_CAPTURED and PARTLY_REFUNDED
    },

    updateGrid: function(payment)
    {
        var me = this;
        
        me.operationStore.removeAll();
        payment.operations().each((operation) => { me.operationStore.add(operation) });
    }
});