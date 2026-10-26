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

	/** Descrizioni aggiunte (1.0.6) anche alle box create dalla 1.0.5. */
	const OPTION_DESC = 'scb_defaults_descriptions';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_create' ) );
	}

	public static function maybe_create() {
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		if ( ! get_option( self::OPTION ) ) {
			// Segnato subito: anche se qualcosa va storto non si rischiano doppioni.
			update_option( self::OPTION, gmdate( 'c' ), false );
			update_option( self::OPTION_DESC, gmdate( 'c' ), false );
			foreach ( self::boxes() as $box ) {
				self::create( $box );
			}
			return;
		}

		if ( ! get_option( self::OPTION_DESC ) ) {
			update_option( self::OPTION_DESC, gmdate( 'c' ), false );
			self::add_descriptions();
		}
	}

	/**
	 * Box predefinite già create senza descrizione: la aggiunge solo se il campo è ancora vuoto,
	 * così non tocca testi scritti a mano.
	 */
	private static function add_descriptions() {
		foreach ( self::boxes() as $box ) {
			$ids = get_posts(
				array(
					'post_type'   => 'product',
					'post_status' => 'any',
					'title'       => $box['name'],
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_key'    => '_scb_enabled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				if ( '' === trim( $product->get_short_description() ) ) {
					$product->set_short_description( $box['short'] );
				}
				if ( '' === trim( $product->get_description() ) ) {
					$product->set_description( $box['desc'] );
				}
				$product->save();
			}
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
		$product->set_short_description( $box['short'] );
		$product->set_description( $box['desc'] );
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
				'name'  => 'Vesuvio e Bollicine',
				'short' => 'Box rosa 36×26×18 cm: una bottiglia di bollicine e una golosità al cioccolato «Vesuvio Buono», scelte da te.',
				'desc'  => "Componi il tuo cofanetto in due passaggi.\n\nPrima scegli le bollicine: Franciacorta oppure Trento D.O.C.\n\nPoi abbinale al cioccolato: Les cabosses da 12 pezzi «Vesuvio Buono» oppure la tavoletta al cioccolato fondente 70% «Vesuvio Buono» con una confezione di biscotti.\n\nIl prezzo comprende la scatola e i prodotti che scegli.",
				'cost'  => 7,
				'min'   => 0,
				'max'   => 0,
				'rows'  => array(
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
				'name'  => 'Lo Scrigno del Maestro',
				'short' => 'Box rosa 36×26×18 cm: quattro specialità della pasticceria, una per ogni sezione, scelte da te.',
				'desc'  => "Un piccolo tesoro di pasticceria da comporre a tuo gusto: scegli un prodotto per ognuna delle quattro sezioni.\n\nCioccolattini: Les cabosses da 12 o da 18 pezzi.\nBiscotti: cantucci, amaretti, roccocò o mostaccioli.\nCrema spalmabile: cacao e nocciola, nocciola o pistacchio.\nBabà mignon: al rum, al limoncello o alla pellecchiella.\n\nIl prezzo comprende la scatola e i prodotti che scegli.",
				'cost'  => 12,
				'min'   => 0,
				'max'   => 0,
				'rows'  => array(
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
				'name'  => 'Alchimia del gusto',
				'short' => 'Box 40×40 cm: da 6 a 9 pezzi a scelta tra cioccolato, biscotti, creme, babà e dolci della tradizione.',
				'desc'  => "La box più ricca, da comporre pezzo per pezzo: scegli da 6 a 9 prodotti tra tutte le sezioni, nelle quantità che preferisci.\n\nCioccolattini, biscotti, creme spalmabili, babà in vaso cottura, dolci della tradizione mignon, tavolette e primitive: mescola i sapori e crea il tuo equilibrio.\n\nIl prezzo comprende la scatola e i prodotti che scegli.",
				'cost'  => 22,
				'min'   => 6,
				'max'   => 9,
				'rows'  => array(
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
