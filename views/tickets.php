<?php
/**
 * View Router & High-Performance Front Desk POS / Master Ticket Registry / Dedicated Details Page (Dashicons UI)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue separated POS & Tickets stylesheet with dynamic cache buster
if ( defined( 'IFS_PMS_URL' ) && defined( 'IFS_PMS_VERSION' ) ) {
    wp_enqueue_style(
        'oz-pos-tickets-css',
        IFS_PMS_URL . 'assets/css/pos-tickets.css',
        array( 'dashicons' ),
        time()
    );
}

global $wpdb;

$t_tick        = $wpdb->prefix . 'ifs_pms_tickets';
$t_cust        = $wpdb->prefix . 'ifs_pms_customers';
$currency      = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$default_price = (float) get_option( 'ifs_pms_ticket_price', 500.00 );
$b_name        = esc_html( (string) get_option( 'ifs_pms_business_name', 'Ozone Restaurant & Skypool' ) );
$address       = esc_html( (string) get_option( 'ifs_pms_address', '20th Floor, Ritz Tower, Dargah Gate, Sylhet' ) );
$phone         = esc_html( (string) get_option( 'ifs_pms_phone', '+880 1700-000000' ) );
$logo_url      = esc_url( (string) get_option( 'ifs_pms_logo_url', '' ) );
$base_url      = admin_url( 'admin.php?page=ifs-pms' );
$current_staff = wp_get_current_user()->display_name;
$is_admin      = current_user_can( 'manage_options' ) || current_user_can( 'ozone_manage_settings' );

// Determine current sub-view route
$current_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'tickets';

// ==========================================
// ROUTE: DEDICATED TICKET DETAILS VIEW PAGE
// ==========================================
if ( 'ticket_detail' === $current_view ) :
    $ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : 0;

    $ticket = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT t.*, c.name AS customer_name, c.phone AS customer_phone
             FROM {$t_tick} t
             LEFT JOIN {$t_cust} c ON t.customer_id = c.id
             WHERE t.id = %d",
            $ticket_id
        )
    );

    if ( ! $ticket ) :
        ?>
        <div class="wrap">
            <div style="background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 20px; padding: 50px; text-align: center; max-width: 600px; margin: 40px auto; box-shadow: 0 20px 40px rgba(0,0,0,0.05);">
                <div style="font-size: 42px; color: #ef4444; margin-bottom: 16px; display: inline-flex;">
                    <span class="dashicons dashicons-shield" style="font-size: 42px; width: 42px; height: 42px;"></span>
                </div>
                <h3 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 10px;"><?php esc_html_e( 'Ticket Record Not Found', 'swimming-pool-manager' ); ?></h3>
                <p style="color: #64748b; font-size: 14.5px; margin: 0 0 24px;"><?php esc_html_e( 'The requested ticket ID does not exist or has been removed from the registry.', 'swimming-pool-manager' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=tickets&tab=list' ) ); ?>" class="oz-btn oz-btn-primary" style="height: 48px; padding: 0 24px; border-radius: 12px; background: #0284c7; color: #ffffff; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Back to Master Ledger', 'swimming-pool-manager' ); ?>
                </a>
            </div>
        </div>
        <?php
        return;
    endif;

    // Automatic Stay Duration Calculations
    $check_in_epoch  = strtotime( $ticket->sold_at );
    $duration_hrs    = max( 1, (int) $ticket->duration_hours );
    $check_out_epoch = $check_in_epoch + ( $duration_hrs * 3600 );
    $check_in_time   = gmdate( 'h:i A', $check_in_epoch );
    $check_out_time  = gmdate( 'h:i A', $check_out_epoch );
    $is_overstay     = ( 'Valid' === $ticket->status || 'Used' === $ticket->status ) && ( current_time( 'timestamp' ) > $check_out_epoch );

    $is_valid           = ( 'Valid' === $ticket->status );
    $status_badge_class = $is_valid
        ? 'background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3);'
        : ( 'Used' === $ticket->status
            ? 'background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3);'
            : ( 'Completed' === $ticket->status
                ? 'background: rgba(2, 132, 199, 0.12); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.3);'
                : 'background: rgba(244, 63, 94, 0.12); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.3);' ) );
    ?>

    <div class="oz-detail-wrap">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=tickets&tab=list' ) ); ?>" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13.5px; color: #0284c7; text-decoration: none;">
                <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Back to All Tickets Ledger', 'swimming-pool-manager' ); ?>
            </a>
            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="ozPrintTicketDetailSlip();" class="oz-btn" style="height: 42px; padding: 0 20px; border-radius: 12px; background: #0284c7; border: 1.5px solid #0284c7; font-weight: 700; color: #ffffff; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Ticket Slip', 'swimming-pool-manager' ); ?>
                </button>
            </div>
        </div>

        <div class="oz-detail-card" style="background:#ffffff; border-radius:18px; padding:24px; border:1px solid #e2e8f0;">
            <div class="oz-detail-head" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 52px; height: 52px; border-radius: 16px; background: rgba(2, 132, 199, 0.1); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <span class="dashicons dashicons-tickets-alt" style="font-size: 28px; width: 28px; height: 28px;"></span>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 19px; font-weight: 800; color: #0f172a;"><?php esc_html_e( 'Serial Pass Details', 'swimming-pool-manager' ); ?></h3>
                        <span class="ifs-pms-mono" style="font-size: 14px; color: #0284c7; font-weight: 800; letter-spacing: 0.5px;"><?php printf( esc_html__( 'Serial No: %s', 'swimming-pool-manager' ), esc_html( $ticket->ticket_code ) ); ?></span>
                    </div>
                </div>
                <?php if ( $is_overstay ) : ?>
                    <span style="padding: 8px 18px; border-radius: 30px; font-weight: 800; font-size: 13px; background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5;">
                        <?php esc_html_e( 'OVERSTAY ALERT', 'swimming-pool-manager' ); ?>
                    </span>
                <?php else : ?>
                    <span style="padding: 8px 18px; border-radius: 30px; font-weight: 800; font-size: 13px; <?php echo esc_attr( $status_badge_class ); ?>">
                        <?php echo esc_html( $ticket->status ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="oz-detail-body" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-top:20px;">
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Guest Name', 'swimming-pool-manager' ); ?></label>
                    <strong style="color:#0f172a; font-size:15px;"><?php echo esc_html( ! empty( $ticket->customer_name ) ? $ticket->customer_name : __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?></strong>
                </div>
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Contact Phone', 'swimming-pool-manager' ); ?></label>
                    <strong class="ifs-pms-mono" style="color:#0f172a; font-size:15px;"><?php echo esc_html( $ticket->customer_phone ?: '-' ); ?></strong>
                </div>
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Admissions & Inclusions', 'swimming-pool-manager' ); ?></label>
                    <strong class="ifs-pms-mono" style="color:#a855f7; font-size:14.5px;"><?php echo esc_html( $ticket->package_details ?: '1 Adult' ); ?></strong>
                </div>
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Duration Booked', 'swimming-pool-manager' ); ?></label>
                    <strong class="ifs-pms-mono" style="color:#0284c7; font-size:15px;"><?php echo esc_html( (string) $duration_hrs ); ?> <?php echo esc_html( _n( 'Hour', 'Hours', $duration_hrs, 'swimming-pool-manager' ) ); ?></strong>
                </div>
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Check-In (Entry Time)', 'swimming-pool-manager' ); ?></label>
                    <strong class="ifs-pms-mono" style="color:#059669; font-size:15px;"><?php echo esc_html( $check_in_time ); ?></strong>
                </div>
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Check-Out (Exit Deadline)', 'swimming-pool-manager' ); ?></label>
                    <strong class="ifs-pms-mono" style="color:<?php echo $is_overstay ? '#ef4444' : '#0284c7'; ?>; font-size:15px;"><?php echo esc_html( $check_out_time ); ?></strong>
                </div>
                <?php if ( ! empty( $ticket->room_no ) ) : ?>
                    <div>
                        <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Hotel Room Number', 'swimming-pool-manager' ); ?></label>
                        <strong class="ifs-pms-mono" style="color:#0284c7; font-size:15px;"><?php echo esc_html( $ticket->room_no ); ?></strong>
                    </div>
                <?php endif; ?>
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Total Amount Paid', 'swimming-pool-manager' ); ?></label>
                    <strong class="ifs-pms-mono" style="color:#0f172a; font-size:15px;"><?php echo esc_html( $currency . ' ' . number_format( (float) $ticket->amount, 2 ) ); ?></strong>
                </div>
                <div>
                    <label style="color:#64748b; font-size:12px; display:block;"><?php esc_html_e( 'Payment Method', 'swimming-pool-manager' ); ?></label>
                    <strong style="color:#0f172a; font-size:15px;"><?php echo esc_html( $ticket->payment_method ?: 'Cash' ); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Thermal Slip Container for 80mm POS Printing -->
    <div id="ozDetailThermalSlip" style="display: none; background: #ffffff; color: #000000; font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; width: 72mm; margin: 0 auto; padding: 8px;">
        <div style="text-align: center;">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <div style="display: flex; justify-content: center; margin-bottom: 6px;">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" style="max-height: 40px; width: auto;">
                </div>
            <?php endif; ?>
            <strong style="font-size: 15px; letter-spacing: 0.5px; text-transform: uppercase; color: #000;"><?php echo $b_name; ?></strong>
            <div style="font-size: 10.5px; color: #333; margin-top: 2px; line-height: 1.4;">
                <?php echo $address; ?><br>
                <?php echo esc_html__( 'Tel:', 'swimming-pool-manager' ) . ' ' . $phone; ?>
            </div>
        </div>

        <div style="border-bottom: 1.5px dashed #000; margin: 8px 0;"></div>

        <div style="text-align: center; margin: 8px 0;">
            <div id="ozDetailSlipQrWrap" style="display: flex; justify-content: center; margin-bottom: 8px;"></div>
            <div style="font-size: 9px; font-weight: 800; letter-spacing: 1.2px; color: #555; text-transform: uppercase;">
                <?php esc_html_e( 'Serial No / Token', 'swimming-pool-manager' ); ?>
            </div>
            <div class="ifs-pms-mono" style="font-weight: 800; font-size: 16px; letter-spacing: 1px; color: #000; margin-top: 2px;">
                <?php echo esc_html( $ticket->ticket_code ); ?>
            </div>
        </div>

        <div style="border-bottom: 1.5px dashed #000; margin: 8px 0;"></div>

        <div style="font-size: 9.5px; font-weight: 800; color: #555; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px;">
            <?php esc_html_e( 'Guest Identification', 'swimming-pool-manager' ); ?>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Guest Type', 'swimming-pool-manager' ); ?>:</td>
                <td style="text-align: right; font-weight: 700; color: #000;">
                    <?php echo esc_html( ! empty( $ticket->room_no ) ? __( 'Hotel Room Guest', 'swimming-pool-manager' ) : __( 'General Customer', 'swimming-pool-manager' ) ); ?>
                </td>
            </tr>
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Guest Name', 'swimming-pool-manager' ); ?>:</td>
                <td style="text-align: right; font-weight: 700; color: #000;">
                    <?php echo esc_html( ! empty( $ticket->customer_name ) ? $ticket->customer_name : __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?>
                </td>
            </tr>
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Contact #', 'swimming-pool-manager' ); ?>:</td>
                <td style="text-align: right; font-weight: 700; color: #000;">
                    <?php echo esc_html( $ticket->customer_phone ?: '-' ); ?>
                </td>
            </tr>
            <?php if ( ! empty( $ticket->room_no ) ) : ?>
                <tr>
                    <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Hotel Room #', 'swimming-pool-manager' ); ?>:</td>
                    <td style="text-align: right; font-weight: 800; color: #000;">
                        <?php echo esc_html( $ticket->room_no ); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>

        <div style="border-bottom: 1.5px dashed #000; margin: 8px 0;"></div>

        <div style="font-size: 9.5px; font-weight: 800; color: #555; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px;">
            <?php esc_html_e( 'Pass & Headcount Inclusions', 'swimming-pool-manager' ); ?>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <tr>
                <td style="color: #444; padding: 2px 0; vertical-align: top;"><?php esc_html_e( 'Admissions:', 'swimming-pool-manager' ); ?></td>
                <td style="text-align: right; font-weight: 700; color: #000;">
                    <?php 
                    $pkgs = explode( ',', $ticket->package_details );
                    foreach ( $pkgs as $p ) {
                        echo '<div>' . esc_html( trim( $p ) ) . '</div>';
                    }
                    if ( empty( $ticket->package_details ) ) {
                        echo 'Adult x 1';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Session Duration:', 'swimming-pool-manager' ); ?></td>
                <td style="text-align: right; font-weight: 700; color: #000;">
                    <?php echo esc_html( (string) $duration_hrs ); ?> Hour(s) Session
                </td>
            </tr>
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Check-In:', 'swimming-pool-manager' ); ?></td>
                <td style="text-align: right; font-weight: 700; color: #059669;"><?php echo esc_html( $check_in_time ); ?></td>
            </tr>
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Check-Out:', 'swimming-pool-manager' ); ?></td>
                <td style="text-align: right; font-weight: 700; color: #0284c7;"><?php echo esc_html( $check_out_time ); ?></td>
            </tr>
        </table>

        <div style="border-bottom: 1.5px dashed #000; margin: 8px 0;"></div>

        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Payment Method', 'swimming-pool-manager' ); ?>:</td>
                <td style="text-align: right; font-weight: 700; color: #000;"><?php echo esc_html( $ticket->payment_method ?: 'Cash' ); ?></td>
            </tr>
            <tr>
                <td style="color: #444; padding: 2px 0;"><?php esc_html_e( 'Desk Cashier', 'swimming-pool-manager' ); ?>:</td>
                <td style="text-align: right; color: #333;"><?php echo esc_html( $ticket->sold_by ); ?></td>
            </tr>
        </table>

        <div style="border-bottom: 1.5px dashed #000; margin: 8px 0;"></div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0;">
            <span style="font-weight: 800; font-size: 13px; color: #000;"><?php esc_html_e( 'NET PAYABLE:', 'swimming-pool-manager' ); ?></span>
            <span style="font-size: 18px; font-weight: 800; color: #000;">
                <?php echo $currency . ' ' . number_format( (float) $ticket->amount, 2 ); ?>
            </span>
        </div>

        <div style="margin-top: 8px; padding: 6px; border: 1px dashed #666; border-radius: 6px; font-size: 8.5px; line-height: 1.35; color: #333;">
            <div style="font-weight: 800; text-align: center; margin-bottom: 4px;"><?php esc_html_e( 'TURNSTILE PASS & POOL SAFETY RULES', 'swimming-pool-manager' ); ?></div>
            <ul style="margin: 0; padding-left: 12px;">
                <li><?php esc_html_e( 'Valid for single turnstile gate entry on date of issue only.', 'swimming-pool-manager' ); ?></li>
                <li><?php esc_html_e( 'Proper synthetic swimwear compulsory.', 'swimming-pool-manager' ); ?></li>
                <li><?php esc_html_e( 'Mandatory shower required before entering pool.', 'swimming-pool-manager' ); ?></li>
                <li><?php esc_html_e( 'Non-refundable.', 'swimming-pool-manager' ); ?></li>
            </ul>
        </div>
    </div>

    <script>
    function ozPrintTicketDetailSlip() {
        var slipElem = document.getElementById('ozDetailThermalSlip');
        if (!slipElem) {
            window.print();
            return;
        }

        var qrContainer = document.getElementById('ozDetailSlipQrWrap');
        if (qrContainer && typeof QRCode !== 'undefined' && qrContainer.children.length === 0) {
            new QRCode(qrContainer, {
                text: <?php echo wp_json_encode( $ticket->ticket_code ); ?>,
                width: 90,
                height: 90,
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        var iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);

        var doc = iframe.contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html><html><head><title>Ticket Slip</title>');
        doc.write('<style>');
        doc.write('@page { size: 80mm auto; margin: 0; }');
        doc.write('body { margin: 0; padding: 8px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #000; width: 72mm; }');
        doc.write('.ifs-pms-mono { font-family: monospace; }');
        doc.write('table { width: 100%; border-collapse: collapse; }');
        doc.write('img { max-height: 40px; }');
        doc.write('</style></head><body>');
        doc.write(slipElem.innerHTML);
        doc.write('</body></html>');
        doc.close();

        setTimeout(function() {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            setTimeout(function() {
                document.body.removeChild(iframe);
            }, 1000);
        }, 250);
    }

    window.addEventListener('DOMContentLoaded', function() {
        var qrWrap = document.getElementById('ozDetailSlipQrWrap');
        if (qrWrap && typeof QRCode !== 'undefined') {
            new QRCode(qrWrap, {
                text: <?php echo wp_json_encode( $ticket->ticket_code ); ?>,
                width: 90,
                height: 90,
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
    </script>
    <?php
    return;
endif;
// ==========================================
// END OF ROUTE: TICKET DETAILS VIEW
// ==========================================

// Active Tab Router for Main POS / Ledger View
$active_tab = isset( $_GET['tab'] ) && 'list' === $_GET['tab'] ? 'list' : 'add';

// Check if a ticket was recently issued in this request cycle
$last_issued_ticket = null;
if ( isset( $_GET['issued_ticket_id'] ) && intval( $_GET['issued_ticket_id'] ) > 0 ) {
    $last_issued_ticket = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT t.*, c.name AS customer_name, c.phone AS customer_phone
             FROM {$t_tick} t
             LEFT JOIN {$t_cust} c ON t.customer_id = c.id
             WHERE t.id = %d",
            intval( $_GET['issued_ticket_id'] )
        )
    );
}

// Retrieve Dynamic Settings & States
$pricing_tiers = get_option( 'ifs_pms_pricing_tiers', array(
    array( 'name' => 'Standard Sky Swim Pass', 'age_group' => 'Adult (13+ yrs)', 'price' => 500.00 ),
    array( 'name' => 'Junior Splash Pass', 'age_group' => 'Child (Under 13 yrs)', 'price' => 300.00 ),
) );

$enable_amenities = get_option( 'ifs_pms_enable_amenities', '1' );
$pool_status      = get_option( 'ifs_pms_pool_status', 'open' );

// Day-Wise Operating Hours Check
$current_day_key  = strtolower( current_time( 'l' ) );
$weekly_schedule  = get_option( 'ifs_pms_weekly_schedule', array() );
$today_schedule   = $weekly_schedule[ $current_day_key ] ?? array( 'status' => 'open', 'open' => '10:00', 'close' => '23:00' );
$is_today_open    = ( 'open' === $today_schedule['status'] );
$current_time_val = current_time( 'H:i' );
$is_within_hours  = ( $current_time_val >= $today_schedule['open'] && $current_time_val <= $today_schedule['close'] );

// Setup Token Counter & Sequence (OZ-SEP-21-0005)
$today_start  = current_time( 'Y-m-d 00:00:00' );
$today_end    = current_time( 'Y-m-d 23:59:59' );
$today_count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$t_tick} WHERE sold_at >= %s AND sold_at <= %s", $today_start, $today_end ) );
$preview_code = 'OZ-' . strtoupper( current_time( 'M' ) ) . '-' . current_time( 'd' ) . '-' . str_pad( (string) ( $today_count + 1 ), 4, '0', STR_PAD_LEFT );

// --- ADVANCED CALENDAR / DATE RANGE FILTER ENGINE ---
$date_filter = sanitize_key( $_GET['date_filter'] ?? 'all' );
$custom_from = sanitize_text_field( $_GET['custom_from'] ?? '' );
$custom_to   = sanitize_text_field( $_GET['custom_to'] ?? '' );
$status_flt  = sanitize_key( $_GET['status_flt'] ?? 'ALL' );

$where_clauses = array( '1=1' );
$where_values  = array();

switch ( $date_filter ) {
    case 'today':
        $where_clauses[] = 'DATE(t.sold_at) = %s';
        $where_values[]  = current_time( 'Y-m-d' );
        break;
    case 'tomorrow':
        $where_clauses[] = 'DATE(t.sold_at) = %s';
        $where_values[]  = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( current_time( 'Y-m-d' ) ) ) );
        break;
    case 'this_week':
        $monday = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( current_time( 'Y-m-d' ) ) ) );
        $sunday = gmdate( 'Y-m-d', strtotime( 'sunday this week', strtotime( current_time( 'Y-m-d' ) ) ) );
        $where_clauses[] = 'DATE(t.sold_at) BETWEEN %s AND %s';
        $where_values[]  = $monday;
        $where_values[]  = $sunday;
        break;
    case 'this_month':
        $where_clauses[] = 'YEAR(t.sold_at) = %d AND MONTH(t.sold_at) = %d';
        $where_values[]  = (int) current_time( 'Y' );
        $where_values[]  = (int) current_time( 'm' );
        break;
    case 'custom':
        if ( ! empty( $custom_from ) && ! empty( $custom_to ) ) {
            $where_clauses[] = 'DATE(t.sold_at) BETWEEN %s AND %s';
            $where_values[]  = $custom_from;
            $where_values[]  = $custom_to;
        } elseif ( ! empty( $custom_from ) ) {
            $where_clauses[] = 'DATE(t.sold_at) >= %s';
            $where_values[]  = $custom_from;
        }
        break;
}

if ( in_array( $status_flt, array( 'Valid', 'Used', 'Cancelled', 'Completed' ), true ) ) {
    $where_clauses[] = 't.status = %s';
    $where_values[]  = $status_flt;
}

$where_sql = implode( ' AND ', $where_clauses );

// Query Paginated Records
$per_page = 15;
$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset   = ( $paged - 1 ) * $per_page;

if ( ! empty( $where_values ) ) {
    $total_tickets = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$t_tick} t WHERE {$where_sql}", $where_values ) );
    $all_tickets   = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.*, c.name AS customer_name, c.phone AS customer_phone
             FROM {$t_tick} t
             LEFT JOIN {$t_cust} c ON t.customer_id = c.id
             WHERE {$where_sql}
             ORDER BY t.id DESC
             LIMIT %d OFFSET %d",
            array_merge( $where_values, array( $per_page, $offset ) )
        )
    );
} else {
    $total_tickets = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_tick} t WHERE {$where_sql}" );
    $all_tickets   = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.*, c.name AS customer_name, c.phone AS customer_phone
             FROM {$t_tick} t
             LEFT JOIN {$t_cust} c ON t.customer_id = c.id
             WHERE {$where_sql}
             ORDER BY t.id DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );
}

$total_pages = ceil( $total_tickets / $per_page );
?>

<div class="oz-pos-wrapper">
    <!-- Sub Navigation Tab Bar -->
    <div class="oz-subnav-bar" role="tablist">
        <button type="button" class="oz-subnav-btn <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>" id="ozTabBtnAdd" onclick="ozSwitchTicketTab('add', this)">
            <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Add Ticket', 'swimming-pool-manager' ); ?>
        </button>
        <button type="button" class="oz-subnav-btn <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>" id="ozTabBtnList" onclick="ozSwitchTicketTab('list', this)">
            <span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'All Tickets', 'swimming-pool-manager' ); ?>
        </button>
    </div>

    <!-- TAB 1: Add Ticket POS -->
    <div id="ozTicketPaneAdd" class="oz-tab-pane <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>">
        <?php if ( 'open' !== $pool_status || ! $is_today_open || ! $is_within_hours ) : ?>
            <div style="background: rgba(244, 63, 94, 0.08); border: 1.5px solid rgba(244, 63, 94, 0.3); color: #f43f5e; padding: 16px 22px; border-radius: 16px; margin-bottom: 24px; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 14px;">
                <div style="font-size: 20px; display: inline-flex;">
                    <span class="dashicons dashicons-shield"></span>
                </div>
                <div>
                    <?php if ( 'closed' === $pool_status ) : ?>
                        <?php esc_html_e( 'Notice: The pool is currently marked as CLOSED by management.', 'swimming-pool-manager' ); ?>
                    <?php elseif ( 'maintenance' === $pool_status ) : ?>
                        <?php esc_html_e( 'Notice: Pool is undergoing routine MAINTENANCE.', 'swimming-pool-manager' ); ?>
                    <?php else : ?>
                        <?php printf( esc_html__( 'Notice: Outside normal operating hours (%s: %s to %s).', 'swimming-pool-manager' ), ucfirst( $current_day_key ), esc_html( $today_schedule['open'] ), esc_html( $today_schedule['close'] ) ); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="oz-pos-layout">
            <!-- Left Console Form -->
            <div class="oz-pos-card">
                <div class="oz-pos-head">
                    <h3 class="oz-pos-title">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e( 'Skypool Front Gate POS', 'swimming-pool-manager' ); ?>
                    </h3>
                    <span style="font-size: 12px; color: #64748b;">
                        <?php esc_html_e( 'Print Shortcut:', 'swimming-pool-manager' ); ?>
                        <kbd style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 3px 6px; border-radius: 6px; font-weight: 800; color: #0284c7;">Ctrl + Enter</kbd>
                    </span>
                </div>

                <form method="POST" action="<?php echo esc_url( $base_url . '&view=tickets' ); ?>" id="ozPosMasterForm" onsubmit="return ozHandleFormSubmit(event);">
                    <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                    <input type="hidden" name="ifs_pms_action" value="issue_ticket">
                    <input type="hidden" name="package_name" id="ozPackageNameInput" value="Adult x 1">
                    <input type="hidden" name="duration_hours" id="ozDurationHoursInput" value="1">
                    <input type="hidden" name="amount" id="ozSubmittedAmount" value="500.00">
                    <input type="hidden" name="payment_method" id="ozSelectedPayment" value="Cash">
                    <input type="hidden" name="guest_type" id="ozGuestTypeInput" value="customer">

                    <div class="oz-pos-form-wrap">
                        <!-- Guest Classification -->
                        <div class="oz-field-group">
                            <label class="oz-field-label">
                                <span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Guest Classification', 'swimming-pool-manager' ); ?> *
                            </label>
                            <div class="oz-guest-type-selector">
                                <button type="button" class="oz-type-pill active" id="ozTypePillWalkin" onclick="ozSetGuestType('walkin');">
                                    <span class="dashicons dashicons-groups"></span>
                                    <div>
                                        <strong><?php esc_html_e( 'General Customer', 'swimming-pool-manager' ); ?></strong>
                                        <small><?php esc_html_e( 'Standard Ticket Rates Apply', 'swimming-pool-manager' ); ?></small>
                                    </div>
                                </button>

                                <button type="button" class="oz-type-pill" id="ozTypePillRoom" onclick="ozSetGuestType('room');">
                                    <span class="dashicons dashicons-building"></span>
                                    <div>
                                        <strong><?php esc_html_e( 'Hotel Room Guest', 'swimming-pool-manager' ); ?></strong>
                                        <small><?php esc_html_e( 'Complimentary Pass', 'swimming-pool-manager' ); ?></small>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Guest Details -->
                        <div class="oz-grid-2">
                            <div class="oz-field-group">
                                <label class="oz-field-label" for="ozGuestName"><?php esc_html_e( 'Guest Name', 'swimming-pool-manager' ); ?> *</label>
                                <input type="text" name="name" id="ozGuestName" required placeholder="<?php esc_attr_e( 'e.g. Tanvir Ahmed', 'swimming-pool-manager' ); ?>" autocomplete="off" oninput="ozSyncReceiptDetails();">
                            </div>
                            <div class="oz-field-group">
                                <label class="oz-field-label" for="ozGuestPhone"><?php esc_html_e( 'Mobile Number', 'swimming-pool-manager' ); ?> *</label>
                                <input type="tel" name="phone" id="ozGuestPhone" required placeholder="017XXXXXXXX" pattern="[0-9+\s\-]{7,20}" autocomplete="off" oninput="ozSyncReceiptDetails();">
                            </div>
                        </div>

                        <!-- Hotel Room Number Input -->
                        <div class="oz-field-group" id="ozRoomNumberWrap" style="display: none; margin-top: 14px;">
                            <label class="oz-field-label" for="ozHotelRoomNo">
                                <span class="dashicons dashicons-building"></span> <?php esc_html_e( 'Hotel Guest Room Number', 'swimming-pool-manager' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" name="room_no" id="ozHotelRoomNo" class="ifs-pms-mono" placeholder="e.g. Room 402" autocomplete="off" oninput="ozSyncRoomDisplay(this.value);">
                        </div>

                        <!-- Modular Multi-Package Switch Deck (Adult & Child Headcounts) -->
                        <div class="oz-field-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <label class="oz-field-label" style="margin: 0;">
                                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Admission Packages & Headcounts (Adult / Child)', 'swimming-pool-manager' ); ?> *
                                </label>
                                <span style="font-size: 11.5px; color: #64748b; font-weight: 600;">
                                    <?php esc_html_e( 'Adjust persons & duration per tier', 'swimming-pool-manager' ); ?>
                                </span>
                            </div>

                            <div class="oz-modular-deck" id="ozModularDeckWrap">
                                <?php if ( ! empty( $pricing_tiers ) ) : ?>
                                    <?php foreach ( $pricing_tiers as $index => $tier ) :
                                        $is_default_on = ( 0 === $index );
                                    ?>
                                        <div class="oz-tier-box <?php echo $is_default_on ? 'is-enabled' : ''; ?>" id="ozTierBox_<?php echo esc_attr( $index ); ?>"
                                             data-index="<?php echo esc_attr( $index ); ?>"
                                             data-name="<?php echo esc_attr( $tier['name'] ); ?>"
                                             data-age="<?php echo esc_attr( $tier['age_group'] ?? 'General' ); ?>"
                                             data-price="<?php echo esc_attr( $tier['price'] ); ?>">

                                            <div class="oz-tier-box-head">
                                                <div>
                                                    <div class="oz-tier-title"><?php echo esc_html( $tier['name'] ); ?></div>
                                                    <span class="oz-tier-badge"><span class="dashicons dashicons-admin-users" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> <?php echo esc_html( $tier['age_group'] ?? 'General' ); ?></span>
                                                </div>
                                                <label class="oz-switch" title="<?php esc_attr_e( 'Enable / Disable Tier', 'swimming-pool-manager' ); ?>">
                                                    <input type="checkbox" class="oz-tier-toggle-input" id="ozTierToggle_<?php echo esc_attr( $index ); ?>" <?php checked( $is_default_on ); ?> onchange="ozToggleTierSwitch(<?php echo esc_attr( $index ); ?>)">
                                                    <span class="oz-slider"></span>
                                                </label>
                                            </div>

                                            <div class="oz-tier-controls-row">
                                                <div class="oz-metric-pill">
                                                    <span class="oz-metric-label"><?php esc_html_e( 'Base Rate', 'swimming-pool-manager' ); ?></span>
                                                    <strong class="ifs-pms-mono" style="color: #0284c7; font-size: 13.5px;">
                                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $tier['price'], 2 ) ); ?>
                                                        <small style="font-weight: 500; color: #64748b;">/hr</small>
                                                    </strong>
                                                </div>

                                                <div class="oz-metric-pill">
                                                    <span class="oz-metric-label"><?php esc_html_e( 'Headcount', 'swimming-pool-manager' ); ?></span>
                                                    <div class="oz-mini-qty">
                                                        <button type="button" class="oz-mini-btn" onclick="ozDeltaModularQty(<?php echo esc_attr( $index ); ?>, -1)">-</button>
                                                        <input type="number" id="ozTierPersons_<?php echo esc_attr( $index ); ?>" class="oz-mini-input ifs-pms-mono oz-live-trigger" value="<?php echo $is_default_on ? 1 : 0; ?>" min="0" max="50" oninput="ozSyncReceiptDetails();" onchange="ozSyncReceiptDetails();">
                                                        <button type="button" class="oz-mini-btn" onclick="ozDeltaModularQty(<?php echo esc_attr( $index ); ?>, 1)">+</button>
                                                    </div>
                                                </div>

                                                <div class="oz-metric-pill">
                                                    <span class="oz-metric-label"><?php esc_html_e( 'Duration (Hrs)', 'swimming-pool-manager' ); ?></span>
                                                    <div class="oz-mini-qty">
                                                        <button type="button" class="oz-mini-btn" onclick="ozDeltaModularHours(<?php echo esc_attr( $index ); ?>, -1)">-</button>
                                                        <input type="number" id="ozTierHours_<?php echo esc_attr( $index ); ?>" class="oz-mini-input ifs-pms-mono oz-live-trigger" value="1" min="1" max="12" oninput="ozSyncReceiptDetails();" onchange="ozSyncReceiptDetails();">
                                                        <button type="button" class="oz-mini-btn" onclick="ozDeltaModularHours(<?php echo esc_attr( $index ); ?>, 1)">+</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tender Methods & Calculations -->
                        <div class="oz-field-group" style="border-top: 1.5px solid #f1f5f9; padding-top: 20px;">
                            <label class="oz-field-label"><?php esc_html_e( 'Payment Tender Method', 'swimming-pool-manager' ); ?></label>
                            <div class="oz-tender-grid">
                                <div class="oz-tender-box active" onclick="ozSelectTender('Cash', this)">
                                    <span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Cash', 'swimming-pool-manager' ); ?>
                                </div>
                                <div class="oz-tender-box" onclick="ozSelectTender('Complementary', this)">
                                    <span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Complementary', 'swimming-pool-manager' ); ?>
                                </div>
                                <div class="oz-tender-box" onclick="ozSelectTender('bKash / Nagad', this)">
                                    <span class="dashicons dashicons-smartphone"></span> <?php esc_html_e( 'bKash / MFS', 'swimming-pool-manager' ); ?>
                                </div>
                                <div class="oz-tender-box" onclick="ozSelectTender('Card POS', this)">
                                    <span class="dashicons dashicons-id-alt"></span> <?php esc_html_e( 'POS Card', 'swimming-pool-manager' ); ?>
                                </div>
                            </div>

                            <!-- Cash Received Breakdown -->
                            <div class="oz-grid-2" style="margin-top: 18px;" id="ozCashWrap">
                                <div>
                                    <label class="oz-field-label" for="ozCashReceived">
                                        <?php printf( esc_html__( 'Cash Tendered (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?>
                                        <span id="ozCashRequiredStar" style="color: #ef4444; font-weight: 800;">*</span>
                                    </label>
                                    <input type="number" step="0.01" id="ozCashReceived" class="ifs-pms-mono" placeholder="0.00" required>
                                    <div class="oz-quick-cash-row">
                                        <button type="button" class="oz-btn oz-btn-sm" onclick="ozQuickCash(500)">+500</button>
                                        <button type="button" class="oz-btn oz-btn-sm" onclick="ozQuickCash(1000)">+1000</button>
                                        <button type="button" class="oz-btn oz-btn-sm" onclick="ozQuickCash('exact')">Exact</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="oz-field-label"><?php esc_html_e( 'Change Due Back', 'swimming-pool-manager' ); ?></label>
                                    <div id="ozChangeDue" class="ifs-pms-mono" style="background: #f8fafc; height: 48px; display: flex; align-items: center; padding: 0 16px; border-radius: 12px; font-weight: 800; color: #10b981; border: 1.5px solid #cbd5e1; font-size: 16px;">
                                        0.00 <?php echo $currency; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 14px; margin-top: 10px;">
                            <button type="submit" class="oz-btn oz-btn-primary oz-btn-lg" style="flex: 2;">
                                <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Confirm & Print Thermal Slip', 'swimming-pool-manager' ); ?>
                            </button>
                            <button type="button" class="oz-btn oz-btn-secondary oz-btn-lg" style="flex: 1;" onclick="ozResetTerminal()">
                                <?php esc_html_e( 'Reset (Alt+C)', 'swimming-pool-manager' ); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Right Terminal Live Slip Preview -->
            <div>
                <div class="oz-receipt-preview-card" id="ozReceiptPreviewContainer">
                    <div style="text-align: center;">
                        <?php if ( ! empty( $logo_url ) ) : ?>
                            <div style="display: flex; justify-content: center; margin-bottom: 8px;">
                                <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" style="max-height: 40px; width: auto; border-radius: 6px;">
                            </div>
                        <?php endif; ?>
                        <strong style="font-size: 15px; letter-spacing: 0.5px; text-transform: uppercase; color: #0f172a;"><?php echo $b_name; ?></strong>
                        <div style="font-size: 11px; color: #64748b; margin-top: 3px; line-height: 1.4;">
                            <?php echo $address; ?><br>
                            <?php echo esc_html__( 'Tel:', 'swimming-pool-manager' ) . ' ' . $phone; ?>
                        </div>
                    </div>

                    <div class="oz-receipt-sep"></div>

                    <!-- Synchronized Serial / Token Header -->
                    <div style="text-align: center; margin: 8px 0;">
                        <div id="ozReceiptQrWrap" style="display: flex; justify-content: center; margin-bottom: 8px;"></div>
                        <div style="font-size: 9.5px; font-weight: 800; letter-spacing: 1.2px; color: #64748b; text-transform: uppercase;">
                            <?php esc_html_e( 'Serial No / Token', 'swimming-pool-manager' ); ?>
                        </div>
                        <div id="ozPrevToken" class="ifs-pms-mono" style="font-weight: 800; font-size: 15px; letter-spacing: 1px; color: #0284c7; margin-top: 2px;">
                            <?php echo esc_html( $last_issued_ticket ? $last_issued_ticket->ticket_code : $preview_code ); ?>
                        </div>
                    </div>

                    <div class="oz-receipt-sep"></div>

                    <!-- Section 1: Guest Identification -->
                    <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">
                        <?php esc_html_e( 'Guest Identification', 'swimming-pool-manager' ); ?>
                    </div>
                    <table class="oz-receipt-table" style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="color: #64748b;"><?php esc_html_e( 'Guest Type', 'swimming-pool-manager' ); ?>:</td>
                            <td style="text-align: right; font-weight: 700; color: #0284c7;" id="ozPrevClassification">
                                <?php echo esc_html( ( $last_issued_ticket && ! empty( $last_issued_ticket->room_no ) ) ? __( 'Hotel Room Guest', 'swimming-pool-manager' ) : __( 'General Customer', 'swimming-pool-manager' ) ); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;"><?php esc_html_e( 'Guest Name', 'swimming-pool-manager' ); ?>:</td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;" id="ozPrevName">
                                <?php echo esc_html( $last_issued_ticket ? ( $last_issued_ticket->customer_name ?: __( 'Walk-in Guest', 'swimming-pool-manager' ) ) : __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;"><?php esc_html_e( 'Contact #', 'swimming-pool-manager' ); ?>:</td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;" id="ozPrevPhone">
                                <?php echo esc_html( $last_issued_ticket ? ( $last_issued_ticket->customer_phone ?: '-' ) : '017XXXXXXXX' ); ?>
                            </td>
                        </tr>
                        <tr id="ozPrevRoomRow" style="<?php echo ( $last_issued_ticket && ! empty( $last_issued_ticket->room_no ) ) ? '' : 'display: none;'; ?>">
                            <td style="color: #64748b;"><?php esc_html_e( 'Hotel Room #', 'swimming-pool-manager' ); ?>:</td>
                            <td style="text-align: right; font-weight: 800; color: #0284c7;" id="ozPrevRoom">
                                <?php echo esc_html( ( $last_issued_ticket && ! empty( $last_issued_ticket->room_no ) ) ? $last_issued_ticket->room_no : '-' ); ?>
                            </td>
                        </tr>
                    </table>

                    <div class="oz-receipt-sep"></div>

                    <!-- Section 2: Admissions & Stay Times -->
                    <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">
                        <?php esc_html_e( 'Admissions & Stay Session Times', 'swimming-pool-manager' ); ?>
                    </div>
                    <table class="oz-receipt-table">
                        <tr>
                            <td style="color: #64748b; vertical-align: top;"><?php esc_html_e( 'Admissions:', 'swimming-pool-manager' ); ?></td>
                            <td style="text-align: right; font-weight: 800; color: #0f172a;" id="ozPrevInclusions">
                                <div>Adult x 1</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding-top:4px;"><?php esc_html_e( 'Session Duration:', 'swimming-pool-manager' ); ?></td>
                            <td style="text-align: right; font-weight: 700; color: #a855f7; padding-top:4px;" id="ozPrevDuration">1 Hour(s)</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;"><?php esc_html_e( 'Check-In:', 'swimming-pool-manager' ); ?></td>
                            <td style="text-align: right; font-weight: 700; color: #059669;" id="ozPrevCheckIn">--:--</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;"><?php esc_html_e( 'Check-Out:', 'swimming-pool-manager' ); ?></td>
                            <td style="text-align: right; font-weight: 700; color: #0284c7;" id="ozPrevCheckOut">--:--</td>
                        </tr>
                    </table>

                    <div class="oz-receipt-sep"></div>

                    <!-- Section 3: Settlement Summary -->
                    <table class="oz-receipt-table" style="margin-bottom: 4px;">
                        <tr>
                            <td style="color: #64748b;"><?php esc_html_e( 'Payment Method', 'swimming-pool-manager' ); ?>:</td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;" id="ozPrevTender">
                                <?php echo esc_html( $last_issued_ticket ? $last_issued_ticket->payment_method : 'Cash' ); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;"><?php esc_html_e( 'Desk Cashier', 'swimming-pool-manager' ); ?>:</td>
                            <td style="text-align: right; color: #64748b;">
                                <?php echo esc_html( $last_issued_ticket ? $last_issued_ticket->sold_by : $current_staff ); ?>
                            </td>
                        </tr>
                    </table>

                    <div class="oz-receipt-sep"></div>

                    <!-- Final Settlement -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0;">
                        <span style="font-weight: 800; font-size: 13px; color: #0f172a;"><?php esc_html_e( 'NET PAYABLE:', 'swimming-pool-manager' ); ?></span>
                        <span style="font-size: 20px; font-weight: 800; color: #0284c7;" id="ozPrevTotal">
                            <?php echo $currency . ' ' . number_format( (float) ( $last_issued_ticket ? $last_issued_ticket->amount : 500.00 ), 2 ); ?>
                        </span>
                    </div>

                    <!-- Structured Thermal Safety Disclaimers -->
                    <div class="oz-receipt-rules">
                        <div class="oz-rules-title"><?php esc_html_e( 'TURNSTILE PASS & POOL SAFETY RULES', 'swimming-pool-manager' ); ?></div>
                        <ul class="oz-rules-list">
                            <li><?php esc_html_e( 'Valid for single turnstile gate entry on date of issue only.', 'swimming-pool-manager' ); ?></li>
                            <li><?php esc_html_e( 'Proper synthetic swimwear compulsory; cotton wear strictly restricted.', 'swimming-pool-manager' ); ?></li>
                            <li><?php esc_html_e( 'Mandatory shower required before entering the pool water.', 'swimming-pool-manager' ); ?></li>
                            <li><?php esc_html_e( 'Non-refundable.', 'swimming-pool-manager' ); ?></li>
                        </ul>
                    </div>

                    <!-- Thermal Slip Print Trigger -->
                    <div class="oz-receipt-print-action" id="ozReceiptPrintActionWrap" style="<?php echo $last_issued_ticket ? 'display: block;' : 'display: none;'; ?> margin-top: 18px; padding-top: 12px; border-top: 1.5px dashed #cbd5e1;">
                        <button type="button" class="oz-btn oz-btn-primary" style="width: 100%; height: 44px; border-radius: 10px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px;" onclick="ozPrintPreviewReceipt()">
                            <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Thermal Slip Only', 'swimming-pool-manager' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: All Tickets Master Registry -->
    <div id="ozTicketPaneList" class="oz-tab-pane <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>">
        <div class="oz-pos-card">
            <div class="oz-pos-head">
                <h3 class="oz-pos-title">
                    <span class="dashicons dashicons-media-text"></span>
                    <?php esc_html_e( 'Gate Pass Master Ledger', 'swimming-pool-manager' ); ?>
                </h3>
                <span class="ifs-pms-badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 700; padding: 5px 12px; border-radius: 8px;">
                    <?php echo $total_tickets; ?> <?php esc_html_e( 'Total Records', 'swimming-pool-manager' ); ?>
                </span>
            </div>

            <!-- ADVANCED CALENDAR & DATE FILTER BAR -->
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:16px; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; display:flex; flex-wrap:wrap; align-items:center; gap:12px;">
                <input type="hidden" name="page" value="ifs-pms">
                <input type="hidden" name="view" value="tickets">
                <input type="hidden" name="tab" value="list">

                <div style="display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-calendar-alt" style="color:#0284c7;"></span>
                    <select name="date_filter" id="ozDateFilterSelect" onchange="ozToggleCustomRange(this.value);" style="height:38px; border-radius:8px; font-weight:700; border-color:#cbd5e1;">
                        <option value="all" <?php selected( $date_filter, 'all' ); ?>><?php esc_html_e( 'All Dates', 'swimming-pool-manager' ); ?></option>
                        <option value="today" <?php selected( $date_filter, 'today' ); ?>><?php esc_html_e( 'Today', 'swimming-pool-manager' ); ?></option>
                        <option value="tomorrow" <?php selected( $date_filter, 'tomorrow' ); ?>><?php esc_html_e( 'Tomorrow', 'swimming-pool-manager' ); ?></option>
                        <option value="this_week" <?php selected( $date_filter, 'this_week' ); ?>><?php esc_html_e( 'This Week', 'swimming-pool-manager' ); ?></option>
                        <option value="this_month" <?php selected( $date_filter, 'this_month' ); ?>><?php esc_html_e( 'This Month', 'swimming-pool-manager' ); ?></option>
                        <option value="custom" <?php selected( $date_filter, 'custom' ); ?>><?php esc_html_e( 'Custom Date Range', 'swimming-pool-manager' ); ?></option>
                    </select>
                </div>

                <div id="ozCustomRangeBox" style="display:<?php echo ( 'custom' === $date_filter ) ? 'flex' : 'none'; ?>; align-items:center; gap:8px;">
                    <input type="date" name="custom_from" value="<?php echo esc_attr( $custom_from ); ?>" style="height:38px; border-radius:8px; border-color:#cbd5e1; font-weight:600;" placeholder="Start Date">
                    <span style="color:#64748b; font-weight:700;">&rarr;</span>
                    <input type="date" name="custom_to" value="<?php echo esc_attr( $custom_to ); ?>" style="height:38px; border-radius:8px; border-color:#cbd5e1; font-weight:600;" placeholder="End Date">
                </div>

                <select name="status_flt" style="height:38px; border-radius:8px; font-weight:700; border-color:#cbd5e1;">
                    <option value="ALL" <?php selected( $status_flt, 'ALL' ); ?>><?php esc_html_e( 'All Statuses', 'swimming-pool-manager' ); ?></option>
                    <option value="Valid" <?php selected( $status_flt, 'Valid' ); ?>><?php esc_html_e( 'Valid (Unused)', 'swimming-pool-manager' ); ?></option>
                    <option value="Used" <?php selected( $status_flt, 'Used' ); ?>><?php esc_html_e( 'Used (Admitted)', 'swimming-pool-manager' ); ?></option>
                    <option value="Completed" <?php selected( $status_flt, 'Completed' ); ?>><?php esc_html_e( 'Completed (Exited)', 'swimming-pool-manager' ); ?></option>
                    <option value="Cancelled" <?php selected( $status_flt, 'Cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'swimming-pool-manager' ); ?></option>
                </select>

                <button type="submit" class="oz-btn oz-btn-primary" style="height:38px; padding:0 16px; border-radius:8px;">
                    <?php esc_html_e( 'Apply Filter', 'swimming-pool-manager' ); ?>
                </button>

                <?php if ( 'all' !== $date_filter || 'ALL' !== $status_flt ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=tickets&tab=list' ) ); ?>" class="oz-btn" style="height:38px; display:inline-flex; align-items:center; padding:0 14px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; color:#64748b; text-decoration:none; font-weight:700;">
                        <?php esc_html_e( 'Reset Filter', 'swimming-pool-manager' ); ?>
                    </a>
                <?php endif; ?>
            </form>

            <div class="oz-search-bar" style="margin-bottom:14px; display:flex; gap:10px;">
                <input type="text" id="ozTicketSearchInput" placeholder="<?php esc_attr_e( 'Live filter visible tickets by code, guest name, or phone...', 'swimming-pool-manager' ); ?>" oninput="ozFilterTicketTable();" style="flex:1;">
            </div>

            <div class="oz-table-wrap">
                <table class="oz-table" id="ozTicketsMasterTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Serial No / Code', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Guest Name', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Duration', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Check-In', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Check-Out (Exit)', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'swimming-pool-manager' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $all_tickets ) ) : ?>
                            <?php foreach ( $all_tickets as $tkt ) :
                                $in_epoch   = strtotime( $tkt->sold_at );
                                $dur_hrs    = max( 1, (int) $tkt->duration_hours );
                                $out_epoch  = $in_epoch + ( $dur_hrs * 3600 );
                                $is_today   = ( date( 'Y-m-d', $in_epoch ) === current_time( 'Y-m-d' ) );
                                $is_late    = ( 'Valid' === $tkt->status || 'Used' === $tkt->status ) && $is_today && ( current_time( 'timestamp' ) > $out_epoch );
                                $is_valid   = ( 'Valid' === $tkt->status );
                                $status_class = $is_valid ? 'ifs-pms-badge-success' : ( 'Used' === $tkt->status ? 'ifs-pms-badge-warning' : ( 'Completed' === $tkt->status ? 'ifs-pms-badge-info' : 'ifs-pms-badge-danger' ) );
                            ?>
                                <tr class="oz-ticket-row <?php echo $is_late ? 'oz-row-overstay' : ''; ?>" data-status="<?php echo esc_attr( $tkt->status ); ?>" style="<?php echo $is_late ? 'background-color: #fff1f2 !important;' : ''; ?>">
                                    <td class="ifs-pms-mono" style="font-weight: 800; color: #0284c7;">
                                        <?php echo esc_html( $tkt->ticket_code ); ?>
                                    </td>
                                    <td style="font-weight: 700;">
                                        <?php echo esc_html( ! empty( $tkt->customer_name ) ? $tkt->customer_name : __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?>
                                    </td>
                                    <td class="ifs-pms-mono" style="color: #475569;">
                                        <?php echo esc_html( $tkt->customer_phone ?: '-' ); ?>
                                    </td>
                                    <td class="ifs-pms-mono">
                                        <span class="ifs-pms-badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7; font-weight: 800;">
                                            <?php echo esc_html( (string) $dur_hrs ); ?> <?php echo esc_html( _n( 'Hr', 'Hrs', $dur_hrs, 'swimming-pool-manager' ) ); ?>
                                        </span>
                                    </td>
                                    <td class="ifs-pms-mono" style="color: #059669; font-weight: 700;">
                                        <?php echo esc_html( gmdate( 'h:i A', $in_epoch ) ); ?>
                                    </td>
                                    <td class="ifs-pms-mono" style="font-weight: 700; color: <?php echo $is_late ? '#ef4444' : '#0284c7'; ?>;">
                                        <?php echo esc_html( gmdate( 'h:i A', $out_epoch ) ); ?>
                                        <?php if ( $is_late ) : ?>
                                            <span style="font-size: 10px; display: block; color: #dc2626; font-weight: 800;">OVERSTAY</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="ifs-pms-badge <?php echo esc_attr( $status_class ); ?>">
                                            <?php echo esc_html( $tkt->status ); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <?php if ( 'Used' === $tkt->status ) : ?>
                                            <button type="button" class="oz-btn oz-btn-sm" style="background: #10b981; color: #fff; border: none; font-weight:700;" onclick="ozManualCheckoutSwimmer(<?php echo esc_attr( $tkt->id ); ?>, '<?php echo esc_attr( $tkt->ticket_code ); ?>')">
                                                <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Exit', 'swimming-pool-manager' ); ?>
                                            </button>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=ticket_detail&ticket_id=' . $tkt->id ) ); ?>" class="oz-btn oz-btn-sm oz-btn-view">
                                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'swimming-pool-manager' ); ?>
                                        </a>

                                        <button type="button" class="oz-btn oz-btn-sm oz-btn-edit" onclick='ozOpenEditModal(<?php echo wp_json_encode( array(
                                            'id'      => $tkt->id,
                                            'code'    => $tkt->ticket_code,
                                            'name'    => ! empty( $tkt->customer_name ) ? $tkt->customer_name : '',
                                            'phone'   => $tkt->customer_phone,
                                            'room_no' => $tkt->room_no ?? '',
                                            'amount'  => $tkt->amount,
                                            'status'  => $tkt->status,
                                        ) ); ?>)'>
                                            <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'swimming-pool-manager' ); ?>
                                        </button>

                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url . '&view=tickets' ); ?>" style="display: inline-block; margin: 0;" onsubmit="return confirm(<?php echo wp_json_encode( __( 'Delete this ticket permanently from the registry?', 'swimming-pool-manager' ) ); ?>);">
                                                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                                                <input type="hidden" name="ifs_pms_action" value="delete_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $tkt->id ); ?>">
                                                <button type="submit" class="oz-btn oz-btn-sm oz-btn-delete" title="<?php esc_attr_e( 'Delete Permanently', 'swimming-pool-manager' ); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 48px; color: #94a3b8;">
                                    <span class="dashicons dashicons-tickets-alt" style="font-size: 36px; width: 36px; height: 36px;"></span>
                                    <div style="margin-top: 10px;"><?php esc_html_e( 'No tickets found matching the selected filters.', 'swimming-pool-manager' ); ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="oz-pagination-footer" style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size: 13px; color: #475569; font-weight: 600;">
                        <?php printf( esc_html__( 'Showing %1$d–%2$d of %3$d records', 'swimming-pool-manager' ), $offset + 1, min( $offset + $per_page, $total_tickets ), $total_tickets ); ?>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <a href="<?php echo esc_url( add_query_arg( 'paged', max( 1, $paged - 1 ) ) ); ?>" class="oz-page-num <?php echo ( $paged <= 1 ) ? 'disabled' : ''; ?>">&laquo;</a>
                        <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                            <?php if ( 1 === $i || $i === $total_pages || ( $i >= $paged - 2 && $i <= $paged + 2 ) ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="oz-page-num <?php echo ( $i === $paged ) ? 'active' : ''; ?>"><?php echo esc_html( $i ); ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', min( $total_pages, $paged + 1 ) ) ); ?>" class="oz-page-num <?php echo ( $paged >= $total_pages ) ? 'disabled' : ''; ?>">&raquo;</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="ozEditTicketModal" class="oz-modal-overlay">
    <div class="oz-modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit Ticket Record', 'swimming-pool-manager' ); ?> (<span id="ozModalTicketCode" class="ifs-pms-mono"></span>)
            </h3>
            <button type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;" onclick="ozCloseEditModal()">&times;</button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url . '&view=tickets' ); ?>" id="ozEditTicketForm">
            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
            <input type="hidden" name="ifs_pms_action" value="edit_ticket">
            <input type="hidden" name="ticket_id" id="ozModalTicketId" value="">

            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div class="oz-field-group">
                    <label class="oz-field-label"><?php esc_html_e( 'Guest Name', 'swimming-pool-manager' ); ?> *</label>
                    <input type="text" name="name" id="ozModalName" required>
                </div>
                <div class="oz-field-group">
                    <label class="oz-field-label"><?php esc_html_e( 'Contact Phone', 'swimming-pool-manager' ); ?> *</label>
                    <input type="tel" name="phone" id="ozModalPhone" required>
                </div>
                <div class="oz-field-group">
                    <label class="oz-field-label"><?php esc_html_e( 'Hotel Room Number', 'swimming-pool-manager' ); ?></label>
                    <input type="text" name="room_no" id="ozModalRoomNo" class="ifs-pms-mono" placeholder="e.g. Room 402">
                </div>
                <div class="oz-grid-2">
                    <div class="oz-field-group">
                        <label class="oz-field-label"><?php printf( esc_html__( 'Amount (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?> *</label>
                        <input type="number" step="0.01" name="amount" id="ozModalAmount" class="ifs-pms-mono" required>
                    </div>
                    <div class="oz-field-group">
                        <label class="oz-field-label"><?php esc_html_e( 'Status', 'swimming-pool-manager' ); ?> *</label>
                        <select name="status" id="ozModalStatus" style="height: 48px; border-radius: 12px; border: 1.5px solid #cbd5e1; padding: 8px 14px; font-weight: 700;">
                            <option value="Valid"><?php esc_html_e( 'Valid (Unused)', 'swimming-pool-manager' ); ?></option>
                            <option value="Used"><?php esc_html_e( 'Used (Admitted)', 'swimming-pool-manager' ); ?></option>
                            <option value="Completed"><?php esc_html_e( 'Completed (Exited)', 'swimming-pool-manager' ); ?></option>
                            <option value="Cancelled"><?php esc_html_e( 'Cancelled / Void', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 14px;">
                    <button type="submit" class="oz-btn oz-btn-primary" style="flex: 2; height: 46px; border-radius: 12px;">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Update Ticket', 'swimming-pool-manager' ); ?>
                    </button>
                    <button type="button" class="oz-btn oz-btn-secondary" style="flex: 1; height: 46px; border-radius: 12px;" onclick="ozCloseEditModal()">
                        <?php esc_html_e( 'Cancel', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Direct POS Controller, Registry Filtering & Real-Time Sync Engine
 */
