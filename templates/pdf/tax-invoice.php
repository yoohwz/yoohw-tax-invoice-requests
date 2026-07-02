<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'yoohw_tir_country_state_name' ) ) {
	function yoohw_tir_country_state_name( $yoohw_tir_country_code, $state_code = '' ) {
		$yoohw_tir_country_code = strtoupper( trim( (string) $yoohw_tir_country_code ) );
		$state_code   = strtoupper( trim( (string) $state_code ) );

		$countries = WC()->countries->get_countries();
		$states    = WC()->countries->get_states( $yoohw_tir_country_code );

		$yoohw_tir_country_name = isset( $countries[ $yoohw_tir_country_code ] ) ? $countries[ $yoohw_tir_country_code ] : $yoohw_tir_country_code;

		if ( $state_code && is_array( $states ) && isset( $states[ $state_code ] ) ) {
			return $states[ $state_code ] . ', ' . $yoohw_tir_country_name;
		}

		return $yoohw_tir_country_name;
	}
}

if ( ! function_exists( 'yoohw_tir_state_name_only' ) ) {
	function yoohw_tir_state_name_only( $yoohw_tir_country_code, $state_code ) {
		$yoohw_tir_country_code = strtoupper( trim( (string) $yoohw_tir_country_code ) );
		$state_code   = strtoupper( trim( (string) $state_code ) );

		$states = WC()->countries->get_states( $yoohw_tir_country_code );

		if ( $state_code && is_array( $states ) && isset( $states[ $state_code ] ) ) {
			return $states[ $state_code ];
		}

		return $state_code;
	}
}

if ( ! function_exists( 'yoohw_tir_get_item_tax_rates' ) ) {
	function yoohw_tir_get_item_tax_rates( $yoohw_tir_item ) {
		$tax_rate_labels = [];
		$yoohw_tir_item_taxes      = $yoohw_tir_item->get_taxes();

		if ( empty( $yoohw_tir_item_taxes['total'] ) || ! is_array( $yoohw_tir_item_taxes['total'] ) ) {
			return '-';
		}

		foreach ( $yoohw_tir_item_taxes['total'] as $yoohw_tir_rate_id => $yoohw_tir_tax_amount ) {
			if ( (float) $yoohw_tir_tax_amount <= 0 ) {
				continue;
			}

			$yoohw_tir_rate = WC_Tax::_get_tax_rate( $yoohw_tir_rate_id );

			if ( ! empty( $yoohw_tir_rate['tax_rate'] ) ) {
				$tax_rate_labels[] = wc_format_decimal( $yoohw_tir_rate['tax_rate'], 2 ) . '%';
			}
		}

		$tax_rate_labels = array_unique( array_filter( $tax_rate_labels ) );

		return ! empty( $tax_rate_labels ) ? implode( ', ', $tax_rate_labels ) : '-';
	}
}

