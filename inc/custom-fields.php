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
 *  - _work_items              (string, JSON — repeater: title, item_years, image_id, item_description)
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
	// No sanitize_callback — handled explicitly in work_save_meta() so the
	// sanitizer can receive the post ID (used to preserve existing data when
	// corrupt input is detected).
	register_post_meta( 'work', '_work_items', array_merge( $shared, [
		'type'        => 'string',
		'description' => 'Work repeater items (JSON).',
		'show_in_rest' => [
			'schema' => [ 'type' => 'string' ],
		],
	] ) );
}


/* ==========================================================================
   2. SANITIZATION HELPERS
   ========================================================================== */

function work_sanitize_wysiwyg( string $value ): string {
	return wp_kses_post( $value );
}

/**
 * Sanitize the hero background JSON.
 *
 * Expects UNSLASHED input — the caller is responsible for wp_unslash().
 */
function work_sanitize_hero_bg_json( string $value ): string {

	$data = json_decode( $value, true );

	if ( ! is_array( $data ) ) {
		return work_hero_bg_defaults_json();
	}

	$allowed_position_x = [ 'left', 'center', 'right' ];
	$allowed_position_y = [ 'top', 'center', 'bottom' ];
	$allowed_size       = [ 'auto', 'cover', 'contain' ];
	$allowed_repeat     = [ 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ];
	$allowed_attachment = [ 'scroll', 'fixed' ];

	$clean = [
		'image_id'   => isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0,
		'position_x' => ( isset( $data['position_x'] ) && in_array( $data['position_x'], $allowed_position_x, true ) ) ? $data['position_x'] : 'center',
		'position_y' => ( isset( $data['position_y'] ) && in_array( $data['position_y'], $allowed_position_y, true ) ) ? $data['position_y'] : 'center',
		'size'       => ( isset( $data['size'] )       && in_array( $data['size'],       $allowed_size,       true ) ) ? $data['size']       : 'cover',
		'repeat'     => ( isset( $data['repeat'] )     && in_array( $data['repeat'],     $allowed_repeat,     true ) ) ? $data['repeat']     : 'no-repeat',
		'attachment' => ( isset( $data['attachment'] ) && in_array( $data['attachment'], $allowed_attachment, true ) ) ? $data['attachment'] : 'scroll',
	];

	return wp_json_encode( $clean );
}

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
 * Sanitize the repeater items JSON.
 *
 * Expects UNSLASHED input — the caller is responsible for wp_unslash().
 *
 * If $post_id is provided and the input fails to decode, the existing stored
 * value is preserved instead of wiping the field — a corrupt save request
 * should never destroy previously-saved data.
 */
