<?php
/**
 * Pricing Package — Custom Fields
 *
 * Registers meta boxes, fields, sanitization, saving, and REST API
 * exposure for the `pricing-package` custom post type.
 *
 * @package Pricing Package Post Type
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 1. Register post meta (REST API exposure + sanitization callbacks)
// ---------------------------------------------------------------------------

add_action( 'init', 'pp_register_post_meta' );
add_action( 'init', 'pp_migrate_list_meta_json_strings_to_array', 30 );

function pp_register_post_meta(): void {

	$shared = [
		'object_subtype'    => 'pricing-package',
		'single'            => true,
		'show_in_rest'      => true,
		// 'revisions_enabled' => true,
	];

	// Title
	register_post_meta( 'pricing-package', '_pp_title', array_merge( $shared, [
		'type'              => 'string',
		'description'       => __( 'Pricing package title.', 'willow-pricing-package' ),
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => 'pp_meta_auth_callback',
	] ) );

	// Description
	register_post_meta( 'pricing-package', '_pp_description', array_merge( $shared, [
		'type'              => 'string',
		'description'       => __( 'Pricing package description.', 'willow-pricing-package' ),
		'sanitize_callback' => 'sanitize_textarea_field',
		'auth_callback'     => 'pp_meta_auth_callback',
	] ) );

	// Price
	register_post_meta( 'pricing-package', '_pp_price', array_merge( $shared, [
		'type'              => 'string',
		'description'       => __( 'Pricing package price.', 'willow-pricing-package' ),
		'sanitize_callback' => 'pp_sanitize_price',
		'auth_callback'     => 'pp_meta_auth_callback',
	] ) );

	// List items (stored as a PHP array in post meta; REST exposes array of strings)
	register_post_meta( 'pricing-package', '_pp_list', array_merge( $shared, [
		'type'              => 'array',
		'description'       => __( 'Pricing package list items.', 'willow-pricing-package' ),
		'sanitize_callback' => 'pp_sanitize_list',
		'auth_callback'     => 'pp_meta_auth_callback',
		'default'           => [],
		'show_in_rest'      => [
			'schema' => [
				'type'  => 'array',
				'items' => [
					'type' => 'string',
				],
			],
		],
	] ) );

	// List item "included" flags (parallel array to _pp_list; 1 = included, 0 = excluded)
	register_post_meta( 'pricing-package', '_pp_list_plus', array_merge( $shared, [
		'type'              => 'array',
		'description'       => __( 'Whether each pricing package list item should use a plus icon.', 'willow-pricing-package' ),
		'sanitize_callback' => 'pp_sanitize_list_plus',
		'auth_callback'     => 'pp_meta_auth_callback',
		'default'           => [],
		'show_in_rest'      => [
			'schema' => [
				'type'  => 'array',
				'items' => [
					'type' => 'boolean',
				],
			],
		],
	] ) );

	// Link
	register_post_meta( 'pricing-package', '_pp_link', array_merge( $shared, [
		'type'              => 'string',
		'description'       => __( 'Pricing package link URL.', 'willow-pricing-package' ),
		'sanitize_callback' => 'esc_url_raw',
		'auth_callback'     => 'pp_meta_auth_callback',
	] ) );

	// Featured
	register_post_meta( 'pricing-package', '_pp_featured', array_merge( $shared, [
		'type'              => 'boolean',
		'description'       => __( 'Whether this pricing package is the "Most Popular" package.', 'willow-pricing-package' ),
		'sanitize_callback' => 'pp_sanitize_boolean',
		'auth_callback'     => 'pp_meta_auth_callback',
		'show_in_rest'      => [
			'schema' => [
				'type' => 'boolean',
			],
		],
	] ) );
}

// ---------------------------------------------------------------------------
// 2. Auth callback
// ---------------------------------------------------------------------------

function pp_meta_auth_callback( bool $allowed, string $meta_key, int $post_id, int $user_id ): bool {
	return user_can( $user_id, 'edit_post', $post_id );
}

/**
 * Decode HTML entities until stable (handles double-encoded REST values).
 */
