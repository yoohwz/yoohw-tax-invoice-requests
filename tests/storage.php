<?php
/**
 * Local-only private-storage regression. Creates/deletes only its own fixture order/files.
 * TIR_WP_LOAD=/path/to/wp-load.php TIR_DOCUMENT_ROOT=/actual/web/root \
 * php -d mysqli.default_socket=/path/to/mysql.sock tests/storage.php
 * All mail is intercepted. Legacy PDFs already in the store are migrated, never deleted.
 */
$bootstrap = getenv( 'TIR_WP_LOAD' );
$document_root = getenv( 'TIR_DOCUMENT_ROOT' );
if ( ! $bootstrap || ! is_file( $bootstrap ) || ! $document_root || ! is_dir( $document_root ) ) {
	fwrite( STDERR, "Set TIR_WP_LOAD and TIR_DOCUMENT_ROOT for a disposable local WordPress installation.\n" );
	exit( 1 );
}
$mode = $argv[1] ?? 'suite';
$_SERVER['DOCUMENT_ROOT'] = $document_root;
if ( 'no-root' === $mode ) {
	unset( $_SERVER['DOCUMENT_ROOT'] );
} elseif ( 'public-root' === $mode ) {
	define( 'YOOHW_TIR_PRIVATE_DIR', $document_root . '/tir-storage-public-' . bin2hex( random_bytes( 5 ) ) );
} elseif ( 'subdirectory' === $mode ) {
	// WordPress is below this simulated document root; an ABSPATH sibling is still public.
	$_SERVER['DOCUMENT_ROOT'] = dirname( realpath( $document_root ) );
	define( 'YOOHW_TIR_PRIVATE_DIR', $_SERVER['DOCUMENT_ROOT'] . '/tir-storage-public-' . bin2hex( random_bytes( 5 ) ) );
} elseif ( 'unwritable' === $mode ) {
	$blocked_dir = sys_get_temp_dir() . '/tir-storage-blocked-' . bin2hex( random_bytes( 5 ) );
	mkdir( $blocked_dir, 0500 );
	define( 'YOOHW_TIR_PRIVATE_DIR', $blocked_dir );
}
$GLOBALS['wp_filter']['option_active_plugins'][PHP_INT_MAX][] = [
	'function' => static function() { return [ 'woocommerce/woocommerce.php', 'yoohw-tax-invoice-requests/yoohw-tax-invoice-requests.php' ]; },
	'accepted_args' => 1,
];
$GLOBALS['wp_filter']['pre_wp_mail'][PHP_INT_MAX][] = [ 'function' => '__return_true', 'accepted_args' => 1 ];
require $bootstrap;

function storage_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function storage_prepare() {
	$method = new ReflectionMethod( 'YoOhw_Tir_PDF_Generator', 'prepare_storage' );
	$method->setAccessible( true );
	return $method->invoke( null );
}

if ( 'suite' !== $mode ) {
	try {
		$result = storage_prepare();
		storage_assert( is_wp_error( $result ), 'Unsafe/unavailable storage was accepted in ' . $mode );
		$result = YoOhw_Tir_PDF_Generator::generate( 0 );
		storage_assert( is_wp_error( $result ) && 'private_storage_unavailable' === $result->get_error_code(), 'Generation did not fail closed in ' . $mode );
		if ( in_array( $mode, [ 'public-root', 'subdirectory' ], true ) ) {
			storage_assert( ! file_exists( YOOHW_TIR_PRIVATE_DIR ), 'Rejected storage directory was created inside document root.' );
		}
		echo "PASS rejected " . $mode . " storage without uploads fallback\n";
	} finally {
		if ( isset( $blocked_dir ) ) { chmod( $blocked_dir, 0700 ); rmdir( $blocked_dir ); }
	}
	exit;
}

