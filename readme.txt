=== Lesson Credit Wallet ===
Contributors: baxtersweb
Tags: woocommerce, tutor lms, appointments, lesson credits, bookings, tutoring
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight Lesson Credit Wallet for WooCommerce appointment-based lesson bookings and Tutor LMS dashboards.

== Description ==

Lesson Credit Wallet adds prepaid Lesson Credits to WooCommerce lesson booking websites.

It was built for appointment-based education businesses where customers can either pay normally at checkout or purchase packs that grant Lesson Credits for future bookings.

The plugin is designed to make the workflow clear for both customers and administrators:

* Credit Pack products can grant Lesson Credits.
* Redeemable Lesson products can be booked with Lesson Credits.
* Customers see their available Lesson Credits on the Tutor LMS dashboard.
* Product pages clearly explain that credits are optional and normal payment is still available.
* Admins can manually add or subtract Lesson Credits.
* A credit ledger records grants, redemptions, refunds, and manual adjustments.
* Offline/EFT workflows are supported by granting credits when the order is marked Processing or Completed.

== Features ==

* WooCommerce product tab for Lesson Credit settings.
* Product type selector: Standard Product, Credit Pack, or Redeemable Lesson.
* Credit Pack products grant Lesson Credits after purchase.
* Redeemable Lesson products can use Lesson Credits at checkout.
* Tutor LMS dashboard credit card.
* Admin dashboard with dependencies, setup guidance, ledger, user balances, and changelog.
* Manual admin credit adjustments.
* Ledger entries for support and troubleshooting.
* Full-refund guidance and credit return handling.
* Offline/EFT workflow support.
* HPOS-conscious WooCommerce order handling.

== Requirements ==

* WordPress
* WooCommerce
* Tutor LMS
* WooCommerce Appointments or a WooCommerce-compatible appointable product flow

== Setup ==

1. Install and activate the plugin.
2. Go to WooCommerce products.
3. Edit a product and open the Lesson Credits product data tab.
4. For pack products, choose Credit Pack and set the number of Lesson Credits granted.
5. For lesson products, choose Redeemable Lesson and set the number of Lesson Credits required.
6. Visit the Lesson Credits admin page for setup guidance, manual adjustments, and ledger activity.

== Offline / EFT Workflow ==

If a parent pays directly by bank transfer, an admin can place the order on behalf of the user and select the admin-only EFT payment method.

Credits are granted when the order reaches Processing or Completed, so the admin should only mark the order as paid after payment has been confirmed.

== Refund Policy ==

The recommended workflow is full refunds only.

Partial credit refunds are not supported. If a credit pack has already been partly used, review the account manually before issuing any refund.

== Changelog ==

= 1.0.4 =
* Removed the plugin website row link from the plugin list.
* Added GPLv2-or-later licence details.
* Added WordPress-style readme file.

= 1.0.3 =
* Added plugin list Settings link.
* Added View details link to the admin changelog.
* Added Baxtersweb author URL in plugin details.

= 1.0.2 =
* Improved product page Pay with Credits wording.
* Removed bold styling from product page credit messaging and pack links.
* Hid duration text inside product credit price display.
* Reduced visual size of Save percentage labels.

= 1.0.1 =
* Improved lesson product-page wording so Lesson Credits feel optional, not required.
* Added inline Available Credit Packs with automatic Save percentage labels.
* Added Credit Pack product-page information showing included 1 Hour Sessions and Lesson Credits.
* Capitalised Lesson Credit / Lesson Credits consistently.

= 1.0.0 =
* Initial stable release.
