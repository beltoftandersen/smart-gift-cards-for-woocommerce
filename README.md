# Beltoft Gift Cards for WooCommerce

Sell digital gift cards, deliver them by email, and let customers redeem them at checkout.

- Stable version: 1.4.0
- Requires: WordPress 5.8+, PHP 7.4+, WooCommerce 6.0+
- Author: Chimkins IT
- Text domain: beltoft-gift-cards

## Overview

This plugin adds a gift card product type to your WooCommerce store. Customers purchase a gift card, choose an amount, and enter the recipient's email. When the order is processed, the recipient gets a branded email with their unique gift card code. Codes are redeemed at checkout through the standard WooCommerce coupon field — no extra steps for the customer.

## Features

- Gift card product type with predefined amounts (e.g., $25, $50, $100) or custom amounts
- Email delivery using WooCommerce email templates — same look as your order emails
- Coupon field redemption — codes work in the standard WooCommerce coupon field, no setup required
- Optional dedicated "Apply Gift Card" field on cart/checkout via settings or shortcode
- Auto-apply from email — the "Shop Now" button in the delivery email automatically applies the gift card to the recipient's cart
- Virtual coupon integration — gift card discounts display natively between subtotal and total with WooCommerce [Remove] link
- Balance tracking with partial redemption — remaining balance carries over
- Personal message displayed in cart and order details
- Price range display in shop catalog (e.g., "$25 – $100")
- My Account tab for customers to view gift cards, balances, and transaction history
- Admin dashboard with stats: total issued, outstanding balance, redeemed, expired
- Gift card management list with search, status filters, pagination, and bulk actions
- Manual gift card creation from the admin panel — no order required
- Order meta box showing gift cards created by and used on each order
- Automatic balance restore on cancel/refund, including proportional partial refunds
- Loyalty Rewards integration — optionally block or allow customers from using loyalty points to purchase gift cards
- Shortcode `[bgcw_product_form]` for page builders (Bricks, Elementor, etc.)
- Email settings (subject, heading, on/off) under WooCommerce > Settings > Emails
- Atomic balance deduction to prevent race conditions on concurrent redemptions
- Rate limiting on gift card code lookups
- HPOS compatible — works with WooCommerce High-Performance Order Storage
- Clean uninstall with opt-in data removal
- Portuguese (pt_PT) translation included
- PSR-4 codebase, no Composer dependency

## How It Works

1. Create a "Gift Card" product in WooCommerce and set the predefined amounts.
2. Customer purchases the gift card, picks an amount, and enters recipient details and an optional message.
3. When the order is processed, a unique code is generated and emailed to the recipient.
4. Recipient enters the code at checkout in the coupon field — the gift card balance is applied as a discount.
5. Partial use is tracked. The remaining balance stays on the gift card for future orders.

## Installation

1. Upload the plugin to `wp-content/plugins/` or install from a ZIP.
2. Activate the plugin.
3. Go to **WooCommerce > Gift Cards > Settings** to configure.
4. Create a new product and select **"Gift card"** as the product type.
5. Optionally adjust the delivery email under **WooCommerce > Settings > Emails > Gift Card Delivery**.

## Configuration

### Gift Card Product

Create a product, select "Gift card" as the product type. Set predefined amounts in the Gift Card data panel (e.g., 25,50,75,100). Custom amounts and their min/max are controlled from the global settings page.

### Redemption

Gift card codes always work in the standard WooCommerce coupon field — this is automatic. To also show a dedicated "Apply Gift Card" field, enable it in settings. You can choose automatic placement or shortcode-only:

```
[bgcw_apply_field]
```

### Email Template

Uses WooCommerce's email system — same header, footer, and colours as your other store emails. Customise the subject and heading under WooCommerce > Settings > Emails > Gift Card Delivery.

Override the template by copying `templates/emails/gift-card-delivery.php` to your theme's `woocommerce/emails/` folder.

### Page Builders

For Bricks, Elementor, or other page builders that replace WooCommerce templates, use the WooCommerce Add to Cart element or the shortcode:

```
[bgcw_product_form]
```

## Hooks & Filters

Developers can extend the plugin:

- `bgcw_gift_card_created` — fires after a gift card is created (used by the email system)

## Translations

- Text domain: `beltoft-gift-cards`
- Translation template: `languages/beltoft-gift-cards.pot`

## Changelog

### 1.4.0

- Renamed plugin slug and folder to `beltoft-gift-cards`.
- Renamed text domain to `beltoft-gift-cards`.
- Replaced inline scripts with `wp_add_inline_script()`.
- Fixed double-escaping on gift card price display.
- Improved input sanitization on all add-to-cart POST data.
- Moved all inline styles to external CSS files.
- Added `wp_cache_delete()` calls after custom table writes.
- Updated author to beltoft.net.

### 1.3.0

- Improved: MySQL advisory lock for concurrent balance deductions.
- Improved: SQL-level pagination for My Account gift cards.
- Improved: Bulk gift card code lookups in cart and Store API.
- Improved: Expiry sync moved to WP-Cron (hourly) with composite DB index.
- Fixed: Tax-inclusive discount amount in balance deductions.
- Fixed: Refund safety guard requires prior deduction before restoring balance.
- Added: Block checkout support — gift card codes identified via Store API extension.

### 1.0.0

- Initial release.
- Gift card product type with predefined and custom amounts.
- Email delivery to recipients using WooCommerce email templates.
- Auto-apply gift card from email "Shop Now" link.
- Virtual coupon integration — gift card discounts display natively between subtotal and total with WooCommerce [Remove] link.
- Optional dedicated "Apply Gift Card" field with automatic or shortcode-only placement.
- Personal message displayed in cart and order details.
- Price range display in shop catalog (e.g., "$25 – $100").
- Balance tracking with partial redemption.
- My Account tab for viewing gift cards and transactions.
- Admin dashboard, gift card list with bulk actions, and manual creation.
- Order meta box showing created and used gift cards.
- Automatic balance restore on cancel/refund with partial refund support.
- Loyalty Rewards for WooCommerce integration — block or allow loyalty points for gift card purchases.
- Atomic balance deduction to prevent race conditions.
- Rate limiting on gift card code lookups.
- HPOS compatibility.
- Block checkout incompatibility declared (classic checkout required).
- Portuguese (pt_PT) translation included.

## About the Author

Beltoft Gift Cards for WooCommerce is built and maintained by [beltoft.net](https://beltoft.net).

## License

GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html.
