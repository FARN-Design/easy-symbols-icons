<?php
/**
 * Server-side rendering for the icon block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = $attributes ?? [];

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'selected-icon-wrapper',
] );

$color = null;

if ( ! empty( $attributes['style']['color']['text'] ) ) {
	$color = $attributes['style']['color']['text'];
} elseif ( ! empty( $attributes['textColor'] ) ) {
	$color = 'var(--wp--preset--color--' . $attributes['textColor'] . ')';
}

$icon_class = $attributes['iconClass'] ?? $attributes['className'] ?? '';

?>

<div <?php echo $wrapper_attributes; ?>>
	<?php if ( ! empty( $icon_class ) ) : ?>
		<span
			class="<?php echo esc_attr( $icon_class . ' eics-icon-span' ); ?>"
			<?php if ( $color ) : ?>
				style="color: <?php echo esc_attr( $color ); ?>;"
			<?php endif; ?>
		></span>
	<?php else : ?>
		<p><?php esc_html_e( 'No Icon Selected', 'easy-symbols-icons' ); ?></p>
	<?php endif; ?>
</div>