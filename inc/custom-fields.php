<?php
/**
 * Series — Custom Fields
 *
 * Registers meta boxes, fields, sanitization, saving, and REST API
 * exposure for the `series` custom post type.
 *
 * Meta keys registered:
 *  - _series_hero_image_id      (int,    attachment ID for the hero image)
 *  - _series_description        (string, WYSIWYG)
 *  - _series_press              (string, WYSIWYG)
 *  - _series_items              (array,  repeater — title, image_id, item_description)
 *
 * @package Series Post Type
 */

defined( 'ABSPATH' ) || exit;


/* ==========================================================================
   1. REGISTER META (post meta + REST API exposure)
   ========================================================================== */

add_action( 'init', 'series_register_meta' );

function series_register_meta(): void {

	$shared = [
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => fn() => current_user_can( 'edit_posts' ),
	];

	// Hero image — stores an attachment ID.
	register_post_meta( 'series', '_series_hero_image_id', array_merge( $shared, [
		'type'              => 'integer',
		'description'       => 'Series hero image attachment ID.',
		'sanitize_callback' => 'absint',
	] ) );

	// Plain WYSIWYG fields stored as post content-style HTML.
	register_post_meta( 'series', '_series_description', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Series description (HTML).',
		'sanitize_callback' => 'series_sanitize_wysiwyg',
	] ) );

	register_post_meta( 'series', '_series_press', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Series press (HTML).',
		'sanitize_callback' => 'series_sanitize_wysiwyg',
	] ) );

	// Repeater stored as a JSON-encoded array.
	register_post_meta( 'series', '_series_items', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Series repeater items (JSON).',
		'sanitize_callback' => 'series_sanitize_items_json',
		// Expose the decoded array shape to the REST API.
		'show_in_rest'      => [
			'schema' => [
				'type'  => 'string',
			],
		],
	] ) );
}


/* ==========================================================================
   2. SANITIZATION HELPERS
   ========================================================================== */

/**
 * Sanitize a WYSIWYG / rich-text value.
 * Allows the same tags WordPress itself permits in post content.
 */
function series_sanitize_wysiwyg( string $value ): string {
	return wp_kses_post( $value );
}

/**
 * Sanitize the repeater JSON string.
 * Each item: { title: string, image_id: int, item_description: string }
 */
function series_sanitize_items_json( string $value ): string {

	$items = json_decode( wp_unslash( $value ), true );

	if ( ! is_array( $items ) ) {
		return '[]';
	}

	$clean = [];

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';

		$clean[] = [
			'title'            => $title,
			'image_id'         => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
			'item_description' => isset( $item['item_description'] ) ? wp_kses_post( $item['item_description'] ) : '',
		];
	}

	return wp_json_encode( $clean );
}


/* ==========================================================================
   3. META BOX — REGISTRATION
   ========================================================================== */

add_action( 'add_meta_boxes', 'series_add_meta_boxes' );

function series_add_meta_boxes(): void {

	add_meta_box(
		'series_fields',
		__( 'Series Fields', 'series-post-type' ),
		'series_meta_box_render',
		'series',
		'normal',
		'high'
	);
}


/* ==========================================================================
   4. META BOX — RENDER
   ========================================================================== */

