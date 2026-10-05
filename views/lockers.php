<?php
/**
 * View: Locker Key Bands, Towel Rental & Security Deposit Tracker
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Context, Permissions & Tables
$can_manage = current_user_can( 'ifs_access_terminal' ) || current_user_can( 'manage_options' );
$is_admin   = current_user_can( 'manage_options' ) || current_user_can( 'ifs_manage_settings' );

if ( ! $can_manage ) {
    wp_die( esc_html__( 'Forbidden: Insufficient privileges to manage lockers.', 'swimming-pool-manager' ), 403 );
}

$t_lockers = $wpdb->prefix . 'ifs_pms_lockers';
$t_tick    = $wpdb->prefix . 'ifs_pms_tickets';
$t_cust    = $wpdb->prefix . 'ifs_pms_customers';

$currency = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$base_url = admin_url( 'admin.php?page=ifs-pms&view=lockers' );

// Auto-create lockers table if not exists (Zero migration friction)
$charset_collate = $wpdb->get_charset_collate();
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$t_lockers} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        locker_no varchar(20) NOT NULL,
        ticket_code varchar(50) DEFAULT NULL,
        patron_name varchar(100) NOT NULL,
        patron_phone varchar(20) NOT NULL,
        towels_issued int(3) DEFAULT 0 NOT NULL,
        deposit_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        status varchar(20) DEFAULT 'Occupied' NOT NULL,
        issued_by varchar(100) NOT NULL,
        issued_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        returned_at datetime NULL,
        PRIMARY KEY  (id),
        KEY idx_locker_no (locker_no),
        KEY idx_status (status)
    ) {$charset_collate};"
);

// 2. Handle Locker Allocation & Release Actions
$action_feedback = null;

// Issue Locker
if ( isset( $_POST['ifs_pms_action'] ) && 'assign_locker' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $locker_no    = sanitize_text_field( wp_unslash( $_POST['locker_no'] ?? '' ) );
    $ticket_code  = sanitize_text_field( wp_unslash( $_POST['ticket_code'] ?? '' ) );
    $patron_name  = sanitize_text_field( wp_unslash( $_POST['patron_name'] ?? '' ) );
    $patron_phone = sanitize_text_field( wp_unslash( $_POST['patron_phone'] ?? '' ) );
    $towels       = absint( wp_unslash( $_POST['towels_issued'] ?? 1 ) );
    $deposit      = max( 0.00, floatval( wp_unslash( $_POST['deposit_amount'] ?? 0.00 ) ) );
    $staff        = wp_get_current_user()->display_name;

    // Check if locker is already occupied
    $occupied = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_lockers} WHERE locker_no = %s AND status = 'Occupied'", $locker_no ) );

    if ( $occupied ) {
        $action_feedback = array(
            'success' => false,
            'message' => sprintf( __( 'Locker #%s is currently occupied! Please choose another locker.', 'swimming-pool-manager' ), $locker_no ),
        );
    } elseif ( empty( $locker_no ) || empty( $patron_name ) ) {
        $action_feedback = array(
            'success' => false,
            'message' => __( 'Missing required fields: Locker number and Patron Name are required.', 'swimming-pool-manager' ),
        );
    } else {
        $inserted = $wpdb->insert(
            $t_lockers,
            array(
                'locker_no'      => $locker_no,
                'ticket_code'    => $ticket_code,
                'patron_name'    => $patron_name,
                'patron_phone'   => $patron_phone,
                'towels_issued'  => $towels,
                'deposit_amount' => $deposit,
                'status'         => 'Occupied',
                'issued_by'      => $staff,
                'issued_at'      => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            if ( function_exists( 'ifs_pms_record_audit' ) ) {
                ifs_pms_record_audit( 'assign_locker', "Locker #{$locker_no}", "Assigned to {$patron_name}, Deposit: {$deposit}" );
            }
            $action_feedback = array(
                'success' => true,
                'message' => sprintf( __( 'Locker #%s successfully issued to %s.', 'swimming-pool-manager' ), $locker_no, $patron_name ),
            );
        }
    }
}

// Return / Release Locker
if ( isset( $_POST['ifs_pms_action'] ) && 'release_locker' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $entry_id = absint( wp_unslash( $_POST['entry_id'] ?? 0 ) );
    $record   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_lockers} WHERE id = %d", $entry_id ) );

    if ( $record && 'Occupied' === $record->status ) {
        $wpdb->update(
            $t_lockers,
            array(
                'status'      => 'Returned',
                'returned_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $entry_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( function_exists( 'ifs_pms_record_audit' ) ) {
            ifs_pms_record_audit( 'release_locker', "Locker #{$record->locker_no}", "Key band & towels returned. Refunded {$record->deposit_amount}" );
        }

        $action_feedback = array(
            'success' => true,
            'message' => sprintf( __( 'Locker #%s released! Refunded deposit: %s %s.', 'swimming-pool-manager' ), $record->locker_no, $currency, number_format( $record->deposit_amount, 2 ) ),
        );
    }
}

// 3. Telemetry Calculations
$total_occupied_lockers = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_lockers} WHERE status = 'Occupied'" );
$active_towels_out      = (int) $wpdb->get_var( "SELECT COALESCE(SUM(towels_issued), 0) FROM {$t_lockers} WHERE status = 'Occupied'" );
$total_deposits_held    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(deposit_amount), 0) FROM {$t_lockers} WHERE status = 'Occupied'" );

// Default Pool Locker Bay: 60 Total Lockers
$total_pool_lockers = max( $total_occupied_lockers, 60 );
$available_lockers  = max( 0, $total_pool_lockers - $total_occupied_lockers );

// 4. Fetch Active Occupied Lockers
$occupied_list = $wpdb->get_results( "SELECT * FROM {$t_lockers} WHERE status = 'Occupied' ORDER BY id DESC" );
?>

<style>
.ifs-lockers-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}
.ifs-lockers-wrap * { box-sizing: border-box; }
.ifs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }

.ifs-card {
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.ifs-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    padding-bottom: 14px;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 10px;
}

.ifs-card-title {
    font-size: 16px;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--ifs-text-primary, #0f172a);
}

/* 4-Column KPI Bar */
.ifs-kpi-quad {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}
.ifs-kpi-box {
    background: var(--ifs-surface-hover, #f8fafc);
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
    margin-bottom: 4px;
}
.ifs-kpi-note {
    font-size: 11.5px;
    color: var(--ifs-text-tertiary, #64748b);
}

/* Two-Column Split */
.ifs-split-cols {
    display: grid;
    grid-template-columns: 0.9fr 1.1fr;
    gap: 18px;
}
.ifs-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}

.ifs-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
}
.ifs-form-group label {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--ifs-text-secondary, #334155);
}

