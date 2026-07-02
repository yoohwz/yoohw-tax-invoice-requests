<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_Email {

	public function __construct() {
		add_action(
			'woocommerce_email_after_order_table',
			[ $this, 'maybe_add_request_tax_invoice_link' ],
			20,
			4
		);
	}

	public function maybe_add_request_tax_invoice_link( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $sent_to_admin ) {
			return;
		}

		if ( 'yes' !== get_option( 'yoohw_tir_invoice_email_request_link_enabled', 'no' ) ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_yoohw_tir_requested' ) ) {
			return;
		}

		if ( ! $this->is_order_status_allowed( $order ) ) {
			return;
		}

		if ( ! $this->is_customer_order_email( $email ) ) {
			return;
		}

		$url = $order->get_checkout_order_received_url();

		$url = add_query_arg(
			'key',
			rawurlencode( $order->get_order_key() ),
			$url
		);

		$url .= '#yoohw-tax-invoice-request';

		$message = get_option(
			'yoohw_tir_invoice_email_request_link_message',
			__( 'Need a tax invoice? You can request one from your order details page.', 'yoohw-tax-invoice-requests' )
		);

		if ( $plain_text ) {
			echo "\n" . esc_html( wp_strip_all_tags( $message ) ) . "\n";
			echo esc_url( $url ) . "\n\n";
			return;
		}

		$base_color        = get_option( 'woocommerce_email_base_color', '#111111' );
		$button_text_color = wc_light_or_dark( $base_color, '#202020', '#ffffff' );

		echo '<div class="yoohw-tax-invoice-email-block" style="margin:24px 0; padding:16px; border:1px solid #e5e5e5;">';
		echo '<p style="margin:0 0 12px;">' . esc_html( $message ) . '</p>';
		echo '<p style="margin:0;">';
		echo '<a href="' . esc_url( $url ) . '" class="button" style="display:inline-block; padding:10px 14px; background-color:' . esc_attr( $base_color ) . '; color:' . esc_attr( $button_text_color ) . '; text-decoration:none;">';
		echo esc_html__( 'Request tax invoice', 'yoohw-tax-invoice-requests' );
		echo '</a>';
		echo '</p>';
		echo '</div>';
	}

	private function is_customer_order_email( $email ) {
		if ( ! is_object( $email ) || empty( $email->id ) ) {
			return false;
		}

		$allowed_email_ids = [
			'customer_processing_order',
			'customer_completed_order',
			'customer_on_hold_order',
			'customer_invoice',
		];

		return in_array( $email->id, $allowed_email_ids, true );
	}

	private function is_order_status_allowed( WC_Order $order ) {
		$allowed_statuses = get_option(
			'yoohw_tir_invoice_allowed_order_statuses',
			[ 'wc-processing', 'wc-completed' ]
		);

		if ( ! is_array( $allowed_statuses ) ) {
			$allowed_statuses = [ 'wc-processing', 'wc-completed' ];
		}

		return in_array( 'wc-' . $order->get_status(), $allowed_statuses, true );
	}
}