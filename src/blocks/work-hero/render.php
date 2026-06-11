<?php
/**
 * Render: work/work-hero
 *
 * Available variables:
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content.
 * @var WP_Block $block      Block instance (provides context).
 */

$post_id = $block->context['postId'] ?? get_the_ID();

if ( ! $post_id ) {
	return;
}

// _work_hero_bg is a JSON string:
// { image_id, position_x, position_y, size, repeat, attachment }
$raw = get_post_meta( $post_id, '_work_hero_bg', true );
$bg  = $raw ? json_decode( $raw, true ) : null;
$bg  = is_array( $bg ) ? $bg : array();

$image_id = ! empty( $bg['image_id'] ) ? absint( $bg['image_id'] ) : 0;
$bg_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

$styles = array();

if ( $bg_url ) {
	$position_x = ! empty( $bg['position_x'] ) ? $bg['position_x'] : 'center';
	$position_y = ! empty( $bg['position_y'] ) ? $bg['position_y'] : 'center';

	$styles[] = 'background-image:url(' . esc_url( $bg_url ) . ')';
	$styles[] = 'background-position:' . esc_attr( $position_x . ' ' . $position_y );
	$styles[] = 'background-size:' . esc_attr( ! empty( $bg['size'] ) ? $bg['size'] : 'cover' );
	$styles[] = 'background-repeat:' . esc_attr( ! empty( $bg['repeat'] ) ? $bg['repeat'] : 'no-repeat' );
	$styles[] = 'background-attachment:' . esc_attr( ! empty( $bg['attachment'] ) ? $bg['attachment'] : 'scroll' );
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'work-hero wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained',
		'style' => implode( ';', $styles ),
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
  <div class="work-hero__inner alignwide">
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- Inner blocks, already rendered/escaped by core. ?>
  </div>
</div>