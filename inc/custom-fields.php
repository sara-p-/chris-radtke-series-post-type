<?php
/**
 * Work — Custom Fields
 *
 * Registers meta boxes, fields, sanitization, saving, and REST API
 * exposure for the `work` custom post type.
 *
 * Meta keys registered:
 *  - _work_hero_bg            (string, JSON — image_id, position_x, position_y, size, repeat, attachment)
 *  - _work_years              (string, plain text year/s)
 *  - _work_description        (string, WYSIWYG)
 *  - _work_press              (string, WYSIWYG)
 *  - _work_items              (array,  repeater — title, item_years, image_id, item_description)
 *
 * @package Work Post Type
 */

defined( 'ABSPATH' ) || exit;


/* ==========================================================================
   1. REGISTER META (post meta + REST API exposure)
   ========================================================================== */

add_action( 'init', 'work_register_meta' );

function work_register_meta(): void {

	$shared = [
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => fn() => current_user_can( 'edit_posts' ),
	];

	// Hero background — stores image ID + CSS background settings as JSON.
	register_post_meta( 'work', '_work_hero_bg', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Work hero background image settings (JSON).',
		'sanitize_callback' => 'work_sanitize_hero_bg_json',
		'show_in_rest'      => [
			'schema' => [
				'type' => 'string',
			],
		],
	] ) );

	// Year/s — plain text field.
	register_post_meta( 'work', '_work_years', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Work year/s.',
		'sanitize_callback' => 'sanitize_text_field',
	] ) );

	// Plain WYSIWYG fields stored as post content-style HTML.
	register_post_meta( 'work', '_work_description', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Work description (HTML).',
		'sanitize_callback' => 'work_sanitize_wysiwyg',
	] ) );

	register_post_meta( 'work', '_work_press', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Work press (HTML).',
		'sanitize_callback' => 'work_sanitize_wysiwyg',
	] ) );

	// Repeater stored as a JSON-encoded array.
	register_post_meta( 'work', '_work_items', array_merge( $shared, [
		'type'              => 'string',
		'description'       => 'Work repeater items (JSON).',
		'sanitize_callback' => 'work_sanitize_items_json',
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
function work_sanitize_wysiwyg( string $value ): string {
	return wp_kses_post( $value );
}

/**
 * Sanitize the hero background JSON string.
 * Shape: { image_id: int, position_x: string, position_y: string,
 *           size: string, repeat: string, attachment: string }
 */
function work_sanitize_hero_bg_json( string $value ): string {

	$data = json_decode( wp_unslash( $value ), true );

	if ( ! is_array( $data ) ) {
		return work_hero_bg_defaults_json();
	}

	$allowed_position_x  = [ 'left', 'center', 'right' ];
	$allowed_position_y  = [ 'top', 'center', 'bottom' ];
	$allowed_size        = [ 'auto', 'cover', 'contain' ];
	$allowed_repeat      = [ 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ];
	$allowed_attachment  = [ 'scroll', 'fixed' ];

	$clean = [
		'image_id'   => isset( $data['image_id'] )   ? absint( $data['image_id'] )   : 0,
		'position_x' => ( isset( $data['position_x'] ) && in_array( $data['position_x'], $allowed_position_x, true ) )
		                ? $data['position_x'] : 'center',
		'position_y' => ( isset( $data['position_y'] ) && in_array( $data['position_y'], $allowed_position_y, true ) )
		                ? $data['position_y'] : 'center',
		'size'       => ( isset( $data['size'] )       && in_array( $data['size'],       $allowed_size,       true ) )
		                ? $data['size']       : 'cover',
		'repeat'     => ( isset( $data['repeat'] )     && in_array( $data['repeat'],     $allowed_repeat,     true ) )
		                ? $data['repeat']     : 'no-repeat',
		'attachment' => ( isset( $data['attachment'] ) && in_array( $data['attachment'], $allowed_attachment, true ) )
		                ? $data['attachment'] : 'scroll',
	];

	return wp_json_encode( $clean );
}

/**
 * Return a JSON-encoded string of the hero background defaults.
 */
function work_hero_bg_defaults_json(): string {
	return wp_json_encode( [
		'image_id'   => 0,
		'position_x' => 'center',
		'position_y' => 'center',
		'size'       => 'cover',
		'repeat'     => 'no-repeat',
		'attachment' => 'scroll',
	] );
}

/**
 * Sanitize the repeater JSON string.
 * Each item: { title: string, item_years: string, image_id: int, item_description: string }
 */
function work_sanitize_items_json( string $value ): string {

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
			'item_years'       => isset( $item['item_years'] ) ? sanitize_text_field( $item['item_years'] ) : '',
			'image_id'         => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
			'item_description' => isset( $item['item_description'] ) ? wp_kses_post( $item['item_description'] ) : '',
		];
	}

	return wp_json_encode( $clean );
}


/* ==========================================================================
   3. META BOX — REGISTRATION
   ========================================================================== */

add_action( 'add_meta_boxes', 'work_add_meta_boxes' );

function work_add_meta_boxes(): void {

	add_meta_box(
		'work_fields',
		__( 'Work Fields', 'work-post-type' ),
		'work_meta_box_render',
		'work',
		'normal',
		'high'
	);
}


/* ==========================================================================
   4. META BOX — RENDER
   ========================================================================== */

function work_meta_box_render( WP_Post $post ): void {

	wp_nonce_field( 'work_save_fields', 'work_fields_nonce' );

	// Hero background.
	$hero_bg_json = get_post_meta( $post->ID, '_work_hero_bg', true );
	$hero_bg      = $hero_bg_json ? json_decode( $hero_bg_json, true ) : [];
	if ( ! is_array( $hero_bg ) ) {
		$hero_bg = [];
	}
	$hero_image_id  = isset( $hero_bg['image_id'] )   ? absint( $hero_bg['image_id'] )   : 0;
	$hero_pos_x     = isset( $hero_bg['position_x'] ) ? $hero_bg['position_x']           : 'center';
	$hero_pos_y     = isset( $hero_bg['position_y'] ) ? $hero_bg['position_y']           : 'center';
	$hero_size      = isset( $hero_bg['size'] )       ? $hero_bg['size']                 : 'cover';
	$hero_repeat    = isset( $hero_bg['repeat'] )     ? $hero_bg['repeat']               : 'no-repeat';
	$hero_attach    = isset( $hero_bg['attachment'] ) ? $hero_bg['attachment']           : 'scroll';
	$hero_image_url = $hero_image_id ? wp_get_attachment_image_url( $hero_image_id, 'medium' ) : '';

	$years         = get_post_meta( $post->ID, '_work_years', true );
	$description   = get_post_meta( $post->ID, '_work_description', true );
	$press         = get_post_meta( $post->ID, '_work_press',       true );
	$items_json    = get_post_meta( $post->ID, '_work_items',       true );
	$items         = $items_json ? json_decode( $items_json, true ) : [];
	if ( ! is_array( $items ) ) {
		$items = [];
	}

	?>
<div class="work-fields-wrap">

  <?php /* ── Hero Background Image ─────────────────────────────── */ ?>
  <div class="work-field-group">
    <label class="work-label">
      <?php esc_html_e( 'Hero Background Image', 'work-post-type' ); ?>
    </label>

    <?php /* Hidden JSON store for all hero-bg values */ ?>
    <input type="hidden" id="work_hero_bg_json" name="work_hero_bg_json"
      value="<?php echo esc_attr( $hero_bg_json ?: work_hero_bg_defaults_json() ); ?>">

    <div class="work-hero-bg-wrap">

      <?php /* Image picker */ ?>
      <div class="work-hero-image-picker">
        <div class="work-hero-image-preview" style="<?php echo $hero_image_url ? '' : 'display:none;'; ?>">
          <?php if ( $hero_image_url ) : ?>
          <img src="<?php echo esc_url( $hero_image_url ); ?>" alt="">
          <?php endif; ?>
        </div>
        <div class="work-hero-image-actions">
          <button type="button" id="work-hero-image-select" class="button">
            <?php echo $hero_image_id
              ? esc_html__( 'Change Image', 'work-post-type' )
              : esc_html__( 'Select Image', 'work-post-type' ); ?>
          </button>
          <button type="button" id="work-hero-image-remove" class="button-link work-image-remove"
            style="<?php echo $hero_image_id ? '' : 'display:none;'; ?>">
            <?php esc_html_e( 'Remove', 'work-post-type' ); ?>
          </button>
        </div>
      </div><!-- .work-hero-image-picker -->

      <?php /* Background settings — only shown when an image is selected */ ?>
      <div id="work-hero-bg-settings" class="work-hero-bg-settings"
        style="<?php echo $hero_image_id ? '' : 'display:none;'; ?>">

        <div class="work-bg-settings-grid">

          <?php /* Position X */ ?>
          <div class="work-bg-setting">
            <label class="work-bg-setting-label" for="work_hero_pos_x">
              <?php esc_html_e( 'Horizontal position', 'work-post-type' ); ?>
            </label>
            <div class="work-bg-btn-group" data-setting="position_x">
              <?php
              $px_options = [
                'left'   => __( 'Left',   'work-post-type' ),
                'center' => __( 'Center', 'work-post-type' ),
                'right'  => __( 'Right',  'work-post-type' ),
              ];
              foreach ( $px_options as $val => $label ) :
                $active = ( $hero_pos_x === $val ) ? ' is-active' : '';
                ?>
              <button type="button" class="work-bg-btn<?php echo esc_attr( $active ); ?>"
                data-value="<?php echo esc_attr( $val ); ?>">
                <?php echo esc_html( $label ); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <?php /* Position Y */ ?>
          <div class="work-bg-setting">
            <label class="work-bg-setting-label" for="work_hero_pos_y">
              <?php esc_html_e( 'Vertical position', 'work-post-type' ); ?>
            </label>
            <div class="work-bg-btn-group" data-setting="position_y">
              <?php
              $py_options = [
                'top'    => __( 'Top',    'work-post-type' ),
                'center' => __( 'Center', 'work-post-type' ),
                'bottom' => __( 'Bottom', 'work-post-type' ),
              ];
              foreach ( $py_options as $val => $label ) :
                $active = ( $hero_pos_y === $val ) ? ' is-active' : '';
                ?>
              <button type="button" class="work-bg-btn<?php echo esc_attr( $active ); ?>"
                data-value="<?php echo esc_attr( $val ); ?>">
                <?php echo esc_html( $label ); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <?php /* Size */ ?>
          <div class="work-bg-setting">
            <label class="work-bg-setting-label" for="work_hero_size">
              <?php esc_html_e( 'Size', 'work-post-type' ); ?>
            </label>
            <div class="work-bg-btn-group" data-setting="size">
              <?php
              $size_options = [
                'cover'   => __( 'Cover',   'work-post-type' ),
                'contain' => __( 'Contain', 'work-post-type' ),
                'auto'    => __( 'Auto',    'work-post-type' ),
              ];
              foreach ( $size_options as $val => $label ) :
                $active = ( $hero_size === $val ) ? ' is-active' : '';
                ?>
              <button type="button" class="work-bg-btn<?php echo esc_attr( $active ); ?>"
                data-value="<?php echo esc_attr( $val ); ?>">
                <?php echo esc_html( $label ); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <?php /* Repeat */ ?>
          <div class="work-bg-setting">
            <label class="work-bg-setting-label" for="work_hero_repeat">
              <?php esc_html_e( 'Repeat', 'work-post-type' ); ?>
            </label>
            <select id="work_hero_repeat" class="work-bg-select" data-setting="repeat">
              <?php
              $repeat_options = [
                'no-repeat' => __( 'No Repeat', 'work-post-type' ),
                'repeat'    => __( 'Tile',      'work-post-type' ),
                'repeat-x'  => __( 'Tile Horizontally', 'work-post-type' ),
                'repeat-y'  => __( 'Tile Vertically',   'work-post-type' ),
              ];
              foreach ( $repeat_options as $val => $label ) :
                ?>
              <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $hero_repeat, $val ); ?>>
                <?php echo esc_html( $label ); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php /* Attachment */ ?>
          <div class="work-bg-setting">
            <label class="work-bg-setting-label" for="work_hero_attachment">
              <?php esc_html_e( 'Scroll behavior', 'work-post-type' ); ?>
            </label>
            <div class="work-bg-btn-group" data-setting="attachment">
              <?php
              $attach_options = [
                'scroll' => __( 'Scroll', 'work-post-type' ),
                'fixed'  => __( 'Fixed (parallax)', 'work-post-type' ),
              ];
              foreach ( $attach_options as $val => $label ) :
                $active = ( $hero_attach === $val ) ? ' is-active' : '';
                ?>
              <button type="button" class="work-bg-btn<?php echo esc_attr( $active ); ?>"
                data-value="<?php echo esc_attr( $val ); ?>">
                <?php echo esc_html( $label ); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

        </div><!-- .work-bg-settings-grid -->

        <?php /* Live preview */ ?>
        <div class="work-bg-preview-wrap">
          <p class="work-bg-setting-label"><?php esc_html_e( 'Preview', 'work-post-type' ); ?></p>
          <div id="work-hero-bg-preview" class="work-hero-bg-preview" <?php if ( $hero_image_url ) : ?> style="background-image:url('<?php echo esc_url( $hero_image_url ); ?>');
                   background-position:<?php echo esc_attr( $hero_pos_x . ' ' . $hero_pos_y ); ?>;
                   background-size:<?php echo esc_attr( $hero_size ); ?>;
                   background-repeat:<?php echo esc_attr( $hero_repeat ); ?>;
                   background-attachment:<?php echo esc_attr( $hero_attach ); ?>;" <?php endif; ?>>
            <span class="work-bg-preview-label">
              <?php esc_html_e( 'Background preview', 'work-post-type' ); ?>
            </span>
          </div>
        </div>

      </div><!-- #work-hero-bg-settings -->

    </div><!-- .work-hero-bg-wrap -->
  </div>

  <?php /* ── Year/s ───────────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <label class="work-label" for="work_years">
      <?php esc_html_e( 'Year/s', 'work-post-type' ); ?>
    </label>
    <input type="text" id="work_years" name="work_years" class="widefat" value="<?php echo esc_attr( $years ); ?>"
      placeholder="<?php esc_attr_e( 'e.g. 2023 or 2022–2024', 'work-post-type' ); ?>">
  </div>

  <?php /* ── Description ──────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <label class="work-label" for="work_description">
      <?php esc_html_e( 'Description', 'work-post-type' ); ?>
    </label>
    <?php
		wp_editor(
			$description,
			'work_description',
			[
				'textarea_name' => 'work_description',
				'textarea_rows' => 8,
				'media_buttons' => false,
				'teeny'         => false,
				'tinymce'       => [
					'toolbar1' => 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_adv',
					'toolbar2' => 'strikethrough hr forecolor | pastetext removeformat | charmap | outdent indent | undo redo | wp_help',
				],
				'quicktags'     => true,
			]
		);
		?>
  </div>

  <?php /* ── Repeater ─────────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <p class="work-label"><?php esc_html_e( 'Items', 'work-post-type' ); ?></p>

    <div id="work-repeater" class="work-repeater">

      <?php foreach ( $items as $index => $item ) : ?>
      <?php work_repeater_row_html( $index, $item ); ?>
      <?php endforeach; ?>

    </div><!-- #work-repeater -->

    <button type="button" id="work-add-item" class="button work-add-btn">
      <?php esc_html_e( '+ Add Item', 'work-post-type' ); ?>
    </button>

    <input type="hidden" id="work_items_json" name="work_items_json"
      value="<?php echo esc_attr( $items_json ?: '[]' ); ?>">
  </div>

  <?php /* ── Press ────────────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <label class="work-label" for="work_press">
      <?php esc_html_e( 'Press', 'work-post-type' ); ?>
    </label>
    <?php
		wp_editor(
			$press,
			'work_press',
			[
				'textarea_name' => 'work_press',
				'textarea_rows' => 8,
				'media_buttons' => false,
				'teeny'         => false,
				'tinymce'       => [
					'toolbar1' => 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_adv',
					'toolbar2' => 'strikethrough hr forecolor | pastetext removeformat | charmap | outdent indent | undo redo | wp_help',
				],
				'quicktags'     => true,
			]
		);
		?>
  </div>

</div><!-- .work-fields-wrap -->

<?php
	// Hidden template row (index = __INDEX__), cloned by JS.
	echo '<script type="text/html" id="work-row-template">';
	work_repeater_row_html( '__INDEX__', [ 'title' => '', 'item_years' => '', 'image_id' => 0, 'item_description' => '' ] );
	echo '</script>';

	work_enqueue_meta_box_assets();
}

/**
 * Output a single repeater row.
 *
 * @param int|string $index  Row index (or '__INDEX__' for the JS template).
 * @param array      $item   { title, item_years, image_id, item_description }
 */
function work_repeater_row_html( $index, array $item ): void {

	$title            = isset( $item['title'] )            ? esc_attr( $item['title'] )            : '';
	$item_years       = isset( $item['item_years'] )       ? esc_attr( $item['item_years'] )       : '';
	$image_id         = isset( $item['image_id'] )         ? absint( $item['image_id'] )            : 0;
	$item_description = isset( $item['item_description'] ) ? $item['item_description']              : '';
	$image_url        = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

	$is_template = ( '__INDEX__' === (string) $index );
	$row_id      = $is_template ? 'work-row-__INDEX__' : 'work-row-' . $index;

	?>
<div class="work-repeater-row" id="<?php echo esc_attr( $row_id ); ?>" data-index="<?php echo esc_attr( $index ); ?>">

  <div class="work-row-handle">
    <span class="dashicons dashicons-move"></span>
  </div>

  <div class="work-row-fields">

    <?php /* Title */ ?>
    <div class="work-row-field">
      <label class="work-row-label" for="work-title-<?php echo esc_attr( $index ); ?>">
        <?php esc_html_e( 'Title', 'work-post-type' ); ?>
      </label>
      <input type="text" id="work-title-<?php echo esc_attr( $index ); ?>" class="work-item-title widefat"
        value="<?php echo $title; ?>" placeholder="<?php esc_attr_e( 'Enter title…', 'work-post-type' ); ?>">
    </div>

    <?php /* Item Year/s */ ?>
    <div class="work-row-field">
      <label class="work-row-label" for="work-item-years-<?php echo esc_attr( $index ); ?>">
        <?php esc_html_e( 'Item Year/s', 'work-post-type' ); ?>
      </label>
      <input type="text" id="work-item-years-<?php echo esc_attr( $index ); ?>" class="work-item-item-years widefat"
        value="<?php echo $item_years; ?>"
        placeholder="<?php esc_attr_e( 'e.g. 2023 or 2022–2024', 'work-post-type' ); ?>">
    </div>

    <?php /* Image */ ?>
    <div class="work-row-field">
      <label class="work-row-label">
        <?php esc_html_e( 'Image', 'work-post-type' ); ?>
      </label>
      <div class="work-image-wrap">
        <div class="work-image-preview" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
          <?php if ( $image_url ) : ?>
          <img src="<?php echo esc_url( $image_url ); ?>" alt="">
          <?php endif; ?>
        </div>
        <input type="hidden" class="work-item-image-id" value="<?php echo esc_attr( $image_id ); ?>">
        <button type="button" class="button work-image-select">
          <?php echo $image_id ? esc_html__( 'Change Image', 'work-post-type' ) : esc_html__( 'Select Image', 'work-post-type' ); ?>
        </button>
        <button type="button" class="button-link work-image-remove"
          style="<?php echo $image_id ? '' : 'display:none;'; ?>">
          <?php esc_html_e( 'Remove', 'work-post-type' ); ?>
        </button>
      </div>
    </div>

    <?php /* Item Description — plain textarea; JS upgrades it to TinyMCE */ ?>
    <div class="work-row-field">
      <label class="work-row-label" for="work-item-desc-<?php echo esc_attr( $index ); ?>">
        <?php esc_html_e( 'Item Description', 'work-post-type' ); ?>
      </label>
      <textarea id="work-item-desc-<?php echo esc_attr( $index ); ?>" class="work-item-item-description widefat"
        rows="5"><?php echo $is_template ? '' : esc_textarea( $item_description ); ?></textarea>
    </div>

  </div><!-- .work-row-fields -->

  <div class="work-row-actions">
    <button type="button" class="button-link work-remove-row"
      aria-label="<?php esc_attr_e( 'Remove item', 'work-post-type' ); ?>">
      <span class="dashicons dashicons-no-alt"></span>
    </button>
  </div>

</div><!-- .work-repeater-row -->
<?php
}


/* ==========================================================================
   5. ENQUEUE META BOX ASSETS
   ========================================================================== */

add_action( 'admin_enqueue_scripts', 'work_enqueue_meta_box_scripts' );

function work_enqueue_meta_box_scripts( string $hook ): void {

	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'work' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_editor();
	wp_enqueue_style( 'dashicons' );
}

function work_enqueue_meta_box_assets(): void {

	// ── Inline CSS ────────────────────────────────────────────────────────
	$css = '
		.work-fields-wrap { max-width: 960px; }

		.work-field-group {
			margin-bottom: 28px;
			padding-bottom: 24px;
			border-bottom: 1px solid #dcdcde;
		}
		.work-field-group:last-child { border-bottom: none; }

		.work-label {
			display: block;
			font-weight: 600;
			margin-bottom: 8px;
			font-size: 13px;
			color: #1e1e1e;
		}

		/* ── Hero background ───────────────────────────────────────── */
		.work-hero-bg-wrap {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}

		.work-hero-image-picker {
			display: flex;
			flex-direction: column;
			gap: 10px;
			align-items: flex-start;
		}
		.work-hero-image-actions {
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.work-hero-image-preview img {
			display: block;
			max-width: 300px;
			max-height: 200px;
			border-radius: 3px;
			border: 1px solid #dcdcde;
		}

		/* Settings panel */
		.work-hero-bg-settings {
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 16px 18px;
		}

		.work-bg-settings-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
			gap: 16px 24px;
			margin-bottom: 20px;
		}

		.work-bg-setting {}

		.work-bg-setting-label {
			display: block;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: .04em;
			color: #50575e;
			margin-bottom: 6px;
		}

		/* Button group (position / size / attachment) */
		.work-bg-btn-group {
			display: inline-flex;
			border: 1px solid #8c8f94;
			border-radius: 3px;
			overflow: hidden;
		}
		.work-bg-btn {
			background: #fff;
			border: none;
			border-right: 1px solid #8c8f94;
			padding: 5px 12px;
			font-size: 12px;
			cursor: pointer;
			color: #2c3338;
			line-height: 1.4;
			transition: background .1s, color .1s;
		}
		.work-bg-btn:last-child { border-right: none; }
		.work-bg-btn:hover { background: #f0f0f1; }
		.work-bg-btn.is-active {
			background: #2271b1;
			color: #fff;
		}

		/* Select (repeat) */
		.work-bg-select {
			max-width: 200px;
			font-size: 13px;
		}

		/* Preview */
		.work-bg-preview-wrap {}

		.work-hero-bg-preview {
			width: 100%;
			max-width: 520px;
			height: 180px;
			border-radius: 3px;
			border: 1px solid #dcdcde;
			display: flex;
			align-items: center;
			justify-content: center;
			background-color: #e0e0e0;
			background-image: none;
			overflow: hidden;
			margin-top: 6px;
		}
		.work-bg-preview-label {
			font-size: 11px;
			color: #8c8f94;
			background: rgba(255,255,255,.75);
			padding: 2px 8px;
			border-radius: 2px;
			pointer-events: none;
		}

		/* ── Repeater ──────────────────────────────────────────────── */
		.work-repeater { margin-bottom: 12px; }

		.work-repeater-row {
			display: flex;
			gap: 0;
			align-items: flex-start;
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			margin-bottom: 12px;
			position: relative;
		}

		.work-row-handle {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 12px 8px;
			cursor: grab;
			color: #8c8f94;
			flex-shrink: 0;
		}
		.work-row-handle:active { cursor: grabbing; }

		.work-row-fields {
			flex: 1;
			padding: 14px 12px;
			min-width: 0;
		}

		.work-row-field { margin-bottom: 14px; }
		.work-row-field:last-child { margin-bottom: 0; }

		.work-row-label {
			display: block;
			font-size: 12px;
			font-weight: 600;
			color: #3c434a;
			margin-bottom: 5px;
		}

		.work-row-actions {
			padding: 12px 10px;
			flex-shrink: 0;
		}
		.work-remove-row {
			color: #d63638 !important;
			text-decoration: none !important;
			line-height: 1;
		}
		.work-remove-row:hover { color: #b32d2e !important; }

		/* Repeater image preview */
		.work-image-preview { margin-bottom: 8px; }
		.work-image-preview img {
			display: block;
			max-width: 120px;
			max-height: 80px;
			border-radius: 3px;
			border: 1px solid #dcdcde;
		}
		.work-image-remove { margin-left: 6px; color: #d63638 !important; }
		.work-image-remove:hover { color: #b32d2e !important; }

		.work-add-btn { margin-top: 4px; }
	';

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<style>' . $css . '</style>';

	// ── Inline JS ─────────────────────────────────────────────────────────
	?>
<script>
(function($) {
  'use strict';

  /* ====================================================================
     HERO BACKGROUND
     All values are kept in a plain JS object (heroBg) and written to
     the hidden #work_hero_bg_json input on every change so a single
     field is posted to PHP.
     ==================================================================== */

  // Read initial state from the hidden input (populated by PHP).
  let heroBg = (function() {
    try {
      return JSON.parse($('#work_hero_bg_json').val()) || {};
    } catch (e) {
      return {};
    }
  })();

  // Defaults match the PHP sanitizer defaults.
  heroBg = Object.assign({
    image_id: 0,
    position_x: 'center',
    position_y: 'center',
    size: 'cover',
    repeat: 'no-repeat',
    attachment: 'scroll',
  }, heroBg);

  /** Persist heroBg state → hidden input + live preview. */
  function syncHeroBg() {
    $('#work_hero_bg_json').val(JSON.stringify(heroBg));
    updateHeroBgPreview();
  }

  /** Reflect current heroBg settings in the preview div. */
  function updateHeroBgPreview() {
    const $preview = $('#work-hero-bg-preview');
    if (!heroBg.image_id) {
      $preview.css('background-image', '');
      return;
    }
    // Use the full-size URL already stored in the img src of the preview.
    const imgSrc = $('.work-hero-image-preview img').attr('src') || '';
    $preview.css({
      'background-image': imgSrc ? 'url(' + imgSrc + ')' : '',
      'background-position': heroBg.position_x + ' ' + heroBg.position_y,
      'background-size': heroBg.size,
      'background-repeat': heroBg.repeat,
      'background-attachment': heroBg.attachment,
    });
  }

  /* ── Image picker ─────────────────────────────────────────────── */

  $('#work-hero-image-select').on('click', function() {
    const frame = wp.media({
      title: '<?php echo esc_js( __( 'Select or Upload Hero Image', 'work-post-type' ) ); ?>',
      button: {
        text: '<?php echo esc_js( __( 'Use this image', 'work-post-type' ) ); ?>'
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

      heroBg.image_id = attachment.id;
      syncHeroBg();

      $('.work-hero-image-preview').html('<img src="' + previewUrl + '" alt="">').show();
      $('#work-hero-image-select').text(
        '<?php echo esc_js( __( 'Change Image', 'work-post-type' ) ); ?>');
      $('#work-hero-image-remove').show();
      $('#work-hero-bg-settings').show();

      // Re-run preview now that the img src is in the DOM.
      updateHeroBgPreview();
    });

    frame.open();
  });

  $('#work-hero-image-remove').on('click', function() {
    heroBg.image_id = 0;
    syncHeroBg();

    $('.work-hero-image-preview').hide().empty();
    $('#work-hero-image-select').text('<?php echo esc_js( __( 'Select Image', 'work-post-type' ) ); ?>');
    $(this).hide();
    $('#work-hero-bg-settings').hide();
  });

  /* ── Button-group settings (position_x / position_y / size / attachment) ── */

  $(document).on('click', '.work-bg-btn-group .work-bg-btn', function() {
    const $btn = $(this);
    const $group = $btn.closest('.work-bg-btn-group');
    const setting = $group.data('setting');
    const value = $btn.data('value');

    $group.find('.work-bg-btn').removeClass('is-active');
    $btn.addClass('is-active');

    heroBg[setting] = value;
    syncHeroBg();
  });

  /* ── Select (repeat) ──────────────────────────────────────────── */

  $(document).on('change', '.work-bg-select', function() {
    const setting = $(this).data('setting');
    heroBg[setting] = $(this).val();
    syncHeroBg();
  });

  /* ── Init preview on page load ───────────────────────────────── */
  updateHeroBgPreview();


  /* ====================================================================
     REPEATER
     ==================================================================== */

  const repeater = $('#work-repeater');
  const jsonInput = $('#work_items_json');
  const template = $('#work-row-template').html();
  let rowIndex = repeater.children('.work-repeater-row').length;

  /* ── helpers ─────────────────────────────────────────────────── */

  function syncJSON() {
    const items = [];

    repeater.children('.work-repeater-row').each(function() {
      const $row = $(this);
      const edId = getEditorId($row);
      let desc = '';

      if (edId && window.tinymce && tinymce.get(edId)) {
        desc = tinymce.get(edId).getContent().replace(/\n/g, '');
      } else {
        desc = $row.find('.work-item-item-description').val();
      }

      items.push({
        title: $row.find('.work-item-title').val().trim(),
        item_years: $row.find('.work-item-item-years').val().trim(),
        image_id: parseInt($row.find('.work-item-image-id').val(), 10) || 0,
        item_description: desc,
      });
    });

    jsonInput.val(JSON.stringify(items));
  }

  function getEditorId($row) {
    return $row.find('.work-item-item-description').attr('id');
  }

  function initEditor($textarea) {
    const id = $textarea.attr('id');
    if (!id || !window.tinymce) return;

    tinymce.init({
      selector: '#' + id,
      skin: 'wordpress',
      skin_url: '<?php echo esc_js( includes_url( 'js/tinymce/skins/wordpress' ) ); ?>',
      plugins: 'charmap hr lists paste tabfocus wplink',
      toolbar: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink',
      menubar: false,
      statusbar: false,
      resize: true,
      min_height: 200,
      entity_encoding: 'raw',
      setup: function(editor) {
        editor.on('input keyup change NodeChange', syncJSON);
      },
    });
  }

  function destroyEditor($row) {
    const id = getEditorId($row);
    if (id && window.tinymce && tinymce.get(id)) {
      tinymce.get(id).remove();
    }
  }

  /* ── add row ─────────────────────────────────────────────────── */

  $('#work-add-item').on('click', function() {
    const html = template.replace(/__INDEX__/g, rowIndex);
    const $row = $(html);
    repeater.append($row);

    const $ta = $row.find('.work-item-item-description');
    $ta.attr('id', 'work-item-desc-' + rowIndex);

    initEditor($ta);
    rowIndex++;
  });

  /* ── remove row ──────────────────────────────────────────────── */

  repeater.on('click', '.work-remove-row', function() {
    const $row = $(this).closest('.work-repeater-row');
    destroyEditor($row);
    $row.remove();
    syncJSON();
  });

  /* ── repeater image picker ───────────────────────────────────── */

  repeater.on('click', '.work-image-select', function() {
    const $btn = $(this);
    const $row = $btn.closest('.work-repeater-row');

    const frame = wp.media({
      title: '<?php echo esc_js( __( 'Select or Upload Image', 'work-post-type' ) ); ?>',
      button: {
        text: '<?php echo esc_js( __( 'Use this image', 'work-post-type' ) ); ?>'
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

      $row.find('.work-item-image-id').val(attachment.id);
      $row.find('.work-image-preview').html('<img src="' + thumbUrl + '" alt="">').show();
      $row.find('.work-image-select').text(
        '<?php echo esc_js( __( 'Change Image', 'work-post-type' ) ); ?>');
      $row.find('.work-image-remove').show();
      syncJSON();
    });

    frame.open();
  });

  repeater.on('click', '.work-image-remove', function() {
    const $row = $(this).closest('.work-repeater-row');
    $row.find('.work-item-image-id').val(0);
    $row.find('.work-image-preview').hide().empty();
    $row.find('.work-image-select').text('<?php echo esc_js( __( 'Select Image', 'work-post-type' ) ); ?>');
    $(this).hide();
    syncJSON();
  });

  /* ── live sync on text changes ───────────────────────────────── */

  repeater.on('input change', '.work-item-title, .work-item-item-years', syncJSON);

  /* ── sync both JSON blobs before WP saves ────────────────────── */

  $('#post').on('submit', function() {
    syncHeroBg();
    syncJSON();
  });

  /* ── init existing repeater rows on page load ─────────────────── */

  (function waitForTinymce() {
    if (window.tinymce) {
      repeater.children('.work-repeater-row').each(function() {
        const $ta = $(this).find('.work-item-item-description');
        if ($ta.length) initEditor($ta);
      });
    } else {
      setTimeout(waitForTinymce, 50);
    }
  })();

})(jQuery);
</script>
<?php
}


/* ==========================================================================
   6. SAVE META
   ========================================================================== */

add_action( 'save_post_work', 'work_save_meta', 10, 2 );

function work_save_meta( int $post_id, WP_Post $post ): void {

	// ── Guards ────────────────────────────────────────────────────────────

	$nonce = isset( $_POST['work_fields_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['work_fields_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'work_save_fields' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// ── Hero Background ───────────────────────────────────────────────────

	if ( isset( $_POST['work_hero_bg_json'] ) ) {
		update_post_meta(
			$post_id,
			'_work_hero_bg',
			work_sanitize_hero_bg_json( wp_unslash( $_POST['work_hero_bg_json'] ) )
		);
	}

	// ── Year/s ────────────────────────────────────────────────────────────

	if ( isset( $_POST['work_years'] ) ) {
		update_post_meta(
			$post_id,
			'_work_years',
			sanitize_text_field( wp_unslash( $_POST['work_years'] ) )
		);
	}

	// ── Description ───────────────────────────────────────────────────────

	if ( isset( $_POST['work_description'] ) ) {
		update_post_meta(
			$post_id,
			'_work_description',
			work_sanitize_wysiwyg( wp_unslash( $_POST['work_description'] ) )
		);
	}

	// ── Press ─────────────────────────────────────────────────────────────

	if ( isset( $_POST['work_press'] ) ) {
		update_post_meta(
			$post_id,
			'_work_press',
			work_sanitize_wysiwyg( wp_unslash( $_POST['work_press'] ) )
		);
	}

	// ── Repeater items ────────────────────────────────────────────────────

	if ( isset( $_POST['work_items_json'] ) ) {
		update_post_meta(
			$post_id,
			'_work_items',
			work_sanitize_items_json( wp_unslash( $_POST['work_items_json'] ) )
		);
	}
}