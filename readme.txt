=== ING Lease Now ===
Contributors: leasenow
Tags: ING Lease Now, Lease Now, leasing, ING, ING Lease, online payments, WooCommerce, payment
Requires at least: 4.7
Tested up to: 6.2.2
Requires PHP: 5.4.0
License: GPLv2
Stable tag: 1.2.9

Add payment via ING Lease Now to WooCommerce.

== Description ==
Plugin for connect WooCommerce shops and Lease Now service.
Plugin adds usage of online payments via Lease Now payment gateway to WooCommerce.
Additional info about ING Lease Now can be found [here](https://www.leasenow.pl/).
For sandbox click „Enable” check-box
Sandbox environment can be found under [acc.leasenow.pl](https://acc.leasenow.pl/)
Technical Support:
leasenow@ingfintech.pl

== Instalation ==
The module requires configuration in the ING Lease Now administration panel.
To finish configuration please contact Technical Support: leasenow@ingfintech.pl where you will get Store ID and Secret.
Copy the keys into the fields in plugin configuration:
Administration panel->Woocemmerce->Settings->Payments->ING Lease Now->Manage

== Frequently Asked Questions ==
= After configuration I can’t see Lease Now button. How can I fix it? =
Plugin request two hooks:
woocommerce_after_add_to_cart_button
woocommerce_after_cart_table
If your site has hooks on and you can’t still see button, please contact Technical Support: leasenow@ingfintech.pl

== Changelog ==
=1.2.9=
*fix minor connection problems
=1.2.8=
*fix display tooltip in ajax call
=1.2.7=
*adjust getting product price to Lease Now
=1.2.6=
*adjust meta box for HPOS
=1.2.5=
*lowering the price limits of the product available for leasing - from PLN 1,000 net
*adding a modal informing about the missing amount to meet the minimum basket of PLN 5,000 net
*changed the way the button is displayed in the page code

=1.2.4=
*Fix variant products