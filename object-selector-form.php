/**
 * Plugin Name: Objekti Relationship Form Handler
 * Description: Saves JetFormBuilder checkbox (obj) into JetEngine relation ID 7 (Users → objekti).
 * Version:     2.0.0 — Production
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OBJ_RELATION_ID', 7     );
define( 'OBJ_FORM_FIELD',  'obj' );

// ─────────────────────────────────────────────────────────────────────────────
// When the form is submitted, replace the current user's related objekti
// with whatever was selected in the checkbox field.
// ─────────────────────────────────────────────────────────────────────────────
add_action(
    'jet-form-builder/custom-action/save_objekti_relations',
    function ( $request, $action_handler ) {

        $parent_id = get_current_user_id();
        if ( ! $parent_id ) return;

        // Normalise checkbox value → array of integer post IDs
        $raw = $request[ OBJ_FORM_FIELD ] ?? null;

        if ( is_array( $raw ) ) {
            $child_ids = array_map( 'absint', $raw );
        } elseif ( is_string( $raw ) && $raw !== '' ) {
            $decoded   = json_decode( $raw, true );
            $child_ids = is_array( $decoded )
                ? array_map( 'absint', $decoded )
                : array_map( 'absint', explode( ',', $raw ) );
        } else {
            $child_ids = [];
        }

        $child_ids = array_values( array_filter( $child_ids ) );

        // Get the JetEngine relation object
        if ( ! function_exists( 'jet_engine' ) || ! isset( jet_engine()->relations ) ) return;
        $relation = jet_engine()->relations->get_active_relations( OBJ_RELATION_ID );
        if ( ! $relation ) return;

        // Replace: clear all existing children for this user, then insert selected ones
        $relation->set_update_context( 'child' );
        $relation->delete_rows( $parent_id, null, true );

        foreach ( $child_ids as $child_id ) {
            $relation->update( $parent_id, $child_id );
        }
    },
    10,
    2
);

// ─────────────────────────────────────────────────────────────────────────────
// Refresh the JFB nonce after page load so cached pages still work correctly.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_obj_refresh_nonce',        'obj_refresh_nonce' );
add_action( 'wp_ajax_nopriv_obj_refresh_nonce', 'obj_refresh_nonce' );

function obj_refresh_nonce() {
    wp_send_json_success( [ 'nonce' => wp_create_nonce( 'jet-form-builder-nonce' ) ] );
}

add_action( 'wp_footer', function () { ?>
    <script>
    (function() {
        fetch('<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>?action=obj_refresh_nonce')
            .then( r => r.json() )
            .then( data => {
                if ( ! data.success ) return;
                document.querySelectorAll(
                    'input[name="_wpnonce"], input[name="jet-form-builder-nonce"]'
                ).forEach( el => el.value = data.data.nonce );
            });
    })();
    </script>
<?php } );
