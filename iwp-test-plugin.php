<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       IWP TEST PLUGIN
 * Description:       a test plugin
 * Version:           0.0.5
 * Author:            InstaWP Team
 * Author URI:        https://instawp.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/copyleft/gpl.html
 * Text Domain:       iwp-test-plugin
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
require_once plugin_dir_path( __FILE__ ) . 'AutoUpdatePluginFromGitHub.php';
// Initialize the updater
$updater = new AutoUpdatePluginFromGitHub(
	'0.0.1', // Current version of your plugin
	'https://github.com/randhirinsta/iwp-test-plugin', // URL to your GitHub repo
	plugin_basename( __FILE__ ) // Plugin slug
);