function pp_decode_entities( string $value ): string {
	$previous = '';

	while ( $previous !== $value ) {
		$previous = $value;
		$value    = wp_specialchars_decode( $value, ENT_QUOTES );
	}

	return $value;
}

/**
 * Return plain-text strings from REST for use with data-wp-text (textContent).
 */
function pp_rest_prepare_decode_entities( WP_REST_Response $response, WP_Post $post, WP_REST_Request $request ): WP_REST_Response {
	unset( $post, $request );

	$data = $response->get_data();

	if ( isset( $data['title']['rendered'] ) && is_string( $data['title']['rendered'] ) ) {
		$data['title']['rendered'] = pp_decode_entities( $data['title']['rendered'] );
	}

	if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
		foreach ( [ '_pp_title', '_pp_description', '_pp_price' ] as $meta_key ) {
			if ( isset( $data['meta'][ $meta_key ] ) && is_string( $data['meta'][ $meta_key ] ) ) {
				$data['meta'][ $meta_key ] = pp_decode_entities( $data['meta'][ $meta_key ] );
			}
		}

		if ( isset( $data['meta']['_pp_list'] ) && is_array( $data['meta']['_pp_list'] ) ) {
			$data['meta']['_pp_list'] = array_map(
				static fn( $item ) => is_string( $item ) ? pp_decode_entities( $item ) : $item,
				$data['meta']['_pp_list']
			);
		}
	}

	$response->set_data( $data );

	return $response;
}

add_filter( 'rest_prepare_pricing-package', 'pp_rest_prepare_decode_entities', 10, 3 );

// ---------------------------------------------------------------------------
// 3. Custom sanitization helpers
// ---------------------------------------------------------------------------

/**
 * Sanitize price — strip anything that isn't a digit, dot, or comma.
 */
function pp_sanitize_price( string $value ): string {
	$value = sanitize_text_field( $value );
	return preg_replace( '/[^\d.,]/', '', $value );
}

/**
 * Normalize list meta to a flat array of sanitized strings.
 *
 * Accepts an array (REST / native meta) or a legacy JSON string from older saves.
 *
 * @param mixed $meta_value Value passed from `sanitize_*_meta_*` or raw meta.
 * @return string[] Non-empty string items, re-indexed.
 */
function pp_sanitize_list( mixed $meta_value, string $meta_key = '', string $object_type = 'post', string $object_subtype = '' ): array {
	$items = [];

	if ( is_array( $meta_value ) ) {
		$items = $meta_value;
	} elseif ( is_string( $meta_value ) && $meta_value !== '' ) {
		$decoded = json_decode( stripslashes( $meta_value ), true );
		$items   = is_array( $decoded ) ? $decoded : [];
	}

	return array_values(
		array_filter(
			array_map(
				static function ( $item ) {
					return sanitize_text_field( is_scalar( $item ) ? (string) $item : '' );
				},
				$items
			),
			static fn( $item ) => $item !== ''
		)
	);
}

/**
 * One-time migration: legacy `_pp_list` values stored as JSON strings → PHP arrays.
 */
