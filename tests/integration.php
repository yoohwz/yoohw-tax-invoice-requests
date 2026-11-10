<?php
/**
 * Local-only WordPress/WooCommerce integration regression.
 * TIR_WP_LOAD=/path/to/wp-load.php php -d mysqli.default_socket=/path/to/mysql.sock tests/integration.php
 * Set TIR_DOCUMENT_ROOT to the actual web document root for private PDF storage.
 * Optional TIR_TEST_LEGACY=1 uses the legacy order datastore without changing DB options.
 * Creates and deletes fixture orders/user. All mail is intercepted before transport.
 */

$bootstrap = getenv( 'TIR_WP_LOAD' );
if ( ! $bootstrap || ! is_file( $bootstrap ) ) {
	fwrite( STDERR, "Set TIR_WP_LOAD to a disposable local WordPress wp-load.php.\n" );
	exit( 1 );
}
if ( getenv( 'TIR_DOCUMENT_ROOT' ) ) {
	$_SERVER['DOCUMENT_ROOT'] = getenv( 'TIR_DOCUMENT_ROOT' );
}
$mode   = $argv[1] ?? 'suite';
$events = $argv[2] ?? '';
if ( getenv( 'TIR_TEST_LEGACY' ) ) {
	foreach ( [ 'woocommerce_custom_orders_table_enabled', 'woocommerce_custom_orders_table_data_sync_enabled' ] as $option ) {
		$GLOBALS['wp_filter'][ 'pre_option_' . $option ][PHP_INT_MAX][] = [
			'function' => function() { return 'no'; },
			'accepted_args' => 1,
		];
	}
}
$GLOBALS['tir_mail_result'] = true;
$GLOBALS['tir_mail_count']  = 0;
$GLOBALS['wp_filter']['option_active_plugins'][PHP_INT_MAX][] = [
	'function' => function() {
		return [ 'woocommerce/woocommerce.php', 'yoohw-tax-invoice-requests/yoohw-tax-invoice-requests.php' ];
	},
	'accepted_args' => 1,
];
$GLOBALS['wp_filter']['pre_wp_mail'][PHP_INT_MAX][] = [
	'function' => function( $pre, $atts ) use ( $mode, $events ) {
		++$GLOBALS['tir_mail_count'];
		if ( 'suite' !== $mode ) {
			file_put_contents( $events . '/mail', "attempt\n", FILE_APPEND | LOCK_EX );
			if ( 'hold' === ( $GLOBALS['argv'][5] ?? '' ) ) {
				touch( $events . '/sending' );
				$deadline = microtime( true ) + 20;
				while ( ! is_file( $events . '/release' ) && microtime( true ) < $deadline ) {
					usleep( 20000 );
				}
			}
		}
		return $GLOBALS['tir_mail_result'];
	},
	'accepted_args' => 2,
];
$mail_interceptor = $GLOBALS['wp_filter']['pre_wp_mail'][PHP_INT_MAX][0]['function'];
require $bootstrap;
// Register last, after any local MU mail guards, so false-transport simulation wins.
remove_filter( 'pre_wp_mail', $mail_interceptor, PHP_INT_MAX );
add_filter( 'pre_wp_mail', $mail_interceptor, PHP_INT_MAX, 2 );

if ( ! class_exists( 'YoOhw_Tir_Handler' ) ) {
	throw new RuntimeException( 'Invoice plugin or WooCommerce did not bootstrap.' );
}
// Tests do not consume the store invoice sequence or change invoice settings.
add_filter( 'pre_update_option_yoohw_tir_next_invoice_number', function( $new, $old ) { return $old; }, 10, 2 );
add_filter( 'pre_option_yoohw_tir_invoice_number_prefix', function() { return 'TIR-TEST'; } );
add_filter( 'pre_option_yoohw_tir_invoice_allowed_order_statuses', function() { return [ 'wc-processing' ]; } );
add_filter( 'woocommerce_email_enabled_yoohw_tir_invoice', '__return_true' );
add_filter( 'woocommerce_email_attachments', function( $attachments, $id ) {
	if ( 'yoohw_tir_invoice' === $id && empty( $attachments ) ) {
		throw new RuntimeException( 'Invoice email attempted without PDF attachment.' );
	}
	return $attachments;
}, 10, 2 );
class TIR_Test_Redirect extends RuntimeException {}
add_filter( 'wp_redirect', function( $url ) { throw new TIR_Test_Redirect( $url ); } );

