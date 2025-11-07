<?php
/**
 * Plugin Name:       My Custom GPTs
 * Plugin URI:        https://coachproai.com
 * Description:       Manages and displays Custom GPTs with prompt builders using a shortcode.
 * Version:           1.0.0
 * Author:            Coach Pro AI
 * Author URI:        https://coachproai.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cpg
 * Domain Path:       /languages
 *
 * This file contains the main plugin logic, database setup, admin menus,
 * AJAX handlers, and shortcode registration.
 */

// Exit if accessed directly (Direct access protection)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Part 1 — Activation Hook & Database Setup
 * * This part handles the plugin activation, creating the necessary
 * database table to store the GPTs and setting the plugin version.
 */

// Define constants
define( 'CPG_VERSION', '1.0.0' );
define( 'CPG_PLUGIN_FILE', __FILE__ );
define( 'CPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPG_TEXT_DOMAIN', 'cpg' );

/**
 * Get centralized table names.
 * * @return array List of table names with WordPress prefix.
 */
function cpg_get_table_names() {
    global $wpdb;
    // We centralize table names here for easy maintenance.
    return [
        'gpts' => $wpdb->prefix . 'cpg_gpts',
    ];
}

/**
 * Runs on plugin activation.
 * Creates the database table using dbDelta.
 */
function cpg_activate_plugin() {
    // Check if the user has permission
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    global $wpdb;
    $tables = cpg_get_table_names();
    $gpts_table_name = $tables['gpts'];
    $charset_collate = $wpdb->get_charset_collate();

    // SQL to create the table
    // We store prompt_fields as JSON to allow flexibility.
    $sql = "CREATE TABLE $gpts_table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        gpt_url VARCHAR(2083) NOT NULL,
        prompt_template TEXT DEFAULT NULL,
        prompt_fields JSON DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // We need dbDelta to create/update the table
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Store the version number
    add_option( 'cpg_version', CPG_VERSION );
}
register_activation_hook( CPG_PLUGIN_FILE, 'cpg_activate_plugin' );

/**
 * Load text domain for translations.
 */
function cpg_load_textdomain() {
    load_plugin_textdomain( 
        CPG_TEXT_DOMAIN, 
        false, 
        dirname( plugin_basename( CPG_PLUGIN_FILE ) ) . '/languages' 
    );
}
add_action( 'plugins_loaded', 'cpg_load_textdomain' );
