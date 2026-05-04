<?php
/**
 * Testimonial Custom Post Type - Meta Fields
 *
 * Registers and handles custom meta fields for the 'testimonial' CPT:
 *  - Stars (range 1–5)
 *  - Testimonial Content (textarea)
 *  - Name (text)
 *  - Location (text)
 */

// -------------------------------------------------------------------------
// 1. Register the meta boxes
// -------------------------------------------------------------------------
add_action( 'add_meta_boxes', 'testimonial_add_meta_boxes' );

function testimonial_add_meta_boxes() {
	add_meta_box(
		'testimonial_details',           // Unique ID
		'Testimonial Details',            // Box title
		'testimonial_meta_box_html',     // Callback
		'testimonial',                   // Post type
		'normal',                         // Context
		'high'                            // Priority
	);
}

// -------------------------------------------------------------------------
// 2. Render the meta box HTML
// -------------------------------------------------------------------------
function testimonial_meta_box_html( $post ) {

	// Security nonce
	wp_nonce_field( 'testimonial_save_meta', 'testimonial_nonce' );

	// Retrieve existing values (fall back to sensible defaults)
	$stars    = get_post_meta( $post->ID, '_testimonial_stars',   true );
	$content  = get_post_meta( $post->ID, '_testimonial_content', true );
	$name     = get_post_meta( $post->ID, '_testimonial_name',    true );
	$location = get_post_meta( $post->ID, '_testimonial_location', true );

	// Default star rating to 5 when empty
	if ( $stars === '' ) {
		$stars = 5;
	}
	?>

	<style>
		.tmeta-grid {
			display: grid;
			gap: 16px;
			padding: 8px 0;
		}
		.tmeta-row label {
			display: block;
			font-weight: 600;
			margin-bottom: 4px;
		}
		.tmeta-row input[type="text"],
		.tmeta-row textarea {
			width: 100%;
			box-sizing: border-box;
		}
		.tmeta-row textarea {
			height: 120px;
			resize: vertical;
		}
		.star-range-wrap {
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.star-range-wrap input[type="range"] {
			width: 200px;
			cursor: pointer;
		}
		#stars-display {
			font-size: 1.1em;
			font-weight: 700;
			min-width: 24px;
		}
	</style>

	<div class="tmeta-grid">

		<!-- Stars -->
		<div class="tmeta-row">
			<label for="testimonial_stars">Stars (1 – 5)</label>
			<div class="star-range-wrap">
				<input
					type="range"
					id="testimonial_stars"
					name="testimonial_stars"
					min="1"
					max="5"
					step="1"
					value="<?php echo esc_attr( $stars ); ?>"
					oninput="document.getElementById('stars-display').textContent = this.value;"
				>
				<span id="stars-display"><?php echo esc_html( $stars ); ?></span>
			</div>
		</div>

		<!-- Testimonial Content -->
		<div class="tmeta-row">
			<label for="testimonial_content">Testimonial Content</label>
			<textarea
				id="testimonial_content"
				name="testimonial_content"
				placeholder="Enter the testimonial text…"
			><?php echo esc_textarea( $content ); ?></textarea>
		</div>

		<!-- Name -->
		<div class="tmeta-row">
			<label for="testimonial_name">Name</label>
			<input
				type="text"
				id="testimonial_name"
				name="testimonial_name"
				value="<?php echo esc_attr( $name ); ?>"
				placeholder="e.g. Jane Smith"
			>
		</div>

		<!-- Location -->
		<div class="tmeta-row">
			<label for="testimonial_location">Location</label>
			<input
				type="text"
				id="testimonial_location"
				name="testimonial_location"
				value="<?php echo esc_attr( $location ); ?>"
				placeholder="e.g. Atlanta, GA"
			>
		</div>

	</div>
	<?php
}

// -------------------------------------------------------------------------
// 3. Register the meta fields
// -------------------------------------------------------------------------

function testimonial_register_meta() {

    $common = [
        'object_subtype' => 'testimonial',
        'single'         => true,
        'show_in_rest'   => true,
    ];

    register_post_meta( 'post', '_testimonial_stars', array_merge( $common, [
        'type'              => 'integer',
        'description'       => 'Star rating from 1 to 5',
        'sanitize_callback' => function( $val ) {
            return max( 1, min( 5, absint( $val ) ) );
        },
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ]));

    register_post_meta( 'post', '_testimonial_content', array_merge( $common, [
        'type'              => 'string',
        'description'       => 'Testimonial body text',
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ]));

    register_post_meta( 'post', '_testimonial_name', array_merge( $common, [
        'type'              => 'string',
        'description'       => 'Name of the person giving the testimonial',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ]));

    register_post_meta( 'post', '_testimonial_location', array_merge( $common, [
        'type'              => 'string',
        'description'       => 'Location of the person giving the testimonial',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ]));
}

add_action( 'init', 'testimonial_register_meta' );

// -------------------------------------------------------------------------
// 4. Save the meta data
// -------------------------------------------------------------------------
add_action( 'save_post_testimonial', 'testimonial_save_meta' );

function testimonial_save_meta( $post_id ) {

	// --- Security checks ---

	// Verify nonce
	if (
		! isset( $_POST['testimonial_nonce'] ) ||
		! wp_verify_nonce( $_POST['testimonial_nonce'], 'testimonial_save_meta' )
	) {
		return;
	}

	// Bail on autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check user capability
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// --- Sanitize & save each field ---

	// Stars: clamp to 1–5
	if ( isset( $_POST['testimonial_stars'] ) ) {
		$stars = absint( $_POST['testimonial_stars'] );
		$stars = max( 1, min( 5, $stars ) );
		update_post_meta( $post_id, '_testimonial_stars', $stars );
	}

	// Testimonial Content
	if ( isset( $_POST['testimonial_content'] ) ) {
		update_post_meta(
			$post_id,
			'_testimonial_content',
			sanitize_textarea_field( $_POST['testimonial_content'] )
		);
	}

	// Name
	if ( isset( $_POST['testimonial_name'] ) ) {
		update_post_meta(
			$post_id,
			'_testimonial_name',
			sanitize_text_field( $_POST['testimonial_name'] )
		);
	}

	// Location
	if ( isset( $_POST['testimonial_location'] ) ) {
		update_post_meta(
			$post_id,
			'_testimonial_location',
			sanitize_text_field( $_POST['testimonial_location'] )
		);
	}
}

