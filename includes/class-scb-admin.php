<?php
defined( 'ABSPATH' ) || exit;

/**
 * Backend: tab "Box combinato" nella scheda prodotto con campo ripetitore "Righe prodotto".
 */
class SCB_Admin {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'current_menu' ) );
	}

	/**
	 * Voce "Box componibili" sotto Prodotti: elenco prodotti filtrato sulle box.
	 */
	public static function menu() {
		add_submenu_page( 'edit.php?post_type=product', 'Box componibili', 'Box componibili', 'edit_products', 'edit.php?post_type=product&scb_combo=1' );
	}

	private static function is_combo_list() {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo un filtro di visualizzazione.
		return is_admin() && 'edit.php' === $pagenow && ! empty( $_GET['scb_combo'] ) && 'product' === ( $_GET['post_type'] ?? '' );
	}

	public static function filter_list( $query ) {
		if ( ! $query->is_main_query() || ! self::is_combo_list() ) {
			return;
		}
		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'key'   => '_scb_enabled',
			'value' => 'yes',
		);
		$query->set( 'meta_query', $meta );
	}

	public static function current_menu( $submenu_file ) {
		return self::is_combo_list() ? 'edit.php?post_type=product&scb_combo=1' : $submenu_file;
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'product' !== get_post_type() ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'scb-admin', SCB_URL . 'assets/admin.css', array(), SCB_VERSION );
		wp_enqueue_script( 'scb-admin', SCB_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wc-enhanced-select' ), SCB_VERSION, true );
	}

	public static function tab( $tabs ) {
		$tabs['scb'] = array(
			'label'    => 'Box combinato',
			'target'   => 'scb_data',
			'class'    => array( 'show_if_simple' ),
			'priority' => 65,
		);
		return $tabs;
	}

	public static function panel() {
		global $post;
		$product = wc_get_product( $post->ID );
		$cfg     = SCB_Core::get_config( $product );

		echo '<div id="scb_data" class="panel woocommerce_options_panel hidden">';

		woocommerce_wp_checkbox( array(
			'id'          => '_scb_enabled',
			'label'       => 'Prodotto combinato',
			'description' => 'Il cliente compone la box scegliendo i prodotti riga per riga.',
			'value'       => $cfg['enabled'] ? 'yes' : 'no',
		) );

		echo '<div class="scb-options">';

		woocommerce_wp_select( array(
			'id'          => '_scb_mode',
			'label'       => 'Calcolo del prezzo',
			'options'     => array(
				'sum'   => 'Somma: costo scatola + prodotti scelti',
				'fixed' => 'Prezzo fisso (campo "Prezzo di listino")',
			),
			'value'       => $cfg['mode'],
			'desc_tip'    => true,
			'description' => 'Con "Somma" il prezzo del prodotto viene calcolato in automatico e mostrato come "A partire da".',
		) );

		woocommerce_wp_text_input( array(
			'id'        => '_scb_box_cost',
			'label'     => 'Costo scatola (' . get_woocommerce_currency_symbol() . ')',
			'data_type' => 'price',
			'value'     => $cfg['box_cost'] ? wc_format_localized_price( $cfg['box_cost'] ) : '',
		) );

		woocommerce_wp_checkbox( array(
			'id'          => '_scb_show_prices',
			'label'       => 'Mostra prezzi singoli',
			'description' => 'Mostra il prezzo di ogni prodotto selezionabile.',
			'value'       => $cfg['show_prices'] ? 'yes' : 'no',
		) );

		woocommerce_wp_text_input( array(
			'id'    => '_scb_title',
			'label' => 'Titolo del blocco',
			'value' => $cfg['title'],
		) );

		woocommerce_wp_text_input( array(
			'id'                => '_scb_total_min',
			'label'             => 'Minimo pezzi totali',
			'type'              => 'number',
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			'desc_tip'          => true,
			'description'       => '0 = nessun minimo (serve comunque almeno 1 prodotto).',
			'value'             => $cfg['total_min'],
		) );

		woocommerce_wp_text_input( array(
			'id'                => '_scb_total_max',
			'label'             => 'Massimo pezzi totali',
			'type'              => 'number',
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			'desc_tip'          => true,
			'description'       => '0 = nessun massimo.',
			'value'             => $cfg['total_max'],
		) );

		echo '<div class="scb-rows-wrap"><h4>Righe prodotto</h4>';
		echo '<p class="description">Ogni riga è una sezione dell\'accordion. Per ogni riga indica quanti prodotti scegliere (min/max), quali prodotti del catalogo e, se serve, prodotti non in elenco.</p>';
		echo '<div id="scb-rows">';
		foreach ( $cfg['rows'] as $i => $row ) {
			self::row_html( $i, $row );
		}
		echo '</div>';
		echo '<p><button type="button" class="button button-primary" id="scb-add-row">+ Aggiungi riga prodotto</button></p>';
		echo '</div>';

		echo '</div>'; // .scb-options

		// Template riga e template prodotto personalizzato.
		echo '<script type="text/html" id="scb-row-tpl">';
		self::row_html( '__I__', array( 'title' => '', 'min' => 1, 'max' => 1, 'items' => array(), 'custom' => array() ) );
		echo '</script>';
		echo '<script type="text/html" id="scb-custom-tpl">';
		self::custom_html( '__I__', '__K__', array( 'id' => '', 'title' => '', 'desc' => '', 'image_id' => 0, 'price' => '' ) );
		echo '</script>';

		self::modal_html();

		echo '</div>';
	}

	private static function row_html( $i, $row ) {
		?>
		<div class="scb-row" data-index="<?php echo esc_attr( $i ); ?>">
			<div class="scb-row-head">
				<span class="scb-handle dashicons dashicons-menu" title="Trascina per riordinare"></span>
				<input type="text" class="scb-row-title" name="scb_rows[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="Titolo riga (es. Cioccolattini)" />
				<label>Min <input type="number" min="0" step="1" name="scb_rows[<?php echo esc_attr( $i ); ?>][min]" value="<?php echo esc_attr( $row['min'] ); ?>" /></label>
				<label>Max <input type="number" min="0" step="1" name="scb_rows[<?php echo esc_attr( $i ); ?>][max]" value="<?php echo esc_attr( $row['max'] ); ?>" /></label>
				<a href="#" class="scb-remove" title="Rimuovi riga">&times;</a>
			</div>
			<select class="wc-product-search" multiple="multiple" style="width:100%;" name="scb_rows[<?php echo esc_attr( $i ); ?>][items][]" data-placeholder="Cerca i prodotti del catalogo selezionabili in questa riga…" data-action="woocommerce_json_search_products" data-exclude="<?php echo esc_attr( get_the_ID() ); ?>" data-exclude_type="grouped,external">
				<?php
				foreach ( (array) $row['items'] as $pid ) {
					$p = wc_get_product( $pid );
					if ( $p ) {
						echo '<option value="' . esc_attr( $pid ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $p->get_formatted_name() ) ) . '</option>';
					}
				}
				?>
			</select>
			<div class="scb-custom">
				<div class="scb-custom-list">
					<?php
					foreach ( (array) ( $row['custom'] ?? array() ) as $k => $c ) {
						self::custom_html( $i, $k, $c );
					}
					?>
				</div>
				<button type="button" class="button scb-add-custom">+ Aggiungi prodotto non in elenco</button>
			</div>
		</div>
		<?php
	}

	private static function custom_html( $i, $k, $c ) {
		$name  = 'scb_rows[' . $i . '][custom][' . $k . ']';
		$thumb = $c['image_id'] ? wp_get_attachment_image_url( $c['image_id'], 'thumbnail' ) : '';
		$price = ( '' === $c['price'] || null === $c['price'] ) ? '' : wc_format_localized_price( $c['price'] );
		?>
		<div class="scb-custom-item">
			<span class="scb-custom-thumb"><?php echo $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" />' : ''; ?></span>
			<strong class="scb-custom-name"><?php echo esc_html( $c['title'] ); ?></strong>
			<span class="scb-custom-price"><?php echo '' !== $price ? esc_html( $price . ' ' . get_woocommerce_currency_symbol() ) : ''; ?></span>
			<a href="#" class="scb-custom-edit">Modifica</a>
			<a href="#" class="scb-custom-del" title="Rimuovi">&times;</a>
			<input type="hidden" class="f-id" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $c['id'] ); ?>" />
			<input type="hidden" class="f-title" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $c['title'] ); ?>" />
			<input type="hidden" class="f-desc" name="<?php echo esc_attr( $name ); ?>[desc]" value="<?php echo esc_attr( $c['desc'] ); ?>" />
			<input type="hidden" class="f-image" name="<?php echo esc_attr( $name ); ?>[image_id]" value="<?php echo esc_attr( $c['image_id'] ); ?>" data-thumb="<?php echo esc_url( $thumb ); ?>" />
			<input type="hidden" class="f-price" name="<?php echo esc_attr( $name ); ?>[price]" value="<?php echo esc_attr( $price ); ?>" />
		</div>
		<?php
	}

	private static function modal_html() {
		?>
		<div id="scb-modal" class="scb-modal" hidden>
			<div class="scb-modal-box" role="dialog" aria-modal="true" aria-labelledby="scb-modal-h">
				<h3 id="scb-modal-h">Prodotto non in elenco</h3>
				<p>
					<label for="scb-m-title"><strong>Titolo *</strong></label>
					<input type="text" id="scb-m-title" class="widefat" />
				</p>
				<p>
					<label for="scb-m-desc"><strong>Breve descrizione</strong> <em>(facoltativa)</em></label>
					<textarea id="scb-m-desc" class="widefat" rows="3"></textarea>
				</p>
				<p>
					<strong>Foto</strong> <em>(facoltativa)</em><br />
					<span id="scb-m-img" class="scb-m-img"></span>
					<button type="button" class="button" id="scb-m-pick">Scegli foto</button>
					<button type="button" class="button-link" id="scb-m-clear">Rimuovi</button>
				</p>
				<p>
					<label for="scb-m-price"><strong>Prezzo (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>)</strong> <em>(facoltativo)</em></label>
					<input type="text" id="scb-m-price" class="short wc_input_price" />
				</p>
				<p class="scb-modal-actions">
					<button type="button" class="button button-primary" id="scb-m-save">Salva</button>
					<button type="button" class="button" id="scb-m-cancel">Annulla</button>
				</p>
			</div>
		</div>
		<?php
	}

	public static function save( $product ) {
		$enabled = isset( $_POST['_scb_enabled'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification
		$product->update_meta_data( '_scb_enabled', $enabled );

		if ( 'yes' !== $enabled ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$mode = ( isset( $_POST['_scb_mode'] ) && 'fixed' === $_POST['_scb_mode'] ) ? 'fixed' : 'sum';
		$product->update_meta_data( '_scb_mode', $mode );
		$product->update_meta_data( '_scb_box_cost', isset( $_POST['_scb_box_cost'] ) ? wc_format_decimal( wp_unslash( $_POST['_scb_box_cost'] ) ) : '' );
		$product->update_meta_data( '_scb_show_prices', isset( $_POST['_scb_show_prices'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_scb_title', isset( $_POST['_scb_title'] ) ? sanitize_text_field( wp_unslash( $_POST['_scb_title'] ) ) : '' );
		$product->update_meta_data( '_scb_total_min', isset( $_POST['_scb_total_min'] ) ? absint( $_POST['_scb_total_min'] ) : 0 );
		$product->update_meta_data( '_scb_total_max', isset( $_POST['_scb_total_max'] ) ? absint( $_POST['_scb_total_max'] ) : 0 );

		$rows = array();
		if ( isset( $_POST['scb_rows'] ) && is_array( $_POST['scb_rows'] ) ) {
			foreach ( wp_unslash( $_POST['scb_rows'] ) as $r ) {
				if ( ! is_array( $r ) ) {
					continue;
				}
				$items  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $r['items'] ?? array() ) ) ) ) );
				$custom = SCB_Core::normalize_custom( $r['custom'] ?? array() );
				if ( ! $items && ! $custom ) {
					continue;
				}
				$rows[] = array(
					'title'  => sanitize_text_field( $r['title'] ?? '' ),
					'min'    => absint( $r['min'] ?? 0 ),
					'max'    => absint( $r['max'] ?? 0 ),
					'items'  => $items,
					'custom' => $custom,
				);
			}
		}
		// phpcs:enable
		$product->update_meta_data( '_scb_rows', $rows );

		// In modalità "somma" il prezzo di listino diventa il "a partire da" (rende il prodotto acquistabile e ordinabile).
		if ( 'sum' === $mode ) {
			$product->set_sale_price( '' );
			$product->set_regular_price( (string) SCB_Core::min_price( $product ) );
		}
	}
}
