//{namespace name="plugins/quickpay"}

//{block name="backend/order/model/order/fields" append}
	{ name: 'quickpay_payment_id', type: 'string', defaultValue: null },
        { name: 'quickpay_payment_status', type: 'int' },
        { name: 'quickpay_amount_authorized', type: 'int' },
        { name: 'quickpay_amount_captured', type: 'int' },
        { name: 'quickpay_amount_refunded', type: 'int' },
//{/block}
