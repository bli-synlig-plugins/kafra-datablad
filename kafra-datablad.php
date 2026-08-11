<?php
/**
 * Plugin Name: Kafra Datablad
 * Description: Last opp PDF-datablad, koble dem til WooCommerce-produkter, vis på produktsider og på /dokumenter/.
 * Version: 1.2.1
 * Author: Benjamin
 * Text Domain: kafra-datablad
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

define('KDB_VERSION', '1.2.1');
define('KDB_PLUGIN_FILE', __FILE__);
define('KDB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KDB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KDB_POST_TYPE', 'kafra_datablad');
define('KDB_META_PDF', '_kdb_pdf_id');
define('KDB_META_PRODUCTS', '_kdb_product_ids');

require_once KDB_PLUGIN_DIR . 'includes/class-kdb-plugin.php';
require_once KDB_PLUGIN_DIR . 'includes/class-kdb-cpt.php';
require_once KDB_PLUGIN_DIR . 'includes/class-kdb-repository.php';
require_once KDB_PLUGIN_DIR . 'includes/class-kdb-admin.php';
require_once KDB_PLUGIN_DIR . 'includes/class-kdb-frontend.php';

register_activation_hook(__FILE__, array('KDB_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('KDB_Plugin', 'deactivate'));

add_action('plugins_loaded', array('KDB_Plugin', 'init'));