function pp_migrate_list_meta_json_strings_to_array(): void {
	if ( get_option( 'pp_list_meta_migrated_to_array' ) ) {
		return;
	}

	$post_ids = get_posts(
		array(
			'post_type'              => 'pricing-package',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $post_ids as $post_id ) {
		$raw = get_post_meta( (int) $post_id, '_pp_list', true );
		if ( is_string( $raw ) && $raw !== '' ) {
			update_post_meta( (int) $post_id, '_pp_list', pp_sanitize_list( $raw ) );
		}
	}

	update_option( 'pp_list_meta_migrated_to_array', '1', true );
}

/**
 * Normalize list-plus meta to a flat array of booleans.
 *
 * @param mixed $meta_value Raw value from POST or meta storage.
 * @return bool[] One boolean per list item, re-indexed.
 */
function pp_sanitize_list_plus( mixed $meta_value ): array {
	$items = is_array( $meta_value ) ? $meta_value : [];
	return array_values(
		array_map(
			static fn( $v ) => (bool) filter_var( $v, FILTER_VALIDATE_BOOLEAN ),
			$items
		)
	);
}

/**
 * Sanitize boolean — returns true only for truthy values.
 */
function pp_sanitize_boolean( mixed $value ): bool {
	return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

// ---------------------------------------------------------------------------
// 4. Register meta box
// ---------------------------------------------------------------------------

add_action( 'add_meta_boxes', 'pp_add_meta_boxes' );

function pp_add_meta_boxes(): void {
	add_meta_box(
		'pp_package_details',
		__( 'Package Details', 'willow-pricing-package' ),
		'pp_render_meta_box',
		'pricing-package',
		'normal',
		'high'
	);
}

// ---------------------------------------------------------------------------
// 5. Render meta box
// ---------------------------------------------------------------------------

function pp_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'pp_save_meta', 'pp_meta_nonce' );

	$title       = get_post_meta( $post->ID, '_pp_title',       true );
	$description = get_post_meta( $post->ID, '_pp_description', true );
	$price       = get_post_meta( $post->ID, '_pp_price',       true );
	$list_raw    = get_post_meta( $post->ID, '_pp_list',        true );
	$list_plus_raw  = get_post_meta( $post->ID, '_pp_list_plus', true );
	$link        = get_post_meta( $post->ID, '_pp_link',        true );
	$featured    = (bool) get_post_meta( $post->ID, '_pp_featured', true );

	$list_items = pp_sanitize_list( $list_raw );
	$list_plus  = pp_sanitize_list_plus( is_array( $list_plus_raw ) ? $list_plus_raw : [] );

	pp_enqueue_meta_box_assets();
	?>

	<div class="pp-meta-box">

		<?php pp_render_styles(); ?>

		<!-- Title -->
		<div class="pp-field pp-field--required">
			<label for="pp_title" class="pp-label">
				<?php esc_html_e( 'Title', 'willow-pricing-package' ); ?>
				<span class="pp-required" aria-label="<?php esc_attr_e( 'Required', 'willow-pricing-package' ); ?>">*</span>
			</label>
			<input
				type="text"
				id="pp_title"
				name="pp_title"
				class="pp-input"
				value="<?php echo esc_attr( $title ); ?>"
				placeholder="<?php esc_attr_e( 'Package title…', 'willow-pricing-package' ); ?>"
				required
				aria-required="true"
			>
		</div>

		<!-- Description -->
		<div class="pp-field">
			<label for="pp_description" class="pp-label">
				<?php esc_html_e( 'Description', 'willow-pricing-package' ); ?>
			</label>
			<textarea
				id="pp_description"
				name="pp_description"
				class="pp-input pp-input--textarea"
				rows="3"
			><?php echo esc_textarea( $description ); ?></textarea>
		</div>

		<!-- Price -->
		<div class="pp-field pp-field--required">
			<label for="pp_price" class="pp-label">
				<?php esc_html_e( 'Price', 'willow-pricing-package' ); ?>
				<span class="pp-required" aria-label="<?php esc_attr_e( 'Required', 'willow-pricing-package' ); ?>">*</span>
			</label>
			<input
				type="text"
				id="pp_price"
				name="pp_price"
				class="pp-input pp-input--half"
				value="<?php echo esc_attr( $price ); ?>"
				placeholder="e.g. 49.99"
				required
				aria-required="true"
			>
		</div>

		<!-- List -->
		<div class="pp-field pp-field--list">
			<label class="pp-label">
				<?php esc_html_e( 'List', 'willow-pricing-package' ); ?>
				<span class="pp-hint"><?php esc_html_e( 'Optional. Add one item per row.', 'willow-pricing-package' ); ?></span>
			</label>

			<div id="pp-list-items" class="pp-list-items">
				<?php if ( ! empty( $list_items ) ) : ?>
					<?php foreach ( $list_items as $index => $item ) : ?>
						<?php $plus = isset( $list_plus[ $index ] ) ? $list_plus[ $index ] : false; ?>
						<div class="pp-list-item">
							<input type="hidden" name="pp_list_plus[<?php echo $index; ?>]" value="0">

							<label class="pp-list-item__plus-label">
								<input
									type="checkbox"
									name="pp_list_plus[<?php echo $index; ?>]"
									class="pp-checkbox pp-list-item__plus"
									value="1"
									<?php checked( $plus, true ); ?>
								>
								<?php esc_html_e( 'Plus', 'willow-pricing-package' ); ?>
							</label>
							<input
								type="text"
								name="pp_list_items[]"
								class="pp-input pp-list-item__input"
								value="<?php echo esc_attr( $item ); ?>"
								placeholder="<?php esc_attr_e( 'List item…', 'willow-pricing-package' ); ?>"
							>
							<button
								type="button"
								class="pp-list-item__remove"
								aria-label="<?php esc_attr_e( 'Remove item', 'willow-pricing-package' ); ?>"
							>
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
							</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<button type="button" id="pp-add-list-item" class="pp-btn pp-btn--add">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
				<?php esc_html_e( 'Add List Item', 'willow-pricing-package' ); ?>
			</button>
		</div>

		<!-- Link -->
		<div class="pp-field pp-field--required">
			<label for="pp_link" class="pp-label">
				<?php esc_html_e( 'Link', 'willow-pricing-package' ); ?>
				<span class="pp-required" aria-label="<?php esc_attr_e( 'Required', 'willow-pricing-package' ); ?>">*</span>
			</label>
			<input
				type="url"
				id="pp_link"
				name="pp_link"
				class="pp-input"
				value="<?php echo esc_attr( $link ); ?>"
				placeholder="https://"
				required
				aria-required="true"
			>
		</div>

		<!-- Featured -->
		<div class="pp-field pp-field--checkbox">
			<label class="pp-checkbox-label" for="pp_featured">
				<input
					type="checkbox"
					id="pp_featured"
					name="pp_featured"
					class="pp-checkbox"
					value="1"
					<?php checked( $featured, true ); ?>
				>
				<span class="pp-checkbox-text">
					<?php esc_html_e( 'Featured', 'willow-pricing-package' ); ?>
				</span>
				<span class="pp-hint"><?php esc_html_e( 'Mark this package as the "Most Popular" package.', 'willow-pricing-package' ); ?></span>
			</label>
		</div>

	</div><!-- .pp-meta-box -->

	<?php pp_render_inline_script(); ?>

	<?php
}

