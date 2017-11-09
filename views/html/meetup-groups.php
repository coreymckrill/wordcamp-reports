<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Ticket_Revenue;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var array $data */
?>

<?php if ( $data['total_groups'] ) : ?>
	<h3>Meetup groups in the chapter program as of <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

	<ul>
		<li>Total groups: <?php echo number_format_i18n( $data['total_groups'] ); ?></li>
		<li>
			Total groups by country:<br />
			<table class="striped">
				<thead>
				<tr>
					<td>Country</td>
					<td># of Groups</td>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( array_keys( $data['total_groups_by_country'] ) as $country ) : ?>
					<tr>
						<td><?php echo esc_html( $country ); ?></td>
						<td><?php echo number_format_i18n( $data['total_groups_by_country'][ $country ] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</li>
		<li>Total group members: <?php echo number_format_i18n( $data['total_members'] ); ?></li>
		<li>
			Total group members by country:<br />
			<table class="striped">
				<thead>
				<tr>
					<td>Country</td>
					<td># of Members</td>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( array_keys( $data['total_members_by_country'] ) as $country ) : ?>
					<tr>
						<td><?php echo esc_html( $country ); ?></td>
						<td><?php echo number_format_i18n( $data['total_members_by_country'][ $country ] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</li>
	</ul>

	<?php if ( $data['joined_groups'] ) : ?>
		<h3>Meetup groups that joined the chapter program between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

		<ul>
			<li>Total groups that joined: <?php echo number_format_i18n( $data['joined_groups'] ); ?></li>
			<li>
				Total groups that joined by country:<br />
				<table class="striped">
					<thead>
					<tr>
						<td>Country</td>
						<td># of Groups</td>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( array_keys( $data['joined_groups_by_country'] ) as $country ) : ?>
						<tr>
							<td><?php echo esc_html( $country ); ?></td>
							<td><?php echo number_format_i18n( $data['joined_groups_by_country'][ $country ] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</li>
			<li>Total group members that joined: <?php echo number_format_i18n( $data['joined_members'] ); ?></li>
			<li>
				Total group members that joined by country:<br />
				<table class="striped">
					<thead>
					<tr>
						<td>Country</td>
						<td># of Members</td>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( array_keys( $data['joined_members_by_country'] ) as $country ) : ?>
						<tr>
							<td><?php echo esc_html( $country ); ?></td>
							<td><?php echo number_format_i18n( $data['joined_members_by_country'][ $country ] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</li>
		</ul>
	<?php endif; ?>
<?php else : ?>
	<p>
		No data
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
