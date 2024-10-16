<?php
/**
 * AutoUpdatePluginFromGitHub class for WordPress plugins.
 *
 * @package InstaWP\Connect\Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'AutoUpdatePluginFromGitHub' ) ) {
	/**
	 * Class AutoUpdatePluginFromGitHub
	 */
	class AutoUpdatePluginFromGitHub {

		/**
		 * The plugin current version
		 *
		 * @var string
		 */
		private $current_version;

		/**
		 * The plugin remote update path
		 *
		 * @var string
		 */
		private $update_path;

		/**
		 * Plugin Slug (plugin_directory/plugin_file.php)
		 *
		 * @var string
		 */
		private $plugin_slug;

		/**
		 * Plugin directory (plugin_directory)
		 *
		 * @var string
		 */
		private $plugin_directory;

		/**
		 * Plugin name (plugin_file)
		 *
		 * @var string
		 */
		private $slug;

		/**
		 * Initialize a new instance of the WordPress Auto-Update class
		 *
		 * @param string $current_version Current plugin version.
		 * @param string $update_path URL of the repo.
		 * @param string $plugin_slug Plugin slug.
		 */
		public function __construct( $current_version, $update_path, $plugin_slug ) {
			// Set the class public variables.
			$this->current_version = $current_version;
			$this->update_path     = esc_url( $update_path );
			$this->plugin_slug     = $plugin_slug;

			list ( $t1, $t2 )       = explode( '/', $plugin_slug );
			$this->slug             = str_replace( '.php', '', $t2 );
			$this->plugin_directory = $t1;

			// Define the alternative API for updating checking.
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
			add_filter( 'auto_update_plugin', array( $this, 'auto_update_specific_plugin' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'force_update_check' ) );
			add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
		}

		public function is_plugin_active() {
			if ( ! function_exists( 'is_plugin_active' ) && ! file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			return function_exists( 'is_plugin_active' ) ? is_plugin_active( $this->plugin_slug ) : false;
		}

		public function after_install( $response, $hook_extra, $result ) {
			error_log( 'AutoUpdatePluginFromGitHub: result ' . json_encode( $result ) );
			if ( empty( $result['destination_name'] ) || empty( $result['destination'] ) || ! defined( 'WP_PLUGIN_DIR' ) || ! file_exists( ABSPATH . '/wp-admin/includes/file.php' ) || ! $this->is_plugin_active() ) {
				error_log( 'AutoUpdatePluginFromGitHub: Plugin not correctly installed' );
				return $result;
			}

			global $wp_filesystem;

			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();

			if ( empty( $wp_filesystem ) ) {
				error_log( 'AutoUpdatePluginFromGitHub: file system not available' );
				return $result;
			}

			$plugin_folder = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_directory;
			error_log( 'AutoUpdatePluginFromGitHub: plugin_folder ' . $plugin_folder );
			// Check if the extracted folder ends with -main, -master, or a version number
			if ( $result['destination_name'] === $this->plugin_directory . '-main' || $result['destination_name'] === $this->plugin_directory . '-master' ) {
				error_log( 'AutoUpdatePluginFromGitHub: trying to move' );
				// If the target folder already exists, remove it
				if ( $wp_filesystem->exists( $plugin_folder ) ) {
					$wp_filesystem->delete( $plugin_folder, true, 'd' );
				}

				// Rename the extracted folder to the correct plugin folder name
				$wp_filesystem->move( $result['destination'], $plugin_folder );
				$result['destination_name']   = $this->plugin_directory;
				$result['destination']        = $plugin_folder;
				$result['remote_destination'] = $plugin_folder;
				// Ensure the plugin is active if it was active before the update
				if ( function_exists( 'activate_plugin' ) ) {
					$activate_result = activate_plugin( $this->plugin_slug );
					if ( is_wp_error( $activate_result ) ) {
						error_log( 'Error activating plugin: ' . $activate_result->get_error_message() );
					}
				}
			} else {
				error_log( 'AutoUpdatePluginFromGitHub: destination not correct' );
			}

			return $result;
		}


		public function force_update_check() {
			if ( ! empty( $_GET['iwp_check_plugin_update'] ) ) {
				wp_clean_plugins_cache();
				wp_update_plugins();
			}
		}

		/**
		 * Add our self-hosted autoupdate plugin to the filter transient
		 *
		 * @param object $transient The WordPress update transient.
		 * @return object $transient Modified update transient.
		 */
		public function check_update( $transient ) {
			if ( empty( $transient->checked ) || ! $this->is_plugin_active() ) {
				return $transient;
			}

			$remote_version = $this->get_remote_version();

			if ( $remote_version && version_compare( $this->current_version, $remote_version, '<' ) ) {
				$update                                    = $this->get_update_data( $remote_version );
				$transient->response[ $this->plugin_slug ] = (object) $update;
			} else {
				$item                                       = $this->get_mock_update_data();
				$transient->no_update[ $this->plugin_slug ] = (object) $item;
			}

			return $transient;
		}

		/**
		 * Get update data for the plugin.
		 *
		 * @param string $new_version New version of the plugin.
		 * @return array Update data.
		 */
		private function get_update_data( $new_version ) {
			return array(
				'id'            => $this->plugin_slug,
				'slug'          => $this->slug,
				'plugin'        => $this->plugin_slug,
				'new_version'   => $new_version,
				'url'           => $this->update_path,
				'package'       => esc_url( $this->update_path . '/archive/refs/heads/main.zip' ),
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new stdClass(),
			);
		}

		/**
		 * Get mock update data for the plugin when no update is available.
		 *
		 * @return array Mock update data.
		 */
		private function get_mock_update_data() {
			return array(
				'id'            => $this->plugin_slug,
				'slug'          => $this->slug,
				'plugin'        => $this->plugin_slug,
				'new_version'   => $this->current_version,
				'url'           => '',
				'package'       => '',
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new stdClass(),
			);
		}

		/**
		 * Get remote version from GitHub.
		 *
		 * @return string $remote_version Remote plugin version.
		 */
		public function get_remote_version() {
			$request = wp_remote_get( $this->update_path . '/raw/main/' . esc_attr( $this->slug ) . '.php' );
			if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
				$request = wp_remote_retrieve_body( $request );

				// Check if this file contains the Version header
				if ( preg_match( '/Version:\s*(\S+)/i', $request, $matches ) ) {
					return $matches[1]; // Return the version number
				}
			}

			return false;
		}

		/**
		 * Auto update specific plugin.
		 *
		 * @param boolean $update   Whether to auto update.
		 * @param object  $item     Plugin update data.
		 *
		 * @return boolean $update Whether to auto update.
		 */
		public function auto_update_specific_plugin( $update, $item ) {
			if ( $this->slug === $item->slug ) {
				return true;
			} else {
				return $update;
			}
		}
	}
}
