<?php
defined( 'ABSPATH' ) || exit;

/**
 * Front-end: blocco "Aggiungi prodotti alla box" (accordion) nella scheda prodotto, compatibile Woodmart.
 */
class SCB_Frontend {

	/**
	 * Prodotti già mostrati in questa richiesta: il blocco va stampato una volta sola
	 * (Woodmart ristampa il form nello sticky add to cart).
	 *
	 * @var array
	 */
	private static $rendered = array();

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render' ), 5 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_text' ), 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'loop_url' ), 20, 2 );
		add_filter( 'woocommerce_product_supports', array( __CLASS__, 'supports' ), 20, 3 );
	}

	public static function register_assets() {
		wp_register_style( 'scb-front', SCB_URL . 'assets/front.css', array(), SCB_VERSION );
		wp_register_script( 'scb-front', SCB_URL . 'assets/front.js', array(), SCB_VERSION, true );

		// Quick view di Woodmart: la scheda arriva via ajax, gli asset devono essere già nella pagina.
		if ( function_exists( 'woodmart_get_opt' ) && woodmart_get_opt( 'quick_view' ) ) {
			wp_enqueue_style( 'scb-front' );
			wp_enqueue_script( 'scb-front' );
		}
	}

	public static function price_html( $html, $product ) {
		if ( SCB_Core::is_combo( $product ) ) {
			$cfg = SCB_Core::get_config( $product );
			if ( 'sum' === $cfg['mode'] && $cfg['rows'] ) {
				// Box non componibile: nessun "a partire da" (e nessun prezzo).
				if ( ! SCB_Core::is_completable( $product ) ) {
					return '';
				}
				return '<span class="scb-from">A partire da</span> ' . wc_price( SCB_Core::min_price( $product ) );
			}
		}
		return $html;
	}

	public static function loop_text( $text, $product ) {
		return SCB_Core::is_combo( $product ) ? 'Componi la box' : $text;
	}

	public static function loop_url( $url, $product ) {
		return SCB_Core::is_combo( $product ) ? get_permalink( $product->get_id() ) : $url;
	}

	public static function supports( $supports, $feature, $product ) {
		if ( 'ajax_add_to_cart' === $feature && SCB_Core::is_combo( $product ) ) {
			return false;
		}
		return $supports;
	}

	private static function rule_text( $min, $max ) {
		if ( $min === $max ) {
			return 'Scegli ' . $max;
		}
		return $min ? 'Scegli da ' . $min . ' a ' . $max : 'Scegli fino a ' . $max;
	}

	public static function render() {
		global $product;
		if ( ! SCB_Core::is_combo( $product ) ) {
			return;
		}
		$cfg = SCB_Core::get_config( $product );
		if ( ! $cfg['rows'] || isset( self::$rendered[ $product->get_id() ] ) ) {
			return;
		}
		self::$rendered[ $product->get_id() ] = true;

		wp_enqueue_style( 'scb-front' );
		wp_enqueue_script( 'scb-front' );

		// Prodotti davvero disponibili per riga (pubblicati e acquistabili).
		$available = array();
		foreach ( $cfg['rows'] as $ri => $row ) {
			$available[ $ri ] = array();
			foreach ( $row['items'] as $pid ) {
				$it = SCB_Core::item_data( $pid );
				if ( $it ) {
					$available[ $ri ][] = $it;
				}
			}
		}
		$blocked = ! SCB_Core::is_completable( $product );

		$js = array(
			'mode'       => $cfg['mode'],
			'boxCost'    => $cfg['box_cost'],
			'totalMin'   => $cfg['total_min'],
			'totalMax'   => $cfg['total_max'],
			'showPrices' => $cfg['show_prices'],
			'currency'   => array(
				'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES ),
				'decimals' => wc_get_price_decimals(),
				'decSep'   => wc_get_price_decimal_separator(),
				'thouSep'  => wc_get_price_thousand_separator(),
				'format'   => get_woocommerce_price_format(),
			),
		);

		$uid = 'scb-' . $product->get_id();

		echo '<div class="scb' . ( $blocked ? ' is-blocked' : '' ) . '" id="' . esc_attr( $uid ) . '" data-scb="' . esc_attr( wp_json_encode( $js ) ) . '">';
		echo '<h3 class="scb-title">' . esc_html( $cfg['title'] ) . '</h3>';
		if ( $blocked ) {
			echo '<p class="scb-unavailable">Questa box al momento non si può comporre: alcuni prodotti non sono disponibili.</p>';
		}
		echo '<div class="scb-summary"><span class="scb-counter"></span>';
		if ( 'sum' === $cfg['mode'] ) {
			echo '<strong class="scb-total"></strong>';
		}
		echo '</div>';
		echo '<div class="scb-accordion">';

		foreach ( $cfg['rows'] as $ri => $row ) {
			$count = count( $available[ $ri ] ) + count( $row['custom'] );
			$max   = min( $row['max'], $count ); // con prodotti non disponibili la riga si completa prima
			$head  = $uid . '-h' . $ri;
			$body  = $uid . '-b' . $ri;

			echo '<section class="scb-row' . ( 0 === $ri ? ' is-open' : '' ) . '" data-min="' . esc_attr( $row['min'] ) . '" data-max="' . esc_attr( $max ) . '" data-title="' . esc_attr( $row['title'] ) . '">';
			echo '<button type="button" class="scb-row-head" id="' . esc_attr( $head ) . '" aria-controls="' . esc_attr( $body ) . '" aria-expanded="' . ( 0 === $ri ? 'true' : 'false' ) . '">';
			echo '<span class="scb-row-num" aria-hidden="true">' . esc_html( $ri + 1 ) . '</span>';
			echo '<span class="scb-row-label"><span class="scb-row-title">' . esc_html( $row['title'] ) . '</span><span class="scb-row-rule">' . esc_html( self::rule_text( $row['min'], max( $row['min'], $max ) ) ) . '</span></span>';
			echo '<span class="scb-row-status">0 / ' . esc_html( $max ) . '</span>';
			echo '</button>';
			echo '<div class="scb-row-body" id="' . esc_attr( $body ) . '" role="region" aria-labelledby="' . esc_attr( $head ) . '">';

			if ( ! $count ) {
				echo '<p class="scb-empty">Nessun prodotto disponibile in questa sezione.</p>';
			} else {
				if ( $count < $row['min'] ) {
					echo '<p class="scb-empty">In questa sezione ' . esc_html( 1 === $count ? 'è disponibile solo 1 prodotto' : 'sono disponibili solo ' . $count . ' prodotti' ) . ' su ' . esc_html( $row['min'] ) . ' richiesti.</p>';
				}
				echo '<div class="scb-grid">';
				foreach ( $available[ $ri ] as $it ) {
					self::product_item( $ri, $it, $cfg['show_prices'] );
				}
				foreach ( $row['custom'] as $cu ) {
					self::custom_item( $ri, $cu, $cfg['show_prices'] );
				}
				echo '</div>';
			}

			echo '<button type="button" class="scb-next button">Avanti</button></div>';
			echo '</section>';
		}

		echo '</div>';
		echo '<p class="scb-msg" role="status" aria-live="polite"></p>';
		echo '</div>';
	}

	private static function plain_price( $price ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES );
	}

	private static function product_item( $ri, $it, $show_prices ) {
		$has_var = ! empty( $it['variations'] );
		$first   = $has_var ? $it['variations'][0] : null;
		$price   = $has_var ? $first['price'] : $it['price'];
		$value   = $it['id'] . ':' . ( $has_var ? $first['id'] : 0 );

		echo '<div class="scb-item" data-pid="' . esc_attr( $it['id'] ) . '" data-price="' . esc_attr( $price ) . '">';
		echo '<label class="scb-item-main">';
		echo '<input type="checkbox" class="scb-check" name="scb[' . esc_attr( $ri ) . '][]" value="' . esc_attr( $value ) . '" />';
		echo '<span class="scb-thumb">' . $it['image'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="scb-info"><span class="scb-name">' . esc_html( $it['name'] ) . '</span>';
		if ( $show_prices ) {
			echo '<span class="scb-price">' . esc_html( self::plain_price( $price ) ) . '</span>';
		}
		echo '</span></label>';

		if ( $has_var ) {
			echo '<select class="scb-var" aria-label="Variante di ' . esc_attr( $it['name'] ) . '">';
			foreach ( $it['variations'] as $v ) {
				$text = $v['label'] . ( $show_prices ? ' – ' . self::plain_price( $v['price'] ) : '' );
				echo '<option value="' . esc_attr( $v['id'] ) . '" data-price="' . esc_attr( $v['price'] ) . '">' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
		}
		echo '</div>';
	}

	private static function custom_item( $ri, $cu, $show_prices ) {
		$price = '' === $cu['price'] ? 0 : (float) $cu['price'];
		$img   = $cu['image_id'] ? wp_get_attachment_image( $cu['image_id'], 'woocommerce_thumbnail' ) : wc_placeholder_img( 'woocommerce_thumbnail' );

		echo '<div class="scb-item scb-item-custom" data-price="' . esc_attr( $price ) . '">';
		echo '<label class="scb-item-main">';
		echo '<input type="checkbox" class="scb-check" name="scb[' . esc_attr( $ri ) . '][]" value="c:' . esc_attr( $cu['id'] ) . '" />';
		echo '<span class="scb-thumb">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="scb-info"><span class="scb-name">' . esc_html( $cu['title'] ) . '</span>';
		if ( '' !== $cu['desc'] ) {
			echo '<span class="scb-desc">' . esc_html( $cu['desc'] ) . '</span>';
		}
		if ( $show_prices && '' !== $cu['price'] ) {
			echo '<span class="scb-price">' . esc_html( self::plain_price( $price ) ) . '</span>';
		}
		echo '</span></label></div>';
	}
}
