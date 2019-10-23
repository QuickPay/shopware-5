//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.detail.ConfirmPanel',
{
    extend: 'Ext.form.Panel',

    alias: 'widget.order-quickpay-confirm-panel',

    cls: Ext.baseCSSPrefix + 'order-quickpay-confirm-panel',

    flex: 1,

    bodyPadding: '10 10 10 10',

    border: 0,

    autoScroll: true,

    collapsible: false,

    snippets:
    {
        buttons:
        {
            confirm: '{s name=order/buttons/confirm}Confirm{/s}',
            cancel: '{s name=order/buttons/cancel}Cancel{/s}'
        }
    },

    /**
     *
     */
    initComponent:function()
    {
        var me = this;

        me.registerEvents();

        me.items = [
            me.createDetailsContainer(),

            me.createButtonsContainer()
        ];

        me.callParent(arguments);
    },

    /**
     *
     */
    registerEvents: function()
    {
        this.addEvents(
            'confirmOperation',
            'cancelOperation'
        );
    },

    /**
     *
     */
    createDetailsContainer: function()
    {
        var me = this;

        me.detailsContainer = Ext.create('Ext.form.Panel', {
            cls: 'confirm-panel-main',
            bodyPadding: 5,
            margin: '0 0 5 0',
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                align: 'stretch',
                width: '100%'
            },
            items: me.createFields()   
        });

        return me.detailsContainer;
    },

    createFields: function() {
        var me = this;
                
        var items = [
            {
                xtype: 'displayfield',
                name: 'message',
                value: me.message
            },
            {
                xtype: 'hiddenfield',
                name: 'id',
                value: me.data.id
            }
        ];
        
        if(me.maxAmount > 0)
        {
            items.push({
                xtype: 'numberfield',
                name: 'amount',
                minValue: 0.01,
                maxValue: me.maxAmount,
                fieldLabel: me.amountLabel,
                value: me.amount / 100.0
            });
        }
        else
        {
            items.push({
                xtype: 'hiddenfield',
                name: 'amunt',
                value: 0
            });
        }
        
        return items;
    },

    createButtonsContainer: function()
    {
        var me = this;

        return Ext.create('Ext.toolbar.Toolbar', {
            dock: 'bottom',
            ui: 'shopware-ui',
            border: false,
            shadow: false,
            padding: '20 0 0 0',
            style: {
                'background-color': 'transparent',
                'box-shadow': 'none',
                '-moz-box-shadow': 'none',
                '-webkit-box-shadow': 'none',
                '-o-box-shadow': 'none'
            },
            items: [
                { xtype: 'tbfill' },
                Ext.create('Ext.button.Button', {
                    cls: 'secondary',
                    text: me.snippets.buttons.cancel,
                    action: 'cancelOperation',
                    handler: function() {
                        me.fireEvent('cancelOperation', me.source);
                    }
                }),
                Ext.create('Ext.button.Button', {
                    cls: 'primary',
                    text: me.snippets.buttons.confirm,
                    action: 'confirmOperation',
                    handler: function() {
                        var values = me.getForm().getValues();
                        values.amount *= 100;
                        me.fireEvent('confirmOperation', values, me.source);
                    }
                })
            ]
        });
    }
    
});
