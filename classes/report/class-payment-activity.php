<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Budgets_Dashboard\Reimbursement_Requests;

/**
 * Class Payment_Activity
 *
 * @package WordCamp\Reports\Report
 */
class Payment_Activity extends Date_Range {
	/**
	 * Report name.
	 */
	public static $name = 'Payment Activity';

	/**
	 * Report slug.
	 */
	public static $slug = 'payment-activity';

	/**
	 * Report description.
	 */
	public static $description = 'A summary of payment activity during a given time period.';

	/**
	 * @var int The ID of the WordCamp post for this report.
	 */
	public $wordcamp_id = 0;

	/**
	 * @var int The ID of the WordCamp site where the invoices are located.
	 */
	public $wordcamp_site_id = 0;


	public function __construct( $start_date, $end_date, $wordcamp_id = 0, array $options = array() ) {
		parent::__construct( $start_date, $end_date, $options );

		if ( $wordcamp_id && $this->validate_wordcamp_id( $wordcamp_id ) ) {
			$this->wordcamp_id      = $wordcamp_id;
			$this->wordcamp_site_id = get_wordcamp_site_id( get_post( $wordcamp_id ) );
		}
	}

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = parent::get_cache_key();

		if ( $this->wordcamp_id ) {
			$cache_key .= '_' . $this->wordcamp_id;
		}

