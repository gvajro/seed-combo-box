<?php
defined( 'ABSPATH' ) || exit;

/**
 * Carrello e ordine: validazione, prezzo dinamico, dettaglio componenti.
 * Lo stock dei componenti non viene controllato né scalato.
 */
class SCB_Cart {

	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'set_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item' ), 10, 3 );
	}

	private static function posted() {
		// phpcs:ignore WordPress.Security.NonceVerification
		return isset( $_POST['scb'] ) && is_array( $_POST['scb'] ) ? $_POST['scb'] : array();
	}

	public static function validate( $passed, $product_id ) {
		if ( ! SCB_Core::is_combo( $product_id ) ) {
			return $passed;
		}
		$res = SCB_Core::parse_selection( $product_id, self::posted() );
		if ( is_wp_error( $res ) ) {
			wc_add_notice( $res->get_error_message(), 'error' );
			return false;
		}
		return $passed;
	}

	public static function cart_item_data( $data, $product_id ) {
		if ( ! SCB_Core::is_combo( $product_id ) ) {
			return $data;
		}
		$res = SCB_Core::parse_selection( $product_id, self::posted() );
		if ( is_wp_error( $res ) ) {
			return $data;
		}
		$data['scb']     = $res;
		$data['scb_key'] = md5( wp_json_encode( $res ) ); // righe separate per selezioni diverse
		return $data;
	}

	public static function set_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['scb'] ) ) {
				continue;
			}
			$cfg = SCB_Core::get_config( $item['product_id'] );
			if ( 'sum' !== $cfg['mode'] ) {
				continue;
			}
			$item['data']->set_price( SCB_Core::cart_price( $item['product_id'], $item['scb'] ) );
		}
	}

	public static function item_data( $data, $cart_item ) {
		if ( empty( $cart_item['scb'] ) ) {
			return $data;
		}
		foreach ( SCB_Core::describe( $cart_item['product_id'], $cart_item['scb'] ) as $title => $list ) {
			$data[] = array(
				'key'   => $title,
				'value' => implode( ', ', $list ),
			);
		}
		return $data;
	}

	public static function order_item( $item, $cart_item_key, $values ) {
		if ( empty( $values['scb'] ) ) {
			return;
		}
		foreach ( SCB_Core::describe( $values['product_id'], $values['scb'] ) as $title => $list ) {
			$item->add_meta_data( $title, implode( ', ', $list ), true );
		}
		$item->add_meta_data( '_scb_components', $values['scb'], true );
	}
}