(function() {
    var currentGuestClassification = 'customer';

    window.ozSetGuestType = function(type) {
        var isRoom = (type === 'room' || type === 'room_guest');
        currentGuestClassification = isRoom ? 'room_guest' : 'customer';

        var pillWalkin = document.getElementById('ozTypePillWalkin');
        var pillRoom   = document.getElementById('ozTypePillRoom');
        var roomWrap   = document.getElementById('ozRoomNumberWrap');
        var roomInput  = document.getElementById('ozHotelRoomNo');
        var cashWrap   = document.getElementById('ozCashWrap');
        var cashInput  = document.getElementById('ozCashReceived');
        var hiddenType = document.getElementById('ozGuestTypeInput');

        if (hiddenType) {
            hiddenType.value = currentGuestClassification;
        }

        if (pillWalkin && pillRoom) {
            if (isRoom) {
                pillWalkin.classList.remove('active');
                pillRoom.classList.add('active');
            } else {
                pillRoom.classList.remove('active');
                pillWalkin.classList.add('active');
            }
        }

        if (roomWrap) {
            roomWrap.style.setProperty('display', isRoom ? 'block' : 'none', 'important');
        }

        if (roomInput) {
            if (isRoom) {
                roomInput.setAttribute('required', 'required');
                setTimeout(function() { roomInput.focus(); }, 50);
            } else {
                roomInput.removeAttribute('required');
                roomInput.value = '';
            }
        }

        if (typeof window.ozSelectTender === 'function') {
            window.ozSelectTender(isRoom ? 'Complementary' : 'Cash');
        }

        if (cashWrap) {
            cashWrap.style.setProperty('display', isRoom ? 'none' : 'grid', 'important');
        }

        if (cashInput) {
            if (isRoom) {
                cashInput.removeAttribute('required');
                cashInput.value = '';
            } else {
                cashInput.setAttribute('required', 'required');
            }
        }

        window.ozSyncReceiptDetails();
    };

    window.ozSelectTender = function(tenderName, elem) {
        var tenderInput = document.getElementById('ozSelectedPayment');
        if (tenderInput) {
            tenderInput.value = tenderName;
        }
        var boxes = document.querySelectorAll('.oz-tender-box');
        boxes.forEach(function(b) {
            b.classList.remove('active');
        });
        if (elem) {
            elem.classList.add('active');
        } else {
            boxes.forEach(function(b) {
                if (b.textContent.indexOf(tenderName) !== -1) {
                    b.classList.add('active');
                }
            });
        }
        window.ozSyncReceiptDetails();
    };

    window.ozSyncRoomDisplay = function(val) {
        window.ozSyncReceiptDetails();
    };

    window.ozToggleCustomRange = function(val) {
        var box = document.getElementById('ozCustomRangeBox');
        if (box) {
            box.style.display = (val === 'custom') ? 'flex' : 'none';
        }
    };

    // 1. TIER ON/OFF SWITCH
    window.ozToggleTierSwitch = function(index) {
        var box    = document.getElementById('ozTierBox_' + index);
        var toggle = document.getElementById('ozTierToggle_' + index);
        var qtyIn  = document.getElementById('ozTierPersons_' + index);

        if (toggle && box && qtyIn) {
            if (toggle.checked) {
                box.classList.add('is-enabled');
                if (parseInt(qtyIn.value, 10) <= 0) {
                    qtyIn.value = 1;
                }
            } else {
                box.classList.remove('is-enabled');
                qtyIn.value = 0;
            }
        }
        window.ozSyncReceiptDetails();
    };

    // 2. HEADCOUNT INCREMENT / DECREMENT
    window.ozDeltaModularQty = function(index, delta) {
        var qtyIn  = document.getElementById('ozTierPersons_' + index);
        var toggle = document.getElementById('ozTierToggle_' + index);
        var box    = document.getElementById('ozTierBox_' + index);

        if (!qtyIn) return;
        var val = parseInt(qtyIn.value, 10) || 0;
        val = Math.max(0, Math.min(50, val + delta));
        qtyIn.value = val;

        if (toggle && box) {
            if (val > 0) {
                toggle.checked = true;
                box.classList.add('is-enabled');
            } else {
                toggle.checked = false;
                box.classList.remove('is-enabled');
            }
        }
        window.ozSyncReceiptDetails();
    };

    // 3. DURATION HOURS INCREMENT / DECREMENT
    window.ozDeltaModularHours = function(index, delta) {
        var hrsIn = document.getElementById('ozTierHours_' + index);
        if (!hrsIn) return;
        var val = parseInt(hrsIn.value, 10) || 1;
        val = Math.max(1, Math.min(12, val + delta));
        hrsIn.value = val;
        window.ozSyncReceiptDetails();
    };

    window.ozManualCheckoutSwimmer = function(ticketId, ticketCode) {
        if (!confirm('Process manual exit / check-out for pass ' + ticketCode + '?')) {
            return;
        }

        var data = {
            'action': 'ifs_pms_checkout_swimmer_ajax',
            'ticket_id': ticketId,
            'security': ifsPmsConfig.security_token
        };

        jQuery.post(ifsPmsConfig.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data.message || 'Patron checked out successfully.');
                location.reload();
            } else {
                alert(response.data.message || 'Failed to process checkout.');
            }
        }).fail(function() {
            alert('Server communication error. Please try again.');
        });
    };

    // CORE SYNCHRONIZER
    window.ozSyncReceiptDetails = function() {
        var nameField       = document.getElementById('ozGuestName');
        var phoneField      = document.getElementById('ozGuestPhone');
        var roomField       = document.getElementById('ozHotelRoomNo');
        var tenderField     = document.getElementById('ozSelectedPayment');
        var hiddenType      = document.getElementById('ozGuestTypeInput');
        var pillRoom        = document.getElementById('ozTypePillRoom');

        var isRoom = (currentGuestClassification === 'room_guest') || 
                     (pillRoom && pillRoom.classList.contains('active')) ||
                     (hiddenType && (hiddenType.value === 'room_guest' || hiddenType.value === 'room'));

        var name   = (nameField && nameField.value) ? nameField.value.trim() : 'Walk-in Guest';
        var phone  = (phoneField && phoneField.value) ? phoneField.value.trim() : '017XXXXXXXX';
        var room   = (roomField && roomField.value) ? roomField.value.trim() : '';
        var tender = (tenderField && tenderField.value) ? tenderField.value : 'Cash';

        var totalCost = 0;
        var maxSessionHours = 1;
        var inclusionsHTML = '';
        var inclusionsPlain = [];
        var boxes = document.querySelectorAll('.oz-tier-box');

        boxes.forEach(function(box) {
            var index    = box.getAttribute('data-index');
            var toggle   = document.getElementById('ozTierToggle_' + index);
            var qtyInput = document.getElementById('ozTierPersons_' + index);
            var hrsInput = document.getElementById('ozTierHours_' + index);

            // Tier is active if toggle checked OR box has active class
            var isTierActive = (toggle && toggle.checked) || box.classList.contains('is-enabled');

            if (isTierActive && qtyInput) {
                var tierName = box.getAttribute('data-name') || '';
                var ageGroup = box.getAttribute('data-age') || '';
                var price    = parseFloat(box.getAttribute('data-price')) || 0;
                var persons  = parseInt(qtyInput.value, 10) || 0;
                var hours    = hrsInput ? (parseInt(hrsInput.value, 10) || 1) : 1;

                if (persons > 0) {
                    if (hours > maxSessionHours) {
                        maxSessionHours = hours;
                    }
                    totalCost += (isRoom ? 0 : (price * persons * hours));

                    var categoryTag = (ageGroup.indexOf('Child') !== -1 || tierName.indexOf('Junior') !== -1 || tierName.indexOf('Child') !== -1) 
                        ? 'Child' 
                        : 'Adult';

                    inclusionsHTML += '<div>' + categoryTag + ' x ' + persons + '</div>';
                    inclusionsPlain.push(categoryTag + ' x ' + persons);
                }
            }
        });

        // Fallback default
        if (inclusionsPlain.length === 0) {
            inclusionsHTML = '<div>Adult x 1</div>';
            inclusionsPlain.push('Adult x 1');
        }

        var inclusionStr = inclusionsPlain.join(', ');
        
        var pkgInput = document.getElementById('ozPackageNameInput');
        if (pkgInput) {
            pkgInput.value = inclusionStr;
        }

        var durationInput = document.getElementById('ozDurationHoursInput');
        if (durationInput) {
            durationInput.value = maxSessionHours;
        }

        var submittedAmt = document.getElementById('ozSubmittedAmount');
        if (submittedAmt) {
            submittedAmt.value = totalCost.toFixed(2);
        }

        // --- UPDATE RIGHT PREVIEW PANEL ---
        var prevName = document.getElementById('ozPrevName');
        if (prevName) prevName.textContent = name || 'Walk-in Guest';

        var prevPhone = document.getElementById('ozPrevPhone');
        if (prevPhone) prevPhone.textContent = phone || '017XXXXXXXX';

        // 1. Admissions Live List
        var prevInclusions = document.getElementById('ozPrevInclusions');
        if (prevInclusions) {
            prevInclusions.innerHTML = inclusionsHTML;
        }

        // 2. Classification & Tender
        var prevTender = document.getElementById('ozPrevTender');
        if (prevTender) prevTender.textContent = isRoom ? 'Complementary' : tender;

        var prevClassification = document.getElementById('ozPrevClassification');
        if (prevClassification) {
            prevClassification.textContent = isRoom ? 'Hotel Room Guest' : 'General Customer';
        }

        // 3. Hotel Room Number Display
        var prevRoomRow = document.getElementById('ozPrevRoomRow');
        var prevRoom    = document.getElementById('ozPrevRoom');
        if (prevRoomRow && prevRoom) {
            if (isRoom) {
                prevRoomRow.style.setProperty('display', 'table-row', 'important');
                prevRoom.textContent = room.length > 0 ? room : '-';
            } else {
                prevRoomRow.style.setProperty('display', 'none', 'important');
                prevRoom.textContent = '-';
            }
        }

        // 4. Session Duration & Times
        var now = new Date();
        var checkout = new Date(now.getTime() + (maxSessionHours * 3600000));
        
        var prevDuration = document.getElementById('ozPrevDuration');
        var prevCheckIn  = document.getElementById('ozPrevCheckIn');
        var prevCheckOut = document.getElementById('ozPrevCheckOut');
        var prevTotal    = document.getElementById('ozPrevTotal');

        if (prevDuration) prevDuration.textContent = maxSessionHours + ' Hour(s)';
        if (prevCheckIn) prevCheckIn.textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        if (prevCheckOut) prevCheckOut.textContent = checkout.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        if (prevTotal) prevTotal.textContent = '<?php echo esc_js( $currency ); ?> ' + totalCost.toFixed(2);
    };

    window.ozPrintPreviewReceipt = function() {
        var receiptEl = document.getElementById('ozReceiptPreviewContainer');
        if (!receiptEl) {
            window.print();
            return;
        }

        var printClone = receiptEl.cloneNode(true);
        var actionWrap = printClone.querySelector('.oz-receipt-print-action');
        if (actionWrap) actionWrap.remove();

        var iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);

        var doc = iframe.contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html><html><head><title>Thermal Pass</title>');
        doc.write('<style>');
        doc.write('@page { size: 80mm auto; margin: 0; }');
        doc.write('body { margin: 0; padding: 8px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #000; width: 72mm; }');
        doc.write('.ifs-pms-mono { font-family: monospace; }');
        doc.write('.oz-receipt-sep { border-bottom: 1.5px dashed #000; margin: 8px 0; }');
        doc.write('.oz-receipt-table { width: 100%; border-collapse: collapse; font-size: 11px; }');
        doc.write('.oz-receipt-table td { padding: 3px 0; }');
        doc.write('.oz-receipt-rules { margin-top: 8px; padding: 6px; border: 1px dashed #666; border-radius: 6px; font-size: 8.5px; line-height: 1.35; color: #333; }');
        doc.write('img { max-height: 40px; }');
        doc.write('</style></head><body>');
        doc.write(printClone.innerHTML);
        doc.write('</body></html>');
        doc.close();

        setTimeout(function() {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            setTimeout(function() {
                document.body.removeChild(iframe);
            }, 1000);
        }, 250);
    };

    window.ozFilterTicketTable = function() {
        var searchInput = document.getElementById('ozTicketSearchInput');
        var query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        var rows = document.querySelectorAll('#ozTicketsMasterTable tbody .oz-ticket-row');

        rows.forEach(function(r) {
            var rowText = r.textContent.toLowerCase();
            var matchesQuery = (!query || rowText.indexOf(query) !== -1);
            r.style.display = matchesQuery ? '' : 'none';
        });
    };

    window.ozOpenEditModal = function(data) {
        document.getElementById('ozModalTicketId').value = data.id || '';
        document.getElementById('ozModalTicketCode').textContent = data.code || '';
        document.getElementById('ozModalName').value = data.name || '';
        document.getElementById('ozModalPhone').value = data.phone || '';
        document.getElementById('ozModalRoomNo').value = data.room_no || '';
        document.getElementById('ozModalAmount').value = parseFloat(data.amount || 0).toFixed(2);
        document.getElementById('ozModalStatus').value = data.status || 'Valid';

        var modal = document.getElementById('ozEditTicketModal');
        if (modal) modal.classList.add('is-visible');
    };

    window.ozCloseEditModal = function() {
        var modal = document.getElementById('ozEditTicketModal');
        if (modal) modal.classList.remove('is-visible');
    };

    window.ozHandleFormSubmit = function(e) {
        var roomWrap = document.getElementById('ozRoomNumberWrap');
        var isRoom = roomWrap && roomWrap.style.display !== 'none';
        var roomInput = document.getElementById('ozHotelRoomNo');

        if (isRoom && roomInput && !roomInput.value.trim()) {
            e.preventDefault();
            alert('Please specify the Hotel Guest Room Number.');
            roomInput.focus();
            return false;
        }

        var previewCard = document.getElementById('ozReceiptPreviewContainer');
        if (previewCard) {
            sessionStorage.setItem('oz_saved_receipt_html', previewCard.innerHTML);
            sessionStorage.setItem('oz_saved_active', '1');
        }
        return true;
    };

    window.addEventListener('DOMContentLoaded', function() {
        var masterForm = document.getElementById('ozPosMasterForm');
        if (masterForm) {
            ['input', 'keyup', 'change', 'click'].forEach(function(evt) {
                masterForm.addEventListener(evt, function() {
                    window.ozSyncReceiptDetails();
                });
            });
        }

        window.ozSyncReceiptDetails();

        var tokenElem = document.getElementById('ozPrevToken');
        var qrWrap = document.getElementById('ozReceiptQrWrap');
        if (tokenElem && qrWrap && typeof QRCode !== 'undefined') {
            var tokenCode = tokenElem.innerText.trim();
            if (tokenCode) {
                qrWrap.innerHTML = '';
                new QRCode(qrWrap, {
                    text: tokenCode,
                    width: 85,
                    height: 85,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }
    });
})();
</script>