function tir_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function tir_order( $id ) {
	$order = wc_get_order( $id );
	$order->read_meta_data( true );
	return $order;
}
function tir_submit( $id, $email = 'original@example.test', $nonce = null ) {
	$_POST = [
		'yoohw_tir_action' => 'request',
		'_wpnonce' => null === $nonce ? wp_create_nonce( 'yoohw_tir_request_invoice' ) : $nonce,
		'order_id' => $id,
		'customer_name' => 'Invoice regression fixture',
		'tax_id' => 'TEST',
		'country' => 'US',
		'address' => 'Test address',
		'email' => $email,
	];
	try {
		( new YoOhw_Tir_Handler() )->handle_submit();
	} catch ( TIR_Test_Redirect $redirect ) {
		return $redirect->getMessage();
	}
	return '';
}
function tir_admin_action( $id, $action ) {
	$_REQUEST = [ 'order_id' => $id, '_wpnonce' => wp_create_nonce( $action . '_' . $id ) ];
	try {
		$admin  = new YoOhw_Tir_Admin();
		$method = 'yoohw_tir_resend_invoice' === $action ? 'handle_resend_invoice' : 'handle_regenerate_invoice';
		$admin->$method();
	} catch ( TIR_Test_Redirect $redirect ) {
		return $redirect->getMessage();
	}
	throw new RuntimeException( 'Admin action did not redirect.' );
}
function tir_generated_count( $id ) {
	return count( array_filter( wc_get_order_notes( [ 'order_id' => $id, 'limit' => 100 ] ), function( $note ) {
		return false !== strpos( $note->content, 'Tax invoice PDF generated. Invoice number:' );
	} ) );
}
function tir_fixture( $user_id, &$orders ) {
	$order = wc_create_order( [ 'customer_id' => $user_id, 'status' => 'processing' ] );
	$orders[] = $order->get_id();
	$order->set_billing_email( 'fixture@example.test' );
	$order->set_billing_country( 'US' );
	$item = new WC_Order_Item_Product();
	$item->set_name( 'Regression test item' );
	$item->set_quantity( 1 );
	$item->set_subtotal( 100 );
	$item->set_total( 90 );
	$order->add_item( $item );
	$order->calculate_totals( false );
	$order->save();
	return $order;
}

if ( 'suite' !== $mode ) {
	wp_set_current_user( (int) $argv[4] );
	WC()->session = new WC_Session_Handler();
	if ( 'slow-note' === $mode ) {
		add_action( 'woocommerce_order_note_added', function( $note_id ) use ( $events ) {
			$note = get_comment( $note_id );
			if ( ! $note || false === strpos( $note->comment_content, 'Customer requested a tax invoice.' ) ) {
				return;
			}
			global $wpdb;
			$connection = $wpdb->get_var( 'SELECT CONNECTION_ID()' );
			// Only this worker's connection expires; the request is still in its critical section.
			tir_assert( false !== $wpdb->query( 'SET SESSION wait_timeout = 1' ), 'Could not set fixture session timeout.' );
			sleep( 2 );
			file_put_contents( $events . '/connection-expired', (string) $connection );
			$deadline = microtime( true ) + 20;
			while ( ! is_file( $events . '/release' ) && microtime( true ) < $deadline ) { usleep( 20000 ); }
			tir_assert( is_file( $events . '/release' ), 'Parent did not release expired-connection worker.' );
		}, 10, 1 );
	}
	$result = in_array( $mode, [ 'worker', 'slow-note' ], true )
		? tir_submit( (int) $argv[3], 'concurrent@example.test' )
		: tir_admin_action( (int) $argv[3], 'admin-resend' === $mode ? 'yoohw_tir_resend_invoice' : 'yoohw_tir_regenerate_invoice' );
	global $wpdb;
	file_put_contents( $events . '/' . $mode . '-' . getmypid(), json_encode( [ 'redirect' => $result, 'notices' => wc_get_notices(), 'connection' => $wpdb->get_var( 'SELECT CONNECTION_ID()' ) ] ) );
	exit;
}

