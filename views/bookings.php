<?php
/**
 * View: Facility & Lane Reservations, Private Events & Slot Manager
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Context, Permissions & Tables
$can_book  = current_user_can( 'ifs_sell_tickets' ) || current_user_can( 'manage_options' );
$is_admin  = current_user_can( 'manage_options' ) || current_user_can( 'ifs_manage_settings' );

if ( ! $can_book ) {
    wp_die( esc_html__( 'Forbidden: Insufficient privileges to manage reservations.', 'swimming-pool-manager' ), 403 );
}

$t_bookings = $wpdb->prefix . 'ifs_pms_bookings';
$currency   = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$base_url   = admin_url( 'admin.php?page=ifs-pms&view=bookings' );
$today_ymd  = current_time( 'Y-m-d' );

// Auto-create bookings table if not exists (Zero migration friction)
$charset_collate = $wpdb->get_charset_collate();
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$t_bookings} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        booking_code varchar(50) NOT NULL,
        client_name varchar(100) NOT NULL,
        client_phone varchar(20) NOT NULL,
        event_type varchar(50) DEFAULT 'Private Lane' NOT NULL,
        booking_date date NOT NULL,
        slot_start time NOT NULL,
        slot_end time NOT NULL,
        guests_count int(4) DEFAULT 1 NOT NULL,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        advance_paid decimal(10,2) NOT NULL DEFAULT 0.00,
        status varchar(20) DEFAULT 'Confirmed' NOT NULL,
        booked_by varchar(100) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY booking_code (booking_code),
        KEY idx_date (booking_date),
        KEY idx_status (status)
    ) {$charset_collate};"
);

// 2. Action Handlers (Create & Status Updates)
$feedback = null;

// Create New Reservation
if ( isset( $_POST['ifs_pms_action'] ) && 'create_booking' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $name         = sanitize_text_field( wp_unslash( $_POST['client_name'] ?? '' ) );
    $phone        = sanitize_text_field( wp_unslash( $_POST['client_phone'] ?? '' ) );
    $event_type   = sanitize_text_field( wp_unslash( $_POST['event_type'] ?? 'Private Lane' ) );
    $b_date       = sanitize_text_field( wp_unslash( $_POST['booking_date'] ?? $today_ymd ) );
    $slot_start   = sanitize_text_field( wp_unslash( $_POST['slot_start'] ?? '10:00' ) );
    $slot_end     = sanitize_text_field( wp_unslash( $_POST['slot_end'] ?? '12:00' ) );
    $guests       = max( 1, absint( wp_unslash( $_POST['guests_count'] ?? 1 ) ) );
    $total_amount = max( 0.00, floatval( wp_unslash( $_POST['total_amount'] ?? 0.00 ) ) );
    $advance      = max( 0.00, floatval( wp_unslash( $_POST['advance_paid'] ?? 0.00 ) ) );
    $staff        = wp_get_current_user()->display_name;

    // Check for overlapping full-facility bookings on same date/time
    $conflict = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$t_bookings} 
             WHERE booking_date = %s 
             AND status IN ('Confirmed', 'Checked-in') 
             AND (
                 (slot_start <= %s AND slot_end > %s) OR
                 (slot_start < %s AND slot_end >= %s) OR
                 (slot_start >= %s AND slot_end <= %s)
             ) LIMIT 1",
            $b_date,
            $slot_start, $slot_start,
            $slot_end, $slot_end,
            $slot_start, $slot_end
        )
    );

    if ( $conflict && 'Entire Facility' === $event_type ) {
        $feedback = array(
            'success' => false,
            'message' => __( 'Slot Conflict: Another reservation overlaps with this schedule window.', 'swimming-pool-manager' ),
        );
    } elseif ( empty( $name ) || empty( $phone ) ) {
        $feedback = array(
            'success' => false,
            'message' => __( 'Client Name and Contact Phone are required.', 'swimming-pool-manager' ),
        );
    } else {
        $code = 'BK-' . strtoupper( current_time( 'M' ) ) . '-' . strtoupper( wp_generate_password( 5, false, false ) );

        $inserted = $wpdb->insert(
            $t_bookings,
            array(
                'booking_code' => $code,
                'client_name'  => $name,
                'client_phone' => $phone,
                'event_type'   => $event_type,
                'booking_date' => $b_date,
                'slot_start'   => $slot_start,
                'slot_end'     => $slot_end,
                'guests_count' => $guests,
                'total_amount' => $total_amount,
                'advance_paid' => $advance,
                'status'       => 'Confirmed',
                'booked_by'    => $staff,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            if ( function_exists( 'ifs_pms_record_audit' ) ) {
                ifs_pms_record_audit( 'create_booking', $code, "{$event_type} on {$b_date} ({$slot_start}-{$slot_end}) for {$name}" );
            }
            $feedback = array(
                'success' => true,
                'message' => sprintf( __( 'Reservation #%s booked successfully for %s.', 'swimming-pool-manager' ), $code, $name ),
            );
        }
    }
}

// Update Booking Status (Check-in, Complete, Cancel)
if ( isset( $_POST['ifs_pms_action'] ) && 'update_booking_status' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $booking_id = absint( wp_unslash( $_POST['booking_id'] ?? 0 ) );
    $new_status = sanitize_key( wp_unslash( $_POST['new_status'] ?? 'Confirmed' ) );
    $allowed    = array( 'Confirmed', 'Checked-in', 'Completed', 'Cancelled' );

    if ( in_array( $new_status, $allowed, true ) && $booking_id > 0 ) {
        $wpdb->update(
            $t_bookings,
            array( 'status' => $new_status ),
            array( 'id' => $booking_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( function_exists( 'ifs_pms_record_audit' ) ) {
            ifs_pms_record_audit( 'update_booking', "ID #{$booking_id}", "Status changed to {$new_status}" );
        }

        $feedback = array(
            'success' => true,
            'message' => sprintf( __( 'Reservation status updated to "%s".', 'swimming-pool-manager' ), $new_status ),
        );
    }
}

// 3. Telemetry Calculations
$total_upcoming   = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_bookings} WHERE booking_date >= '{$today_ymd}' AND status = 'Confirmed'" );
$today_events     = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_bookings} WHERE booking_date = '{$today_ymd}' AND status IN ('Confirmed', 'Checked-in')" );
$total_advance_in = (float) $wpdb->get_var( "SELECT COALESCE(SUM(advance_paid), 0) FROM {$t_bookings} WHERE booking_date >= '{$today_ymd}' AND status != 'Cancelled'" );

// 4. Fetch Active & Upcoming Bookings
$bookings = $wpdb->get_results( "SELECT * FROM {$t_bookings} ORDER BY booking_date ASC, slot_start ASC LIMIT 30" );
?>

<style>
.ifs-bk-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}
.ifs-bk-wrap * { box-sizing: border-box; }
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

/* 3-Column KPI Bar */
.ifs-kpi-triple {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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

/* Two-Column Working Grid */
.ifs-split-cols {
    display: grid;
    grid-template-columns: 0.85fr 1.15fr;
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

/* Badges */
.ifs-pill-tag {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-block;
}
.ifs-status-confirmed  { background: #e0f2fe; color: #0284c7; }
.ifs-status-checkedin  { background: #ecfdf5; color: #059669; }
.ifs-status-completed  { background: #f1f5f9; color: #475569; }
.ifs-status-cancelled  { background: #fef2f2; color: #dc2626; }

/* Table */
.ifs-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
}
.ifs-bk-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}
.ifs-bk-table th {
    background: var(--ifs-surface-hover, #f8fafc);
    color: var(--ifs-text-tertiary, #64748b);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1.5px solid var(--ifs-border-subtle, #e2e8f0);
}
.ifs-bk-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    vertical-align: middle;
}
.ifs-bk-table tr:hover td {
    background: var(--ifs-surface-hover, #f8fafc);
}

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
[data-theme="dark"] .ifs-bk-table th {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-bk-table td {
    border-color: rgba(255, 255, 255, 0.05);
}

@media screen and (max-width: 1024px) {
    .ifs-split-cols { grid-template-columns: 1fr; }
    .ifs-kpi-triple { grid-template-columns: 1fr; }
}
</style>

<div class="ifs-bk-wrap">
    <?php if ( is_array( $feedback ) ) : ?>
        <div style="background: <?php echo $feedback['success'] ? '#ecfdf5' : '#fef2f2'; ?>; border: 1px solid <?php echo $feedback['success'] ? '#a7f3d0' : '#fecaca'; ?>; color: <?php echo $feedback['success'] ? '#065f46' : '#991b1b'; ?>; padding: 12px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px;">
            <?php echo $feedback['success'] ? '✓' : '⚠️'; ?> <?php echo esc_html( $feedback['message'] ); ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cockpit Bar -->
    <div class="ifs-card">
        <div class="ifs-card-header">
            <div>
                <h2 class="ifs-card-title">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Facility & Lane Reservations Command', 'swimming-pool-manager' ); ?>
                </h2>
                <span style="font-size: 12px; color: var(--ifs-text-tertiary, #64748b);">
                    <?php esc_html_e( 'Private Swimming Lanes, Rooftop VIP Parties & Slot Scheduling', 'swimming-pool-manager' ); ?>
                </span>
            </div>
            <span class="ifs-pill-tag ifs-status-confirmed">
                <?php echo esc_html( sprintf( __( 'Today Active Slots: %d', 'swimming-pool-manager' ), $today_events ) ); ?>
            </span>
        </div>

        <div class="ifs-kpi-triple">
            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Scheduled Today', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #0284c7;"><?php echo esc_html( (string) $today_events ); ?></div>
                <div class="ifs-kpi-note"><?php esc_html_e( 'Slots due for check-in today', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Total Upcoming Bookings', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #10b981;"><?php echo esc_html( (string) $total_upcoming ); ?></div>
                <div class="ifs-kpi-note"><?php esc_html_e( 'Confirmed future reservations', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-kpi-box">
                <div class="ifs-kpi-sub"><?php esc_html_e( 'Advance Retained Deposits', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-kpi-val ifs-mono" style="color: #d97706;"><?php echo esc_html( $currency . ' ' . number_format_i18n( $total_advance_in, 2 ) ); ?></div>
                <div class="ifs-kpi-note"><?php esc_html_e( 'Collected booking advance balance', 'swimming-pool-manager' ); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Working Deck -->
    <div class="ifs-split-cols">
        <!-- 1. Booking Form -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e( 'Book New Reservation Slot', 'swimming-pool-manager' ); ?>
                </h3>
            </div>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <input type="hidden" name="ifs_pms_action" value="create_booking">
                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Client / Patron Name *', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="client_name" class="ifs-input" placeholder="e.g. Alex Morgan" required>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Contact Phone *', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="client_phone" class="ifs-input ifs-mono" placeholder="e.g. 01700000000" required>
                    </div>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Reservation Type', 'swimming-pool-manager' ); ?></label>
                        <select name="event_type" class="ifs-select">
                            <option value="Private Lane"><?php esc_html_e( 'Private Swimming Lane', 'swimming-pool-manager' ); ?></option>
                            <option value="Coaching / Trainer"><?php esc_html_e( 'Swimming Coach Session', 'swimming-pool-manager' ); ?></option>
                            <option value="Birthday Party"><?php esc_html_e( 'Poolside Birthday Party', 'swimming-pool-manager' ); ?></option>
                            <option value="Commercial Shoot"><?php esc_html_e( 'Commercial Photoshoot', 'swimming-pool-manager' ); ?></option>
                            <option value="Entire Facility"><?php esc_html_e( 'Entire Rooftop Pool Exclusivity', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Booking Date *', 'swimming-pool-manager' ); ?></label>
                        <input type="date" name="booking_date" class="ifs-input" value="<?php echo esc_attr( $today_ymd ); ?>" required>
                    </div>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Slot Start Time *', 'swimming-pool-manager' ); ?></label>
                        <input type="time" name="slot_start" class="ifs-input ifs-mono" value="10:00" required>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Slot End Time *', 'swimming-pool-manager' ); ?></label>
                        <input type="time" name="slot_end" class="ifs-input ifs-mono" value="12:00" required>
                    </div>
                </div>

                <div class="ifs-grid-3">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Guests (Headcount)', 'swimming-pool-manager' ); ?></label>
                        <input type="number" name="guests_count" class="ifs-input ifs-mono" value="5" min="1">
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Total Fee (' . $currency . ')', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.01" name="total_amount" id="ifsBkTotal" class="ifs-input ifs-mono" value="2500.00" oninput="ifsCalcDue()">
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Advance Paid (' . $currency . ')', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.01" name="advance_paid" id="ifsBkAdvance" class="ifs-input ifs-mono" value="1000.00" oninput="ifsCalcDue()">
                    </div>
                </div>

                <div style="background: var(--ifs-surface-hover, #f8fafc); padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; display: flex; justify-content: space-between;">
                    <span><?php esc_html_e( 'Balance Due at Check-in:', 'swimming-pool-manager' ); ?></span>
                    <strong class="ifs-mono" id="ifsBkDueDisplay" style="color: #ef4444;"><?php echo esc_html( $currency . ' 1,500.00' ); ?></strong>
                </div>

                <button type="submit" class="ifs-btn ifs-btn-primary" style="width: 100%;">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Confirm Reservation', 'swimming-pool-manager' ); ?>
                </button>
            </form>
        </div>

        <!-- 2. Bookings Ledger -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Reservation Roster', 'swimming-pool-manager' ); ?>
                </h3>
            </div>

            <div class="ifs-table-container">
                <table class="ifs-bk-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Slot & Code', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Client / Event', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Financials', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'swimming-pool-manager' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Lifecycle Action', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $bookings ) ) : ?>
                            <?php foreach ( $bookings as $bk ) : 
                                $status_slug  = strtolower( str_replace( array( '-', ' ' ), '', $bk->status ) );
                                $status_class = 'ifs-status-' . $status_slug;
                                $due          = (float) $bk->total_amount - (float) $bk->advance_paid;
                            ?>
                                <tr>
                                    <td>
                                        <strong class="ifs-mono" style="color: #0284c7;"><?php echo esc_html( $bk->booking_code ); ?></strong>
                                        <div style="font-size: 11px; color: #475569; font-weight: 600;">
                                            <?php echo esc_html( $bk->booking_date ); ?> &bull; <span class="ifs-mono"><?php echo esc_html( substr( $bk->slot_start, 0, 5 ) . ' - ' . substr( $bk->slot_end, 0, 5 ) ); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $bk->client_name ); ?></strong>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?php echo esc_html( $bk->event_type ); ?> (<?php echo esc_html( (string) $bk->guests_count ); ?> pax)
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ifs-mono" style="font-weight: 800;"><?php echo esc_html( $currency . ' ' . number_format( (float) $bk->total_amount, 2 ) ); ?></div>
                                        <div class="ifs-mono" style="font-size: 11px; color: <?php echo $due > 0 ? '#ef4444' : '#10b981'; ?>;">
                                            <?php echo $due > 0 ? esc_html__( 'Due: ', 'swimming-pool-manager' ) . esc_html( number_format( $due, 2 ) ) : esc_html__( 'Paid in Full', 'swimming-pool-manager' ); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ifs-pill-tag <?php echo esc_attr( $status_class ); ?>">
                                            <?php echo esc_html( $bk->status ); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display: inline-flex; gap: 4px;">
                                            <input type="hidden" name="ifs_pms_action" value="update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?php echo esc_attr( (string) $bk->id ); ?>">
                                            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

                                            <?php if ( 'Confirmed' === $bk->status ) : ?>
                                                <button type="submit" name="new_status" value="Checked-in" class="ifs-btn ifs-btn-primary" style="height: 30px; font-size: 11px; padding: 0 8px;">
                                                    <?php esc_html_e( 'Check-in', 'swimming-pool-manager' ); ?>
                                                </button>
                                                <button type="submit" name="new_status" value="Cancelled" class="ifs-btn ifs-btn-secondary" style="height: 30px; font-size: 11px; padding: 0 8px; color: #ef4444;" onclick="return confirm('Cancel this booking?');">
                                                    ✕
                                                </button>
                                            <?php elseif ( 'Checked-in' === $bk->status ) : ?>
                                                <button type="submit" name="new_status" value="Completed" class="ifs-btn ifs-btn-secondary" style="height: 30px; font-size: 11px; padding: 0 8px; color: #10b981;">
                                                    ✓ <?php esc_html_e( 'Complete', 'swimming-pool-manager' ); ?>
                                                </button>
                                            <?php else : ?>
                                                <span style="font-size: 11px; color: #94a3b8;">—</span>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #64748b; padding: 40px 10px;">
                                    <?php esc_html_e( 'No facility reservations scheduled.', 'swimming-pool-manager' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function ifsCalcDue() {
    var total = parseFloat(document.getElementById('ifsBkTotal').value) || 0;
    var advance = parseFloat(document.getElementById('ifsBkAdvance').value) || 0;
    var due = Math.max(0, total - advance);
    var cur = '<?php echo esc_js( $currency ); ?> ';
    document.getElementById('ifsBkDueDisplay').textContent = cur + due.toFixed(2);
}
</script>