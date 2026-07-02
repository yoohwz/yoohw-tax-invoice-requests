<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yoohw_tir_current_user_id = get_current_user_id();

$yoohw_tir_saved_tax_company      = $yoohw_tir_current_user_id ? get_user_meta( $yoohw_tir_current_user_id, '_yoohw_tir_company', true ) : '';
$yoohw_tir_saved_tax_id           = $yoohw_tir_current_user_id ? get_user_meta( $yoohw_tir_current_user_id, '_yoohw_tir_tax_id', true ) : '';
$yoohw_tir_saved_tax_id_label     = $yoohw_tir_current_user_id ? get_user_meta( $yoohw_tir_current_user_id, '_yoohw_tir_tax_id_label', true ) : '';
$yoohw_tir_saved_tax_country      = $yoohw_tir_current_user_id ? get_user_meta( $yoohw_tir_current_user_id, '_yoohw_tir_country', true ) : '';
$yoohw_tir_saved_tax_address      = $yoohw_tir_current_user_id ? get_user_meta( $yoohw_tir_current_user_id, '_yoohw_tir_address', true ) : '';
$yoohw_tir_saved_tax_email        = $yoohw_tir_current_user_id ? get_user_meta( $yoohw_tir_current_user_id, '_yoohw_tir_email', true ) : '';
$yoohw_tir_saved_tax_invoice_note = $yoohw_tir_current_user_id ? get_user_meta( $yoohw_tir_current_user_id, '_yoohw_tir_note', true ) : '';

$yoohw_tir_customer_name = $order->get_meta( '_yoohw_tir_company' );

if ( empty( $yoohw_tir_customer_name ) ) {
	$yoohw_tir_customer_name = $yoohw_tir_saved_tax_company;
}

if ( empty( $yoohw_tir_customer_name ) ) {
	$yoohw_tir_customer_name = $order->get_billing_company();

	if ( empty( $yoohw_tir_customer_name ) ) {
		$yoohw_tir_customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
	}
}

$yoohw_tir_tax_id = $order->get_meta( '_yoohw_tir_tax_id' );

if ( empty( $yoohw_tir_tax_id ) ) {
	$yoohw_tir_tax_id = $yoohw_tir_saved_tax_id;
}

$yoohw_tir_tax_id_label = $order->get_meta( '_yoohw_tir_tax_id_label' );

if ( empty( $yoohw_tir_tax_id_label ) ) {
	$yoohw_tir_tax_id_label = $yoohw_tir_saved_tax_id_label;
}

if ( empty( $yoohw_tir_tax_id_label ) ) {
	$yoohw_tir_tax_id_label = __( 'Tax ID / VAT / GST Number', 'yoohw-tax-invoice-requests' );
}

$yoohw_tir_country = $order->get_meta( '_yoohw_tir_country' );

if ( empty( $yoohw_tir_country ) ) {
	$yoohw_tir_country = $yoohw_tir_saved_tax_country;
}

if ( empty( $yoohw_tir_country ) ) {
	$yoohw_tir_country = $order->get_billing_country();
}

$yoohw_tir_address = $order->get_meta( '_yoohw_tir_address' );

if ( empty( $yoohw_tir_address ) ) {
	$yoohw_tir_address = $yoohw_tir_saved_tax_address;
}

if ( empty( $yoohw_tir_address ) ) {
	$yoohw_tir_address = WC()->countries->get_formatted_address( [
		'first_name' => $order->get_billing_first_name(),
		'last_name'  => $order->get_billing_last_name(),
		'company'    => $order->get_billing_company(),
		'address_1'  => $order->get_billing_address_1(),
		'address_2'  => $order->get_billing_address_2(),
		'city'       => $order->get_billing_city(),
		'state'      => $order->get_billing_state(),
		'postcode'   => $order->get_billing_postcode(),
		'country'    => $order->get_billing_country(),
	] );

	$yoohw_tir_address = preg_replace( '/<br\s*\/?>/i', "\n", $yoohw_tir_address );
	$yoohw_tir_address = wp_strip_all_tags( $yoohw_tir_address );
}

$yoohw_tir_invoice_email = $order->get_meta( '_yoohw_tir_email' );

