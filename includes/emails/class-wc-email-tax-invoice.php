<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'YoOhw_Tir_Email_Tax_Invoice' ) ) {

	class YoOhw_Tir_Email_Tax_Invoice extends WC_Email {

		public function __construct() {
			$this->id             = 'yoohw_tir_invoice';
			$this->customer_email = true;
			$this->title          = __( 'Tax invoice', 'yoohw-tax-invoice-requests' );
			$this->description    = __( 'This email is sent to the customer after a tax invoice PDF is generated.', 'yoohw-tax-invoice-requests' );

			$this->template_html  = 'emails/customer-tax-invoice.php';
			$this->template_plain = 'emails/plain/customer-tax-invoice.php';
			$this->template_base  = YOOHW_TIR_PATH . 'templates/';

			$this->placeholders = [
				'{site_title}'     => $this->get_blogname(),
				'{order_number}'   => '',
				'{invoice_number}' => '',
			];

			parent::__construct();

			$this->recipient = '';
		}

		public function trigger( $order_id ) {
			$this->object = wc_get_order( $order_id );

			if ( ! $this->object ) {
				return false;
			}

			$this->recipient = $this->object->get_meta( '_yoohw_tir_email' );

			if ( ! $this->recipient ) {
				$this->recipient = $this->object->get_billing_email();
			}

			$this->placeholders['{order_number}']   = $this->object->get_order_number();
			$this->placeholders['{invoice_number}'] = $this->object->get_meta( '_yoohw_tir_invoice_number' );

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return false;
			}

			return $this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}

		public function get_default_subject() {
			return __( 'Your tax invoice for order #{order_number}', 'yoohw-tax-invoice-requests' );
		}

		public function get_default_heading() {
			return __( 'Your tax invoice is ready', 'yoohw-tax-invoice-requests' );
		}

		public function get_default_additional_content() {
			return __( 'Your tax invoice PDF is attached to this email.', 'yoohw-tax-invoice-requests' );
		}

		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				[
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				],
				'',
				$this->template_base
			);
		}

		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				[
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				],
				'',
				$this->template_base
			);
		}

		public function get_attachments() {
			$attachments = [];

			if ( $this->object instanceof WC_Order ) {
				$pdf_path = $this->object->get_meta( '_yoohw_tir_pdf_path' );

				if (
					$pdf_path &&
					file_exists( $pdf_path ) &&
					class_exists( 'YoOhw_Tir_PDF_Generator' ) &&
					YoOhw_Tir_PDF_Generator::is_invoice_file_path( $pdf_path )
				) {
					$attachments[] = $pdf_path;
				}
			}

			return $attachments;
		}
	}
}
