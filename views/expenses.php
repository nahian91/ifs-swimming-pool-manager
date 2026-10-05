<?php
/**
 * View: Operating Expenses & Outflow Registry (Dashicons - Enterprise Edition v5)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$t_expense = $wpdb->prefix . 'ifs_pms_expenses';
$currency  = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$base_url  = admin_url( 'admin.php?page=ifs-pms' );
$is_admin  = current_user_can( 'manage_options' ) || current_user_can( 'ozone_manage_settings' );

// 1. Timezone-aligned date boundaries & filters
$today_ymd        = current_time( 'Y-m-d' );
$month_start      = current_time( 'Y-m-01' );
$month_end        = current_time( 'Y-m-t' );

$start_date_input = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : $month_start;
$end_date_input   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $month_end;
$sector_filter    = sanitize_text_field( wp_unslash( $_GET['sector'] ?? 'ALL' ) );

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date_input ) ) {
    $start_date_input = $month_start;
}
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date_input ) ) {
    $end_date_input = $month_end;
}

// Active Tab Router
$active_tab = isset( $_GET['tab'] ) && 'list' === $_GET['tab'] ? 'list' : 'add';

// 2. Financial Metrics (Indexed Range Queries)
$exp_today = (float) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$t_expense} WHERE expense_date = %s",
        $today_ymd
    )
);

$exp_period = (float) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$t_expense} WHERE expense_date >= %s AND expense_date <= %s",
        $start_date_input,
        $end_date_input
    )
);

$top_cat = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT category 
         FROM {$t_expense} 
         WHERE expense_date >= %s AND expense_date <= %s 
         GROUP BY category 
         ORDER BY SUM(amount) DESC 
         LIMIT 1",
        $start_date_input,
        $end_date_input
    )
);
$top_cat = $top_cat ? $top_cat : __( 'None Recorded', 'swimming-pool-manager' );

// 3. Fetch Filtered Outflows
$query_sql  = "SELECT * FROM {$t_expense} WHERE expense_date >= %s AND expense_date <= %s";
$query_args = array( $start_date_input, $end_date_input );

if ( 'ALL' !== $sector_filter ) {
    $query_sql     .= ' AND category = %s';
    $query_args[] = $sector_filter;
}
$query_sql .= ' ORDER BY expense_date DESC, id DESC LIMIT 500';

$expenses       = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_args ) );
$filtered_total = array_sum( wp_list_pluck( $expenses, 'amount' ) );
?>

<div class="ifs-pms-expense-wrapper">
    <!-- Top KPI Metrics -->
    <div class="ifs-pms-kpi-grid">
        <div class="ifs-pms-kpi-card">
            <div class="ifs-pms-kpi-top">
                <span class="ifs-pms-kpi-label"><?php esc_html_e( 'Selected Range Cost', 'swimming-pool-manager' ); ?></span>
                <div class="ifs-pms-kpi-icon ifs-pms-kpi-icon-red">
                    <span class="dashicons dashicons-clock"></span>
                </div>
            </div>
            <div class="ifs-pms-kpi-val ifs-pms-mono ifs-pms-kpi-val-red">
                <?php echo esc_html( $currency . ' ' . number_format_i18n( $exp_period, 2 ) ); ?>
            </div>
            <div class="ifs-pms-kpi-sub"><?php printf( esc_html__( 'Filtered total (%s to %s)', 'swimming-pool-manager' ), esc_html( $start_date_input ), esc_html( $end_date_input ) ); ?></div>
        </div>

        <div class="ifs-pms-kpi-card">
            <div class="ifs-pms-kpi-top">
                <span class="ifs-pms-kpi-label"><?php esc_html_e( "Today's Outflow", 'swimming-pool-manager' ); ?></span>
                <div class="ifs-pms-kpi-icon ifs-pms-kpi-icon-amber">
                    <span class="dashicons dashicons-dashboard"></span>
                </div>
            </div>
            <div class="ifs-pms-kpi-val ifs-pms-mono ifs-pms-kpi-val-amber">
                <?php echo esc_html( $currency . ' ' . number_format_i18n( $exp_today, 2 ) ); ?>
            </div>
            <div class="ifs-pms-kpi-sub"><?php esc_html_e( 'Disbursements posted today', 'swimming-pool-manager' ); ?></div>
        </div>

        <div class="ifs-pms-kpi-card">
            <div class="ifs-pms-kpi-top">
                <span class="ifs-pms-kpi-label"><?php esc_html_e( 'Major Cost Sector', 'swimming-pool-manager' ); ?></span>
                <div class="ifs-pms-kpi-icon ifs-pms-kpi-icon-purple">
                    <span class="dashicons dashicons-admin-settings"></span>
                </div>
            </div>
            <div class="ifs-pms-kpi-val ifs-pms-kpi-val-dark">
                <?php echo esc_html( $top_cat ); ?>
            </div>
            <div class="ifs-pms-kpi-sub"><?php esc_html_e( 'Highest allocation in range', 'swimming-pool-manager' ); ?></div>
        </div>
    </div>

    <!-- Sub Navigation Tabs -->
    <div class="ifs-pms-subnav-bar" role="tablist">
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>" onclick="ifsPms.switchExpenseTab('add', this)">
            <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Expense', 'swimming-pool-manager' ); ?>
        </button>
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>" onclick="ifsPms.switchExpenseTab('list', this)">
            <span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'All Expenses & Audit', 'swimming-pool-manager' ); ?>
        </button>
    </div>

    <!-- TAB 1: Add Expense Form -->
    <div id="ifsPmsExpensePaneAdd" class="ifs-pms-tab-pane <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-form-box-centered">
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e( 'Record Facility Outflow', 'swimming-pool-manager' ); ?>
                    </h3>
                    <span class="ifs-pms-badge ifs-pms-badge-danger"><?php esc_html_e( 'Debit (-)', 'swimming-pool-manager' ); ?></span>
                </div>

                <form method="POST" action="<?php echo esc_url( $base_url . '&view=expenses' ); ?>" id="ifsPmsExpenseForm">
                    <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                    <input type="hidden" name="ifs_pms_action" value="add_expense">

                    <div class="ifs-pms-form-stack">
                        <div class="ifs-pms-form-group">
                            <label class="ifs-pms-label" for="ifsExpTitle"><?php esc_html_e( 'Outflow Purpose / Description', 'swimming-pool-manager' ); ?> *</label>
                            <input type="text" name="expense_title" id="ifsExpTitle" required placeholder="<?php esc_attr_e( 'e.g. Monthly Staff Salary / Electricity Bill', 'swimming-pool-manager' ); ?>" autocomplete="off">
                            <div class="ifs-pms-chips-wrap">
                                <span class="ifs-pms-chip" onclick="ifsPms.setExpensePreset('Monthly Staff Salary & Wages', 'Staff Salaries')"><?php esc_html_e( 'Staff Salary', 'swimming-pool-manager' ); ?></span>
                                <span class="ifs-pms-chip" onclick="ifsPms.setExpensePreset('Monthly Electricity & Power Utility Bill', 'Utility Bills')"><?php esc_html_e( 'Utility Bill', 'swimming-pool-manager' ); ?></span>
                                <span class="ifs-pms-chip" onclick="ifsPms.setExpensePreset('Liquid Chlorine 50L Drum', 'Water Treatment')"><?php esc_html_e( 'Chlorine Drum', 'swimming-pool-manager' ); ?></span>
                                <span class="ifs-pms-chip" onclick="ifsPms.setExpensePreset('Rooftop BBQ Charcoal & Fuel', 'Restaurant & F&B')"><?php esc_html_e( 'BBQ Charcoal', 'swimming-pool-manager' ); ?></span>
                                <span class="ifs-pms-chip" onclick="ifsPms.setExpensePreset('Tower Backup Generator Diesel', 'Electricity / Utilities')"><?php esc_html_e( 'Diesel Gen', 'swimming-pool-manager' ); ?></span>
                                <span class="ifs-pms-chip" onclick="ifsPms.setExpensePreset('Pool Vacuum Filter Cartridge', 'Pump Maintenance')"><?php esc_html_e( 'Filter Cartridge', 'swimming-pool-manager' ); ?></span>
                            </div>
                        </div>

                        <div class="ifs-pms-form-group">
                            <label class="ifs-pms-label" for="ifsExpCat"><?php esc_html_e( 'Operational Sector', 'swimming-pool-manager' ); ?> *</label>
                            <select name="expense_cat" id="ifsExpCat">
                                <option value="Staff Salaries"><?php esc_html_e( 'Staff Salaries & Wages', 'swimming-pool-manager' ); ?></option>
                                <option value="Utility Bills"><?php esc_html_e( 'Utility Bills (Electricity, Water, Gas)', 'swimming-pool-manager' ); ?></option>
                                <option value="Water Treatment"><?php esc_html_e( 'Water Treatment & Chemicals', 'swimming-pool-manager' ); ?></option>
                                <option value="Restaurant & F&B"><?php esc_html_e( 'Restaurant, F&B & Bar Inventory', 'swimming-pool-manager' ); ?></option>
                                <option value="Electricity / Utilities"><?php esc_html_e( 'General Utilities & Power', 'swimming-pool-manager' ); ?></option>
                                <option value="Pump Maintenance"><?php esc_html_e( 'Pump, Filter & Mechanical Maintenance', 'swimming-pool-manager' ); ?></option>
                                <option value="Staff Expenses"><?php esc_html_e( 'Staff Uniforms, Meals & Bonus', 'swimming-pool-manager' ); ?></option>
                                <option value="General Facility"><?php esc_html_e( 'Rooftop Deck & General Maintenance', 'swimming-pool-manager' ); ?></option>
                                <option value="Other Costs"><?php esc_html_e( 'Miscellaneous & Operating Overheads', 'swimming-pool-manager' ); ?></option>
                            </select>
                        </div>

                        <div class="ifs-pms-grid-2">
                            <div class="ifs-pms-form-group">
                                <label class="ifs-pms-label" for="ifsExpAmount"><?php printf( esc_html__( 'Amount Outflow (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?> *</label>
                                <input type="number" step="0.01" name="expense_amount" id="ifsExpAmount" class="ifs-pms-mono" required placeholder="0.00" min="0.01">
                            </div>

                            <div class="ifs-pms-form-group">
                                <label class="ifs-pms-label" for="ifsExpDate"><?php esc_html_e( 'Disbursement Date', 'swimming-pool-manager' ); ?> *</label>
                                <input type="date" name="expense_date" id="ifsExpDate" class="ifs-pms-mono" value="<?php echo esc_attr( $today_ymd ); ?>" required>
                            </div>
                        </div>

                        <div class="ifs-pms-submit-wrap">
                            <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary ifs-pms-btn-lg ifs-pms-btn-submit-block">
                                <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Post Expense Record', 'swimming-pool-manager' ); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 2: All Expenses & Audit Registry -->
    <div id="ifsPmsExpensePaneList" class="ifs-pms-tab-pane <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-panel-card">
            <div class="ifs-pms-panel-head">
                <h3 class="ifs-pms-panel-title">
                    <span class="dashicons dashicons-media-text"></span>
                    <?php esc_html_e( 'Expense Audit & Date Range Registry', 'swimming-pool-manager' ); ?>
                </h3>
                <span class="ifs-pms-badge ifs-pms-badge-neutral">
                    <?php echo count( $expenses ); ?> <?php esc_html_e( 'Postings Found', 'swimming-pool-manager' ); ?>
                </span>
            </div>

            <!-- Date Range & Sector Filtering Toolbar -->
            <div class="ifs-pms-audit-toolbar">
                <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ifs-pms-audit-form">
                    <input type="hidden" name="page" value="ifs-pms">
                    <input type="hidden" name="view" value="expenses">
                    <input type="hidden" name="tab" value="list">

                    <div class="ifs-pms-audit-filters">
                        <div class="ifs-pms-date-pill">
                            <span class="ifs-pms-pill-label"><?php esc_html_e( 'From', 'swimming-pool-manager' ); ?></span>
                            <input type="date" name="start_date" value="<?php echo esc_attr( $start_date_input ); ?>">
                            <span class="ifs-pms-pill-label"><?php esc_html_e( 'To', 'swimming-pool-manager' ); ?></span>
                            <input type="date" name="end_date" value="<?php echo esc_attr( $end_date_input ); ?>">
                        </div>

                        <select name="sector" onchange="this.form.submit()">
                            <option value="ALL" <?php selected( $sector_filter, 'ALL' ); ?>><?php esc_html_e( 'All Sectors', 'swimming-pool-manager' ); ?></option>
                            <option value="Staff Salaries" <?php selected( $sector_filter, 'Staff Salaries' ); ?>><?php esc_html_e( 'Staff Salaries', 'swimming-pool-manager' ); ?></option>
                            <option value="Utility Bills" <?php selected( $sector_filter, 'Utility Bills' ); ?>><?php esc_html_e( 'Utility Bills', 'swimming-pool-manager' ); ?></option>
                            <option value="Water Treatment" <?php selected( $sector_filter, 'Water Treatment' ); ?>><?php esc_html_e( 'Water Treatment', 'swimming-pool-manager' ); ?></option>
                            <option value="Restaurant & F&B" <?php selected( $sector_filter, 'Restaurant & F&B' ); ?>><?php esc_html_e( 'Restaurant & F&B', 'swimming-pool-manager' ); ?></option>
                            <option value="Electricity / Utilities" <?php selected( $sector_filter, 'Electricity / Utilities' ); ?>><?php esc_html_e( 'General Utilities', 'swimming-pool-manager' ); ?></option>
                            <option value="Pump Maintenance" <?php selected( $sector_filter, 'Pump Maintenance' ); ?>><?php esc_html_e( 'Pump Maintenance', 'swimming-pool-manager' ); ?></option>
                            <option value="Staff Expenses" <?php selected( $sector_filter, 'Staff Expenses' ); ?>><?php esc_html_e( 'Staff Meals / Uniforms', 'swimming-pool-manager' ); ?></option>
                            <option value="General Facility" <?php selected( $sector_filter, 'General Facility' ); ?>><?php esc_html_e( 'General Facility', 'swimming-pool-manager' ); ?></option>
                            <option value="Other Costs" <?php selected( $sector_filter, 'Other Costs' ); ?>><?php esc_html_e( 'Other Costs', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-pms-flex-gap-8">
                        <button type="submit" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-primary ifs-pms-btn-filter-action">
                            <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter Ledger', 'swimming-pool-manager' ); ?>
                        </button>
                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-secondary ifs-pms-btn-print-action" onclick="window.print()">
                            <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print', 'swimming-pool-manager' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="ifs-pms-table-wrap">
                <table class="ifs-pms-table" id="ifsPmsExpenseTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Description', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Sector', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Logged By', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'swimming-pool-manager' ); ?></th>
                            <th class="ifs-pms-th-actions"><?php esc_html_e( 'Actions', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $expenses ) ) : ?>
                            <?php foreach ( $expenses as $e ) : ?>
                                <tr class="ifs-pms-expense-data-row" data-cat="<?php echo esc_attr( $e->category ); ?>">
                                    <td>
                                        <strong class="ifs-pms-text-strong"><?php echo esc_html( $e->title ); ?></strong>
                                    </td>
                                    <td>
                                        <span class="ifs-pms-cat-badge">
                                            <?php echo esc_html( $e->category ); ?>
                                        </span>
                                    </td>
                                    <td class="ifs-pms-mono ifs-pms-text-danger">
                                        -<?php echo esc_html( $currency . ' ' . number_format_i18n( (float) $e->amount, 2 ) ); ?>
                                    </td>
                                    <td class="ifs-pms-text-muted">
                                        <?php echo esc_html( ! empty( $e->added_by ) ? $e->added_by : __( 'System', 'swimming-pool-manager' ) ); ?>
                                    </td>
                                    <td class="ifs-pms-mono ifs-pms-text-sub">
                                        <?php echo esc_html( $e->expense_date ); ?>
                                    </td>
                                    <td class="ifs-pms-td-actions">
                                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-view" onclick='ifsPms.openViewExpenseModal(<?php echo wp_json_encode( array(
                                            'id'       => $e->id,
                                            'title'    => $e->title,
                                            'category' => $e->category,
                                            'amount'   => number_format_i18n( (float) $e->amount, 2 ),
                                            'date'     => $e->expense_date,
                                            'by'       => ! empty( $e->added_by ) ? $e->added_by : __( 'System', 'swimming-pool-manager' ),
                                        ) ); ?>)'>
                                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'swimming-pool-manager' ); ?>
                                        </button>

                                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-edit" onclick='ifsPms.openEditExpenseModal(<?php echo wp_json_encode( array(
                                            'id'       => $e->id,
                                            'title'    => $e->title,
                                            'category' => $e->category,
                                            'amount'   => $e->amount,
                                            'date'     => $e->expense_date,
                                        ) ); ?>)'>
                                            <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'swimming-pool-manager' ); ?>
                                        </button>

                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url . '&view=expenses' ); ?>" class="ifs-pms-inline-form" onsubmit="return confirm(<?php echo wp_json_encode( __( 'Delete this expense posting permanently?', 'swimming-pool-manager' ) ); ?>);">
                                                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                                                <input type="hidden" name="ifs_pms_action" value="delete_expense">
                                                <input type="hidden" name="expense_id" value="<?php echo esc_attr( $e->id ); ?>">
                                                <button type="submit" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-delete" title="<?php esc_attr_e( 'Delete Permanently', 'swimming-pool-manager' ); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr id="ifsPmsExpenseEmptyRow">
                                <td colspan="6" class="ifs-pms-empty-state">
                                    <div class="ifs-pms-empty-state-icon">
                                        <span class="dashicons dashicons-media-text"></span>
                                    </div>
                                    <?php esc_html_e( 'No operational expenditures recorded for this date range.', 'swimming-pool-manager' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Filtered Footer -->
            <div class="ifs-pms-table-footer">
                <span class="ifs-pms-footer-label"><?php esc_html_e( 'Total Filtered Outflow Outlay', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-pms-mono ifs-pms-footer-total">
                    -<?php echo esc_html( $currency . ' ' . number_format_i18n( $filtered_total, 2 ) ); ?>
                </strong>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: View Expense Details -->
<div id="ifsPmsViewExpenseModal" class="ifs-pms-modal-overlay">
    <div class="ifs-pms-modal-card">
        <div class="ifs-pms-modal-head">
            <h3 class="ifs-pms-modal-title">
                <span class="dashicons dashicons-media-text"></span>
                <?php esc_html_e( 'Expense Details', 'swimming-pool-manager' ); ?>
            </h3>
            <button type="button" class="ifs-pms-modal-close" onclick="ifsPms.closeViewExpenseModal()">&times;</button>
        </div>

        <div class="ifs-pms-modal-details-stack">
            <div class="ifs-pms-modal-row">
                <span class="ifs-pms-modal-label"><?php esc_html_e( 'Description:', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-pms-modal-val-strong" id="ifsPmsViewExpTitle">--</strong>
            </div>

            <div class="ifs-pms-modal-row">
                <span class="ifs-pms-modal-label"><?php esc_html_e( 'Sector Category:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-cat-badge" id="ifsPmsViewExpCat">--</span>
            </div>

            <div class="ifs-pms-modal-row">
                <span class="ifs-pms-modal-label"><?php esc_html_e( 'Amount Outflow:', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-pms-mono ifs-pms-modal-val-red" id="ifsPmsViewExpAmount">--</strong>
            </div>

            <div class="ifs-pms-modal-row">
                <span class="ifs-pms-modal-label"><?php esc_html_e( 'Payment Date:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-mono" id="ifsPmsViewExpDate">--</span>
            </div>

            <div class="ifs-pms-modal-row-last">
                <span class="ifs-pms-modal-label"><?php esc_html_e( 'Logged By Operator:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-modal-val-muted" id="ifsPmsViewExpBy">--</span>
            </div>
        </div>

        <div class="ifs-pms-modal-footer">
            <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-secondary ifs-pms-btn-block ifs-pms-btn-close-modal" onclick="ifsPms.closeViewExpenseModal()">
                <?php esc_html_e( 'Close Slip', 'swimming-pool-manager' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Edit Expense Record -->
<div id="ifsPmsEditExpenseModal" class="ifs-pms-modal-overlay">
    <div class="ifs-pms-modal-card">
        <div class="ifs-pms-modal-head">
            <h3 class="ifs-pms-modal-title">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit Expense Entry', 'swimming-pool-manager' ); ?>
            </h3>
            <button type="button" class="ifs-pms-modal-close" onclick="ifsPms.closeEditExpenseModal()">&times;</button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url . '&view=expenses' ); ?>" id="ifsPmsEditExpenseForm">
            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
            <input type="hidden" name="ifs_pms_action" value="edit_expense">
            <input type="hidden" name="expense_id" id="ifsPmsEditExpId" value="">

            <div class="ifs-pms-form-stack">
                <div class="ifs-pms-form-group">
                    <label class="ifs-pms-label"><?php esc_html_e( 'Outflow Description', 'swimming-pool-manager' ); ?> *</label>
                    <input type="text" name="expense_title" id="ifsPmsEditExpTitle" required>
                </div>

                <div class="ifs-pms-form-group">
                    <label class="ifs-pms-label"><?php esc_html_e( 'Operational Sector', 'swimming-pool-manager' ); ?> *</label>
                    <select name="expense_cat" id="ifsPmsEditExpCat">
                        <option value="Staff Salaries"><?php esc_html_e( 'Staff Salaries & Wages', 'swimming-pool-manager' ); ?></option>
                        <option value="Utility Bills"><?php esc_html_e( 'Utility Bills (Electricity, Water, Gas)', 'swimming-pool-manager' ); ?></option>
                        <option value="Water Treatment"><?php esc_html_e( 'Water Treatment & Chemicals', 'swimming-pool-manager' ); ?></option>
                        <option value="Restaurant & F&B"><?php esc_html_e( 'Restaurant, F&B & Bar Inventory', 'swimming-pool-manager' ); ?></option>
                        <option value="Electricity / Utilities"><?php esc_html_e( 'General Utilities & Power', 'swimming-pool-manager' ); ?></option>
                        <option value="Pump Maintenance"><?php esc_html_e( 'Pump, Filter & Mechanical Maintenance', 'swimming-pool-manager' ); ?></option>
                        <option value="Staff Expenses"><?php esc_html_e( 'Staff Uniforms, Meals & Bonus', 'swimming-pool-manager' ); ?></option>
                        <option value="General Facility"><?php esc_html_e( 'Rooftop Deck & General Maintenance', 'swimming-pool-manager' ); ?></option>
                        <option value="Other Costs"><?php esc_html_e( 'Miscellaneous & Operating Overheads', 'swimming-pool-manager' ); ?></option>
                    </select>
                </div>

                <div class="ifs-pms-grid-2">
                    <div class="ifs-pms-form-group">
                        <label class="ifs-pms-label"><?php printf( esc_html__( 'Amount (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?> *</label>
                        <input type="number" step="0.01" name="expense_amount" id="ifsPmsEditExpAmount" class="ifs-pms-mono" required min="0.01">
                    </div>

                    <div class="ifs-pms-form-group">
                        <label class="ifs-pms-label"><?php esc_html_e( 'Date', 'swimming-pool-manager' ); ?> *</label>
                        <input type="date" name="expense_date" id="ifsPmsEditExpDate" class="ifs-pms-mono" required>
                    </div>
                </div>

                <div class="ifs-pms-modal-actions">
                    <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary ifs-pms-btn-flex-2">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Save Changes', 'swimming-pool-manager' ); ?>
                    </button>
                    <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-secondary ifs-pms-btn-flex-1" onclick="ifsPms.closeEditExpenseModal()">
                        <?php esc_html_e( 'Cancel', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>