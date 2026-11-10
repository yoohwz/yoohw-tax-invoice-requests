<?php
/** Isolated PDF template regression checks. Run: php tests/pdf-regression.php */
define( 'ABSPATH', __DIR__ );
function __( $s, $domain = '' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_html_e( $s, $domain = '' ) { echo esc_html( $s ); }
function wp_kses_post( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function wc_format_decimal( $n, $dp ) { return number_format( (float) $n, $dp, '.', '' ); }
function wc_price( $n, $options = [] ) { return number_format( $n, 2, '.', '' ); }
function wc_tax_enabled() { return true; }
function wc_display_item_meta( $item, $args ) { return ''; }
function get_bloginfo( $key ) { return 'Test Shop'; }
function get_option( $key, $default = '' ) {
	return [ 'yoohw_tir_invoice_seller_address' => 'Shop address' ][ $key ] ?? $default;
}
function metadata_exists( $type, $id, $key ) { return 2 !== $id; }
function WC() {
	return (object) [ 'countries' => new class {
		function get_countries() { return [ 'US' => 'United States' ]; }
		function get_states( $country ) { return []; }
	} ];
}
// Looking up a live tax rate must never be needed for an existing invoice.
class WC_Tax {
	static function _get_tax_rate( $id ) { throw new RuntimeException( 'Live rate lookup used' ); }
}
class Test_Tax_Item {
	public $id = 1;
	public $percent = 10;
	public $rate_id = 7;
	function get_id() { return $this->id; }
	function get_rate_id() { return $this->rate_id; }
	function get_label() { return 'Historical VAT'; }
	function get_rate_percent( $context = 'view' ) { return $this->percent; }
}
class Test_Line_Item {
	public $net;
	public $tax;
	public $taxes;
	function __construct( $net, $tax ) { $this->net = $net; $this->tax = $tax; }
	function get_total() { return $this->net; }
	function get_total_tax() { return $this->tax; }
	function get_subtotal() { return 100; }
	function get_subtotal_tax() { return 10; }
	function get_taxes() { return [ 'total' => $this->taxes ?? [ 7 => $this->tax ] ]; }
	function get_name() { return 'Coupon product'; }
	function get_quantity() { return 1; }
}
class WC_Order {
	public $items;
	function get_items( $type ) { return $this->items[ $type ] ?? []; }
	function get_meta( $key ) { return [ '_yoohw_tir_country' => 'US', '_yoohw_tir_address' => 'Buyer address' ][ $key ] ?? ''; }
	function get_order_item_totals() { return []; }
	function get_currency() { return 'USD'; }
	function get_order_number() { return '123'; }
	function get_billing_state() { return ''; }
	function get_date_created() { return null; }
	function get_payment_method_title() { return ''; }
}
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$order = new WC_Order();
$tax = new Test_Tax_Item();
$product = new Test_Line_Item( 90, 9 );
$order->items = [ 'tax' => [ $tax ], 'line_item' => [ $product ] ];
$invoice_number = 'INV-123';
$generated_date = '2026-11-10';
ob_start();
require dirname( __DIR__ ) . '/templates/pdf/tax-invoice.php';
$html = ob_get_clean();
$dom = new DOMDocument();
@$dom->loadHTML( $html );
$xpath = new DOMXPath( $dom );
$values = [];
foreach ( $xpath->query( '//table[@class="items-table"]/tbody/tr/td' ) as $cell ) {
	$values[] = trim( $cell->textContent );
}
check( [ 'Coupon product', '1', '90.00', '10.00%', '9.00', '99.00' ] === $values, 'Coupon line must reconcile net + actual tax = total' );
check( false !== strpos( $html, 'Net amount' ), 'Net column must describe the discounted amount' );
check( '10.00%' === yoohw_tir_get_item_tax_rates( $product, $order ), 'Use historical 10% snapshot even if live rate changed or was deleted' );
$tax->id = 2;
$tax->percent = 0; // WooCommerce casts missing persisted metadata to zero on read.
check( '-' === yoohw_tir_get_item_tax_rates( $product, $order ), 'Legacy missing snapshot must not claim zero or current rate' );
check( 'Historical VAT' === yoohw_tir_get_order_tax_summary_rows( $order )[7]['label'], 'Legacy summary retains historical label without invented percentage' );
$tax->id = 1;
check( '0.00%' === yoohw_tir_get_order_tax_rate_percent( $order, 7 ), 'A recorded zero percent snapshot remains valid' );
$tax->percent = 10;
$order->items['line_item'] = [ new Test_Line_Item( 100, 10 ) ];
$order->items['fee'] = [ new Test_Line_Item( -10, -1 ) ];
$rows = yoohw_tir_get_order_tax_summary_rows( $order );
check( 90.0 === $rows[7]['taxable_amount'] && 9.0 === $rows[7]['tax_amount'], 'Negative taxable fee must reduce both summary amounts' );
check( 'Historical VAT (10.00%)' === $rows[7]['label'], 'Summary uses snapshot label and percentage' );
$second_tax = new Test_Tax_Item();
$second_tax->id = 3;
$second_tax->rate_id = 8;
$second_tax->percent = 20;
$second_product = new Test_Line_Item( 100, 20 );
$second_product->taxes = [ 8 => 20 ];
$split_fee = new Test_Line_Item( -20, -3 );
$split_fee->taxes = [ 7 => -1, 8 => -2 ];
$order->items['tax'][] = $second_tax;
$order->items['line_item'][] = $second_product;
$order->items['fee'] = [ $split_fee ];
$rows = yoohw_tir_get_order_tax_summary_rows( $order );
check( null === $rows[7]['taxable_amount'] && null === $rows[8]['taxable_amount'], 'A split negative fee must not subtract its full base from every rate' );
check( 9.0 === $rows[7]['tax_amount'] && 18.0 === $rows[8]['tax_amount'], 'Split fee retains exact stored tax amounts for each rate' );
ob_start();
require dirname( __DIR__ ) . '/templates/pdf/tax-invoice.php';
$dom = new DOMDocument();
$mixed_html = ob_get_clean();
@$dom->loadHTML( $mixed_html );
check( false !== strpos( $mixed_html, 'Taxable amount is not shown' ), 'Unknown bases must include an explanation' );
$xpath = new DOMXPath( $dom );
foreach ( $xpath->query( '//table[@class="tax-summary-table"]/tbody/tr/td[2]' ) as $cell ) {
	check( '-' === trim( $cell->textContent ), 'Unknown taxable base must render as a dash, not a guessed number' );
}
$exempt = new Test_Line_Item( 100, 0 );
$exempt->taxes = [];
$order->items['line_item'] = [ new Test_Line_Item( 100, 10 ), $exempt ];
$order->items['fee'] = [ new Test_Line_Item( -20, -1 ) ];
$rows = yoohw_tir_get_order_tax_summary_rows( $order );
check( null === $rows[7]['taxable_amount'] && 9.0 === $rows[7]['tax_amount'], 'Exempt items make even a single-rate fee base allocation uncertain' );
echo "PASS: coupon arithmetic, historical and missing tax snapshots, single-rate and apportioned negative fees\n";