.ifs-input, .ifs-select {
    width: 100%;
    height: 42px;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1.5px solid var(--ifs-border-subtle, #cbd5e1);
    background: var(--ifs-surface-hover, #f8fafc);
    color: var(--ifs-text-primary, #0f172a);
    font-size: 13.5px;
    transition: all 0.2s ease;
}
.ifs-input:focus, .ifs-select:focus {
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
.ifs-btn-secondary { background: var(--ifs-surface-hover, #f1f5f9); color: var(--ifs-text-secondary, #334155); border: 1px solid var(--ifs-border-subtle, #cbd5e1); }
.ifs-btn-danger { background: #ef4444; color: #ffffff; }
.ifs-btn-success { background: #10b981; color: #ffffff; }

/* Table */
.ifs-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
}
.ifs-lockers-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}
.ifs-lockers-table th {
    background: var(--ifs-surface-hover, #f8fafc);
    color: var(--ifs-text-tertiary, #64748b);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1.5px solid var(--ifs-border-subtle, #e2e8f0);
}
.ifs-lockers-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    vertical-align: middle;
}
.ifs-lockers-table tr:hover td {
    background: var(--ifs-surface-hover, #f8fafc);
}

/* Badges */
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

/* Dark Mode */
[data-theme="dark"] .ifs-card, [data-theme="dark"] .ifs-kpi-box {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-input, [data-theme="dark"] .ifs-select {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.15);
    color: #f8fafc;
}
[data-theme="dark"] .ifs-lockers-table th {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-lockers-table td {
    border-color: rgba(255, 255, 255, 0.05);
}

@media screen and (max-width: 1024px) {
    .ifs-split-cols { grid-template-columns: 1fr; }
    .ifs-kpi-quad { grid-template-columns: repeat(2, 1fr); }
}
@media screen and (max-width: 640px) {
    .ifs-kpi-quad { grid-template-columns: 1fr; }
}
</style>

<div class="ifs-lockers-wrap">
    <?php if ( is_array( $action_feedback ) ) : ?>
        <div style="background: <?php echo $action_feedback['success'] ? '#ecfdf5' : '#fef2f2'; ?>; border: 1px solid <?php echo $action_feedback['success'] ? '#a7f3d0' : '#fecaca'; ?>; color: <?php echo $action_feedback['success'] ? '#065f46' : '#991b1b'; ?>; padding: 12px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px;">
            <?php echo $action_feedback['success'] ? '✓' : '⚠️'; ?> <?php echo esc_html( $action_feedback['message'] ); ?>
        </div>
    <?php endif; ?>

    <!-- KPI Telemetry Header Strip -->
    <div class="ifs-card">
        <div class="ifs-card-header">
            <div>
                <h2 class="ifs-card-title">
                    <span class="dashicons dashicons-vault"></span>
                    <?php esc_html_e( 'Locker Bay & Towel Asset Control Desk', 'swimming-pool-manager' ); ?>
                </h2>
                <span style="font-size: 12px; color: var(--ifs-text-tertiary, #64748b);">
                    <?php esc_html_e( 'Real-time Key Wristband Allocations & Security Deposits', 'swimming-pool-manager' ); ?>
                </span>
            </div>
            <span class="ifs-pill-tag ifs-status-success">
                <?php echo esc_html( sprintf( __( 'Bay Occupancy: %d%%', 'swimming-pool-manager' ), round( ( $total_occupied_lockers / $total_pool_lockers ) * 100 ) ) ); ?>
            </span>
        </div>

        <div class="ifs-kpi-quad">
            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Active Occupied Lockers', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #ef4444;"><?php echo esc_html( (string) $total_occupied_lockers ); ?></div>
                <div class="ifs-kpi-note"><?php echo esc_html( sprintf( __( 'Wristbands currently in pool deck', 'swimming-pool-manager' ) ) ); ?></div>
            </div>

            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Available Free Lockers', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #10b981;"><?php echo esc_html( (string) $available_lockers ); ?></div>
                <div class="ifs-kpi-note"><?php echo esc_html( sprintf( __( 'Ready for new guests (Total: %d)', 'swimming-pool-manager' ), $total_pool_lockers ) ); ?></div>
            </div>

            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Active Towels Dispatched', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #0284c7;"><?php echo esc_html( (string) $active_towels_out ); ?></div>
                <div class="ifs-kpi-note"><?php esc_html_e( 'Pending return to laundry desk', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Total Deposits Held', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #d97706;"><?php echo esc_html( $currency . ' ' . number_format_i18n( $total_deposits_held, 2 ) ); ?></div>
                <div class="ifs-kpi-note"><?php esc_html_e( 'Refundable upon key return', 'swimming-pool-manager' ); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Working Deck -->
    <div class="ifs-split-cols">
        <!-- 1. Assign Locker & Towel Form -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Assign Key Band & Towels', 'swimming-pool-manager' ); ?>
                </h3>
            </div>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <input type="hidden" name="ifs_pms_action" value="assign_locker">
                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Locker Number *', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="locker_no" class="ifs-input ifs-mono" placeholder="e.g. L-12" required>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Admission Pass / Ticket Code', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="ticket_code" class="ifs-input ifs-mono" placeholder="e.g. IFS-OCT-05-0001">
                    </div>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Patron Name *', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="patron_name" class="ifs-input" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Contact Phone', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="patron_phone" class="ifs-input ifs-mono" placeholder="e.g. 01700000000">
                    </div>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Towels Issued (Qty)', 'swimming-pool-manager' ); ?></label>
                        <select name="towels_issued" class="ifs-select">
                            <option value="0">0 (No Towel)</option>
                            <option value="1" selected>1 Towel</option>
                            <option value="2">2 Towels</option>
                            <option value="3">3 Towels</option>
                        </select>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Security Deposit (' . $currency . ')', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.01" name="deposit_amount" class="ifs-input ifs-mono" value="200.00" style="font-weight: 800; color: #d97706;">
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <button type="submit" class="ifs-btn ifs-btn-primary" style="width: 100%;">
                        <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Assign Locker & Issue Key', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. Active Occupied Lockers Ledger -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e( 'Currently Occupied Lockers', 'swimming-pool-manager' ); ?>
                </h3>
                <span class="ifs-pill-tag ifs-status-danger"><?php echo esc_html( sprintf( __( '%d Occupied', 'swimming-pool-manager' ), $total_occupied_lockers ) ); ?></span>
            </div>

            <div class="ifs-table-container">
                <table class="ifs-lockers-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Locker', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Patron Name', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Towels', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Deposit', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Time', 'swimming-pool-manager' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Checkout / Return', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $occupied_list ) ) : ?>
                            <?php foreach ( $occupied_list as $row ) : ?>
                                <tr>
                                    <td>
                                        <strong class="ifs-mono" style="color: #0284c7; font-size: 14px;">#<?php echo esc_html( $row->locker_no ); ?></strong>
                                        <?php if ( ! empty( $row->ticket_code ) ) : ?>
                                            <div class="ifs-mono" style="font-size: 10.5px; color: #64748b;"><?php echo esc_html( $row->ticket_code ); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $row->patron_name ); ?></strong>
                                        <div class="ifs-mono" style="font-size: 11px; color: #64748b;"><?php echo esc_html( $row->patron_phone ?: '—' ); ?></div>
                                    </td>
                                    <td><?php echo esc_html( (string) $row->towels_issued ); ?> pcs</td>
                                    <td>
                                        <strong class="ifs-mono" style="color: #d97706;"><?php echo esc_html( $currency . ' ' . number_format_i18n( (float) $row->deposit_amount, 2 ) ); ?></strong>
                                    </td>
                                    <td class="ifs-mono" style="font-size: 11px; color: #64748b;"><?php echo esc_html( gmdate( 'h:i A', strtotime( $row->issued_at ) ) ); ?></td>
                                    <td style="text-align: right;">
                                        <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display: inline-block;">
                                            <input type="hidden" name="ifs_pms_action" value="release_locker">
                                            <input type="hidden" name="entry_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
                                            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                                            <button type="submit" class="ifs-btn ifs-btn-success" style="height: 32px; font-size: 11.5px; padding: 0 12px;" onclick="return confirm('<?php echo esc_attr( sprintf( __( 'Confirm key return for Locker #%s? Refund deposit: %s %s', 'swimming-pool-manager' ), $row->locker_no, $currency, number_format( $row->deposit_amount, 2 ) ) ); ?>');">
                                                ✓ <?php esc_html_e( 'Release & Refund', 'swimming-pool-manager' ); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #64748b; padding: 40px 10px;">
                                    <span class="dashicons dashicons-vault" style="font-size: 32px; width: 32px; height: 32px; color: #94a3b8;"></span>
                                    <div style="margin-top: 8px; font-size: 13.5px; font-weight: 600;"><?php esc_html_e( 'All lockers are currently available! Zero key bands dispatched.', 'swimming-pool-manager' ); ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>