function work_sanitize_items_json( string $value, int $post_id = 0 ): string {

	$items = json_decode( $value, true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $items ) ) {
		if ( $post_id ) {
			$existing = get_post_meta( $post_id, '_work_items', true );
			if ( is_string( $existing ) && '' !== $existing ) {
				return $existing;
			}
		}
		return '[]';
	}

	$clean = [];

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$clean[] = [
			'title'            => isset( $item['title'] )            ? sanitize_text_field( $item['title'] )            : '',
			'item_years'       => isset( $item['item_years'] )       ? sanitize_text_field( $item['item_years'] )       : '',
			'image_id'         => isset( $item['image_id'] )         ? absint( $item['image_id'] )                      : 0,
			'item_description' => isset( $item['item_description'] ) ? wp_kses_post( $item['item_description'] )        : '',
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

	$years      = get_post_meta( $post->ID, '_work_years',       true );
	$description = get_post_meta( $post->ID, '_work_description', true );
	$press      = get_post_meta( $post->ID, '_work_press',       true );
	$items_json = get_post_meta( $post->ID, '_work_items',       true );
	$items      = $items_json ? json_decode( $items_json, true ) : [];
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

    <input type="hidden" id="work_hero_bg_json" name="work_hero_bg_json"
      value="<?php echo esc_attr( $hero_bg_json ?: work_hero_bg_defaults_json() ); ?>">

    <div class="work-hero-bg-wrap">

      <div class="work-hero-image-picker">
        <div class="work-hero-image-preview" style="<?php echo $hero_image_url ? '' : 'display:none;'; ?>">
          <?php if ( $hero_image_url ) : ?>
          <img src="<?php echo esc_url( $hero_image_url ); ?>" alt="">
          <?php endif; ?>
        </div>
        <div class="work-hero-image-actions">
          <button type="button" id="work-hero-image-select" class="button">
            <?php echo $hero_image_id ? esc_html__( 'Change Image', 'work-post-type' ) : esc_html__( 'Select Image', 'work-post-type' ); ?>
          </button>
          <button type="button" id="work-hero-image-remove" class="button-link work-image-remove"
            style="<?php echo $hero_image_id ? '' : 'display:none;'; ?>">
            <?php esc_html_e( 'Remove', 'work-post-type' ); ?>
          </button>
        </div>
      </div>

      <div id="work-hero-bg-settings" class="work-hero-bg-settings"
        style="<?php echo $hero_image_id ? '' : 'display:none;'; ?>">

        <div class="work-bg-settings-grid">

          <div class="work-bg-setting">
            <label class="work-bg-setting-label"><?php esc_html_e( 'Horizontal position', 'work-post-type' ); ?></label>
            <div class="work-bg-btn-group" data-setting="position_x">
              <?php foreach ( [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] as $val => $label ) : ?>
              <button type="button" class="work-bg-btn<?php echo $hero_pos_x === $val ? ' is-active' : ''; ?>"
                data-value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="work-bg-setting">
            <label class="work-bg-setting-label"><?php esc_html_e( 'Vertical position', 'work-post-type' ); ?></label>
            <div class="work-bg-btn-group" data-setting="position_y">
              <?php foreach ( [ 'top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom' ] as $val => $label ) : ?>
              <button type="button" class="work-bg-btn<?php echo $hero_pos_y === $val ? ' is-active' : ''; ?>"
                data-value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="work-bg-setting">
            <label class="work-bg-setting-label"><?php esc_html_e( 'Size', 'work-post-type' ); ?></label>
            <div class="work-bg-btn-group" data-setting="size">
              <?php foreach ( [ 'cover' => 'Cover', 'contain' => 'Contain', 'auto' => 'Auto' ] as $val => $label ) : ?>
              <button type="button" class="work-bg-btn<?php echo $hero_size === $val ? ' is-active' : ''; ?>"
                data-value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="work-bg-setting">
            <label class="work-bg-setting-label"><?php esc_html_e( 'Repeat', 'work-post-type' ); ?></label>
            <select class="work-bg-select" data-setting="repeat">
              <?php foreach ( [ 'no-repeat' => 'No Repeat', 'repeat' => 'Tile', 'repeat-x' => 'Tile Horizontally', 'repeat-y' => 'Tile Vertically' ] as $val => $label ) : ?>
              <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $hero_repeat, $val ); ?>>
                <?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="work-bg-setting">
            <label class="work-bg-setting-label"><?php esc_html_e( 'Scroll behavior', 'work-post-type' ); ?></label>
            <div class="work-bg-btn-group" data-setting="attachment">
              <?php foreach ( [ 'scroll' => 'Scroll', 'fixed' => 'Fixed (parallax)' ] as $val => $label ) : ?>
              <button type="button" class="work-bg-btn<?php echo $hero_attach === $val ? ' is-active' : ''; ?>"
                data-value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></button>
              <?php endforeach; ?>
            </div>
          </div>

        </div>

        <div class="work-bg-preview-wrap">
          <p class="work-bg-setting-label"><?php esc_html_e( 'Preview', 'work-post-type' ); ?></p>
          <div id="work-hero-bg-preview" class="work-hero-bg-preview" <?php if ( $hero_image_url ) : ?>
            style="background-image:url('<?php echo esc_url( $hero_image_url ); ?>'); background-position:<?php echo esc_attr( "$hero_pos_x $hero_pos_y" ); ?>; background-size:<?php echo esc_attr( $hero_size ); ?>; background-repeat:<?php echo esc_attr( $hero_repeat ); ?>; background-attachment:<?php echo esc_attr( $hero_attach ); ?>;"
            <?php endif; ?>>
            <span class="work-bg-preview-label"><?php esc_html_e( 'Background preview', 'work-post-type' ); ?></span>
          </div>
        </div>

      </div>

    </div>
  </div>

  <?php /* ── Year/s ───────────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <label class="work-label" for="work_years"><?php esc_html_e( 'Year/s', 'work-post-type' ); ?></label>
    <input type="text" id="work_years" name="work_years" class="widefat" value="<?php echo esc_attr( $years ); ?>"
      placeholder="<?php esc_attr_e( 'e.g. 2023 or 2022–2024', 'work-post-type' ); ?>">
  </div>

  <?php /* ── Description ──────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <label class="work-label" for="work_description"><?php esc_html_e( 'Description', 'work-post-type' ); ?></label>
    <?php wp_editor( $description, 'work_description', [
      'textarea_name' => 'work_description',
      'textarea_rows' => 8,
      'media_buttons' => false,
      'teeny'         => false,
      'tinymce'       => [
        'toolbar1' => 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_adv',
        'toolbar2' => 'strikethrough hr forecolor | pastetext removeformat | charmap | outdent indent | undo redo | wp_help',
      ],
      'quicktags' => true,
    ] ); ?>
  </div>

  <?php /* ── Repeater ─────────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <p class="work-label"><?php esc_html_e( 'Items', 'work-post-type' ); ?></p>

    <div id="work-repeater" class="work-repeater">
      <?php foreach ( $items as $index => $item ) : ?>
      <?php work_repeater_row_html( $index, $item ); ?>
      <?php endforeach; ?>
    </div>

    <button type="button" id="work-add-item" class="button work-add-btn">
      <?php esc_html_e( '+ Add Item', 'work-post-type' ); ?>
    </button>

    <input type="hidden" id="work_items_json" name="work_items_json"
      value="<?php echo esc_attr( $items_json ?: '[]' ); ?>">
  </div>

  <?php /* ── Press ────────────────────────────────────────────────── */ ?>
  <div class="work-field-group">
    <label class="work-label" for="work_press"><?php esc_html_e( 'Press', 'work-post-type' ); ?></label>
    <?php wp_editor( $press, 'work_press', [
      'textarea_name' => 'work_press',
      'textarea_rows' => 8,
      'media_buttons' => false,
      'teeny'         => false,
      'tinymce'       => [
        'toolbar1' => 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_adv',
        'toolbar2' => 'strikethrough hr forecolor | pastetext removeformat | charmap | outdent indent | undo redo | wp_help',
      ],
      'quicktags' => true,
    ] ); ?>
  </div>

