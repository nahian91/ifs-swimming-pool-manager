<?php
/**
 * View: Security Audit Trail & Activity Console
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Authorization Gatekeeper (Supervisors / Admins Only)
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ifs_manage_settings' ) ) {
    wp_die( esc_html__( 'Forbidden: Security Audit Vault access requires administrative privileges.', 'swimming-pool-manager' ), 403 );
}

$t_audit = $wpdb->prefix . 'ifs_pms_audit_trail';

// 2. Filters & Search Parameters
$search_query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$action_filter = isset( $_GET['action_filter'] ) ? sanitize_key( wp_unslash( $_GET['action_filter'] ) ) : '';
$date_filter   = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : '';

$where_clauses = array( '1=1' );
$query_params  = array();

if ( ! empty( $search_query ) ) {
    $like = '%' . $wpdb->esc_like( $search_query ) . '%';
    $where_clauses[] = '(user_name LIKE %s OR item_ref LIKE %s OR details LIKE %s OR ip_address LIKE %s)';
    $query_params[]  = $like;
    $query_params[]  = $like;
    $query_params[]  = $like;
    $query_params[]  = $like;
}

if ( ! empty( $action_filter ) ) {
    $where_clauses[] = 'action_type = %s';
    $query_params[]  = $action_filter;
}

if ( ! empty( $date_filter ) ) {
    $where_clauses[] = 'DATE(timestamp) = %s';
    $query_params[]  = $date_filter;
}

$where_sql = implode( ' AND ', $where_clauses );

// 3. Pagination Configuration
$paged       = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
$per_page    = 25;
$offset      = ( $paged - 1 ) * $per_page;

$count_sql = "SELECT COUNT(id) FROM {$t_audit} WHERE {$where_sql}";
$total_rows = ! empty( $query_params ) ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $query_params ) ) : (int) $wpdb->get_var( $count_sql );
$total_pages = ceil( $total_rows / $per_page );

$list_sql = "SELECT * FROM {$t_audit} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
$params_with_limit = array_merge( $query_params, array( $per_page, $offset ) );
$logs = $wpdb->get_results( $wpdb->prepare( $list_sql, $params_with_limit ) );

// 4. Action Type Badge Dictionary
function ifs_pms_get_action_badge( $action ) {
    $map = array(
        'issue_ticket'   => array( 'label' => 'Ticket Issued',    'class' => 'ifs-badge-cyan' ),
        'edit_ticket'    => array( 'label' => 'Ticket Modified',  'class' => 'ifs-badge-amber' ),
        'delete_ticket'  => array( 'label' => 'Ticket Voided',    'class' => 'ifs-badge-red' ),
        'scan_success'   => array( 'label' => 'Gate Unlocked',    'class' => 'ifs-badge-green' ),
        'scan_rejected'  => array( 'label' => 'Access Denied',    'class' => 'ifs-badge-red' ),
        'create_staff'   => array( 'label' => 'Staff Provisioned','class' => 'ifs-badge-purple' ),
        'add_expense'    => array( 'label' => 'Expense Recorded', 'class' => 'ifs-badge-amber' ),
        'save_settings'  => array( 'label' => 'Config Modified',  'class' => 'ifs-badge-blue' ),
    );

    $entry = $map[ $action ] ?? array( 'label' => ucfirst( str_replace( '_', ' ', $action ) ), 'class' => 'ifs-badge-gray' );
    return sprintf( '<span class="ifs-audit-badge %s">%s</span>', esc_attr( $entry['class'] ), esc_html( $entry['label'] ) );
}
?>

<style>
.ifs-audit-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}
.ifs-audit-wrap * { box-sizing: border-box; }
.ifs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }

.ifs-audit-card {
    background: var(--ifs-surface, #ffffff);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.ifs-audit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    padding-bottom: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.ifs-audit-title {
    font-size: 17px;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--ifs-text-primary, #0f172a);
}

/* Filters */
.ifs-filter-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 18px;
}
.ifs-input, .ifs-select {
    height: 40px;
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
    height: 40px;
    padding: 0 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
}
.ifs-btn-primary { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; }
.ifs-btn-secondary { background: var(--ifs-surface-hover, #f1f5f9); color: var(--ifs-text-secondary, #334155); border: 1px solid var(--ifs-border-subtle, #cbd5e1); }
.ifs-btn-secondary:hover { background: #e2e8f0; }

/* Table */
.ifs-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
}
.ifs-audit-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}
.ifs-audit-table th {
    background: var(--ifs-surface-hover, #f8fafc);
    color: var(--ifs-text-tertiary, #64748b);
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1.5px solid var(--ifs-border-subtle, #e2e8f0);
}
.ifs-audit-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    vertical-align: middle;
}
.ifs-audit-table tr:hover td {
    background: var(--ifs-surface-hover, #f8fafc);
}

/* Badges */
.ifs-audit-badge {
    padding: 4px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-block;
}
.ifs-badge-cyan   { background: #e0f2fe; color: #0284c7; }
.ifs-badge-green  { background: #ecfdf5; color: #059669; }
.ifs-badge-amber  { background: #fffbeb; color: #d97706; }
.ifs-badge-red    { background: #fef2f2; color: #dc2626; }
.ifs-badge-purple { background: #f3e8ff; color: #7e22ce; }
.ifs-badge-blue   { background: #eff6ff; color: #2563eb; }
.ifs-badge-gray   { background: #f1f5f9; color: #475569; }

/* Detail Payload Display */
.ifs-log-payload {
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--ifs-text-secondary, #334155);
}

/* Modals */
.ifs-modal-backdrop {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}
.ifs-modal-backdrop.active { display: flex; }
.ifs-modal-card {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 540px;
    padding: 24px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

/* Dark Mode Variables */
[data-theme="dark"] .ifs-audit-card,
[data-theme="dark"] .ifs-modal-card {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-input, 
[data-theme="dark"] .ifs-select {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.15);
    color: #f8fafc;
}
[data-theme="dark"] .ifs-audit-table th {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-audit-table td {
    border-color: rgba(255, 255, 255, 0.05);
}
</style>

<div class="ifs-audit-wrap">
    <div class="ifs-audit-card">
        <div class="ifs-audit-header">
            <div>
                <h2 class="ifs-audit-title">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e( 'System Security Audit Vault', 'swimming-pool-manager' ); ?>
                </h2>
                <span style="font-size: 12px; color: var(--ifs-text-tertiary, #64748b);">
                    <?php echo esc_html( sprintf( __( 'Immutable Anti-Tamper Activity Ledger &bull; %d Events Captured', 'swimming-pool-manager' ), $total_rows ) ); ?>
                </span>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="ifsExportAuditCSV()" class="ifs-btn ifs-btn-secondary">
                    <span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Audit Trail', 'swimming-pool-manager' ); ?>
                </button>
            </div>
        </div>

        <!-- Filter Controls -->
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ifs-filter-row">
            <input type="hidden" name="page" value="ifs-pms">
            <input type="hidden" name="view" value="audit-logs">

            <input type="text" name="s" class="ifs-input" placeholder="<?php esc_attr_e( 'User, Ref, Payload, IP...', 'swimming-pool-manager' ); ?>" value="<?php echo esc_attr( $search_query ); ?>" style="max-width: 240px;">

            <select name="action_filter" class="ifs-select" style="max-width: 180px;">
                <option value=""><?php esc_html_e( 'All Event Actions', 'swimming-pool-manager' ); ?></option>
                <option value="issue_ticket" <?php selected( $action_filter, 'issue_ticket' ); ?>><?php esc_html_e( 'Ticket Issued', 'swimming-pool-manager' ); ?></option>
                <option value="edit_ticket" <?php selected( $action_filter, 'edit_ticket' ); ?>><?php esc_html_e( 'Ticket Modified', 'swimming-pool-manager' ); ?></option>
                <option value="delete_ticket" <?php selected( $action_filter, 'delete_ticket' ); ?>><?php esc_html_e( 'Ticket Deleted', 'swimming-pool-manager' ); ?></option>
                <option value="scan_success" <?php selected( $action_filter, 'scan_success' ); ?>><?php esc_html_e( 'Gate Unlocked (Scan)', 'swimming-pool-manager' ); ?></option>
                <option value="scan_rejected" <?php selected( $action_filter, 'scan_rejected' ); ?>><?php esc_html_e( 'Scan Rejected', 'swimming-pool-manager' ); ?></option>
                <option value="add_expense" <?php selected( $action_filter, 'add_expense' ); ?>><?php esc_html_e( 'Expense Logged', 'swimming-pool-manager' ); ?></option>
                <option value="create_staff" <?php selected( $action_filter, 'create_staff' ); ?>><?php esc_html_e( 'Staff Provisioned', 'swimming-pool-manager' ); ?></option>
                <option value="save_settings" <?php selected( $action_filter, 'save_settings' ); ?>><?php esc_html_e( 'Config Modified', 'swimming-pool-manager' ); ?></option>
            </select>

            <input type="date" name="date_filter" class="ifs-input" value="<?php echo esc_attr( $date_filter ); ?>" style="max-width: 160px;">

            <button type="submit" class="ifs-btn ifs-btn-primary">
                <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Apply Filter', 'swimming-pool-manager' ); ?>
            </button>

            <?php if ( ! empty( $search_query ) || ! empty( $action_filter ) || ! empty( $date_filter ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=audit-logs' ) ); ?>" class="ifs-btn ifs-btn-secondary">
                    <?php esc_html_e( 'Clear', 'swimming-pool-manager' ); ?>
                </a>
            <?php endif; ?>
        </form>

        <!-- Audit Ledger Stream -->
        <div class="ifs-table-container">
            <table class="ifs-audit-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Timestamp', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Operator', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Reference Item', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'Details / Payload', 'swimming-pool-manager' ); ?></th>
                        <th><?php esc_html_e( 'IP Address', 'swimming-pool-manager' ); ?></th>
                        <th style="text-align: right;"><?php esc_html_e( 'Inspect', 'swimming-pool-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $logs ) ) : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td class="ifs-mono" style="font-size: 11.5px; color: #64748b; white-space: nowrap;">
                                    <?php echo esc_html( $log->timestamp ); ?>
                                </td>
                                <td>
                                    <?php echo wp_kses_post( ifs_pms_get_action_badge( $log->action_type ) ); ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $log->user_name ); ?></strong>
                                    <div class="ifs-mono" style="font-size: 10.5px; color: #94a3b8;">UID #<?php echo esc_html( $log->user_id ); ?></div>
                                </td>
                                <td>
                                    <strong class="ifs-mono" style="color: #0284c7;"><?php echo esc_html( $log->item_ref ?: '—' ); ?></strong>
                                </td>
                                <td>
                                    <div class="ifs-log-payload ifs-mono" title="<?php echo esc_attr( $log->details ); ?>">
                                        <?php echo esc_html( $log->details ?: '—' ); ?>
                                    </div>
                                </td>
                                <td class="ifs-mono" style="font-size: 11.5px; color: #64748b;">
                                    <?php echo esc_html( $log->ip_address ); ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="ifs-btn ifs-btn-secondary" style="height: 30px; padding: 0 10px; font-size: 11px;" onclick="ifsInspectAuditLog(<?php echo esc_js( wp_json_encode( $log ) ); ?>)">
                                        <?php esc_html_e( 'View', 'swimming-pool-manager' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #64748b; padding: 48px 10px;">
                                <span class="dashicons dashicons-shield" style="font-size: 32px; width: 32px; height: 32px; color: #94a3b8;"></span>
                                <div style="margin-top: 10px; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'No security audit entries match your criteria.', 'swimming-pool-manager' ); ?></div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ( $total_pages > 1 ) : ?>
            <div style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 18px;">
                <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="ifs-btn <?php echo ( $paged === $i ) ? 'ifs-btn-primary' : 'ifs-btn-secondary'; ?>" style="height: 34px; padding: 0 12px; font-size: 12px;">
                        <?php echo esc_html( $i ); ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Inspection Modal -->
<div class="ifs-modal-backdrop" id="ifsAuditDetailModal">
    <div class="ifs-modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 16px;">
            <h3 class="ifs-audit-title" style="font-size: 15px;">
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e( 'Audit Log Event Inspector', 'swimming-pool-manager' ); ?>
            </h3>
            <button type="button" onclick="ifsCloseAuditModal()" style="border: none; background: transparent; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b; font-weight: 600;"><?php esc_html_e( 'Captured Time:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-mono" id="ifsModalTime"></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b; font-weight: 600;"><?php esc_html_e( 'Action Classification:', 'swimming-pool-manager' ); ?></span>
                <span id="ifsModalAction"></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b; font-weight: 600;"><?php esc_html_e( 'Responsible Operator:', 'swimming-pool-manager' ); ?></span>
                <span id="ifsModalOperator"></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b; font-weight: 600;"><?php esc_html_e( 'Origin IP Terminal:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-mono" id="ifsModalIP"></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b; font-weight: 600;"><?php esc_html_e( 'Item Reference Key:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-mono" style="color: #0284c7; font-weight: 700;" id="ifsModalRef"></span>
            </div>

            <div style="margin-top: 8px;">
                <div style="color: #64748b; font-weight: 600; margin-bottom: 6px;"><?php esc_html_e( 'Event Data / Details Payload:', 'swimming-pool-manager' ); ?></div>
                <pre id="ifsModalPayload" class="ifs-mono" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 12px; max-height: 160px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin: 0;"></pre>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="ifs-btn ifs-btn-secondary" onclick="ifsCloseAuditModal()">
                <?php esc_html_e( 'Close', 'swimming-pool-manager' ); ?>
            </button>
        </div>
    </div>
</div>

<script>
function ifsInspectAuditLog(log) {
    document.getElementById('ifsModalTime').textContent = log.timestamp || '—';
    document.getElementById('ifsModalAction').textContent = log.action_type || '—';
    document.getElementById('ifsModalOperator').textContent = (log.user_name || 'System') + ' (UID: ' + log.user_id + ')';
    document.getElementById('ifsModalIP').textContent = log.ip_address || '—';
    document.getElementById('ifsModalRef').textContent = log.item_ref || 'None';
    document.getElementById('ifsModalPayload').textContent = log.details || 'No payload recorded.';
    document.getElementById('ifsAuditDetailModal').classList.add('active');
}

function ifsCloseAuditModal() {
    document.getElementById('ifsAuditDetailModal').classList.remove('active');
}

function ifsExportAuditCSV() {
    var query = window.location.search.replace('?', '');
    var exportUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=ifs_pms_export_audit_csv&security=<?php echo esc_js( wp_create_nonce( 'ifs_pms_security_token' ) ); ?>&' + query;
    window.location.href = exportUrl;
}
</script>