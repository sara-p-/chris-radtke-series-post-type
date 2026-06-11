<?php
/**
 * Render: work/work-projects
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused — no inner blocks).
 * @var WP_Block $block      Block instance (provides context).
 */

$post_id = $block->context['postId'] ?? get_the_ID();

if ( ! $post_id ) {
	return;
}

$terms = get_the_terms( $post_id, 'projects' );

if ( empty( $terms ) || is_wp_error( $terms ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => 'work-projects' )
);
?>
<ul <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
  <?php foreach ( $terms as $term ) : ?>
  <li class="work-projects__item">
    <h6><?php echo esc_html( $term->name ); ?></h6>
  </li>
  <?php endforeach; ?>
</ul>