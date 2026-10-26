<?php
defined( 'ABSPATH' ) || exit;

/**
 * Box predefinite (dal listino "Componi il tuo cofanetto"), create una sola volta per sito.
 * I componenti sono prodotti non in elenco, così le box funzionano anche senza quei prodotti
 * nel catalogo; si possono poi sostituire con i prodotti veri dalla scheda. Le box nascono in
 * bozza: si pubblicano dopo aver controllato prezzi e foto.
 */
class SCB_Defaults {

	const OPTION = 'scb_defaults_created';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_create' ) );
	}

	public static function maybe_create() {
		if ( get_option( self::OPTION ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		// Segnato subito: anche se qualcosa va storto non si rischiano doppioni.
		update_option( self::OPTION, gmdate( 'c' ), false );

		foreach ( self::boxes() as $box ) {
			self::create( $box );
		}
	}

	private static function create( $box ) {
		$rows = array();
		foreach ( $box['rows'] as $row ) {
			$custom = array();
			foreach ( $row[3] as $title => $price ) {
				$custom[] = array(
					'title' => $title,
					'price' => $price,
				);
			}
			$custom = SCB_Core::normalize_custom( $custom );
			$rows[] = array(
				'title'  => $row[0],
				'min'    => $row[1],
				'max'    => $row[2] ? $row[2] : count( $custom ),
				'items'  => array(),
				'custom' => $custom,
			);
		}

		$product = new WC_Product_Simple();
		$product->set_name( $box['name'] );
		$product->set_status( 'draft' );
		$product->update_meta_data( '_scb_enabled', 'yes' );
		$product->update_meta_data( '_scb_mode', 'sum' );
		$product->update_meta_data( '_scb_box_cost', $box['cost'] );
		$product->update_meta_data( '_scb_show_prices', 'yes' );
		$product->update_meta_data( '_scb_title', '' );
		$product->update_meta_data( '_scb_total_min', $box['min'] );
		$product->update_meta_data( '_scb_total_max', $box['max'] );
		$product->update_meta_data( '_scb_rows', $rows );
		$product->set_regular_price( (string) SCB_Core::min_price( $product ) );
		$product->save();
	}

	/**
	 * Righe: array( titolo, minimo, massimo (0 = tutti), array( prodotto => prezzo ) ).
	 * Prezzo '' = non indicato nel listino, da completare.
	 */
	private static function boxes() {
		return array(
			array(
				'name' => 'Vesuvio e Bollicine',
				'cost' => 7,
				'min'  => 0,
				'max'  => 0,
				'rows' => array(
					array( 'Vino', 1, 1, array(
						'Franciacorta' => 20,
						'Trento D.O.C.' => 50,
					) ),
					array( 'Cioccolato', 1, 1, array(
						'Les cabosses 12 pezzi "Vesuvio Buono"' => 19.90,
						'Tavoletta fondente 70% "Vesuvio Buono" + confezione di biscotti' => 19.80,
					) ),
				),
			),
			array(
				'name' => 'Lo Scrigno del Maestro',
				'cost' => 12,
				'min'  => 0,
				'max'  => 0,
				'rows' => array(
					array( 'Cioccolattini', 1, 1, array(
						'Les cabosses 12 pezzi' => 19.90,
						'Les cabosses 18 pezzi' => 28.80,
					) ),
					array( 'Biscotti', 1, 1, array(
						'Cantucci'    => 6.90,
						'Amaretti'    => 10.50,
						'Roccocò'     => 7.50,
						'Mostaccioli' => 7.50,
					) ),
					array( 'Crema spalmabile', 1, 1, array(
						'Cacao e nocciola' => 9.50,
						'Nocciola'         => 12.90,
						'Pistacchio'       => 13.50,
					) ),
					array( 'Babà mignon', 1, 1, array(
						'Rum'          => 7.70,
						'Limoncello'   => 7.70,
						'Pellecchiella' => 7.70,
					) ),
				),
			),
			array(
				'name' => 'Alchimia del gusto',
				'cost' => 22,
				'min'  => 6,
				'max'  => 9,
				'rows' => array(
					array( 'I cioccolattini', 0, 0, array(
						'Les cabosses 12 pezzi' => 19.90,
						'Les cabosses 18 pezzi' => 28.80,
						'Les cabosses 36 pezzi' => 49.90,
						'Les Truffes'           => 19.90,
					) ),
					array( 'I biscotti', 0, 0, array(
						'Cantucci'                    => 6.90,
						'Brutti ma buoni'             => '',
						'Amaretti'                    => 10.50,
						'Soffiati al latte o fondente' => '',
					) ),
					array( 'Le creme spalmabili', 0, 0, array(
						'Cacao e nocciola' => 9.50,
						'Nocciola'         => 12.90,
						'Pistacchio'       => '',
					) ),
					array( 'Il babà in vaso cottura', 0, 0, array(
						'Rum'           => '',
						'Limoncello'    => '',
						'Pellecchiella' => '',
						'Arancia'       => '',
					) ),
					array( 'Dolci della tradizione mignon', 0, 0, array(
						'Roccocò'     => '',
						'Mostaccioli' => '',
					) ),
					array( 'La tavoletta', 0, 0, array(
						'Vesuvio Buono' => '',
						'Pistacchio'    => '',
						'Goji'          => '',
					) ),
					array( 'Primitive', 0, 0, array(
						'Mandorla'           => '',
						"Scorzette d'arance" => '',
					) ),
				),
			),
		);
	}
}
