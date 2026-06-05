<?php
/**
 * Replace the Projects taxonomy description textarea with TinyMCE.
 */

// ── 1. Hide the default description field via CSS ────────────────────────────

add_action( 'admin_head', 'projects_hide_default_description' );

function projects_hide_default_description() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->taxonomy !== 'projects' ) return;
    ?>
<style>
/* Hide the default textarea description row only — not the TinyMCE replacement */
tr.term-description-wrap:has(textarea#description) {
  display: none !important;
}
</style>
<?php
}

// ── 2. Hide "Parent Category" field ───────────────────────────────
add_action( 'admin_head', 'projects_hide_parent_category' );

function projects_hide_parent_category() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->taxonomy !== 'projects' ) return;
    ?>
<style>
/* Hide default description field */
/* your existing selectors here */

/* Hide Parent Category field */
.term-parent-wrap,
.form-field.term-parent-wrap {
  display: none !important;
}
</style>
<?php
}


// ── 3. Add TinyMCE on the "Add Project" screen ───────────────────────────────

add_action( 'projects_add_form_fields', 'projects_add_description_editor' );

function projects_add_description_editor() {
    ?>
<div class="form-field term-description-wrap">
  <label for="tag-description"><?php _e( 'Description', 'your-textdomain' ); ?></label>
  <?php
        wp_editor( '', 'tag-description', [
            'textarea_name' => 'description',
            'media_buttons' => false,
            'textarea_rows' => 10,
            'teeny'         => false,
            'quicktags'     => true,
        ] );
        ?>
  <p class="description">
    <?php _e( 'The description is not prominent by default; however, some themes may show it.', 'your-textdomain' ); ?>
  </p>
</div>
<?php
}


// ── 4. Add TinyMCE on the "Edit Project" screen ──────────────────────────────

add_action( 'projects_edit_form_fields', 'projects_edit_description_editor', 10, 2 );

function projects_edit_description_editor( $term, $taxonomy ) {
    $description = $term->description ?? '';
    ?>
<tr class="form-field term-description-wrap">
  <th scope="row">
    <label for="tag-description"><?php _e( 'Description', 'your-textdomain' ); ?></label>
  </th>
  <td>
    <?php
            wp_editor( htmlspecialchars_decode( $description ), 'tag-description', [
                'textarea_name' => 'description',
                'media_buttons' => false,
                'textarea_rows' => 10,
                'teeny'         => false,
                'quicktags'     => true,
            ] );
            ?>
    <p class="description">
      <?php _e( 'The description is not prominent by default; however, some themes may show it.', 'your-textdomain' ); ?>
    </p>
  </td>
</tr>
<?php
}


// ── 5. Sanitize & save (allow HTML from TinyMCE) ─────────────────────────────

add_filter( 'pre_insert_term', 'projects_allow_html_description', 10, 2 );
add_filter( 'pre_term_description', 'projects_sanitize_description', 10, 2 );

function projects_allow_html_description( $term, $taxonomy ) {
    if ( $taxonomy === 'projects' ) {
        remove_filter( 'pre_term_description', 'wp_filter_kses' );
    }
    return $term;
}

function projects_sanitize_description( $value, $taxonomy ) {
    if ( $taxonomy === 'projects' ) {
        return wp_kses_post( $value );
    }
    return $value;
}