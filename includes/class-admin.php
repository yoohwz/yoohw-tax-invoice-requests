<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_Admin {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_tax_invoice_metabox' ] );
		add_action( 'admin_post_yoohw_tir_regenerate_invoice', [ $this, 'handle_regenerate_invoice' ] );
		add_action( 'admin_post_yoohw_tir_resend_invoice', [ $this, 'handle_resend_invoice' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
		add_action( 'admin_head', [ $this, 'print_admin_styles' ] );
	}

	public function add_tax_invoice_metabox() {
		add_meta_box(
			'yoohw_tir_invoice_request',
			__( 'Tax invoice request', 'yoohw-tax-invoice-requests' ),
			[ $this, 'render_tax_invoice_metabox' ],
			$this->get_order_screen_id(),
			'side',
			'core'
		);
	}

	private function get_order_screen_id() {
		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			$controller = wc_get_container()->get(
				\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class
			);

			if ( $controller->custom_orders_table_usage_is_enabled() ) {
				return wc_get_page_screen_id( 'shop-order' );
			}
		}

		return 'shop_order';
	}

	public function render_tax_invoice_metabox( $post_or_order_object ) {
		$order = $post_or_order_object instanceof WC_Order
			? $post_or_order_object
			: wc_get_order( $post_or_order_object->ID );

		if ( ! $order ) {
			return;
		}

		if ( ! $this->order_has_invoice_record( $order ) ) {
			echo '<p>' . esc_html__( 'No tax invoice request for this order.', 'yoohw-tax-invoice-requests' ) . '</p>';
			return;
		}

		$pdf_path       = $order->get_meta( '_yoohw_tir_pdf_path' );
		$invoice_number = $order->get_meta( '_yoohw_tir_invoice_number' );
		$status         = $order->get_meta( '_yoohw_tir_status' );
		$tax_id_label   = $order->get_meta( '_yoohw_tir_tax_id_label' );
		$has_valid_pdf  = (
			$pdf_path &&
			file_exists( $pdf_path ) &&
			class_exists( 'YoOhw_Tir_PDF_Generator' ) &&
			YoOhw_Tir_PDF_Generator::is_invoice_file_path( $pdf_path )
		);

		if ( empty( $tax_id_label ) ) {
			$tax_id_label = __( 'Tax ID / VAT / GST Number', 'yoohw-tax-invoice-requests' );
		}

		echo '<p><strong>' . esc_html__( 'Status:', 'yoohw-tax-invoice-requests' ) . '</strong><br>' . esc_html( $status ? $status : 'requested' ) . '</p>';

		if ( $invoice_number ) {
			echo '<p><strong>' . esc_html__( 'Invoice number:', 'yoohw-tax-invoice-requests' ) . '</strong><br>' . esc_html( $invoice_number ) . '</p>';
		}

		echo '<hr>';

		echo '<p><strong>' . esc_html__( 'Company / Customer:', 'yoohw-tax-invoice-requests' ) . '</strong><br>' . esc_html( $order->get_meta( '_yoohw_tir_company' ) ) . '</p>';
		echo '<p><strong>' . esc_html( $tax_id_label ) . ':</strong><br>' . esc_html( $order->get_meta( '_yoohw_tir_tax_id' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Country / Region:', 'yoohw-tax-invoice-requests' ) . '</strong><br>' . esc_html( $order->get_meta( '_yoohw_tir_country' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Invoice email:', 'yoohw-tax-invoice-requests' ) . '</strong><br>' . esc_html( $order->get_meta( '_yoohw_tir_email' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Billing address:', 'yoohw-tax-invoice-requests' ) . '</strong><br>' . nl2br( esc_html( $order->get_meta( '_yoohw_tir_address' ) ) ) . '</p>';

		$note = $order->get_meta( '_yoohw_tir_note' );

		if ( $note ) {
			echo '<p><strong>' . esc_html__( 'Invoice note:', 'yoohw-tax-invoice-requests' ) . '</strong><br>' . nl2br( esc_html( $note ) ) . '</p>';
		}

		if ( $has_valid_pdf && class_exists( 'YoOhw_Tir_Download' ) ) {
		echo '<p class="yoohw-tir-admin-download">';
		echo '<a class="button button-primary yoohw-tir-admin-button" href="' . esc_url( YoOhw_Tir_Download::get_download_url( $order->get_id() ) ) . '">';
		echo esc_html__( 'Download Tax Invoice PDF', 'yoohw-tax-invoice-requests' );
		echo '</a>';
		echo '</p>';
		}

		echo '<hr>';
		echo '<p><strong>' . esc_html__( 'Invoice actions', 'yoohw-tax-invoice-requests' ) . '</strong></p>';
		echo '<div class="yoohw-tir-admin-actions">';

		if ( class_exists( 'YoOhw_Tir_PDF_Generator' ) ) {
			$this->render_admin_action_form(
				'yoohw_tir_regenerate_invoice',
				$order->get_id(),
				__( 'Regenerate invoice', 'yoohw-tax-invoice-requests' )
			);
		}

		if ( $has_valid_pdf ) {
			$this->render_admin_action_form(
				'yoohw_tir_resend_invoice',
				$order->get_id(),
				__( 'Resend invoice email', 'yoohw-tax-invoice-requests' )
			);
		} else {
			echo '<p class="description">' . esc_html__( 'Generate the PDF before resending the invoice email.', 'yoohw-tax-invoice-requests' ) . '</p>';
		}

		echo '</div>';
	}

	public function handle_regenerate_invoice() {
		$order = $this->get_action_order( 'yoohw_tir_regenerate_invoice' );

		if ( is_wp_error( $order ) ) {
			$this->redirect_with_notice( 0, 'error', $order->get_error_message() );
		}

		$order_id          = $order->get_id();
		$previous_pdf_path = $order->get_meta( '_yoohw_tir_pdf_path' );
		$result            = YoOhw_Tir_PDF_Generator::generate( $order_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( $order->get_id(), 'error', $result->get_error_message() );
		}

		$this->delete_replaced_pdf( $previous_pdf_path, $result['path'] );

		$order = wc_get_order( $order_id );

		if ( $order ) {
			$order->add_order_note( __( 'Tax invoice PDF was regenerated by an admin.', 'yoohw-tax-invoice-requests' ) );
			$order->save();
		}

		$this->redirect_with_notice(
			$order_id,
			'success',
			__( 'Tax invoice PDF regenerated.', 'yoohw-tax-invoice-requests' )
		);
	}

	public function handle_resend_invoice() {
		$order = $this->get_action_order( 'yoohw_tir_resend_invoice' );

		if ( is_wp_error( $order ) ) {
			$this->redirect_with_notice( 0, 'error', $order->get_error_message() );
		}

		$pdf_path = $order->get_meta( '_yoohw_tir_pdf_path' );

		if (
			empty( $pdf_path ) ||
			! file_exists( $pdf_path ) ||
			! class_exists( 'YoOhw_Tir_PDF_Generator' ) ||
			! YoOhw_Tir_PDF_Generator::is_invoice_file_path( $pdf_path )
		) {
			$this->redirect_with_notice(
				$order->get_id(),
				'error',
				__( 'Tax invoice PDF file was not found. Regenerate it before resending.', 'yoohw-tax-invoice-requests' )
			);
		}

		$email = $this->get_tax_invoice_email();

		if ( ! $email ) {
			$this->redirect_with_notice(
				$order->get_id(),
				'error',
				__( 'Tax invoice email class is not available.', 'yoohw-tax-invoice-requests' )
			);
		}

		$sent = $email->trigger( $order->get_id() );

		if ( ! $sent ) {
			$this->redirect_with_notice(
				$order->get_id(),
				'error',
				__( 'Tax invoice email could not be sent. Check the recipient and WooCommerce email settings.', 'yoohw-tax-invoice-requests' )
			);
		}

		$order->add_order_note( __( 'Tax invoice email was resent by an admin.', 'yoohw-tax-invoice-requests' ) );
		$order->save();

		$this->redirect_with_notice(
			$order->get_id(),
			'success',
			__( 'Tax invoice email resent.', 'yoohw-tax-invoice-requests' )
		);
	}

	public function render_admin_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		if ( empty( $_GET['yoohw_tir_notice'] ) || empty( $_GET['yoohw_tir_notice_type'] ) ) {
			return;
		}

		$type = sanitize_key( wp_unslash( $_GET['yoohw_tir_notice_type'] ) );

		if ( ! in_array( $type, [ 'success', 'error', 'warning', 'info' ], true ) ) {
			$type = 'info';
		}

		$message = sanitize_text_field( wp_unslash( $_GET['yoohw_tir_notice'] ) );

		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	public function print_admin_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->id, [ 'shop_order', wc_get_page_screen_id( 'shop-order' ) ], true ) ) {
			return;
		}
		?>
		<style>
			#yoohw_tir_invoice_request .yoohw-tir-admin-button {
				align-items: center;
				box-sizing: border-box;
				display: inline-flex;
				justify-content: center;
				line-height: 1.35;
				min-height: 38px;
				padding: 8px 12px;
				text-align: center;
				white-space: normal;
				width: 100%;
			}

			#yoohw_tir_invoice_request .yoohw-tir-admin-download {
				margin: 14px 0 18px;
			}

			#yoohw_tir_invoice_request .yoohw-tir-admin-actions {
				display: flex;
				flex-direction: column;
				gap: 10px;
				margin-top: 10px;
			}

			#yoohw_tir_invoice_request .yoohw-tir-admin-action-form {
				margin: 0;
			}
		</style>
		<?php
	}

	private function render_admin_action_form( $action, $order_id, $label ) {
		$order_id = absint( $order_id );

		echo '<form class="yoohw-tir-admin-action-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( sanitize_key( $action ) ) . '">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '">';
		wp_nonce_field( $action . '_' . $order_id );
		echo '<button type="submit" class="button yoohw-tir-admin-button">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	private function get_action_order( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) ) {
			return new WP_Error( 'permission_denied', __( 'You are not allowed to manage tax invoices.', 'yoohw-tax-invoice-requests' ) );
		}

		$order_id = isset( $_REQUEST['order_id'] ) ? absint( wp_unslash( $_REQUEST['order_id'] ) ) : 0;

		if ( ! $order_id ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'yoohw-tax-invoice-requests' ) );
		}

		check_admin_referer( $action . '_' . $order_id );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'order_not_found', __( 'Order not found.', 'yoohw-tax-invoice-requests' ) );
		}

		if ( ! $this->order_has_invoice_record( $order ) ) {
			return new WP_Error( 'invoice_not_requested', __( 'This order does not have a tax invoice request.', 'yoohw-tax-invoice-requests' ) );
		}

		return $order;
	}

	private function order_has_invoice_record( WC_Order $order ) {
		$meta_keys = [
			'_yoohw_tir_requested',
			'_yoohw_tir_pdf_path',
			'_yoohw_tir_invoice_number',
			'_yoohw_tir_status',
			'_yoohw_tir_company',
			'_yoohw_tir_tax_id',
			'_yoohw_tir_email',
		];

		foreach ( $meta_keys as $meta_key ) {
			if ( '' !== (string) $order->get_meta( $meta_key ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_tax_invoice_email() {
		if ( ! class_exists( 'YoOhw_Tir_Email_Tax_Invoice' ) ) {
			require_once YOOHW_TIR_PATH . 'includes/emails/class-wc-email-tax-invoice.php';
		}

		if ( ! class_exists( 'YoOhw_Tir_Email_Tax_Invoice' ) ) {
			return null;
		}

		WC()->mailer();

		$emails = WC()->mailer()->get_emails();

		if ( ! empty( $emails['YoOhw_Tir_Email_Tax_Invoice'] ) ) {
			return $emails['YoOhw_Tir_Email_Tax_Invoice'];
		}

		return new YoOhw_Tir_Email_Tax_Invoice();
	}

	private function redirect_with_notice( $order_id, $type, $message ) {
		$redirect_url = $order_id ? $this->get_order_edit_url( $order_id ) : wp_get_referer();

		if ( ! $redirect_url ) {
			$redirect_url = admin_url( 'edit.php?post_type=shop_order' );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'yoohw_tir_notice_type' => sanitize_key( $type ),
					'yoohw_tir_notice'      => wp_strip_all_tags( $message ),
				],
				$redirect_url
			)
		);
		exit;
	}

	private function get_order_edit_url( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
			return $order->get_edit_order_url();
		}

		return admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' );
	}

	private function delete_replaced_pdf( $previous_pdf_path, $new_pdf_path ) {
		if ( empty( $previous_pdf_path ) || empty( $new_pdf_path ) || $previous_pdf_path === $new_pdf_path ) {
			return;
		}

		if (
			file_exists( $previous_pdf_path ) &&
			class_exists( 'YoOhw_Tir_PDF_Generator' ) &&
			YoOhw_Tir_PDF_Generator::is_invoice_file_path( $previous_pdf_path )
		) {
			wp_delete_file( $previous_pdf_path );
		}
	}
}