</div>

<?php
	echo '<script type="text/html" id="work-row-template">';
	work_repeater_row_html( '__INDEX__', [ 'title' => '', 'item_years' => '', 'image_id' => 0, 'item_description' => '' ] );
	echo '</script>';

	work_enqueue_meta_box_assets();
}

function work_repeater_row_html( $index, array $item ): void {

	$title            = isset( $item['title'] )            ? esc_attr( $item['title'] )      : '';
	$item_years       = isset( $item['item_years'] )       ? esc_attr( $item['item_years'] )  : '';
	$image_id         = isset( $item['image_id'] )         ? absint( $item['image_id'] )       : 0;
	$item_description = isset( $item['item_description'] ) ? $item['item_description']         : '';
	$image_url        = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
	$is_template      = ( '__INDEX__' === (string) $index );
	$row_id           = $is_template ? 'work-row-__INDEX__' : 'work-row-' . $index;

	?>
<div class="work-repeater-row" id="<?php echo esc_attr( $row_id ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
  <div class="work-row-handle"><span class="dashicons dashicons-move"></span></div>
  <div class="work-row-fields">

    <div class="work-row-field">
      <label class="work-row-label"
        for="work-title-<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Title', 'work-post-type' ); ?></label>
      <input type="text" id="work-title-<?php echo esc_attr( $index ); ?>" class="work-item-title widefat"
        value="<?php echo $title; ?>" placeholder="<?php esc_attr_e( 'Enter title…', 'work-post-type' ); ?>">
    </div>

    <div class="work-row-field">
      <label class="work-row-label"
        for="work-item-years-<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Item Year/s', 'work-post-type' ); ?></label>
      <input type="text" id="work-item-years-<?php echo esc_attr( $index ); ?>" class="work-item-item-years widefat"
        value="<?php echo $item_years; ?>"
        placeholder="<?php esc_attr_e( 'e.g. 2023 or 2022–2024', 'work-post-type' ); ?>">
    </div>

    <div class="work-row-field">
      <label class="work-row-label"><?php esc_html_e( 'Image', 'work-post-type' ); ?></label>
      <div class="work-image-wrap">
        <div class="work-image-preview" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
          <?php if ( $image_url ) : ?><img src="<?php echo esc_url( $image_url ); ?>" alt=""><?php endif; ?>
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

    <div class="work-row-field">
      <label class="work-row-label"
        for="work-item-desc-<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Item Description', 'work-post-type' ); ?></label>
      <textarea id="work-item-desc-<?php echo esc_attr( $index ); ?>" class="work-item-item-description widefat"
        rows="5"><?php echo $is_template ? '' : esc_textarea( $item_description ); ?></textarea>
    </div>

  </div>
  <div class="work-row-actions">
    <button type="button" class="button-link work-remove-row"
      aria-label="<?php esc_attr_e( 'Remove item', 'work-post-type' ); ?>">
      <span class="dashicons dashicons-no-alt"></span>
    </button>
  </div>
</div>
<?php
}


