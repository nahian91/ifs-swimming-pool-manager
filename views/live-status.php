<?php
/**
 * View: Live Pool Floor Status & Active Swimmer Telemetry (Dashicons UI with Pagination & Live Search)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$t_tick = $wpdb->prefix . 'ifs_pms_tickets';
$t_cust = $wpdb->prefix . 'ifs_pms_customers';

$wp_now_dt    = current_datetime(); 
$today_ymd    = $wp_now_dt->format( 'Y-m-d' );
$server_epoch = $wp_now_dt->getTimestamp();
$currency     = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );

$max_capacity = (int) get_option( 'ifs_pms_max_capacity', 80 );
if ( $max_capacity <= 0 ) {
    $max_capacity = 80;
}

// Pagination & Search parameters
$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page    = 10;
$offset      = ( $paged - 1 ) * $per_page;
$search_query = isset( $_GET['s_patron'] ) ? sanitize_text_field( wp_unslash( $_GET['s_patron'] ) ) : '';

// Handle manual checkout/exit action
if ( isset( $_POST['ifs_pms_action'] ) && 'checkout_swimmer' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );
    $checkout_id = absint( $_POST['ticket_id'] ?? 0 );
    if ( $checkout_id > 0 ) {
        $wpdb->update(
            $t_tick,
            array( 'status' => 'Completed' ),
            array( 'id' => $checkout_id ),
            array( '%s' ),
            array( '%d' )
        );
        echo '<div class="notice notice-success is-dismissible oz-notice-spacing"><p>' . esc_html__( 'Patron checked out from pool deck successfully.', 'swimming-pool-manager' ) . '</p></div>';
    }
}

// Build SQL where clauses for filtering and pagination
$where_sql = "t.status = 'Used' AND (DATE(COALESCE(NULLIF(t.scanned_at, '0000-00-00 00:00:00'), t.sold_at)) = %s)";
$query_params = array( $today_ymd );

if ( ! empty( $search_query ) ) {
    $where_sql .= " AND (t.ticket_code LIKE %s OR c.name LIKE %s OR c.phone LIKE %s)";
    $like = '%' . $wpdb->esc_like( $search_query ) . '%';
    array_push( $query_params, $like, $like, $like );
}

// Total count for pagination calculation
$total_query = "SELECT COUNT(t.id) FROM {$t_tick} t LEFT JOIN {$t_cust} c ON t.customer_id = c.id WHERE " . $where_sql;
$total_items = (int) $wpdb->get_var( $wpdb->prepare( $total_query, $query_params ) );
$total_pages = max( 1, ceil( $total_items / $per_page ) );

// Fetch active admissions for today with limit and offset
$sql_active = "SELECT t.id, t.ticket_code, t.customer_id, t.duration_hours, t.amount, t.sold_at, t.scanned_at, c.name as customer_name, c.phone as customer_phone
               FROM {$t_tick} t
               LEFT JOIN {$t_cust} c ON t.customer_id = c.id
               WHERE " . $where_sql . "
               ORDER BY COALESCE(NULLIF(t.scanned_at, '0000-00-00 00:00:00'), t.sold_at) DESC
               LIMIT %d OFFSET %d";

array_push( $query_params, $per_page, $offset );
$active_swimmers = $wpdb->get_results( $wpdb->prepare( $sql_active, $query_params ) );

// Unfiltered total count for census gauge calculations
$current_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(t.id) FROM {$t_tick} t WHERE t.status = 'Used' AND (DATE(COALESCE(NULLIF(t.scanned_at, '0000-00-00 00:00:00'), t.sold_at)) = %s)", $today_ymd ) );
$capacity_pct  = min( 100, (int) round( ( $current_count / $max_capacity ) * 100 ) );

if ( $capacity_pct >= 85 ) {
    $status_label = __( 'Critical / Peak', 'swimming-pool-manager' );
    $status_badge = 'ifs-pms-badge-danger';
    $bar_gradient = 'linear-gradient(90deg, #f59e0b 0%, #ef4444 100%)';
} elseif ( $capacity_pct >= 55 ) {
    $status_label = __( 'Moderate Flow', 'swimming-pool-manager' );
    $status_badge = 'ifs-pms-badge-warning';
    $bar_gradient = 'linear-gradient(90deg, #0284c7 0%, #f59e0b 100%)';
} else {
    $status_label = __( 'Optimal Space', 'swimming-pool-manager' );
    $status_badge = 'ifs-pms-badge-success';
    $bar_gradient = 'linear-gradient(90deg, #0284c7 0%, #10b981 100%)';
}
?>

<div class="oz-live-status-stack">
    <!-- Top Gauge & Live Occupancy -->
    <div class="oz-status-hero">
        <div class="oz-status-hero-left">
            <div class="oz-status-orb">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div>
                <div class="oz-status-title-tag"><?php esc_html_e( 'Live In-Pool Census', 'swimming-pool-manager' ); ?></div>
                <div class="oz-status-count">
                    <span class="ifs-pms-mono" id="ozLiveCensusCount"><?php echo esc_html( (string) $current_count ); ?></span>
                    <span class="oz-status-sub">/ <span class="ifs-pms-mono" id="ozLiveCensusCapacity"><?php echo esc_html( (string) $max_capacity ); ?></span> <?php esc_html_e( 'Capacity', 'swimming-pool-manager' ); ?></span>
                </div>
            </div>
        </div>

        <div>
            <div class="oz-progress-label-row">
                <span class="oz-progress-title"><?php esc_html_e( 'Surface Occupancy', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-mono oz-progress-pct" id="ozLiveCapacityPctText"><?php echo esc_html( (string) $capacity_pct ); ?>%</span>
            </div>
            <div class="oz-progress-track">
                <div class="oz-progress-fill" id="ozLiveCapacityBar" style="width: <?php echo esc_attr( (string) $capacity_pct ); ?>%; background: <?php echo esc_attr( $bar_gradient ); ?>;"></div>
            </div>
        </div>

        <div>
            <span class="ifs-pms-badge <?php echo esc_attr( $status_badge ); ?> oz-status-pill-padded" id="ozLiveStatusPill">
                <span class="ifs-pms-pulse-dot"></span> <span id="ozLiveStatusText"><?php echo esc_html( $status_label ); ?></span>
            </span>
        </div>
    </div>

    <!-- Active Swimmers Registry & Live Search Toolbar -->
    <div class="oz-telemetry-panel">
        <div class="oz-telemetry-head" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <h3 class="oz-telemetry-title" style="margin: 0;">
                <span class="dashicons dashicons-building"></span>
                <?php esc_html_e( 'Active Pool Floor Patrons & Duration Watchdog', 'swimming-pool-manager' ); ?>
            </h3>

            <div style="display: flex; align-items: center; gap: 12px;">
                <!-- Live Search Form -->
                <form method="get" action="" style="display: flex; align-items: center; gap: 6px;">
                    <input type="hidden" name="page" value="ifs-pms-live-status">
                    <div style="position: relative;">
                        <input type="text" name="s_patron" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search code or name...', 'swimming-pool-manager' ); ?>" style="padding: 6px 12px 6px 30px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 13px; width: 220px;">
                        <span class="dashicons dashicons-search" style="position: absolute; left: 8px; top: 9px; color: #94a3b8; font-size: 16px;"></span>
                    </div>
                    <?php if ( ! empty( $search_query ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-live-status' ) ); ?>" class="button" style="border-radius: 8px;"><?php esc_html_e( 'Reset', 'swimming-pool-manager' ); ?></a>
                    <?php endif; ?>
                </form>

                <div class="oz-live-clock-pill">
                    <span class="dashicons dashicons-clock"></span>
                    <span class="ifs-pms-mono" id="ozWatcherClock">--:--:-- --</span>
                </div>
            </div>
        </div>

        <div class="oz-table-wrap">
            <table class="oz-table" id="ozActiveSwimmersTable">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Pass Code', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Patron Name', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Entry Time', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Duration', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Target Exit', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Remaining / Extra & Surcharge', 'swimming-pool-manager' ); ?></th>
                        <th class="oz-th-right"><?php esc_html_e( 'Action', 'swimming-pool-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ozActiveSwimmersTbody">
                    <?php if ( ! empty( $active_swimmers ) ) : ?>
                        <?php foreach ( $active_swimmers as $swimmer ) :
                            $hours       = max( 1, (int) $swimmer->duration_hours );
                            $amount      = max( 0.00, (float) $swimmer->amount );
                            $hourly_rate = $amount > 0 ? ( $amount / $hours ) : 100.00;
                            
                            $entry_raw = ( ! empty( $swimmer->scanned_at ) && '0000-00-00 00:00:00' !== $swimmer->scanned_at ) 
                                ? $swimmer->scanned_at 
                                : $swimmer->sold_at;

                            $entry_dt = date_create_immutable_from_format( 'Y-m-d H:i:s', $entry_raw, wp_timezone() );
                            if ( ! $entry_dt ) {
                                $entry_dt = $wp_now_dt;
                            }
                            
                            $entry_stamp = $entry_dt->getTimestamp();
                            $exit_stamp  = $entry_stamp + ( $hours * 3600 );
                            
                            $entry_formatted = $entry_dt->format( 'h:i:s A' );
                            $exit_formatted  = wp_date( 'h:i:s A', $exit_stamp, wp_timezone() );
                        ?>
                            <tr class="oz-swimmer-row" 
                                id="ozRow-<?php echo esc_attr( (string) $swimmer->id ); ?>"
                                data-id="<?php echo esc_attr( (string) $swimmer->id ); ?>"
                                data-hourly-rate="<?php echo esc_attr( (string) $hourly_rate ); ?>"
                                data-exit-timestamp="<?php echo esc_attr( (string) $exit_stamp ); ?>"
                                data-entry-timestamp="<?php echo esc_attr( (string) $entry_stamp ); ?>">
                                
                                <td class="ifs-pms-mono oz-text-blue oz-text-weight-heavy">
                                    <?php echo esc_html( $swimmer->ticket_code ); ?>
                                </td>

                                <td class="oz-text-dark oz-text-weight-bold">
                                    <?php echo esc_html( ! empty( $swimmer->customer_name ) ? $swimmer->customer_name : __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?>
                                </td>

                                <td class="ifs-pms-mono oz-text-dark">
                                    <?php echo esc_html( $entry_formatted ); ?>
                                </td>

                                <td class="ifs-pms-mono">
                                    <span class="ifs-pms-badge oz-badge-soft-blue">
                                        <?php echo esc_html( (string) $hours ); ?> <?php echo esc_html( _n( 'Hr', 'Hrs', $hours, 'swimming-pool-manager' ) ); ?>
                                    </span>
                                </td>

                                <td class="ifs-pms-mono oz-text-sub oz-text-weight-heavy">
                                    <?php echo esc_html( $exit_formatted ); ?>
                                </td>

                                <td>
                                    <span class="oz-row-clock-pill oz-clock-safe oz-timer-display" id="ozTimer-<?php echo esc_attr( (string) $swimmer->id ); ?>">
                                        <span class="dashicons dashicons-clock"></span>
                                        <span class="oz-clock-digits">00:00:00 left</span>
                                    </span>
                                </td>

                                <td class="oz-td-right">
                                    <button type="button" class="oz-checkout-btn" onclick="ozCheckoutSwimmer(<?php echo esc_attr( (string) $swimmer->id ); ?>, this)">
                                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Check Out', 'swimming-pool-manager' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr id="ozEmptyNoticeRow">
                            <td colspan="7" class="oz-empty-state">
                                <div class="oz-empty-state-icon">
                                    <span class="dashicons dashicons-groups"></span>
                                </div>
                                <?php esc_html_e( 'No swimmers currently registered inside the pool.', 'swimming-pool-manager' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer Controls -->
        <?php if ( $total_pages > 1 ) : ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <div style="font-size: 12.5px; color: #64748b; font-weight: 600;">
                    <?php
                    printf(
                        /* translators: 1: current page, 2: total pages */
                        esc_html__( 'Page %1$d of %2$d', 'swimming-pool-manager' ),
                        $paged,
                        $total_pages
                    );
                    ?>
                </div>

                <div style="display: flex; gap: 6px;">
                    <?php
                    $base_url = remove_query_arg( 'paged' );
                    if ( $paged > 1 ) :
                        $prev_url = add_query_arg( 'paged', $paged - 1, $base_url );
                        if ( ! empty( $search_query ) ) {
                            $prev_url = add_query_arg( 's_patron', urlencode( $search_query ), $prev_url );
                        }
                    ?>
                        <a href="<?php echo esc_url( $prev_url ); ?>" class="button" style="border-radius: 6px; font-weight: 700;"><?php esc_html_e( '&laquo; Previous', 'swimming-pool-manager' ); ?></a>
                    <?php endif; ?>

                    <?php
                    if ( $paged < $total_pages ) :
                        $next_url = add_query_arg( 'paged', $paged + 1, $base_url );
                        if ( ! empty( $search_query ) ) {
                            $next_url = add_query_arg( 's_patron', urlencode( $search_query ), $next_url );
                        }
                    ?>
                        <a href="<?php echo esc_url( $next_url ); ?>" class="button button-primary" style="border-radius: 6px; font-weight: 700;"><?php esc_html_e( 'Next &raquo;', 'swimming-pool-manager' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>