<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_Download {

	const ACTION = 'yoohw_tir_download_invoice';

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_download_invoice' ] );
	}

	public static function get_download_url( $order_id, $order_key = '' ) {
		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return '';
		}

		$args = [
			'yoohw_tir_action' => self::ACTION,
			'order_id'      => $order_id,
		];

		if ( ! empty( $order_key ) ) {
			$args['key'] = $order_key;
		}

		$base_url = wc_get_account_endpoint_url( 'orders' );

		if ( ! is_user_logged_in() && ! empty( $order_key ) ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				$base_url = $order->get_checkout_order_received_url();
			}
		}

		return wp_nonce_url(
			add_query_arg( $args, $base_url ),
			self::ACTION,
			'yoohw_tir_nonce'
		);
	}

	public function maybe_download_invoice() {
		$yoohw_tir_action = filter_input( INPUT_GET, 'yoohw_tir_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $yoohw_tir_action ) || self::ACTION !== sanitize_text_field( $yoohw_tir_action ) ) {
			return;
		}

		$yoohw_tir_nonce = filter_input( INPUT_GET, 'yoohw_tir_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if (
			empty( $yoohw_tir_nonce ) ||
			! wp_verify_nonce(
				sanitize_text_field( $yoohw_tir_nonce ),
				self::ACTION
			)
		) {
			wp_die( esc_html__( 'Security check failed.', 'yoohw-tax-invoice-requests' ), 403 );
		}

		$order_id = absint( filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT ) );

		if ( ! $order_id ) {
			wp_die( esc_html__( 'Invalid order.', 'yoohw-tax-invoice-requests' ), 403 );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'yoohw-tax-invoice-requests' ), 404 );
		}

		if ( ! $this->can_download_invoice( $order ) ) {
			wp_die( esc_html__( 'You are not allowed to download this invoice.', 'yoohw-tax-invoice-requests' ), 403 );
		}

		$file_path = $order->get_meta( '_yoohw_tir_pdf_path' );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Invoice file not found.', 'yoohw-tax-invoice-requests' ), 404 );
		}

		if ( ! class_exists( 'YoOhw_Tir_PDF_Generator' ) || ! YoOhw_Tir_PDF_Generator::is_invoice_file_path( $file_path ) ) {
			wp_die( esc_html__( 'Invalid invoice file path.', 'yoohw-tax-invoice-requests' ), 403 );
		}

		$real_file      = realpath( $file_path );
		$invoice_number = $order->get_meta( '_yoohw_tir_invoice_number' );
		$file_name      = $invoice_number
			? 'tax-invoice-' . $invoice_number . '-order-' . $order->get_id() . '.pdf'
			: basename( $real_file );

		nocache_headers();

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file_name ) . '"' );
		header( 'Content-Length: ' . filesize( $real_file ) );
		header( 'X-Content-Type-Options: nosniff' );

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			wp_die( esc_html__( 'Could not initialize the filesystem.', 'yoohw-tax-invoice-requests' ), 500 );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF binary output must not be escaped. Access and path are validated above.
		echo $wp_filesystem->get_contents( $real_file );
		exit;
	}

	private function can_download_invoice( WC_Order $order ) {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}

		if ( is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() ) {
			return true;
		}

		$order_key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key = $order_key ? sanitize_text_field( $order_key ) : '';

		if ( $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
			return true;
		}

		return false;
	}
}