function series_meta_box_render( WP_Post $post ): void {

	wp_nonce_field( 'series_save_fields', 'series_fields_nonce' );

	$hero_image_id = absint( get_post_meta( $post->ID, '_series_hero_image_id', true ) );
	$description   = get_post_meta( $post->ID, '_series_description', true );
	$press         = get_post_meta( $post->ID, '_series_press',       true );
	$items_json    = get_post_meta( $post->ID, '_series_items',       true );
	$items         = $items_json ? json_decode( $items_json, true ) : [];
	if ( ! is_array( $items ) ) {
		$items = [];
	}

	$hero_image_url = $hero_image_id ? wp_get_attachment_image_url( $hero_image_id, 'medium' ) : '';

	?>
<div class="series-fields-wrap">

  <?php /* ── Hero Image ───────────────────────────────────────────── */ ?>
  <div class="series-field-group">
    <label class="series-label">
      <?php esc_html_e( 'Hero Image', 'series-post-type' ); ?>
    </label>
    <div class="series-hero-image-wrap">
      <div class="series-hero-image-preview" style="<?php echo $hero_image_url ? '' : 'display:none;'; ?>">
        <?php if ( $hero_image_url ) : ?>
        <img src="<?php echo esc_url( $hero_image_url ); ?>" alt="">
        <?php endif; ?>
      </div>
      <input type="hidden" id="series_hero_image_id" name="series_hero_image_id"
        value="<?php echo esc_attr( $hero_image_id ); ?>">
      <button type="button" id="series-hero-image-select" class="button">
        <?php echo $hero_image_id ? esc_html__( 'Change Hero Image', 'series-post-type' ) : esc_html__( 'Select Hero Image', 'series-post-type' ); ?>
      </button>
      <button type="button" id="series-hero-image-remove" class="button-link series-image-remove"
        style="<?php echo $hero_image_id ? '' : 'display:none;'; ?>">
        <?php esc_html_e( 'Remove', 'series-post-type' ); ?>
      </button>
    </div>
  </div>

  <?php /* ── Description ──────────────────────────────────────────── */ ?>
  <div class="series-field-group">
    <label class="series-label" for="series_description">
      <?php esc_html_e( 'Description', 'series-post-type' ); ?>
    </label>
    <?php
			wp_editor(
				$description,
				'series_description',
				[
					'textarea_name' => 'series_description',
					'textarea_rows' => 8,
					'media_buttons' => false,
					'teeny'         => false,
					'tinymce'       => true,
					'quicktags'     => true,
				]
			);
			?>
  </div>

  <?php /* ── Repeater ─────────────────────────────────────────────── */ ?>
  <div class="series-field-group">
    <p class="series-label"><?php esc_html_e( 'Items', 'series-post-type' ); ?></p>

    <div id="series-repeater" class="series-repeater">

      <?php foreach ( $items as $index => $item ) : ?>
      <?php series_repeater_row_html( $index, $item ); ?>
      <?php endforeach; ?>

    </div><!-- #series-repeater -->

    <button type="button" id="series-add-item" class="button series-add-btn">
      <?php esc_html_e( '+ Add Item', 'series-post-type' ); ?>
    </button>

    <input type="hidden" id="series_items_json" name="series_items_json"
      value="<?php echo esc_attr( $items_json ?: '[]' ); ?>">
  </div>

  <?php /* ── Press ────────────────────────────────────────────────── */ ?>
  <div class="series-field-group">
    <label class="series-label" for="series_press">
      <?php esc_html_e( 'Press', 'series-post-type' ); ?>
    </label>
    <?php
			wp_editor(
				$press,
				'series_press',
				[
					'textarea_name' => 'series_press',
					'textarea_rows' => 8,
					'media_buttons' => false,
					'teeny'         => false,
					'tinymce'       => true,
					'quicktags'     => true,
				]
			);
			?>
  </div>

</div><!-- .series-fields-wrap -->

<?php
	// Hidden template row (index = __INDEX__), cloned by JS.
	echo '<script type="text/html" id="series-row-template">';
	series_repeater_row_html( '__INDEX__', [ 'title' => '', 'image_id' => 0, 'item_description' => '' ] );
	echo '</script>';

	series_enqueue_meta_box_assets();
}

/**
 * Output a single repeater row.
 *
 * @param int|string $index  Row index (or '__INDEX__' for the JS template).
 * @param array      $item   { title, image_id, item_description }
 */
