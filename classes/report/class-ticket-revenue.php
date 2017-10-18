<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/**
 * Class Ticket_Revenue
 *
 * @package WordCamp\Reports\Report
 */
class Ticket_Revenue extends Base {
	/**
	 * Report name.
	 */
	const NAME = 'Ticket Revenue';

	/**
	 * Report slug.
	 */
	const SLUG = 'ticket-revenue';

	/**
	 * Report description.
	 */
	const DESCRIPTION = 'A summary of WordCamp ticket revenue during a given time period.';

	/**
	 * The start of the date range for the report.
	 *
	 * @var \DateTime|null
	 */
	protected $start_date = null;

	/**
	 * The end of the date range for the report.
	 *
	 * @var \DateTime|null
	 */
	protected $end_date = null;

	/**
	 * A container object to hold error messages.
	 *
	 * @var \WP_Error
	 */
	public $error = null;

	/**
	 * Ticket_Revenue constructor.
	 *
	 * @param \DateTime $start_date The start of the date range for the report.
	 * @param \DateTime $end_date   The end of the date range for the report.
	 */
	public function __construct( $start_date, $end_date ) {
		$this->error = new \WP_Error();

		$this->start_date = new \DateTime( $start_date );
		$this->end_date = new \DateTime( $end_date );

		// If the end date doesn't have a specific time, make sure
		// the entire day is included.
		if ( '00:00:00' === $this->end_date->format( 'H:i:s' ) ) {
			$this->end_date->setTime( 23, 59, 59 );
		}
	}


	public function get_data() {

	}

}