// ---------------------------------------------------------------------------
// 6. Styles (injected inline — scoped to meta box)
// ---------------------------------------------------------------------------

function pp_render_styles(): void {
	?>
	<style>
		.pp-meta-box {
			padding: 4px 0 8px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}

		/* Field wrapper */
		.pp-field {
			margin-bottom: 20px;
		}
		.pp-field:last-child {
			margin-bottom: 0;
		}

		/* Label */
		.pp-label {
			display: flex;
			align-items: center;
			gap: 6px;
			margin-bottom: 6px;
			font-size: 13px;
			font-weight: 600;
			color: #1e1e1e;
		}

		.pp-required {
			color: #c0392b;
			font-size: 14px;
			line-height: 1;
		}

		.pp-hint {
			font-size: 11px;
			font-weight: 400;
			color: #757575;
		}

		/* Inputs */
		.pp-input {
			display: block;
			width: 100%;
			padding: 8px 10px;
			font-size: 13px;
			color: #1e1e1e;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			box-shadow: inset 0 1px 2px rgba(0,0,0,.07);
			transition: border-color .15s, box-shadow .15s;
			box-sizing: border-box;
		}
		.pp-input:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
			outline: none;
		}

		.pp-input--textarea {
			resize: vertical;
			min-height: 80px;
		}

		.pp-input--half {
			max-width: 200px;
		}

		/* List field */
		.pp-list-items {
			display: flex;
			flex-direction: column;
			gap: 8px;
			margin-bottom: 10px;
		}

		.pp-list-item {
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.pp-list-item__input {
			flex: 1;
		}

		.pp-list-item__remove {
			flex-shrink: 0;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 28px;
			height: 28px;
			padding: 0;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			color: #757575;
			cursor: pointer;
			transition: background .15s, border-color .15s, color .15s;
		}
		.pp-list-item__remove:hover {
			background: #fce8e6;
			border-color: #c0392b;
			color: #c0392b;
		}

		/* Buttons */
		.pp-btn--add {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 12px;
			font-size: 12px;
			font-weight: 600;
			color: #2271b1;
			background: #f0f6fc;
			border: 1px solid #2271b1;
			border-radius: 4px;
			cursor: pointer;
			transition: background .15s, color .15s;
		}
		.pp-btn--add:hover {
			background: #2271b1;
			color: #fff;
		}

		/* Checkbox */
		.pp-field--checkbox {
			display: flex;
			align-items: flex-start;
		}
		.pp-checkbox-label {
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
			font-size: 13px;
			color: #1e1e1e;
		}
		.pp-checkbox {
			width: 16px;
			height: 16px;
			margin: 0;
			cursor: pointer;
			accent-color: #2271b1;
		}
		.pp-checkbox-text {
			font-weight: 600;
		}
	</style>
	<?php
}

