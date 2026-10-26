<?php
/**
 * Plugin Name:       Seed Combo Box
 * Description:       Prodotti combinati per WooCommerce: il cliente compone la box scegliendo i prodotti riga per riga (accordion). Prezzo = costo scatola + prodotti scelti, oppure prezzo fisso.
 * Version:           1.0.4
 * Author:            Seed Media Agency
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       seed-combo-box
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'SCB_VERSION', '1.0.4' );
define( 'SCB_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCB_URL', plugin_dir_url( __FILE__ ) );

// Compatibilità HPOS (High-Performance Order Storage).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Aggiornamenti automatici dal repository GitHub pubblico: non serve nulla nel wp-config.
 * WordPress propone l'aggiornamento quando su GitHub c'è una release con versione più alta.
 * Per usare un altro repository: define( 'SCB_UPDATE_REPO', 'https://github.com/utente/repo/' );
 */
if ( ! defined( 'SCB_UPDATE_REPO' ) ) {
	define( 'SCB_UPDATE_REPO', 'https://github.com/gvajro/seed-combo-box/' );
}

if ( SCB_UPDATE_REPO ) {
	require_once SCB_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';
	$scb_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( SCB_UPDATE_REPO, __FILE__, 'seed-combo-box' );
	$scb_updater->setBranch( 'main' );
	if ( defined( 'SCB_GITHUB_TOKEN' ) && SCB_GITHUB_TOKEN ) {
		$scb_updater->setAuthentication( SCB_GITHUB_TOKEN );
	}
}

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Seed Combo Box</strong> richiede WooCommerce attivo.</p></div>';
		} );
		return;
	}

	require_once SCB_PATH . 'includes/class-scb-core.php';
	require_once SCB_PATH . 'includes/class-scb-admin.php';
	require_once SCB_PATH . 'includes/class-scb-frontend.php';
	require_once SCB_PATH . 'includes/class-scb-cart.php';

	SCB_Admin::init();
	SCB_Frontend::init();
	SCB_Cart::init();
} );
