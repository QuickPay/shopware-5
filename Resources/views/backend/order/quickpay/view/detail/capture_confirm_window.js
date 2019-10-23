//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.detail.CaptureConfirmWindow',
{
    extend: 'Shopware.apps.Order.QuickPay.view.detail.ConfirmWindow',
    
    alias: 'widget.order-quickpay-capture-confirm-window',

    cls: Ext.baseCSSPrefix + 'order-quickpay-capture-confirm-window',

    snippets: {
        title: '{s name=order/capture_confirm_window/title}Capture payment{/s}',
        text: '{s name=order/capture_confirm_window/text}Check the amount and press confirm to send the capture request to the QuickPay API.{/s}',
        amountLabel: '{s name=order/capture_confirm_window/amount_label}Amount:{/s}'
    },

    /**
     *
     */
    initComponent: function()
    {
        var me = this;

        me.title = me.snippets.title;
    
        me.callParent(arguments);
    },

    getPanelProperties: function ()
    {
        var me = this;
        
        var props = me.callParent(arguments);
        
        props.message = me.snippets.text;
        props.amountLabel = me.snippets.amountlabel;
        props.maxAmount = me.data.amountAuthorized - me.data.amountCaptured;
        props.amount = props.maxAmount;
        
        return props;
    }
});
