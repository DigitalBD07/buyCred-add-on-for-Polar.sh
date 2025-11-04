<?php
/**
 * Plugin Name: myCred Polar.sh Points Purchase
 * Plugin URI: https://devbd.net
 * Description: Purchase myCred points or subscribe via Polar.sh. Award points on the order.paid (one-time & recurring)
 * Version: 2.4.1
 * Author: Tanvir Haider
 * License: GPL-2.0-or-later
 * Text Domain: mycred-polar
 * Requires Plugins: mycred
 */

if (!defined('ABSPATH')) exit;

/* -----------------------------------------------------------
   Dependency & constants
----------------------------------------------------------- */
function mycred_polar_check_mycred() {
    if (!function_exists('mycred')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>myCred Polar.sh</strong> requires the myCred plugin to be installed and activated.</p></div>';
        });
        return false;
    }
    return true;
}
add_action('plugins_loaded', 'mycred_polar_check_mycred');

/* -----------------------------------------------------------
   Admin menu
----------------------------------------------------------- */
function mycred_polar_add_admin_menu() {
    add_menu_page('myCred Polar.sh', 'myCred Polar.sh', 'manage_options', 'mycred_polar_settings', 'mycred_polar_settings_page_html', 'dashicons-money-alt', 80);
    add_submenu_page('mycred_polar_settings', 'Transaction Logs', 'Transaction Logs', 'manage_options', 'mycred_polar_logs', 'mycred_polar_logs_page_html');
}
add_action('admin_menu', 'mycred_polar_add_admin_menu');

/* -----------------------------------------------------------
   Options
----------------------------------------------------------- */
function mycred_polar_settings_init() {
    register_setting('mycred_polar_settings_group', 'mycred_polar_options', 'mycred_polar_sanitize_options');

    add_settings_section('mycred_polar_main_section', 'Main Configuration', null, 'mycred_polar_settings');

    $fields = array(
        'mode' => array('Payment Mode', 'mycred_polar_field_mode_html'),
        'access_token_live' => array('Live Access Token', 'mycred_polar_field_access_token_live_html'),
        'access_token_sandbox' => array('Sandbox Access Token', 'mycred_polar_field_access_token_sandbox_html'),
        'product_id_live' => array('Live One‑Time Product ID (PWYW or fixed)', 'mycred_polar_field_product_id_live_html'),
        'product_id_sandbox' => array('Sandbox One‑Time Product ID (PWYW or fixed)', 'mycred_polar_field_product_id_sandbox_html'),
        'exchange_rate' => array('Exchange Rate ($ per Point)', 'mycred_polar_field_exchange_rate_html'),
        'min_points' => array('Minimum Points', 'mycred_polar_field_min_points_html'),
        'default_points' => array('Default Points', 'mycred_polar_field_default_points_html'),
        'point_type' => array('myCred Point Type', 'mycred_polar_field_point_type_html'),
        'webhook_secret' => array('Polar Webhook Secret (whsec_…)', 'mycred_polar_field_webhook_secret_html'),
        'subscription_plans' => array('Subscription Plans', 'mycred_polar_field_subscription_plans_html'),
        'log_entry' => array('myCred Log Entry Template', 'mycred_polar_field_log_entry_html'),
    );

    foreach ($fields as $key => $field) {
        add_settings_field('mycred_polar_' . $key, $field[0], $field[1], 'mycred_polar_settings', 'mycred_polar_main_section');
    }
}
add_action('admin_init', 'mycred_polar_settings_init');

function mycred_polar_get_options() {
    $defaults = array(
        'mode' => 'sandbox',
        'access_token_live' => '',
        'access_token_sandbox' => '',
        'product_id_live' => '',
        'product_id_sandbox' => '',
        'exchange_rate' => 0.10,
        'min_points' => 50,
        'default_points' => 100,
        'point_type' => 'mycred_default',
        'webhook_secret' => '',
        'subscription_plans' => array(), // array of arrays: name, product_id, points_per_cycle, use_custom_amount
        'log_entry' => 'Points purchased via Polar.sh (Order: %order_id%)',
    );
    $stored = get_option('mycred_polar_options', array());
    if (!is_array($stored)) $stored = array();
    $merged = wp_parse_args($stored, $defaults);

    // Ensure plans is array
    if (!is_array($merged['subscription_plans'])) $merged['subscription_plans'] = array();
    return $merged;
}

function mycred_polar_sanitize_options($input) {
    $san = array();
    $san['mode'] = (!empty($input['mode']) && $input['mode'] === 'live') ? 'live' : 'sandbox';
    $san['access_token_live'] = sanitize_text_field($input['access_token_live'] ?? '');
    $san['access_token_sandbox'] = sanitize_text_field($input['access_token_sandbox'] ?? '');
    $san['product_id_live'] = sanitize_text_field($input['product_id_live'] ?? '');
    $san['product_id_sandbox'] = sanitize_text_field($input['product_id_sandbox'] ?? '');
    $san['exchange_rate'] = floatval($input['exchange_rate'] ?? 0.10);
    $san['min_points'] = intval($input['min_points'] ?? 50);
    $san['default_points'] = intval($input['default_points'] ?? 100);
    $san['point_type'] = sanitize_text_field($input['point_type'] ?? 'mycred_default');
    $san['webhook_secret'] = sanitize_text_field($input['webhook_secret'] ?? '');
    $san['log_entry'] = sanitize_text_field($input['log_entry'] ?? 'Points purchased via Polar.sh (Order: %order_id%)');

    // Plans
    $plans = array();
    if (!empty($input['subscription_plans_json'])) {
        $dec = json_decode(wp_unslash($input['subscription_plans_json']), true);
        if (is_array($dec)) {
            foreach ($dec as $p) {
                $plans[] = array(
                    'name' => sanitize_text_field($p['name'] ?? ''),
                    'product_id' => sanitize_text_field($p['product_id'] ?? ''),
                    'points_per_cycle' => intval($p['points_per_cycle'] ?? 0),
                    'use_custom_amount' => !empty($p['use_custom_amount']) ? 1 : 0,
                );
            }
        }
    } elseif (!empty($input['subscription_plans']) && is_array($input['subscription_plans'])) {
        foreach ($input['subscription_plans'] as $p) {
            $plans[] = array(
                'name' => sanitize_text_field($p['name'] ?? ''),
                'product_id' => sanitize_text_field($p['product_id'] ?? ''),
                'points_per_cycle' => intval($p['points_per_cycle'] ?? 0),
                'use_custom_amount' => !empty($p['use_custom_amount']) ? 1 : 0,
            );
        }
    }
    $san['subscription_plans'] = $plans;

    return $san;
}