$order = null;
$owned_files = [];
$failed = false;
try {
	$base = storage_prepare();
	storage_assert( ! is_wp_error( $base ), is_wp_error( $base ) ? $base->get_error_message() : 'Private directory unavailable.' );
	storage_assert( 0 !== strpos( realpath( $base ) . '/', realpath( $document_root ) . '/' ), 'Storage is inside the actual document root.' );
	$legacy = trailingslashit( wp_upload_dir()['basedir'] ) . YoOhw_Tir_PDF_Generator::INVOICE_DIR . '/';
	wp_mkdir_p( $legacy );
	$initial_legacy_files = glob( $legacy . '*.pdf' );
	$tag = bin2hex( random_bytes( 8 ) );
	$number = 'TEST-STORAGE-' . $tag;
	$order = new WC_Order();
	$order->set_billing_country( 'US' );
	$order->set_billing_email( 'storage-fixture@example.test' );
	$order->update_meta_data( '_yoohw_tir_invoice_number', $number );
	$order->update_meta_data( '_yoohw_tir_company', 'Private storage regression fixture' );
	$order->update_meta_data( '_yoohw_tir_email', 'storage-fixture@example.test' );
	$item = new WC_Order_Item_Product();
	$item->set_name( 'Private storage fixture' );
	$item->set_quantity( 1 );
	$item->set_subtotal( 100 );
	$item->set_total( 100 );
	$order->add_item( $item );
	$order->calculate_totals( false );
	$order->save();
	$generated = YoOhw_Tir_PDF_Generator::generate( $order->get_id() );
	storage_assert( ! is_wp_error( $generated ), 'Initial fixture generation failed.' );
	$owned_files[] = $generated['path'];
	$bytes = file_get_contents( $generated['path'] );
	storage_assert( '%PDF' === substr( $bytes, 0, 4 ), 'Fixture is not a real Dompdf PDF.' );
	$legacy_path = $legacy . 'tax-invoice-' . $number . '-order-' . $order->get_id() . '-legacy.pdf';
	$migrated_path = $base . basename( $legacy_path );
	$owned_files[] = $legacy_path;
	$owned_files[] = $migrated_path;
	storage_assert( rename( $generated['path'], $legacy_path ), 'Could not seed a legacy fixture.' );
	$order = wc_get_order( $order->get_id() );
	$order->update_meta_data( '_yoohw_tir_pdf_path', $legacy_path );
	$order->save();
	storage_assert( ! is_wp_error( storage_prepare() ), 'Legacy fixture migration failed.' );
	storage_assert( ! file_exists( $legacy_path ) && hash( 'sha256', $bytes ) === hash_file( 'sha256', $migrated_path ), 'Migration changed bytes or retained public PDF.' );
	storage_assert( $migrated_path === YoOhw_Tir_PDF_Generator::get_order_pdf_path( $order ), 'Old metadata does not resolve to migrated PDF.' );
	storage_assert( $legacy_path === $order->get_meta( '_yoohw_tir_pdf_path' ), 'Resolver rewrote metadata on the order object.' );
	$order = wc_get_order( $order->get_id() );
	$order->read_meta_data( true );
	storage_assert( $legacy_path === $order->get_meta( '_yoohw_tir_pdf_path' ) && $number === $order->get_meta( '_yoohw_tir_invoice_number' ), 'Migration changed persisted metadata or invoice number.' );
	storage_assert( ! YoOhw_Tir_PDF_Generator::is_invoice_file_path( $legacy_path ) && YoOhw_Tir_PDF_Generator::is_invoice_file_path( $migrated_path ), 'Path validation did not enforce private storage.' );
	echo "PASS real PDF migration preserves bytes, invoice number and legacy metadata; resolver is read-only\n";

	$emails = WC()->mailer()->get_emails();
	$email = $emails['YoOhw_Tir_Email_Tax_Invoice'];
	$email->object = $order;
	storage_assert( [ $migrated_path ] === $email->get_attachments(), 'Migrated attachment is not the private file.' );
	$mail_count = 0;
	add_filter( 'woocommerce_email_enabled_yoohw_tir_invoice', '__return_true' );
	add_filter( 'pre_wp_mail', static function( $pre, $atts ) use ( &$mail_count, $migrated_path, $bytes ) {
		++$mail_count;
		storage_assert( [ $migrated_path ] === $atts['attachments'], 'Transport did not receive the migrated private PDF.' );
		storage_assert( $bytes === file_get_contents( $atts['attachments'][0] ), 'Attachment bytes changed.' );
		return true;
	}, PHP_INT_MAX, 2 );
	storage_assert( $email->trigger( $order->get_id() ) && 1 === $mail_count, 'Migrated email did not reach intercepted transport once.' );
	echo "PASS migrated PDF attaches with exact bytes; transport intercepted before any send\n";

	$collision_name = 'tir-storage-collision-' . $tag . '.pdf';
	$collision_source = $legacy . $collision_name;
	$collision_dest = $base . $collision_name;
	$owned_files[] = $collision_source;
	$owned_files[] = $collision_dest;
	file_put_contents( $collision_source, 'source fixture' );
	file_put_contents( $collision_dest, 'destination fixture' );
	storage_assert( is_wp_error( storage_prepare() ), 'Migration overwrote an existing destination.' );
	storage_assert( 'source fixture' === file_get_contents( $collision_source ) && 'destination fixture' === file_get_contents( $collision_dest ), 'Collision lost source or destination data.' );
	$order->update_meta_data( '_yoohw_tir_pdf_path', $collision_source );
	storage_assert( '' === YoOhw_Tir_PDF_Generator::get_order_pdf_path( $order ), 'Resolver served a collided destination while the public source remained.' );
	$order->update_meta_data( '_yoohw_tir_pdf_path', $legacy_path );
	unlink( $collision_source );
	unlink( $collision_dest );
	echo "PASS migration collision fails without overwriting either file\n";

	$copy_source = $legacy . 'tir-storage-copy-only-' . $tag . '.pdf';
	$copy_dest = $base . basename( $copy_source );
	$owned_files[] = $copy_source;
	$owned_files[] = $copy_dest;
	file_put_contents( $copy_source, $bytes );
	$original_filesystem = $GLOBALS['wp_filesystem'];
	try {
		// Match adapters whose move() reports copy success even when source deletion failed.
		$GLOBALS['wp_filesystem'] = new class( null ) extends WP_Filesystem_Direct {
			public function move( $source, $destination, $overwrite = false ) {
				copy( $source, $destination );
				return true;
			}
		};
		storage_assert( is_wp_error( storage_prepare() ), 'Migration accepted a successful copy that retained the public source.' );
		storage_assert( $bytes === file_get_contents( $copy_source ) && $bytes === file_get_contents( $copy_dest ), 'Copy-only migration unexpectedly changed source or destination bytes.' );
		$order->update_meta_data( '_yoohw_tir_pdf_path', $copy_source );
		storage_assert( '' === YoOhw_Tir_PDF_Generator::get_order_pdf_path( $order ), 'Resolver served a partially migrated destination while the public source remained.' );
		$order->update_meta_data( '_yoohw_tir_pdf_path', $legacy_path );
	} finally {
		$GLOBALS['wp_filesystem'] = $original_filesystem;
	}
	unlink( $copy_source );
	unlink( $copy_dest );
	echo "PASS copy-only move is rejected while public source remains, even when adapter returns true\n";


	$outside = tempnam( sys_get_temp_dir(), 'tir-storage-outside-' );
	$outside_pdf = $outside . '.pdf';
	rename( $outside, $outside_pdf );
	$escape = $base . 'tir-storage-symlink-' . $tag . '.pdf';
	$owned_files[] = $outside_pdf;
	$owned_files[] = $escape;
	file_put_contents( $outside_pdf, 'outside fixture' );
	storage_assert( symlink( $outside_pdf, $escape ), 'Symlink fixture unavailable.' );
	storage_assert( ! YoOhw_Tir_PDF_Generator::is_invoice_file_path( $escape ), 'Private symlink escaped the allowed directory.' );
	unlink( $escape );
	unlink( $outside_pdf );
	echo "PASS private symlink escape rejected\n";

	$regenerated = YoOhw_Tir_PDF_Generator::generate( $order->get_id() );
	storage_assert( ! is_wp_error( $regenerated ), 'Regeneration after migration failed.' );
	$owned_files[] = $regenerated['path'];
	storage_assert( $number === $regenerated['invoice_number'] && YoOhw_Tir_PDF_Generator::is_invoice_file_path( $regenerated['path'] ), 'Regeneration changed the invoice number or private boundary.' );
	storage_assert( $initial_legacy_files === glob( $legacy . '*.pdf' ), 'Generation fell back to public uploads.' );
	echo "PASS regeneration remains private and preserves invoice number\n";

	foreach ( [ 'public-root', 'subdirectory', 'no-root', 'unwritable' ] as $scenario ) {
		$command = [ PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get( 'mysqli.default_socket' ), __FILE__, $scenario ];
		$pipes = [];
		$child = proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		storage_assert( 0 === proc_close( $child ) && false !== strpos( $output, 'PASS rejected ' . $scenario ), 'Storage failure scenario failed: ' . $scenario . "\n" . $output . $error );
		echo $output;
	}
} catch ( Throwable $error ) {
	$failed = true;
	fwrite( STDERR, 'FAIL ' . $error->getMessage() . "\n" );
} finally {
	foreach ( array_unique( $owned_files ) as $path ) {
		if ( is_file( $path ) || is_link( $path ) ) { unlink( $path ); }
	}
	if ( $order instanceof WC_Order ) { $order->delete( true ); }
}
exit( $failed ? 1 : 0 );
