<?php
/**
 * View: Operations Dashboard & Executive Command Center (Dashicons UI)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Establish Permissions & Role Capabilities
$is_admin          = current_user_can( 'manage_options' ) || current_user_can( 'ozone_manage_settings' );
$can_view_finances = $is_admin || current_user_can( 'ozone_view_finances' );

// 2. Establish Table References & Parameters
$t_tick    = $wpdb->prefix . 'ifs_pms_tickets';
$t_cust    = $wpdb->prefix . 'ifs_pms_customers';
$t_members = $wpdb->prefix . 'ifs_pms_memberships';
$t_expense = $wpdb->prefix . 'ifs_pms_expenses';

$currency      = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$today_ymd     = current_time( 'Y-m-d' );
$base_dash_url = admin_url( 'admin.php?page=ifs-pms' );

// Enqueue standalone dashboard stylesheet and script
wp_enqueue_style( 'ifs-pms-dashboard-css', IFS_PMS_URL . 'assets/css/dashboard.css', array( 'dashicons' ), IFS_PMS_VERSION );
wp_enqueue_script( 'ifs-pms-dashboard-js', IFS_PMS_URL . 'assets/js/dashboard.js', array(), IFS_PMS_VERSION, true );

// 3. Operational Throughput Telemetry
$today_tickets_issued = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t_tick} WHERE DATE(sold_at) = %s",
        $today_ymd
    )
);

$today_admitted = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t_tick} WHERE DATE(scanned_at) = %s AND status = 'Used'",
        $today_ymd
    )
);

$entry_turnover = $today_tickets_issued > 0 ? (int) round( ( $today_admitted / $today_tickets_issued ) * 100 ) : 0;

// 4. Financial Telemetry (Executed strictly for authorized personnel)
$today_revenue    = 0.00;
$today_expenses   = 0.00;
$today_net_margin = 0.00;
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
            "SELECT COALESCE(SUM(amount), 0) FROM {$t_members} WHERE DATE(created_at) = %s",
            $today_ymd
        )
    );

    $today_revenue = $today_tickets_rev + $today_members_rev;

    $today_expenses = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$t_expense} WHERE expense_date = %s",
            $today_ymd
        )
    );

    $today_net_margin = $today_revenue - $today_expenses;
}

// 5. Pool Capacity Metrics & Dynamics
$max_pool_capacity = (int) get_option( 'ifs_pms_max_capacity', 80 );
if ( $max_pool_capacity <= 0 ) {
    $max_pool_capacity = 80;
}

$current_swimmers = max( 0, $today_admitted );
$capacity_pct     = min( 100, (int) round( ( $current_swimmers / $max_pool_capacity ) * 100 ) );

if ( $capacity_pct >= 85 ) {
    $status_label = __( 'Near Peak Capacity', 'swimming-pool-manager' );
    $status_class = 'ifs-pms-status-red';
    $bar_gradient = 'linear-gradient(90deg, #f59e0b 0%, #ef4444 100%)';
} elseif ( $capacity_pct >= 55 ) {
    $status_label = __( 'Moderate Traffic', 'swimming-pool-manager' );
    $status_class = 'ifs-pms-status-amber';
    $bar_gradient = 'linear-gradient(90deg, #0284c7 0%, #f59e0b 100%)';
} else {
    $status_label = __( 'Optimal Space', 'swimming-pool-manager' );
    $status_class = 'ifs-pms-status-green';
    $bar_gradient = 'linear-gradient(90deg, #0284c7 0%, #10b981 100%)';
}

// 6. Operating Hours & Schedule Calculation
$weekly_schedule  = get_option( 'ifs_pms_weekly_schedule', array() );
$day_key          = strtolower( current_time( 'l' ) );
$today_sched      = $weekly_schedule[ $day_key ] ?? array();
$opening_time_str = $today_sched['open'] ?? '08:00';
$closing_time_str = $today_sched['close'] ?? '23:00';
$day_status       = $today_sched['status'] ?? 'open';

wp_localize_script(
    'ifs-pms-dashboard-js',
    'ifsPmsDashboardConfig',
    array(
        'opening_time' => $opening_time_str,
        'closing_time' => $closing_time_str,
        'day_status'   => $day_status,
    )
);

// 7. Live Admissions Stream (Strictly Latest 5 Records)
$recent_admissions = $wpdb->get_results(
    "SELECT t.ticket_code, t.amount, t.status, t.sold_at, t.scanned_at, c.name AS customer_name 
     FROM {$t_tick} t 
     LEFT JOIN {$t_cust} c ON t.customer_id = c.id 
     ORDER BY t.id DESC 
     LIMIT 5"
);
?>

<div class="ifs-pms-dash-stack">
    <!-- Live Telemetry & Station Header -->
    <div class="ifs-pms-telemetry-header">
        <div class="ifs-pms-clock-wrap">
            <div class="ifs-pms-clock-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div>
                <div class="ifs-pms-clock-digits" id="ifsPmsLiveClock">--:--:-- --</div>
                <div class="ifs-pms-clock-date" id="ifsPmsLiveDate"><?php echo esc_html( current_time( 'l, F j, Y' ) ); ?></div>
                <div class="ifs-pms-closing-countdown" id="ifsPmsClosingCountdown" style="margin-top: 4px; font-size: 11.5px; font-weight: 700; color: var(--ifs-accent, #0284c7);">
                    <span id="ifsPmsCountdownText"><?php esc_html_e( 'Syncing schedule...', 'swimming-pool-manager' ); ?></span>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 24px;">
            <div class="ifs-pms-operator-meta">
                <div class="ifs-pms-operator-name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></div>
                <div class="ifs-pms-operator-role">
                    <?php echo $can_view_finances ? esc_html__( 'Executive Station • Supervisor Active', 'swimming-pool-manager' ) : esc_html__( 'Front Desk Station • Operator Active', 'swimming-pool-manager' ); ?>
                </div>
            </div>
            <div class="ifs-pms-status-pill ifs-pms-status-green">
                <span class="ifs-pms-pulse-dot"></span> <?php esc_html_e( 'Turnstiles Synchronized', 'swimming-pool-manager' ); ?>
            </div>
        </div>
    </div>

    <!-- Live Rooftop Capacity Gauge -->
    <div class="ifs-pms-gauge-card">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="ifs-pms-gauge-icon-box">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div>
                <div class="ifs-pms-gauge-title">
                    <?php esc_html_e( 'Rooftop Skypool Occupancy', 'swimming-pool-manager' ); ?>
                </div>
                <div class="ifs-pms-gauge-numbers">
                    <span class="ifs-pms-mono"><?php echo esc_html( (string) $current_swimmers ); ?></span>
                    <span class="ifs-pms-gauge-subtitle">/ <span class="ifs-pms-mono"><?php echo esc_html( (string) $max_pool_capacity ); ?></span> <?php esc_html_e( 'max swimmers', 'swimming-pool-manager' ); ?></span>
                </div>
            </div>
        </div>

        <div class="ifs-pms-gauge-progress-wrap">
            <div class="ifs-pms-gauge-progress-meta">
                <span class="ifs-pms-gauge-progress-label"><?php esc_html_e( 'Water & Deck Utilization', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-mono ifs-pms-gauge-progress-val">
                    <?php echo esc_html( (string) $capacity_pct ); ?>%
                </span>
            </div>
            <div class="ifs-pms-gauge-progress-track">
                <div class="ifs-pms-gauge-progress-bar" style="width: <?php echo esc_attr( (string) $capacity_pct ); ?>%; background: <?php echo esc_attr( $bar_gradient ); ?>;"></div>
            </div>
        </div>

        <div>
            <span class="ifs-pms-status-pill <?php echo esc_attr( $status_class ); ?>">
                <?php echo esc_html( $status_label ); ?>
            </span>
        </div>
    </div>

    <!-- Operational Quick Action Tiles -->
    <div class="ifs-pms-action-deck">
        <a href="<?php echo esc_url( $base_dash_url . '&view=tickets' ); ?>" class="ifs-pms-action-card">
            <div class="ifs-pms-action-icon ifs-pms-action-icon-blue">
                <span class="dashicons dashicons-tickets-alt"></span>
            </div>
            <div>
                <div class="ifs-pms-action-title"><?php esc_html_e( 'Sell Ticket Pass', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-pms-action-sub"><?php esc_html_e( 'Issue Guest Admission Pass', 'swimming-pool-manager' ); ?></div>
            </div>
        </a>

        <a href="<?php echo esc_url( $base_dash_url . '&view=scanner' ); ?>" class="ifs-pms-action-card">
            <div class="ifs-pms-action-icon ifs-pms-action-icon-green">
                <span class="dashicons dashicons-fullscreen-alt"></span>
            </div>
            <div>
                <div class="ifs-pms-action-title"><?php esc_html_e( 'Gate Turnstile Scan', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-pms-action-sub"><?php esc_html_e( 'Check-in Admission Barcode', 'swimming-pool-manager' ); ?></div>
            </div>
        </a>

        <a href="<?php echo esc_url( $base_dash_url . '&view=membership' ); ?>" class="ifs-pms-action-card">
            <div class="ifs-pms-action-icon ifs-pms-action-icon-purple">
                <span class="dashicons dashicons-id-alt"></span>
            </div>
            <div>
                <div class="ifs-pms-action-title"><?php esc_html_e( 'Enroll Members', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-pms-action-sub"><?php esc_html_e( 'Monthly & Season Passes', 'swimming-pool-manager' ); ?></div>
            </div>
        </a>

        <?php if ( $can_view_finances ) : ?>
            <a href="<?php echo esc_url( $base_dash_url . '&view=expenses' ); ?>" class="ifs-pms-action-card">
                <div class="ifs-pms-action-icon ifs-pms-action-icon-red">
                    <span class="dashicons dashicons-media-text"></span>
                </div>
                <div>
                    <div class="ifs-pms-action-title"><?php esc_html_e( 'Record Outflow', 'swimming-pool-manager' ); ?></div>
                    <div class="ifs-pms-action-sub"><?php esc_html_e( 'Salary, Utility, Fuel & Bills', 'swimming-pool-manager' ); ?></div>
                </div>
            </a>
        <?php else : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-customers' ) ); ?>" class="ifs-pms-action-card">
                <div class="ifs-pms-action-icon ifs-pms-action-icon-blue">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div>
                    <div class="ifs-pms-action-title"><?php esc_html_e( 'Patron Directory', 'swimming-pool-manager' ); ?></div>
                    <div class="ifs-pms-action-sub"><?php esc_html_e( 'Search Guest History & Records', 'swimming-pool-manager' ); ?></div>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- KPI Metric Matrix: Financial vs Operational -->
    <div class="ifs-pms-kpi-quad">
        <?php if ( $can_view_finances ) : ?>
            <!-- FINANCIAL KPI METRICS (Admins / Managers) -->
            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-inflow">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Shift Gross Receipts', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #10b981;">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_revenue, 2 ) ); ?>
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Net Take-Home:', 'swimming-pool-manager' ); ?> 
                    <strong style="color: <?php echo $today_net_margin >= 0 ? '#10b981' : '#ef4444'; ?>;" class="ifs-pms-mono">
                        <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_net_margin, 2 ) ); ?>
                    </strong>
                </div>
            </div>

            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-admit">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Passes Issued Today', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #0f172a;">
                    <?php echo esc_html( number_format_i18n( $today_tickets_issued ) ); ?>
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Tickets sold during current session', 'swimming-pool-manager' ); ?>
                </div>
            </div>

            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-turnover">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Gate Turnstile Verified', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #c084fc;">
                    <?php echo esc_html( number_format_i18n( $today_admitted ) ); ?>
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Verification Conversion:', 'swimming-pool-manager' ); ?> 
                    <strong style="color: #0f172a;" class="ifs-pms-mono"><?php echo esc_html( (string) $entry_turnover ); ?>%</strong>
                </div>
            </div>

            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-outflow">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Operating Outflows', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-media-text"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #ef4444;">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_expenses, 2 ) ); ?>
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Logged disbursements & supplies', 'swimming-pool-manager' ); ?>
                </div>
            </div>
        <?php else : ?>
            <!-- PURELY OPERATIONAL METRICS (Cashiers / Non-Admin) -->
            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-inflow">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Passes Issued Shift', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #0284c7;">
                    <?php echo esc_html( number_format_i18n( $today_tickets_issued ) ); ?>
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Total admissions dispatched', 'swimming-pool-manager' ); ?>
                </div>
            </div>

            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-turnover">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Turnstile Admissions', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #10b981;">
                    <?php echo esc_html( number_format_i18n( $today_admitted ) ); ?>
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Verified at electronic gates', 'swimming-pool-manager' ); ?>
                </div>
            </div>

            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-admit">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Admission Turnover', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-chart-pie"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #a855f7;">
                    <?php echo esc_html( (string) $entry_turnover ); ?>%
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Tickets scanned vs issued', 'swimming-pool-manager' ); ?>
                </div>
            </div>

            <div class="ifs-pms-kpi-brick ifs-pms-kpi-brick-outflow">
                <div class="ifs-pms-kpi-meta-tag">
                    <span><?php esc_html_e( 'Deck Slots Remaining', 'swimming-pool-manager' ); ?></span>
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="ifs-pms-kpi-val ifs-pms-mono" style="color: #0f172a;">
                    <?php echo esc_html( (string) max( 0, $max_pool_capacity - $current_swimmers ) ); ?>
                </div>
                <div class="ifs-pms-kpi-foot">
                    <?php esc_html_e( 'Open capacity before cap', 'swimming-pool-manager' ); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Live Stream & Operational Split -->
    <div class="ifs-pms-activity-grid">
        <!-- Live Admissions Stream (Latest 5) -->
        <div class="ifs-pms-panel-card">
            <div class="ifs-pms-panel-top">
                <h3 class="ifs-pms-panel-header-title">
                    <span class="dashicons dashicons-building"></span>
                    <?php esc_html_e( 'Live Turnstile Activity Stream (Latest 5)', 'swimming-pool-manager' ); ?>
                </h3>
                <a href="<?php echo esc_url( $base_dash_url . '&view=tickets&tab=list' ); ?>" style="color: #0284c7; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <?php esc_html_e( 'Full Pass Ledger', 'swimming-pool-manager' ); ?> &rarr;
                </a>
            </div>

            <?php if ( ! empty( $recent_admissions ) ) : ?>
                <?php foreach ( $recent_admissions as $act ) : 
                    $is_valid    = ( 'Valid' === $act->status );
                    $badge_class = $is_valid 
                        ? 'ifs-pms-status-green' 
                        : ( 'Used' === $act->status 
                            ? 'ifs-pms-status-amber' 
                            : 'ifs-pms-status-red' );
                ?>
                    <div class="ifs-pms-feed-item">
                        <div>
                            <div class="ifs-pms-feed-guest">
                                <?php echo esc_html( ! empty( $act->customer_name ) ? $act->customer_name : __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?>
                            </div>
                            <div class="ifs-pms-feed-meta ifs-pms-mono">
                                <?php echo esc_html( $act->ticket_code ); ?>
                                <?php if ( $can_view_finances ) : ?>
                                    &bull; <?php echo esc_html( number_format_i18n( (float) $act->amount, 2 ) . ' ' . $currency ); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <span class="ifs-pms-status-pill <?php echo esc_attr( $badge_class ); ?>" style="padding: 4px 10px; font-size: 11px;">
                                <?php echo esc_html( $act->status ); ?>
                            </span>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 5px;" class="ifs-pms-mono">
                                <?php echo esc_html( 'Used' === $act->status ? ( $act->scanned_at ?: $act->sold_at ) : $act->sold_at ); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div style="text-align: center; color: #64748b; padding: 56px 20px;">
                    <span class="dashicons dashicons-tickets-alt" style="font-size: 32px; width: 32px; height: 32px;"></span>
                    <div style="margin-top: 12px;"><?php esc_html_e( 'No tickets issued or scanned yet today.', 'swimming-pool-manager' ); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( $can_view_finances ) : ?>
            <!-- Shift Financial Breakdown (Managers / Admins Only) -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-top">
                    <h3 class="ifs-pms-panel-header-title">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e( 'Shift Balance Breakdown', 'swimming-pool-manager' ); ?>
                    </h3>
                    <span class="ifs-pms-status-pill ifs-pms-status-green" style="font-size: 11px; padding: 4px 10px;">
                        <?php esc_html_e( 'Audited', 'swimming-pool-manager' ); ?>
                    </span>
                </div>

                <div class="ifs-pms-finance-row">
                    <span style="display: flex; align-items: center; gap: 12px; color: #0f172a;">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <?php esc_html_e( 'Day Admission Tickets', 'swimming-pool-manager' ); ?>
                    </span>
                    <strong class="ifs-pms-mono" style="color: #0f172a;">
                        <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_tickets_rev, 2 ) ); ?>
                    </strong>
                </div>

                <div class="ifs-pms-finance-row">
                    <span style="display: flex; align-items: center; gap: 12px; color: #0f172a;">
                        <span class="dashicons dashicons-id-alt"></span>
                        <?php esc_html_e( 'Pass Subscriptions', 'swimming-pool-manager' ); ?>
                    </span>
                    <strong class="ifs-pms-mono" style="color: #0f172a;">
                        <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_members_rev, 2 ) ); ?>
                    </strong>
                </div>

                <div class="ifs-pms-finance-row">
                    <span style="display: flex; align-items: center; gap: 12px; color: #0f172a;">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e( 'Operational Outflows', 'swimming-pool-manager' ); ?>
                    </span>
                    <strong class="ifs-pms-mono" style="color: #ef4444;">
                        -<?php echo esc_html( $currency . ' ' . number_format_i18n( $today_expenses, 2 ) ); ?>
                    </strong>
                </div>

                <div class="ifs-pms-finance-row ifs-pms-finance-total">
                    <span style="font-size: 15px; color: #0f172a;"><?php esc_html_e( 'Daily Cash Margin', 'swimming-pool-manager' ); ?></span>
                    <strong class="ifs-pms-mono" style="font-size: 19px; color: <?php echo $today_net_margin >= 0 ? '#10b981' : '#ef4444'; ?>;">
                        <?php echo esc_html( $currency . ' ' . number_format_i18n( $today_net_margin, 2 ) ); ?>
                    </strong>
                </div>
            </div>
        <?php else : ?>
            <!-- Station & Turnstile Operational Status (Non-Administrators) -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-top">
                    <h3 class="ifs-pms-panel-header-title">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e( 'Station & Facility Status', 'swimming-pool-manager' ); ?>
                    </h3>
                    <span class="ifs-pms-status-pill ifs-pms-status-green" style="font-size: 11px; padding: 4px 10px;">
                        <?php esc_html_e( 'Operating Normal', 'swimming-pool-manager' ); ?>
                    </span>
                </div>

                <div class="ifs-pms-finance-row">
                    <span style="display: flex; align-items: center; gap: 12px; color: #0f172a;">
                        <span class="dashicons dashicons-clock"></span>
                        <?php esc_html_e( 'Operating Hours', 'swimming-pool-manager' ); ?>
                    </span>
                    <strong class="ifs-pms-mono" style="color: #0f172a;">
                        <?php echo esc_html( $opening_time_str . ' — ' . $closing_time_str ); ?>
                    </strong>
                </div>

                <div class="ifs-pms-finance-row">
                    <span style="display: flex; align-items: center; gap: 12px; color: #0f172a;">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e( 'Duty Operator', 'swimming-pool-manager' ); ?>
                    </span>
                    <strong style="color: #0f172a;">
                        <?php echo esc_html( wp_get_current_user()->display_name ); ?>
                    </strong>
                </div>

                <div class="ifs-pms-finance-row">
                    <span style="display: flex; align-items: center; gap: 12px; color: #0f172a;">
                        <span class="dashicons dashicons-fullscreen-alt"></span>
                        <?php esc_html_e( 'Turnstile Barrier Relay', 'swimming-pool-manager' ); ?>
                    </span>
                    <strong style="color: #10b981;">
                        <?php esc_html_e( 'Locked / Armed', 'swimming-pool-manager' ); ?>
                    </strong>
                </div>

                <div class="ifs-pms-finance-row ifs-pms-finance-total">
                    <span style="font-size: 15px; color: #0f172a;"><?php esc_html_e( 'Facility Status', 'swimming-pool-manager' ); ?></span>
                    <strong style="font-size: 16px; color: #10b981;">
                        <?php echo esc_html( ucfirst( $day_status ) ); ?> &bull; <?php esc_html_e( 'Active Session', 'swimming-pool-manager' ); ?>
                    </strong>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>