=== Credit Packs for WooCommerce ===
Contributors: baxterjones
Tags: woocommerce, credits, credit packs, prepaid
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell prepaid credit packs in WooCommerce and let customers redeem credits on selected products.

== Description ==

Credit Packs for WooCommerce adds a prepaid pack system to WooCommerce.

Create products that grant credits when purchased, then allow selected products to be redeemed using those credits. It is designed for tutoring, classes, consulting, sessions, passes, downloads, service packages, and other prepaid package use cases.

The plugin focuses on credit packs, not loyalty points or store credit.

== Features ==

* Credit Pack products can grant credits.
* Redeemable Products can require credits.
* Customers can use credits or pay normally.
* Admins can manually add or subtract credits.
* Ledger records every credit change.
* Optional expiry dates for granted credits.
* Bulk-style product credit settings table.
* Custom labels for Credits, Sessions, Passes, Lessons, Hours, or similar.
* Custom frontend messages with template variables.
* Built-in SVG icons or theme icon class support.
* Colour and spacing controls for polished frontend cards.
* Optional Tutor LMS dashboard credit summary card when Tutor LMS is active.
* Refund and cancellation handling with order notes.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate Credit Packs for WooCommerce.
3. Make sure WooCommerce is active. WooCommerce is the only required plugin.
4. Go to Credit Packs in the WordPress admin.
5. Configure labels, frontend display, and product credit settings.

== Requirements ==

* WooCommerce

Tutor LMS is an optional supported integration. It is not required for the core credit pack system.

== Shortcodes ==

Use `[bxtr_credit_packs]` to display the logged-in customer's credit card in a custom location.

Use `[bxtr_credit_packs type="balance"]` to display the balance only.

Use `[bxtr_credit_packs type="product" product_id="123"]` to display a product credit box for a specific WooCommerce product.

Advanced Views example: `[bxtr_credit_packs type="product" product_id="{{ _layout.object_id }}"]`.

== Frequently Asked Questions ==

= Is this a wallet plugin? =

No. It is focused on prepaid credit packs. Customers buy packs and redeem credits against eligible WooCommerce products.

= Can I rename Credits to something else? =

Yes. You can use labels such as Sessions, Lessons, Passes, Hours, Tokens, or your own wording.

= Does the plugin load icon libraries? =

No. It includes built-in SVG icons and also supports custom theme icon classes. If you use a theme icon class, your theme or icon library must provide the icon.

== Changelog ==

= 1.0 =
* Added the Shortcodes admin tab and `[bxtr_credit_packs]` shortcode support.
* Added customer card, balance-only, product box, and Advanced Views shortcode examples.
* Refined product card preview layout and font-size controls.
* Removed the admin changelog tab.

= 1.0.6 =
* Removed overview width limiter, refined Tutor LMS dashboard insertion, updated theme icon help text, and continued plugin-check cleanup.
* Refined admin header links, icon settings visibility, ledger search, Tutor LMS dashboard fallback, and admin wording.

= 1.0.2 =
* Refined admin layout, style preview, ledger tools, header links, and Tutor LMS dashboard placement.

= 1.0.0 =
* Renamed plugin to Credit Packs for WooCommerce.
* Refactored internals to bxtr_cp prefix and BXTR_CP classes.
* Added custom labels, frontend display settings, icon options, and message templates.
* Added product credit settings table.
* Kept the existing working credit pack, redemption, ledger, refund, and Tutor LMS behaviour.
