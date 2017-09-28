<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Admin;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

?>

<div class="wrap">
	<h1>WordCamp Reports</h1>

	<p>Choose a report:</p>

	<ul class="ul-disc">
		<?php foreach ( Reports\get_report_classes() as $class ) : ?>
			<li>
				<a href="<?php echo esc_attr( Reports\get_page_url( $class::SLUG ) ); ?>"><?php echo esc_html( $class::NAME ); ?></a>
				&ndash;
				<em><?php echo esc_html( $class::DESCRIPTION ); ?></em>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
