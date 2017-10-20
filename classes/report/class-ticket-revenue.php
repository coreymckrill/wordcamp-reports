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
class Ticket_Revenue extends Date_Range {
	/**
	 * Report name.
	 */
	public static $name = 'Ticket Revenue';

	/**
	 * Report slug.
	 */
	public static $slug = 'ticket-revenue';

	/**
	 * Report description.
	 */
	public static $description = 'A summary of WordCamp ticket revenue during a given time period.';


	public function get_data() {}

}
