<?php
/**
 * Generic Callback Batch Processor for EDD Recount Tools
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2024, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\EDD\Register\RecountTools;

if ( ! class_exists( 'EDD_Batch_Export' ) ) {
	return;
}

class EDD_Batch_Callback_Processor extends EDD_Batch_Export {

	/**
	 * Our export type. Used for export-type specific filters/actions.
	 *
	 * @var string
	 */
	public $export_type = 'callback_processor';

	/**
	 * The tool key being processed.
	 *
	 * @var string
	 */
	private string $tool_key;

	/**
	 * The callback function to process items.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * The callback function to count total items.
	 *
	 * @var callable
	 */
	private $count_callback;

	/**
	 * Number of items to process per batch.
	 *
	 * @var int
	 */
	private int $batch_size;

	/**
	 * Set the properties specific to the recount
	 *
	 * @param int $step The current step being processed.
	 */
	public function __construct( $step = 1 ) {
		parent::__construct( $step );

		$this->is_recount = true;

		// Get tool key from request
		$this->tool_key = $_REQUEST['tool_key'] ?? '';

		if ( empty( $this->tool_key ) ) {
			return;
		}

		// Get tool configuration
		$tool = RecountTools::get_callback_tool( $this->tool_key );

		if ( ! $tool ) {
			return;
		}

		$this->export_type    = $this->tool_key;
		$this->callback       = $tool['callback'];
		$this->count_callback = $tool['count_callback'];
		$this->batch_size     = $tool['batch_size'];
	}

	/**
	 * Get the items to process for the current step
	 *
	 * @return array|false Array of items to process or false if none.
	 */
	public function get_data() {
		if ( ! $this->callback ) {
			return false;
		}

		$offset = ( $this->step - 1 ) * $this->batch_size;

		return call_user_func( $this->callback, $offset, $this->batch_size );
	}

	/**
	 * Process the current step
	 *
	 * @return bool True to continue processing, false when complete.
	 */
	public function process_step() {
		$items = $this->get_data();

		if ( empty( $items ) ) {
			return false; // No more items to process
		}

		// The callback handles the processing and returns the data
		// We just need to return true to continue
		return true;
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @return float The completion percentage.
	 */
	public function get_percentage_complete() {
		if ( ! $this->count_callback ) {
			return 0;
		}

		$total     = call_user_func( $this->count_callback );
		$processed = ( $this->step - 1 ) * $this->batch_size;

		if ( $total <= 0 ) {
			return 100;
		}

		return min( 100, ( $processed / $total ) * 100 );
	}

	/**
	 * Set the properties specific to the recount being run
	 *
	 * @param array $request The request array.
	 */
	public function set_properties( $request ) {
		$this->start    = isset( $request['start'] ) ? sanitize_text_field( $request['start'] ) : '';
		$this->end      = isset( $request['end'] ) ? sanitize_text_field( $request['end'] ) : '';
		$this->tool_key = isset( $request['tool_key'] ) ? sanitize_text_field( $request['tool_key'] ) : '';
	}

}