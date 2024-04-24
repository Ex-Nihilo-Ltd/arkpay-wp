=== ArkPay ===
Contributors: lukaxx
Donate link: https://exn.rs
Tags: comments, spam
Requires at least: 5.2
Tested up to: 6.5
Stable tag: 1.0.11
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The Smartest, Fastest & Most Secure Payment Processor.

== Description ==

ArkPay is a Swiss payment service provider which is affiliated with SOFIT, a self regulatory organization which itself is directly supervised by FINMA. We utilize this license to offer payment services to merchants of all categories. ArkPay offers a full suite gateway too merchants, which means unlike many other payment services providers you are not billed separately for gateway fees.

[ArkPay](https://arkpay.com/)

== Usage of 3rd Party Service ==

ArkPay plugin utilizes ArkPay service in the following circumstances:

Cart page

- Users can pay for their orders directly from the cart by clicking the "Pay via ArkPay" button. At that moment, a request is sent to the ArkPay service to create a transaction, redirecting users to a new Hosted Payment Page (HPP) to complete the payment for their order.

Checkout page

- Users can also pay for their orders on the checkout page. It's necessary for users to enter valid credit card information and select the ArkPay service as their payment method. By clicking the "Pay/Place Order" button, the plugin sends a request to the ArkPay service to create a transaction and another request to immediately process the payment using the entered credit card information.

Requests

- [Create transaction](https://arkpay.com/api/v1/merchant/api/transactions)
- [Pay transaction](https://arkpay.com/api/v1/merchant/api/transactions/[transaction_id]/pay)

For more information about ArkPay, please visit our website: https://arkpay.com.

== Legal Information ==

It's important to understand the terms of service and privacy policies associated with ArkPay. Please review the following documents:

- [Terms of Service](https://arkpay.com/terms-of-service)
- [Privacy Policy](https://arkpay.com/privacy-policy)
- [Licenses](https://arkpay.com/licences)

By using this plugin, you acknowledge and agree to abide by the terms and policies of ArkPay.

== Frequently Asked Questions ==

= What is ArkPay? =

ArkPay is a Swiss payment service provider which is affiliated with SOFIT,  a self regulatory organization  which itself is directly supervised by FINMA. We utilize this license to offer payment services to merchants of all categories. ArkPay offers a full suite gateway too merchants, which means unlike many other payment services providers you are not billed separately for gateway fees.

= What is the Goal of Arkpay? =

The goal of ArkPay is to streamline the acquiring process for merchants, and help them navigate the complex payments landscape, specifically within higher risk sectors. We aim to be more transparent than other players within the industry.

= How do I recieve funds? =

ArkPay offers a wide variety of settlement options, you can opt to receive settlements within cryptocurrency such as USDT, or conventional bank wires in currencies such as US dollars, Euros and many more.

= Can ArkPay offer me a bank account? =

Currently, ArkPay does not offer deposit account functionality, however we have a network of partners which we can refer you to assist your account opening.

== Installation ==

= Minimum Requirements =

* PHP 7.4 or greater is required (PHP 8.0 or greater is recommended)
* MySQL 5.6 or greater, OR MariaDB version 10.1 or greater, is required

= Automatic installation =

Automatic installation is the easiest option -- WordPress will handle the file transfer, and you won’t need to leave your web browser. To do an automatic install of ArkPay, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New”.
 
In the search field type “ArkPay” then click “Search Plugins”. Once you’ve found us, you can view details about it such as the point release, rating, and description. Most importantly of course, you can install it by clicking “Install Now” and WordPress will take it from there.

= Manual installation =

Manual installation method requires downloading the ArkPay plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation).

= Updating =

Automatic updates should work smoothly, but we still recommend you back up your site.

== Changelog ==

= 1.0.0 2024-02-21 =

**ArkPay**

* Update - Updating README.txt

== Changelog ==

= 1.0.1 2024-02-22 =

**ArkPay**

* Fix - Adding shipping to orders via cart payments.

== Changelog ==

= 1.0.2 2024-02-23 =

**ArkPay**

* Fix - Fix order amount type.
* Fix - Fixed some plugin error/warning messages.

== Changelog ==

= 1.0.3 2024-02-29 =

**ArkPay**

* Fix - Webhook auth failed response.

== Changelog ==

= 1.0.4 2024-02-29 =

**ArkPay**

* Fix - Enhanced response for webhook authentication failure.

= 1.0.5 2024-03-01 =

**ArkPay**

* Fix - use $_SERVER instead of getallheaders()

= 1.0.6 2024-03-01 =

**ArkPay**

* Fix - Webhook $data(body) missing.

= 1.0.7 2024-03-01 =

**ArkPay**

* Fix - Webhook auth failed - signature mismatch message.

= 1.0.8 2024-03-01 =

**ArkPay**

* Fix - Order transaction check if there is already an active transaction before proceeding.

= 1.0.9 2024-03-06 =

**ArkPay**

* Fix - Removed the JSON_UNESCAPED_SLASHES option from the signature creation in the payment request.

= 1.0.10 2024-04-10 =

**ArkPay**

* Update - Code/Functions update.

= 1.0.11 2024-04-23 =

**ArkPay**

* Update - Removing PHP short tags, as its not recommended by WordPress standard.
* Update - Updating function/class/define/namespace/option names with plugins prefix to make them more unique and avoid conflicts with other plugins or themes.
