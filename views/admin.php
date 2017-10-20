<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Admin;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/** @var array $reports_with_admin */
?>

<div class="wrap">
	<h1>WordCamp Reports</h1>

	<p>Choose a report:</p>

	<ul class="ul-disc">
		<?php foreach ( $reports_with_admin as $class ) : ?>
			<li>
				<a href="<?php echo esc_attr( Reports\get_page_url( $class::$slug ) ); ?>"><?php echo esc_html( $class::$name ); ?></a>
				&ndash;
				<em><?php echo esc_html( $class::$description ); ?></em>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
