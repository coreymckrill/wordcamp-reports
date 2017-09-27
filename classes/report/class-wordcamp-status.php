<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;


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


	public function set_locale_to_en_US() {
		return 'en_US';
	}


	public function get_data() {
		\add_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		$wordcamp_posts = $this->get_all_wordcamp_posts();
		$data           = array();

		foreach ( $wordcamp_posts as $wordcamp ) {
			$logs = $this->get_wordcamp_status_logs( $wordcamp );

			// Trim log entries occurring after the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $this->end_date->getTimestamp() >= $entry['timestamp'] ) {
					return true;
				}

				return false;
			} );

			// Skip if there is no log activity before the end of the date range.
			if ( empty( $logs ) ) {
				continue;
			}

			$latest_log    = $logs[0];
			$latest_status = $this->get_log_status_result( $latest_log );

			// Trim log entries occurring before the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $this->start_date->getTimestamp() <= $entry['timestamp'] ) {
					return true;
				}

				return false;
			} );

			// Skip if there is no log activity in the date range and the camp has an inactive status.
			if ( empty( $logs ) && in_array( $latest_status, $this->get_inactive_statuses(), true ) ) {
				continue;
			}

			$data[ $wordcamp->ID ] = array(
				'name'          => \get_wordcamp_name( \get_wordcamp_site_id( $wordcamp ) ),
				'url'           => \get_post_meta( $wordcamp->ID, 'URL', true ),
				'post'          => $wordcamp,
				'logs'          => $logs,
				'latest_log'    => $latest_log,
				'latest_status' => $latest_status,
			);
		}

		\remove_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		return $data;
	}


	protected function get_all_wordcamp_posts() {
		$post_args = array(
			'post_type'           => \WCPT_POST_TYPE_ID,
			'post_status'         => 'any',
			'nopaging'            => true,
			'ignore_sticky_posts' => true,
		);

		return \get_posts( $post_args );
	}


	protected function get_wordcamp_status_logs( \WP_Post $wordcamp ) {
		$log_entries = \get_post_meta( $wordcamp->ID, '_status_change' );

		if ( ! empty( $log_entries ) ) {
			// Sort log entries in reverse-chronological order.
			usort( $log_entries, function( $a, $b ) {
				if ( $a['timestamp'] === $b['timestamp'] ) {
					return 0;
				}

				return ( $a['timestamp'] > $b['timestamp'] ) ? -1 : 1;
			} );

			return $log_entries;
		}

		return array();
	}


	protected function get_log_status_result( array $log_entry ) {
		if ( isset( $log_entry['message'] ) ) {
			$pieces = explode( ' &rarr; ', $log_entry['message'] );

			if ( isset( $pieces[1] ) ) {
				return $this->get_status_id_from_name( $pieces[1] );
			}
		}

		return '';
	}


	protected function get_status_id_from_name( $status_name ) {
		$statuses = array_flip( \WordCamp_Loader::get_post_statuses() );

		if ( isset( $statuses[ $status_name ] ) ) {
			return $statuses[ $status_name ];
		}

		return '';
	}


	protected function get_inactive_statuses() {
		return array(
			'wcpt-rejected',
			'wcpt-cancelled',
			'wcpt-scheduled',
			'wcpt-closed',
		);
	}


	public function render_html( $data ) {
		$start_date = $this->start_date;
		$end_date   = $this->end_date;

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

		$statuses = \WordCamp_Loader::get_post_statuses();

		include Reports\get_views_dir_path() . 'report/wordcamp-status-html.php';
	}
}
