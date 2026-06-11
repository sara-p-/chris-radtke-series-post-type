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

$description = get_post_meta( $post_id, '_work_description', true );

if ( ! $description ) {
	return;
}

// Replicate WordPress's standard WYSIWYG output formatting.
$description = wptexturize( $description );
$description = wpautop( $description );
$description = do_shortcode( $description );

$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => 'work-statement' )
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
  <?php echo wp_kses_post( $description ); ?>
</div>