=== Tax Invoice Requests for WooCommerce ===
Contributors: yoohw
Tags: woocommerce invoice, tax invoice, pdf invoice, vat invoice, gst invoice
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 10.7
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let WooCommerce customers request tax invoices, VAT invoices, or GST invoices from their order page, then generate and email a secure PDF invoice automatically.

== Description ==

Tax Invoice Requests for WooCommerce adds a practical self-service invoice request workflow to WooCommerce stores. Customers can request a tax invoice directly from an eligible order, enter their company or tax details, receive a PDF invoice by email, and download the invoice again from their account.

This plugin is built for stores that need to collect VAT, GST, ABN, TRN, business tax ID, or other regional tax registration details after checkout without handling every invoice request manually through support tickets.

Instead of asking customers to email billing details to your team, the plugin adds a tax invoice request form to WooCommerce order pages. After a valid request is submitted, the plugin saves the invoice details to the order, generates a PDF tax invoice, attaches it to a WooCommerce email, and protects the PDF behind permission checks.

= Why use this WooCommerce tax invoice plugin? =

* Reduce manual tax invoice requests from customers.
* Let customers submit tax ID, VAT, GST, ABN, TRN, billing address, and invoice email details from the order page.
* Generate PDF tax invoices from WooCommerce order data.
* Keep invoice PDFs available for future download.
* Give store managers invoice details and PDF actions inside the WooCommerce order admin screen.
* Support both logged-in customers and guest customers with valid WooCommerce order keys.

= Key features =

* Customer tax invoice request form on WooCommerce order details pages.
* Support for guest invoice requests from the order received page when a valid order key is present.
* Automatic invoice field prefill from billing details and previously saved customer invoice details.
* Custom tax ID label field for VAT number, GST number, ABN, TRN, Tax ID, or local registration labels.
* Automatic PDF tax invoice generation after a valid request.
* Dedicated WooCommerce customer email with the generated PDF invoice attached.
* Protected PDF invoice download links for customers, guests with a valid order key, and store managers.
* WooCommerce order admin metabox showing invoice status, invoice number, tax ID, invoice email, billing address, and notes.
* Admin actions to regenerate the PDF invoice and resend the invoice email.
* Configurable seller legal name, seller tax ID label, seller tax ID number, seller address, and seller email.
* Configurable invoice number prefix and next invoice number.
* Safer invoice number reservation to reduce duplicate invoice numbers during concurrent requests.
* Optional tax rate column in the generated PDF invoice.
* Optional tax summary grouped by tax rate.
* Custom footer or legal note for the PDF invoice.
* Optional tax invoice request link in WooCommerce customer order emails.
* Compatible with WooCommerce High-Performance Order Storage (HPOS) order screens.

= Customer invoice workflow =

1. The customer opens an eligible WooCommerce order from My Account > Orders, or opens the order received page with a valid order key.
2. The customer clicks Request tax invoice.
3. A modal form opens with prefilled billing and invoice details when available.
4. The customer enters the company or customer name, tax ID label, tax ID number, country or region, billing address, invoice email, and optional invoice note.
5. The plugin validates the request with a WordPress nonce and WooCommerce order access checks.
6. The invoice details are saved to the order.
7. A PDF tax invoice is generated automatically.
8. The customer receives an email with the PDF invoice attached.
9. The customer can download the PDF invoice again from the order page when permitted.

= Store manager workflow =

Store managers and administrators can review tax invoice requests from the WooCommerce order edit screen. The order metabox shows the invoice status, invoice number, company or customer name, tax ID, country or region, invoice email, billing address, invoice note, and a protected PDF download button when a valid PDF exists.

The same metabox includes admin actions to:

* Regenerate the tax invoice PDF.
* Resend the tax invoice email to the invoice recipient.

Both admin actions use nonce checks and WooCommerce order management capability checks.

= PDF tax invoice details =

Generated PDF invoices can include:

* Invoice title.
* Invoice number and invoice date.
* WooCommerce order number and order date.
* Currency and payment method.
* Seller legal business details.
* Customer invoice details.
* Order items, quantities, subtotals, tax amounts, and totals.
* Optional tax rate column for line items.
* Optional tax summary grouped by tax rate.
* Customer invoice note.
* Store-configured footer or legal note.

= Email behavior =

When a PDF invoice is generated, the plugin sends a dedicated WooCommerce customer email with the PDF attached. The email includes the WooCommerce order number and invoice number when available.

Store managers can also enable a tax invoice request link in supported customer order emails. When enabled, the email includes a Request tax invoice button that sends customers to the order page and opens the request form.

= Invoice numbering =

Invoices use the configured invoice number prefix and the next invoice number setting. The default format is:

`PREFIX-YEAR-000001`

Example:

`TAX-2026-000001`

Existing invoice numbers are preserved when invoice settings change or when an invoice PDF is regenerated.

= Security and access control =

The plugin is designed to keep invoice access controlled through WordPress and WooCommerce permissions.

Security-related behavior includes:

