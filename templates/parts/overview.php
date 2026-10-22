<?php
/**
 * Project overview band. Empty rows, columns, and the whole band are omitted.
 *
 * @package WW\HarrimanProjects
 */

defined( 'ABSPATH' ) || exit;

$ww_template = ww_template_number();
$ww_labels   = array(
	'client'      => __( 'Client', 'ww-projects' ),
	'type'        => __( 'Type', 'ww-projects' ),
	'size'        => __( 'Size', 'ww-projects' ),
	'program'     => __( 'Program', 'ww-projects' ),
	'components'  => __( 'Program Components', 'ww-projects' ),
	'recognition' => __( 'Recognition', 'ww-projects' ),
);

$ww_row = static function ( $key ) use ( $ww_labels ) {
	if ( ! isset( $ww_labels[ $key ] ) ) {
		return '';
	}
	return ww_overview_row( $ww_labels[ $key ], ww_overview_text( $key ) );
};

$ww_column_two   = array();
$ww_column_three = array();

foreach ( array( 'client', 'type' ) as $ww_key ) {
	$ww_item = $ww_row( $ww_key );
	if ( $ww_item ) {
		$ww_column_two[] = $ww_item;
	}
}

if ( 1 === $ww_template ) {
	$ww_size = $ww_row( 'size' );
	if ( $ww_size ) {
		$ww_column_three[] = $ww_size;
	}
} else {
	$ww_size = $ww_row( 'size' );
	if ( $ww_size ) {
		$ww_column_two[] = $ww_size;
	}
	if ( 2 === $ww_template ) {
		$ww_program = $ww_row( 'program' );
		if ( $ww_program ) {
			$ww_column_two[] = $ww_program;
		}
	}
}

$ww_components = $ww_row( 'components' );
if ( $ww_components ) {
	$ww_column_three[] = $ww_components;
}

$ww_contacts = ww_overview_contacts();
if ( $ww_contacts ) {
	$ww_column_three[] = ww_overview_row( __( 'Key Contacts', 'ww-projects' ), $ww_contacts );
}

$ww_recognition = $ww_row( 'recognition' );
if ( $ww_recognition ) {
	$ww_column_three[] = $ww_recognition;
}

if ( ! $ww_column_two && ! $ww_column_three ) {
	return;
}
?>
<section class="ww-project__overview">
	<div class="ww-project__overview-title">
		<h2><?php esc_html_e( 'Project', 'ww-projects' ); ?><br><?php esc_html_e( 'Overview', 'ww-projects' ); ?></h2>
	</div>
	<?php if ( $ww_column_two ) : ?>
		<div class="ww-project__overview-col">
			<?php echo implode( '', $ww_column_two ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped in ww_overview_row(). ?>
		</div>
	<?php endif; ?>
	<?php if ( $ww_column_three ) : ?>
		<div class="ww-project__overview-col">
			<?php echo implode( '', $ww_column_three ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped in ww_overview_row(). ?>
		</div>
	<?php endif; ?>
</section>
