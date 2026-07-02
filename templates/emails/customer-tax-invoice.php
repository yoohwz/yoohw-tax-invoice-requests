<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Required WooCommerce email template hook.
do_action( 'woocommerce_email_header', $email_heading, $email );

?>

<p>
	<?php
	printf(
		/* translators: %s: Customer first name. */
		esc_html__( 'Hi %s,', 'yoohw-tax-invoice-requests' ),
		esc_html( $order->get_billing_first_name() )
	);
	?>
</p>

<p>
	<?php
	printf(
		/* translators: %s: WooCommerce order number. */
		esc_html__( 'Your tax invoice for order #%s has been generated and is attached to this email.', 'yoohw-tax-invoice-requests' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<?php if ( $order->get_meta( '_yoohw_tir_invoice_number' ) ) : ?>
	<p>
		<strong><?php esc_html_e( 'Invoice number:', 'yoohw-tax-invoice-requests' ); ?></strong>
		<?php echo esc_html( $order->get_meta( '_yoohw_tir_invoice_number' ) ); ?>
	</p>
<?php endif; ?>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Required WooCommerce email template hook.
do_action( 'woocommerce_email_footer', $email );