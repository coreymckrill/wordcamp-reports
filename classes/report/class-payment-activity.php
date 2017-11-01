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

	/**
	 * Payment_Activity constructor.
	 *
	 * @param string $start_date  The start of the date range for the report.
	 * @param string $end_date    The end of the date range for the report.
	 * @param int    $wordcamp_id Optional. The ID of a WordCamp post to retrieve invoices for.
	 * @param array  $options     {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and Date_Range::__construct for additional parameters.
	 * }
	 */
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
		$payment_posts = array_filter( $payment_posts, function( $payment ) {
			if ( ! $this->timestamp_within_date_range( $payment['timestamp_approved'] ) && ! $this->timestamp_within_date_range( $payment['timestamp_payment_pending'] ) ) {
				return false;
			}

			return true;
		} );

		$data = $this->derive_totals_from_payment_events( $payment_posts );

		// Maybe cache the data.
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Retrieve Vendor Payments and Reimbursement Requests from their respective index database tables.
	 *
	 * @return array
	 */
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

	/**
	 * Get payment posts from a particular site.
	 *
	 * @param int   $blog_id  The ID of the site.
	 * @param array $post_ids The list of post IDs to get.
	 *
	 * @return array
	 */
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

	/**
	 * Determine the timestamps for particular payment post events from the post's log.
	 *
	 * This walks through the log array looking for specific events. If it finds them, it adds the event
	 * timestamp to a new key in the payment post array. At the end, it removes the log from the array.
	 *
	 * @param array $payment_post The array of data for a payment post.
	 *
	 * @return array
	 */
	protected function parse_payment_post_log( array $payment_post ) {
		$parsed_post = wp_parse_args( array(
			'timestamp_approved'        => 0,
			'timestamp_payment_pending' => 0,
		), $payment_post );

		if ( ! isset( $parsed_post['log'] ) ) {
			return $parsed_post;
		}

		foreach ( $parsed_post['log'] as $entry ) {
			if ( false !== strpos( $entry['message'], 'Request approved' ) ) {
				$parsed_post['timestamp_approved'] = $entry['timestamp'];
			} elseif ( false !== strpos( $entry['message'], 'Pending Payment' ) ) {
				$parsed_post['timestamp_payment_pending'] = $entry['timestamp'];
			}
		}

		unset( $parsed_post['log'] );

		return $parsed_post;
	}

	/**
	 * Aggregate the number and payment amounts of a group of Vendor Payments and Reimbursement Requests.
	 *
	 * @param array $payment_posts The group of posts to aggregate.
	 *
	 * @return array
	 */
	protected function derive_totals_from_payment_events( array $payment_posts ) {
		$data = array(
			'vendor_payment_count'              => 0,
			'vendor_payment_amount_by_currency' => array(),
			'reimbursement_count'               => 0,
			'reimbursement_amount_by_currency'  => array(),
		);

		$data_groups = array(
			'requests' => $data,
			'payments' => $data,
		);

		$currencies = array();

		foreach ( $payment_posts as $payment ) {
			if ( ! isset( $payment['currency'] ) || ! $payment['currency'] ) {
				continue;
			}

			if ( ! in_array( $payment['currency'], $currencies, true ) ) {
				$data_groups['requests']['vendor_payment_amount_by_currency'][ $payment['currency'] ] = 0;
				$data_groups['requests']['reimbursement_amount_by_currency'][ $payment['currency'] ]  = 0;
				$data_groups['payments']['vendor_payment_amount_by_currency'][ $payment['currency'] ] = 0;
				$data_groups['payments']['reimbursement_amount_by_currency'][ $payment['currency'] ]  = 0;
				$currencies[]                                                                         = $payment['currency'];
			}

			switch ( $payment['post_type'] ) {
				case 'wcp_payment_request' :
					if ( $this->timestamp_within_date_range( $payment['timestamp_approved'] ) ) {
						$data_groups['requests']['vendor_payment_count'] ++;
						$data_groups['requests']['vendor_payment_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
					}
					if ( $this->timestamp_within_date_range( $payment['timestamp_payment_pending'] ) ) {
						$data_groups['payments']['vendor_payment_count'] ++;
						$data_groups['payments']['vendor_payment_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
					}
					break;

				case 'wcb_reimbursement' :
					if ( $this->timestamp_within_date_range( $payment['timestamp_approved'] ) ) {
						$data_groups['requests']['reimbursement_count'] ++;
						$data_groups['requests']['reimbursement_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
					}
					if ( $this->timestamp_within_date_range( $payment['timestamp_payment_pending'] ) ) {
						$data_groups['payments']['reimbursement_count'] ++;
						$data_groups['payments']['reimbursement_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
					}
					break;
			}
		}

		foreach ( $data_groups as &$group ) {
			ksort( $group['vendor_payment_amount_by_currency'] );
			ksort( $group['reimbursement_amount_by_currency'] );
		}

		return $data_groups;
	}

	/**
	 * Check if a given Unix timestamp is within the date range set in the report.
	 *
	 * @param int $timestamp The Unix timestamp to test.
	 *
	 * @return bool True if within the date range.
	 */
	protected function timestamp_within_date_range( $timestamp ) {
		$date = new \DateTime();
		$date->setTimestamp( $timestamp );

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

		$report = new self( '2017-08-01', '2017-09-30', 0, array( 'cache_data' => false ) );

		echo '<pre>';
		var_dump( $report->error->get_error_messages() );
		var_dump( $report->get_data() );
		echo '</pre>';

		//include Reports\get_views_dir_path() . 'report/payment-activity.php';
	}
}