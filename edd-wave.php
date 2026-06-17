<?php
/**
 * Plugin Name: Wave + Easy Digital Downloads
 * Description: This helpful bookkeeping WP plugin moves a successful EDD purchase into Wave Apps accounting, with a payment entry under Accounting -> Transactions.
 *
 * Version: 2.0
 * Author: Canyon Webworks
 * Text Domain: edd-wave
 * Domain Path: /lang
 *
 * Easy Digital Downloads Wave
 * Copyright: (c) 2022-2026 Canyon Webworks
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
	add_action( 'admin_notices', 'edd_wave_edd_required_notice' );
	edd_debug_log( 'EDD + Wave error: This plugin requires Easy Digital Downloads be installed and activated.' );
	return;
}

if ( ! defined( 'EDD_WAVE_PLUGIN_FILE' ) ) {
	define( 'EDD_WAVE_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'EDD_WAVE_PLUGIN_DIR' ) ) {
	define( 'EDD_WAVE_PLUGIN_DIR', __DIR__ );
}

if ( ! defined( 'EDD_WAVE_VERSION' ) ) {
	define( 'EDD_WAVE_VERSION', '2.0' );
}

function eddwave() {
	// Include the main PDF Ink class
	if ( ! class_exists( \CanyonWebworks\EDDWave\Classes\EDDWave::class, false ) ) {
		include_once __DIR__ . '/Classes/EDDWave.php';
	}
	return \CanyonWebworks\EDDWave\Classes\EDDWave::instance();
}
add_action( 'plugins_loaded', 'eddwave' );

function edd_wave_edd_required_notice() {
	echo '<div class="error"><p>' . esc_html__( 'The EDD Wave plugin requires Easy Digital Downloads be installed and activated.', 'edd-wave' ) . '</p></div>';
}