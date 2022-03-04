//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.controller.Detail',
{
    override: 'Shopware.apps.Order.controller.Detail',
    
    quickpaySnippets: {
        notifications: {
            captureSuccess: {
                title: '{s name=detail/notifications/request_success/title}Request submitted{/s}',
                message: '{s name=detail/notifications/capture_request_success/message}The capture request has been successfuly submitted.{/s}'
            },
            captureFailure: {
                title: '{s name=detail/notifications/request_failure/title}Request failed{/s}'
            },
            cancelSuccess: {
                title: '{s name=detail/notifications/request_success/title}Request submitted{/s}',
                message: '{s name=detail/notifications/cancel_request_success/message}The cancel request has been successfuly submitted.{/s}'
            },
            cancelFailure: {
                title: '{s name=detail/notifications/request_failure/title}Request failed{/s}'
            },
            refundFailure: {
                title: '{s name=detail/notifications/request_failure/title}Request failed{/s}'
            },
            refundSuccess: {
                title: '{s name=detail/notifications/request_success/title}Request submitted{/s}',
                message: '{s name=detail/notifications/refund_request_success/message}The refund request has been successfuly submitted.{/s}'
            },
            growlMessage: 'Order-QuickPay'
        }
    },
    
    init: function()
    {
        var me = this;
        me.quickpayPaymentStore = Ext.getStore('quickpay-payment-store');
        if (!me.quickpayPaymentStore) {
            me.quickpayPaymentStore = Ext.create('Shopware.apps.Order.QuickPay.store.Payment');
        }
        me.control({
            'order-detail-window order-quickpay-panel, order-list': {
                showCaptureConfirmWindow: me.onShowCaptureConfirmWindow,
            },
            'order-detail-window order-quickpay-panel': {
                showCancelConfirmWindow: me.onShowCancelConfirmWindow,
                showRefundConfirmWindow: me.onShowRefundConfirmWindow,
                reload: me.loadQuickpayPayment
            },
            'order-quickpay-capture-confirm-window order-quickpay-confirm-panel': {
                confirmOperation: me.onConfirmCapture,
                cancelOperation: me.onCancelCapture
            },
            'order-quickpay-refund-confirm-window order-quickpay-confirm-panel': {
                confirmOperation: me.onConfirmRefund,
                cancelOperation: me.onCancelRefund
            },
            'order-quickpay-cancel-confirm-window order-quickpay-confirm-panel': {
                confirmOperation: me.onConfirmCancel,
                cancelOperation: me.onCancelCancel
            }
        });
        me.callParent(arguments);
    },
    
     
    onShowCaptureConfirmWindow: function(data, record, source)
    {
        var me = this;
        if (me.captureConfirmWindow !== undefined) {
            me.captureConfirmWindow.destroy();
            delete me.captureConfirmWindow;
        }
        me.captureConfirmWindow = Ext.create('Shopware.apps.Order.QuickPay.view.detail.CaptureConfirmWindow', {
            data: data,
            record: record,
            source: source
        }).show(undefined, function() {
            this.subApplication = me.subApplication;
        });
    },
    
    onShowCancelConfirmWindow: function(data, record, source)
    {
        var me = this;
        if (me.cancelConfirmWindow !== undefined) {
            me.cancelConfirmWindow.destroy();
            delete me.cancelConfirmWindow;
        }
        me.cancelConfirmWindow = Ext.create('Shopware.apps.Order.QuickPay.view.detail.CancelConfirmWindow', {
            data: data,
            record: record,
            source: source
        }).show(undefined, function() {
            this.subApplication = me.subApplication;
        });
    },

    loadQuickpayPayment: function(id)
    {
        var me = this;
        var payment = me.quickpayPaymentStore.getById(id);
        if(payment)
        {
            me.quickpayPaymentStore.remove(payment);
        }
        var paymentModel = Ext.ModelManager.getModel('Shopware.apps.Order.QuickPay.model.Payment');
        paymentModel.load(id, { success: function(payment)
        {
            if(payment)
                me.quickpayPaymentStore.add(payment);
            
            me.quickpayPaymentStore.fireEvent('paymentUpdate', id);
        }});
    },

    onShowRefundConfirmWindow: function(data, record, source)
    {
        var me = this;
        if (me.refundConfirmWindow !== undefined) {
            me.refundConfirmWindow.destroy();
            delete me.refundConfirmWindow;
        }
        me.refundConfirmWindow = Ext.create('Shopware.apps.Order.QuickPay.view.detail.RefundConfirmWindow', {
            data: data,
            record: record,
            source: source
        }).show(undefined, function() {
            this.subApplication = me.subApplication;
        });
    },
    
    onConfirmCapture: function(values, source)
    {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="QuickPay" action="capture"}',
            params: values,
            success: function(response) {
                var data = Ext.JSON.decode(response.responseText);
                
                if (!data.success) {
                    var notification = me.quickpaySnippets.notifications.captureFailure;
                    Shopware.Notification.createGrowlMessage(notification.title, data.message, me.quickpaySnippets.notifications.growlMessage);
                    
                    return;
                }
                
                var notification = me.quickpaySnippets.notifications.captureSuccess;
                Shopware.Notification.createGrowlMessage(notification.title, notification.message, me.quickpaySnippets.notifications.growlMessage);
                
                source.operationFinished('capture', false);
                
                if (me.captureConfirmWindow !== undefined) {
                    me.captureConfirmWindow.destroy();
                    delete me.captureConfirmWindow;
                }
                
            }
        });  
    },
    
    onCancelCapture: function(source)
    {
        var me = this;
        if (me.captureConfirmWindow !== undefined) {
            me.captureConfirmWindow.destroy();
            delete me.captureConfirmWindow;
        }
        source.operationFinished('capture', true);
    },
    

    
    onConfirmRefund: function(values, source)
    {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="QuickPay" action="refund"}',
            params: values,
            success: function(response) {
                var data = Ext.JSON.decode(response.responseText);
                
                if (!data.success) {
                    var notification = me.quickpaySnippets.notifications.refundFailure;
                    Shopware.Notification.createGrowlMessage(notification.title, data.message, me.quickpaySnippets.notifications.growlMessage);
                    
                    return;
                }
                
                var notification = me.quickpaySnippets.notifications.refundSuccess;
                Shopware.Notification.createGrowlMessage(notification.title, notification.message, me.quickpaySnippets.notifications.growlMessage);
                
                source.operationFinished('refund', false);
                
                if (me.refundConfirmWindow !== undefined) {
                    me.refundConfirmWindow.destroy();
                    delete me.refundConfirmWindow;
                }
                
            }
        });  
    },

    onConfirmCancel: function(values, source)
    {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="QuickPay" action="cancel"}',
            params: values,
            success: function(response) {
                var data = Ext.JSON.decode(response.responseText);
                
                if (!data.success) {
                    var notification = me.quickpaySnippets.notifications.cancelFailure;
                    Shopware.Notification.createGrowlMessage(notification.title, data.message, me.quickpaySnippets.notifications.growlMessage);
                    
                    return;
                }
                
                var notification = me.quickpaySnippets.notifications.cancelSuccess;
                Shopware.Notification.createGrowlMessage(notification.title, notification.message, me.quickpaySnippets.notifications.growlMessage);
                
                source.operationFinished('cancel', false);
                
                if (me.cancelConfirmWindow !== undefined) {
                    me.cancelConfirmWindow.destroy();
                    delete me.cancelConfirmWindow;
                }
                
            }
        });  
    },
    
    onCancelCancel: function(source)
    {
        var me = this;
        if (me.cancelConfirmWindow !== undefined) {
            me.cancelConfirmWindow.destroy();
            delete me.cancelConfirmWindow;
        }
        source.operationFinished('cancel', true);
    },
    onCancelRefund: function(source)
    {
        var me = this;
        if (me.refundConfirmWindow !== undefined) {
            me.refundConfirmWindow.destroy();
            delete me.refundConfirmWindow;
        }
        source.operationFinished('refund', true);
    }
});