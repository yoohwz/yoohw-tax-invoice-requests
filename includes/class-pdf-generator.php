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
		$result = self::prepare_storage();
		if ( is_wp_error( $result ) ) {
			add_action( 'admin_notices', static function() use ( $result ) {
				if ( current_user_can( 'manage_woocommerce' ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				}
			} );
		}
	}

	/** Resolve legacy metadata without saving a potentially stale order object. */
	public static function get_order_pdf_path( WC_Order $order ) {
		$path = $order->get_meta( self::META_PDF_PATH );
		if ( self::is_invoice_file_path( $path ) ) {
			return $path;
		}

		$base_dir = self::get_invoice_base_dir();
		$legacy   = self::get_legacy_base_dir();
		if ( ! $path || is_wp_error( $base_dir ) || wp_normalize_path( dirname( $path ) ) !== untrailingslashit( wp_normalize_path( $legacy ) ) ) {
			return '';
		}
		// A surviving source means migration is incomplete or collided with another file.
		clearstatcache( true, $path );
		if ( file_exists( $path ) || is_link( $path ) ) {
			return '';
		}
		$path = $base_dir . basename( $path );
		return self::is_invoice_file_path( $path ) ? $path : '';
	}

	public static function is_invoice_file_path( $file_path ) {
		if ( empty( $file_path ) ) {
			return false;
		}

		$base_dir = self::get_invoice_base_dir();
		if ( is_wp_error( $base_dir ) ) {
			return false;
		}
		$allowed_dir = realpath( $base_dir );
		$real_file   = realpath( $file_path );

		$allowed_dir = $allowed_dir ? trailingslashit( wp_normalize_path( $allowed_dir ) ) : '';
		$real_file   = $real_file ? wp_normalize_path( $real_file ) : false;

		return (
			$allowed_dir &&
			$real_file &&
			0 === strpos( $real_file, $allowed_dir ) &&
			'pdf' === strtolower( pathinfo( $real_file, PATHINFO_EXTENSION ) )
		);
	}

	public static function generate( $order_id ) {
		$base_dir = self::prepare_storage();
		if ( is_wp_error( $base_dir ) ) {
			return $base_dir;
		}
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

		if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $file_path, $pdf_output, 0600 ) ) {
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

	private static function get_legacy_base_dir() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . self::INVOICE_DIR . '/';
	}

	private static function storage_error() {
		return new WP_Error( 'private_storage_unavailable', __( 'Tax invoice private storage is unavailable. Set YOOHW_TIR_PRIVATE_DIR to a writable directory outside the web document root. Existing PDFs in uploads must be moved out of public access before invoice processing can continue.', 'yoohw-tax-invoice-requests' ) );
	}

	private static function get_invoice_base_dir() {
		$document_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : false;
		if ( defined( 'YOOHW_TIR_PRIVATE_DIR' ) ) {
			$directory = YOOHW_TIR_PRIVATE_DIR;
		} elseif ( $document_root && is_dir( $document_root ) ) {
			$directory = dirname( $document_root ) . '/' . self::INVOICE_DIR . '-' . substr( hash( 'sha256', ABSPATH . ':' . get_current_blog_id() ), 0, 12 );
		} else {
			// CLI must supply DOCUMENT_ROOT or an explicitly configured private directory.
			return self::storage_error();
		}

		if ( ! is_string( $directory ) || '' === $directory || false !== strpos( $directory, "\0" ) || ! path_is_absolute( $directory ) ) {
			return self::storage_error();
		}
		$directory = untrailingslashit( $directory );
		$parent    = realpath( dirname( $directory ) );
		$resolved  = realpath( $directory );
		if ( ! $resolved && ( ! $parent || file_exists( $directory ) || is_link( $directory ) ) ) {
			return self::storage_error();
		}
		$resolved = $resolved ? $resolved : $parent . '/' . basename( $directory );
		$resolved = trailingslashit( wp_normalize_path( $resolved ) );

		$upload = wp_upload_dir();
		foreach ( [ realpath( ABSPATH ), $document_root, realpath( $upload['basedir'] ) ] as $public_root ) {
			if ( ! $public_root ) {
				continue;
			}
			$public_root = trailingslashit( wp_normalize_path( $public_root ) );
			if ( 0 === strpos( $resolved, $public_root ) || 0 === strpos( $public_root, $resolved ) ) {
				return self::storage_error();
			}
		}
		return $resolved;
	}

	/** Move legacy PDFs, preserving bytes and names, before they can be used again. */
	private static function prepare_storage() {
		$base_dir = self::get_invoice_base_dir();
		if ( is_wp_error( $base_dir ) ) {
			return $base_dir;
		}
		$filesystem = self::get_filesystem();
		if ( ! $filesystem || ( ! is_dir( $base_dir ) && ! $filesystem->mkdir( $base_dir, 0700 ) ) || ! is_writable( $base_dir ) ) {
			return self::storage_error();
		}

		$legacy = self::get_legacy_base_dir();
		if ( is_link( untrailingslashit( $legacy ) ) ) {
			return self::storage_error();
		}
		$files = glob( $legacy . '*.pdf' );
		if ( false === $files ) {
			return self::storage_error();
		}
		foreach ( $files as $source ) {
			$destination = $base_dir . basename( $source );
			if ( is_link( $source ) || ! is_file( $source ) || file_exists( $destination ) || is_link( $destination ) ) {
				// Another worker may have already completed this exact move.
				if ( ! file_exists( $source ) && self::is_invoice_file_path( $destination ) ) {
					continue;
				}
				return self::storage_error();
			}
			$filesystem->move( $source, $destination, false );
			// Some filesystem adapters report a successful copy even if source deletion fails.
			clearstatcache( true, $source );
			if ( file_exists( $source ) || is_link( $source ) || ! self::is_invoice_file_path( $destination ) ) {
				return self::storage_error();
			}
			$filesystem->chmod( $destination, 0600 );
		}
		return $base_dir;
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
