<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( $email_heading ) . "\n\n";

printf(
	/* translators: %s: Customer first name. */
	esc_html__( 'Hi %s,', 'yoohw-tax-invoice-requests' ),
	esc_html( $order->get_billing_first_name() )
);

echo "\n\n";

printf(
	/* translators: %s: WooCommerce order number. */
	esc_html__( 'Your tax invoice for order #%s has been generated and is attached to this email.', 'yoohw-tax-invoice-requests' ),
	esc_html( $order->get_order_number() )
);

echo "\n\n";

if ( $order->get_meta( '_yoohw_tir_invoice_number' ) ) {
	echo esc_html__( 'Invoice number:', 'yoohw-tax-invoice-requests' ) . ' ';
	echo esc_html( $order->get_meta( '_yoohw_tir_invoice_number' ) );
	echo "\n\n";
}

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Required WooCommerce email footer text filter.
echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
