<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Admin;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var Report\WordCamp_Status $wordcamp_status */

?>

<div class="wrap">

<?php
$wordcamp_status = new Report\WordCamp_Status( '2017-04-01', '2017-06-01' );
$data = $wordcamp_status->get_data();
$wordcamp_status->render_html($data);
?>

</div>