/* ==========================================================================
   5. ENQUEUE META BOX ASSETS
   ========================================================================== */

add_action( 'admin_enqueue_scripts', 'work_enqueue_meta_box_scripts' );

function work_enqueue_meta_box_scripts( string $hook ): void {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
	$screen = get_current_screen();
	if ( ! $screen || 'work' !== $screen->post_type ) return;

	wp_enqueue_media();
	wp_enqueue_editor();
	wp_enqueue_style( 'dashicons' );
}

function work_enqueue_meta_box_assets(): void {

	$css = '
		.work-fields-wrap { max-width: 960px; }
		.work-field-group { margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid #dcdcde; }
		.work-field-group:last-child { border-bottom: none; }
		.work-label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #1e1e1e; }

		.work-hero-bg-wrap { display: flex; flex-direction: column; gap: 20px; }
		.work-hero-image-picker { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
		.work-hero-image-actions { display: flex; align-items: center; gap: 8px; }
		.work-hero-image-preview img { display: block; max-width: 300px; max-height: 200px; border-radius: 3px; border: 1px solid #dcdcde; }

		.work-hero-bg-settings { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 16px 18px; }
		.work-bg-settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px 24px; margin-bottom: 20px; }
		.work-bg-setting-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #50575e; margin-bottom: 6px; }
		.work-bg-btn-group { display: inline-flex; border: 1px solid #8c8f94; border-radius: 3px; overflow: hidden; }
		.work-bg-btn { background: #fff; border: none; border-right: 1px solid #8c8f94; padding: 5px 12px; font-size: 12px; cursor: pointer; color: #2c3338; line-height: 1.4; transition: background .1s, color .1s; }
		.work-bg-btn:last-child { border-right: none; }
		.work-bg-btn:hover { background: #f0f0f1; }
		.work-bg-btn.is-active { background: #2271b1; color: #fff; }
		.work-bg-select { max-width: 200px; font-size: 13px; }
		.work-hero-bg-preview { width: 100%; max-width: 520px; height: 180px; border-radius: 3px; border: 1px solid #dcdcde; display: flex; align-items: center; justify-content: center; background-color: #e0e0e0; overflow: hidden; margin-top: 6px; }
		.work-bg-preview-label { font-size: 11px; color: #8c8f94; background: rgba(255,255,255,.75); padding: 2px 8px; border-radius: 2px; pointer-events: none; }

		.work-repeater { margin-bottom: 12px; }
		.work-repeater-row { display: flex; align-items: flex-start; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; margin-bottom: 12px; }
		.work-row-handle { display: flex; align-items: center; justify-content: center; padding: 12px 8px; cursor: grab; color: #8c8f94; flex-shrink: 0; }
		.work-row-handle:active { cursor: grabbing; }
		.work-row-fields { flex: 1; padding: 14px 12px; min-width: 0; }
		.work-row-field { margin-bottom: 14px; }
		.work-row-field:last-child { margin-bottom: 0; }
		.work-row-label { display: block; font-size: 12px; font-weight: 600; color: #3c434a; margin-bottom: 5px; }
		.work-row-actions { padding: 12px 10px; flex-shrink: 0; }
		.work-remove-row { color: #d63638 !important; text-decoration: none !important; line-height: 1; }
		.work-remove-row:hover { color: #b32d2e !important; }
		.work-image-preview { margin-bottom: 8px; }
		.work-image-preview img { display: block; max-width: 120px; max-height: 80px; border-radius: 3px; border: 1px solid #dcdcde; }
		.work-image-remove { margin-left: 6px; color: #d63638 !important; }
		.work-image-remove:hover { color: #b32d2e !important; }
		.work-add-btn { margin-top: 4px; }
	';

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<style>' . $css . '</style>';

	?>
<script>
jQuery(document).ready(function($) {
  'use strict';

  /* ====================================================================
     HERO BACKGROUND
     ==================================================================== */

  var heroBg = (function() {
    try {
      return JSON.parse($('#work_hero_bg_json').val()) || {};
    } catch (e) {
      return {};
    }
  })();

  heroBg = $.extend({
    image_id: 0,
    position_x: 'center',
    position_y: 'center',
    size: 'cover',
    repeat: 'no-repeat',
    attachment: 'scroll'
  }, heroBg);

  function syncHeroBg() {
    $('#work_hero_bg_json').val(JSON.stringify(heroBg));
    updateHeroBgPreview();
  }

  function updateHeroBgPreview() {
    var $preview = $('#work-hero-bg-preview');
    if (!heroBg.image_id) {
      $preview.css('background-image', '');
      return;
    }
    var imgSrc = $('.work-hero-image-preview img').attr('src') || '';
    $preview.css({
      'background-image': imgSrc ? 'url(' + imgSrc + ')' : '',
      'background-position': heroBg.position_x + ' ' + heroBg.position_y,
      'background-size': heroBg.size,
      'background-repeat': heroBg.repeat,
      'background-attachment': heroBg.attachment
    });
  }

  $('#work-hero-image-select').on('click', function() {
    var frame = wp.media({
      title: '<?php echo esc_js( __( 'Select or Upload Hero Image', 'work-post-type' ) ); ?>',
      button: {
        text: '<?php echo esc_js( __( 'Use this image', 'work-post-type' ) ); ?>'
      },
      library: {
        type: 'image'
      },
      multiple: false
    });
    frame.on('select', function() {
      var attachment = frame.state().get('selection').first().toJSON();
      var previewUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url :
        attachment.url;
      heroBg.image_id = attachment.id;
      syncHeroBg();
      $('.work-hero-image-preview').html('<img src="' + previewUrl + '" alt="">').show();
      $('#work-hero-image-select').text('<?php echo esc_js( __( 'Change Image', 'work-post-type' ) ); ?>');
      $('#work-hero-image-remove').show();
      $('#work-hero-bg-settings').show();
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

  $(document).on('click', '.work-bg-btn-group .work-bg-btn', function() {
    var $btn = $(this),
      $group = $btn.closest('.work-bg-btn-group');
    $group.find('.work-bg-btn').removeClass('is-active');
    $btn.addClass('is-active');
    heroBg[$group.data('setting')] = $btn.data('value');
    syncHeroBg();
  });

  $(document).on('change', '.work-bg-select', function() {
    heroBg[$(this).data('setting')] = $(this).val();
    syncHeroBg();
  });

  updateHeroBgPreview();


  /* ====================================================================
     REPEATER
     ==================================================================== */

  var repeater = $('#work-repeater');
  var jsonInput = $('#work_items_json');
  var template = $('#work-row-template').html();
  var rowIndex = repeater.children('.work-repeater-row').length;

  function syncJSON() {
    var items = [];
    repeater.children('.work-repeater-row').each(function() {
      var $row = $(this);
      var edId = $row.find('.work-item-item-description').attr('id');
      var desc = '';
      if (edId && window.tinymce && tinymce.get(edId)) {
        try {
          desc = tinymce.get(edId).getContent().replace(/\n/g, '');
        } catch (e) {
          desc = $row.find('.work-item-item-description').val();
        }
      } else {
        desc = $row.find('.work-item-item-description').val();
      }
      items.push({
        title: $row.find('.work-item-title').val().trim(),
        item_years: $row.find('.work-item-item-years').val().trim(),
        image_id: parseInt($row.find('.work-item-image-id').val(), 10) || 0,
        item_description: desc
      });
    });
    jsonInput.val(JSON.stringify(items));
  }

  function initEditor($textarea) {
    var id = $textarea.attr('id');
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
      }
    });
  }

  function destroyEditor($row) {
    var id = $row.find('.work-item-item-description').attr('id');
    if (id && window.tinymce && tinymce.get(id)) tinymce.get(id).remove();
  }

  $('#work-add-item').on('click', function() {
    var html = template.replace(/__INDEX__/g, rowIndex);
    var $row = $(html);
    repeater.append($row);
    var $ta = $row.find('.work-item-item-description');
    $ta.attr('id', 'work-item-desc-' + rowIndex);
    initEditor($ta);
    rowIndex++;
  });

  repeater.on('click', '.work-remove-row', function() {
    var $row = $(this).closest('.work-repeater-row');
    destroyEditor($row);
    $row.remove();
    syncJSON();
  });

  repeater.on('click', '.work-image-select', function() {
    var $row = $(this).closest('.work-repeater-row');
    var frame = wp.media({
      title: '<?php echo esc_js( __( 'Select or Upload Image', 'work-post-type' ) ); ?>',
      button: {
        text: '<?php echo esc_js( __( 'Use this image', 'work-post-type' ) ); ?>'
      },
      library: {
        type: 'image'
      },
      multiple: false
    });
    frame.on('select', function() {
      var attachment = frame.state().get('selection').first().toJSON();
      var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url :
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
    var $row = $(this).closest('.work-repeater-row');
    $row.find('.work-item-image-id').val(0);
    $row.find('.work-image-preview').hide().empty();
    $row.find('.work-image-select').text('<?php echo esc_js( __( 'Select Image', 'work-post-type' ) ); ?>');
    $(this).hide();
    syncJSON();
  });

  repeater.on('input change', '.work-item-title, .work-item-item-years', syncJSON);

  // Sync before WordPress saves the post.
  $('#post').on('submit', function() {
    if (window.tinymce) tinymce.triggerSave();
    syncHeroBg();
    syncJSON();
  });

  // Init TinyMCE on existing rows, polling until tinymce is available.
  (function waitForTinymce() {
    if (window.tinymce) {
      repeater.children('.work-repeater-row').each(function() {
        var $ta = $(this).find('.work-item-item-description');
        if ($ta.length) initEditor($ta);
      });
    } else {
      setTimeout(waitForTinymce, 50);
    }
  })();

});
</script>
<?php
}


/* ==========================================================================
   6. SAVE META
   ========================================================================== */

add_action( 'save_post_work', 'work_save_meta', 10, 2 );

function work_save_meta( int $post_id, WP_Post $post ): void {

	$nonce = isset( $_POST['work_fields_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['work_fields_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'work_save_fields' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( wp_is_post_revision( $post_id ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	/*
	 * IMPORTANT: update_post_meta() expects SLASHED data — it runs
	 * wp_unslash() internally before storing. Values are therefore:
	 *   1. wp_unslash()-ed from $_POST,
	 *   2. sanitized,
	 *   3. wp_slash()-ed before being passed to update_post_meta().
	 * Skipping step 3 corrupts JSON values: the internal unslash strips the
	 * backslashes JSON uses to escape double quotes (e.g. `64\"` -> `64"`),
	 * producing invalid JSON in the database.
	 */

	if ( isset( $_POST['work_hero_bg_json'] ) ) {
		update_post_meta(
			$post_id,
			'_work_hero_bg',
			wp_slash( work_sanitize_hero_bg_json( wp_unslash( $_POST['work_hero_bg_json'] ) ) )
		);
	}

	if ( isset( $_POST['work_years'] ) ) {
		update_post_meta(
			$post_id,
			'_work_years',
			wp_slash( sanitize_text_field( wp_unslash( $_POST['work_years'] ) ) )
		);
	}

	if ( isset( $_POST['work_description'] ) ) {
		update_post_meta(
			$post_id,
			'_work_description',
			wp_slash( work_sanitize_wysiwyg( wp_unslash( $_POST['work_description'] ) ) )
		);
	}

	if ( isset( $_POST['work_press'] ) ) {
		update_post_meta(
			$post_id,
			'_work_press',
			wp_slash( work_sanitize_wysiwyg( wp_unslash( $_POST['work_press'] ) ) )
		);
	}

	if ( isset( $_POST['work_items_json'] ) ) {
		update_post_meta(
			$post_id,
			'_work_items',
			wp_slash( work_sanitize_items_json( wp_unslash( $_POST['work_items_json'] ), $post_id ) )
		);
	}
}