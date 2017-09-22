<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();


class Base {

	public function get_data() {}


	protected function get_updated_wordcamp_posts( \DateTime $updated_since ) {
		$post_args = array(
			'post_type'           => \WCPT_POST_TYPE_ID,
			'post_status'         => 'any',
			'nopaging'            => true,
			'ignore_sticky_posts' => true,
			'date_query'          => array(
				array(
					'column' => 'post_modified_gmt',
					'after'  => $updated_since->format( 'Y-m-d H:i:s' ),
				),
			),
		);

		return get_posts( $post_args );
	}


	public function create_post() {}


	public function send_email() {}


	public function export_csv() {}
}
