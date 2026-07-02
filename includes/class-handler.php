<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_Handler {

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_submit' ] );
	}

	public function handle_submit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This only checks whether the plugin form was submitted; the nonce is verified before processing submitted values.
		if ( empty( $_POST['yoohw_tir_action'] ) ) {
			return;
		}

		if (
			empty( $_POST['_wpnonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
				'yoohw_tir_request_invoice'
			)
		) {
			wc_add_notice( __( 'Security error. Please try again.', 'yoohw-tax-invoice-requests' ), 'error' );
			return;
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wc_add_notice( __( 'Invalid order.', 'yoohw-tax-invoice-requests' ), 'error' );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'yoohw-tax-invoice-requests' ), 'error' );
			return;
		}

		if ( ! $this->can_request_invoice( $order ) ) {
			wc_add_notice( __( 'Permission denied.', 'yoohw-tax-invoice-requests' ), 'error' );
			return;
		}

		if ( ! $this->is_order_status_allowed( $order ) ) {
			wc_add_notice(
				__( 'Tax invoice requests are not available for this order status.', 'yoohw-tax-invoice-requests' ),
				'error'
			);
			return;
		}

		$customer_name = isset( $_POST['customer_name'] )
			? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) )
			: '';

		$tax_id_label = isset( $_POST['tax_id_label'] )
			? sanitize_text_field( wp_unslash( $_POST['tax_id_label'] ) )
			: '';

		$tax_id = isset( $_POST['tax_id'] )
			? sanitize_text_field( wp_unslash( $_POST['tax_id'] ) )
			: '';

		$country = isset( $_POST['country'] )
			? sanitize_text_field( wp_unslash( $_POST['country'] ) )
			: '';

		$address = isset( $_POST['address'] )
			? sanitize_textarea_field( wp_unslash( $_POST['address'] ) )
			: '';

		$email = isset( $_POST['email'] )
			? sanitize_email( wp_unslash( $_POST['email'] ) )
			: '';

		$note = isset( $_POST['note'] )
			? sanitize_textarea_field( wp_unslash( $_POST['note'] ) )
			: '';

		if ( empty( $customer_name ) || empty( $tax_id ) || empty( $country ) || empty( $address ) || empty( $email ) ) {
			wc_add_notice( __( 'Please complete all required invoice fields.', 'yoohw-tax-invoice-requests' ), 'error' );
			return;
		}

		if ( ! is_email( $email ) ) {
			wc_add_notice( __( 'Please enter a valid invoice email address.', 'yoohw-tax-invoice-requests' ), 'error' );
			return;
		}

		if ( empty( $tax_id_label ) ) {
			$tax_id_label = __( 'Tax ID / VAT / GST Number', 'yoohw-tax-invoice-requests' );
		}

		$order->update_meta_data( '_yoohw_tir_requested', 'yes' );
		$order->update_meta_data( '_yoohw_tir_status', 'requested' );

		$order->update_meta_data( '_yoohw_tir_company', $customer_name );
		$order->update_meta_data( '_yoohw_tir_tax_id', $tax_id );
		$order->update_meta_data( '_yoohw_tir_tax_id_label', $tax_id_label );
		$order->update_meta_data( '_yoohw_tir_country', $country );
		$order->update_meta_data( '_yoohw_tir_address', $address );
		$order->update_meta_data( '_yoohw_tir_email', $email );
		$order->update_meta_data( '_yoohw_tir_note', $note );

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_yoohw_tir_company', $customer_name );
			update_user_meta( get_current_user_id(), '_yoohw_tir_tax_id', $tax_id );
			update_user_meta( get_current_user_id(), '_yoohw_tir_tax_id_label', $tax_id_label );
			update_user_meta( get_current_user_id(), '_yoohw_tir_country', $country );
			update_user_meta( get_current_user_id(), '_yoohw_tir_address', $address );
			update_user_meta( get_current_user_id(), '_yoohw_tir_email', $email );
			update_user_meta( get_current_user_id(), '_yoohw_tir_note', $note );
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: Tax ID label, 2: Tax ID value. */
				__( 'Customer requested a tax invoice. %1$s: %2$s', 'yoohw-tax-invoice-requests' ),
				$tax_id_label,
				$tax_id
			)
		);

		$order->save();

		if ( class_exists( 'YoOhw_Tir_PDF_Generator' ) ) {
			$pdf_result = YoOhw_Tir_PDF_Generator::generate( $order->get_id() );

			if ( is_wp_error( $pdf_result ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: PDF generation error message. */
						__( 'Tax invoice PDF generation failed: %s', 'yoohw-tax-invoice-requests' ),
						$pdf_result->get_error_message()
					)
				);

				$order->update_meta_data( '_yoohw_tir_status', 'pdf_failed' );
				$order->save();

				$this->set_order_notice(
					$order,
					__( 'Your tax invoice request was submitted, but the PDF could not be generated automatically.', 'yoohw-tax-invoice-requests' ),
					'notice'
				);
			} else {
				WC()->mailer();

				$emails = WC()->mailer()->get_emails();

				if ( ! empty( $emails['YoOhw_Tir_Email_Tax_Invoice'] ) ) {
					$emails['YoOhw_Tir_Email_Tax_Invoice']->trigger( $order->get_id() );
				}

				$this->set_order_notice(
					$order,
					__( 'Your tax invoice request has been submitted and the PDF invoice has been generated.', 'yoohw-tax-invoice-requests' ),
					'success'
				);
			}
		} else {
			$this->set_order_notice(
				$order,
				__( 'Your tax invoice request has been submitted.', 'yoohw-tax-invoice-requests' ),
				'success'
			);
		}

		wp_safe_redirect( $this->get_redirect_url( $order ) );
		exit;
	}

	private function can_request_invoice( WC_Order $order ) {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}

		if ( is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() ) {
			return true;
		}

		$order_key = filter_input( INPUT_POST, 'order_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key = $order_key ? sanitize_text_field( $order_key ) : '';

		if ( $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
			return true;
		}

		return false;
	}

	private function is_order_status_allowed( WC_Order $order ) {
		$allowed_statuses = get_option(
			'yoohw_tir_invoice_allowed_order_statuses',
			[ 'wc-processing', 'wc-completed' ]
		);

		if ( ! is_array( $allowed_statuses ) ) {
			$allowed_statuses = [ 'wc-processing', 'wc-completed' ];
		}

		$current_status = 'wc-' . $order->get_status();

		return in_array( $current_status, $allowed_statuses, true );
	}

	private function get_redirect_url( WC_Order $order ) {
		$order_key = filter_input( INPUT_POST, 'order_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key = $order_key ? sanitize_text_field( $order_key ) : '';

		$is_guest_order_received_context = (
			! is_user_logged_in()
			&& $order_key
			&& hash_equals( $order->get_order_key(), $order_key )
		);

		if ( $is_guest_order_received_context ) {
			return add_query_arg(
				'key',
				rawurlencode( $order_key ),
				$order->get_checkout_order_received_url()
			);
		}

		return $order->get_view_order_url();
	}

	private function set_order_notice( WC_Order $order, $message, $type = 'success' ) {
		$key = 'yoohw_tir_invoice_notice_' . $order->get_id() . '_' . md5( $order->get_order_key() );

		set_transient(
			$key,
			[
				'type'    => sanitize_key( $type ),
				'message' => wp_kses_post( $message ),
			],
			5 * MINUTE_IN_SECONDS
		);
	}
}