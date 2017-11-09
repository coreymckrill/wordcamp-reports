<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/**
 * Class Meetup_Groups
 *
 * @package WordCamp\Reports\Report
 */
class Meetup_Groups extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Meetup Groups';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'meetup-groups';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'An analysis of Meetup groups in the Chapter program and their members during a given time period.';

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'meetup';

	/**
	 * Query, parse, and compile the data for the report.
	 *
	 * @return array
	 */
	public function get_data() {
		// Bail if there are errors.
		if ( ! empty( $this->error->get_error_messages() ) ) {
			return array();
		}

		// Maybe use cached data.
		$data = $this->maybe_get_cached_data();
		if ( is_array( $data ) ) {
			return $data;
		}

		$meetup = new Reports\Meetup_Client();

		$all_groups = $meetup->get_groups( array(
			'pro_join_date_max' => $this->end_date->getTimestamp() * 1000, // Meetup API uses milliseconds :/
		) );

		$joined_groups = array_filter( $all_groups, function( $group ) {
			$join_date = new \DateTime();
			$join_date->setTimestamp( intval( $group['pro_join_date'] / 1000 ) ); // Meetup API uses milliseconds :/

			if ( $join_date >= $this->start_date && $join_date <= $this->end_date ) {
				return true;
			}

			return false;
		} );

		$new_group_count = array_reduce( $joined_groups, function( $carry, $item ) use ( $meetup ) {
			$route = '2/events';
			$args  = array(
				'group_id' => $item['id'],
				'status'   => 'past',
				'time'     => '0,' . $item['pro_join_date'],
			);

			$previous_event_count = $meetup->get_total_count( $route, $args );

			if ( $previous_event_count > 0 ) {
				$carry ++;
			}

			return $carry;
		}, 0 );

		$total_member_count = array_reduce( $all_groups, function( $carry, $item ) {
			$carry += absint( $item['member_count'] );

			return $carry;
		}, 0 );

		$data = array(
			'total_groups'  => count( $all_groups ),
			'joined_groups' => count( $joined_groups ),
			'new_groups'    => $new_group_count,
			'total_members' => $total_member_count,
		);

		// Maybe cache the data.
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		/*
		$start_date  = filter_input( INPUT_POST, 'start-date' );
		$end_date    = filter_input( INPUT_POST, 'end-date' );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			$options = array(
				'cache_data'     => false, // WP Admin is low traffic and more trusted, so turn off caching.
			);

			$report = new self( $start_date, $end_date, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}
		*/

		$report = new self( '2017-01-01', '2017-12-31', array( 'cache_data' => false ) );
		var_dump( $report->get_data() );
		var_dump( $report->error->get_error_messages() );

		//include Reports\get_views_dir_path() . 'report/meetup-groups.php';
	}
}
