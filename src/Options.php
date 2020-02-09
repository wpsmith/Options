<?php
/**
 * Options Class
 *
 * The options-specific functionality for WordPress.
 *
 * You may copy, distribute and modify the software as long as you track
 * changes/dates in source files. Any modifications to or software including
 * (via compiler) GPL-licensed code must also be made available under the GPL
 * along with build & install instructions.
 *
 * PHP Version 7.2
 *
 * @category  WPS
 * @package   WPS\Options
 * @author    Travis Smith <t@wpsmith.net>
 * @copyright 2018-2019 Travis Smith
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link      https://github.com/akamai/wp-akamai
 * @since     0.2.0
 */

namespace WPS\WP;

// Exit if accessed directly.
use WPS\Core\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Options' ) ) {
	/**
	 * Class Options
	 */
	class Options extends Singleton {

		/**
		 * Return option from the options table and cache result.
		 *
		 * Applies `wps_pre_get_option_$key` and `genesis_options` filters.
		 *
		 * Values pulled from the database are cached on each request, so a second request for the same value won't cause a
		 * second DB interaction.
		 *
		 * @param string $key       Option name.
		 * @param string $setting   Optional. Settings field name. Eventually defaults to `wps-settings` if not
		 *                          passed as an argument.
		 * @param bool   $use_cache Optional. Whether to use the Genesis cache value or not. Default is true.
		 * @return mixed The value of the `$key` in the database, or the return from
		 *               `wps_pre_get_option_{$key}` short circuit filter if not `null`.
		 */
		public static function get_option( $key, $setting = null, $use_cache = true ) {
			// The default is set here, so it doesn't have to be repeated in the function arguments for wps_option() too.
			$setting = $setting ?: 'wps-settings';

			// Allow child theme to short circuit this function.
			$pre = apply_filters( "wps_pre_get_option_{$key}", null, $setting );
			if ( null !== $pre ) {
				return $pre;
			}

			// Bypass cache if viewing site in Customizer.
			if ( is_customize_preview() ) {
				$use_cache = false;
			}

			// If we need to bypass the cache.
			if ( ! $use_cache ) {
				$options = get_option( $setting );

				if ( ! is_array( $options ) || ! array_key_exists( $key, $options ) ) {
					return '';
				}

				return is_array( $options[ $key ] ) ? $options[ $key ] : wp_kses_decode_entities( $options[ $key ] );
			}

			// Setup caches.
			static $settings_cache = [];
			static $options_cache = [];

			// Check options cache.
			if ( isset( $options_cache[ $setting ][ $key ] ) ) {
				// Option has been cached.
				return $options_cache[ $setting ][ $key ];
			}

			// Check settings cache.
			if ( isset( $settings_cache[ $setting ] ) ) {
				// Setting has been cached.
				$options = apply_filters( 'wps_options', $settings_cache[ $setting ], $setting );
			} else {
				// Set value and cache setting.
				$settings_cache[ $setting ] = apply_filters( 'wps_options', \get_option( $setting ), $setting );
				$options                    = $settings_cache[ $setting ];
			}

			// Check for non-existent option.
			if ( ! is_array( $options ) || ! array_key_exists( $key, (array) $options ) ) {
				// Cache non-existent option.
				$options_cache[ $setting ][ $key ] = '';
			} else {
				// Option has not previously been cached, so cache now.
				$options_cache[ $setting ][ $key ] = is_array( $options[ $key ] ) ? $options[ $key ] : wp_kses_decode_entities( $options[ $key ] );
			}

			return $options_cache[ $setting ][ $key ];
		}

		/**
		 * Echo options from the options database.
		 *
		 * @param string $key       Option name.
		 * @param string $setting   Optional. Settings field name. Eventually defaults to GENESIS_SETTINGS_FIELD.
		 * @param bool   $use_cache Optional. Whether to use the Genesis cache value or not. Default is true.
		 */
		public static function genesis_option( $key, $setting = null, $use_cache = true ) {

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::get_option( $key, $setting, $use_cache );

		}

		/**
		 * Takes an array of new settings, merges them with the old settings, and pushes them into the database.
		 *
		 * @param string|array $new     New settings. Can be a string, or an array.
		 * @param string       $setting Optional. Settings field name. Default is GENESIS_SETTINGS_FIELD.
		 * @return bool `true` if option was updated, `false` otherwise.
		 */
		public static function update_settings( $new = '', $setting = 'wps-settings' ) {

			$old = get_option( $setting );

			$settings = wp_parse_args( $new, $old );

			// Allow settings to be deleted.
			foreach ( $settings as $key => $value ) {
				if ( 'unset' === $value ) {
					unset( $settings[ $key ] );
				}
			}

			return update_option( $setting, $settings );

		}
	}
}

