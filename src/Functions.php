<?php
/**
 * Helper functions for EDD Recount Tools registration
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2024, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 */

declare( strict_types=1 );

use ArrayPress\EDD\Register\RecountTools;

if ( ! function_exists( 'edd_register_recount_tools' ) ) :
	/**
	 * Register custom recount tools for EDD.
	 *
	 * @param array         $tools          An associative array of custom recount tools.
	 * @param callable|null $error_callback Optional error handling callback.
	 *
	 * @return RecountTools|WP_Error
	 */
	function edd_register_recount_tools( array $tools, ?callable $error_callback = null ) {
		$manager = new RecountTools();
		$result  = $manager->register( $tools );

		if ( is_wp_error( $result ) ) {
			if ( is_callable( $error_callback ) ) {
				call_user_func( $error_callback, $result );
			}

			return $result;
		}

		$manager->init();

		return $manager;
	}
endif;