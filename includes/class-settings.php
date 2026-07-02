<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_Settings {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_tax', [ $this, 'add_tax_invoice_section' ] );
		add_filter( 'woocommerce_get_settings_tax', [ $this, 'add_tax_invoice_settings' ], 10, 2 );
	}

	public function add_tax_invoice_section( $sections ) {
		$sections['yoohw_tir_invoice'] = __( 'Tax invoice', 'yoohw-tax-invoice-requests' );
		return $sections;
	}

	public function add_tax_invoice_settings( $settings, $current_section ) {
		if ( 'yoohw_tir_invoice' !== $current_section ) {
			return $settings;
		}

		return [
			[
				'title' => __( 'General', 'yoohw-tax-invoice-requests' ),
				'type'  => 'title',
				'desc'  => __( 'Configure the general tax invoice behavior.', 'yoohw-tax-invoice-requests' ),
				'id'    => 'yoohw_tir_invoice_general_title',
			],

			[
				'title'    => __( 'Invoice title', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_title',
				'type'     => 'text',
				'default'  => __( 'Tax Invoice', 'yoohw-tax-invoice-requests' ),
				'desc_tip' => __( 'Displayed as the main title on the PDF invoice.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'    => __( 'Allowed order statuses', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_allowed_order_statuses',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:350px;',
				'default'  => [ 'wc-processing', 'wc-completed' ],
				'options'  => wc_get_order_statuses(),
				'desc_tip' => __( 'Customers can request tax invoices only when the order has one of these statuses.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'type' => 'sectionend',
				'id'   => 'yoohw_tir_invoice_general_end',
			],

			[
				'title' => __( 'Seller details', 'yoohw-tax-invoice-requests' ),
				'type'  => 'title',
				'desc'  => __( 'These details appear in the seller section of the generated PDF invoice.', 'yoohw-tax-invoice-requests' ),
				'id'    => 'yoohw_tir_invoice_seller_title',
			],

			[
				'title'    => __( 'Seller legal business name', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_seller_legal_name',
				'type'     => 'text',
				'default'  => get_bloginfo( 'name' ),
				'desc_tip' => __( 'Use your registered legal business name, not just the store display name.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'    => __( 'Seller tax ID label', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_seller_tax_id_label',
				'type'     => 'text',
				'default'  => __( 'Tax ID', 'yoohw-tax-invoice-requests' ),
				'desc_tip' => __( 'Examples: VAT Number, GST Number, ABN, TRN, Tax ID.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'    => __( 'Seller tax ID number', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_seller_tax_id',
				'type'     => 'text',
				'default'  => '',
				'desc_tip' => __( 'Your business tax registration number.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'    => __( 'Seller address', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_seller_address',
				'type'     => 'textarea',
				'default'  => $this->get_default_store_address(),
				'css'      => 'min-width:400px; min-height:90px;',
				'desc_tip' => __( 'Displayed in the seller section of the invoice.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'    => __( 'Seller email', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_seller_email',
				'type'     => 'email',
				'default'  => get_option( 'woocommerce_email_from_address' ),
				'desc_tip' => __( 'Displayed in the seller section of the invoice.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'type' => 'sectionend',
				'id'   => 'yoohw_tir_invoice_seller_end',
			],

			[
				'title' => __( 'Invoice numbering', 'yoohw-tax-invoice-requests' ),
				'type'  => 'title',
				'desc'  => __( 'Configure the invoice number format used for newly generated tax invoices.', 'yoohw-tax-invoice-requests' ),
				'id'    => 'yoohw_tir_invoice_numbering_title',
			],

			[
				'title'    => __( 'Invoice number prefix', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_number_prefix',
				'type'     => 'text',
				'default'  => 'TAX',
				'desc_tip' => __( 'Example: TAX, INV, VAT, GST.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'             => __( 'Next invoice number', 'yoohw-tax-invoice-requests' ),
				'id'                => 'yoohw_tir_next_invoice_number',
				'type'              => 'number',
				'default'           => 1,
				'custom_attributes' => [
					'min'  => 1,
					'step' => 1,
				],
				'desc_tip'          => __( 'Used for the next generated invoice. Existing invoice numbers are not changed.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'type' => 'sectionend',
				'id'   => 'yoohw_tir_invoice_numbering_end',
			],

			[
				'title' => __( 'PDF display', 'yoohw-tax-invoice-requests' ),
				'type'  => 'title',
				'desc'  => __( 'Control what information is shown in the generated tax invoice PDF.', 'yoohw-tax-invoice-requests' ),
				'id'    => 'yoohw_tir_invoice_pdf_display_title',
			],

			[
				'title'   => __( 'Show tax summary by rate', 'yoohw-tax-invoice-requests' ),
				'id'      => 'yoohw_tir_invoice_show_tax_summary',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Show taxable amount and tax amount grouped by tax rate', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'   => __( 'Show tax rate column', 'yoohw-tax-invoice-requests' ),
				'id'      => 'yoohw_tir_invoice_show_tax_rate_column',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Show tax rate for each order item when available', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'    => __( 'Footer / legal note', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_footer_note',
				'type'     => 'textarea',
				'default'  => __( 'This tax invoice was generated automatically from the order records.', 'yoohw-tax-invoice-requests' ),
				'css'      => 'min-width:400px; min-height:90px;',
				'desc_tip' => __( 'Use this for reverse charge, zero-rated export, tax exempt, or other legal notes.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'type' => 'sectionend',
				'id'   => 'yoohw_tir_invoice_pdf_display_end',
			],

			[
				'title' => __( 'Customer emails', 'yoohw-tax-invoice-requests' ),
				'type'  => 'title',
				'desc'  => __( 'Control whether customer order emails include a tax invoice request link.', 'yoohw-tax-invoice-requests' ),
				'id'    => 'yoohw_tir_invoice_customer_emails_title',
			],

			[
				'title'   => __( 'Show request link in customer emails', 'yoohw-tax-invoice-requests' ),
				'id'      => 'yoohw_tir_invoice_email_request_link_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'Add a tax invoice request button to customer order emails when the order status is allowed', 'yoohw-tax-invoice-requests' ),
			],

			[
				'title'    => __( 'Email request message', 'yoohw-tax-invoice-requests' ),
				'id'       => 'yoohw_tir_invoice_email_request_link_message',
				'type'     => 'textarea',
				'default'  => __( 'Need a tax invoice? You can request one from your order details page.', 'yoohw-tax-invoice-requests' ),
				'css'      => 'min-width:400px; min-height:80px;',
				'desc_tip' => __( 'Shown above the request tax invoice button in customer order emails.', 'yoohw-tax-invoice-requests' ),
			],

			[
				'type' => 'sectionend',
				'id'   => 'yoohw_tir_invoice_customer_emails_end',
			],
		];
	}

	private function get_default_store_address() {

		$address_1 = get_option( 'woocommerce_store_address' );
		$address_2 = get_option( 'woocommerce_store_address_2' );
		$city      = get_option( 'woocommerce_store_city' );
		$postcode  = get_option( 'woocommerce_store_postcode' );
		$country_state = get_option( 'woocommerce_default_country' );

		$country = $country_state;
		$state   = '';

		if ( false !== strpos( $country_state, ':' ) ) {
			list( $country, $state ) = explode( ':', $country_state, 2 );
		}

		$countries = WC()->countries->get_countries();
		$states    = WC()->countries->get_states( $country );

		$country_name = isset( $countries[ $country ] ) ? $countries[ $country ] : $country;
		$state_name   = '';

		if ( $state && is_array( $states ) && isset( $states[ $state ] ) ) {
			$state_name = $states[ $state ];
		}

		$city_line = '';
		if ( $city && $state_name ) {
			$city_line = $city . ', ' . $state_name . ' ' . $postcode;
		} elseif ( $city ) {
			$city_line = $city . ' ' . $postcode;
		}

		$lines = array_filter( [
			$address_1,
			$address_2,
			$city_line,
			$country_name,
		] );

		return implode( "\n", $lines );
	}
}