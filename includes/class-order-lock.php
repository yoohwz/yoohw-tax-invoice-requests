<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Serializes invoice operations across workers, independently of database reconnects. */
class YoOhw_Tir_Order_Lock {
	public static function acquire( $order_id ) {
		$directory = YoOhw_Tir_PDF_Generator::prepare_storage();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$path = $directory . '.order-' . absint( $order_id ) . '.lock';
		$lock = is_link( $path ) ? false : @fopen( $path, 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( is_resource( $lock ) ) {
				fclose( $lock );
			}
			return new WP_Error( 'invoice_busy', __( 'This invoice is being processed or could not be locked. Please try again shortly.', 'yoohw-tax-invoice-requests' ) );
		}

		return $lock;
	}

	public static function release( $lock ) {
		if ( is_resource( $lock ) ) {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
		// Keep the file: unlinking lets a competing worker lock a different inode.
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
