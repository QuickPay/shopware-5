# QuickPay Payment Plugin for Shopware #
This plugin enables QuickPay as payment option for Shopware
## Installation ##
The plugin can easily be installed by following the steps below:
- Clone the repository into a folder named *QuickPayPayment* inside Shopwares *custom/plugins* directory.
- Open the Plugin Manager in the Shopware backend
- Select **Installed** from the Menu on the side and look for the QuickPay Payment plugin in the list of uninstalled plugins
- Install the plugin
- Activate the plugin

## Updating ##
To update the plugin follow these steps
- Pull the latest version
- Open the Plugin Manager in the backend
- Find the QuickPay Payment plugin
- Click on the update icon (Local update)

## Configuration ##
Configuration of the plugin is done as for every Shopware plugin by opening up the detail view from the Plugin Manager or via the Basic Settings window.
The QuickPay Payment plugin has the following settings:

|  Name        | Descritpion                                   |
| ------------ | --------------------------------------------- |
|  Public Key  | The API key for the QuickPay integration      |
|  Private Key | The private key for the QuickPay integration  |
|  Test mode   | Configure wether the test mode is enabled. With test mode enabled payments using the QuickPay [test data](https://learn.quickpay.net/tech-talk/appendixes/test/ "test data") are possible.  |


The public and private key can be found in the QuickPay management panel under Settings->Integration

In order to use the QuickPay payment method the it has to be activated using the Payment methods window in the Shopware backend. Enabling it for different shipping methods might also be necessary

## Backend functionality ##
The following actions can be performed in the Shopware backend:

#### Orders List ####
The plugin adds an additional column to the list or orders in the Shopware Backend. If the QuickPay payment status of an order allows capturing this column will contain an icon-button indicating this possibility. Updon clicking the icon a confimation window will be opened. After entering the amount to be captured (or leaving the preselected full amount) the capture can be confirmed and will be sent to the QuickPay API

Additionaly the plugin extends the options provided by the Batch processing button. Upon selecting one or multiple orders and opening the Batch processing window one additional dropdown will be present. It can be used to select a Quickpay action (capture, cancel or refund) performed for all selected orders. When processing the changes the respective requests to the QuickPay API will be performed for each of the orders. Capture and refund will always request the full amount, when using the batch processing functionality.

#### QuickPay panel ####
When opening the detail window for an order in the backend a new QuickPay tab has been added to the List. If the order was completed using QuickPay as the payment method, this tab is enabled and can be selected to access the QuickPay panel.

This panel contains a List containing the History of the QuickPay payment. That means every requested operation by the user (capture/cancel/refund) and every callback response from the QuickPay server is logged and displayed there.

In addition abore this list the following four buttons are present:

| Button   | Functionality                                      |
| -------- | -------------------------------------------------- |
| Capture  | Send a capture request to the QuickPay API         |
| Cancel   | Cancel a payment that has not been captured yet    |
| Refund   | Refund a payment that has already been captured    |
| Reload   | Refresh the history and the status of the payment  |


Each button is enabled or disabled according to the current status of the QuickPay payment.
Clicking either of the first three buttons will open a window to confirm this operation. When capturing or refunding partial the amount can be entered to make partial captures/refunds a possibility.
