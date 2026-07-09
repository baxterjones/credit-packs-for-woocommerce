# Credit Packs for WooCommerce

Sell prepaid credit packs in WooCommerce and let customers redeem credits on selected products.

## What it does

Credit Packs for WooCommerce adds a prepaid pack system to WooCommerce. A product can grant credits when purchased, and another product can require credits when redeemed. Customers can buy normally or use available credits where eligible.

## Current features

- WooCommerce product tab for Credit Pack settings.
- Product types: Standard Product, Credit Pack, Redeemable Product.
- Credit packs grant credits when orders reach Processing or Completed.
- Redeemable products can be paid for with available credits.
- Customer credit balance stored against the user.
- Custom credit ledger table for tracking all changes.
- Admin manual credit adjustments with notes and optional expiry dates.
- Product credit settings table for faster bulk-style setup.
- Customer-facing product notices with custom labels, colours, icons, and message templates.
- Optional Tutor LMS dashboard credit summary card when Tutor LMS is active.
- Refund and cancellation handling with ledger/order notes.

## Requirements

- WooCommerce
- Configurable labels such as Credits, Lessons, Sessions, Passes, Hours, or any other business-friendly term.

## Internal naming

- Plugin folder: `credit-packs-for-woocommerce`
- Main file: `credit-packs-for-woocommerce.php`
- Prefix: `bxtr_cp_`
- Classes: `BXTR_CP_*`
- Text domain: `credit-packs-for-woocommerce`
- Ledger table: `{prefix}_bxtr_cp_credit_ledger`

## Author

Author: Baxter Jones  
Author URI: https://baxtersweb.com
