<?php
/**
 * Plugin Name: Jonaki Support Hub
 * Description: Support hub foundation plugin with ticketing and knowledge base database tables.
 * Version: 1.0.0
 * Author: Saiful Islam
 * Author URI: https://jonaki.com.bd
 * Text Domain: jonaki-support-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JSH_PLUGIN_VERSION', '1.0.0' );
define( 'JSH_PLUGIN_FILE', __FILE__ );
define( 'JSH_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once JSH_PLUGIN_PATH . 'includes/class-jsh-activator.php';
require_once JSH_PLUGIN_PATH . 'includes/class-jsh-rest-api.php';
require_once JSH_PLUGIN_PATH . 'includes/class-jsh-admin.php';

/**
 * Runs plugin activation tasks.
 *
 * @return void
 */
function jsh_activate_plugin() {
	JSH_Activator::activate();
}
register_activation_hook( __FILE__, 'jsh_activate_plugin' );

/**
 * Register REST API routes.
 *
 * @return void
 */
function jsh_register_rest_routes() {
	JSH_REST_API::register_routes();
}
add_action( 'rest_api_init', 'jsh_register_rest_routes' );

JSH_Admin::init();
