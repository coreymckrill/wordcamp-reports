<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();


class WordCamp_Status extends Base {

	public $start_date = null;


	public $end_date = null;


	public function __construct( $start_date, $end_date ) {
		$this->start_date = new \DateTime( $start_date );
		$this->end_date = new \DateTime( $end_date );

		// If the end date doesn't have a specific time, make sure
		// the entire day is included.
		if ( '00:00:00' === $this->end_date->format( 'H:i:s' ) ) {
			$this->end_date->setTime( 23, 59, 59 );
		}
	}


	public function get_data() {
		$wordcamps = $this->get_updated_wordcamp_posts( $this->start_date );

		return $wordcamps;
	}



}
