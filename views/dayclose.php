<?php
/**
 * View: Cash Drawer Reconciliation & End-of-Day Z-Report Console
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Permissions & Access Control
$can_close = current_user_can( 'ifs_sell_tickets' ) || current_user_can( 'manage_options' );
$is_admin  = current_user_can( 'manage_options' ) || current_user_can( 'ifs_manage_settings' );

if ( ! $can_close ) {
    wp_die( esc_html__( 'Forbidden: Insufficient authorization to access shift reconciliation.', 'swimming-pool-manager' ), 403 );
}

$current_user = wp_get_current_user();
$today_ymd    = current_time( 'Y-m-d' );
$currency     = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$b_name       = (string) get_option( 'ifs_pms_business_name', 'IFS Swimming Pool' );
$base_url     = admin_url( 'admin.php?page=ifs-pms&view=dayclose' );

// 2. Database Tables
$t_tick   = $wpdb->prefix . 'ifs_pms_tickets';
$t_member = $wpdb->prefix . 'ifs_pms_memberships';
$t_exp    = $wpdb->prefix . 'ifs_pms_expenses';

// 3. Shift Financial Calculations
// Tickets by Payment Tender
$tender_breakdown = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT payment_method, COUNT(id) as count, COALESCE(SUM(amount), 0) as total 
         FROM {$t_tick} 
         WHERE DATE(sold_at) = %s AND status != 'Cancelled' 
         GROUP BY payment_method",
        $today_ymd
    )
);

$total_ticket_sales = 0.00;
$total_cash_tickets = 0.00;
$total_digital_tickets = 0.00;
$total_tickets_count = 0;

if ( ! empty( $tender_breakdown ) ) {
    foreach ( $tender_breakdown as $tb ) {
        $amount = (float) $tb->total;
        $total_ticket_sales += $amount;
        $total_tickets_count += (int) $tb->count;

        if ( 'Cash' === $tb->payment_method ) {
            $total_cash_tickets += $amount;
        } else {
            $total_digital_tickets += $amount;
        }
    }
}

// Memberships Collected Today
$memberships_today = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT COUNT(id) as count, COALESCE(SUM(amount), 0) as total 
         FROM {$t_member} 
         WHERE DATE(created_at) = %s AND status = 'Active'",
        $today_ymd
    )
);
$total_member_sales = $memberships_today ? (float) $memberships_today->total : 0.00;
$total_member_count = $memberships_today ? (int) $memberships_today->count : 0;

// Shift Outflows / Expenses
$expenses_today = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT COUNT(id) as count, COALESCE(SUM(amount), 0) as total 
         FROM {$t_exp} 
         WHERE expense_date = %s",
        $today_ymd
    )
);
$total_expenses = $expenses_today ? (float) $expenses_today->total : 0.00;

// Grand Financial Calculations
$gross_intake       = $total_ticket_sales + $total_member_sales;
$expected_cash_drawer = ( $total_cash_tickets + $total_member_sales ) - $total_expenses;
$net_retained       = $gross_intake - $total_expenses;

// 4. Handle Shift Closing Action (Form Post)
$shift_closed_msg = false;
if ( isset( $_POST['ifs_pms_action'] ) && 'close_shift_zreport' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $counted_cash = isset( $_POST['actual_counted_cash'] ) ? max( 0.00, floatval( wp_unslash( $_POST['actual_counted_cash'] ) ) ) : 0.00;
    $cash_variance = $counted_cash - $expected_cash_drawer;
    $shift_notes   = isset( $_POST['shift_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shift_notes'] ) ) : '';

    $audit_details = array(
        'operator'        => $current_user->display_name,
        'gross_intake'    => $gross_intake,
        'expected_cash'   => $expected_cash_drawer,
        'counted_cash'    => $counted_cash,
        'variance'        => $cash_variance,
        'tickets_count'   => $total_tickets_count,
        'expenses_amount' => $total_expenses,
        'notes'           => $shift_notes,
    );

    if ( function_exists( 'ifs_pms_record_audit' ) ) {
        ifs_pms_record_audit( 'shift_close_zreport', 'Z-REPORT-' . current_time( 'Ymd-His' ), $audit_details );
    }

    $shift_closed_msg = true;
}
?>

<style>
.ifs-dayclose-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}
.ifs-dayclose-wrap * { box-sizing: border-box; }
.ifs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }

/* Grid Layouts */
.ifs-card {
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.ifs-header-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    padding-bottom: 14px;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 10px;
}
.ifs-title {
    font-size: 16px;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--ifs-text-primary, #0f172a);
}

