<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Sponsor_Invoices;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string $wordcamp_name */
/** @var array $invoices */
/** @var array $payments */
?>

<h3>
	Sponsor Invoice activity
	<?php if ( $wordcamp_name ) : ?>
		for <?php echo esc_html( $wordcamp_name ); ?>
	<?php endif; ?>
	<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
		on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
	<?php else : ?>
		between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
	<?php endif; ?>
</h3>

<?php if ( $invoices['total_count'] > 0 || $payments['total_count'] > 0 ) : ?>
	<ul>
		<?php if ( $invoices['total_count'] > 0 ) : ?>
			<li>Invoices sent: <?php echo number_format_i18n( $invoices['total_count'] ); ?></li>
			<li>
				Amount invoiced:
				<table class="striped">
					<thead>
					<tr>
						<td>Currency</td>
						<td>Amount</td>
						<td>Estimated Value in USD *</td>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( array_keys( $invoices['amount_by_currency'] ) as $currency ) : ?>
						<tr>
							<td><?php echo esc_html( $currency ); ?></td>
							<td><?php echo number_format_i18n( $invoices['amount_by_currency'][ $currency ] ); ?></td>
							<td><?php echo number_format_i18n( $invoices['converted_amounts'][ $currency ] ); ?></td>
						</tr>
					<?php endforeach; ?>
						<tr>
							<td></td>
							<td>Total: </td>
							<td><?php echo number_format_i18n( $invoices['total_amount_converted'] ); ?></td>
						</tr>
					</tbody>
				</table>
			</li>
		<?php endif; ?>
		<?php if ( $payments['total_count'] > 0 ) : ?>
			<li>Payments received: <?php echo number_format_i18n( $payments['total_count'] ); ?></li>
			<li>
				Amount received:
				<table class="striped">
					<thead>
					<tr>
						<td>Currency</td>
						<td>Amount</td>
						<td>Estimated Value in USD *</td>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( array_keys( $payments['amount_by_currency'] ) as $currency ) : ?>
						<tr>
							<td><?php echo esc_html( $currency ); ?></td>
							<td><?php echo number_format_i18n( $payments['amount_by_currency'][ $currency ] ); ?></td>
							<td><?php echo number_format_i18n( $payments['converted_amounts'][ $currency ] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<td></td>
						<td>Total: </td>
						<td><?php echo number_format_i18n( $payments['total_amount_converted'] ); ?></td>
					</tr>
					</tbody>
				</table>
			</li>
		<?php endif; ?>
	</ul>

	<p class="description">* Estimate based on exchange rates for <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></p>
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