if ( ! function_exists( 'yoohw_tir_get_order_tax_summary_rows' ) ) {
	function yoohw_tir_get_order_tax_summary_rows( WC_Order $order ) {
		$rows            = [];
		$tax_item_labels = [];

		foreach ( $order->get_items( 'tax' ) as $yoohw_tir_tax_item ) {
			$tax_item_labels[ absint( $yoohw_tir_tax_item->get_rate_id() ) ] = $yoohw_tir_tax_item->get_label();
		}

		foreach ( [ 'line_item', 'fee', 'shipping' ] as $yoohw_tir_item_type ) {
			foreach ( $order->get_items( $yoohw_tir_item_type ) as $yoohw_tir_item ) {
				if ( ! method_exists( $yoohw_tir_item, 'get_taxes' ) || ! method_exists( $yoohw_tir_item, 'get_total' ) ) {
					continue;
				}

				$yoohw_tir_item_taxes = $yoohw_tir_item->get_taxes();

				if ( empty( $yoohw_tir_item_taxes['total'] ) || ! is_array( $yoohw_tir_item_taxes['total'] ) ) {
					continue;
				}

				$yoohw_tir_taxable_amount = (float) $yoohw_tir_item->get_total();

				foreach ( $yoohw_tir_item_taxes['total'] as $yoohw_tir_rate_id => $yoohw_tir_tax_amount ) {
					$yoohw_tir_rate_id    = absint( $yoohw_tir_rate_id );
					$yoohw_tir_tax_amount = (float) $yoohw_tir_tax_amount;

					if ( $yoohw_tir_tax_amount <= 0 ) {
						continue;
					}

					if ( ! isset( $rows[ $yoohw_tir_rate_id ] ) ) {
						$yoohw_tir_rate       = WC_Tax::_get_tax_rate( $yoohw_tir_rate_id );
						$yoohw_tir_rate_label = isset( $tax_item_labels[ $yoohw_tir_rate_id ] ) ? $tax_item_labels[ $yoohw_tir_rate_id ] : '';

						if ( empty( $yoohw_tir_rate_label ) && ! empty( $yoohw_tir_rate['tax_rate_name'] ) ) {
							$yoohw_tir_rate_label = $yoohw_tir_rate['tax_rate_name'];
						}

						if ( empty( $yoohw_tir_rate_label ) ) {
							$yoohw_tir_rate_label = __( 'Tax', 'yoohw-tax-invoice-requests' );
						}

						if ( ! empty( $yoohw_tir_rate['tax_rate'] ) ) {
							$yoohw_tir_rate_label .= ' (' . wc_format_decimal( $yoohw_tir_rate['tax_rate'], 2 ) . '%)';
						}

						$rows[ $yoohw_tir_rate_id ] = [
							'label'          => $yoohw_tir_rate_label,
							'taxable_amount' => 0,
							'tax_amount'     => 0,
						];
					}

					$rows[ $yoohw_tir_rate_id ]['taxable_amount'] += $yoohw_tir_taxable_amount;
					$rows[ $yoohw_tir_rate_id ]['tax_amount']     += $yoohw_tir_tax_amount;
				}
			}
		}

		return array_filter(
			$rows,
			function( $row ) {
				return ! empty( $row['tax_amount'] );
			}
		);
	}
}

/**
 * Buyer/customer invoice details.
 */
$yoohw_tir_customer_name   = $order->get_meta( '_yoohw_tir_company' );
$yoohw_tir_tax_id          = $order->get_meta( '_yoohw_tir_tax_id' );
$yoohw_tir_tax_id_label    = $order->get_meta( '_yoohw_tir_tax_id_label' );
$yoohw_tir_country         = $order->get_meta( '_yoohw_tir_country' );
$yoohw_tir_billing_address = $order->get_meta( '_yoohw_tir_address' );
$yoohw_tir_invoice_email   = $order->get_meta( '_yoohw_tir_email' );
$yoohw_tir_invoice_note    = $order->get_meta( '_yoohw_tir_note' );

if ( empty( $yoohw_tir_tax_id_label ) ) {
	$yoohw_tir_tax_id_label = __( 'Tax ID / VAT / GST Number', 'yoohw-tax-invoice-requests' );
}

if ( empty( $yoohw_tir_country ) ) {
	$yoohw_tir_country = $order->get_billing_country();
}

/**
 * Settings-controlled seller/invoice details.
 */
$yoohw_tir_invoice_title = get_option(
	'yoohw_tir_invoice_title',
	__( 'Tax Invoice', 'yoohw-tax-invoice-requests' )
);

$yoohw_tir_seller_name = get_option(
	'yoohw_tir_invoice_seller_legal_name',
	get_bloginfo( 'name' )
);

if ( empty( $yoohw_tir_seller_name ) ) {
	$yoohw_tir_seller_name = get_bloginfo( 'name' );
}

$yoohw_tir_seller_tax_id_label = get_option(
	'yoohw_tir_invoice_seller_tax_id_label',
	__( 'Tax ID', 'yoohw-tax-invoice-requests' )
);

$yoohw_tir_seller_tax_id = get_option( 'yoohw_tir_invoice_seller_tax_id', '' );

$yoohw_tir_seller_address = get_option( 'yoohw_tir_invoice_seller_address', '' );

