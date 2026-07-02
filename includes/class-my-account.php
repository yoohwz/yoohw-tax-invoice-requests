<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_My_Account {

	public function __construct() {
		add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'add_order_action_button' ], 20, 2 );

		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'render_tax_invoice_section' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_order_action_button( $actions, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}

		if ( ! is_user_logged_in() || (int) $order->get_user_id() !== get_current_user_id() ) {
			return $actions;
		}

		$requested     = $order->get_meta( '_yoohw_tir_requested' );
		$pdf_path      = $order->get_meta( '_yoohw_tir_pdf_path' );
		$is_order_view = is_wc_endpoint_url( 'view-order' );

		if ( 'yes' === $requested ) {
			if ( ! $is_order_view && $pdf_path && class_exists( 'YoOhw_Tir_Download' ) ) {
				$actions['yoohw_tir_download_invoice'] = [
					'url'  => YoOhw_Tir_Download::get_download_url( $order->get_id() ),
					'name' => __( 'Download tax invoice', 'yoohw-tax-invoice-requests' ),
				];
			}

			return $actions;
		}

		if ( $this->is_order_status_allowed( $order ) ) {
			$actions['yoohw_tir_request_invoice'] = [
				'url'  => $order->get_view_order_url() . '#yoohw-tax-invoice-request',
				'name' => __( 'Request tax invoice', 'yoohw-tax-invoice-requests' ),
			];
		}

		return $actions;
	}

	public function render_tax_invoice_section( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'yes' !== $order->get_meta( '_yoohw_tir_requested' ) && ! $this->is_order_status_allowed( $order ) ) {
			return;
		}

		if ( ! $this->can_view_tax_invoice_section( $order ) ) {
			return;
		}

		$this->maybe_print_order_notice( $order );

		$requested = $order->get_meta( '_yoohw_tir_requested' );

		wc_get_template(
			'tax-invoice-form.php',
			[
				'order'     => $order,
				'requested' => $requested,
			],
			'',
			YOOHW_TIR_PATH . 'templates/'
		);
	}

	private function can_view_tax_invoice_section( WC_Order $order ) {
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

	private function maybe_print_order_notice( WC_Order $order ) {
		$key = 'yoohw_tir_invoice_notice_' . $order->get_id() . '_' . md5( $order->get_order_key() );
		$notice = get_transient( $key );

		if ( empty( $notice['message'] ) ) {
			return;
		}

		$type = ! empty( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'success';

		wc_print_notice(
			wp_kses_post( $notice['message'] ),
			$type
		);

		delete_transient( $key );
	}

	public function enqueue_assets() {
		if ( ! is_account_page() && ! is_order_received_page() ) {
			return;
		}

		wp_enqueue_style(
			'yoohw-tax-modal',
			YOOHW_TIR_URL . 'assets/css/tax-modal.css',
			[],
			YOOHW_TIR_VERSION
		);

		wp_enqueue_script(
			'yoohw-tax-modal',
			YOOHW_TIR_URL . 'assets/js/tax-modal.js',
			[],
			YOOHW_TIR_VERSION,
			true
		);
	}
}