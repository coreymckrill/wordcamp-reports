<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Admin;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var Report\WordCamp_Status $wordcamp_status */

var_dump( $wordcamp_status->get_data() );