/* -----------------------------------------------------------
   Settings UI fields
----------------------------------------------------------- */
function mycred_polar_field_mode_html() {
    $o = mycred_polar_get_options(); $mode = $o['mode']; ?>
    <select name="mycred_polar_options[mode]">
        <option value="sandbox" <?php selected($mode,'sandbox'); ?>>Sandbox (Test)</option>
        <option value="live" <?php selected($mode,'live'); ?>>Live (Production)</option>
    </select>
    <p class="description">Sandbox is safe for test cards.</p>
<?php }
function mycred_polar_field_access_token_live_html() { $o = mycred_polar_get_options(); ?>
    <input type="password" name="mycred_polar_options[access_token_live]" value="<?php echo esc_attr($o['access_token_live']); ?>" class="regular-text">
    <p class="description">Live Organization Access Token (starts with polar_at_). Scopes: products:read, checkouts:write, orders:read, subscriptions:read.</p>
<?php }
function mycred_polar_field_access_token_sandbox_html() { $o = mycred_polar_get_options(); ?>
    <input type="password" name="mycred_polar_options[access_token_sandbox]" value="<?php echo esc_attr($o['access_token_sandbox']); ?>" class="regular-text">
    <p class="description">Sandbox Organization Access Token (starts with polar_at_).</p>
<?php }
function mycred_polar_field_product_id_live_html() { $o = mycred_polar_get_options(); ?>
    <input type="text" name="mycred_polar_options[product_id_live]" value="<?php echo esc_attr($o['product_id_live']); ?>" class="regular-text">
    <p class="description">Live Product ID for one-time points. PWYW or fixed. Example: prod_xxxxx (UUID).</p>
<?php }
function mycred_polar_field_product_id_sandbox_html() { $o = mycred_polar_get_options(); ?>
    <input type="text" name="mycred_polar_options[product_id_sandbox]" value="<?php echo esc_attr($o['product_id_sandbox']); ?>" class="regular-text">
    <p class="description">Sandbox Product ID for one-time points.</p>
<?php }
function mycred_polar_field_exchange_rate_html() { $o = mycred_polar_get_options(); ?>
    <input type="number" step="0.001" min="0.001" name="mycred_polar_options[exchange_rate]" value="<?php echo esc_attr($o['exchange_rate']); ?>" class="small-text"> USD per point
<?php }
function mycred_polar_field_min_points_html() { $o = mycred_polar_get_options(); ?>
    <input type="number" step="1" min="1" name="mycred_polar_options[min_points]" value="<?php echo esc_attr($o['min_points']); ?>" class="small-text">
<?php }
function mycred_polar_field_default_points_html() { $o = mycred_polar_get_options(); ?>
    <input type="number" step="1" min="1" name="mycred_polar_options[default_points]" value="<?php echo esc_attr($o['default_points']); ?>" class="small-text">
<?php }
function mycred_polar_field_point_type_html() {
    $o = mycred_polar_get_options();
    $pt = $o['point_type']; $types = array('mycred_default' => 'Default Points');
    if (function_exists('mycred_get_types')) {
        $mts = mycred_get_types(); if (!empty($mts)) { $types=array(); foreach($mts as $k=>$lbl) $types[$k]=$lbl; }
    } ?>
    <select name="mycred_polar_options[point_type]">
        <?php foreach($types as $k=>$lbl): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($pt,$k); ?>><?php echo esc_html($lbl); ?></option>
        <?php endforeach; ?>
    </select>
<?php }
function mycred_polar_field_webhook_secret_html() { $o = mycred_polar_get_options(); $url=esc_html(rest_url('mycred-polar/v1/webhook')); ?>
    <input type="text" name="mycred_polar_options[webhook_secret]" value="<?php echo esc_attr($o['webhook_secret']); ?>" class="regular-text">
    <p class="description">Paste your Polar webhook secret (whsec_…). Set Webhook Format: Raw. Enable event: <code>order.paid</code> (you can leave <code>order.updated</code> off). Endpoint: <code><?php echo $url; ?></code></p>
<?php }
function mycred_polar_field_subscription_plans_html() {
    $o = mycred_polar_get_options();
    $plans = $o['subscription_plans']; ?>
    <style>
        .mp-sub-table th, .mp-sub-table td { padding:6px; }
        .mp-sub-table input[type="text"], .mp-sub-table input[type="number"] { width: 100%; }
        .mp-sub-compact { font-size:12px; color:#666; }
        .mp-sub-del { color:#b32d2e; cursor:pointer; }
        .mp-sub-add { margin-top:8px; }
        textarea#mp-plans-json { width:100%; height:120px; }
    </style>
    <p>Define subscription plans (recurring Polar products). For PWYW subscriptions, check “Use custom amount” and the amount will be computed from points_per_cycle × exchange_rate.</p>
    <table class="widefat mp-sub-table">
        <thead><tr><th>Name (shown to users)</th><th>Polar Product ID (recurring)</th><th>Points per cycle</th><th>Use custom amount?</th><th></th></tr></thead>
        <tbody id="mp-plans-body">
        <?php if (empty($plans)): ?>
            <tr>
                <td><input type="text" data-k="name" value=""></td>
                <td><input type="text" data-k="product_id" value=""></td>
                <td><input type="number" min="1" step="1" data-k="points_per_cycle" value="100"></td>
                <td style="text-align:center;"><input type="checkbox" data-k="use_custom_amount"></td>
                <td><span class="mp-sub-del" title="Remove">✕</span></td>
            </tr>
        <?php else: foreach($plans as $p): ?>
            <tr>
                <td><input type="text" data-k="name" value="<?php echo esc_attr($p['name']); ?>"></td>
                <td><input type="text" data-k="product_id" value="<?php echo esc_attr($p['product_id']); ?>"></td>
                <td><input type="number" min="1" step="1" data-k="points_per_cycle" value="<?php echo esc_attr($p['points_per_cycle']); ?>"></td>
                <td style="text-align:center;"><input type="checkbox" data-k="use_custom_amount" <?php checked(!empty($p['use_custom_amount'])); ?>></td>
                <td><span class="mp-sub-del" title="Remove">✕</span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <button type="button" class="button mp-sub-add">+ Add Plan</button>
    <p class="mp-sub-compact">These plans are stored as JSON below for reliability. You can also edit the JSON directly if needed.</p>
    <textarea id="mp-plans-json" name="mycred_polar_options[subscription_plans_json]"><?php echo esc_textarea(wp_json_encode($plans)); ?></textarea>
    <script>
    (function(){
        const body = document.getElementById('mp-plans-body');
        const jsonTA = document.getElementById('mp-plans-json');
        function readTable(){
            const rows = [...body.querySelectorAll('tr')];
            const out = [];
            rows.forEach(tr=>{
                const name = tr.querySelector('input[data-k="name"]')?.value?.trim() || '';
                const product_id = tr.querySelector('input[data-k="product_id"]')?.value?.trim() || '';
                const points = parseInt(tr.querySelector('input[data-k="points_per_cycle"]')?.value || '0', 10) || 0;
                const custom = tr.querySelector('input[data-k="use_custom_amount"]')?.checked ? 1 : 0;
                if (name || product_id) out.push({name, product_id, points_per_cycle: points, use_custom_amount: custom});
            });
            jsonTA.value = JSON.stringify(out, null, 2);
        }
        function addRow(data={}){
            const tr = document.createElement('tr');
            tr.innerHTML = `<td><input type="text" data-k="name" value="${data.name||''}"></td>
                <td><input type="text" data-k="product_id" value="${data.product_id||''}"></td>
                <td><input type="number" min="1" step="1" data-k="points_per_cycle" value="${data.points_per_cycle||100}"></td>
                <td style="text-align:center;"><input type="checkbox" data-k="use_custom_amount" ${data.use_custom_amount? 'checked':''}></td>
                <td><span class="mp-sub-del" title="Remove">✕</span></td>`;
            body.appendChild(tr);
        }
        document.querySelector('.mp-sub-add').addEventListener('click', ()=>{ addRow(); });
        body.addEventListener('input', readTable);
        body.addEventListener('change', readTable);
        body.addEventListener('click', (e)=>{ if (e.target.classList.contains('mp-sub-del')) { e.target.closest('tr').remove(); readTable(); }});
        // keep JSON and table in sync on initial display
        body.addEventListener('DOMNodeInserted', readTable);
        readTable();
    })();
    </script>
<?php }
function mycred_polar_field_log_entry_html() { $o = mycred_polar_get_options(); ?>
    <input type="text" name="mycred_polar_options[log_entry]" value="<?php echo esc_attr($o['log_entry']); ?>" class="regular-text">
    <p class="description">Use %points%, %order_id%, %amount%. Example: "Points purchased via Polar.sh (Order: %order_id%)".</p>
<?php }

/* -----------------------------------------------------------
   Settings page shell
----------------------------------------------------------- */
function mycred_polar_settings_page_html() {
    if (!current_user_can('manage_options')) return;
    $webhook_url = esc_html(rest_url('mycred-polar/v1/webhook')); ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="notice notice-info" style="padding: 15px;">
            <h3>Setup</h3>
            <ol>
                <li>Create a Polar product for one-time points (PWYW or fixed). For subscriptions, create monthly or yearly products.</li>
                <li>Create an Access Token with scopes: <code>products:read</code>, <code>checkouts:write</code>, <code>orders:read</code>, <code>subscriptions:read</code>.</li>
                <li>Webhooks (Format: Raw): Endpoint <code><?php echo $webhook_url; ?></code>. Enable <code>order.paid</code>.</li>
                <li>Fill the form below and Save. Then Test Connection.</li>
                <li>Place <code>[mycred_polar_form]</code> on a page.</li>
            </ol>
        </div>

        <form action="options.php" method="post">
            <?php settings_fields('mycred_polar_settings_group'); ?>
            <?php do_settings_sections('mycred_polar_settings'); ?>
            <?php submit_button('Save Settings'); ?>
        </form>

        <hr>
        <h2>🧪 Test Connection</h2>
        <button type="button" id="mp-test-btn" class="button button-primary">Test Connection</button>
        <div id="mp-test-result" style="margin-top:10px;"></div>

        <script>
        (function(){
            const btn = document.getElementById('mp-test-btn');
            const res = document.getElementById('mp-test-result');
            btn.addEventListener('click', function(){
                btn.disabled = true; btn.textContent='Testing...'; res.innerHTML='';
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'action=mycred_polar_test_connection&nonce=<?php echo wp_create_nonce('mycred_polar_test'); ?>'
                }).then(r=>r.json()).then(d=>{
                    if (d.success) res.innerHTML = '<div class="notice notice-success inline" style="padding:10px;"><p>✅ '+d.data.message+'</p></div>';
                    else res.innerHTML = '<div class="notice notice-error inline" style="padding:10px;"><p>❌ '+(d.data?.message||'Failed')+'</p></div>';
                }).catch(e=>{
                    res.innerHTML = '<div class="notice notice-error inline" style="padding:10px;"><p>❌ '+e.message+'</p></div>';
                }).finally(()=>{ btn.disabled=false; btn.textContent='Test Connection'; });
            });
        })();
        </script>
    </div>
    <?php
}