$orders = [];
$user_id = 0;
$failed = false;
$first = null;
$second = null;
$dir = sys_get_temp_dir() . '/tir-integration-' . bin2hex( random_bytes( 6 ) );
mkdir( $dir );
try {
	$user_id = wp_insert_user( [ 'user_login' => 'tir-test-' . bin2hex( random_bytes( 5 ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator', 'user_email' => 'fixture@example.test' ] );
	tir_assert( ! is_wp_error( $user_id ), 'Fixture user creation failed.' );
	wp_set_current_user( $user_id );
	WC()->session = new WC_Session_Handler();

	// Cold mailer: no WC_Email class has been autoloaded by the fixture bootstrap.
	tir_assert( ! class_exists( 'WC_Email', false ), 'Cold-mailer precondition failed.' );
	$method = new ReflectionMethod( 'YoOhw_Tir_Admin', 'get_tax_invoice_email' );
	$method->setAccessible( true );
	tir_assert( $method->invoke( new YoOhw_Tir_Admin() ) instanceof WC_Email, 'Cold admin mailer failed.' );
	echo "PASS cold admin mailer initialization\n";

	$order = tir_fixture( $user_id, $orders );
	$store_class = $order->get_data_store()->get_current_class_name();
	if ( getenv( 'TIR_TEST_LEGACY' ) ) { tir_assert( 'WC_Order_Data_Store_CPT' === $store_class, 'Legacy datastore override failed.' ); }
	echo 'PASS datastore ' . $store_class . "\n";
	$GLOBALS['tir_mail_count'] = 0;
	wc_clear_notices();
	tir_submit( $order->get_id(), 'invalid@example.test', 'invalid-nonce' );
	tir_assert( wc_has_notice( 'Security error. Please try again.', 'error' ), 'Invalid nonce was not rejected.' );
	wp_set_current_user( 0 );
	wc_clear_notices();
	tir_submit( $order->get_id(), 'unauthorized@example.test' );
	tir_assert( wc_has_notice( 'Permission denied.', 'error' ), 'Unauthorized submit was not rejected.' );
	$order = tir_order( $order->get_id() );
	tir_assert( ! $order->get_meta( '_yoohw_tir_requested' ) && ! $order->get_meta( '_yoohw_tir_invoice_number' ) && 0 === $GLOBALS['tir_mail_count'] && 0 === tir_generated_count( $order->get_id() ), 'Rejected submit performed invoice work.' );
	wp_set_current_user( $user_id );
	echo "PASS invalid nonce and unauthorized submit perform no invoice work\n";
	tir_submit( $order->get_id() );
	$order = tir_order( $order->get_id() );
	$path = $order->get_meta( '_yoohw_tir_pdf_path' );
	$number = $order->get_meta( '_yoohw_tir_invoice_number' );
	tir_assert( is_file( $path ) && '%PDF' === file_get_contents( $path, false, null, 0, 4 ), 'Real Dompdf output missing.' );
	tir_assert( 1 === $GLOBALS['tir_mail_count'] && 1 === tir_generated_count( $order->get_id() ), 'Initial submit did not generate/send once.' );
	tir_submit( $order->get_id(), 'overwrite@example.test' );
	$order = tir_order( $order->get_id() );
	tir_assert( 'original@example.test' === $order->get_meta( '_yoohw_tir_email' ), 'Replay overwrote recipient.' );
	tir_assert( $path === $order->get_meta( '_yoohw_tir_pdf_path' ) && $number === $order->get_meta( '_yoohw_tir_invoice_number' ), 'Replay replaced invoice.' );
	tir_assert( 1 === $GLOBALS['tir_mail_count'] && 1 === tir_generated_count( $order->get_id() ), 'Replay repeated work.' );
	echo "PASS real PDF and replay: one generate/one intercepted mail attempt, recipient unchanged\n";

	// Parse the actual metabox/footer output in the editor's surrounding form.
	$admin = new YoOhw_Tir_Admin();
	ob_start();
	echo '<form id="editor"><input name="action" value="save_order"><button id="save-order">Save order</button>';
	$admin->render_tax_invoice_metabox( $order );
	echo '</form>';
	$admin->render_action_forms();
	$dom = new DOMDocument();
	@$dom->loadHTML( ob_get_clean() );
	$xpath = new DOMXPath( $dom );
	tir_assert( 0 === $xpath->query( '//form[@id="editor"]//form' )->length, 'Metabox nested a form inside the editor.' );
	tir_assert( 1 === $xpath->query( '//form[@id="editor"]//input[@name="action"]' )->length, 'Invoice fields contaminated the save-order form.' );
	foreach ( [ 'yoohw_tir_regenerate_invoice', 'yoohw_tir_resend_invoice' ] as $action ) {
		$form_id = $action . '_' . $order->get_id();
		tir_assert( 1 === $xpath->query( '//button[@form="' . $form_id . '"]' )->length, 'Invoice button has no explicit form owner.' );
		tir_assert( 1 === $xpath->query( '//form[@id="' . $form_id . '"][@method="post"]//input[@name="_wpnonce"]' )->length, 'Invoice form is missing its POST nonce.' );
		tir_assert( admin_url( 'admin-post.php' ) === $xpath->query( '//form[@id="' . $form_id . '"]' )->item( 0 )->getAttribute( 'action' ), 'Invoice action uses the wrong endpoint.' );
	}
	echo "PASS parsed metabox/footer forms preserve editor Save action and independent POST nonces\n";


	$order = tir_fixture( $user_id, $orders );
	$GLOBALS['tir_mail_result'] = false;
	$GLOBALS['tir_mail_count'] = 0;
	tir_submit( $order->get_id() );
	$order = tir_order( $order->get_id() );
	$path = $order->get_meta( '_yoohw_tir_pdf_path' );
	$number = $order->get_meta( '_yoohw_tir_invoice_number' );
	$notice = get_transient( 'yoohw_tir_invoice_notice_' . $order->get_id() . '_' . md5( $order->get_order_key() ) );
	tir_assert( 'failed' === $order->get_meta( '_yoohw_tir_email_status' ), 'Transport failure was not persisted: ' . json_encode( [ $order->get_id(), $order->get_meta_data(), $GLOBALS['tir_mail_count'], wc_get_notices() ] ) );
	tir_assert( 'generated' === $order->get_meta( '_yoohw_tir_status' ) && is_file( $path ), 'Email failure lost PDF.' );
	tir_assert( 'notice' === $notice['type'] && false !== strpos( $notice['message'], 'could not be sent' ), 'Email failure notice is misleading.' );
	tir_submit( $order->get_id(), 'retry@example.test' );
	tir_assert( 1 === $GLOBALS['tir_mail_count'] && 1 === tir_generated_count( $order->get_id() ), 'Failure replay retried automatically.' );
	$GLOBALS['tir_mail_result'] = true;
	$url = tir_admin_action( $order->get_id(), 'yoohw_tir_resend_invoice' );
	$order = tir_order( $order->get_id() );
	tir_assert( false !== strpos( $url, 'yoohw_tir_notice_type=success' ), 'Admin resend failed.' );
	tir_assert( 'accepted' === $order->get_meta( '_yoohw_tir_email_status' ) && 2 === $GLOBALS['tir_mail_count'], 'Admin resend did not record recovery.' );
	tir_assert( $path === $order->get_meta( '_yoohw_tir_pdf_path' ) && $number === $order->get_meta( '_yoohw_tir_invoice_number' ), 'Resend regenerated invoice.' );
	$url = tir_admin_action( $order->get_id(), 'yoohw_tir_regenerate_invoice' );
	$order = tir_order( $order->get_id() );
	tir_assert( false !== strpos( $url, 'yoohw_tir_notice_type=success' ) && is_file( $order->get_meta( '_yoohw_tir_pdf_path' ) ), 'Admin regenerate failed.' );
	tir_assert( $number === $order->get_meta( '_yoohw_tir_invoice_number' ) && ! is_file( $path ) && 2 === $GLOBALS['tir_mail_count'], 'Regenerate changed number, retained old PDF, or sent mail.' );
	echo "PASS false transport, blocked retry, explicit admin resend recovery and regeneration\n";

	$order = tir_fixture( $user_id, $orders );
	$command = [ PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get( 'mysqli.default_socket' ), __FILE__, 'worker', $dir, (string) $order->get_id(), (string) $user_id ];
	$pipes = [];
	$first = proc_open( array_merge( $command, [ 'hold' ] ), [ 0 => [ 'pipe', 'r' ], 1 => [ 'file', $dir . '/first.out', 'w' ], 2 => [ 'file', $dir . '/first.err', 'w' ] ], $pipes );
	$deadline = microtime( true ) + 20;
	while ( ! is_file( $dir . '/sending' ) && microtime( true ) < $deadline ) { usleep( 20000 ); }
	tir_assert( is_file( $dir . '/sending' ), 'First worker never reached transport: ' . file_get_contents( $dir . '/first.err' ) );
	$second = proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'file', $dir . '/second.out', 'w' ], 2 => [ 'file', $dir . '/second.err', 'w' ] ], $pipes );
	$second_exit = proc_close( $second );
	// Fresh admin requests must share the same lock as the in-flight customer send.
	foreach ( [ 'admin-resend', 'admin-regenerate' ] as $admin_mode ) {
		$admin_command = $command;
		$admin_command[4] = $admin_mode;
		$admin_worker = proc_open( $admin_command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'file', $dir . '/' . $admin_mode . '.out', 'w' ], 2 => [ 'file', $dir . '/' . $admin_mode . '.err', 'w' ] ], $pipes );
		tir_assert( 0 === proc_close( $admin_worker ), 'Concurrent admin worker failed.' );
		$admin_results = glob( $dir . '/' . $admin_mode . '-*' );
		tir_assert( 1 === count( $admin_results ), 'Missing concurrent admin result.' );
		$admin_result = json_decode( file_get_contents( $admin_results[0] ), true );
		tir_assert( false !== strpos( $admin_result['redirect'], 'yoohw_tir_notice_type=warning' ), 'Concurrent admin action did not report a busy invoice.' );
		tir_assert( 1 === tir_generated_count( $order->get_id() ) && "attempt\n" === file_get_contents( $dir . '/mail' ), 'Concurrent admin action generated or sent another invoice.' );
	}
	echo "PASS admin Resend and Regenerate reject a held customer lock without extra generation/mail\n";
	touch( $dir . '/release' );
	$first_exit = proc_close( $first );
	tir_assert( 0 === $first_exit && 0 === $second_exit, 'Concurrent worker failed.' );
	$results = glob( $dir . '/worker-*' );
	tir_assert( 2 === count( $results ), 'Missing worker results.' );
	$busy = 0;
	foreach ( $results as $result ) {
		$data = json_decode( file_get_contents( $result ), true );
		if ( false !== strpos( json_encode( $data['notices'] ), 'being processed' ) ) { ++$busy; }
	}
	tir_assert( 1 === $busy && "attempt\n" === file_get_contents( $dir . '/mail' ), 'Concurrent request was not excluded during transport.' );
	tir_assert( 1 === tir_generated_count( $order->get_id() ), 'Concurrent request generated more than once.' );
	// This process primed the order before the workers; exercise a stale in-process reader.
	tir_submit( $order->get_id(), 'stale-reader@example.test' );
	tir_assert( 1 === tir_generated_count( $order->get_id() ), 'Stale HPOS reader repeated generation after worker completion.' );
	$order = tir_order( $order->get_id() );
	tir_assert( 'accepted' === $order->get_meta( '_yoohw_tir_email_status' ), 'Concurrent winning worker did not complete.' );
	echo "PASS two independent PHP workers overlapped during send: one generate/one intercepted mail attempt; competing worker rejected; stale reader replay blocked\n";

	// Expire A's DB connection before request metadata is saved, while A still owns the invoice lock.
	foreach ( glob( $dir . '/*' ) as $file ) { unlink( $file ); }
	$order = tir_fixture( $user_id, $orders );
	$command = [ PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get( 'mysqli.default_socket' ), __FILE__, 'slow-note', $dir, (string) $order->get_id(), (string) $user_id ];
	$first = proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'file', $dir . '/first.out', 'w' ], 2 => [ 'file', $dir . '/first.err', 'w' ] ], $pipes );
	$deadline = microtime( true ) + 20;
	while ( ! is_file( $dir . '/connection-expired' ) && microtime( true ) < $deadline ) { usleep( 20000 ); }
	tir_assert( is_file( $dir . '/connection-expired' ), 'Slow-note worker never reached expired connection: ' . file_get_contents( $dir . '/first.err' ) );
	$command[4] = 'worker';
	$second = proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'file', $dir . '/second.out', 'w' ], 2 => [ 'file', $dir . '/second.err', 'w' ] ], $pipes );
	$second_exit = proc_close( $second );
	touch( $dir . '/release' );
	$first_exit = proc_close( $first );
	tir_assert( 0 === $first_exit && 0 === $second_exit, 'Expired-connection workers failed: ' . file_get_contents( $dir . '/first.err' ) . file_get_contents( $dir . '/second.err' ) );
	$first_results = glob( $dir . '/slow-note-*' );
	$second_results = glob( $dir . '/worker-*' );
	tir_assert( 1 === count( $first_results ) && 1 === count( $second_results ), 'Missing expired-connection worker results.' );
	$first_result = json_decode( file_get_contents( $first_results[0] ), true );
	$second_result = json_decode( file_get_contents( $second_results[0] ), true );
	tir_assert( (string) $first_result['connection'] !== trim( file_get_contents( $dir . '/connection-expired' ) ), 'Fixture did not cause an actual DB reconnect.' );
	$generation_count = tir_generated_count( $order->get_id() );
	$mail_attempts = is_file( $dir . '/mail' ) ? substr_count( file_get_contents( $dir . '/mail' ), "attempt\n" ) : 0;
	tir_assert( false !== strpos( json_encode( $second_result['notices'] ), 'being processed' ) && 1 === $generation_count && 1 === $mail_attempts, 'DB reconnect lost invoice exclusion: generations=' . $generation_count . ', mail_attempts=' . $mail_attempts . ', competitor=' . json_encode( $second_result ) );
	$order = wc_get_order( $order->get_id() );
	$verification_lock = YoOhw_Tir_Order_Lock::acquire( $order->get_id() );
	tir_assert( ! is_wp_error( $verification_lock ), 'Completed worker retained the invoice lock.' );
	try {
		YoOhw_Tir_Order_Lock::refresh( $order );
	} finally {
		YoOhw_Tir_Order_Lock::release( $verification_lock );
	}
	tir_assert( 'accepted' === $order->get_meta( '_yoohw_tir_email_status' ), 'Expired-connection winning worker did not complete.' );
	echo "PASS real SESSION wait_timeout reconnect retains invoice exclusion: competing worker busy, one generation/mail\n";

} catch ( Throwable $error ) {
	$failed = true;
	fwrite( STDERR, 'FAIL ' . $error->getMessage() . "\n" );
} finally {
	if ( is_resource( $first ) || is_resource( $second ) ) {
		touch( $dir . '/release' );
		foreach ( [ $first, $second ] as $worker ) { if ( is_resource( $worker ) ) { proc_close( $worker ); } }
	}
	foreach ( $orders as $id ) {
		$order = wc_get_order( $id );
		if ( $order ) {
			$cleanup_lock = YoOhw_Tir_Order_Lock::acquire( $id );
			if ( ! is_wp_error( $cleanup_lock ) ) {
				YoOhw_Tir_Order_Lock::refresh( $order );
				YoOhw_Tir_Order_Lock::release( $cleanup_lock );
			}
			$path = $order->get_meta( '_yoohw_tir_pdf_path' );
			if ( $path && YoOhw_Tir_PDF_Generator::is_invoice_file_path( $path ) ) {
				// Also clean replaced fixture PDFs if a regression generated twice.
				foreach ( glob( dirname( $path ) . '/tax-invoice-TIR-TEST-*-order-' . $id . '-*.pdf' ) as $fixture_pdf ) { wp_delete_file( $fixture_pdf ); }
			}
			delete_transient( 'yoohw_tir_invoice_notice_' . $id . '_' . md5( $order->get_order_key() ) );
			$order->delete( true );
		}
	}
	if ( $user_id && ! is_wp_error( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
	foreach ( glob( $dir . '/*' ) as $file ) { unlink( $file ); }
	rmdir( $dir );
}

exit( $failed ? 1 : 0 );
