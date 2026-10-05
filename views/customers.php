<?php
/**
 * View: Patron Directory & Complete Ticket Intelligence Registry (Dashicons UI)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$t_cust   = $wpdb->prefix . 'ifs_pms_customers';
$t_tick   = $wpdb->prefix . 'ifs_pms_tickets';
$currency = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$base_url = admin_url( 'admin.php?page=ifs-pms&view=customers' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'ozone_manage_settings' );

// ============================================================================
// SELF-CONTAINED ACTION DISPATCHER (EDIT & DELETE PATRON)
// ============================================================================
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ifs_pms_action'] ) ) {
    if ( ! isset( $_POST['ifs_pms_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_pms_action_nonce'] ) ), 'ifs_pms_secure_action' ) ) {
        wp_die( esc_html__( 'Security check failed. Please refresh and try again.', 'swimming-pool-manager' ) );
    }

    $action  = sanitize_key( $_POST['ifs_pms_action'] );
    $cust_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;

    // Handle Edit Customer
    if ( 'edit_customer' === $action && $cust_id > 0 ) {
        $name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

        if ( ! empty( $name ) && ! empty( $phone ) ) {
            $wpdb->update(
                $t_cust,
                array(
                    'name'  => $name,
                    'phone' => $phone,
                ),
                array( 'id' => $cust_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
            wp_safe_redirect( add_query_arg( array( 'page' => 'ifs-pms', 'view' => 'customers', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    // Handle Delete Customer
    if ( 'delete_customer' === $action && $cust_id > 0 && $is_admin ) {
        $wpdb->delete( $t_cust, array( 'id' => $cust_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ifs-pms', 'view' => 'customers', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}

// Handle Profile View / Edit Routing States
$action_mode = sanitize_key( $_GET['mode'] ?? 'list' );
$target_id   = absint( $_GET['customer_id'] ?? 0 );

// Pagination Setup for Master List
$per_page = 15;
$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset   = ( $paged - 1 ) * $per_page;

$total_customers = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_cust}" );
$total_pages     = ceil( max( 1, $total_customers ) / $per_page );

// Aggregate Customers directly linked with Tickets Intelligence
$customers = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT 
            c.id,
            COALESCE(NULLIF(c.name, ''), 'Walk-in Guest') AS name,
            COALESCE(c.phone, '-') AS phone,
            COUNT(t.id) AS total_visits, 
            COALESCE(SUM(CASE WHEN t.status != 'Cancelled' THEN t.amount ELSE 0 END), 0.00) AS lifetime_spend,
            MAX(t.sold_at) AS last_visit,
            MIN(t.sold_at) AS first_visit,
            COALESCE(AVG(CASE WHEN t.status != 'Cancelled' THEN t.amount ELSE NULL END), 0.00) AS avg_ticket_spend
         FROM {$t_cust} c
         LEFT JOIN {$t_tick} t ON c.id = t.customer_id
         GROUP BY c.id
         ORDER BY (MAX(t.sold_at) IS NULL), MAX(t.sold_at) DESC, c.id DESC
         LIMIT %d OFFSET %d",
        $per_page,
        $offset
    )
);

// If viewing a specific customer intelligence profile
$single_customer  = null;
$customer_tickets = array();
$customer_metrics = null;
if ( 'view' === $action_mode && $target_id > 0 ) {
    $single_customer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_cust} WHERE id = %d", $target_id ) );
    if ( $single_customer ) {
        $customer_tickets = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM {$t_tick} WHERE customer_id = %d ORDER BY id DESC", 
            $target_id 
        ) );

        $customer_metrics = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                COUNT(id) AS total_passes,
                COALESCE(SUM(amount), 0.00) AS total_spend,
                COALESCE(AVG(amount), 0.00) AS avg_spend,
                SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END) AS cash_spend,
                SUM(CASE WHEN payment_method = 'Card POS' THEN amount ELSE 0 END) AS card_spend,
                SUM(CASE WHEN payment_method = 'bKash / Nagad' THEN amount ELSE 0 END) AS mfs_spend,
                SUM(CASE WHEN status = 'Used' OR status = 'Completed' THEN 1 ELSE 0 END) AS redeemed_count,
                MAX(sold_at) AS recent_pass_date
             FROM {$t_tick} 
             WHERE customer_id = %d AND status != 'Cancelled'",
            $target_id
        ) );
    }
}

// If editing a customer inline
$edit_customer = null;
if ( 'edit' === $action_mode && $target_id > 0 ) {
    $edit_customer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_cust} WHERE id = %d", $target_id ) );
}
?>

<div class="ifs-pms-customer-wrapper" style="padding: 20px;">

<?php if ( isset( $_GET['updated'] ) ) : ?>
    <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; color: #065f46; padding: 12px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 700;">
        <?php esc_html_e( 'Patron profile updated successfully.', 'swimming-pool-manager' ); ?>
    </div>
<?php endif; ?>

<?php if ( isset( $_GET['deleted'] ) ) : ?>
    <div style="background: #fef2f2; border: 1.5px solid #fecaca; color: #991b1b; padding: 12px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 700;">
        <?php esc_html_e( 'Patron removed permanently.', 'swimming-pool-manager' ); ?>
    </div>
<?php endif; ?>

<?php if ( 'view' === $action_mode && $single_customer ) : ?>
    <!-- ========================================================================== -->
    <!-- VIEW MODE: DETAILED PATRON PROFILE & TICKET HISTORY -->
    <!-- ========================================================================== -->
    <div class="ifs-pms-pos-card" style="background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div class="ifs-pms-pos-head" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 18px; margin-bottom: 20px;">
            <h3 class="ifs-pms-pos-title" style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-groups" style="color: #0284c7;"></span>
                <?php printf( esc_html__( 'Patron Profile: %s', 'swimming-pool-manager' ), esc_html( $single_customer->name ) ); ?>
            </h3>
            <div class="ifs-pms-flex-gap-10">
                <a href="<?php echo esc_url( $base_url ); ?>" class="oz-btn" style="height: 38px; padding: 0 16px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #475569; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Back to Directory', 'swimming-pool-manager' ); ?>
                </a>
            </div>
        </div>

        <div class="ifs-pms-profile-content-wrap">
            <!-- 4-Column Intelligence Stat Grid -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Mobile Contact', 'swimming-pool-manager' ); ?></div>
                    <div class="ifs-pms-mono" style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?php echo esc_html( $single_customer->phone ); ?></div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Lifetime Spend', 'swimming-pool-manager' ); ?></div>
                    <div class="ifs-pms-mono" style="font-size: 16px; font-weight: 800; color: #10b981; margin-top: 6px;"><?php echo esc_html( $currency . ' ' . number_format( (float) ( $customer_metrics->total_spend ?? 0 ), 2 ) ); ?></div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Avg Ticket Outlay', 'swimming-pool-manager' ); ?></div>
                    <div class="ifs-pms-mono" style="font-size: 16px; font-weight: 800; color: #0284c7; margin-top: 6px;"><?php echo esc_html( $currency . ' ' . number_format( (float) ( $customer_metrics->avg_spend ?? 0 ), 2 ) ); ?></div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Admitted Swims', 'swimming-pool-manager' ); ?></div>
                    <div class="ifs-pms-mono" style="font-size: 16px; font-weight: 800; color: #a855f7; margin-top: 6px;"><?php echo esc_html( (int) ( $customer_metrics->redeemed_count ?? 0 ) ); ?> / <?php echo count( $customer_tickets ); ?> Passes</div>
                </div>
            </div>

            <!-- Activity & Breakdown Cards -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 28px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                    <h5 style="margin: 0 0 12px; font-size: 14px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-id-alt"></span> <?php esc_html_e( 'Payment Method Breakdown', 'swimming-pool-manager' ); ?>
                    </h5>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                            <span style="color: #64748b;"><?php esc_html_e( 'Cash Payments:', 'swimming-pool-manager' ); ?></span>
                            <strong class="ifs-pms-mono" style="color: #0f172a;"><?php echo esc_html( $currency . ' ' . number_format( (float) ( $customer_metrics->cash_spend ?? 0 ), 2 ) ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                            <span style="color: #64748b;"><?php esc_html_e( 'Card POS Payments:', 'swimming-pool-manager' ); ?></span>
                            <strong class="ifs-pms-mono" style="color: #0f172a;"><?php echo esc_html( $currency . ' ' . number_format( (float) ( $customer_metrics->card_spend ?? 0 ), 2 ) ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                            <span style="color: #64748b;"><?php esc_html_e( 'bKash / MFS Payments:', 'swimming-pool-manager' ); ?></span>
                            <strong class="ifs-pms-mono" style="color: #0f172a;"><?php echo esc_html( $currency . ' ' . number_format( (float) ( $customer_metrics->mfs_spend ?? 0 ), 2 ) ); ?></strong>
                        </div>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                    <h5 style="margin: 0 0 12px; font-size: 14px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Engagement Timeline', 'swimming-pool-manager' ); ?>
                    </h5>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                            <span style="color: #64748b;"><?php esc_html_e( 'First Registered Visit:', 'swimming-pool-manager' ); ?></span>
                            <strong class="ifs-pms-mono" style="color: #0f172a;"><?php echo esc_html( $single_customer->created_at ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                            <span style="color: #64748b;"><?php esc_html_e( 'Most Recent Activity:', 'swimming-pool-manager' ); ?></span>
                            <strong class="ifs-pms-mono" style="color: #0f172a;"><?php echo esc_html( $customer_metrics->recent_pass_date ? $customer_metrics->recent_pass_date : __( 'None', 'swimming-pool-manager' ) ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                            <span style="color: #64748b;"><?php esc_html_e( 'Patron Record ID:', 'swimming-pool-manager' ); ?></span>
                            <strong class="ifs-pms-mono" style="color: #0284c7;">#<?php echo esc_html( $single_customer->id ); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pass History Table -->
            <h4 style="margin: 0 0 14px; font-size: 15px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-tickets-alt"></span> <?php esc_html_e( 'Associated Ticket & Pass History Ledger', 'swimming-pool-manager' ); ?>
            </h4>

            <div class="oz-table-wrap">
                <table class="oz-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; font-size: 12px; color: #64748b;">
                            <th style="padding: 10px 14px;"><?php esc_html_e( 'Pass Code', 'swimming-pool-manager' ); ?></th>
                            <th style="padding: 10px 14px;"><?php esc_html_e( 'Package / Details', 'swimming-pool-manager' ); ?></th>
                            <th style="padding: 10px 14px;"><?php esc_html_e( 'Amount Paid', 'swimming-pool-manager' ); ?></th>
                            <th style="padding: 10px 14px;"><?php esc_html_e( 'Payment Method', 'swimming-pool-manager' ); ?></th>
                            <th style="padding: 10px 14px;"><?php esc_html_e( 'Status', 'swimming-pool-manager' ); ?></th>
                            <th style="padding: 10px 14px;"><?php esc_html_e( 'Sold By', 'swimming-pool-manager' ); ?></th>
                            <th style="padding: 10px 14px; text-align: right;"><?php esc_html_e( 'Issued Time', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $customer_tickets ) ) : ?>
                            <?php foreach ( $customer_tickets as $tkt ) : 
                                $is_valid    = ( 'Valid' === $tkt->status );
                                $status_badge_style = $is_valid 
                                    ? 'background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;' 
                                    : ( 'Used' === $tkt->status 
                                        ? 'background: #fffbeb; color: #d97706; border: 1px solid #fde68a;' 
                                        : ( 'Completed' === $tkt->status
                                            ? 'background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;'
                                            : 'background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5;' ) );
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; font-size: 13.5px;">
                                    <td class="ifs-pms-mono" style="padding: 12px 14px; font-weight: 800; color: #0284c7;">
                                        <?php echo esc_html( $tkt->ticket_code ); ?>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <strong style="color: #0f172a;"><?php echo esc_html( ! empty( $tkt->package_details ) ? $tkt->package_details : __( 'Standard Ticket', 'swimming-pool-manager' ) ); ?></strong>
                                        <?php if ( ! empty( $tkt->room_no ) ) : ?>
                                            <span style="display: inline-block; background: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; margin-left: 6px;">
                                                Room <?php echo esc_html( $tkt->room_no ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ifs-pms-mono" style="padding: 12px 14px; font-weight: 800; color: #0f172a;">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $tkt->amount, 2 ) ); ?>
                                    </td>
                                    <td style="padding: 12px 14px; color: #64748b; font-weight: 600;">
                                        <?php echo esc_html( $tkt->payment_method ); ?>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <span style="display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; <?php echo esc_attr( $status_badge_style ); ?>">
                                            <?php echo esc_html( $tkt->status ); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px; color: #64748b;">
                                        <?php echo esc_html( $tkt->sold_by ); ?>
                                    </td>
                                    <td class="ifs-pms-mono" style="padding: 12px 14px; text-align: right; color: #64748b; font-size: 12px;">
                                        <?php echo esc_html( $tkt->sold_at ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 32px; color: #94a3b8;">
                                    <?php esc_html_e( 'No tickets found for this patron.', 'swimming-pool-manager' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $edit_customer ) : ?>
    <!-- ========================================================================== -->
    <!-- EDIT MODE: INLINE EDIT FORM -->
    <!-- ========================================================================== -->
    <div class="ifs-pms-pos-card" style="background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 24px;">
        <div class="ifs-pms-pos-head" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 18px; margin-bottom: 20px;">
            <h3 class="ifs-pms-pos-title" style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-admin-settings" style="color: #0284c7;"></span>
                <?php esc_html_e( 'Edit Patron Profile Details', 'swimming-pool-manager' ); ?>
            </h3>
            <a href="<?php echo esc_url( $base_url ); ?>" class="oz-btn" style="height: 38px; padding: 0 16px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #475569; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Cancel', 'swimming-pool-manager' ); ?>
            </a>
        </div>

        <div style="max-width: 600px; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 24px;">
            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                <input type="hidden" name="ifs_pms_action" value="edit_customer">
                <input type="hidden" name="customer_id" value="<?php echo esc_attr( $edit_customer->id ); ?>">

                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-weight: 700; margin-bottom: 6px; color: #0f172a; font-size: 13.5px;"><?php esc_html_e( 'Patron Full Name', 'swimming-pool-manager' ); ?> *</label>
                    <input type="text" name="name" value="<?php echo esc_attr( $edit_customer->name ); ?>" required style="width: 100%; height: 44px; border-radius: 8px; border: 1.5px solid #cbd5e1; padding: 0 12px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display:block; font-weight: 700; margin-bottom: 6px; color: #0f172a; font-size: 13.5px;"><?php esc_html_e( 'Mobile Contact / Phone', 'swimming-pool-manager' ); ?> *</label>
                    <input type="tel" name="phone" value="<?php echo esc_attr( $edit_customer->phone ); ?>" required style="width: 100%; height: 44px; border-radius: 8px; border: 1.5px solid #cbd5e1; padding: 0 12px; font-size: 14px;">
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="oz-btn oz-btn-primary" style="height: 44px; padding: 0 20px; background: #0284c7; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Update Patron Profile', 'swimming-pool-manager' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $base_url ); ?>" class="oz-btn" style="height: 44px; padding: 0 20px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; text-decoration: none;">
                        <?php esc_html_e( 'Cancel', 'swimming-pool-manager' ); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php else : ?>
    <!-- ========================================================================== -->
    <!-- LIST MODE: MASTER CUSTOMER DIRECTORY TABLE -->
    <!-- ========================================================================== -->
    <div class="ifs-pms-pos-card" style="background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div class="ifs-pms-pos-head" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 18px; margin-bottom: 20px;">
            <h3 class="ifs-pms-pos-title" style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-groups" style="color: #0284c7;"></span>
                <?php esc_html_e( 'Patron Directory & Ticket Intelligence', 'swimming-pool-manager' ); ?>
            </h3>
            <span class="ifs-pms-badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 700; padding: 5px 12px; border-radius: 8px; font-size: 13px;">
                <?php echo esc_html( $total_customers ); ?> <?php esc_html_e( 'Registered Patrons', 'swimming-pool-manager' ); ?>
            </span>
        </div>

        <div style="margin-bottom: 16px;">
            <input type="text" id="ifsPmsCustomerSearchInput" placeholder="<?php esc_attr_e( 'Search patrons by name or phone...', 'swimming-pool-manager' ); ?>" oninput="ifsPmsFilterCustomerTable()" autocomplete="off" style="width: 100%; height: 42px; border-radius: 8px; border: 1.5px solid #cbd5e1; padding: 0 14px; font-size: 14px;">
        </div>

        <div class="oz-table-wrap">
            <table class="oz-table" id="ifsPmsCustomersTable" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left; font-size: 12px; color: #64748b;">
                        <th style="padding: 10px 14px;"><?php esc_html_e( 'ID', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 14px;"><?php esc_html_e( 'Patron Name', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 14px;"><?php esc_html_e( 'Phone Contact', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 14px;"><?php esc_html_e( 'Total Visits', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 14px;"><?php esc_html_e( 'Lifetime Spend', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 14px;"><?php esc_html_e( 'Last Activity', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 14px; text-align: right;"><?php esc_html_e( 'Actions', 'swimming-pool-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $customers ) ) : ?>
                        <?php foreach ( $customers as $cust ) : ?>
                            <tr class="ifs-pms-customer-row" style="border-bottom: 1px solid #f1f5f9; font-size: 13.5px;">
                                <td class="ifs-pms-mono" style="padding: 12px 14px; color: #64748b; font-weight: 700;">
                                    #<?php echo esc_html( $cust->id ); ?>
                                </td>
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <?php echo esc_html( $cust->name ); ?>
                                </td>
                                <td class="ifs-pms-mono" style="padding: 12px 14px; color: #475569;">
                                    <?php echo esc_html( $cust->phone ); ?>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <span style="display: inline-block; background: #eff6ff; color: #0284c7; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 12px;">
                                        <?php echo esc_html( $cust->total_visits ); ?> <?php esc_html_e( 'Passes', 'swimming-pool-manager' ); ?>
                                    </span>
                                </td>
                                <td class="ifs-pms-mono" style="padding: 12px 14px; color: #10b981; font-weight: 800;">
                                    <?php echo esc_html( $currency . ' ' . number_format( (float) $cust->lifetime_spend, 2 ) ); ?>
                                </td>
                                <td class="ifs-pms-mono" style="padding: 12px 14px; font-size: 12px; color: #64748b;">
                                    <?php echo esc_html( $cust->last_visit ? $cust->last_visit : __( 'Never', 'swimming-pool-manager' ) ); ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=customers&mode=view&customer_id=' . $cust->id ) ); ?>" class="oz-btn oz-btn-sm" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #0284c7; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 12px;">
                                        <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'swimming-pool-manager' ); ?>
                                    </a>

                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=customers&mode=edit&customer_id=' . $cust->id ) ); ?>" class="oz-btn oz-btn-sm" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 12px; margin-left: 4px;">
                                        <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'swimming-pool-manager' ); ?>
                                    </a>

                                    <?php if ( $is_admin ) : ?>
                                        <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline-block; margin:0 0 0 4px;" onsubmit="return confirm(<?php echo wp_json_encode( __( 'Delete this customer profile permanently?', 'swimming-pool-manager' ) ); ?>);">
                                            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                                            <input type="hidden" name="ifs_pms_action" value="delete_customer">
                                            <input type="hidden" name="customer_id" value="<?php echo esc_attr( $cust->id ); ?>">
                                            <button type="submit" class="oz-btn oz-btn-sm" style="padding: 4px 8px; background: #fff1f2; color: #ef4444; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer;" title="<?php esc_attr_e( 'Delete Profile', 'swimming-pool-manager' ); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <span class="dashicons dashicons-groups" style="font-size: 36px; width: 36px; height: 36px;"></span>
                                <div style="margin-top: 8px; font-weight: 600;"><?php esc_html_e( 'No customer profiles found in database.', 'swimming-pool-manager' ); ?></div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ( $total_pages > 1 ) : ?>
            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">
                    <?php 
                    $start = $offset + 1;
                    $end   = min( $offset + $per_page, $total_customers );
                    printf( esc_html__( 'Showing %1$d–%2$d of %3$d patrons', 'swimming-pool-manager' ), $start, $end, $total_customers ); 
                    ?>
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=customers&paged=' . $i ) ); ?>" 
                           style="height: 32px; min-width: 32px; padding: 0 8px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border: 1px solid #cbd5e1; <?php echo ( $i === $paged ) ? 'background: #0284c7; color: #fff; border-color: #0284c7;' : 'background: #fff; color: #475569;'; ?>">
                            <?php echo esc_html( $i ); ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<script>
function ifsPmsFilterCustomerTable() {
    var input  = document.getElementById('ifsPmsCustomerSearchInput');
    var filter = input ? input.value.toLowerCase().trim() : '';
    var rows   = document.querySelectorAll('#ifsPmsCustomersTable tbody .ifs-pms-customer-row');

    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = (!filter || text.indexOf(filter) !== -1) ? '' : 'none';
    });
}
</script>