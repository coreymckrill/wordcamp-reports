<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();


abstract class Base {

	public abstract function get_data();


	public abstract static function render_admin_page();


	public function render_html() {}
}
