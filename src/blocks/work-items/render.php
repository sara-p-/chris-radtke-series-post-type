<?php
/**
 * Render: work/work-items
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance (provides context).
 */

$post_id = $block->context['postId'] ?? get_the_ID();

if ( ! $post_id ) {
	return;
}

// _work_items: array of { title, item_years, image_id, item_description }
$raw   = get_post_meta( $post_id, '_work_items', true );
$items = is_array( $raw ) ? $raw : ( $raw ? json_decode( $raw, true ) : null );

if ( empty( $items ) || ! is_array( $items ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => 'work-items' )
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
  <?php foreach ( $items as $item ) : ?>
  <?php
		if ( ! is_array( $item ) ) {
			continue;
		}

		$title       = $item['title'] ?? '';
		$years       = $item['item_years'] ?? '';
		$image_id    = ! empty( $item['image_id'] ) ? absint( $item['image_id'] ) : 0;
		$description = $item['item_description'] ?? '';

		// Skip entirely empty rows.
		if ( ! $title && ! $years && ! $image_id && ! $description ) {
			continue;
		}

    // Create class for items that don't have a title, year, or description
    $column_class = '';
    if (! $title && ! $years && ! $description) {
      $column_class = 'work-items__column-empty';
    }
    
		?>
  <article class="work-items__item">
    <div class="work-items__header">
      <div class="work-items__title-box  work-items__column <?php echo $column_class; ?>">
        <h2 class="work-items__title">
          <?php if ( $title ) : ?>
          <span><?php echo esc_html( $title ); ?> </span>
          <?php endif; ?>
          <?php if ( $title && $years ) : ?>
          <span class="work-items__line"> | </span>
          <?php endif; ?>
          <?php if ( $years ) : ?>
          <span class="work-items__years"><?php echo esc_html( $years ); ?></span>
          <?php endif; ?>
        </h2>
      </div>
      <?php if ( $description ) : ?>
      <div class="work-items__description work-items__column">
        <?php echo wp_kses_post( wpautop( wptexturize( $description ) ) ); ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ( $image_id ) : ?>
    <?php
				echo wp_get_attachment_image(
					$image_id,
					'large',
					false,
					array( 'class' => 'work-items__image' )
				);
				?>
    <?php endif; ?>


  </article>
  <?php endforeach; ?>
</div>