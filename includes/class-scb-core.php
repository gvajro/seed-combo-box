<?php
defined( 'ABSPATH' ) || exit;

/**
 * Logica condivisa: configurazione, dati prodotti, validazione selezione, calcolo prezzi.
 * Nota: lo stock dei componenti NON viene né controllato né scalato.
 */
class SCB_Core {

	public static function product( $p ) {
		return $p instanceof WC_Product ? $p : wc_get_product( $p );
	}

	public static function is_combo( $p ) {
		$p = self::product( $p );
		return $p && $p->is_type( 'simple' ) && 'yes' === $p->get_meta( '_scb_enabled' );
	}

	/**
	 * Normalizza i prodotti personalizzati (non in catalogo) di una riga.
	 */
	public static function normalize_custom( $list ) {
		$out = array();
		foreach ( (array) $list as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$title = sanitize_text_field( $c['title'] ?? '' );
			if ( '' === $title ) {
				continue;
			}
			$id = sanitize_key( $c['id'] ?? '' );
			if ( '' === $id ) {
				// Stabile: lo stesso prodotto deve avere lo stesso id a ogni lettura.
				$id = 'c' . substr( md5( $title ), 0, 8 );
			}
			$raw   = trim( (string) ( $c['price'] ?? '' ) );
			$price = '' === $raw ? '' : ( is_numeric( $raw ) ? (float) $raw : (float) wc_format_decimal( $raw ) );
			$out[] = array(
				'id'       => $id,
				'title'    => $title,
				'desc'     => sanitize_text_field( $c['desc'] ?? '' ),
				'image_id' => absint( $c['image_id'] ?? 0 ),
				'price'    => $price,
			);
		}
		return $out;
	}