function series_repeater_row_html( $index, array $item ): void {

	$title            = isset( $item['title'] )            ? esc_attr( $item['title'] )            : '';
	$image_id         = isset( $item['image_id'] )         ? absint( $item['image_id'] )            : 0;
	$item_description = isset( $item['item_description'] ) ? $item['item_description']              : '';
	$image_url        = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

	$is_template = ( '__INDEX__' === (string) $index );
	$row_id      = $is_template ? 'series-row-__INDEX__' : 'series-row-' . $index;

	?>
<div class="series-repeater-row" id="<?php echo esc_attr( $row_id ); ?>" data-index="<?php echo esc_attr( $index ); ?>">

  <div class="series-row-handle">
    <span class="dashicons dashicons-move"></span>
  </div>

  <div class="series-row-fields">

    <?php /* Title */ ?>
    <div class="series-row-field">
      <label class="series-row-label" for="series-title-<?php echo esc_attr( $index ); ?>">
        <?php esc_html_e( 'Title', 'series-post-type' ); ?>
      </label>
      <input type="text" id="series-title-<?php echo esc_attr( $index ); ?>" class="series-item-title widefat"
        value="<?php echo $title; ?>" placeholder="<?php esc_attr_e( 'Enter title…', 'series-post-type' ); ?>">
    </div>

    <?php /* Image */ ?>
    <div class="series-row-field">
      <label class="series-row-label">
        <?php esc_html_e( 'Image', 'series-post-type' ); ?>
      </label>
      <div class="series-image-wrap">
        <div class="series-image-preview" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
          <?php if ( $image_url ) : ?>
          <img src="<?php echo esc_url( $image_url ); ?>" alt="">
          <?php endif; ?>
        </div>
        <input type="hidden" class="series-item-image-id" value="<?php echo esc_attr( $image_id ); ?>">
        <button type="button" class="button series-image-select">
          <?php echo $image_id ? esc_html__( 'Change Image', 'series-post-type' ) : esc_html__( 'Select Image', 'series-post-type' ); ?>
        </button>
        <button type="button" class="button-link series-image-remove"
          style="<?php echo $image_id ? '' : 'display:none;'; ?>">
          <?php esc_html_e( 'Remove', 'series-post-type' ); ?>
        </button>
      </div>
    </div>

    <?php /* Item Description — plain textarea; JS upgrades it to TinyMCE */ ?>
    <div class="series-row-field">
      <label class="series-row-label" for="series-item-desc-<?php echo esc_attr( $index ); ?>">
        <?php esc_html_e( 'Item Description', 'series-post-type' ); ?>
      </label>
      <textarea id="series-item-desc-<?php echo esc_attr( $index ); ?>" class="series-item-item-description widefat"
        rows="5"><?php echo $is_template ? '' : wp_kses_post( $item_description ); ?></textarea>
    </div>

  </div><!-- .series-row-fields -->

  <div class="series-row-actions">
    <button type="button" class="button-link series-remove-row"
      aria-label="<?php esc_attr_e( 'Remove item', 'series-post-type' ); ?>">
      <span class="dashicons dashicons-no-alt"></span>
    </button>
  </div>

</div><!-- .series-repeater-row -->
<?php
}


/* ==========================================================================
   5. ENQUEUE META BOX ASSETS (inline — no extra files needed)
   ========================================================================== */