* WordPress nonce validation for customer invoice requests.
* WooCommerce order ownership checks for logged-in customers.
* Valid order key checks for guest customers.
* WooCommerce order management capability checks for store managers.
* Protected download handler for PDF invoices.
* Randomized PDF file names to make direct guessing impractical.
* Stricter invoice file path validation for downloads, email attachments, and admin cleanup.
* Direct-access protection rules for the invoice upload folder when supported by the server.
* Dompdf remote fetching disabled by default for generated invoice PDFs.

= Plugin settings =

Go to:

WooCommerce > Settings > Tax > Tax invoice

Settings are grouped into:

* General
* Seller details
* Invoice numbering
* PDF display
* Customer emails

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/yoohw-tax-invoice-requests/` directory, or install the plugin ZIP file from WordPress admin.
2. Activate the plugin from the Plugins screen in WordPress.
3. Go to WooCommerce > Settings > Tax > Tax invoice.
4. Choose the order statuses that can request a tax invoice.
5. Add your seller legal business name, tax ID label, tax ID number, address, and email.
6. Configure the invoice number prefix and next invoice number.
7. Review the PDF display and customer email settings.
8. Save changes.

== Frequently Asked Questions ==

= Does this plugin create WooCommerce PDF tax invoices? =

Yes. After a valid tax invoice request is submitted, the plugin generates a PDF invoice from WooCommerce order data and the submitted invoice details.

= Where do customers request a tax invoice? =

Logged-in customers can request a tax invoice from the WooCommerce order details page in My Account. Guest customers can request a tax invoice from the order received page when the URL includes a valid WooCommerce order key.

= Can customers enter VAT, GST, ABN, TRN, or other tax IDs? =

Yes. The request form includes a tax ID label field and a tax ID number field. Customers can use labels such as VAT Number, GST Number, ABN, TRN, Tax ID, or another local registration label.

= Which WooCommerce order statuses can request a tax invoice? =

You can configure allowed order statuses in WooCommerce > Settings > Tax > Tax invoice. Processing and Completed are enabled by default.

= Is the PDF invoice generated automatically? =

Yes. The plugin attempts to generate the PDF tax invoice immediately after a valid request is submitted.

= Can customers download the tax invoice later? =

Yes. When a valid PDF exists, authorized customers can download it again from the order page. Store managers can download it from the WooCommerce order admin screen.

= Can guest customers download the PDF invoice? =

Yes, if they access the order with a valid WooCommerce order key. Guest download URLs preserve the order key and still pass through the protected download handler.

= Can store managers regenerate an invoice PDF? =

Yes. The WooCommerce order metabox includes a Regenerate invoice action when invoice details exist for the order. Existing invoice numbers are preserved during regeneration.

= Can store managers resend the tax invoice email? =

Yes. When a valid PDF invoice exists, the order metabox includes a Resend invoice email action.

= Can I customize seller details on the invoice? =

Yes. You can configure the seller legal business name, tax ID label, tax ID number, address, and email in the plugin settings.

= Can I customize the invoice number format? =

You can configure the invoice number prefix and next invoice number. The generated format uses the prefix, current year, and a padded sequential number.

= Does the plugin support tax summaries by rate? =

Yes. The generated PDF can show an optional tax summary grouped by tax rate, using tax records from order line items, fees, and shipping.

= Does this plugin replace accounting software? =

No. This plugin generates WooCommerce-based tax invoice PDFs from order records. You should confirm that the generated invoice format meets your local legal, tax, and accounting requirements.

= Does this plugin support HPOS? =

Yes. The admin order metabox is designed to work with WooCommerce order screens, including stores using High-Performance Order Storage.

== Screenshots ==

1. Tax invoice request action in My Account orders.
2. Tax invoice details and actions in the WooCommerce order admin metabox.
3. Tax invoice settings under WooCommerce > Settings > Tax.

== Changelog ==

= 1.0.1 (June 23, 2026) =
* Hardened PDF storage with randomized file names and stricter direct-access protection.
* Added invoice file path validation for downloads, email attachments, and admin cleanup.
* Added safer invoice number reservation to reduce duplicate numbers during concurrent requests.
* Fixed guest invoice download links on order received pages by preserving valid order keys.
* Disabled remote fetching in Dompdf and handled PDF generation exceptions gracefully.
* Improved tax summary calculation to use order item, fee, and shipping tax records.
* Added admin order actions to regenerate invoice PDFs and resend invoice emails.
* Improved admin action buttons in the tax invoice request metabox.

= 1.0.0 (May 23, 2026) =
* Initial release.
* Added customer tax invoice request form.
* Added automatic PDF tax invoice generation.
* Added customer email with PDF attachment.
* Added protected PDF download handler.
* Added WooCommerce order admin metabox.
* Added configurable seller details, invoice numbering, PDF display, allowed statuses, and customer email request link.

== Upgrade Notice ==

= 1.0.1 (June 23, 2026) =
Security and admin workflow improvements for PDF invoices, invoice numbering, guest downloads, tax summary output, and resend/regenerate actions.

= 1.0.0 (May 23, 2026) =
Initial release.