	/**
	 * Configurazione normalizzata di un prodotto combinato.
	 */
	public static function get_config( $p ) {
		$p    = self::product( $p );
		$rows = array();
		$raw  = $p ? $p->get_meta( '_scb_rows' ) : array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $r ) {
				$items  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $r['items'] ?? array() ) ) ) ) );
				$custom = self::normalize_custom( $r['custom'] ?? array() );
				if ( ! $items && ! $custom ) {
					continue;
				}
				$n   = count( $items ) + count( $custom );
				$max = absint( $r['max'] ?? 0 );
				if ( $max <= 0 || $max > $n ) {
					$max = $n;
				}
				$min    = min( absint( $r['min'] ?? 0 ), $max );
				$rows[] = array(
					'title'  => (string) ( $r['title'] ?? '' ),
					'min'    => $min,
					'max'    => $max,
					'items'  => $items,
					'custom' => $custom,
				);
			}
		}

		foreach ( $rows as $i => $row ) {
			if ( '' === trim( $row['title'] ) ) {
				$rows[ $i ]['title'] = 'Sezione ' . ( $i + 1 );
			}
		}

		$total_min = $p ? absint( $p->get_meta( '_scb_total_min' ) ) : 0;
		$total_max = $p ? absint( $p->get_meta( '_scb_total_max' ) ) : 0;
		if ( $total_max && $total_max < $total_min ) {
			$total_max = $total_min;
		}
		$title = $p ? trim( (string) $p->get_meta( '_scb_title' ) ) : '';

		return array(
			'enabled'     => $p && 'yes' === $p->get_meta( '_scb_enabled' ),
			'mode'        => ( $p && 'fixed' === $p->get_meta( '_scb_mode' ) ) ? 'fixed' : 'sum',
			'box_cost'    => $p ? (float) $p->get_meta( '_scb_box_cost' ) : 0.0,
			'show_prices' => ! $p || 'no' !== $p->get_meta( '_scb_show_prices' ),
			'total_min'   => $total_min,
			'total_max'   => $total_max,
			'title'       => $title ? $title : 'Aggiungi prodotti alla box',
			'rows'        => $rows,
		);
	}

	/**
	 * Dati di un prodotto di catalogo selezionabile (semplice o variabile). Null se non utilizzabile.
	 */
	public static function item_data( $pid ) {
		$p = wc_get_product( $pid );
		if ( ! $p || 'publish' !== $p->get_status() ) {
			return null;
		}

		$data = array(
			'id'         => $p->get_id(),
			'name'       => $p->get_name(),
			'image'      => $p->get_image( 'woocommerce_thumbnail' ),
			'price'      => 0.0,
			'variations' => array(),
		);

		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_available_variations( 'objects' ) as $v ) {
				if ( ! $v->is_purchasable() ) {
					continue;
				}
				$label = wc_get_formatted_variation( $v, true, false, false );
				$data['variations'][] = array(
					'id'    => $v->get_id(),
					'label' => $label ? $label : $v->get_name(),
					'price' => (float) $v->get_price(),
				);
			}
			if ( empty( $data['variations'] ) ) {
				return null;
			}
		} elseif ( $p->is_type( 'simple' ) ) {
			if ( ! $p->is_purchasable() ) {
				return null;
			}
			$data['price'] = (float) $p->get_price();
		} else {
			return null;
		}

		return $data;
	}

	/**
	 * Prezzo minimo ("a partire da"): scatola + selezione più economica che rispetta i limiti.
	 */
	public static function min_price( $p ) {
		$cfg = self::get_config( $p );
		$sum = $cfg['box_cost'];
		if ( 'sum' !== $cfg['mode'] ) {
			return $sum;
		}

		$taken = 0;
		$pool  = array();
		foreach ( $cfg['rows'] as $row ) {
			$prices = array();
			foreach ( $row['items'] as $pid ) {
				$it = self::item_data( $pid );
				if ( ! $it ) {
					continue;
				}
				$prices[] = $it['variations'] ? min( wp_list_pluck( $it['variations'], 'price' ) ) : $it['price'];
			}
			foreach ( $row['custom'] as $cu ) {
				$prices[] = (float) $cu['price'];
			}
			sort( $prices );
			$head   = array_slice( $prices, 0, $row['min'] );
			$sum   += array_sum( $head );
			$taken += count( $head );
			$pool   = array_merge( $pool, array_slice( $prices, $row['min'], max( 0, $row['max'] - $row['min'] ) ) );
		}

		$need = max( $cfg['total_min'], 1 ) - $taken;
		if ( $need > 0 ) {
			sort( $pool );
			$sum += array_sum( array_slice( $pool, 0, $need ) );
		}

		return $sum;
	}

	/**
	 * Valida la selezione inviata dal cliente. Restituisce l'elenco componenti o WP_Error.
	 * $raw = array( indice_riga => array( 'pid:vid' | 'c:ID_custom', ... ) )
	 */
	public static function parse_selection( $p, $raw ) {
		$cfg = self::get_config( $p );
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();

		$components = array();
		$seen       = array();

		foreach ( $cfg['rows'] as $ri => $row ) {
			$vals  = ( isset( $raw[ $ri ] ) && is_array( $raw[ $ri ] ) ) ? array_values( $raw[ $ri ] ) : array();
			$count = 0;

			foreach ( $vals as $val ) {
				if ( ! is_scalar( $val ) ) {
					return new WP_Error( 'scb', 'Selezione non valida. Riprova.' );
				}
				$parts = explode( ':', sanitize_text_field( (string) $val ) );

				// Prodotto personalizzato (non in catalogo).
				if ( 'c' === ( $parts[0] ?? '' ) ) {
					$cid   = sanitize_key( $parts[1] ?? '' );
					$found = null;
					foreach ( $row['custom'] as $cu ) {
						if ( $cu['id'] === $cid ) {
							$found = $cu;
						}
					}
					if ( ! $found ) {
						return new WP_Error( 'scb', 'Selezione non valida. Riprova.' );
					}
					$key = $ri . '-c-' . $cid;
					if ( isset( $seen[ $key ] ) ) {
						return new WP_Error( 'scb', 'Ogni prodotto può essere scelto una sola volta per sezione.' );
					}
					$seen[ $key ] = true;
					$components[] = array(
						'row'          => $ri,
						'product_id'   => 0,
						'variation_id' => 0,
						'custom_id'    => $cid,
						'name'         => $found['title'],
						'variation'    => '',
					);
					$count++;
					continue;
				}

				// Prodotto di catalogo.
				$pid = absint( $parts[0] ?? 0 );
				$vid = absint( $parts[1] ?? 0 );

				if ( ! $pid || ! in_array( $pid, $row['items'], true ) ) {
					return new WP_Error( 'scb', 'Selezione non valida. Riprova.' );
				}
				$key = $ri . '-' . $pid;
				if ( isset( $seen[ $key ] ) ) {
					return new WP_Error( 'scb', 'Ogni prodotto può essere scelto una sola volta per sezione.' );
				}
				$seen[ $key ] = true;

				$parent = wc_get_product( $pid );
				if ( ! $parent || 'publish' !== $parent->get_status() ) {
					return new WP_Error( 'scb', 'Uno dei prodotti scelti non è più disponibile.' );
				}

				$var_label = '';
				if ( $parent->is_type( 'variable' ) ) {
					$buy = $vid ? wc_get_product( $vid ) : false;
					if ( ! $buy || $buy->get_parent_id() !== $pid ) {
						return new WP_Error( 'scb', 'Scegli una variante per «' . $parent->get_name() . '».' );
					}
					$var_label = wc_get_formatted_variation( $buy, true, false, false );
				} elseif ( $parent->is_type( 'simple' ) && ! $vid ) {
					$buy = $parent;
				} else {
					return new WP_Error( 'scb', 'Selezione non valida. Riprova.' );
				}

				if ( ! $buy->is_purchasable() ) {
					return new WP_Error( 'scb', '«' . $parent->get_name() . '» non è disponibile.' );
				}

				$components[] = array(
					'row'          => $ri,
					'product_id'   => $pid,
					'variation_id' => $vid,
					'custom_id'    => '',
					'name'         => $parent->get_name(),
					'variation'    => $var_label,
				);
				$count++;
			}

			if ( $count < $row['min'] ) {
				return new WP_Error( 'scb', 'Nella sezione «' . $row['title'] . '» scegli almeno ' . self::pieces( $row['min'] ) . '.' );
			}
			if ( $count > $row['max'] ) {
				return new WP_Error( 'scb', 'Nella sezione «' . $row['title'] . '» puoi scegliere al massimo ' . self::pieces( $row['max'] ) . '.' );
			}
		}

		$total = count( $components );
		$min   = $cfg['total_min'] ? $cfg['total_min'] : 1;
		if ( $total < $min ) {
			return new WP_Error( 'scb', 'Scegli almeno ' . self::pieces( $min ) . '.' );
		}
		if ( $cfg['total_max'] && $total > $cfg['total_max'] ) {
			return new WP_Error( 'scb', 'Puoi scegliere al massimo ' . self::pieces( $cfg['total_max'] ) . ' in totale.' );
		}

		return $components;
	}

	/**
	 * "1 prodotto" / "N prodotti".
	 */
	public static function pieces( $n ) {
		return $n . ( 1 === (int) $n ? ' prodotto' : ' prodotti' );
	}

	/**
	 * Prezzo finale della box per una selezione (prezzi correnti dei componenti).
	 */
	public static function cart_price( $p, array $components ) {
		$cfg = self::get_config( $p );
		$sum = $cfg['box_cost'];
		foreach ( $components as $c ) {
			if ( ! empty( $c['custom_id'] ) ) {
				$row = $cfg['rows'][ $c['row'] ] ?? null;
				if ( $row ) {
					foreach ( $row['custom'] as $cu ) {
						if ( $cu['id'] === $c['custom_id'] ) {
							$sum += (float) $cu['price'];
						}
					}
				}
				continue;
			}
			$buy = wc_get_product( ! empty( $c['variation_id'] ) ? $c['variation_id'] : $c['product_id'] );
			if ( $buy ) {
				$sum += (float) $buy->get_price();
			}
		}
		return $sum;
	}

	/**
	 * Raggruppa i componenti per titolo riga, con testo leggibile.
	 */
	public static function describe( $p, array $components ) {
		$cfg    = self::get_config( $p );
		$groups = array();
		foreach ( $components as $c ) {
			$title = isset( $cfg['rows'][ $c['row'] ] ) ? $cfg['rows'][ $c['row'] ]['title'] : 'Prodotti';
			$text  = $c['name'] . ( $c['variation'] ? ' (' . $c['variation'] . ')' : '' );
			$groups[ $title ][] = $text;
		}
		return $groups;
	}
}