if ( empty( $yoohw_tir_invoice_email ) ) {
	$yoohw_tir_invoice_email = $yoohw_tir_saved_tax_email;
}

if ( empty( $yoohw_tir_invoice_email ) ) {
	$yoohw_tir_invoice_email = $order->get_billing_email();
}

$yoohw_tir_invoice_note = $order->get_meta( '_yoohw_tir_note' );

if ( empty( $yoohw_tir_invoice_note ) ) {
	$yoohw_tir_invoice_note = $yoohw_tir_saved_tax_invoice_note;
}

$yoohw_tir_current_order_key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
$yoohw_tir_current_order_key = $yoohw_tir_current_order_key ? sanitize_text_field( $yoohw_tir_current_order_key ) : '';

if ( ! $yoohw_tir_current_order_key || ! hash_equals( $order->get_order_key(), $yoohw_tir_current_order_key ) ) {
	$yoohw_tir_current_order_key = '';
}

?>

<section id="yoohw-tax-invoice-request" class="yoohw-tax-invoice-request">

	<?php if ( $requested === 'yes' ) : ?>

		<?php
		$yoohw_tir_pdf_path       = $order->get_meta( '_yoohw_tir_pdf_path' );
		$status         = $order->get_meta( '_yoohw_tir_status' );
		$yoohw_tir_invoice_number = $order->get_meta( '_yoohw_tir_invoice_number' );
		?>

		<h2><?php esc_html_e( 'Tax Invoice', 'yoohw-tax-invoice-requests' ); ?></h2>

		<table class="woocommerce-table woocommerce-table--order-details shop_table order_details yoohw-tax-invoice-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Status', 'yoohw-tax-invoice-requests' ); ?></th>
					<td>
						<?php echo esc_html( $status ? ucfirst( str_replace( '_', ' ', $status ) ) : __( 'Requested', 'yoohw-tax-invoice-requests' ) ); ?>
					</td>
				</tr>

				<?php if ( $yoohw_tir_invoice_number ) : ?>
					<tr>
						<th><?php esc_html_e( 'Invoice number', 'yoohw-tax-invoice-requests' ); ?></th>
						<td><?php echo esc_html( $yoohw_tir_invoice_number ); ?></td>
					</tr>
				<?php endif; ?>

				<tr>
					<th><?php esc_html_e( 'Request', 'yoohw-tax-invoice-requests' ); ?></th>
					<td><?php esc_html_e( 'Tax invoice already requested.', 'yoohw-tax-invoice-requests' ); ?></td>
				</tr>

				<?php if ( $yoohw_tir_pdf_path && class_exists( 'YoOhw_Tir_Download' ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Download', 'yoohw-tax-invoice-requests' ); ?></th>
						<td>
							<a class="button" href="<?php echo esc_url( YoOhw_Tir_Download::get_download_url( $order->get_id(), $yoohw_tir_current_order_key ) ); ?>">
								<?php esc_html_e( 'Download tax invoice', 'yoohw-tax-invoice-requests' ); ?>
							</a>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

	<?php else : ?>

		<div id="yoohw-tax-modal" class="yoohw-tax-modal" aria-hidden="true">
			<div class="yoohw-tax-modal__overlay" data-yoohw-tax-close></div>

			<div class="yoohw-tax-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="yoohw-tax-modal-title">
				<button type="button" class="yoohw-tax-modal__close" data-yoohw-tax-close aria-label="<?php esc_attr_e( 'Close', 'yoohw-tax-invoice-requests' ); ?>">
					&times;
				</button>

				<h2 id="yoohw-tax-modal-title">
					<?php esc_html_e( 'Request tax invoice', 'yoohw-tax-invoice-requests' ); ?>
				</h2>

				<p class="yoohw-tax-modal__desc">
					<?php esc_html_e( 'Enter your invoice details below. We will generate the tax invoice for this order.', 'yoohw-tax-invoice-requests' ); ?>
				</p>

				<form method="post" class="woocommerce-form yoohw-tax-form">

					<p class="form-row form-row-wide">
						<label for="yoohw_tir_customer_name">
							<?php esc_html_e( 'Company / Customer name', 'yoohw-tax-invoice-requests' ); ?>
							<span class="required">*</span>
						</label>
						<input
							type="text"
							class="input-text"
							name="customer_name"
							id="yoohw_tir_customer_name"
							value="<?php echo esc_attr( $yoohw_tir_customer_name ); ?>"
							required
						>
					</p>

					<p class="form-row form-row-wide">
						<label for="yoohw_tir_id_label">
							<?php esc_html_e( 'Tax ID label', 'yoohw-tax-invoice-requests' ); ?>
						</label>
						<input
							type="text"
							class="input-text"
							name="tax_id_label"
							id="yoohw_tir_id_label"
							value="<?php echo esc_attr( $yoohw_tir_tax_id_label ); ?>"
							placeholder="<?php esc_attr_e( 'VAT number, GST number, ABN, TRN, Tax ID...', 'yoohw-tax-invoice-requests' ); ?>"
						>
					</p>

					<p class="form-row form-row-wide">
						<label for="yoohw_tir_id">
							<?php esc_html_e( 'Tax ID / VAT / GST number', 'yoohw-tax-invoice-requests' ); ?>
							<span class="required">*</span>
						</label>
						<input
							type="text"
							class="input-text"
							name="tax_id"
							id="yoohw_tir_id"
							value="<?php echo esc_attr( $yoohw_tir_tax_id ); ?>"
							required
						>
					</p>

					<p class="form-row form-row-wide">
						<label for="yoohw_tir_country">
							<?php esc_html_e( 'Country / Region', 'yoohw-tax-invoice-requests' ); ?>
							<span class="required">*</span>
						</label>
						<select
							class="input-text"
							name="country"
							id="yoohw_tir_country"
							required
						>
							<?php foreach ( WC()->countries->get_countries() as $yoohw_tir_code => $yoohw_tir_label ) : ?>
								<option value="<?php echo esc_attr( $yoohw_tir_code ); ?>" <?php selected( $yoohw_tir_country, $yoohw_tir_code ); ?>>
									<?php echo esc_html( $yoohw_tir_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p class="form-row form-row-wide">
						<label for="yoohw_tir_address">
							<?php esc_html_e( 'Billing address', 'yoohw-tax-invoice-requests' ); ?>
							<span class="required">*</span>
						</label>
						<textarea
							class="input-text"
							name="address"
							id="yoohw_tir_address"
							rows="4"
							required
						><?php echo esc_textarea( trim( $yoohw_tir_address ) ); ?></textarea>
					</p>

					<p class="form-row form-row-wide">
						<label for="yoohw_tir_email">
							<?php esc_html_e( 'Invoice email', 'yoohw-tax-invoice-requests' ); ?>
							<span class="required">*</span>
						</label>
						<input
							type="email"
							class="input-text"
							name="email"
							id="yoohw_tir_email"
							value="<?php echo esc_attr( $yoohw_tir_invoice_email ); ?>"
							required
						>
					</p>

					<p class="form-row form-row-wide">
						<label for="yoohw_tir_note">
							<?php esc_html_e( 'Invoice note', 'yoohw-tax-invoice-requests' ); ?>
						</label>
						<textarea
							class="input-text"
							name="note"
							id="yoohw_tir_note"
							rows="3"
						><?php echo esc_textarea( $yoohw_tir_invoice_note ); ?></textarea>
					</p>

						<?php wp_nonce_field( 'yoohw_tir_request_invoice' ); ?>

						<input type="hidden" name="yoohw_tir_action" value="1">
						<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php if ( $yoohw_tir_current_order_key ) : ?>
							<input type="hidden" name="order_key" value="<?php echo esc_attr( $yoohw_tir_current_order_key ); ?>">
						<?php endif; ?>

					<p class="form-row form-row-wide yoohw-tax-form__actions">
						<button type="submit" class="button">
							<?php esc_html_e( 'Submit tax invoice request', 'yoohw-tax-invoice-requests' ); ?>
						</button>

						<button type="button" class="button yoohw-tax-secondary-button" data-yoohw-tax-close>
							<?php esc_html_e( 'Cancel', 'yoohw-tax-invoice-requests' ); ?>
						</button>
					</p>

				</form>
			</div>
		</div>

	<?php endif; ?>

</section>