.ifs-kpi-triple {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.ifs-kpi-box {
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
    padding: 16px;
}
.ifs-kpi-sub {
    font-size: 11px;
    font-weight: 700;
    color: var(--ifs-text-tertiary, #64748b);
    text-transform: uppercase;
    margin-bottom: 6px;
}
.ifs-kpi-val {
    font-size: 24px;
    font-weight: 900;
    line-height: 1.1;
    margin-bottom: 6px;
}
.ifs-kpi-note {
    font-size: 11.5px;
    color: var(--ifs-text-tertiary, #64748b);
}

/* Two Column Layout */
.ifs-split-cols {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 18px;
}

/* Financial Ledger Rows */
.ifs-row-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 11px 0;
    border-bottom: 1px solid var(--ifs-border-subtle, #f8fafc);
    font-size: 13px;
}
.ifs-row-item:last-child { border-bottom: none; }
.ifs-row-item.total-bar {
    border-top: 1.5px solid var(--ifs-border-subtle, #e2e8f0);
    margin-top: 6px;
    padding-top: 14px;
    font-weight: 800;
    font-size: 14px;
}

/* Forms & Inputs */
.ifs-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
}
.ifs-form-group label {
    font-size: 12px;
    font-weight: 700;
    color: var(--ifs-text-secondary, #475569);
}
.ifs-input, .ifs-textarea {
    width: 100%;
    padding: 9px 12px;
    border-radius: 10px;
    border: 1.5px solid var(--ifs-border-subtle, #cbd5e1);
    background: var(--ifs-surface-hover, #f8fafc);
    color: var(--ifs-text-primary, #0f172a);
    font-size: 13.5px;
    transition: all 0.2s ease;
}
.ifs-input:focus, .ifs-textarea:focus {
    border-color: #0284c7;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    outline: none;
}

/* Buttons */
.ifs-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 42px;
    padding: 0 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 800;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
}
.ifs-btn-primary { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; }
.ifs-btn-primary:hover { transform: translateY(-1px); }
.ifs-btn-danger  { background: #ef4444; color: #ffffff; }
.ifs-btn-secondary { background: var(--ifs-surface-hover, #f1f5f9); color: var(--ifs-text-secondary, #334155); border: 1px solid var(--ifs-border-subtle, #cbd5e1); }

/* Badges */
.ifs-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.ifs-badge-success { background: #ecfdf5; color: #059669; }
.ifs-badge-danger  { background: #fef2f2; color: #dc2626; }
.ifs-badge-amber   { background: #fffbeb; color: #d97706; }

/* Thermal Receipt Printable Block */
@media print {
    body * { visibility: hidden; }
    #ifsZReportPrintArea, #ifsZReportPrintArea * { visibility: visible; }
    #ifsZReportPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        margin: 0;
        padding: 4mm;
        font-family: monospace;
        font-size: 11px;
        color: #000000;
    }
    .no-print { display: none !important; }
}

/* Dark Mode Variables */
[data-theme="dark"] .ifs-card, 
[data-theme="dark"] .ifs-kpi-box {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-input, 
[data-theme="dark"] .ifs-textarea {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.15);
    color: #f8fafc;
}

@media screen and (max-width: 1024px) {
    .ifs-split-cols { grid-template-columns: 1fr; }
    .ifs-kpi-triple { grid-template-columns: 1fr; }
}
</style>

<div class="ifs-dayclose-wrap">
    <?php if ( $shift_closed_msg ) : ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 14px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                ✓ <?php esc_html_e( 'Shift Closed Successfully. Official Z-Report logged in Audit Vault.', 'swimming-pool-manager' ); ?>
            </div>
            <button type="button" class="ifs-btn ifs-btn-primary" style="height: 32px; font-size: 11.5px;" onclick="window.print();">
                <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Z-Slip', 'swimming-pool-manager' ); ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- Header Cockpit Bar -->
    <div class="ifs-card">
        <div class="ifs-header-strip">
            <div>
                <h2 class="ifs-title">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'Cash Drawer Balancing & Z-Report Console', 'swimming-pool-manager' ); ?>
                </h2>
                <span style="font-size: 12px; color: var(--ifs-text-tertiary, #64748b);">
                    <?php echo esc_html( sprintf( __( 'Operational Session Date: %s &bull; Duty Operator: %s', 'swimming-pool-manager' ), current_time( 'l, F j, Y' ), $current_user->display_name ) ); ?>
                </span>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="ifs-btn ifs-btn-secondary" onclick="window.print();">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Z-Report Slip', 'swimming-pool-manager' ); ?>
                </button>
            </div>
        </div>

        <!-- Top Triple KPI Cards -->
        <div class="ifs-kpi-triple">
            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Gross Shift Intake', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #10b981;">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $gross_intake, 2 ) ); ?>
                </div>
                <div class="ifs-kpi-note"><?php echo esc_html( sprintf( __( '%d Admissions & Passes Sold', 'swimming-pool-manager' ), $total_tickets_count ) ); ?></div>
            </div>

            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Expected Cash in Drawer', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #0284c7;">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $expected_cash_drawer, 2 ) ); ?>
                </div>
                <div class="ifs-kpi-note"><?php esc_html_e( 'Physical currency after recorded expenses', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Shift Outflows / Expenses', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #ef4444;">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $total_expenses, 2 ) ); ?>
                </div>
                <div class="ifs-kpi-note"><?php echo esc_html( sprintf( __( '%d Outflow Vouchers Recorded', 'swimming-pool-manager' ), (int) ( $expenses_today ? $expenses_today->count : 0 ) ) ); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Working Grid -->
    <div class="ifs-split-cols">
        <!-- Column 1: System Ledger Audit Breakdown -->
        <div class="ifs-card">
            <div class="ifs-header-strip">
                <h3 class="ifs-title" style="font-size: 14.5px;">
                    <span class="dashicons dashicons-analytics"></span>
                    <?php esc_html_e( 'Revenue Tender Breakdown', 'swimming-pool-manager' ); ?>
                </h3>
                <span class="ifs-badge ifs-badge-success"><?php esc_html_e( 'Audited Records', 'swimming-pool-manager' ); ?></span>
            </div>

            <div class="ifs-row-item">
                <span><?php esc_html_e( 'Direct Cash Admissions', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-mono"><?php echo esc_html( $currency . ' ' . number_format_i18n( $total_cash_tickets, 2 ) ); ?></strong>
            </div>

            <div class="ifs-row-item">
                <span><?php esc_html_e( 'Card / Mobile Digital Gate Passes', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-mono"><?php echo esc_html( $currency . ' ' . number_format_i18n( $total_digital_tickets, 2 ) ); ?></strong>
            </div>

            <div class="ifs-row-item">
                <span><?php esc_html_e( 'Membership Subscriptions Intake', 'swimming-pool-manager' ); ?> (<?php echo esc_html( (string) $total_member_count ); ?>)</span>
                <strong class="ifs-mono"><?php echo esc_html( $currency . ' ' . number_format_i18n( $total_member_sales, 2 ) ); ?></strong>
            </div>

            <div class="ifs-row-item">
                <span><?php esc_html_e( 'Operational Cash Disbursements', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-mono" style="color: #ef4444;">-<?php echo esc_html( $currency . ' ' . number_format_i18n( $total_expenses, 2 ) ); ?></strong>
            </div>

            <div class="ifs-row-item total-bar">
                <span><?php esc_html_e( 'Target Cash Vault Transfer', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-mono" style="color: #0284c7; font-size: 16px;">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $expected_cash_drawer, 2 ) ); ?>
                </span>
            </div>
            <div class="ifs-row-item" style="padding-top: 4px; font-size: 12px; color: var(--ifs-text-tertiary, #64748b);">
                <span><?php esc_html_e( 'Net Total Retained Margin:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-mono" style="font-weight: 700; color: <?php echo $net_retained >= 0 ? '#10b981' : '#ef4444'; ?>;">
                    <?php echo esc_html( $currency . ' ' . number_format_i18n( $net_retained, 2 ) ); ?>
                </span>
            </div>
        </div>

        <!-- Column 2: Physical Drawer Count & Closing Declaration -->
        <div class="ifs-card">
            <div class="ifs-header-strip">
                <h3 class="ifs-title" style="font-size: 14.5px;">
                    <span class="dashicons dashicons-forms"></span>
                    <?php esc_html_e( 'Physical Cash Count & Close Shift', 'swimming-pool-manager' ); ?>
                </h3>
            </div>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <input type="hidden" name="ifs_pms_action" value="close_shift_zreport">
                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

                <div class="ifs-form-group">
                    <label><?php esc_html_e( 'Actual Physical Cash in Drawer *', 'swimming-pool-manager' ); ?></label>
                    <input type="number" step="0.01" name="actual_counted_cash" id="ifsCountedCash" class="ifs-input ifs-mono" style="font-size: 16px; font-weight: 800; color: #0284c7;" placeholder="0.00" oninput="ifsCalculateVariance()" required>
                </div>

                <div class="ifs-row-item" style="background: var(--ifs-surface-hover, #f8fafc); padding: 10px 14px; border-radius: 8px; margin-bottom: 14px;">
                    <span style="font-weight: 700;"><?php esc_html_e( 'Cash Variance (Short/Over):', 'swimming-pool-manager' ); ?></span>
                    <strong class="ifs-mono" id="ifsVarianceDisplay" style="font-size: 15px; color: #64748b;">
                        <?php echo esc_html( $currency . ' 0.00' ); ?>
                    </strong>
                </div>

                <div class="ifs-form-group">
                    <label><?php esc_html_e( 'Shift Handover / Discrepancy Notes', 'swimming-pool-manager' ); ?></label>
                    <textarea name="shift_notes" rows="3" class="ifs-textarea" placeholder="<?php esc_attr_e( 'e.g. Handed over evening float to supervisor...', 'swimming-pool-manager' ); ?>"></textarea>
                </div>

                <div style="margin-top: 18px;">
                    <button type="submit" class="ifs-btn ifs-btn-danger" style="width: 100%; height: 46px;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to finalize and lock the shift? This will record an immutable Z-Report in the system audit trail.', 'swimming-pool-manager' ); ?>');">
                        <span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Declare Count & Lock Shift (Z-Report)', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Clean Thermal Z-Report Output Area for Printing (80mm Thermal Receipt) -->
<div id="ifsZReportPrintArea" style="display: none;">
    <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 6px; margin-bottom: 8px;">
        <h2 style="font-size: 14px; margin: 0; text-transform: uppercase;"><?php echo esc_html( $b_name ); ?></h2>
        <div style="font-size: 11px; font-weight: bold; margin-top: 3px;"><?php esc_html_e( 'END-OF-DAY Z-REPORT', 'swimming-pool-manager' ); ?></div>
        <div style="font-size: 9.5px;"><?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?></div>
    </div>

    <div style="font-size: 10.5px; line-height: 1.6;">
        <div><strong><?php esc_html_e( 'Duty Operator:', 'swimming-pool-manager' ); ?></strong> <?php echo esc_html( $current_user->display_name ); ?></div>
        <div><strong><?php esc_html_e( 'Tickets Sold:', 'swimming-pool-manager' ); ?></strong> <?php echo esc_html( (string) $total_tickets_count ); ?></div>
        <div><strong><?php esc_html_e( 'Gross Shift Intake:', 'swimming-pool-manager' ); ?></strong> <?php echo esc_html( $currency . ' ' . number_format( $gross_intake, 2 ) ); ?></div>
        <div><strong><?php esc_html_e( 'Cash Intake:', 'swimming-pool-manager' ); ?></strong> <?php echo esc_html( $currency . ' ' . number_format( $total_cash_tickets, 2 ) ); ?></div>
        <div><strong><?php esc_html_e( 'Digital / POS Intake:', 'swimming-pool-manager' ); ?></strong> <?php echo esc_html( $currency . ' ' . number_format( $total_digital_tickets, 2 ) ); ?></div>
        <div><strong><?php esc_html_e( 'Shift Outflows:', 'swimming-pool-manager' ); ?></strong> -<?php echo esc_html( $currency . ' ' . number_format( $total_expenses, 2 ) ); ?></div>
        <div style="border-top: 1px dashed #000; margin-top: 4px; padding-top: 4px;">
            <strong><?php esc_html_e( 'EXPECTED DRAWER CASH:', 'swimming-pool-manager' ); ?></strong> <?php echo esc_html( $currency . ' ' . number_format( $expected_cash_drawer, 2 ) ); ?>
        </div>
    </div>

    <div style="text-align: center; border-top: 1px dashed #000; margin-top: 10px; padding-top: 6px; font-size: 9.5px;">
        *** <?php esc_html_e( 'END OF SHIFT DECLARATION', 'swimming-pool-manager' ); ?> ***
    </div>
</div>

<script>
function ifsCalculateVariance() {
    var expected = <?php echo (float) $expected_cash_drawer; ?>;
    var inputVal = parseFloat(document.getElementById('ifsCountedCash').value) || 0;
    var variance = inputVal - expected;
    var displayEl = document.getElementById('ifsVarianceDisplay');

    var cur = '<?php echo esc_js( $currency ); ?> ';
    if (variance === 0) {
        displayEl.textContent = cur + '0.00 (Balanced)';
        displayEl.style.color = '#10b981';
    } else if (variance > 0) {
        displayEl.textContent = '+' + cur + variance.toFixed(2) + ' (Over)';
        displayEl.style.color = '#0284c7';
    } else {
        displayEl.textContent = cur + variance.toFixed(2) + ' (Shortage)';
        displayEl.style.color = '#ef4444';
    }
}
</script>