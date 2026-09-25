<?php
/**
 * Plugin Name:       LOHEIDE.EU WP Rights Management
 * Plugin URI:        https://loheide.eu
 * Description:       Seitenbasierte Zugriffsrechte für WordPress: komplette Seiten nach Login und WordPress-Rollen freigeben oder sperren. Gesperrte Rollen haben immer Vorrang. Entwickelt von LOHEIDE.EU.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            LOHEIDE.EU
 * Author URI:        https://loheide.eu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       loheide-rights-management
 * Domain Path:       /languages
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

define( 'LRM_VERSION', '1.0.0' );
define( 'LRM_FILE', __FILE__ );
define( 'LRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'LRM_URL', plugin_dir_url( __FILE__ ) );
define( 'LRM_BASENAME', plugin_basename( __FILE__ ) );
define( 'LRM_NAME', 'LOHEIDE.EU WP Rights Management' );
define( 'LRM_VENDOR', 'LOHEIDE.EU' );
define( 'LRM_VENDOR_URL', 'https://loheide.eu' );

require_once LRM_DIR . 'includes/class-lrm-roles.php';
require_once LRM_DIR . 'includes/class-lrm-rule.php';
require_once LRM_DIR . 'includes/class-lrm-settings.php';
require_once LRM_DIR . 'includes/class-lrm-access.php';
require_once LRM_DIR . 'includes/class-lrm-metabox.php';
require_once LRM_DIR . 'includes/class-lrm-admin.php';
require_once LRM_DIR . 'includes/class-lrm-frontend.php';
require_once LRM_DIR . 'includes/class-lrm-shortcodes.php';
require_once LRM_DIR . 'includes/class-lrm-plugin.php';

/**
 * Zentrale Plugin-Instanz.
 *
 * @return LRM_Plugin
 */
function lrm() {
	return LRM_Plugin::instance();
}

lrm();

register_activation_hook( __FILE__, array( 'LRM_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LRM_Plugin', 'deactivate' ) );
