//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.batch.Form', {
    override: 'Shopware.apps.Order.view.batch.Form',
    quickpaySnippets: {
        action: {
            capture: '{s name=order/batch/capture}Capture payment(s){/s}',
            cancel: '{s name=order/batch/cancel}Cancel payment(s){/s}',
            refund: '{s name=order/batch/refund}Refund payment(s){/s}',
            label: '{s name=order/batch/action}QuickPay action{/s}'
        }
    },
    /**
     * @override
     */
    createFormFields: function () {
        var me = this;
        var fields = me.callParent(arguments);
        return Ext.Array.insert(fields, 4, [me.createQuickpayActionField()]);
    },
    
    /**
     * Creates the "Quickpay Action" field
     *
     * @returns Ext.form.field.ComboBox
     */
    createQuickpayActionField: function () {
        var me = this,
            store = new Ext.data.SimpleStore({
                fields: [
                    'value',
                    'description'
                ],
                data: [
                    ['capture', me.quickpaySnippets.action.capture],
                    ['cancel', me.quickpaySnippets.action.cancel],
                    ['refund', me.quickpaySnippets.action.refund]
                ]
            });
        return Ext.create('Ext.form.field.ComboBox', {
            name: 'quickpayAction',
            triggerAction: 'all',
            fieldLabel: me.quickpaySnippets.action.label,
            editable: true,
            typeAhead: true,
            minChars: 2,
            emptyText: me.snippets.selectOption,
            store: store,
            snippets: me.snippets,
            displayField: 'description',
            valueField: 'value',
            validateOnBlur: true,
            validator: me.validateComboboxSelection,
            listeners: {
                scope: me,
                afterrender: this.disableAutocompleteAndSpellcheck
            }
        });
    }
});