<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/**
 * Class Ticket_Sales
 *
 * @package WordCamp\Reports\Report
 */
class Ticket_Sales extends Base {
	/**
	 * Report name.
	 */
	const NAME = 'Ticket Sales';

	/**
	 * Report slug.
	 */
	const SLUG = 'ticket-sales';

	/**
	 * Report description.
	 */
	const DESCRIPTION = 'A summary of WordCamp ticket sales during a given time period.';

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
	 * Ticket_Sales constructor.
	 *
	 * @param \DateTime $start_date The start of the date range for the report.
	 * @param \DateTime $end_date   The end of the date range for the report.
	 */
	public function __construct( $start_date, $end_date ) {
		$this->start_date = new \DateTime( $start_date );
		$this->end_date = new \DateTime( $end_date );

		// If the end date doesn't have a specific time, make sure
		// the entire day is included.
		if ( '00:00:00' === $this->end_date->format( 'H:i:s' ) ) {
			$this->end_date->setTime( 23, 59, 59 );
		}
	}


	public function get_data() {}


	public function render_html() {}


	public static function render_admin_page() {
		$action     = filter_input( INPUT_POST, 'action' );
		$start_date = filter_input( INPUT_POST, 'start-date' );
		$end_date   = filter_input( INPUT_POST, 'end-date' );
		$nonce      = filter_input( INPUT_POST, self::SLUG . '-nonce' );
		$report     = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			$report = new self( $start_date, $end_date );
		}

		include Reports\get_views_dir_path() . 'report/ticket-sales.php';
	}
}