// ---------------------------------------------------------------------------
// 7. Inline JS for dynamic list field
// ---------------------------------------------------------------------------

function pp_render_inline_script(): void {
	?>
	<script>
	( function () {
		const container   = document.getElementById( 'pp-list-items' );
		const addBtn      = document.getElementById( 'pp-add-list-item' );
		const placeholder = <?php echo wp_json_encode( esc_attr__( 'List item…', 'willow-pricing-package' ) ); ?>;
		const removeLabel = <?php echo wp_json_encode( esc_attr__( 'Remove item', 'willow-pricing-package' ) ); ?>;
		const plusLabel   = <?php echo wp_json_encode( esc_attr__( 'Plus', 'willow-pricing-package' ) ); ?>;

		function createItem( value = '', plus = false ) {
			const row = document.createElement( 'div' );
			row.className = 'pp-list-item';

			const hidden = document.createElement( 'input' );
			hidden.type  = 'hidden';
			hidden.name  = 'pp_list_plus[]';
			hidden.value = '0';

			const checkbox = document.createElement( 'input' );
			checkbox.type      = 'checkbox';
			checkbox.name      = 'pp_list_plus[]';
			checkbox.className = 'pp-checkbox pp-list-item__plus';
			checkbox.value     = '1';
			checkbox.checked   = plus;

			const label = document.createElement( 'label' );
			label.className = 'pp-list-item__plus-label';
			label.textContent = plusLabel;
			label.prepend( checkbox );

			const input = document.createElement( 'input' );
			input.type        = 'text';
			input.name        = 'pp_list_items[]';
			input.className   = 'pp-input pp-list-item__input';
			input.value       = value;
			input.placeholder = placeholder;

			const btn = document.createElement( 'button' );
			btn.type      = 'button';
			btn.className = 'pp-list-item__remove';
			btn.setAttribute( 'aria-label', removeLabel );
			btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
			btn.addEventListener( 'click', () => row.remove() );

			row.appendChild( hidden );
			row.appendChild( label );
			row.appendChild( input );
			row.appendChild( btn );
			return row;
		}

		// Wire up existing remove buttons
		container.querySelectorAll( '.pp-list-item__remove' ).forEach( btn => {
			btn.addEventListener( 'click', () => btn.closest( '.pp-list-item' ).remove() );
		} );

		addBtn.addEventListener( 'click', () => {
			const item = createItem();
			container.appendChild( item );
			item.querySelector( 'input[type="text"]' ).focus();
		} );

		document.querySelector( '#post' ).addEventListener( 'submit', function () {
			container.querySelectorAll( '.pp-list-item' ).forEach( ( row, i ) => {
				const hidden   = row.querySelector( 'input[type="hidden"]' );
				const checkbox = row.querySelector( 'input[type="checkbox"]' );
				if ( hidden )   hidden.name   = 'pp_list_plus[' + i + ']';
				if ( checkbox ) checkbox.name = 'pp_list_plus[' + i + ']';
				row.querySelector( 'input[type="text"]' ).name = 'pp_list_items[' + i + ']';
			} );
		} );
	} )();
	</script>
	<?php
}

