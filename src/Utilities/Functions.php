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
	 * @param array $tools An associative array of custom recount tools.
	 *
	 * @return RecountTools|WP_Error
	 */
	function edd_register_recount_tools( array $tools ) {
		static $manager = null;
		if ( null === $manager ) {
			$manager = new RecountTools();
			$manager->init();
		}

		$result = $manager->register( $tools );

		if ( is_wp_error( $result ) ) {
			edd_debug_log( sprintf( '[EDD Recount Tools] Registration error: %s', $result->get_error_message() ) );

			return $result;
		}

		return $manager;
	}
endif;