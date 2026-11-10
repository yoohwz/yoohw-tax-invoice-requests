<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Serializes invoice operations on one order, across PHP workers and storage modes. */
class YoOhw_Tir_Order_Lock {
	public static function acquire( $order_id ) {
		global $wpdb;

		$name = 'yoohw_tir_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . absint( $order_id ) );
		// Connection-owned: no expiring lease can allow another worker in mid-send.
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
		if ( '1' !== (string) $acquired ) {
			return new WP_Error( 'invoice_busy', __( 'This invoice is being processed or could not be locked. Please try again shortly.', 'yoohw-tax-invoice-requests' ) );
		}

		return $name;
	}

	public static function release( $name ) {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}

	/** Refresh a previously loaded order only while holding its invoice lock. */
	public static function refresh( WC_Order $order ) {
		$store = $order->get_data_store();
		// Recent HPOS versions cache raw metadata separately from WC_Data's cache.
		if ( method_exists( $store->get_current_class_name(), 'clear_cached_data' ) ) {
			$store->clear_cached_data( [ $order->get_id() ] );
		} else {
			clean_post_cache( $order->get_id() );
		}
		$store->read( $order );
		$order->read_meta_data( true );
	}
}
