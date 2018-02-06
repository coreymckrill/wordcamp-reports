<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports\Utilities;

/**
 * Class Base
 *
 * A base report class with methods for caching data and handling errors, plus some other helper methods.
 *
 * @package WordCamp\Reports\Report
 */
abstract class Base {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = '';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = '';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = '';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = '';

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = '';

	/**
	 * Additional report parameters.
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * A container object to hold error messages.
	 *
	 * @var \WP_Error
	 */
	public $error = null;

	/**
	 * Base constructor.
	 *
	 * @param array $options    {
	 *     Optional. Additional report parameters.
	 *
	 *     @type bool $cache_data  True to look for cached data and cache the generated data set. Default true.
	 *     @type bool $flush_cache True to delete any cached data generated with the current report parameters. Default false.
	 * }
	 */
	public function __construct( array $options = array() ) {
		$this->options = wp_parse_args( $options, array(
			'cache_data'  => true,
			'flush_cache' => false,
		) );

		$this->error = new \WP_Error();
	}

	/**
	 * Query and parse the data for the report.
	 *
	 * @return array
	 */
	public abstract function get_data();

	/**
	 * Compile the report data into results.
	 *
	 * @param array $data The data to compile.
	 *
	 * @return array
	 */
	public abstract function compile_report_data( array $data );

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = 'report_' . self::$slug;

		return $cache_key;
	}

	/**
	 * Generate a cache expiration interval.
	 *
	 * @return int A time interval in seconds.
	 */
	protected function get_cache_expiration() {
		return DAY_IN_SECONDS;
	}

	/**
	 * If this instance has caching enabled, retrieve cached data.
	 *
	 * @return mixed|null Null if caching is disabled. Otherwise a cached value, or false if none is available.
	 */
	protected function maybe_get_cached_data() {
		if ( true === $this->options['flush_cache'] ) {
			$this->flush_cache();
			return false;
		} elseif ( false !== $this->options['cache_data'] ) {
			return get_transient( $this->get_cache_key() );
		}

		return null;
	}

	/**
	 * If this instance has caching enabled, cache the supplied data.
	 *
	 * @param mixed $data The data to cache.
	 *
	 * @return bool True if the data was successfully cached. Otherwise false.
	 */
	protected function maybe_cache_data( $data ) {
		if ( false !== $this->options['cache_data'] ) {
			$cache_key  = $this->get_cache_key();
			$expiration = $this->get_cache_expiration();

			return set_transient( $cache_key, $data, $expiration );
		}

		return false;
	}

	/**
	 * Delete the cached data for this report instance, if it exists.
	 *
	 * @return bool
	 */
	protected function flush_cache() {
		return delete_transient( $this->get_cache_key() );
	}

	/**
	 * Merge two error objects into one, new error object.
	 *
	 * @param \WP_Error $error1 An error object.
	 * @param \WP_Error $error2 An error object.
	 *
	 * @return \WP_Error The combined errors of the two parameters.
	 */
	protected function merge_errors( \WP_Error $error1, \WP_Error $error2 ) {
		$codes = $error2->get_error_codes();

		foreach ( $codes as $code ) {
			$messages = $error2->get_error_messages( $code );

			foreach ( $messages as $message ) {
				$error1->add( $code, $message );
			}
		}

		return $error1;
	}

	/**
	 * Validate a given WordCamp post ID.
	 *
	 * @param int $wordcamp_id The ID of a WordCamp post.
	 *
	 * @return bool True if the WordCamp ID is valid. Otherwise false.
	 */
	protected function validate_wordcamp_id( $wordcamp_id ) {
		$wordcamp = get_post( $wordcamp_id );

		if ( ! $wordcamp instanceof \WP_Post || Utilities\get_wordcamp_post_type_id() !== get_post_type( $wordcamp ) ) {
			$this->error->add( 'invalid_wordcamp_id', 'Please enter a valid WordCamp ID.' );

			return false;
		}

		$wordcamp_site_id = get_wordcamp_site_id( $wordcamp );

		if ( ! $wordcamp_site_id ) {
			$this->error->add( 'wordcamp_without_site', 'The specified WordCamp does not have a site yet.' );

			return false;
		}

		return true;
	}

	/**
	 * Render an HTML notice containing error messages.
	 *
	 * @return void
	 */
	protected function render_error_html() {
		?>
		<div class="notice notice-error">
			<?php foreach ( $this->error->get_error_messages() as $message ) : ?>
				<?php echo wpautop( wp_kses_post( $message ) ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Prepare report data for a REST response.
	 *
	 * This takes an arbitrary data value and wraps it in a WP REST Response object along with additional
	 * information about the report.
	 *
	 * @param mixed $data                       The data that will go in the `data` parameter of the response.
	 * @param array $additional_response_params Additional top-level parameters to add to the response.
	 *
	 * @return \WP_REST_Response
	 */
	protected static function prepare_rest_response( $data, array $additional_response_params = array() ) {
		$response_data = array_merge( array(
			'report_name'        => static::$name,
			'report_description' => static::$description,
		), $additional_response_params );

		$response_data['data'] = $data;

		return new \WP_REST_Response( $response_data );
	}
}