		return $cache_key;
	}

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

		$indexed_payments = $this->get_indexed_payments();
		$payments_by_site = array();

		foreach ( $indexed_payments as $index ) {
			if ( ! isset( $payments_by_site[ $index['blog_id'] ] ) ) {
				$payments_by_site[ $index['blog_id'] ] = array();
			}

			$payments_by_site[ $index['blog_id'] ][] = $index['post_id'];
		}

		$payment_posts = array();

		foreach ( $payments_by_site as $blog_id => $post_ids ) {
			$payment_posts = array_merge( $payment_posts, $this->get_payment_posts( $blog_id, $post_ids ) );
		}

		$payment_posts = array_map( array( $this, 'parse_payment_post_log' ), $payment_posts );

		$data = array(
			'requests' => array(
				'vendor_payment_count'  => 0,
				'vendor_payment_amount' => 0,
				'reimbursement_count'   => 0,
				'reimbursement_amount'  => 0,
			),
			'payments' => array(
				'vendor_payment_count'  => 0,
				'vendor_payment_amount' => 0,
				'reimbursement_count'   => 0,
				'reimbursement_amount'  => 0,
			),
		);

		foreach ( $payment_posts as $payment ) {
			switch ( $payment['post_type'] ) {
				case 'wcp_payment_request' :
					if ( $this->timestamp_within_date_range( $payment['timestamp_approved'] ) ) {
						$data['requests']['vendor_payment_count'] ++;
						$data['requests']['vendor_payment_amount'] += floatval( $payment['amount'] );
					}
					if ( $this->timestamp_within_date_range( $payment['timestamp_payment_pending'] ) ) {
						$data['payments']['vendor_payment_count'] ++;
						$data['payments']['vendor_payment_amount'] += floatval( $payment['amount'] );
					}
					break;

				case 'wcb_reimbursement' :
					if ( $this->timestamp_within_date_range( $payment['timestamp_approved'] ) ) {
						$data['requests']['reimbursement_count'] ++;
						$data['requests']['reimbursement_amount'] += floatval( $payment['amount'] );
					}
					if ( $this->timestamp_within_date_range( $payment['timestamp_payment_pending'] ) ) {
						$data['payments']['reimbursement_count'] ++;
						$data['payments']['reimbursement_amount'] += floatval( $payment['amount'] );
					}
					break;
			}
		}

		// Maybe cache the data.
		$this->maybe_cache_data( $data );

		return $data;
	}


	protected function get_indexed_payments() {
		/** @global \wpdb $wpdb */
		global $wpdb;

		$payments_table       = \Payment_Requests_Dashboard::get_table_name();
		$reimbursements_table = Reimbursement_Requests\get_index_table_name();

		$extra_where = ( $this->wordcamp_site_id ) ? ' AND blog_id = ' . $this->wordcamp_site_id : '';

		$index_query = $wpdb->prepare( "
			(
			SELECT blog_id, post_id
			FROM $payments_table
			WHERE created <= %d
			AND ( paid = 0 OR paid >= %d )
			$extra_where
			) UNION (
			SELECT blog_id, request_id AS post_id
			FROM $reimbursements_table
			WHERE date_requested <= %d
			AND ( date_paid = 0 OR date_paid >= %d )
			$extra_where
			)
		", $this->end_date->getTimestamp(), $this->start_date->getTimestamp(),
			$this->end_date->getTimestamp(), $this->start_date->getTimestamp() );

		return $wpdb->get_results( $index_query, ARRAY_A );
	}


	protected function get_payment_posts( $blog_id, array $post_ids ) {
		$payment_posts = array();
		$post_types    = array( 'wcp_payment_request', 'wcb_reimbursement' );

		switch_to_blog( $blog_id );

		$query_args = array(
			'post_type'           => $post_types,
			'post_status'         => 'all',
			'post__in'            => $post_ids,
			'nopaging'            => true,
		);

		$raw_posts = get_posts( $query_args );

		foreach ( $raw_posts as $raw_post ) {
			switch ( $raw_post->post_type ) {
				case 'wcp_payment_request' :
					$currency = $raw_post->_camppayments_currency;
					$amount   = $raw_post->_camppayments_payment_amount;
					break;

				case 'wcb_reimbursement' :
					$currency = get_post_meta( $raw_post->ID, '_wcbrr_currency', true );
					$amount   = Reimbursement_Requests\get_amount( $raw_post->ID );
					break;

				default :
					$currency = '';
					$amount   = '';
					break;
			}

			$payment_posts[] = array(
				'blog_id'       => $blog_id,
				'post_id'       => $raw_post->ID,
				'post_type'     => $raw_post->post_type,
				'currency'      => $currency,
				'amount'        => $amount,
				'log'           => json_decode( $raw_post->_wcp_log, true ),
			);

			clean_post_cache( $raw_post );
		}

		restore_current_blog();

		return $payment_posts;
	}


	protected function parse_payment_post_log( $payment_post ) {
		$parsed_post = wp_parse_args( array(
			'timestamp_approved'        => 0,
			'timestamp_payment_pending' => 0,
		), $payment_post );

		if ( ! isset( $parsed_post['log'] ) ) {
			return $parsed_post;
		}

		foreach ( $parsed_post['log'] as $entry ) {
			if ( false !== strpos( $entry['message'], 'Request approved' ) ) {
				$parsed_post['approved'] = $entry['timestamp'];
			} elseif ( false !== strpos( $entry['message'], 'Pending Payment' ) ) {
				$parsed_post['payment_pending'] = $entry['timestamp'];
			}
		}

		unset( $parsed_post['log'] );

		return $parsed_post;
	}


	protected function timestamp_within_date_range( $timestamp ) {
		try {
			$date = new \DateTime( $timestamp );
		} catch ( \Exception $e ) {
			return false;
		}

		if ( $date >= $this->start_date && $date <= $this->end_date ) {
			return true;
		}

		return false;
	}


	public static function render_admin_page() {
		/*
		$start_date  = filter_input( INPUT_POST, 'start-date' );
		$end_date    = filter_input( INPUT_POST, 'end-date' );
		$wordcamp_id = filter_input( INPUT_POST, 'wordcamp-id' );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			$options = array(
				'earliest_start' => new \DateTime( '2007-11-17' ), // Date of first WordCamp in the system.
				'cache_data'     => false, // WP Admin is low traffic and more trusted, so turn off caching.
			);

			$report = new self( $start_date, $end_date, $wordcamp_id, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}
		*/

		$report = new self( '2017-10-01', '2017-10-05', 0, array( 'cache_data' => false ) );

		echo '<pre>';
		var_dump( $report->error->get_error_messages() );
		var_dump( $report->get_data() );
		echo '</pre>';

		//include Reports\get_views_dir_path() . 'report/payment-activity.php';
	}
}
