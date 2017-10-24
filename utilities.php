<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Utilities;
defined( 'WPINC' ) || die();

/**
 * Get the ID string for the WordCamp CPT.
 *
 * Wrapper method to help minimize coupling with the WCPT plugin.
 *
 * @return string
 */
function get_wordcamp_post_type_id() {
	return \WCPT_POST_TYPE_ID;
}

/**
 * A list of all possible WordCamp post statuses.
 *
 * Wrapper method to help minimize coupling with the WCPT plugin.
 *
 * @return array
 */
function get_all_wordcamp_statuses() {
	return \WordCamp_Loader::get_post_statuses();
}