function series_enqueue_meta_box_assets(): void {

	// Media uploader.
	wp_enqueue_media();

	// Dashicons are already enqueued in the admin; listed for clarity.
	wp_enqueue_style( 'dashicons' );

	// ── Inline CSS ────────────────────────────────────────────────────────
	$css = '
		.series-fields-wrap { max-width: 960px; }

		.series-field-group {
			margin-bottom: 28px;
			padding-bottom: 24px;
			border-bottom: 1px solid #dcdcde;
		}
		.series-field-group:last-child { border-bottom: none; }

		.series-label {
			display: block;
			font-weight: 600;
			margin-bottom: 8px;
			font-size: 13px;
			color: #1e1e1e;
		}

		/* Repeater */
		.series-repeater { margin-bottom: 12px; }

		.series-repeater-row {
			display: flex;
			gap: 0;
			align-items: flex-start;
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			margin-bottom: 12px;
			position: relative;
		}

		.series-row-handle {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 12px 8px;
			cursor: grab;
			color: #8c8f94;
			flex-shrink: 0;
		}
		.series-row-handle:active { cursor: grabbing; }

		.series-row-fields {
			flex: 1;
			padding: 14px 12px;
			min-width: 0;
		}

		.series-row-field { margin-bottom: 14px; }
		.series-row-field:last-child { margin-bottom: 0; }

		.series-row-label {
			display: block;
			font-size: 12px;
			font-weight: 600;
			color: #3c434a;
			margin-bottom: 5px;
		}
		.series-row-label .required {
			color: #d63638;
			margin-left: 2px;
		}

		.series-row-actions {
			padding: 12px 10px;
			flex-shrink: 0;
		}
		.series-remove-row {
			color: #d63638 !important;
			text-decoration: none !important;
			line-height: 1;
		}
		.series-remove-row:hover { color: #b32d2e !important; }

		/* Hero image */
		.series-hero-image-wrap { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
		.series-hero-image-preview img {
			display: block;
			max-width: 300px;
			max-height: 200px;
			border-radius: 3px;
			border: 1px solid #dcdcde;
		}

		/* Repeater image preview */
		.series-image-preview {
			margin-bottom: 8px;
		}
		.series-image-preview img {
			display: block;
			max-width: 120px;
			max-height: 80px;
			border-radius: 3px;
			border: 1px solid #dcdcde;
		}
		.series-image-remove { margin-left: 6px; color: #d63638 !important; }
		.series-image-remove:hover { color: #b32d2e !important; }

		.series-add-btn { margin-top: 4px; }
	';

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<style>' . $css . '</style>';

	// ── Inline JS ─────────────────────────────────────────────────────────
	?>
<script>
(function($) {
  'use strict';

  const repeater = $('#series-repeater');
  const jsonInput = $('#series_items_json');
  const template = $('#series-row-template').html();
  let rowIndex = repeater.children('.series-repeater-row').length;

  /* ── helpers ─────────────────────────────────────────────────── */

  /**
   * Collect all repeater data into the hidden JSON input.
   * Called before save and on every meaningful change.
   */
  function syncJSON() {
    const items = [];

    repeater.children('.series-repeater-row').each(function() {
      const $row = $(this);
      const edId = getEditorId($row);
      let desc = '';

      if (edId && window.tinymce && tinymce.get(edId)) {
        desc = tinymce.get(edId).getContent();
      } else {
        desc = $row.find('.series-item-item-description').val();
      }

      items.push({
        title: $row.find('.series-item-title').val().trim(),
        image_id: parseInt($row.find('.series-item-image-id').val(), 10) || 0,
        item_description: desc,
      });
    });

    jsonInput.val(JSON.stringify(items));
  }

  /** Return the textarea ID used by TinyMCE for a row. */
  function getEditorId($row) {
    return $row.find('.series-item-item-description').attr('id');
  }

  /**
   * Initialise a standalone TinyMCE instance directly on a textarea.
   * Bypasses wp.editor.initialize(), which requires the post type to
   * support 'editor'. Calls tinymce.init() directly instead, which works
   * independently of the classic editor bootstrap.
   */
  function initEditor($textarea) {
    const id = $textarea.attr('id');
    if (!id || !window.tinymce) return;

    tinymce.init({
      selector: '#' + id,
      skin: 'wordpress',
      skin_url: '<?php echo esc_js( includes_url( 'js/tinymce/skins/wordpress' ) ); ?>',
      plugins: 'charmap hr lists paste tabfocus fullscreen wplink',
      toolbar: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | fullscreen',
      menubar: false,
      statusbar: false,
      resize: true,
      min_height: 200,
      wpautop: true,
      setup: function(editor) {
        editor.on('input keyup change NodeChange', syncJSON);
      },
    });
  }

  /** Remove the TinyMCE instance for a row before destroying the DOM node. */
  function destroyEditor($row) {
    const id = getEditorId($row);
    if (id && window.tinymce && tinymce.get(id)) {
      tinymce.get(id).remove();
    }
  }

  /* ── add row ─────────────────────────────────────────────────── */

  $('#series-add-item').on('click', function() {
    const html = template.replace(/__INDEX__/g, rowIndex);
    const $row = $(html);
    repeater.append($row);

    // Re-assign stable ID to the new textarea before init.
    const $ta = $row.find('.series-item-item-description');
    $ta.attr('id', 'series-item-desc-' + rowIndex);

    initEditor($ta);
    rowIndex++;
  });

  /* ── remove row ──────────────────────────────────────────────── */

  repeater.on('click', '.series-remove-row', function() {
    const $row = $(this).closest('.series-repeater-row');
    destroyEditor($row);
    $row.remove();
    syncJSON();
  });

  /* ── hero image picker ───────────────────────────────────────── */

  $('#series-hero-image-select').on('click', function() {
    const frame = wp.media({
      title: '<?php echo esc_js( __( 'Select or Upload Hero Image', 'series-post-type' ) ); ?>',
      button: {
        text: '<?php echo esc_js( __( 'Use this image', 'series-post-type' ) ); ?>'
      },
      library: {
        type: 'image'
      },
      multiple: false,
    });

    frame.on('select', function() {
      const attachment = frame.state().get('selection').first().toJSON();
      const previewUrl = attachment.sizes && attachment.sizes.medium ?
        attachment.sizes.medium.url :
        attachment.url;

      $('#series_hero_image_id').val(attachment.id);
      $('.series-hero-image-preview').html('<img src="' + previewUrl + '" alt="">').show();
      $('#series-hero-image-select').text(
        '<?php echo esc_js( __( 'Change Hero Image', 'series-post-type' ) ); ?>');
      $('#series-hero-image-remove').show();
    });

    frame.open();
  });

  $('#series-hero-image-remove').on('click', function() {
    $('#series_hero_image_id').val(0);
    $('.series-hero-image-preview').hide().empty();
    $('#series-hero-image-select').text('<?php echo esc_js( __( 'Select Hero Image', 'series-post-type' ) ); ?>');
    $(this).hide();
  });

  /* ── repeater image picker ───────────────────────────────────── */

  repeater.on('click', '.series-image-select', function() {
    const $btn = $(this);
    const $row = $btn.closest('.series-repeater-row');

    const frame = wp.media({
      title: '<?php echo esc_js( __( 'Select or Upload Image', 'series-post-type' ) ); ?>',
      button: {
        text: '<?php echo esc_js( __( 'Use this image', 'series-post-type' ) ); ?>'
      },
      library: {
        type: 'image'
      },
      multiple: false,
    });

    frame.on('select', function() {
      const attachment = frame.state().get('selection').first().toJSON();
      const thumbUrl = attachment.sizes && attachment.sizes.thumbnail ?
        attachment.sizes.thumbnail.url :
        attachment.url;

      $row.find('.series-item-image-id').val(attachment.id);
      $row.find('.series-image-preview').html('<img src="' + thumbUrl + '" alt="">').show();
      $row.find('.series-image-select').text(
        '<?php echo esc_js( __( 'Change Image', 'series-post-type' ) ); ?>');
      $row.find('.series-image-remove').show();
      syncJSON();
    });

    frame.open();
  });

  repeater.on('click', '.series-image-remove', function() {
    const $row = $(this).closest('.series-repeater-row');
    $row.find('.series-item-image-id').val(0);
    $row.find('.series-image-preview').hide().empty();
    $row.find('.series-image-select').text('<?php echo esc_js( __( 'Select Image', 'series-post-type' ) ); ?>');
    $(this).hide();
    syncJSON();
  });

  /* ── live sync on text changes ───────────────────────────────── */

  repeater.on('input change', '.series-item-title', syncJSON);

  /* ── sync before WP saves ────────────────────────────────────── */

  $('#post').on('submit', syncJSON);

  /* ── init existing rows on page load ─────────────────────────── */

  repeater.children('.series-repeater-row').each(function() {
    const $ta = $(this).find('.series-item-item-description');
    if ($ta.length) {
      initEditor($ta);
    }
  });

})(jQuery);
</script>
<?php
}


/* ==========================================================================
   6. SAVE META
   ========================================================================== */

add_action( 'save_post_series', 'series_save_meta', 10, 2 );

function series_save_meta( int $post_id, WP_Post $post ): void {

	// ── Guards ────────────────────────────────────────────────────────────

	// Verify nonce.
	$nonce = isset( $_POST['series_fields_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['series_fields_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'series_save_fields' ) ) {
		return;
	}

	// Skip autosaves and revisions.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Permission check.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// ── Hero Image ────────────────────────────────────────────────────────

	if ( isset( $_POST['series_hero_image_id'] ) ) {
		update_post_meta(
			$post_id,
			'_series_hero_image_id',
			absint( $_POST['series_hero_image_id'] )
		);
	}

	// ── Description ───────────────────────────────────────────────────────

	if ( isset( $_POST['series_description'] ) ) {
		update_post_meta(
			$post_id,
			'_series_description',
			series_sanitize_wysiwyg( wp_unslash( $_POST['series_description'] ) )
		);
	}

	// ── Press ─────────────────────────────────────────────────────────────

	if ( isset( $_POST['series_press'] ) ) {
		update_post_meta(
			$post_id,
			'_series_press',
			series_sanitize_wysiwyg( wp_unslash( $_POST['series_press'] ) )
		);
	}

	// ── Repeater items ────────────────────────────────────────────────────

	if ( isset( $_POST['series_items_json'] ) ) {
		update_post_meta(
			$post_id,
			'_series_items',
			series_sanitize_items_json( wp_unslash( $_POST['series_items_json'] ) )
		);
	}
}