if ( empty( $yoohw_tir_seller_address ) ) {
	$yoohw_tir_seller_country_state = get_option( 'woocommerce_default_country' );
	$yoohw_tir_seller_country       = $yoohw_tir_seller_country_state;
	$yoohw_tir_seller_state         = '';

	if ( false !== strpos( $yoohw_tir_seller_country_state, ':' ) ) {
		list( $yoohw_tir_seller_country, $yoohw_tir_seller_state ) = explode( ':', $yoohw_tir_seller_country_state, 2 );
	}

	$yoohw_tir_seller_country_name = yoohw_tir_country_state_name( $yoohw_tir_seller_country );
	$yoohw_tir_seller_state_name   = yoohw_tir_state_name_only( $yoohw_tir_seller_country, $yoohw_tir_seller_state );

	$yoohw_tir_seller_city     = get_option( 'woocommerce_store_city' );
	$yoohw_tir_seller_postcode = get_option( 'woocommerce_store_postcode' );

	$yoohw_tir_seller_city_line = '';

	if ( $yoohw_tir_seller_city && $yoohw_tir_seller_state_name ) {
		$yoohw_tir_seller_city_line = trim( $yoohw_tir_seller_city . ', ' . $yoohw_tir_seller_state_name . ' ' . $yoohw_tir_seller_postcode );
	} elseif ( $yoohw_tir_seller_city ) {
		$yoohw_tir_seller_city_line = trim( $yoohw_tir_seller_city . ' ' . $yoohw_tir_seller_postcode );
	}

	$yoohw_tir_seller_address = implode(
		"\n",
		array_filter(
			[
				get_option( 'woocommerce_store_address' ),
				get_option( 'woocommerce_store_address_2' ),
				$yoohw_tir_seller_city_line,
				$yoohw_tir_seller_country_name,
			]
		)
	);
}

$yoohw_tir_seller_email = get_option(
	'yoohw_tir_invoice_seller_email',
	get_option( 'woocommerce_email_from_address' )
);

$yoohw_tir_show_tax_summary = 'yes' === get_option(
	'yoohw_tir_invoice_show_tax_summary',
	'yes'
);

$yoohw_tir_show_tax_rate_column = 'yes' === get_option(
	'yoohw_tir_invoice_show_tax_rate_column',
	'yes'
);

$yoohw_tir_footer_note = get_option(
	'yoohw_tir_invoice_footer_note',
	__( 'This tax invoice was generated automatically from the order records.', 'yoohw-tax-invoice-requests' )
);

/**
 * Customer address formatting.
 * Converts:
 * Portland, OR 97230
 * into:
 * Portland, Oregon 97230
 *
 * Then appends full country name:
 * United States
 */
$yoohw_tir_customer_country_name    = yoohw_tir_country_state_name( $yoohw_tir_country );
$yoohw_tir_customer_state_name      = yoohw_tir_state_name_only( $yoohw_tir_country, $order->get_billing_state() );
$yoohw_tir_customer_display_address = trim( (string) $yoohw_tir_billing_address );

