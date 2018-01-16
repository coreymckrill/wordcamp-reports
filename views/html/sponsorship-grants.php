<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Sponsorship_Grants;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string $wordcamp_name */
/** @var array $data */

$asterisk2 = false;
?>

<?php if ( $data['grant_count'] ) : ?>
	<h3>
		Global Sponsorship Grants
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<ul>
		<?php if ( $data['grant_count'] ) : ?>
			<li>Grants awarded: <?php echo number_format_i18n( $data['grant_count'] ) ?></li>
		<?php endif; ?>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Total Amount Awarded</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $data['total_amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['total_amount_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $data['converted_amounts'][ $currency ] ); ?>
					<?php if ( $data['total_amount_by_currency'][ $currency ] > 0 && $data['converted_amounts'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $data['total_amount_converted'] ); ?></td>
		</tr>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $data['grant_count'] ) : ?>
	<p class="description">* Estimate based on exchange rates for <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></p>
	<?php if ( $asterisk2 ) : ?>
		<p class="description">** Currency exchange rate not available.</p>
	<?php endif; ?>
<?php else : ?>
	<p>
		No data
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
