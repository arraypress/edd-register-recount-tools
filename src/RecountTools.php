<?php
/**
 * Recount Tools Registration for Easy Digital Downloads
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2024, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register;

use WP_Error;

class RecountTools {

	/**
	 * An associative array of custom recount tools with their configurations.
	 *
	 * @var array
	 */
	private array $tools = [];

	/**
	 * Static storage for callback tools (for class access).
	 *
	 * @var array
	 */
	private static array $callback_tools = [];

	/**
	 * Register recount tools.
	 *
	 * @param array $tools An associative array of custom recount tools.
	 *
	 * @return WP_Error|true
	 */
	public function register( array $tools ) {
		if ( empty( $tools ) ) {
			return new WP_Error(
				'empty_tools',
				__( 'Tools array cannot be empty.', 'arraypress' )
			);
		}

		$validated_tools = [];

		foreach ( $tools as $key => $tool ) {
			if ( empty( $key ) ) {
				return new WP_Error(
					'invalid_tool_key',
					__( 'Tool key cannot be empty.', 'arraypress' )
				);
			}

			// Check for callback-based tool (new simplified approach)
			if ( isset( $tool['callback'] ) && isset( $tool['count_callback'] ) ) {
				if ( ! is_callable( $tool['callback'] ) ) {
					return new WP_Error(
						'invalid_callback',
						sprintf( __( 'Tool "%s" callback must be callable.', 'arraypress' ), $key )
					);
				}

				if ( ! is_callable( $tool['count_callback'] ) ) {
					return new WP_Error(
						'invalid_count_callback',
						sprintf( __( 'Tool "%s" count_callback must be callable.', 'arraypress' ), $key )
					);
				}

				$validated_tool = [
					'type'           => 'callback',
					'callback'       => $tool['callback'],
					'count_callback' => $tool['count_callback'],
					'label'          => $tool['label'] ?? ucwords( str_replace( '-', ' ', $key ) ),
					'batch_size'     => $tool['batch_size'] ?? 20,
				];

				// Store in static array for class access
				self::$callback_tools[ $key ] = $validated_tool;
			}
			// Check for class-based tool (original approach)
			else if ( isset( $tool['class'] ) && isset( $tool['file'] ) ) {
				if ( empty( $tool['class'] ) ) {
					return new WP_Error(
						'missing_tool_class',
						sprintf( __( 'Tool "%s" must have a class.', 'arraypress' ), $key )
					);
				}

				if ( empty( $tool['file'] ) ) {
					return new WP_Error(
						'missing_tool_file',
						sprintf( __( 'Tool "%s" must have a file path.', 'arraypress' ), $key )
					);
				}

				$validated_tool = [
					'type'  => 'class',
					'class' => $tool['class'],
					'file'  => $tool['file'],
					'label' => $tool['label'] ?? ucwords( str_replace( '-', ' ', $key ) ),
				];
			} else {
				return new WP_Error(
					'invalid_tool_config',
					sprintf( __( 'Tool "%s" must have either (callback + count_callback) or (class + file).', 'arraypress' ), $key )
				);
			}

			if ( isset( $tool['description'] ) ) {
				$validated_tool['description'] = $tool['description'];
			}

			$validated_tools[ $key ] = $validated_tool;
		}

		$this->tools = array_merge( $this->tools, $validated_tools );

		edd_debug_log( sprintf( '[EDD Recount Tools] Registered %d recount tools', count( $validated_tools ) ) );

		return true;
	}

	/**
	 * Initialize the recount tools registration.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( empty( $this->tools ) ) {
			return;
		}

		add_action( 'edd_recount_tool_options', [ $this, 'add_recount_tool_options' ] );
		add_action( 'edd_recount_tool_descriptions', [ $this, 'add_recount_tool_descriptions' ] );
		add_action( 'edd_batch_export_class_include', [ $this, 'include_batch_processor' ] );

		// Include the generic callback processor class
		$this->include_callback_processor_class();

		edd_debug_log( sprintf( '[EDD Recount Tools] Initialized %d recount tools', count( $this->tools ) ) );
	}

	/**
	 * Add custom recount tool options.
	 *
	 * @return void
	 */
	public function add_recount_tool_options(): void {
		foreach ( $this->tools as $key => $tool ) {
			$class_name = $tool['type'] === 'callback' ? 'EDD_Batch_Callback_Processor' : $tool['class'];

			printf(
				'<option data-type="%s" value="%s" data-tool-key="%s">%s</option>',
				esc_attr( $key ),
				esc_attr( $class_name ),
				esc_attr( $key ),
				esc_html( $tool['label'] )
			);
		}
	}

	/**
	 * Add custom recount tool descriptions.
	 *
	 * @return void
	 */
	public function add_recount_tool_descriptions(): void {
		foreach ( $this->tools as $key => $tool ) {
			if ( isset( $tool['description'] ) ) {
				printf(
					'<span id="%s">%s</span>',
					esc_attr( $key ),
					wp_kses_post( $tool['description'] )
				);
			}
		}
	}

	/**
	 * Include custom batch processing classes.
	 *
	 * @param string $class The class being requested to run for the batch export.
	 *
	 * @return void
	 */
	public function include_batch_processor( string $class ): void {
		// Handle callback-based tools
		if ( $class === 'EDD_Batch_Callback_Processor' ) {
			// Already included in init()
			return;
		}

		// Handle class-based tools
		foreach ( $this->tools as $tool ) {
			if ( $tool['type'] === 'class' && $class === $tool['class'] && ! empty( $tool['file'] ) ) {
				if ( file_exists( $tool['file'] ) ) {
					require_once $tool['file'];
				} else {
					edd_debug_log( sprintf( '[EDD Recount Tools] File not found: %s', $tool['file'] ), true );
				}
				break;
			}
		}
	}

	/**
	 * Include the generic callback processor class.
	 *
	 * @return void
	 */
	private function include_callback_processor_class(): void {
		if ( class_exists( 'EDD_Batch_Callback_Processor' ) ) {
			return;
		}

		// Define the generic callback processor class
		if ( ! class_exists( 'EDD_Batch_Export' ) ) {
			return;
		}

		require_once __DIR__ . '/CallbackProcessor.php';
	}

	/**
	 * Get callback tool configuration.
	 *
	 * @param string $key The tool key.
	 *
	 * @return array|null The tool configuration or null if not found.
	 */
	public static function get_callback_tool( string $key ): ?array {
		return self::$callback_tools[ $key ] ?? null;
	}
}