<?php
/**
 * Plugin Name: Wave + Easy Digital Downloads
 * Description: This helpful bookkeeping WP plugin moves a successful EDD purchase into Wave Apps accounting, with an entry under Accounting -> Transactions.
 *
 * Version: 2.0
 * Author: Canyon Webworks
 * Text Domain: edd-wave
 * Domain Path: /lang
 *
 * Easy Digital Downloads Wave
 * Copyright: (c) 2022-2026 Canyon Webworks
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
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