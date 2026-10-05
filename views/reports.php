<?php
/**
 * View: Financial Performance Reports & Date-Wise Audit Statement (Dashicons UI)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue separated Reports stylesheet
if ( defined( 'IFS_PMS_URL' ) && defined( 'IFS_PMS_VERSION' ) ) {
    wp_enqueue_style(
        'ifs-pms-reports-css',
        IFS_PMS_URL . 'assets/css/reports.css',
        array( 'dashicons' ),
        IFS_PMS_VERSION
    );
}

global $wpdb;

$table_tickets     = $wpdb->prefix . 'ifs_pms_tickets';
$table_memberships = $wpdb->prefix . 'ifs_pms_memberships';
$table_expenses    = $wpdb->prefix . 'ifs_pms_expenses';
$table_customers   = $wpdb->prefix . 'ifs_pms_customers';
$currency          = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$business_name     = esc_html( (string) get_option( 'ifs_pms_business_name', 'Ozone Skypool' ) );

// 1. Date Range Filter Parameters
$today_date_str    = current_time( 'Y-m-d' );
$default_start     = gmdate( 'Y-m-01', strtotime( $today_date_str ) );
$default_end       = $today_date_str;

$start_date_input  = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : $default_start;
$end_date_input    = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $default_end;

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date_input ) ) {
    $start_date_input = $default_start;
}
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date_input ) ) {
    $end_date_input = $default_end;
}

$active_sub_tab    = sanitize_key( $_GET['sub_tab'] ?? 'income' );
$income_filter     = sanitize_key( $_GET['income_filter'] ?? 'all' );
$expense_cat_filter = sanitize_text_field( wp_unslash( $_GET['expense_cat'] ?? 'all' ) );

// 2. High-Performance SQL Timestamp Boundaries
$start_datetime = $start_date_input . ' 00:00:00';
$end_datetime   = $end_date_input . ' 23:59:59';

// 3. High-Level Summary Statistics
$ticket_stats = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT 
            COALESCE(SUM(amount), 0) AS total_money,
            COUNT(id) AS total_count,
            SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END) AS cash_money,
            SUM(CASE WHEN payment_method = 'Card POS' THEN amount ELSE 0 END) AS card_money,
            SUM(CASE WHEN payment_method = 'bKash / Nagad' THEN amount ELSE 0 END) AS mfs_money,
            SUM(CASE WHEN guest_type = 'room_guest' OR payment_method IN ('Room Guest', 'Complementary') THEN 1 ELSE 0 END) AS free_count,
            SUM(CASE WHEN status = 'Used' THEN 1 ELSE 0 END) AS used_count
         FROM {$table_tickets} 
         WHERE sold_at >= %s AND sold_at <= %s AND status != 'Cancelled'",
        $start_datetime,
        $end_datetime
    )
);

$ticket_income  = (float) ( $ticket_stats->total_money ?? 0.00 );
$total_tickets  = (int) ( $ticket_stats->total_count ?? 0 );
$cash_income    = (float) ( $ticket_stats->cash_money ?? 0.00 );
$card_income    = (float) ( $ticket_stats->card_money ?? 0.00 );
$mfs_income     = (float) ( $ticket_stats->mfs_money ?? 0.00 );

$member_stats = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT 
            COALESCE(SUM(amount), 0) AS total_money,
            COUNT(id) AS total_count
         FROM {$table_memberships} 
         WHERE created_at >= %s AND created_at <= %s",
        $start_datetime,
        $end_datetime
    )
);

$member_income  = (float) ( $member_stats->total_money ?? 0.00 );
$total_members  = (int) ( $member_stats->total_count ?? 0 );
$total_income   = $ticket_income + $member_income;

$total_expense  = (float) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) 
         FROM {$table_expenses} 
         WHERE expense_date >= %s AND expense_date <= %s",
        $start_date_input,
        $end_date_input
    )
);

$net_profit  = $total_income - $total_expense;
$profit_rate = $total_income > 0 ? round( ( $net_profit / $total_income ) * 100, 1 ) : 0.0;

// 4. Detailed Ledger Query Lists based on Filters
$detailed_tickets     = array();
$detailed_memberships = array();

if ( 'all' === $income_filter || 'tickets' === $income_filter ) {
    $detailed_tickets = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.*, c.name AS customer_name, c.phone AS customer_phone 
             FROM {$table_tickets} t 
             LEFT JOIN {$table_customers} c ON t.customer_id = c.id 
             WHERE t.sold_at >= %s AND t.sold_at <= %s AND t.status != 'Cancelled' 
             ORDER BY t.id DESC",
            $start_datetime,
            $end_datetime
        )
    );
}

if ( 'all' === $income_filter || 'memberships' === $income_filter ) {
    $detailed_memberships = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_memberships} 
             WHERE created_at >= %s AND created_at <= %s 
             ORDER BY id DESC",
            $start_datetime,
            $end_datetime
        )
    );
}

$expense_categories = $wpdb->get_results( "SELECT DISTINCT category FROM {$table_expenses} ORDER BY category ASC", ARRAY_A );

$expense_query_sql  = "SELECT * FROM {$table_expenses} WHERE expense_date >= %s AND expense_date <= %s";
$expense_query_args = array( $start_date_input, $end_date_input );

if ( ! empty( $expense_cat_filter ) && 'all' !== $expense_cat_filter ) {
    $expense_query_sql     .= ' AND category = %s';
    $expense_query_args[] = $expense_cat_filter;
}
$expense_query_sql .= ' ORDER BY expense_date DESC, id DESC';

$detailed_expenses = $wpdb->get_results( $wpdb->prepare( $expense_query_sql, $expense_query_args ) );

$date_range_label = sprintf( '%s to %s', date_i18n( 'M j, Y', strtotime( $start_date_input ) ), date_i18n( 'M j, Y', strtotime( $end_date_input ) ) );
?>

<div class="ifs-pms-report-wrap">
    <!-- Top Date-Wise Filter Bar -->
    <div class="ifs-pms-top-bar">
        <div>
            <div class="ifs-pms-tag-badge">
                <span class="ifs-pms-pulse-dot"></span> <?php esc_html_e( 'Date Range Filtered Audit', 'swimming-pool-manager' ); ?>
            </div>
            <h2 class="ifs-pms-bar-title">
                <?php echo esc_html( $business_name ); ?> &bull; <?php echo esc_html( $date_range_label ); ?>
            </h2>
        </div>

        <div class="ifs-pms-bar-controls">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ifs-pms-filter-form" id="ifsPmsReportGlobalForm">
                <input type="hidden" name="page" value="ifs-pms">
                <input type="hidden" name="view" value="reports">
                <input type="hidden" name="sub_tab" id="ifsPmsSubTabInput" value="<?php echo esc_attr( $active_sub_tab ); ?>">
                <input type="hidden" name="income_filter" id="ifsPmsIncomeFilterInput" value="<?php echo esc_attr( $income_filter ); ?>">
                <input type="hidden" name="expense_cat" id="ifsPmsExpenseCatInput" value="<?php echo esc_attr( $expense_cat_filter ); ?>">

                <div class="ifs-pms-date-picker-pill">
                    <span class="ifs-pms-date-label"><?php esc_html_e( 'From', 'swimming-pool-manager' ); ?></span>
                    <input type="date" name="start_date" value="<?php echo esc_attr( $start_date_input ); ?>">
                    <span class="ifs-pms-date-label"><?php esc_html_e( 'To', 'swimming-pool-manager' ); ?></span>
                    <input type="date" name="end_date" value="<?php echo esc_attr( $end_date_input ); ?>">
                </div>

                <button type="submit" class="ifs-pms-btn ifs-pms-btn-blue">
                    <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Apply Filter', 'swimming-pool-manager' ); ?>
                </button>
            </form>

            <button type="button" class="ifs-pms-btn ifs-pms-btn-white" onclick="window.print()">
                <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print', 'swimming-pool-manager' ); ?>
            </button>
        </div>
    </div>

    <!-- 4 Summary Cards -->
    <div class="ifs-pms-card-grid">
        <div class="ifs-pms-stat-card">
            <div class="ifs-pms-stat-head">
                <span class="ifs-pms-stat-name"><?php esc_html_e( 'Total Income', 'swimming-pool-manager' ); ?></span>
                <div class="ifs-pms-stat-icon ifs-pms-stat-icon-green">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
            </div>
            <div class="ifs-pms-stat-number ifs-pms-mono ifs-pms-stat-number-green">
                <?php echo esc_html( $currency . ' ' . number_format( $total_income, 2 ) ); ?>
            </div>
            <div class="ifs-pms-stat-bottom">
                <span><?php printf( esc_html__( 'Tickets: %s', 'swimming-pool-manager' ), esc_html( number_format( $ticket_income, 2 ) ) ); ?></span>
                <span><?php printf( esc_html__( 'Members: %s', 'swimming-pool-manager' ), esc_html( number_format( $member_income, 2 ) ) ); ?></span>
            </div>
        </div>

        <div class="ifs-pms-stat-card">
            <div class="ifs-pms-stat-head">
                <span class="ifs-pms-stat-name"><?php esc_html_e( 'Total Expenses', 'swimming-pool-manager' ); ?></span>
                <div class="ifs-pms-stat-icon ifs-pms-stat-icon-red">
                    <span class="dashicons dashicons-media-text"></span>
                </div>
            </div>
            <div class="ifs-pms-stat-number ifs-pms-mono ifs-pms-stat-number-red">
                <?php echo esc_html( $currency . ' ' . number_format( $total_expense, 2 ) ); ?>
            </div>
            <div class="ifs-pms-stat-bottom">
                <span><?php printf( esc_html__( '%d Vouchers', 'swimming-pool-manager' ), count( $detailed_expenses ) ); ?></span>
                <span><?php esc_html_e( 'Outflows', 'swimming-pool-manager' ); ?></span>
            </div>
        </div>

        <div class="ifs-pms-stat-card">
            <div class="ifs-pms-stat-head">
                <span class="ifs-pms-stat-name"><?php esc_html_e( 'Net Profit', 'swimming-pool-manager' ); ?></span>
                <div class="ifs-pms-stat-icon ifs-pms-stat-icon-blue">
                    <span class="dashicons dashicons-chart-area"></span>
                </div>
            </div>
            <div class="ifs-pms-stat-number ifs-pms-mono <?php echo ( $net_profit >= 0 ) ? 'ifs-pms-stat-number-blue' : 'ifs-pms-stat-number-red'; ?>">
                <?php echo esc_html( $currency . ' ' . number_format( $net_profit, 2 ) ); ?>
            </div>
            <div class="ifs-pms-stat-bottom">
                <strong class="<?php echo $net_profit >= 0 ? 'ifs-pms-text-green' : 'ifs-pms-text-red'; ?>">
                    <?php echo esc_html( $net_profit >= 0 ? __( 'Profitable', 'swimming-pool-manager' ) : __( 'Loss Recorded', 'swimming-pool-manager' ) ); ?>
                </strong>
            </div>
        </div>

        <div class="ifs-pms-stat-card">
            <div class="ifs-pms-stat-head">
                <span class="ifs-pms-stat-name"><?php esc_html_e( 'Profit Margin', 'swimming-pool-manager' ); ?></span>
                <div class="ifs-pms-stat-icon ifs-pms-stat-icon-purple">
                    <span class="dashicons dashicons-performance"></span>
                </div>
            </div>
            <div class="ifs-pms-stat-number ifs-pms-mono ifs-pms-stat-number-purple">
                <?php echo esc_html( (string) $profit_rate ); ?>%
            </div>
            <div class="ifs-pms-stat-bottom">
                <span><?php esc_html_e( 'Income vs Cost', 'swimming-pool-manager' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Sub Navigation Tabs for Income vs Expense Statements -->
    <div class="ifs-pms-sub-tabs" role="tablist">
        <button type="button" class="ifs-pms-sub-tab-btn <?php echo ( 'income' === $active_sub_tab ) ? 'active' : ''; ?>" id="ifsSubTabBtnIncome" onclick="ifsPmsSwitchSubTab('income')">
            <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Income Details & Breakdown', 'swimming-pool-manager' ); ?>
        </button>
        <button type="button" class="ifs-pms-sub-tab-btn <?php echo ( 'expense' === $active_sub_tab ) ? 'active' : ''; ?>" id="ifsSubTabBtnExpense" onclick="ifsPmsSwitchSubTab('expense')">
            <span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'Expense Details & Breakdown', 'swimming-pool-manager' ); ?>
        </button>
    </div>

    <!-- TAB CONTENT 1: INCOME STATEMENT -->
    <div id="ifsPmsIncomeTabContent" class="ifs-pms-tab-content <?php echo ( 'income' === $active_sub_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-table-box">
            <div class="ifs-pms-table-head">
                <h3 class="ifs-pms-table-title">
                    <span class="dashicons dashicons-tickets-alt"></span>
                    <?php esc_html_e( 'Income Ledger Transactions', 'swimming-pool-manager' ); ?>
                </h3>

                <div class="ifs-pms-table-filter-group">
                    <span class="ifs-pms-table-filter-label"><?php esc_html_e( 'Filter Type:', 'swimming-pool-manager' ); ?></span>
                    <select id="ifsPmsIncomeFilterSelect" onchange="ifsPmsApplyIncomeFilter(this.value)">
                        <option value="all" <?php selected( $income_filter, 'all' ); ?>><?php esc_html_e( 'All Incomes (Tickets + Memberships)', 'swimming-pool-manager' ); ?></option>
                        <option value="tickets" <?php selected( $income_filter, 'tickets' ); ?>><?php esc_html_e( 'Ticket Sales Only', 'swimming-pool-manager' ); ?></option>
                        <option value="memberships" <?php selected( $income_filter, 'memberships' ); ?>><?php esc_html_e( 'Member Subscriptions Only', 'swimming-pool-manager' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="ifs-pms-table-responsive">
                <table class="ifs-pms-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Reference / Code', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Patron / Subscriber', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Type / Details', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Payment Method', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Cashier / Staff', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Date & Time', 'swimming-pool-manager' ); ?></th>
                            <th class="ifs-pms-text-right"><?php esc_html_e( 'Amount', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $has_income_rows = false;

                        if ( ! empty( $detailed_tickets ) ) {
                            $has_income_rows = true;
                            foreach ( $detailed_tickets as $t ) {
                                ?>
                                <tr>
                                    <td class="ifs-pms-mono ifs-pms-code-cell ifs-pms-code-ticket"><?php echo esc_html( $t->ticket_code ); ?></td>
                                    <td class="ifs-pms-patron-cell">
                                        <?php echo esc_html( ! empty( $t->customer_name ) ? $t->customer_name : __( 'Walk-in Guest', 'swimming-pool-manager' ) ); ?>
                                        <div class="ifs-pms-patron-sub ifs-pms-mono"><?php echo esc_html( $t->customer_phone ); ?></div>
                                    </td>
                                    <td>
                                        <span class="ifs-pms-badge ifs-pms-badge-ticket">
                                            <?php echo esc_html( ! empty( $t->package_details ) ? $t->package_details : __( 'Standard Ticket', 'swimming-pool-manager' ) ); ?>
                                        </span>
                                        <?php if ( ! empty( $t->room_no ) ) : ?>
                                            <div class="ifs-pms-room-tag">
                                                <span class="dashicons dashicons-building"></span> <?php echo esc_html( $t->room_no ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ifs-pms-font-semibold"><?php echo esc_html( $t->payment_method ); ?></td>
                                    <td class="ifs-pms-text-muted"><?php echo esc_html( $t->sold_by ); ?></td>
                                    <td class="ifs-pms-mono ifs-pms-text-date"><?php echo esc_html( $t->sold_at ); ?></td>
                                    <td class="ifs-pms-mono ifs-pms-amount-green ifs-pms-text-right">
                                        +<?php echo esc_html( $currency . ' ' . number_format( (float) $t->amount, 2 ) ); ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }

                        if ( ! empty( $detailed_memberships ) ) {
                            $has_income_rows = true;
                            foreach ( $detailed_memberships as $m ) {
                                ?>
                                <tr>
                                    <td class="ifs-pms-mono ifs-pms-code-cell ifs-pms-code-member"><?php echo esc_html( $m->member_code ); ?></td>
                                    <td class="ifs-pms-patron-cell">
                                        <?php echo esc_html( $m->name ); ?>
                                        <div class="ifs-pms-patron-sub ifs-pms-mono"><?php echo esc_html( $m->phone ); ?></div>
                                    </td>
                                    <td>
                                        <span class="ifs-pms-badge ifs-pms-badge-member">
                                            <?php echo esc_html( $m->plan_type ); ?>
                                        </span>
                                    </td>
                                    <td class="ifs-pms-font-semibold"><?php esc_html_e( 'Subscription', 'swimming-pool-manager' ); ?></td>
                                    <td class="ifs-pms-text-muted"><?php esc_html_e( 'Front Desk', 'swimming-pool-manager' ); ?></td>
                                    <td class="ifs-pms-mono ifs-pms-text-date"><?php echo esc_html( $m->created_at ); ?></td>
                                    <td class="ifs-pms-mono ifs-pms-amount-green ifs-pms-text-right">
                                        +<?php echo esc_html( $currency . ' ' . number_format( (float) $m->amount, 2 ) ); ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }

                        if ( ! $has_income_rows ) {
                            ?>
                            <tr>
                                <td colspan="7" class="ifs-pms-empty-state">
                                    <div class="ifs-pms-empty-state-icon">
                                        <span class="dashicons dashicons-id-alt"></span>
                                    </div>
                                    <?php esc_html_e( 'No income records found for this date range.', 'swimming-pool-manager' ); ?>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="ifs-pms-table-footer">
                <span class="ifs-pms-footer-label"><?php esc_html_e( 'Total Filtered Income Inflow', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-pms-mono ifs-pms-footer-value-green">
                    <?php echo esc_html( $currency . ' ' . number_format( $total_income, 2 ) ); ?>
                </strong>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT 2: EXPENSE STATEMENT -->
    <div id="ifsPmsExpenseTabContent" class="ifs-pms-tab-content <?php echo ( 'expense' === $active_sub_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-table-box">
            <div class="ifs-pms-table-head">
                <h3 class="ifs-pms-table-title">
                    <span class="dashicons dashicons-media-text ifs-pms-icon-red"></span>
                    <?php esc_html_e( 'Expense Outflows Ledger', 'swimming-pool-manager' ); ?>
                </h3>

                <div class="ifs-pms-table-filter-group">
                    <span class="ifs-pms-table-filter-label"><?php esc_html_e( 'Category:', 'swimming-pool-manager' ); ?></span>
                    <select id="ifsPmsExpenseCatSelect" onchange="ifsPmsApplyExpenseFilter(this.value)">
                        <option value="all" <?php selected( $expense_cat_filter, 'all' ); ?>><?php esc_html_e( 'All Categories', 'swimming-pool-manager' ); ?></option>
                        <?php if ( ! empty( $expense_categories ) ) : ?>
                            <?php foreach ( $expense_categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat['category'] ); ?>" <?php selected( $expense_cat_filter, $cat['category'] ); ?>>
                                    <?php echo esc_html( $cat['category'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="ifs-pms-table-responsive">
                <table class="ifs-pms-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Expense Title', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Category', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Logged By', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Voucher Date', 'swimming-pool-manager' ); ?></th>
                            <th class="ifs-pms-text-right"><?php esc_html_e( 'Disbursement', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $detailed_expenses ) ) : ?>
                            <?php foreach ( $detailed_expenses as $e ) : ?>
                                <tr>
                                    <td class="ifs-pms-patron-cell"><?php echo esc_html( $e->title ); ?></td>
                                    <td>
                                        <span class="ifs-pms-badge ifs-pms-badge-expense">
                                            <?php echo esc_html( $e->category ); ?>
                                        </span>
                                    </td>
                                    <td class="ifs-pms-text-muted"><?php echo esc_html( $e->added_by ); ?></td>
                                    <td class="ifs-pms-mono ifs-pms-text-date"><?php echo esc_html( $e->expense_date ); ?></td>
                                    <td class="ifs-pms-mono ifs-pms-amount-red ifs-pms-text-right">
                                        -<?php echo esc_html( $currency . ' ' . number_format( (float) $e->amount, 2 ) ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" class="ifs-pms-empty-state">
                                    <div class="ifs-pms-empty-state-icon">
                                        <span class="dashicons dashicons-media-text"></span>
                                    </div>
                                    <?php esc_html_e( 'No expense disbursements recorded for this date range.', 'swimming-pool-manager' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="ifs-pms-table-footer">
                <span class="ifs-pms-footer-label"><?php esc_html_e( 'Total Filtered Expense Outflow', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-pms-mono ifs-pms-footer-value-red">
                    -<?php echo esc_html( $currency . ' ' . number_format( $total_expense, 2 ) ); ?>
                </strong>
            </div>
        </div>
    </div>
</div>