/* -----------------------------------------------------------
   Logs page
----------------------------------------------------------- */
function mycred_polar_logs_page_html() {
    global $wpdb;
    $table = $wpdb->prefix . 'mycred_polar_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
    ?>
    <div class="wrap">
        <h1>Transaction Logs</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Date</th><th>User</th><th>Points</th><th>Amount</th><th>Order/Checkout ID</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6">No transactions yet.</td></tr>
            <?php else: foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td><?php echo esc_html(get_userdata($log->user_id)->user_login ?? 'Unknown'); ?></td>
                    <td><?php echo esc_html($log->points); ?></td>
                    <td>$<?php echo esc_html(number_format($log->amount / 100, 2)); ?></td>
                    <td><?php echo esc_html($log->order_id); ?></td>
                    <td><?php echo esc_html($log->status); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* -----------------------------------------------------------
   Activation: logs table + rewrites
----------------------------------------------------------- */
function mycred_polar_ensure_logs_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'mycred_polar_logs';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        order_id varchar(255) NOT NULL,
        points int(11) NOT NULL,
        amount int(11) NOT NULL,
        status varchar(50) NOT NULL,
        webhook_data longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY order_id (order_id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('plugins_loaded', 'mycred_polar_ensure_logs_table');

function mycred_polar_rewrite_rules() { add_rewrite_rule('^mycred-success/?', 'index.php?mycred_polar_success=1', 'top'); }
add_action('init', 'mycred_polar_rewrite_rules');
function mycred_polar_query_vars($vars) { $vars[]='mycred_polar_success'; return $vars; }
add_filter('query_vars', 'mycred_polar_query_vars');
function mycred_polar_template_redirect() { if (get_query_var('mycred_polar_success')) mycred_polar_success_page(); }
add_action('template_redirect', 'mycred_polar_template_redirect');

function mycred_polar_activate() {
    mycred_polar_ensure_logs_table();
    mycred_polar_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'mycred_polar_activate');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

/* -----------------------------------------------------------
   Shortcode UI
----------------------------------------------------------- */
function mycred_polar_render_form_shortcode() {
    if (!mycred_polar_check_mycred()) return '<p style="color:red;">myCred plugin is required.</p>';
    if (!is_user_logged_in()) return '<p>You must be logged in to purchase points. <a href="'.esc_url(wp_login_url(get_permalink())).'">Login here</a>.</p>';

    $o = mycred_polar_get_options();
    $ex = floatval($o['exchange_rate']);
    $minp = intval($o['min_points']);
    $defp = intval($o['default_points']);
    $plans = $o['subscription_plans'];
    $user = wp_get_current_user();
    $myc = mycred($o['point_type']);
    $bal = $myc->get_users_balance($user->ID);

    ob_start(); ?>
    <div id="mycred-polar-form-wrapper" style="max-width: 750px; padding: 20px; border: 2px solid #0073aa; border-radius: 8px; background: #f9f9f9;">
        <h3 style="margin-top:0;">💎 myCred Points</h3>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <div style="flex:1; min-width:320px; background:#fff; padding:15px; border-radius:6px;">
                <h4>One‑time purchase</h4>
                <p><strong>Current Balance:</strong> <?php echo $myc->format_creds($bal); ?></p>
                <p><strong>Rate:</strong> $<?php echo esc_html(number_format($ex,3)); ?> per point</p>
                <label><strong>Points:</strong></label>
                <input type="number" id="mp-pts" value="<?php echo esc_attr($defp); ?>" min="<?php echo esc_attr($minp); ?>" step="1" style="width:100%; padding:10px; margin:8px 0; border:1px solid #ddd; border-radius:4px;">
                <div style="background:#0073aa;color:#fff;padding:10px;border-radius:4px;text-align:center;">
                    <strong>$<span id="mp-cost">0.00</span></strong>
                </div>
                <button id="mp-buy" class="button button-primary" style="margin-top:10px;width:100%;">🛒 Purchase Now</button>
                <p id="mp-one-err" style="color:red; display:none; font-weight:bold;"></p>
                <p id="mp-one-load" style="display:none;">⏳ Creating checkout…</p>
            </div>

            <div style="flex:1; min-width:320px; background:#fff; padding:15px; border-radius:6px;">
                <h4>Subscription (recurring points)</h4>
                <?php if (empty($plans)): ?>
                    <p>No subscription plans configured. Ask admin to add some in settings.</p>
                <?php else: ?>
                    <label><strong>Select plan:</strong></label>
                    <select id="mp-plan" style="width:100%; padding:10px; margin:8px 0; border:1px solid #ddd; border-radius:4px;">
                        <?php foreach($plans as $idx=>$p): 
                            $amt = !empty($p['use_custom_amount']) ? (intval($p['points_per_cycle']) * $ex) : null;
                            $label = $p['name'].' — '.$p['points_per_cycle'].' pts/cycle';
                            if ($amt !== null) $label .= ' ($'.number_format($amt,2).')';
                        ?>
                            <option value="<?php echo esc_attr($idx); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="mp-sub" class="button button-primary" style="width:100%;">🔁 Subscribe</button>
                    <p id="mp-sub-err" style="color:red; display:none; font-weight:bold;"></p>
                    <p id="mp-sub-load" style="display:none;">⏳ Creating subscription checkout…</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const ex = <?php echo json_encode($ex); ?>;
        const minp = <?php echo json_encode($minp); ?>;
        const pts = document.getElementById('mp-pts');
        const cost = document.getElementById('mp-cost');
        const bbtn = document.getElementById('mp-buy');
        const berr = document.getElementById('mp-one-err');
        const bload= document.getElementById('mp-one-load');
        function recalc(){ const p = parseInt(pts.value)||0; cost.textContent=(p*ex).toFixed(2); }
        pts?.addEventListener('input', recalc); recalc();

        bbtn?.addEventListener('click', function(){
            const p = parseInt(pts.value);
            if (isNaN(p)||p<minp){ berr.textContent='Please enter at least '+minp+' points.'; berr.style.display='block'; return; }
            berr.style.display='none'; bload.style.display='block'; bbtn.disabled=true;
            const cents = Math.round(p*ex*100);
            const fd = new URLSearchParams();
            fd.append('action','mycred_polar_create_checkout');
            fd.append('points', p);
            fd.append('amount', cents);
            fd.append('nonce','<?php echo wp_create_nonce('mycred_polar_checkout'); ?>');
            fetch('<?php echo admin_url('admin-ajax.php'); ?>',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString() })
              .then(async r=>{ const t=await r.text(); try{return JSON.parse(t);}catch(e){ throw new Error('AJAX returned non‑JSON: '+t.slice(0,300)); }})
              .then(d=>{ if(d.success && d.data.url){ window.location.href = d.data.url; } else { berr.textContent='Error: '+(d.data?.message||'Failed'); berr.style.display='block'; }})
              .catch(e=>{ berr.textContent='Error: '+e.message; berr.style.display='block'; })
              .finally(()=>{ bload.style.display='none'; bbtn.disabled=false; });
        });

        const sbtn = document.getElementById('mp-sub');
        const sel  = document.getElementById('mp-plan');
        const serr = document.getElementById('mp-sub-err');
        const sload= document.getElementById('mp-sub-load');
        sbtn?.addEventListener('click', function(){
            if (!sel) return;
            serr.style.display='none'; sload.style.display='block'; sbtn.disabled=true;
            const fd = new URLSearchParams();
            fd.append('action','mycred_polar_create_subscription_checkout');
            fd.append('plan_index', sel.value);
            fd.append('nonce','<?php echo wp_create_nonce('mycred_polar_checkout'); ?>');
            fetch('<?php echo admin_url('admin-ajax.php'); ?>',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString() })
              .then(async r=>{ const t=await r.text(); try{return JSON.parse(t);}catch(e){ throw new Error('AJAX returned non‑JSON: '+t.slice(0,300)); }})
              .then(d=>{ if(d.success && d.data.url){ window.location.href = d.data.url; } else { serr.textContent='Error: '+(d.data?.message||'Failed'); serr.style.display='block'; }})
              .catch(e=>{ serr.textContent='Error: '+e.message; serr.style.display='block'; })
              .finally(()=>{ sload.style.display='none'; sbtn.disabled=false; });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('mycred_polar_form', 'mycred_polar_render_form_shortcode');

/* -----------------------------------------------------------
   AJAX: One-time checkout
----------------------------------------------------------- */
function mycred_polar_create_checkout() {
    check_ajax_referer('mycred_polar_checkout','nonce');
    if (!is_user_logged_in()) wp_send_json_error(array('message'=>'User not logged in'));

    $points = intval($_POST['points'] ?? 0);
    $amount = intval($_POST['amount'] ?? 0); // cents
    $user   = wp_get_current_user();
    $o = mycred_polar_get_options();

    $mode = $o['mode']; $access = ($mode==='live')? $o['access_token_live'] : $o['access_token_sandbox'];
    $product_id = ($mode==='live')? $o['product_id_live'] : $o['product_id_sandbox'];
    $api = ($mode==='live')? 'https://api.polar.sh' : 'https://sandbox-api.polar.sh';

    if (empty($access) || empty($product_id)) wp_send_json_error(array('message'=>'Polar.sh not configured.'));

    $payload = array(
        'products' => array($product_id),
        'amount'   => $amount, // needed for PWYW; ignored for fixed
        'customer_email' => $user->user_email,
        'external_customer_id' => (string)$user->ID,
        'metadata' => array(
            'user_id' => (string)$user->ID,
            'points'  => (string)$points,
            'amount_cents' => (string)$amount,
            'wp_user_email' => $user->user_email,
            'reason' => 'one_time_points'
        ),
        'success_url' => get_site_url().'/mycred-success?checkout_id={CHECKOUT_ID}',
    );

    $resp = wp_remote_post($api.'/v1/checkouts', array(
        'headers'=>array('Authorization'=>'Bearer '.$access,'Content-Type'=>'application/json'),
        'body'=> wp_json_encode($payload),
        'timeout'=>30,
    ));
    if (is_wp_error($resp)) wp_send_json_error(array('message'=>'API Error: '.$resp->get_error_message()));
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (($code===200||$code===201) && isset($body['url'])) {
        wp_send_json_success(array('url'=>$body['url'],'checkout_id'=>$body['id']??''));
    }
    $err = is_array($body)? ($body['detail'] ?? ($body['message'] ?? 'Unknown error')) : 'Unexpected response';
    wp_send_json_error(array('message'=>'Checkout failed: '.$err,'status_code'=>$code,'response'=>$body));
}
add_action('wp_ajax_mycred_polar_create_checkout','mycred_polar_create_checkout');

/* -----------------------------------------------------------
   AJAX: Subscription checkout
----------------------------------------------------------- */
function mycred_polar_create_subscription_checkout() {
    check_ajax_referer('mycred_polar_checkout','nonce');
    if (!is_user_logged_in()) wp_send_json_error(array('message'=>'User not logged in'));

    $idx = intval($_POST['plan_index'] ?? -1);
    $o = mycred_polar_get_options();
    $plans = $o['subscription_plans'];
    if ($idx < 0 || !isset($plans[$idx])) wp_send_json_error(array('message'=>'Invalid plan.'));

    $plan = $plans[$idx];
    $user = wp_get_current_user();

    $mode = $o['mode']; $access = ($mode==='live')? $o['access_token_live'] : $o['access_token_sandbox'];
    $api  = ($mode==='live')? 'https://api.polar.sh' : 'https://sandbox-api.polar.sh';

    if (empty($access) || empty($plan['product_id'])) wp_send_json_error(array('message'=>'Polar.sh not configured.'));

    $use_custom = !empty($plan['use_custom_amount']);
    $amount = 0;
    if ($use_custom) {
        $amount = intval(round(floatval($o['exchange_rate']) * intval($plan['points_per_cycle']) * 100));
    }

    $meta = array(
        'user_id' => (string)$user->ID,
        'points_per_cycle' => (string)intval($plan['points_per_cycle']),
        'plan_name' => (string)$plan['name'],
        'plan_index' => (string)$idx,
        'wp_user_email' => $user->user_email,
        'reason' => 'subscription_points'
    );

    $payload = array(
        'products' => array($plan['product_id']), // recurring product
        'customer_email' => $user->user_email,
        'external_customer_id' => (string)$user->ID,
        'metadata' => $meta,
        'success_url' => get_site_url().'/mycred-success?checkout_id={CHECKOUT_ID}',
    );
    if ($use_custom) $payload['amount'] = $amount; // only for PWYW subs

    $resp = wp_remote_post($api.'/v1/checkouts', array(
        'headers'=>array('Authorization'=>'Bearer '.$access,'Content-Type'=>'application/json'),
        'body'=> wp_json_encode($payload),
        'timeout'=>30,
    ));
    if (is_wp_error($resp)) wp_send_json_error(array('message'=>'API Error: '.$resp->get_error_message()));
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (($code===200||$code===201) && isset($body['url'])) {
        wp_send_json_success(array('url'=>$body['url'],'checkout_id'=>$body['id']??''));
    }
    $err = is_array($body)? ($body['detail'] ?? ($body['message'] ?? 'Unknown error')) : 'Unexpected response';
    wp_send_json_error(array('message'=>'Subscription checkout failed: '.$err,'status_code'=>$code,'response'=>$body));
}
add_action('wp_ajax_mycred_polar_create_subscription_checkout','mycred_polar_create_subscription_checkout');

/* -----------------------------------------------------------
   AJAX: Test connection
----------------------------------------------------------- */
function mycred_polar_test_connection() {
    check_ajax_referer('mycred_polar_test','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'Permission denied'));

    $o = mycred_polar_get_options();
    $mode = $o['mode']; $access = ($mode==='live')? $o['access_token_live'] : $o['access_token_sandbox'];
    $api = ($mode==='live')? 'https://api.polar.sh' : 'https://sandbox-api.polar.sh';

    if (empty($access)) wp_send_json_error(array('message'=>'Access token not set.'));
    $resp = wp_remote_get($api.'/v1/products', array('headers'=>array('Authorization'=>'Bearer '.$access,'Content-Type'=>'application/json'),'timeout'=>15));
    if (is_wp_error($resp)) wp_send_json_error(array('message'=>'Connection error: '.$resp->get_error_message()));
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code===200) {
        wp_send_json_success(array('message'=>'Connection OK ('.ucfirst($mode).') — products: '.(isset($body['items'])? count($body['items']) : 'N/A')));
    }
    $msg = is_array($body)? ($body['detail'] ?? ($body['message'] ?? 'Unknown error')) : 'Unknown error';
    wp_send_json_error(array('message'=>'Failed ('.$code.'): '.$msg));
}
add_action('wp_ajax_mycred_polar_test_connection','mycred_polar_test_connection');

/* -----------------------------------------------------------
   REST: Webhook (order.paid only)
----------------------------------------------------------- */
function mycred_polar_register_webhook_endpoint() {
    register_rest_route('mycred-polar/v1','/webhook', array(
        'methods'=>'POST',
        'callback'=>'mycred_polar_handle_webhook',
        'permission_callback'=>'__return_true',
    ));
}
add_action('rest_api_init','mycred_polar_register_webhook_endpoint');

/* Svix/Standard Webhooks verification */
function mycred_polar_verify_webhook_signature_std(WP_REST_Request $request, string $payload, string $secret): bool {
    $id = $request->get_header('webhook-id') ?: $request->get_header('svix-id');
    $ts = $request->get_header('webhook-timestamp') ?: $request->get_header('svix-timestamp');
    $sig= $request->get_header('webhook-signature') ?: $request->get_header('svix-signature');

    if (empty($id)||empty($ts)||empty($sig)) { error_log('Polar Webhook: missing required headers'); return false; }
    if (!ctype_digit((string)$ts) || abs(time() - (int)$ts) > 300) { error_log('Polar Webhook: timestamp outside tolerance'); return false; }

    // Secret whsec_... (base64 or base64url)
    $raw = preg_match('/^whsec_(.+)$/', trim($secret), $m) ? $m[1] : trim($secret);
    $key = base64_decode($raw, true);
    if ($key === false) {
        $b64 = strtr($raw, '-_', '+/'); $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $key = base64_decode($b64, true);
    }
    if ($key === false) { error_log('Polar Webhook: invalid secret after normalization'); return false; }

    $signed = $id.'.'.$ts.'.'.$payload;
    $expected = base64_encode(hash_hmac('sha256', $signed, $key, true));

    // Signature may contain multiple entries; accept v1,xxx or v1=xxx
    foreach (preg_split('/\s+/', trim($sig)) as $entry) {
        $entry = trim($entry); if ($entry==='') continue;
        $cand = null;
        if (stripos($entry, 'v1,')===0) $cand = substr($entry, 3);
        elseif (stripos($entry, 'v1=')===0) $cand = substr($entry, 3);
        if ($cand && hash_equals($expected, $cand)) { error_log('Polar Webhook: signature verified'); return true; }
    }
    error_log('Polar Webhook: signature verification failed');
    return false;
}

/* Award points (idempotent + short lock) */
function mycred_polar_award_points($user_id, $points, $order_id, $amount_cents, $raw_payload = '') {
    global $wpdb;
    mycred_polar_ensure_logs_table();

    $lock_key = 'mycred_polar_lock_'.md5($order_id);
    if (get_transient($lock_key)) { error_log('Polar Award: lock present for '.$order_id); return true; }
    set_transient($lock_key, 1, 60);

    $table = $wpdb->prefix . 'mycred_polar_logs';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE order_id=%s LIMIT 1", $order_id));
    if ($exists) { delete_transient($lock_key); error_log('Polar Award: already processed '.$order_id); return true; }

    $o = mycred_polar_get_options();
    $pt = $o['point_type'] ?? 'mycred_default';
    $log = $o['log_entry'] ?? 'Points purchased via Polar.sh (Order: %order_id%)';
    $log = str_replace(array('%points%','%order_id%','%amount%'), array($points,$order_id,'$'.number_format($amount_cents/100,2)), $log);

    if (!function_exists('mycred_add')) { delete_transient($lock_key); return false; }

    $ok = mycred_add('polar_purchase', $user_id, $points, $log, 0, '', $pt);
    if ($ok === false) {
        $wpdb->insert($table, array(
            'user_id'=>$user_id,'order_id'=>$order_id,'points'=>$points,'amount'=>$amount_cents,'status'=>'failed','webhook_data'=>$raw_payload
        ));
        delete_transient($lock_key);
        return false;
    }

    $wpdb->insert($table, array(
        'user_id'=>$user_id,'order_id'=>$order_id,'points'=>$points,'amount'=>$amount_cents,'status'=>'success','webhook_data'=>$raw_payload
    ));
    delete_transient($lock_key);
    error_log("Polar Award: +{$points} pts to user {$user_id} (order {$order_id})");
    return true;
}

/* Helper: fetch subscription to read metadata (for renewals that lack metadata) */
function mycred_polar_fetch_subscription_meta($sub_id) {
    $o = mycred_polar_get_options();
    $mode = $o['mode']; $access = ($mode==='live')? $o['access_token_live'] : $o['access_token_sandbox'];
    $api  = ($mode==='live')? 'https://api.polar.sh' : 'https://sandbox-api.polar.sh';
    if (empty($access) || empty($sub_id)) return array();
    $r = wp_remote_get($api.'/v1/subscriptions/'.rawurlencode($sub_id), array('headers'=>array('Authorization'=>'Bearer '.$access,'Content-Type'=>'application/json'),'timeout'=>15));
    if (is_wp_error($r)) return array();
    $code = wp_remote_retrieve_response_code($r);
    $body = json_decode(wp_remote_retrieve_body($r), true);
    if ($code===200 && is_array($body)) return $body['metadata'] ?? array();
    return array();
}

/* Webhook handler: ONLY order.paid (prevents double credits). Ignores checkout.updated. */
function mycred_polar_handle_webhook($request) {
    if (!mycred_polar_check_mycred()) return new WP_REST_Response(array('error'=>'myCred not active'), 500);

    $o = mycred_polar_get_options();
    $secret = $o['webhook_secret'] ?? '';

    $payload = $request->get_body();
    error_log('Polar Webhook: received '.strlen($payload).' bytes');

    if (!empty($secret)) {
        if (!mycred_polar_verify_webhook_signature_std($request, $payload, $secret)) {
            return new WP_REST_Response(array('error'=>'Invalid signature'), 403);
        }
    } else {
        error_log('Polar Webhook: verification disabled');
    }

    $evt = json_decode($payload, true);
    if (!is_array($evt)) return new WP_REST_Response(array('error'=>'Invalid payload'), 400);

    $type = $evt['type'] ?? '';
    error_log('Polar Webhook: event '.$type);

    // Process only order.paid (covers both one-time and subscription cycles)
    if ($type !== 'order.paid') {
        return new WP_REST_Response(array('success'=>true,'message'=>'Event ignored'), 200);
    }

    $order = $evt['data'] ?? array();
    $status = $order['status'] ?? '';
    if ($status !== 'paid') return new WP_REST_Response(array('success'=>true,'message'=>'Order not paid'), 200);

    $order_id = $order['id'] ?? '';
    $amount   = intval($order['net_amount'] ?? ($order['amount'] ?? 0)); // cents
    $meta     = $order['metadata'] ?? array();
    $user_id  = intval($meta['user_id'] ?? 0);

    // Determine points (one-time or subscription)
    $points = 0;
    if (isset($meta['points'])) $points = intval($meta['points']); // one-time flow
    if ($points<=0 && isset($meta['points_per_cycle'])) $points = intval($meta['points_per_cycle']); // subscription initial

    // For renewals, metadata may be missing on the order: fetch subscription metadata
    if ($points<=0 && !empty($order['subscription_id'])) {
        $smeta = mycred_polar_fetch_subscription_meta($order['subscription_id']);
        if (!empty($smeta['points_per_cycle'])) $points = intval($smeta['points_per_cycle']);
        if ($points<=0 && !empty($smeta['points'])) $points = intval($smeta['points']);
    }

    if ($user_id<=0 || $points<=0 || empty($order_id)) {
        error_log('Polar Webhook: missing data (user/points/order)');
        return new WP_REST_Response(array('error'=>'Invalid data'), 400);
    }

    $ok = mycred_polar_award_points($user_id, $points, $order_id, $amount, $payload);
    return new WP_REST_Response(array('success'=>$ok), $ok ? 202 : 500);
}

/* -----------------------------------------------------------
   Success page: fallback credit (safe & idempotent)
----------------------------------------------------------- */
function mycred_polar_success_page() {
    if (!isset($_GET['checkout_id'])) return;
    $checkout_id = sanitize_text_field($_GET['checkout_id']);

    // Attempt to map checkout -> order and award if already paid (idempotent)
    $o = mycred_polar_get_options();
    $mode = $o['mode']; $access = ($mode==='live')? $o['access_token_live'] : $o['access_token_sandbox'];
    $api  = ($mode==='live')? 'https://api.polar.sh' : 'https://sandbox-api.polar.sh';

    if (!empty($access) && !empty($checkout_id) && mycred_polar_check_mycred()) {
        // Try up to 3 quick polls
        for ($i=0; $i<3; $i++) {
            $url = add_query_arg(array('checkout_id'=>$checkout_id), $api.'/v1/orders');
            $resp = wp_remote_get($url, array('headers'=>array('Authorization'=>'Bearer '.$access,'Content-Type'=>'application/json'),'timeout'=>15));
            if (!is_wp_error($resp)) {
                $code = wp_remote_retrieve_response_code($resp);
                $body = json_decode(wp_remote_retrieve_body($resp), true);
                if ($code===200 && !empty($body['items'])) {
                    $order = $body['items'][0];
                    if (($order['status'] ?? '') === 'paid') {
                        $meta = $order['metadata'] ?? array();
                        $user_id = intval($meta['user_id'] ?? 0);
                        $points = 0;
                        if (isset($meta['points'])) $points = intval($meta['points']);
                        if ($points<=0 && isset($meta['points_per_cycle'])) $points = intval($meta['points_per_cycle']);
                        if ($points<=0 && !empty($order['subscription_id'])) {
                            $smeta = mycred_polar_fetch_subscription_meta($order['subscription_id']);
                            if (!empty($smeta['points_per_cycle'])) $points = intval($smeta['points_per_cycle']);
                            if ($points<=0 && !empty($smeta['points'])) $points = intval($smeta['points']);
                        }
                        $amount = intval($order['net_amount'] ?? ($order['amount'] ?? 0));
                        $oid = $order['id'] ?? $checkout_id;
                        if ($user_id>0 && $points>0) {
                            mycred_polar_award_points($user_id, $points, $oid, $amount, 'success-fallback');
                        }
                        break;
                    }
                }
            }
            usleep(400000); // 0.4s
        }
    }

    // Render success HTML
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Successful</title>
        <meta name="robots" content="noindex,nofollow">
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f0f0f0; }
            .success-box { background: white; border: 3px solid #00a32a; border-radius: 10px; padding: 40px; max-width: 520px; margin: 0 auto; }
            .checkmark { font-size: 80px; color: #00a32a; }
            h1 { color: #00a32a; }
            .button { display: inline-block; padding: 15px 30px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="success-box">
            <div class="checkmark">✓</div>
            <h1>Payment Successful!</h1>
            <p>Thanks! Your points will appear shortly (usually within a few seconds).</p>
            <a href="<?php echo esc_url(home_url()); ?>" class="button">Return to Home</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* -----------------------------------------------------------
   Admin row settings link
----------------------------------------------------------- */
function mycred_polar_settings_link($links) {
    $settings_link = '<a href="admin.php?page=mycred_polar_settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mycred_polar_settings_link');
