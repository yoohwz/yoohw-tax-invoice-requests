<?php

use Dompdf\Dompdf;
use Dompdf\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoOhw_Tir_PDF_Generator {

	const META_PDF_PATH       = '_yoohw_tir_pdf_path';
	const META_INVOICE_NUMBER = '_yoohw_tir_invoice_number';
	const META_GENERATED_AT   = '_yoohw_tir_generated_at';
	const INVOICE_DIR         = 'yoohw-tax-invoices';
	const INVOICE_NUMBER_LOCK = 'yoohw_tir_invoice_number_lock';

	public static function maybe_protect_invoice_folder() {
		$base_dir = self::get_invoice_base_dir();

		if ( is_dir( $base_dir ) ) {
			self::protect_folder( $base_dir );
		}
	}

	public static function is_invoice_file_path( $file_path ) {
		if ( empty( $file_path ) ) {
			return false;
		}

		$allowed_dir = realpath( self::get_invoice_base_dir() );
		$real_file   = realpath( $file_path );

		$allowed_dir = $allowed_dir ? trailingslashit( $allowed_dir ) : '';

		return (
			$allowed_dir &&
			$real_file &&
			0 === strpos( $real_file, $allowed_dir ) &&
			'pdf' === strtolower( pathinfo( $real_file, PATHINFO_EXTENSION ) )
		);
	}

	public static function generate( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'yoohw-tax-invoice-requests' ) );
		}

		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			return new WP_Error( 'dompdf_missing', __( 'Dompdf is not available.', 'yoohw-tax-invoice-requests' ) );
		}

		$invoice_number = self::get_or_create_invoice_number( $order );

		if ( is_wp_error( $invoice_number ) ) {
			return $invoice_number;
		}

		$html = self::get_invoice_html( $order, $invoice_number );
		$html = self::inject_pdf_css( $html );

		if ( empty( $html ) ) {
			return new WP_Error( 'empty_html', __( 'Invoice template is empty.', 'yoohw-tax-invoice-requests' ) );
		}

		$base_dir = self::get_invoice_base_dir();

		if ( ! wp_mkdir_p( $base_dir ) ) {
			return new WP_Error( 'folder_failed', __( 'Could not create invoice folder.', 'yoohw-tax-invoice-requests' ) );
		}

		self::protect_folder( $base_dir );

		$file_name = sanitize_file_name(
			sprintf(
				'tax-invoice-%1$s-order-%2$d-%3$s.pdf',
				$invoice_number,
				$order->get_id(),
				wp_generate_password( 24, false, false )
			)
		);
		$file_path = $base_dir . $file_name;

		$options = new Options();
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isHtml5ParserEnabled', true );

		try {
			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			$pdf_output = $dompdf->output();
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'pdf_failed',
				sprintf(
					/* translators: %s: PDF generation error message. */
					__( 'PDF generation failed: %s', 'yoohw-tax-invoice-requests' ),
					$e->getMessage()
				)
			);
		}

		if ( empty( $pdf_output ) ) {
			return new WP_Error( 'pdf_failed', __( 'Could not generate PDF output.', 'yoohw-tax-invoice-requests' ) );
		}

		$wp_filesystem = self::get_filesystem();

		if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $file_path, $pdf_output, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'save_failed', __( 'Could not save PDF invoice.', 'yoohw-tax-invoice-requests' ) );
		}

		$order->update_meta_data( self::META_PDF_PATH, $file_path );
		$order->update_meta_data( self::META_GENERATED_AT, current_time( 'mysql' ) );
		$order->update_meta_data( '_yoohw_tir_status', 'generated' );

		$order->add_order_note(
			sprintf(
				/* translators: %s: Generated invoice number. */
				__( 'Tax invoice PDF generated. Invoice number: %s', 'yoohw-tax-invoice-requests' ),
				$invoice_number
			)
		);

		$order->save();

		return [
			'path'           => $file_path,
			'url'            => class_exists( 'YoOhw_Tir_Download' ) ? YoOhw_Tir_Download::get_download_url( $order->get_id() ) : '',
			'invoice_number' => $invoice_number,
		];
	}

	private static function get_invoice_html( WC_Order $order, $invoice_number ) {
		ob_start();

		wc_get_template(
			'pdf/tax-invoice.php',
			[
				'order'          => $order,
				'invoice_number' => $invoice_number,
				'generated_date' => wc_format_datetime( new WC_DateTime(), wc_date_format() ),
			],
			'',
			YOOHW_TIR_PATH . 'templates/'
		);

		return ob_get_clean();
	}

	private static function inject_pdf_css( $html ) {
		$pdf_css_file = YOOHW_TIR_PATH . 'assets/css/tax-invoice-pdf.css';

		if ( ! file_exists( $pdf_css_file ) ) {
			return $html;
		}

		$wp_filesystem = self::get_filesystem();

		if ( ! $wp_filesystem ) {
			return $html;
		}

		$css = $wp_filesystem->get_contents( $pdf_css_file );

		if ( empty( $css ) ) {
			return $html;
		}

		$style = '<style>' . $css . '</style>';

		if ( false !== stripos( $html, '</head>' ) ) {
			return str_ireplace( '</head>', $style . '</head>', $html );
		}

		return $style . $html;
	}

	private static function get_or_create_invoice_number( WC_Order $order ) {
		$existing = $order->get_meta( self::META_INVOICE_NUMBER );

		if ( ! empty( $existing ) ) {
			return $existing;
		}

		$lock = self::acquire_invoice_number_lock();

		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			$locked_order = wc_get_order( $order->get_id() );

			if ( $locked_order ) {
				$order = $locked_order;
			}

			$existing = $order->get_meta( self::META_INVOICE_NUMBER );

			if ( ! empty( $existing ) ) {
				return $existing;
			}

			$next_number = absint( get_option( 'yoohw_tir_next_invoice_number', 1 ) );

			if ( $next_number < 1 ) {
				$next_number = 1;
			}

			$prefix = sanitize_text_field( get_option( 'yoohw_tir_invoice_number_prefix', 'TAX' ) );

			if ( empty( $prefix ) ) {
				$prefix = 'TAX';
			}

			$invoice_number = sprintf(
				'%s-%s-%06d',
				$prefix,
				gmdate( 'Y' ),
				$next_number
			);

			update_option( 'yoohw_tir_next_invoice_number', $next_number + 1 );

			$order->update_meta_data( self::META_INVOICE_NUMBER, $invoice_number );
			$order->save();

			return $invoice_number;
		} finally {
			self::release_invoice_number_lock();
		}
	}

	private static function acquire_invoice_number_lock() {
		$expires_at = time() + 15;
		$deadline   = microtime( true ) + 5;

		do {
			if ( add_option( self::INVOICE_NUMBER_LOCK, $expires_at, '', 'no' ) ) {
				return true;
			}

			$locked_until = absint( get_option( self::INVOICE_NUMBER_LOCK ) );

			if ( $locked_until && $locked_until < time() ) {
				delete_option( self::INVOICE_NUMBER_LOCK );
				continue;
			}

			usleep( 100000 );
		} while ( microtime( true ) < $deadline );

		return new WP_Error(
			'invoice_number_locked',
			__( 'Could not reserve an invoice number. Please try again.', 'yoohw-tax-invoice-requests' )
		);
	}

	private static function release_invoice_number_lock() {
		delete_option( self::INVOICE_NUMBER_LOCK );
	}

	private static function get_invoice_base_dir() {
		$upload = wp_upload_dir();

		return trailingslashit( $upload['basedir'] ) . self::INVOICE_DIR . '/';
	}

	private static function protect_folder( $base_dir ) {
		$wp_filesystem = self::get_filesystem();

		if ( ! $wp_filesystem ) {
			return;
		}

		$index_file = trailingslashit( $base_dir ) . 'index.html';

		if ( ! $wp_filesystem->exists( $index_file ) ) {
			$wp_filesystem->put_contents( $index_file, '', FS_CHMOD_FILE );
		}

		$htaccess_file = trailingslashit( $base_dir ) . '.htaccess';
		$htaccess_rules = "Options -Indexes\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";

		if ( ! $wp_filesystem->exists( $htaccess_file ) || $htaccess_rules !== $wp_filesystem->get_contents( $htaccess_file ) ) {
			$wp_filesystem->put_contents(
				$htaccess_file,
				$htaccess_rules,
				FS_CHMOD_FILE
			);
		}
	}

	private static function get_filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem;
	}
}
