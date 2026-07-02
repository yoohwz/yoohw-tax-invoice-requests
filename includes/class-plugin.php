<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_Plugin {

	public function __construct() {
		require_once YOOHW_TIR_PATH . 'includes/class-settings.php';
		require_once YOOHW_TIR_PATH . 'includes/class-pdf-generator.php';
		require_once YOOHW_TIR_PATH . 'includes/class-download.php';
		require_once YOOHW_TIR_PATH . 'includes/class-my-account.php';
		require_once YOOHW_TIR_PATH . 'includes/class-handler.php';
		require_once YOOHW_TIR_PATH . 'includes/class-admin.php';
		require_once YOOHW_TIR_PATH . 'includes/class-email.php';

		YoOhw_Tir_PDF_Generator::maybe_protect_invoice_folder();

		new YoOhw_Tir_Settings();
		new YoOhw_Tir_Download();
		new YoOhw_Tir_My_Account();
		new YoOhw_Tir_Handler();
		new YoOhw_Tir_Admin();
		new YoOhw_Tir_Email();

		add_filter( 'woocommerce_email_classes', [ $this, 'register_emails' ] );
	}

	public function register_emails( $emails ) {
		require_once YOOHW_TIR_PATH . 'includes/emails/class-wc-email-tax-invoice.php';

		$emails['YoOhw_Tir_Email_Tax_Invoice'] = new YoOhw_Tir_Email_Tax_Invoice();

		return $emails;
	}
}
