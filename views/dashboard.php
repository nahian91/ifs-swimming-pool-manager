<?php
/**
 * View: Enterprise Aquatic Operations Dashboard & Command Center
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Context, Permissions & RBAC
$is_admin          = current_user_can( 'manage_options' ) || current_user_can( 'ifs_manage_settings' );
$can_view_finances = $is_admin || current_user_can( 'ifs_view_finances' );
$current_user      = wp_get_current_user();

// 2. Database Tables
$t_tick   = $wpdb->prefix . 'ifs_pms_tickets';
$t_cust   = $wpdb->prefix . 'ifs_pms_customers';
$t_member = $wpdb->prefix . 'ifs_pms_memberships';
$t_exp    = $wpdb->prefix . 'ifs_pms_expenses';
$t_water  = $wpdb->prefix . 'ifs_pms_water_logs';

$currency      = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$today_ymd     = current_time( 'Y-m-d' );
$base_dash_url = admin_url( 'admin.php?page=ifs-pms' );

// 3. Operational Telemetry & Overstay Engine
$today_tickets_issued = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t_tick} WHERE DATE(sold_at) = %s AND status != 'Cancelled'",
        $today_ymd
    )
);

$today_admitted = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t_tick} WHERE DATE(scanned_at) = %s AND status IN ('Used', 'Completed')",
        $today_ymd
    )
);

$active_swimmers = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t_tick} WHERE status = 'Used' AND DATE(scanned_at) = %s",
        $today_ymd
    )
);

$now_mysql = current_time( 'mysql' );
$overstay_count = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t_tick} 
         WHERE status = 'Used' 
         AND DATE(sold_at) = %s 
         AND DATE_ADD(sold_at, INTERVAL duration_hours HOUR) < %s",
        $today_ymd,
        $now_mysql
    )
);

$entry_conversion = $today_tickets_issued > 0 ? (int) round( ( $today_admitted / $today_tickets_issued ) * 100 ) : 0;

// 4. Financial Telemetry
$today_revenue     = 0.00;
$today_expenses    = 0.00;
$today_net_margin  = 0.00;
$today_tickets_rev = 0.00;
$today_members_rev = 0.00;

if ( $can_view_finances ) {
    $today_tickets_rev = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$t_tick} WHERE DATE(sold_at) = %s AND status != 'Cancelled'",
            $today_ymd
        )
    );

    $today_members_rev = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$t_member} WHERE DATE(created_at) = %s AND status = 'Active'",
            $today_ymd
        )
    );

    $today_revenue = $today_tickets_rev + $today_members_rev;

    $today_expenses = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$t_exp} WHERE expense_date = %s",
            $today_ymd
        )
    );

    $today_net_margin = $today_revenue - $today_expenses;
}

// 5. Deck Occupancy Calculations
$max_capacity = max( 1, (int) get_option( 'ifs_pms_max_capacity', 80 ) );
$capacity_pct = min( 100, (int) round( ( $active_swimmers / $max_capacity ) * 100 ) );

if ( $capacity_pct >= 90 ) {
    $capacity_status = __( 'Critical Capacity', 'swimming-pool-manager' );
    $capacity_badge  = 'ifs-status-danger';
    $bar_accent      = 'linear-gradient(90deg, #f59e0b 0%, #ef4444 100%)';
} elseif ( $capacity_pct >= 65 ) {
    $capacity_status = __( 'Elevated Traffic', 'swimming-pool-manager' );
    $capacity_badge  = 'ifs-status-warning';
    $bar_accent      = 'linear-gradient(90deg, #0284c7 0%, #f59e0b 100%)';
} else {
    $capacity_status = __( 'Normal Operations', 'swimming-pool-manager' );
    $capacity_badge  = 'ifs-status-success';
    $bar_accent      = 'linear-gradient(90deg, #0284c7 0%, #10b981 100%)';
}

// 6. Water Chemistry
$latest_water  = $wpdb->get_row( "SELECT * FROM {$t_water} ORDER BY id DESC LIMIT 1" );
$water_healthy = false;
$ph_val        = 7.40;
$cl_val        = 1.50;

if ( $latest_water ) {
    $ph_val = (float) $latest_water->ph_level;
    $cl_val = (float) $latest_water->chlorine_ppm;
    $water_healthy = ( $ph_val >= 7.2 && $ph_val <= 7.8 ) && ( $cl_val >= 1.0 && $cl_val <= 3.0 );
}

// 7. Operating Schedule
$schedule     = get_option( 'ifs_pms_weekly_schedule', array() );
$day_id       = strtolower( current_time( 'l' ) );
$today_config = $schedule[ $day_id ] ?? array();
$open_str     = $today_config['open'] ?? '08:00';
$close_str    = $today_config['close'] ?? '22:00';
$relay_ip     = (string) get_option( 'ifs_pms_relay_ip', '' );

// 8. Recent 5 Access Records
$feed_stream = $wpdb->get_results(
    "SELECT t.ticket_code, t.amount, t.guest_type, t.status, t.sold_at, t.scanned_at, c.name AS customer_name 
     FROM {$t_tick} t 
     LEFT JOIN {$t_cust} c ON t.customer_id = c.id 
     ORDER BY t.id DESC 
     LIMIT 5"
);
?>

<!-- Self-Contained Complete UI Styling (No missing CSS) -->
<style>
.ifs-dash-wrapper {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}

.ifs-dash-wrapper * {
    box-sizing: border-box;
}

.ifs-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
}

/* Cockpit Header */
.ifs-cockpit-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 14px;
    padding: 14px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.ifs-cockpit-timebox {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ifs-pulse-clock-icon {
    width: 42px;
    height: 42px;
    background: #f0f9ff;
    color: #0284c7;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ifs-pulse-clock-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.ifs-clock-digits {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.2;
    color: var(--ifs-text-primary, #0f172a);
}

.ifs-subdate {
    font-size: 12px;
    color: var(--ifs-text-tertiary, #64748b);
    font-weight: 500;
    margin-top: 2px;
}

.ifs-cockpit-meta-group {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ifs-identity-pill {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--ifs-surface-hover, #f8fafc);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 10px;
    padding: 6px 12px;
}

.ifs-avatar-tag {
    width: 28px;
    height: 28px;
    background: #0284c7;
    color: #ffffff;
    font-weight: 800;
    font-size: 12px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ifs-identity-name {
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
}

.ifs-identity-role {
    font-size: 10.5px;
    color: var(--ifs-text-tertiary, #64748b);
}

.ifs-telemetry-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}

.ifs-badge-online { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.ifs-badge-manual { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

.ifs-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

/* Floor Capacity Hero */
.ifs-floor-gauge-deck {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.ifs-gauge-main {
    flex: 1;
    max-width: 65%;
}

.ifs-gauge-indicator-wrap {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}

.ifs-gauge-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--ifs-surface-hover, #f8fafc);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0284c7;
}

.ifs-gauge-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.ifs-metric-label {
    font-size: 11.5px;
    color: var(--ifs-text-tertiary, #64748b);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ifs-strong-count {
    font-size: 28px;
    font-weight: 900;
    color: var(--ifs-text-primary, #0f172a);
}

.ifs-faded-total {
    font-size: 13px;
    color: var(--ifs-text-tertiary, #64748b);
    margin-left: 6px;
}

.ifs-progress-assembly {
    margin-top: 10px;
}

.ifs-progress-legend {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.ifs-progress-track {
    height: 10px;
    background: var(--ifs-border-subtle, #f1f5f9);
    border-radius: 6px;
    overflow: hidden;
}

.ifs-progress-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.4s ease;
}

.ifs-gauge-side-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.ifs-manage-link {
    font-size: 12.5px;
    color: #0284c7;
    font-weight: 700;
    text-decoration: none;
    margin-top: 4px;
}

.ifs-manage-link:hover {
    text-decoration: underline;
}

/* Command Tiles Grid */
.ifs-command-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.ifs-cmd-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
    padding: 16px;
    text-decoration: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.15s ease, border-color 0.15s ease;
}

.ifs-cmd-card:hover {
    transform: translateY(-2px);
    border-color: #0284c7;
}

.ifs-cmd-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ifs-cmd-icon .dashicons {
    font-size: 22px;
    width: 22px;
    height: 22px;
}

.ifs-icon-indigo  { background: #e0f2fe; color: #0284c7; }
.ifs-icon-emerald { background: #d1fae5; color: #059669; }
.ifs-icon-amber   { background: #fef3c7; color: #d97706; }
.ifs-icon-violet  { background: #ede9fe; color: #7c3aed; }

.ifs-cmd-title {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: var(--ifs-text-primary, #0f172a);
}

.ifs-cmd-subtitle {
    display: block;
    font-size: 11px;
    color: var(--ifs-text-tertiary, #64748b);
    margin-top: 2px;
}

/* KPI Tiles Grid */
.ifs-kpi-quad-layout {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.ifs-kpi-tile {
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.ifs-kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    color: var(--ifs-text-tertiary, #64748b);
    text-transform: uppercase;
    margin-bottom: 8px;
}

.ifs-kpi-primary-val {
    font-size: 22px;
    font-weight: 900;
    color: var(--ifs-text-primary, #0f172a);
    margin-bottom: 8px;
    line-height: 1.1;
}

.ifs-kpi-footer-note {
    font-size: 11.5px;
    color: var(--ifs-text-tertiary, #64748b);
    border-top: 1px solid var(--ifs-border-subtle, #f1f5f9);
    padding-top: 8px;
}

/* Twin Panels Grid */
.ifs-twin-panel-grid {
    display: grid;
    grid-template-columns: 1.25fr 0.75fr;
    gap: 16px;
}

.ifs-card-module {
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.ifs-card-module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    padding-bottom: 12px;
    margin-bottom: 12px;
}

.ifs-module-title {
    font-size: 14px;
    font-weight: 800;
    display: flex;
    align-items: center;
    margin: 0;
    gap: 8px;
    color: var(--ifs-text-primary, #0f172a);
}

.ifs-aux-link {
    font-size: 12px;
    color: #0284c7;
    font-weight: 700;
    text-decoration: none;
}

.ifs-aux-link:hover {
    text-decoration: underline;
}

.ifs-stream-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--ifs-border-subtle, #f8fafc);
}

.ifs-stream-row:last-child {
    border-bottom: none;
}

.ifs-patron-name {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
}

.ifs-patron-tag {
    font-size: 11px;
    color: var(--ifs-text-tertiary, #64748b);
    margin-top: 2px;
    display: block;
}

.ifs-stream-status {
    text-align: right;
}

.ifs-timestamp {
    display: block;
    font-size: 11px;
    color: var(--ifs-text-tertiary, #64748b);
    margin-top: 4px;
}

.ifs-data-metric-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--ifs-border-subtle, #f8fafc);
    font-size: 13px;
}

.ifs-spec-label {
    color: var(--ifs-text-secondary, #475569);
    font-weight: 500;
}

.ifs-total-highlight {
    border-top: 1px solid var(--ifs-border-subtle, #e2e8f0);
    margin-top: 6px;
    padding-top: 12px;
    font-weight: 700;
}

.ifs-empty-placeholder {
    text-align: center;
    color: var(--ifs-text-tertiary, #64748b);
    padding: 40px 10px;
}

.ifs-empty-placeholder .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
}

/* Badges & Color Helpers */
.ifs-pill-tag {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-block;
}
.ifs-status-success { background: #ecfdf5; color: #059669; }
.ifs-status-warning { background: #fffbeb; color: #d97706; }
.ifs-status-danger  { background: #fef2f2; color: #dc2626; }

.ifs-text-emerald { color: #10b981; }
.ifs-text-danger  { color: #ef4444; }
.ifs-text-cyan    { color: #0284c7; }
.ifs-text-violet  { color: #8b5cf6; }
.ifs-text-muted   { color: #94a3b8; }

.ifs-pulse-alert {
    animation: ifsAlertPulse 2s infinite ease-in-out;
}

@keyframes ifsAlertPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Dark Mode Variables Support */
[data-theme="dark"] .ifs-cockpit-bar,
[data-theme="dark"] .ifs-floor-gauge-deck,
[data-theme="dark"] .ifs-cmd-card,
[data-theme="dark"] .ifs-kpi-tile,
[data-theme="dark"] .ifs-card-module {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .ifs-identity-pill,
[data-theme="dark"] .ifs-gauge-icon {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .ifs-clock-digits,
[data-theme="dark"] .ifs-strong-count,
[data-theme="dark"] .ifs-cmd-title,
[data-theme="dark"] .ifs-kpi-primary-val,
[data-theme="dark"] .ifs-module-title,
[data-theme="dark"] .ifs-patron-name {
    color: #f8fafc;
}

/* Mobile & Tablet Scaffolding */
@media screen and (max-width: 1080px) {
    .ifs-command-grid, .ifs-kpi-quad-layout { grid-template-columns: repeat(2, 1fr); }
    .ifs-twin-panel-grid { grid-template-columns: 1fr; }
    .ifs-floor-gauge-deck { flex-direction: column; align-items: flex-start; gap: 16px; }
    .ifs-gauge-main { max-width: 100%; width: 100%; }
    .ifs-gauge-side-status { align-items: flex-start; }
}

@media screen and (max-width: 640px) {
    .ifs-cockpit-bar { flex-direction: column; align-items: flex-start; gap: 14px; }
    .ifs-cockpit-meta-group { width: 100%; justify-content: space-between; }
    .ifs-command-grid, .ifs-kpi-quad-layout { grid-template-columns: 1fr; }
}
</style>

<div class="ifs-dash-wrapper">
    <!-- Top Cockpit Station -->
    <header class="ifs-cockpit-bar">
        <div class="ifs-cockpit-timebox">
            <div class="ifs-pulse-clock-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div>
                <div class="ifs-clock-digits ifs-mono" id="ifsPmsLiveClock">--:--:-- --</div>
                <div class="ifs-subdate"><?php echo esc_html( current_time( 'l, F j, Y' ) ); ?> &bull; <span id="ifsPmsCountdownText"><?php esc_html_e( 'Syncing...', 'swimming-pool-manager' ); ?></span></div>
            </div>
        </div>

        <div class="ifs-cockpit-meta-group">
            <div class="ifs-identity-pill">
                <span class="ifs-avatar-tag"><?php echo esc_html( strtoupper( substr( $current_user->display_name, 0, 1 ) ) ); ?></span>
                <div>
                    <div class="ifs-identity-name"><?php echo esc_html( $current_user->display_name ); ?></div>
                    <div class="ifs-identity-role"><?php echo $is_admin ? esc_html__( 'System Supervisor', 'swimming-pool-manager' ) : esc_html__( 'Front Desk Operator', 'swimming-pool-manager' ); ?></div>
                </div>
            </div>

            <div class="ifs-telemetry-badge <?php echo ! empty( $relay_ip ) ? 'ifs-badge-online' : 'ifs-badge-manual'; ?>">
                <span class="ifs-live-dot"></span>
                <span><?php echo ! empty( $relay_ip ) ? esc_html__( 'Gate Controllers Linked', 'swimming-pool-manager' ) : esc_html__( 'Manual Gate Override', 'swimming-pool-manager' ); ?></span>
            </div>
        </div>
    </header>

    <!-- Floor Occupancy Hero Matrix -->
    <section class="ifs-floor-gauge-deck">
        <div class="ifs-gauge-main">
            <div class="ifs-gauge-indicator-wrap">
                <div class="ifs-gauge-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div>
                    <div class="ifs-metric-label"><?php esc_html_e( 'Deck Occupancy & Floor Headcount', 'swimming-pool-manager' ); ?></div>
                    <div class="ifs-metric-numbers">
                        <span class="ifs-mono ifs-strong-count"><?php echo esc_html( (string) $active_swimmers ); ?></span>
                        <span class="ifs-faded-total">/ <span class="ifs-mono"><?php echo esc_html( (string) $max_capacity ); ?></span> <?php esc_html_e( 'Max Allowable Cap', 'swimming-pool-manager' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="ifs-progress-assembly">
                <div class="ifs-progress-legend">
                    <span><?php esc_html_e( 'Surface Density Allocation', 'swimming-pool-manager' ); ?></span>
                    <strong class="ifs-mono"><?php echo esc_html( (string) $capacity_pct ); ?>%</strong>
                </div>
                <div class="ifs-progress-track">
                    <div class="ifs-progress-fill" style="width: <?php echo esc_attr( (string) $capacity_pct ); ?>%; background: <?php echo esc_attr( $bar_accent ); ?>;"></div>
                </div>
            </div>
        </div>

        <div class="ifs-gauge-side-status">
            <span class="ifs-pill-tag <?php echo esc_attr( $capacity_badge ); ?>">
                <?php echo esc_html( $capacity_status ); ?>
            </span>
            <?php if ( $overstay_count > 0 ) : ?>
                <span class="ifs-pill-tag ifs-status-danger ifs-pulse-alert">
                    ⚠️ <?php echo esc_html( sprintf( __( '%d Overstay Incident(s)', 'swimming-pool-manager' ), $overstay_count ) ); ?>
                </span>
            <?php else : ?>
                <span class="ifs-pill-tag ifs-status-success">
                    ✓ <?php esc_html_e( 'Zero Overstay Violations', 'swimming-pool-manager' ); ?>
                </span>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-live-status' ) ); ?>" class="ifs-manage-link">
                <?php esc_html_e( 'Real-Time Floor Map &rarr;', 'swimming-pool-manager' ); ?>
            </a>
        </div>
    </section>

    <!-- Operational Fast Trigger Tiles -->
    <nav class="ifs-command-grid" aria-label="<?php esc_attr_e( 'Quick Command Tiles', 'swimming-pool-manager' ); ?>">
        <a href="<?php echo esc_url( $base_dash_url . '&view=tickets' ); ?>" class="ifs-cmd-card">
            <div class="ifs-cmd-icon ifs-icon-indigo"><span class="dashicons dashicons-cart"></span></div>
            <div class="ifs-cmd-content">
                <span class="ifs-cmd-title"><?php esc_html_e( 'Ticketing POS', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-cmd-subtitle"><?php esc_html_e( 'Dispatch Passes & Amenities', 'swimming-pool-manager' ); ?></span>
            </div>
        </a>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-scanner' ) ); ?>" class="ifs-cmd-card">
            <div class="ifs-cmd-icon ifs-icon-emerald"><span class="dashicons dashicons-fullscreen-alt"></span></div>
            <div class="ifs-cmd-content">
                <span class="ifs-cmd-title"><?php esc_html_e( 'Access Gate', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-cmd-subtitle"><?php esc_html_e( 'Optical Verification & Relays', 'swimming-pool-manager' ); ?></span>
            </div>
        </a>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-bookings' ) ); ?>" class="ifs-cmd-card">
            <div class="ifs-cmd-icon ifs-icon-amber"><span class="dashicons dashicons-calendar-alt"></span></div>
            <div class="ifs-cmd-content">
                <span class="ifs-cmd-title"><?php esc_html_e( 'Reservations', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-cmd-subtitle"><?php esc_html_e( 'Private Lanes & Event Slots', 'swimming-pool-manager' ); ?></span>
            </div>
        </a>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-dayclose' ) ); ?>" class="ifs-cmd-card">
            <div class="ifs-cmd-icon ifs-icon-violet"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="ifs-cmd-content">
                <span class="ifs-cmd-title"><?php esc_html_e( 'Cash Reconciliation', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-cmd-subtitle"><?php esc_html_e( 'Shift Close & Drawer Audit', 'swimming-pool-manager' ); ?></span>
            </div>
        </a>
    </nav>

    <!-- KPI Metric Blocks -->
    <section class="ifs-kpi-quad-layout">
        <?php if ( $can_view_finances ) : ?>
            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Shift Gross Inflow', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-chart-area ifs-text-emerald"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono ifs-text-emerald">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_revenue, 2 ) ); ?>
                </div>
                <div class="ifs-kpi-footer-note">
                    <?php esc_html_e( 'Net Take-Home:', 'swimming-pool-manager' ); ?>
                    <strong class="ifs-mono <?php echo $today_net_margin >= 0 ? 'ifs-text-emerald' : 'ifs-text-danger'; ?>">
                        <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_net_margin, 2 ) ); ?>
                    </strong>
                </div>
            </div>

            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Admission Turnover', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-tickets-alt ifs-text-cyan"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono">
                    <?php echo esc_html( number_format_i18n( $today_tickets_issued ) ); ?>
                </div>
                <div class="ifs-kpi-footer-note">
                    <?php esc_html_e( 'Tickets Rev: ', 'swimming-pool-manager' ); ?>
                    <span class="ifs-mono"><?php echo esc_html( $currency . ' ' . number_format_i18n( $today_tickets_rev, 2 ) ); ?></span>
                </div>
            </div>

            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Gate Verification', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-yes-alt ifs-text-violet"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono ifs-text-violet">
                    <?php echo esc_html( (string) $entry_conversion ); ?>%
                </div>
                <div class="ifs-kpi-footer-note">
                    <?php echo esc_html( sprintf( __( '%d Admitted / %d Dispatched', 'swimming-pool-manager' ), $today_admitted, $today_tickets_issued ) ); ?>
                </div>
            </div>

            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Operating Outflows', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-media-text ifs-text-danger"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono ifs-text-danger">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_expenses, 2 ) ); ?>
                </div>
                <div class="ifs-kpi-footer-note">
                    <?php esc_html_e( 'Expenses logged today', 'swimming-pool-manager' ); ?>
                </div>
            </div>
        <?php else : ?>
            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Passes Issued', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono"><?php echo esc_html( number_format_i18n( $today_tickets_issued ) ); ?></div>
                <div class="ifs-kpi-footer-note"><?php esc_html_e( 'Total admissions sold today', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Turnstile Admissions', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-fullscreen-alt ifs-text-emerald"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono ifs-text-emerald"><?php echo esc_html( number_format_i18n( $today_admitted ) ); ?></div>
                <div class="ifs-kpi-footer-note"><?php esc_html_e( 'Total passed through barcode gate', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Admissions Verified', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-chart-pie ifs-text-violet"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono ifs-text-violet"><?php echo esc_html( (string) $entry_conversion ); ?>%</div>
                <div class="ifs-kpi-footer-note"><?php esc_html_e( 'Conversion of issued tickets', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-kpi-tile">
                <div class="ifs-kpi-header">
                    <span><?php esc_html_e( 'Available Deck Slots', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="ifs-kpi-primary-val ifs-mono"><?php echo esc_html( (string) max( 0, $max_capacity - $active_swimmers ) ); ?></div>
                <div class="ifs-kpi-footer-note"><?php esc_html_e( 'Safety allowance before threshold', 'swimming-pool-manager' ); ?></div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Operational Feeds Split -->
    <div class="ifs-twin-panel-grid">
        <article class="ifs-card-module">
            <div class="ifs-card-module-header">
                <h2 class="ifs-module-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Turnstile Admissions Feed (Recent 5)', 'swimming-pool-manager' ); ?>
                </h2>
                <a href="<?php echo esc_url( $base_dash_url . '&view=tickets&tab=list' ); ?>" class="ifs-aux-link">
                    <?php esc_html_e( 'Master Ledger &rarr;', 'swimming-pool-manager' ); ?>
                </a>
            </div>

            <div class="ifs-stream-body">
                <?php if ( ! empty( $feed_stream ) ) : ?>
                    <?php foreach ( $feed_stream as $row ) : 
                        $status_badge = ( 'Valid' === $row->status ) ? 'ifs-status-success' : ( ( 'Used' === $row->status ) ? 'ifs-status-warning' : 'ifs-status-danger' );
                    ?>
                        <div class="ifs-stream-row">
                            <div class="ifs-stream-patron">
                                <strong class="ifs-patron-name"><?php echo esc_html( $row->customer_name ?: __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?></strong>
                                <span class="ifs-patron-tag ifs-mono">
                                    <?php echo esc_html( $row->ticket_code ); ?> &bull; <?php echo esc_html( ucfirst( $row->guest_type ?: 'Customer' ) ); ?>
                                    <?php if ( $can_view_finances ) : ?>
                                        &bull; <?php echo esc_html( number_format_i18n( (float) $row->amount, 2 ) . ' ' . $currency ); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="ifs-stream-status">
                                <span class="ifs-pill-tag <?php echo esc_attr( $status_badge ); ?>"><?php echo esc_html( $row->status ); ?></span>
                                <time class="ifs-timestamp ifs-mono"><?php echo esc_html( 'Used' === $row->status ? ( $row->scanned_at ?: $row->sold_at ) : $row->sold_at ); ?></time>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="ifs-empty-placeholder">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <p><?php esc_html_e( 'No gate admissions logged yet today.', 'swimming-pool-manager' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <!-- Facility Health & Water Telemetry -->
        <article class="ifs-card-module">
            <div class="ifs-card-module-header">
                <h2 class="ifs-module-title">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php esc_html_e( 'Sanitation & Facility Telemetry', 'swimming-pool-manager' ); ?>
                </h2>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-water-log' ) ); ?>" class="ifs-aux-link">
                    <?php esc_html_e( 'Chemical Log &rarr;', 'swimming-pool-manager' ); ?>
                </a>
            </div>

            <div class="ifs-telemetry-body">
                <div class="ifs-data-metric-line">
                    <span class="ifs-spec-label"><?php esc_html_e( 'Chemical Safety Standard', 'swimming-pool-manager' ); ?></span>
                    <span class="ifs-pill-tag <?php echo $water_healthy ? 'ifs-status-success' : 'ifs-status-danger'; ?>">
                        <?php echo $water_healthy ? esc_html__( 'DIN 19643 Compliant (Safe)', 'swimming-pool-manager' ) : esc_html__( 'Needs Chemical Adjustment', 'swimming-pool-manager' ); ?>
                    </span>
                </div>

                <div class="ifs-data-metric-line">
                    <span class="ifs-spec-label"><?php esc_html_e( 'Water pH Level', 'swimming-pool-manager' ); ?></span>
                    <strong class="ifs-mono <?php echo ( $ph_val >= 7.2 && $ph_val <= 7.8 ) ? 'ifs-text-emerald' : 'ifs-text-danger'; ?>">
                        <?php echo esc_html( number_format_i18n( $ph_val, 2 ) ); ?> pH (Opt: 7.2 - 7.6)
                    </strong>
                </div>

                <div class="ifs-data-metric-line">
                    <span class="ifs-spec-label"><?php esc_html_e( 'Free Chlorine Density', 'swimming-pool-manager' ); ?></span>
                    <strong class="ifs-mono <?php echo ( $cl_val >= 1.0 && $cl_val <= 3.0 ) ? 'ifs-text-emerald' : 'ifs-text-danger'; ?>">
                        <?php echo esc_html( number_format_i18n( $cl_val, 2 ) ); ?> ppm (Opt: 1.0 - 3.0)
                    </strong>
                </div>

                <div class="ifs-data-metric-line">
                    <span class="ifs-spec-label"><?php esc_html_e( 'Clarity & Optical Index', 'swimming-pool-manager' ); ?></span>
                    <strong><?php echo esc_html( $latest_water->clarity ?? 'Clear' ); ?></strong>
                </div>

                <div class="ifs-data-metric-line">
                    <span class="ifs-spec-label"><?php esc_html_e( 'Last Lab Inspection Time', 'swimming-pool-manager' ); ?></span>
                    <span class="ifs-mono" style="color: #64748b;"><?php echo esc_html( $latest_water ? ( $latest_water->logged_at . ' by ' . $latest_water->logged_by ) : __( 'No log today', 'swimming-pool-manager' ) ); ?></span>
                </div>

                <div class="ifs-data-metric-line ifs-total-highlight">
                    <span class="ifs-spec-label"><?php esc_html_e( 'Hardware Relay Output', 'swimming-pool-manager' ); ?></span>
                    <span class="ifs-mono <?php echo ! empty( $relay_ip ) ? 'ifs-text-emerald' : 'ifs-text-muted'; ?>">
                        <?php echo ! empty( $relay_ip ) ? esc_html__( 'Active Barrier Controller Linked', 'swimming-pool-manager' ) : esc_html__( 'Manual Bypass Active', 'swimming-pool-manager' ); ?>
                    </span>
                </div>
            </div>
        </article>
    </div>
</div>

<!-- Embedded Real-Time Clock & Schedule Countdown Engine -->
<script>
(function() {
    function updateClock() {
        var clockEl = document.getElementById('ifsPmsLiveClock');
        var countEl = document.getElementById('ifsPmsCountdownText');
        if (!clockEl) return;

        var now = new Date();
        var hours = now.getHours();
        var minutes = now.getMinutes();
        var seconds = now.getSeconds();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;

        var strTime = (hours < 10 ? '0' : '') + hours + ':' + 
                      (minutes < 10 ? '0' : '') + minutes + ':' + 
                      (seconds < 10 ? '0' : '') + seconds + ' ' + ampm;
        clockEl.textContent = strTime;

        if (countEl) {
            var closeTimeStr = '<?php echo esc_js( $close_str ); ?>';
            var parts = closeTimeStr.split(':');
            var closeDate = new Date();
            closeDate.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0, 0);

            var diffMs = closeDate - now;
            if (diffMs > 0) {
                var diffHrs = Math.floor(diffMs / 3600000);
                var diffMins = Math.floor((diffMs % 3600000) / 60000);
                countEl.textContent = 'Facility closes in ' + diffHrs + 'h ' + diffMins + 'm';
            } else {
                countEl.textContent = 'Session Closed (Overnight Standby)';
            }
        }
    }
    setInterval(updateClock, 1000);
    updateClock();
})();
</script>