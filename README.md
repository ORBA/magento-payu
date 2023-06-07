This project is ABANDONED

# magento-payu
Magento 1 module for Payu.pl payments processor integration.

### Features:
* Add a new payment method to store. Payment is processed by Payu.pl (former Platnosci.pl)
* Allows to configure different payment gates for each store view
* Allows to retry unfinished payment (e.g. cancelled payment, closed browser, insufficient funds, various Payu.pl errors)
* Shows information about the type and status of transaction.
* Allows to select specific payment channel (from those provided by Payu) on checkout.
* Full refunds support (using Magento built Magento refunds module)
* Allows to manually confirm payments (e.g. when payment was finished using different channel)
* Allows to set Your own order statuses for payments statuses (initialized, finished, on error)

### Configuration and testing:

> Remember that Payu.pl doesn't support SANDBOX accounts. For testing payments You need to set this up in Payu.pl payments settings in payu.pl administration panel.

1. First You need to create a working payment gate (POS) at Payu.pl administration panel.
2. In module configuration (System > Configuration > ORBA | Payu.pl Pro) add 3 addresses:
   * Success return URL: ```http://yourdomain.com/payuplpro/payment/ok/sid/%sessionId%```
   * Error return URL: ```http://yourdomain.com/payuplpro/payment/error/sid/%sessionId%/code/%error%```
   * Reports URL: ```http://yourdomain.com/payuplpro/payment/online```
