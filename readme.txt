=== Smart Gift Cards for WooCommerce ===
Contributors: christian198521, Chimkins IT
Tags: woocommerce, gift cards, gift certificate, store credit, voucher
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell digital gift cards, deliver them by email, and let customers redeem them at checkout.

== Description ==

Smart Gift Cards for WooCommerce adds a gift card product type to your store. Customers purchase a gift card, choose an amount, and enter the recipient's email. When the order is processed, the recipient gets a branded email with their unique gift card code. Codes are redeemed at checkout through the standard WooCommerce coupon field — no extra steps for the customer.

=== Key Features ===
1. Gift card product type — predefined amounts (e.g., $25, $50, $100) or custom amounts within configurable min/max.
2. Email delivery using WooCommerce email templates — same look as your order emails.
3. Coupon field redemption — gift card codes work in the standard WooCommerce coupon field, no setup required.
4. Optional dedicated "Apply Gift Card" field on cart/checkout via settings or `[wcgc_apply_field]` shortcode.
5. Auto-apply from email — the "Shop Now" button in the delivery email automatically applies the gift card to the recipient's cart.
6. Balance tracking with partial redemption — remaining balance carries over to the next purchase.
7. My Account tab — customers view their gift cards, balances, and full transaction history.
8. Personal message displayed in cart and order details.
9. Price range display — gift card products show "From $25" or "$25 – $100" in your shop catalog.
10. Admin dashboard with stats: total issued, outstanding balance, redeemed, and expired counts.
11. Gift card management list with search, status filters, pagination, and bulk actions (disable/delete).
12. Manual gift card creation from the admin panel — no order required.
13. Order meta box showing gift cards created by and used on each order.
14. Automatic balance restore on order cancel/refund, including proportional partial refund support.
15. Loyalty Rewards integration — optionally block or allow customers from using loyalty points to purchase gift cards (requires Loyalty Rewards for WooCommerce).
16. Shortcode `[wcgc_product_form]` for page builders (Bricks, Elementor, etc.).
17. HPOS compatible — works with WooCommerce High-Performance Order Storage.
18. Email settings (subject, heading, on/off) under WooCommerce > Settings > Emails.

=== How It Works ===
1. Create a "Gift Card" product in WooCommerce and set the predefined amounts.
2. Customer purchases the gift card, picks an amount, and enters recipient details and an optional message.
3. When the order is processed, a unique code is generated and emailed to the recipient.
4. Recipient enters the code at checkout in the coupon field — the gift card balance is applied as a discount.
5. Partial use is tracked. The remaining balance stays on the gift card for future orders.

== Installation ==
1. Upload the `smart-gift-cards-for-woocommerce` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to **WooCommerce > Gift Cards > Settings** to configure.
4. Create a new product and select **"Gift card"** as the product type.
5. Optionally adjust the delivery email under **WooCommerce > Settings > Emails > Gift Card Delivery**.

== Configuration ==

=== Gift Card Product ===
- Create a product, select "Gift card" as the product type.
- Set predefined amounts in the Gift Card data panel (e.g., 25,50,75,100).
- Custom amounts and their min/max are controlled from the global settings page.

=== Redemption ===
- Gift card codes always work in the standard WooCommerce coupon field — this is automatic.
- To also show a dedicated "Apply Gift Card" field, enable it in settings with automatic placement or shortcode-only.
- Shortcode: `[wcgc_apply_field]` — place it on cart or checkout pages.
- Recipients can click "Shop Now" in the delivery email to auto-apply the gift card to their cart.

=== Email Template ===
- Uses WooCommerce's email system — same header, footer, and colours as your other store emails.
- Customise subject and heading under WooCommerce > Settings > Emails > Gift Card Delivery.
- Override the template by copying `templates/emails/gift-card-delivery.php` to your theme's `woocommerce/emails/` folder.

=== Page Builders ===
- For Bricks, Elementor, or other page builders that replace WooCommerce templates, use the WooCommerce Add to Cart element or the `[wcgc_product_form]` shortcode.

=== Hooks & Filters ===
Developers can extend the plugin:

* `wcgc_gift_card_created` — fires after a gift card is created (used by the email system).

== Frequently Asked Questions ==

= Do gift cards work with the standard coupon field? =
Yes. Gift card codes are always accepted in the WooCommerce coupon field. This works automatically — customers just enter the code where they would enter a coupon.

= Can I also show a separate gift card field? =
Yes. Go to WooCommerce > Gift Cards > Settings and enable the "Dedicated Gift Card Field." You can choose automatic placement (cart & checkout), shortcode-only (`[wcgc_apply_field]`), or both.

= Do gift cards support partial redemption? =
Yes. If a gift card balance exceeds the order total, only the needed amount is deducted. The remaining balance stays on the gift card for future use.

= What happens when an order is refunded? =
Gift card balances are automatically restored when an order is cancelled or fully refunded. Partial refunds proportionally restore the gift card balance.

= Can I create gift cards manually? =
Yes. Go to WooCommerce > Gift Cards > Gift Cards tab and click "Add Gift Card." You can specify the amount, recipient, and message.

= Are gift cards taxable? =
No. Gift card products are set as non-taxable, and gift card discounts are applied as non-taxable negative fees.

= Does it work with page builders like Bricks or Elementor? =
Yes. Use the `[wcgc_product_form]` shortcode inside your page builder's product template to display the gift card amount selector and recipient fields.

= Do emails match my store's design? =
Yes. They use WooCommerce's email template — same header, footer, and styling as order emails.

= Can customers see their gift card balances? =
Yes. A "Gift Cards" tab is added to My Account where customers can view all their gift cards (purchased and received), balances, and transaction history.

= Can customers use loyalty points to buy gift cards? =
By default, no. If you have the Loyalty Rewards for WooCommerce plugin active, an "Integrations" section appears in the gift card settings where you can allow or block loyalty point redemption on gift card purchases.

== Screenshots ==
1. Gift card product page with amount selector and recipient fields.
2. Gift card delivery email sent to the recipient.
3. Gift card applied at checkout via the coupon field.
4. Dedicated "Apply Gift Card" field on the cart page.
5. My Account — Gift Cards tab showing balances and transactions.
6. Admin dashboard with stats.
7. Admin gift card list with search, filters, and bulk actions.
8. Settings page.

== Changelog ==

= 1.0.0 =
* Initial release.
* Gift card product type with predefined and custom amounts.
* Email delivery to recipients using WooCommerce email templates.
* Auto-apply gift card from email "Shop Now" link.
* Virtual coupon integration — gift card discounts display natively between subtotal and total with WooCommerce [Remove] link.
* Optional dedicated "Apply Gift Card" field with automatic or shortcode-only placement.
* Personal message displayed in cart and order details.
* Price range display in shop catalog (e.g., "$25 – $100").
* Balance tracking with partial redemption.
* My Account tab for viewing gift cards and transactions.
* Admin dashboard, gift card list with bulk actions, and manual creation.
* Order meta box showing created and used gift cards.
* Automatic balance restore on cancel/refund with partial refund support.
* Loyalty Rewards for WooCommerce integration — block or allow loyalty points for gift card purchases.
* Atomic balance deduction to prevent race conditions.
* Rate limiting on gift card code lookups.
* HPOS compatibility.
* Block checkout incompatibility declared (classic checkout required).
* Portuguese (pt_PT) translation included.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
