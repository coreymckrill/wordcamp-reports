<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports;
defined( 'WPINC' ) || die();

/**
 * Class Meetup_Client
 *
 * @package WordCamp\Reports
 */
class Meetup_Client {
	/**
	 * @var string The base URL for the API endpoints.
	 */
	protected $api_base = 'https://api.meetup.com/';

	/**
	 * @var string The API key.
	 */
	protected $api_key = '';

	/**
	 * @var \WP_Error|null Container for errors.
	 */
	public $error = null;

	/**
	 * Meetup_Client constructor.
	 */
	public function __construct() {
		$this->error = new \WP_Error();

		if ( defined( 'MEETUP_API_KEY' ) ) {
			$this->api_key = MEETUP_API_KEY;
		} else {
			$this->error->add(
				'api_key_undefined',
				'The Meetup.com API Key is undefined.'
			);
		}
	}

	/**
	 * Send a request to the Meetup API and return the response.
	 *
	 * This automatically paginates requests and will repeat requests to ensure all results are retrieved.
	 * It also tries to account for API request limits and throttles to avoid getting a limit error.
	 *
	 * @param string $request_url The API endpoint URL to send the request to.
	 *
	 * @return array|\WP_Error The results of the request.
	 */
	protected function send_request( $request_url ) {
		$data = array();

		$request_url = add_query_arg( array(
			'page' => 200,
		), $request_url );

		while ( $request_url ) {
			$request_url = $this->sign_request_url( $request_url );

			$response = wcorg_redundant_remote_get( $request_url );

			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $body['results'] ) ) {
					$new_data = $body['results'];
				} else {
					$new_data = $body;
				}

				if ( is_array( $new_data ) ) {
					$data = array_merge( $data, $new_data );
				} else {
					$this->error->add(
						'unexpected_response_data',
						'The API response did not provide the expected data format.'
					);
					break;
				}

				$request_url = $this->get_next_url( $response );
			} else {
				$this->handle_error_response( $response );
				break;
			}

			if ( $request_url ) {
				$this->maybe_throttle( $response );
			}
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $data;
	}

	/**
	 * Sign a request URL with our API key.
	 *
	 * @param string $request_url
	 *
	 * @return string
	 */
	protected function sign_request_url( $request_url ) {
		return add_query_arg( array(
			'sign' => true,
			'key'  => $this->api_key,
		), $request_url );
	}

	/**
	 * Get the URL for the next page of results from a paginated API response.
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	protected function get_next_url( $response ) {
		$url   = '';

		// First try v3.
		$links = wp_remote_retrieve_header( $response, 'link' );
		if ( $links ) {
			foreach ( (array) $links as $link ) {
				if ( false !== strpos( $link, 'rel="next"' ) && preg_match( '/^<([^>]+)>/', $link, $matches ) ) {
					$url = $matches[1];
					break;
				}
			}
		}

		// Then try v2.
		if ( ! $url ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['meta']['next'] ) ) {
				$url = $body['meta']['next'];
			}
		}

		return esc_url_raw( $url );
	}

	/**
	 * Check the rate limit status in an API response and delay further execution if necessary.
	 *
	 * @param array $response
	 *
	 * @return void
	 */
	protected function maybe_throttle( $response ) {
		$remaining = absint( wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ) );
		$period    = absint( wp_remote_retrieve_header( $response, 'x-ratelimit-reset' ) );

		if ( $remaining > 1 ) {
			return;
		}

		// Add a little extra to the reset period.
		usleep( $period * 1000 + 100 );
	}

	/**
	 * Extract error information from an API response and add it to our error handler.
	 *
	 * @param array $response
	 *
	 * @return void
	 */
	protected function handle_error_response( $response ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['errors'] ) ) {
			foreach ( $data['errors'] as $error ) {
				$this->error->add( $error['code'], $error['message'] );
			}
		} elseif ( isset( $data['code'] ) && isset( $data['details'] ) ) {
			$this->error->add( $data['code'], $data['details'] );
		} else {
			$this->error->add(
				'http_response_code',
				wp_remote_retrieve_response_code( $response ) . ': ' . print_r( $data, true )
			);
		}
	}

	/**
	 * Retrieve data about groups in the Chapter program.
	 *
	 * @param array $args Optional. Additional request parameters.
	 *                    See https://www.meetup.com/meetup_api/docs/pro/:urlname/groups/
	 *
	 * @return array|\WP_Error
	 */
	public function get_groups( array $args = array() ) {
		$request_url = $this->api_base . 'pro/wordpress/groups';

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_request( $request_url );
	}

	/**
	 * Retrieve data about events associated with a set of groups.
	 *
	 * This automatically breaks up requests into chunks of 50 groups to avoid overloading the API.
	 *
	 * @param array $group_ids The IDs of the groups to get events for.
	 * @param array $args      Optional. Additional request parameters.
	 *                         See https://www.meetup.com/meetup_api/docs/2/events/
	 *
	 * @return array|\WP_Error
	 */
	public function get_events( array $group_ids, array $args = array() ) {
		$url_base     = $this->api_base . '2/events';
		$group_chunks = array_chunk( $group_ids, 50, true ); // Meetup API sometimes throws an error with chunk size larger than 50.
		$events       = array();

		foreach ( $group_chunks as $chunk ) {
			$query_args = array_merge( array(
				'group_id' => implode( ',', $chunk ),
			), $args );

			$request_url = add_query_arg( $query_args, $url_base );

			$data = $this->send_request( $request_url );

			if ( is_wp_error( $data ) ) {
				return $data;
			}

			$events = array_merge( $events, $data );
		}

		return $events;
	}

	/**
	 * Retrieve data about events associated with one particular group.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array $args        Optional. Additional request parameters.
	 *                           See https://www.meetup.com/meetup_api/docs/:urlname/events/
	 *
	 * @return array|\WP_Error
	 */
	public function get_group_events( $group_slug, array $args = array() ) {
		$request_url = $this->api_base . "$group_slug/events";

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_request( $request_url );
	}
}
