=== Tax Invoice Requests for WooCommerce ===
Contributors: yoohw
Tags: woocommerce, invoice, pdf, billing, orders
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 7.0
WC tested up to: 10.7
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let WooCommerce customers request a tax invoice, then generate, email, and securely download the PDF from their order.

== Description ==

[Product page](https://yoohw.com/product/tax-invoice-requests/) | [Documentation](https://docs.yoohw.com/category/tax-invoice-requests/) | [Support](https://workspace.yoohw.com/)

Tax Invoice Requests for WooCommerce adds a self-service invoice request workflow to eligible orders. Customers can submit company, billing, and tax registration details, receive the generated PDF by email, and download it again from the order page.

The configurable tax ID label can be used for VAT, GST, ABN, TRN, Tax ID, or another regional registration name. The plugin supports signed-in customers and guests who have a valid WooCommerce order key.

= Customer invoice workflow =

* Add a tax invoice request action to eligible WooCommerce order pages.
* Prefill the form from order billing details and previously saved customer invoice details.
* Collect a company or customer name, tax ID label and number, country or region, address, email, and optional note.
* Generate a PDF automatically after a valid request.
* Send a dedicated WooCommerce customer email with the PDF attached.
* Allow authorized customers and guests with a valid order key to download the invoice again.
* Optionally add the invoice request link to supported WooCommerce customer order emails.

= Store administration =

* Review invoice status, number, recipient details, tax ID, address, and notes from the WooCommerce order screen.
* Regenerate an invoice PDF or resend its email from the order metabox.
* Configure seller legal name, tax registration details, address, and email.
* Configure invoice number prefix and the next sequential number.
* Preserve existing invoice numbers when a PDF is regenerated or settings change.
* Reduce duplicate invoice numbers during concurrent requests through safer number reservation.
* Work with WooCommerce High-Performance Order Storage order screens.

= PDF contents and access =

Generated PDFs can include seller and customer details, invoice and order dates, order items, quantities, subtotals, taxes, totals, currency, payment method, and a custom footer or legal note. An optional line-item tax-rate column and tax summary can be enabled.

Invoice requests use nonce and order-access checks. Downloads pass through a protected handler with customer ownership, guest order-key, or store-management permission checks. PDF files use randomized names and validated paths, and remote fetching in Dompdf is disabled by default.

= Legal and accounting scope =

This plugin generates PDFs from WooCommerce order records and the information submitted by the customer. It does not replace accounting software, tax advice, fiscal authorization, e-invoicing clearance, or country-specific compliance services. Store owners must confirm that their settings and invoice output meet applicable legal, tax, and record-keeping requirements.

== Installation ==

1. Install the plugin through the WordPress Plugins screen, or upload it to `/wp-content/plugins/yoohw-tax-invoice-requests/`.
2. Activate WooCommerce, then activate Tax Invoice Requests for WooCommerce.
3. Go to **WooCommerce > Settings > Tax > Tax invoice**.
4. Choose eligible order statuses and enter the seller details.
5. Configure invoice numbering, PDF display, and customer email options.
6. Save the settings and test the complete request, email, and download workflow with a test order.

== Frequently Asked Questions ==

= Where can a customer request an invoice? =

A signed-in customer can use the WooCommerce order details page. A guest can use the order received page when the URL contains a valid order key.

= Can customers enter VAT, GST, ABN, TRN, or another tax ID? =

Yes. The request form includes configurable tax ID label and number fields.

= Which order statuses are eligible? =

Store managers choose the allowed statuses under **WooCommerce > Settings > Tax > Tax invoice**. Processing and Completed are enabled by default.

= Is the PDF generated and emailed automatically? =

Yes. After a valid request, the plugin attempts to generate the PDF and sends it through a dedicated WooCommerce customer email.

= Can customers download the invoice later? =

Yes. Signed-in owners and guests with a valid order key can use the protected download action. Store managers can access it from order administration.

= Can an administrator regenerate or resend an invoice? =

Yes. The order metabox provides both actions when the required invoice details or PDF are available. Regeneration preserves the assigned invoice number.

= Can I customize seller details and invoice numbering? =

Yes. Configure seller identity, tax details, address, email, prefix, and next invoice number in the plugin settings.

= Does it support tax summaries by rate? =

Yes. The optional summary uses tax records from order items, fees, and shipping.

= Does it replace accounting or country-specific e-invoicing software? =

No. You are responsible for checking local invoice, tax, accounting, and record-keeping requirements.

= Does it support WooCommerce HPOS? =

Yes. The plugin supports WooCommerce order screens that use High-Performance Order Storage.

== Screenshots ==

1. Tax invoice request action on a WooCommerce customer order.
2. Tax invoice details and actions in the WooCommerce order metabox.
3. Tax invoice settings under WooCommerce > Settings > Tax.

== Changelog ==

= 1.0.1 (June 23, 2026) =

* Hardened PDF storage, file path validation, direct-access protection, and guest download links.
* Added safer invoice number reservation and improved tax summary calculation.
* Added order actions to regenerate invoice PDFs and resend invoice emails.

See `changelog.txt` for the complete release history.

== Upgrade Notice ==

= 1.0.1 =

Security, invoice numbering, guest download, tax summary, and administrator workflow improvements.