// ---------------------------------------------------------------------------
// 8. Enqueue any extra admin assets (placeholder — extend as needed)
// ---------------------------------------------------------------------------

function pp_enqueue_meta_box_assets(): void {
	// Styles and scripts are injected inline above.
	// Hook wp_enqueue_media() here if you ever add a media picker.
}

// ---------------------------------------------------------------------------
// 9. Save meta
// ---------------------------------------------------------------------------

add_action( 'save_post_pricing-package', 'pp_save_meta', 10, 2 );

function pp_save_meta( int $post_id, WP_Post $post ): void {

	// Nonce check
	if (
		! isset( $_POST['pp_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pp_meta_nonce'] ) ), 'pp_save_meta' )
	) {
		return;
	}

	// Capability check
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Don't save on autosave / revision
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	// --- Title (required) ---
	if ( isset( $_POST['pp_title'] ) ) {
		$title = sanitize_text_field( wp_unslash( $_POST['pp_title'] ) );
		update_post_meta( $post_id, '_pp_title', $title );
	}

	// --- Description ---
	if ( isset( $_POST['pp_description'] ) ) {
		$description = sanitize_textarea_field( wp_unslash( $_POST['pp_description'] ) );
		update_post_meta( $post_id, '_pp_description', $description );
	}

	// --- Price (required) ---
	if ( isset( $_POST['pp_price'] ) ) {
		$price = pp_sanitize_price( wp_unslash( $_POST['pp_price'] ) );
		update_post_meta( $post_id, '_pp_price', $price );
	}

	// --- List items + plus flags ---
	$raw_items = isset( $_POST['pp_list_items'] ) && is_array( $_POST['pp_list_items'] )
		? $_POST['pp_list_items']
		: [];
	$raw_plus = isset( $_POST['pp_list_plus'] ) && is_array( $_POST['pp_list_plus'] )
		? $_POST['pp_list_plus']
		: [];

	$clean_items = [];
	$clean_plus  = [];

	foreach ( $raw_items as $i => $raw_item ) {
		$item = sanitize_text_field( wp_unslash( $raw_item ) );
		if ( $item === '' ) {
			continue;
		}
		$clean_items[] = $item;
		$clean_plus[]  = isset( $raw_plus[ $i ] ) && '1' === sanitize_text_field( wp_unslash( $raw_plus[ $i ] ) );
	}

	update_post_meta( $post_id, '_pp_list',      pp_sanitize_list( $clean_items ) );
	update_post_meta( $post_id, '_pp_list_plus', pp_sanitize_list_plus( $clean_plus ) );

	// --- Link (required) ---
	if ( isset( $_POST['pp_link'] ) ) {
		$link = esc_url_raw( wp_unslash( $_POST['pp_link'] ) );
		update_post_meta( $post_id, '_pp_link', $link );
	}

	// --- Featured ---
	$featured = isset( $_POST['pp_featured'] ) && '1' === $_POST['pp_featured'];
	update_post_meta( $post_id, '_pp_featured', (int) $featured );
}