if ( empty( $yoohw_tir_customer_display_address ) ) {
	$yoohw_tir_customer_display_address = WC()->countries->get_formatted_address(
		[
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'company'    => $order->get_billing_company(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
		]
	);

	$yoohw_tir_customer_display_address = preg_replace( '/<br\s*\/?>/i', "\n", $yoohw_tir_customer_display_address );
	$yoohw_tir_customer_display_address = wp_strip_all_tags( $yoohw_tir_customer_display_address );
}

if ( $order->get_billing_state() && $yoohw_tir_customer_state_name ) {
	$yoohw_tir_customer_display_address = str_replace(
		', ' . $order->get_billing_state() . ' ',
		', ' . $yoohw_tir_customer_state_name . ' ',
		$yoohw_tir_customer_display_address
	);
}

if (
	$yoohw_tir_customer_country_name &&
	false === stripos( $yoohw_tir_customer_display_address, $yoohw_tir_customer_country_name )
) {
	$yoohw_tir_customer_display_address .= "\n" . $yoohw_tir_customer_country_name;
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
</head>
<body>

	<div class="invoice-top-bar"></div>

	<table class="header">
		<tr>
			<td style="width:55%; vertical-align:top;">
				<div class="invoice-title">
					<?php echo esc_html( $yoohw_tir_invoice_title ); ?>
				</div>
			</td>

			<td style="width:45%; vertical-align:top;">
				<div class="invoice-meta-card">
					<strong><?php esc_html_e( 'Invoice number:', 'yoohw-tax-invoice-requests' ); ?></strong>
					<?php echo esc_html( $invoice_number ); ?><br>

					<strong><?php esc_html_e( 'Invoice date:', 'yoohw-tax-invoice-requests' ); ?></strong>
					<?php echo esc_html( $generated_date ); ?><br>

					<strong><?php esc_html_e( 'Order number:', 'yoohw-tax-invoice-requests' ); ?></strong>
					#<?php echo esc_html( $order->get_order_number() ); ?><br>

					<?php if ( $order->get_date_created() ) : ?>
						<strong><?php esc_html_e( 'Order date:', 'yoohw-tax-invoice-requests' ); ?></strong>
						<?php echo esc_html( wc_format_datetime( $order->get_date_created(), wc_date_format() ) ); ?><br>
					<?php endif; ?>

					<strong><?php esc_html_e( 'Currency:', 'yoohw-tax-invoice-requests' ); ?></strong>
					<?php echo esc_html( $order->get_currency() ); ?><br>

					<?php if ( $order->get_payment_method_title() ) : ?>
						<strong><?php esc_html_e( 'Payment method:', 'yoohw-tax-invoice-requests' ); ?></strong>
						<?php echo esc_html( $order->get_payment_method_title() ); ?>
					<?php endif; ?>
				</div>
			</td>
		</tr>
	</table>

	<table class="columns">
		<tr>
			<td class="column" style="border:0; padding:0 12px 0 0;">
				<div class="info-box">
					<div class="section-title"><?php esc_html_e( 'Seller', 'yoohw-tax-invoice-requests' ); ?></div>

					<strong><?php echo esc_html( $yoohw_tir_seller_name ); ?></strong><br>

					<?php if ( $yoohw_tir_seller_address ) : ?>
						<?php echo nl2br( esc_html( $yoohw_tir_seller_address ) ); ?><br>
					<?php endif; ?>

					<?php if ( $yoohw_tir_seller_tax_id ) : ?>
						<?php echo esc_html( $yoohw_tir_seller_tax_id_label ); ?>:
						<?php echo esc_html( $yoohw_tir_seller_tax_id ); ?><br>
					<?php endif; ?>

					<?php if ( $yoohw_tir_seller_email ) : ?>
						<?php esc_html_e( 'Email:', 'yoohw-tax-invoice-requests' ); ?>
						<?php echo esc_html( $yoohw_tir_seller_email ); ?><br>
					<?php endif; ?>
				</div>
			</td>

			<td class="column" style="border:0; padding:0 0 0 12px;">
				<div class="info-box">
					<div class="section-title"><?php esc_html_e( 'Invoice To', 'yoohw-tax-invoice-requests' ); ?></div>

					<strong><?php echo esc_html( $yoohw_tir_customer_name ); ?></strong><br>

					<?php if ( $yoohw_tir_customer_display_address ) : ?>
						<?php echo nl2br( esc_html( $yoohw_tir_customer_display_address ) ); ?><br>
					<?php endif; ?>

					<?php if ( $yoohw_tir_tax_id ) : ?>
						<?php echo esc_html( $yoohw_tir_tax_id_label ); ?>:
						<?php echo esc_html( $yoohw_tir_tax_id ); ?><br>
					<?php endif; ?>

					<?php if ( $yoohw_tir_invoice_email ) : ?>
						<?php esc_html_e( 'Email:', 'yoohw-tax-invoice-requests' ); ?>
						<?php echo esc_html( $yoohw_tir_invoice_email ); ?>
					<?php endif; ?>
				</div>
			</td>
		</tr>
	</table>

	<div class="section-title"><?php esc_html_e( 'Order Items', 'yoohw-tax-invoice-requests' ); ?></div>

	<table class="items-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Description', 'yoohw-tax-invoice-requests' ); ?></th>
				<th class="text-right"><?php esc_html_e( 'Qty', 'yoohw-tax-invoice-requests' ); ?></th>
				<th class="text-right"><?php esc_html_e( 'Subtotal', 'yoohw-tax-invoice-requests' ); ?></th>

				<?php if ( $yoohw_tir_show_tax_rate_column ) : ?>
					<th class="text-right"><?php esc_html_e( 'Tax rate', 'yoohw-tax-invoice-requests' ); ?></th>
				<?php endif; ?>

				<th class="text-right"><?php esc_html_e( 'Tax', 'yoohw-tax-invoice-requests' ); ?></th>
				<th class="text-right"><?php esc_html_e( 'Total', 'yoohw-tax-invoice-requests' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php foreach ( $order->get_items( 'line_item' ) as $yoohw_tir_item ) : ?>
				<tr>
					<td>
						<?php echo esc_html( $yoohw_tir_item->get_name() ); ?>

						<?php
						$yoohw_tir_item_meta = wc_display_item_meta(
							$yoohw_tir_item,
							[
								'before'    => '<br><small>',
								'after'     => '</small>',
								'separator' => '<br>',
								'echo'      => false,
							]
						);

						if ( $yoohw_tir_item_meta ) {
							echo wp_kses_post( $yoohw_tir_item_meta );
						}
						?>
					</td>

					<td class="text-right">
						<?php echo esc_html( $yoohw_tir_item->get_quantity() ); ?>
					</td>

					<td class="text-right">
						<?php echo wp_kses_post( wc_price( $yoohw_tir_item->get_subtotal(), [ 'currency' => $order->get_currency() ] ) ); ?>
					</td>

					<?php if ( $yoohw_tir_show_tax_rate_column ) : ?>
						<td class="text-right">
							<?php echo esc_html( yoohw_tir_get_item_tax_rates( $yoohw_tir_item ) ); ?>
						</td>
					<?php endif; ?>

					<td class="text-right">
						<?php echo wp_kses_post( wc_price( $yoohw_tir_item->get_subtotal_tax(), [ 'currency' => $order->get_currency() ] ) ); ?>
					</td>

					<td class="text-right">
						<?php
						echo wp_kses_post(
							wc_price(
								$yoohw_tir_item->get_total() + $yoohw_tir_item->get_total_tax(),
								[ 'currency' => $order->get_currency() ]
							)
						);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<table class="totals">
		<?php foreach ( $order->get_order_item_totals() as $yoohw_tir_total ) : ?>
			<tr>
				<th><?php echo wp_kses_post( $yoohw_tir_total['label'] ); ?></th>
				<td class="text-right"><?php echo wp_kses_post( $yoohw_tir_total['value'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>

	<?php $yoohw_tir_tax_summary_rows = yoohw_tir_get_order_tax_summary_rows( $order ); ?>
	<?php if ( $yoohw_tir_show_tax_summary && wc_tax_enabled() && ! empty( $yoohw_tir_tax_summary_rows ) ) : ?>
		<div class="section-title" style="margin-top:24px;">
			<?php esc_html_e( 'Tax summary', 'yoohw-tax-invoice-requests' ); ?>
		</div>

		<table class="tax-summary-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tax Rate', 'yoohw-tax-invoice-requests' ); ?></th>
					<th class="text-right"><?php esc_html_e( 'Taxable amount', 'yoohw-tax-invoice-requests' ); ?></th>
					<th class="text-right"><?php esc_html_e( 'Tax amount', 'yoohw-tax-invoice-requests' ); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ( $yoohw_tir_tax_summary_rows as $yoohw_tir_tax_row ) : ?>
					<tr>
						<td><?php echo esc_html( $yoohw_tir_tax_row['label'] ); ?></td>

						<td class="text-right">
							<?php echo wp_kses_post( wc_price( $yoohw_tir_tax_row['taxable_amount'], [ 'currency' => $order->get_currency() ] ) ); ?>
						</td>

						<td class="text-right">
							<?php echo wp_kses_post( wc_price( $yoohw_tir_tax_row['tax_amount'], [ 'currency' => $order->get_currency() ] ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $yoohw_tir_invoice_note ) : ?>
		<div class="note">
			<strong><?php esc_html_e( 'Invoice note:', 'yoohw-tax-invoice-requests' ); ?></strong><br>
			<?php echo nl2br( esc_html( $yoohw_tir_invoice_note ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $yoohw_tir_footer_note ) : ?>
		<div class="footer">
			<?php echo nl2br( esc_html( $yoohw_tir_footer_note ) ); ?>
		</div>
	<?php endif; ?>

</body>
</html>
