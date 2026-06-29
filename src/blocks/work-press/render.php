<?php
/**
 * Render: work/work-statement
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance (provides context).
 */

$post_id = $block->context['postId'] ?? get_the_ID();

if ( ! $post_id ) {
	return;
}

$press = get_post_meta( $post_id, '_work_press', true );

if ( ! $press ) {
	return;
}

// Replicate WordPress's standard WYSIWYG output formatting.
$press = wptexturize( $press );
$press = wpautop( $press );
$press = do_shortcode( $press );

$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => 'work-press' )
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
  <hr>
  <h2>Press</h2>
  <?php echo wp_kses_post( $press ); ?>
</div>