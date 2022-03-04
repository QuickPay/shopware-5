//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.detail.CancelConfirmWindow',
{
    extend: 'Shopware.apps.Order.QuickPay.view.detail.ConfirmWindow',
    
    alias: 'widget.order-quickpay-cancel-confirm-window',
    cls: Ext.baseCSSPrefix + 'order-quickpay-cancel-confirm-window',
    height: 175,
    snippets: {
        title: '{s name=order/cancel_confirm_window/title}Cancel payment{/s}',
        text: '{s name=order/cancel_confirm_window/text}Do you really want to cancel the payment? Click confirm to send the cancel request to the QuickPay API.{/s}',
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
        props.maxAmount = 0;
        return props;
    }
});
