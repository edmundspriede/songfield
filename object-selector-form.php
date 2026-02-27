/**
 * Plugin Name: Objekti Relationship Form
 * Description: [objekti_form] shortcode — Shadow DOM isolated, theme-proof.
 * Version:     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OBJ_REL_ID', 7 );

// ── AJAX save ────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_obj_save_relations', function () {
    check_ajax_referer( 'obj_save_relations', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( ['message'=>'Not logged in'], 403 );

    $raw = $_POST['obj'] ?? [];
    if ( is_array( $raw ) ) {
        $ids = array_values( array_filter( array_map( 'absint', $raw ) ) );
    } elseif ( is_string( $raw ) && $raw !== '' ) {
        $dec = json_decode( $raw, true );
        $ids = array_values( array_filter( array_map( 'absint',
            is_array($dec) ? $dec : explode(',', $raw) ) ) );
    } else {
        $ids = [];
    }

    if ( ! function_exists('jet_engine') || ! isset( jet_engine()->relations ) )
        wp_send_json_error( ['message'=>'JetEngine not available'], 500 );

    $rel = jet_engine()->relations->get_active_relations( OBJ_REL_ID );
    if ( ! $rel ) wp_send_json_error( ['message'=>'Relation not found'], 500 );

    $rel->set_update_context('child');
    $rel->delete_rows( $uid, null, true );
    foreach ( $ids as $cid ) $rel->update( $uid, $cid );

    wp_send_json_success( ['saved' => count($ids)] );
} );

// ── SHORTCODE ────────────────────────────────────────────────────────────────
add_shortcode( 'objekti_form', function () {

    $uid = get_current_user_id();
    if ( ! $uid )
        return '<p>Please log in to manage your objects.</p>';

    // Fetch ALL published objekti
    $query = new WP_Query([
        'post_type'              => 'objekti',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'orderby'                => 'title',
        'order'                  => 'ASC',
        'no_found_rows'          => false,
        'update_post_meta_cache' => false,
    ]);
    $posts       = $query->posts;
    $total_found = $query->found_posts;

    // Current user's existing children
    $existing = [];
    if ( function_exists('jet_engine') && isset( jet_engine()->relations ) ) {
        $rel = jet_engine()->relations->get_active_relations( OBJ_REL_ID );
        if ( $rel ) {
            $ch = $rel->get_children( $uid, 'ids' );
            $existing = is_array($ch) ? array_map('intval', $ch) : [];
        }
    }

    // Build items JSON for JS
    $items = [];
    foreach ( $posts as $post ) {
        $terms   = get_the_terms( $post->ID, 'tips' );
        $t_names = ($terms && !is_wp_error($terms)) ? wp_list_pluck($terms,'name') : [];
        $items[] = [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'checked' => in_array( $post->ID, $existing, true ),
            'terms'   => $t_names,
        ];
    }

    $items_json   = wp_json_encode( $items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE );
    $ajax_url     = admin_url('admin-ajax.php');
    $nonce        = wp_create_nonce('obj_save_relations');
    $sel_count    = count($existing);

    $uid_attr = esc_attr( uniqid('obj-') ); // unique ID per instance

    ob_start();
    ?>
    <div id="<?php echo $uid_attr; ?>"></div>

    <script>
    (function() {
        var HOST = document.getElementById('<?php echo $uid_attr; ?>');

        // ── Attach Shadow DOM ─────────────────────────────────────────────
        var shadow = HOST.attachShadow({ mode: 'open' });

        // ── All styles live inside the shadow — zero theme interference ──
        var CSS = `
            * { box-sizing: border-box; margin: 0; padding: 0; }

            :host { display: block; max-width: 720px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

            /* Search */
            .search-label {
                display: block;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .1em;
                color: #555;
                margin-bottom: 6px;
            }
            .search-input {
                display: block;
                width: 100%;
                padding: 10px 14px;
                font-size: 15px;
                border: 1.5px solid #cdd8cd;
                border-radius: 8px;
                background: #fff;
                color: #111;
                outline: none;
                margin-bottom: 10px;
            }
            .search-input:focus {
                border-color: #5a8f5a;
                box-shadow: 0 0 0 3px rgba(90,143,90,.15);
            }

            /* Counter */
            .counter {
                font-size: 13px;
                color: #666;
                margin-bottom: 10px;
            }
            .counter .vis { color: #3a7a3a; font-weight: 700; }
            .counter strong { color: #222; font-weight: 700; }

            /* List */
            .list {
                display: flex;
                flex-direction: column;
                gap: 6px;
                max-height: 460px;
                overflow-y: auto;
                padding: 2px;
                margin-bottom: 4px;
            }

            /* Card */
            .card {
                display: flex;
                align-items: stretch;
                border: 1.5px solid #dde8dd;
                border-radius: 10px;
                background: #fff;
                overflow: hidden;
                cursor: pointer;
                transition: border-color .15s, box-shadow .15s;
                min-height: 50px;
            }
            .card:hover {
                border-color: #aacaaa;
                box-shadow: 0 2px 8px rgba(0,0,0,.07);
            }
            .card.checked {
                border-color: #6aaa6a;
                background: #f4fbf4;
            }
            .card.hidden { display: none; }

            /* Checkbox col */
            .card-cb {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 50px;
                flex-shrink: 0;
                border-right: 1.5px solid #dde8dd;
                background: #fafafa;
            }
            .card.checked .card-cb {
                background: #e2f3e2;
                border-right-color: #6aaa6a;
            }
            .card-cb input[type=checkbox] {
                width: 17px;
                height: 17px;
                accent-color: #4a8a4a;
                cursor: pointer;
            }

            /* Title + terms col */
            .card-body {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex: 1;
                gap: 10px;
                padding: 10px 14px;
                flex-wrap: wrap;
            }
            .card-title {
                font-size: 14px;
                font-weight: 500;
                color: #1a1a1a;
                line-height: 1.4;
                flex: 1;
            }

            /* Terms */
            .card-terms {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                justify-content: flex-end;
                flex-shrink: 0;
            }
            .term {
                font-size: 11px;
                font-weight: 600;
                padding: 3px 9px;
                border-radius: 20px;
                background: #d6e9d6;
                color: #2e6b2e;
                border: 1px solid #b8d8b8;
                white-space: nowrap;
            }
            .card.checked .term {
                background: #bfdebf;
                border-color: #8aba8a;
            }

            /* No results */
            .no-results {
                font-size: 13px;
                color: #999;
                padding: 14px 4px;
                display: none;
            }
            .no-results.visible { display: block; }

            /* Submit row */
            .actions {
                display: flex;
                align-items: center;
                gap: 14px;
                flex-wrap: wrap;
                margin-top: 14px;
            }
            .btn-save {
                padding: 10px 26px;
                background: #3a7a3a;
                color: #fff;
                border: none;
                border-radius: 7px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                min-width: 160px;
            }
            .btn-save:hover { background: #2d622d; }
            .btn-save:disabled { opacity: .6; cursor: not-allowed; }
            .msg {
                font-size: 13px;
                color: #333;
                display: none;
            }
            .msg.visible { display: block; }
        `;

        // ── Data passed from PHP ──────────────────────────────────────────
        var ITEMS     = <?php echo $items_json; ?>;
        var AJAX_URL  = '<?php echo esc_js($ajax_url); ?>';
        var NONCE     = '<?php echo esc_js($nonce); ?>';
        var TOTAL     = <?php echo (int)$total_found; ?>;
        var SEL_COUNT = <?php echo (int)$sel_count; ?>;

        // ── Build HTML ────────────────────────────────────────────────────
        var html = `
            <style>${CSS}</style>

            <span class="search-label">Search</span>
            <input class="search-input" type="search" placeholder="Filter objects…" autocomplete="off" />

            <div class="counter">
                Showing <span class="vis" id="vis-count">${TOTAL}</span>
                of <strong>${TOTAL}</strong> objects
                &nbsp;·&nbsp;
                <strong><span id="sel-count">${SEL_COUNT}</span></strong> selected
            </div>

            <div class="list" id="obj-list"></div>

            <div class="actions">
                <button class="btn-save" id="btn-save">Save Selection</button>
                <span class="msg" id="msg"></span>
            </div>
        `;

        shadow.innerHTML = html;

        // ── Render cards ──────────────────────────────────────────────────
        var list = shadow.getElementById('obj-list');

        ITEMS.forEach(function(item) {
            var div = document.createElement('div');
            div.className = 'card' + (item.checked ? ' checked' : '');
            div.dataset.title = item.title.toLowerCase();
            div.dataset.id    = item.id;

            // Terms HTML
            var termsHtml = '';
            if ( item.terms && item.terms.length ) {
                termsHtml = '<span class="card-terms">'
                    + item.terms.map(function(t) {
                        return '<span class="term">' + escHtml(t) + '</span>';
                    }).join('')
                    + '</span>';
            }

            div.innerHTML = `
                <span class="card-cb">
                    <input type="checkbox" ${item.checked ? 'checked' : ''} />
                </span>
                <span class="card-body">
                    <span class="card-title">${escHtml(item.title)}</span>
                    ${termsHtml}
                </span>
            `;

            // Click anywhere on card toggles checkbox
            div.addEventListener('click', function(e) {
                if ( e.target.tagName === 'INPUT' ) return; // let native handle it
                var cb = div.querySelector('input[type=checkbox]');
                cb.checked = ! cb.checked;
                updateCard(div, cb.checked);
            });

            div.querySelector('input[type=checkbox]').addEventListener('change', function(e) {
                updateCard(div, e.target.checked);
            });

            list.appendChild(div);
        });

        // No results message
        var noRes = document.createElement('p');
        noRes.className = 'no-results';
        noRes.id = 'no-results';
        noRes.textContent = 'No objects match your search.';
        list.appendChild(noRes);

        // ── Update card checked state ─────────────────────────────────────
        function updateCard(div, checked) {
            div.classList.toggle('checked', checked);
            recount();
        }

        // ── Recount selected ──────────────────────────────────────────────
        function recount() {
            var n = shadow.querySelectorAll('.card.checked').length;
            shadow.getElementById('sel-count').textContent = n;
        }

        // ── Live search ───────────────────────────────────────────────────
        shadow.querySelector('.search-input').addEventListener('input', function(e) {
            var term    = e.target.value.toLowerCase().trim();
            var cards   = shadow.querySelectorAll('.card');
            var visible = 0;

            cards.forEach(function(card) {
                var show = term === '' || card.dataset.title.includes(term);
                card.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            shadow.getElementById('vis-count').textContent = visible;
            var noR = shadow.getElementById('no-results');
            noR.classList.toggle('visible', visible === 0);
        });

        // ── Submit ────────────────────────────────────────────────────────
        var btn = shadow.getElementById('btn-save');
        var msg = shadow.getElementById('msg');

        btn.addEventListener('click', async function() {
            btn.disabled = true;
            btn.textContent = 'Saving…';
            msg.className = 'msg';

            var checked = shadow.querySelectorAll('.card.checked');
            var body = new URLSearchParams();
            body.append('action', 'obj_save_relations');
            body.append('nonce', NONCE);
            checked.forEach(function(card) {
                body.append('obj[]', card.dataset.id);
            });

            try {
                var res  = await fetch(AJAX_URL, {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:        body.toString()
                });
                var data = await res.json();
                msg.textContent = data.success
                    ? '✓ Saved ' + checked.length + ' object' + (checked.length!==1?'s':'') + ' successfully.'
                    : '✗ ' + (data.data?.message || 'Unknown error');
            } catch(err) {
                msg.textContent = '✗ Network error: ' + err.message;
            }

            msg.className = 'msg visible';
            btn.disabled = false;
            btn.textContent = 'Save Selection';
        });

        // ── Utility ───────────────────────────────────────────────────────
        function escHtml(str) {
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

    })();
    </script>
    <?php
    return ob_get_clean();
} );
