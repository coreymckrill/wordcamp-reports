<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/**
 * Class WordCamp_Status
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Status extends Base {
	/**
	 * Report name.
	 */
	const NAME = 'WordCamp Status';

	/**
	 * Report slug.
	 */
	const SLUG = 'wordcamp-status';

	/**
	 * Report description.
	 */
	const DESCRIPTION = 'A summary of WordCamp status changes during a given time period.';

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
	 * The status to filter for in the report.
	 *
	 * @var string
	 */
	protected $status = '';

	/**
	 * WordCamp_Status constructor.
	 *
	 * @param \DateTime $start_date The start of the date range for the report.
	 * @param \DateTime $end_date   The end of the date range for the report.
	 * @param string    $status     Optional. The status to filter for in the report.
	 */
	public function __construct( $start_date, $end_date, $status = '' ) {
		$this->start_date = new \DateTime( $start_date );
		$this->end_date   = new \DateTime( $end_date );

		// If the end date doesn't have a specific time, make sure
		// the entire day is included.
		if ( '00:00:00' === $this->end_date->format( 'H:i:s' ) ) {
			$this->end_date->setTime( 23, 59, 59 );
		}

		if ( array_key_exists( $status, self::get_all_statuses() ) ) {
			$this->status = $status;
		}
	}

	/**
	 * Filter: Set the locale to en_US.
	 *
	 * Some translated strings in the wcpt plugin are used here for comparison and matching. To ensure
	 * that the matching happens correctly, we need need to prevent these strings from being converted
	 * to a different locale.
	 *
	 * @return string
	 */
	public function set_locale_to_en_US() {
		return 'en_US';
	}

	/**
	 * Query, parse, and compile the data for the report.
	 *
	 * @return array
	 */
	public function get_data() {
		\add_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		$wordcamp_posts = $this->get_wordcamp_posts();
		$statuses       = self::get_all_statuses();
		$data           = array();

		foreach ( $wordcamp_posts as $wordcamp ) {
			$logs = $this->get_wordcamp_status_logs( $wordcamp );

			// Trim log entries occurring after the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $entry['timestamp'] > $this->end_date->getTimestamp() ) {
					return false;
				}

				return true;
			} );

			// Skip if there is no log activity before the end of the date range.
			if ( empty( $logs ) ) {
				continue;
			}

			$latest_log    = end( $logs );
			$latest_status = $this->get_log_status_result( $latest_log );
			reset( $logs );

			// Trim log entries occurring before the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $entry['timestamp'] < $this->start_date->getTimestamp() ) {
					return false;
				}

				return true;
			} );

			// Skip if there is no log activity in the date range and the camp has an inactive status.
			if ( empty( $logs ) && ( in_array( $latest_status, self::get_inactive_statuses(), true ) || ! $latest_status ) ) {
				continue;
			}

			// Skip if there is no log entry with a resulting status that matches the status filter.
			if ( $this->status && $latest_status !== $this->status ) {
				$filtered = array_filter( $logs, function( $entry ) use ( $statuses ) {
					return preg_match( '/' . preg_quote( $statuses[ $this->status ], '/' ) . '$/', $entry['message'] );
				} );

				if ( empty( $filtered ) ) {
					continue;
				}
			}

			if ( $site_id = \get_wordcamp_site_id( $wordcamp ) ) {
				$name = \get_wordcamp_name( $site_id );
			} else {
				$name = get_the_title( $wordcamp );
			}

			$data[ $wordcamp->ID ] = array(
				'name'          => $name,
				'logs'          => $logs,
				'latest_log'    => $latest_log,
				'latest_status' => $latest_status,
			);
		}

		\remove_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		return $data;
	}

	/**
	 * Get all current WordCamp posts.
	 *
	 * @return array
	 */
	protected function get_wordcamp_posts() {
		$post_args = array(
			'post_type'           => \WCPT_POST_TYPE_ID,
			'post_status'         => 'any',
			'posts_per_page'      => 9999,
			'nopaging'            => true,
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'ASC',
			// Don't include WordCamps that happened more than 3 months ago.
			'meta_query'          => array(
				'relation' => 'OR',
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => '=',
					'value'   => '',
				),
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => '>=',
					'value'   => strtotime( '-3 months', $this->start_date->getTimestamp() ),
					'type'    => 'NUMERIC',
				),
			),
		);

		return \get_posts( $post_args );
	}

	/**
	 * Retrieve the log of status changes for a particular WordCamp.
	 *
	 * @param \WP_Post $wordcamp A WordCamp post.
	 *
	 * @return array
	 */
	protected function get_wordcamp_status_logs( \WP_Post $wordcamp ) {
		$log_entries = \get_post_meta( $wordcamp->ID, '_status_change' );

		if ( ! empty( $log_entries ) ) {
			// Sort log entries in chronological order.
			usort( $log_entries, function( $a, $b ) {
				if ( $a['timestamp'] === $b['timestamp'] ) {
					return 0;
				}

				return ( $a['timestamp'] > $b['timestamp'] ) ? 1 : -1;
			} );

			return $log_entries;
		}

		return array();
	}

	/**
	 * Determine the ending status of a particular status change event.
	 *
	 * E.g. for this event:
	 *
	 *     Needs Vetting → More Info Requested
	 *
	 * The ending status would be "More Info Requested".
	 *
	 * @param array $log_entry A status change log entry.
	 *
	 * @return string
	 */
	protected function get_log_status_result( $log_entry ) {
		if ( isset( $log_entry['message'] ) ) {
			$pieces = explode( ' &rarr; ', $log_entry['message'] );

			if ( isset( $pieces[1] ) ) {
				return $this->get_status_id_from_name( $pieces[1] );
			}
		}

		return '';
	}

	/**
	 * Given the ID of a WordCamp status, determine the ID string.
	 *
	 * @param string $status_name A WordCamp status name.
	 *
	 * @return string
	 */
	protected function get_status_id_from_name( $status_name ) {
		$statuses = array_flip( self::get_all_statuses() );

		if ( isset( $statuses[ $status_name ] ) ) {
			return $statuses[ $status_name ];
		}

		return '';
	}

	/**
	 * A list of all possible WordCamp post statuses.
	 *
	 * Wrapper method to help minimize coupling with the WCPT plugin.
	 *
	 * @return array
	 */
	protected static function get_all_statuses() {
		return \WordCamp_Loader::get_post_statuses();
	}

	/**
	 * A list of status IDs for statuses that indicate a camp is not active.
	 *
	 * @return array
	 */
	protected static function get_inactive_statuses() {
		return array(
			'wcpt-rejected',
			'wcpt-cancelled',
			'wcpt-scheduled',
			'wcpt-closed',
		);
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$data       = $this->get_data();

		$start_date = $this->start_date;
		$end_date   = $this->end_date;
		$status     = $this->status;

		$active_camps = array_filter( $data, function( $wordcamp ) {
			if ( ! empty( $wordcamp['logs'] ) ) {
				return true;
			}

			return false;
		} );

		$inactive_camps = array_filter( $data, function( $wordcamp ) {
			if ( empty( $wordcamp['logs'] ) ) {
				return true;
			}

			return false;
		} );

		$statuses = self::get_all_statuses();

		include Reports\get_views_dir_path() . 'html/wordcamp-status.php';
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$statuses = self::get_all_statuses();

		$action     = filter_input( INPUT_POST, 'action' );
		$start_date = filter_input( INPUT_POST, 'start-date' );
		$end_date   = filter_input( INPUT_POST, 'end-date' );
		$status     = filter_input( INPUT_POST, 'status' );
		$nonce      = filter_input( INPUT_POST, self::SLUG . '-nonce' );

		$report = null;
		$error  = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			if ( ! strtotime( $start_date ) || ! strtotime( $end_date ) ) {
				$error = new \WP_Error( 'missing_parameter', 'Please enter valid start and end dates.' );
			} else {
				$report = new self( $start_date, $end_date, $status );
			}
		}

		include Reports\get_views_dir_path() . 'report/wordcamp-status.php';
	}
}
