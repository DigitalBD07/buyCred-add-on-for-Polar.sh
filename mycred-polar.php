<?php


/**
 * Plugin Name: myCred Polar Gateway
 * Plugin URI: https://devbd.net
 * Description: Adds a payment gateway integration between <a href="https://wordpress.org/plugins/mycred/" target="_blank">myCred</a> and <a href="https://polar.sh" target="_blank">Polar</a>. Users can purchase or subscribe for points using Polar’s checkout system, supporting both one-time and recurring payments.
 * Version: 1.0.9
 * Author: Tanvir Haider
 * Author URI: https://devbd.net
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mycred-polar
 * Requires Plugins: mycred
 */



if (!defined('ABSPATH')) exit;

// Define plugin constants
define('MYCRED_POLAR_VERSION', '3.5.5');
define('MYCRED_POLAR_FILE', __FILE__);
define('MYCRED_POLAR_PATH', plugin_dir_path(__FILE__));
define('MYCRED_POLAR_URL', plugin_dir_url(__FILE__));

// Load helper functions
require_once MYCRED_POLAR_PATH . 'includes/helpers.php';

// Load core classes
require_once MYCRED_POLAR_PATH . 'includes/class-polar-database.php';
require_once MYCRED_POLAR_PATH . 'includes/class-polar-api.php';
require_once MYCRED_POLAR_PATH . 'includes/class-polar-awards.php';
require_once MYCRED_POLAR_PATH . 'includes/class-polar-webhook.php';
require_once MYCRED_POLAR_PATH . 'includes/class-polar-plugin.php';

// Load admin classes
if (is_admin()) {
    require_once MYCRED_POLAR_PATH . 'admin/class-polar-admin.php';
    require_once MYCRED_POLAR_PATH . 'admin/class-polar-settings.php';
    require_once MYCRED_POLAR_PATH . 'admin/class-polar-logs.php';
    require_once MYCRED_POLAR_PATH . 'admin/class-polar-subscribe.php';
    require_once MYCRED_POLAR_PATH . 'admin/ajax-handlers.php';
}

// Load public classes
require_once MYCRED_POLAR_PATH . 'public/class-polar-shortcode.php';
require_once MYCRED_POLAR_PATH . 'public/class-polar-success.php';

// Initialize the plugin
MyCred_Polar_Plugin::get_instance();