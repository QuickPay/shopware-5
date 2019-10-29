//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.detail.ConfirmWindow',
{
    extend: 'Enlight.app.Window',
    
    width: 300,

    height: 200,

    footerButton: false,

    minimizable: false,

    maximizable: false,

    autoScroll: true,

    draggable: true,

    /**
     *
     */
    initComponent: function()
    {
        var me = this;

        me.items = [
            me.createPanel()
        ];

        me.callParent(arguments);
    },

    /**
     *
     */
    createPanel: function()
    {
        var me = this;

        me.panel = Ext.create('Shopware.apps.Order.QuickPay.view.detail.ConfirmPanel', me.getPanelProperties());
        
        return me.panel;
    },
    
    getPanelProperties: function()
    {
        var me = this;
        
        return {
            data: me.data,
            source: me.source
        };
    }
});
