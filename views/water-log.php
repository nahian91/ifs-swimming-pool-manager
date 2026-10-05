<?php
/**
 * View: Water Quality, Chemical Parameters & Facility Safety Logs
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Context, Permissions & Tables
$can_log  = current_user_can( 'ifs_access_terminal' ) || current_user_can( 'manage_options' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'ifs_manage_settings' );

if ( ! $can_log ) {
    wp_die( esc_html__( 'Forbidden: Insufficient privileges to view water quality telemetry.', 'swimming-pool-manager' ), 403 );
}

$t_water = $wpdb->prefix . 'ifs_pms_water_logs';
$base_url = admin_url( 'admin.php?page=ifs-pms&view=water-log' );

// 2. Form Submission: Record Inspection
$save_feedback = false;

if ( isset( $_POST['ifs_pms_action'] ) && 'add_water_log' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $ph_level    = max( 0.00, floatval( wp_unslash( $_POST['ph_level'] ?? 7.4 ) ) );
    $chlorine    = max( 0.00, floatval( wp_unslash( $_POST['chlorine_ppm'] ?? 1.5 ) ) );
    $water_temp  = floatval( wp_unslash( $_POST['water_temp'] ?? 28.0 ) );
    $clarity     = sanitize_text_field( wp_unslash( $_POST['clarity'] ?? 'Clear' ) );
    $notes       = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
    $operator    = wp_get_current_user()->display_name;

    $inserted = $wpdb->insert(
        $t_water,
        array(
            'ph_level'     => $ph_level,
            'chlorine_ppm' => $chlorine,
            'water_temp'   => $water_temp,
            'clarity'      => $clarity,
            'logged_by'    => $operator,
            'logged_at'    => current_time( 'mysql' ),
            'notes'        => $notes,
        ),
        array( '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
    );

    if ( $inserted ) {
        if ( function_exists( 'ifs_pms_record_audit' ) ) {
            ifs_pms_record_audit( 'water_test_logged', "pH: {$ph_level}", "Cl: {$chlorine}ppm, Temp: {$water_temp}C, Clarity: {$clarity}" );
        }
        $save_feedback = true;
    }
}

// 3. Pagination & Query Parameters
$paged       = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
$per_page    = 20;
$offset      = ( $paged - 1 ) * $per_page;

$total_rows  = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_water}" );
$total_pages = ceil( $total_rows / $per_page );

$logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$t_water} ORDER BY id DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    )
);

// 4. Latest Water Telemetry & Safety Status
$latest = ! empty( $logs ) ? $logs[0] : null;
$ph_cur = $latest ? (float) $latest->ph_level : 7.4;
$cl_cur = $latest ? (float) $latest->chlorine_ppm : 1.5;

$is_ph_optimal = ( $ph_cur >= 7.2 && $ph_cur <= 7.8 );
$is_cl_optimal = ( $cl_cur >= 1.0 && $cl_cur <= 3.0 );
$is_safe       = $is_ph_optimal && $is_cl_optimal;
?>

<style>
.ifs-water-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}
.ifs-water-wrap * { box-sizing: border-box; }
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

/* Diagnostic Overview Grid */
.ifs-diag-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.ifs-diag-tile {
    background: var(--ifs-surface-hover, #f8fafc);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
    padding: 16px;
}

.ifs-diag-sub {
    font-size: 11px;
    font-weight: 700;
    color: var(--ifs-text-tertiary, #64748b);
    text-transform: uppercase;
    margin-bottom: 6px;
}

.ifs-diag-val {
    font-size: 24px;
    font-weight: 900;
    line-height: 1.1;
    margin-bottom: 6px;
}

.ifs-diag-target {
    font-size: 11.5px;
    color: var(--ifs-text-tertiary, #64748b);
}

/* Forms & Inputs */
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

.ifs-input, .ifs-select, .ifs-textarea {
    width: 100%;
    padding: 9px 12px;
    border-radius: 10px;
    border: 1.5px solid var(--ifs-border-subtle, #cbd5e1);
    background: var(--ifs-surface-hover, #f8fafc);
    color: var(--ifs-text-primary, #0f172a);
    font-size: 13.5px;
    transition: all 0.2s ease;
}

.ifs-input:focus, .ifs-select:focus, .ifs-textarea:focus {
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
.ifs-status-success { background: #ecfdf5; color: #059669; }
.ifs-status-warning { background: #fffbeb; color: #d97706; }
.ifs-status-danger  { background: #fef2f2; color: #dc2626; }

/* Table */
.ifs-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
}
.ifs-water-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}
.ifs-water-table th {
    background: var(--ifs-surface-hover, #f8fafc);
    color: var(--ifs-text-tertiary, #64748b);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1.5px solid var(--ifs-border-subtle, #e2e8f0);
}
.ifs-water-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    vertical-align: middle;
}
.ifs-water-table tr:hover td {
    background: var(--ifs-surface-hover, #f8fafc);
}

/* Dark Mode Variables */
[data-theme="dark"] .ifs-card, [data-theme="dark"] .ifs-diag-tile {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-input, [data-theme="dark"] .ifs-select, [data-theme="dark"] .ifs-textarea {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.15);
    color: #f8fafc;
}
[data-theme="dark"] .ifs-water-table th {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-water-table td {
    border-color: rgba(255, 255, 255, 0.05);
}

@media screen and (max-width: 1024px) {
    .ifs-split-cols { grid-template-columns: 1fr; }
    .ifs-diag-grid { grid-template-columns: repeat(2, 1fr); }
}
@media screen and (max-width: 640px) {
    .ifs-diag-grid { grid-template-columns: 1fr; }
}
</style>

<div class="ifs-water-wrap">
    <?php if ( $save_feedback ) : ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px;">
            ✓ <?php esc_html_e( 'Chemical test inspection logged successfully. Audit trail synchronized.', 'swimming-pool-manager' ); ?>
        </div>
    <?php endif; ?>

    <!-- Diagnostic Parameter Cockpit Tiles -->
    <div class="ifs-card">
        <div class="ifs-card-header">
            <div>
                <h2 class="ifs-card-title">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php esc_html_e( 'Live Water Sanitation & Health Index', 'swimming-pool-manager' ); ?>
                </h2>
                <span style="font-size: 12px; color: var(--ifs-text-tertiary, #64748b);">
                    <?php esc_html_e( 'DIN 19643 & OSHA Aquatic Compliance Standard', 'swimming-pool-manager' ); ?>
                </span>
            </div>

            <span class="ifs-pill-tag <?php echo $is_safe ? 'ifs-status-success' : 'ifs-status-danger'; ?>" style="font-size: 12px; padding: 6px 14px;">
                <?php echo $is_safe ? esc_html__( 'Compliant & Safe for Swimming', 'swimming-pool-manager' ) : esc_html__( 'Chemical Imbalance Alert', 'swimming-pool-manager' ); ?>
            </span>
        </div>

        <div class="ifs-diag-grid">
            <div class="ifs-diag-tile">
                <div class="ifs-diag-sub"><?php esc_html_e( 'Current pH Balance', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-diag-val ifs-mono" style="color: <?php echo $is_ph_optimal ? '#10b981' : '#ef4444'; ?>;">
                    <?php echo esc_html( number_format_i18n( $ph_cur, 2 ) ); ?>
                </div>
                <div class="ifs-diag-target"><?php esc_html_e( 'Target: 7.20 – 7.80 pH', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-diag-tile">
                <div class="ifs-diag-sub"><?php esc_html_e( 'Free Chlorine Density', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-diag-val ifs-mono" style="color: <?php echo $is_cl_optimal ? '#10b981' : '#ef4444'; ?>;">
                    <?php echo esc_html( number_format_i18n( $cl_cur, 2 ) ); ?> <span style="font-size: 13px;">ppm</span>
                </div>
                <div class="ifs-diag-target"><?php esc_html_e( 'Target: 1.00 – 3.00 ppm', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-diag-tile">
                <div class="ifs-diag-sub"><?php esc_html_e( 'Water Temperature', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-diag-val ifs-mono" style="color: #0284c7;">
                    <?php echo esc_html( $latest ? number_format_i18n( (float) $latest->water_temp, 1 ) : '28.0' ); ?>°C
                </div>
                <div class="ifs-diag-target"><?php esc_html_e( 'Optimal: 26.0°C – 29.5°C', 'swimming-pool-manager' ); ?></div>
            </div>

            <div class="ifs-diag-tile">
                <div class="ifs-diag-sub"><?php esc_html_e( 'Clarity & Turbidity', 'swimming-pool-manager' ); ?></div>
                <div class="ifs-diag-val" style="color: #0f172a; font-size: 20px;">
                    <?php echo esc_html( $latest ? $latest->clarity : 'Clear' ); ?>
                </div>
                <div class="ifs-diag-target"><?php esc_html_e( 'Drain bottom clearly visible', 'swimming-pool-manager' ); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Working Deck -->
    <div class="ifs-split-cols">
        <!-- 1. Record Inspection Form -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <?php esc_html_e( 'Log Chemical Inspection', 'swimming-pool-manager' ); ?>
                </h3>
            </div>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <input type="hidden" name="ifs_pms_action" value="add_water_log">
                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Measured pH *', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.01" min="0" max="14" name="ph_level" class="ifs-input ifs-mono" value="7.40" required>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Free Chlorine (PPM) *', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.01" min="0" max="20" name="chlorine_ppm" class="ifs-input ifs-mono" value="1.50" required>
                    </div>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Temperature (°C)', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.1" name="water_temp" class="ifs-input ifs-mono" value="28.0">
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Water Clarity', 'swimming-pool-manager' ); ?></label>
                        <select name="clarity" class="ifs-select">
                            <option value="Clear"><?php esc_html_e( 'Crystal Clear', 'swimming-pool-manager' ); ?></option>
                            <option value="Slightly Cloudy"><?php esc_html_e( 'Slightly Cloudy', 'swimming-pool-manager' ); ?></option>
                            <option value="Turbid"><?php esc_html_e( 'Turbid / Poor Visibility', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="ifs-form-group">
                    <label><?php esc_html_e( 'Chemical Additions & Pool Notes', 'swimming-pool-manager' ); ?></label>
                    <textarea name="notes" rows="3" class="ifs-textarea" placeholder="<?php esc_attr_e( 'e.g. Added 500g Chlorine Granules, filter backwash completed...', 'swimming-pool-manager' ); ?>"></textarea>
                </div>

                <div style="margin-top: 10px;">
                    <button type="submit" class="ifs-btn ifs-btn-primary" style="width: 100%;">
                        <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Inspection Record', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. Test History Ledger -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Inspection History Ledger', 'swimming-pool-manager' ); ?>
                </h3>
                <span class="ifs-pill-tag ifs-status-success"><?php echo esc_html( sprintf( __( '%d Logs', 'swimming-pool-manager' ), $total_rows ) ); ?></span>
            </div>

            <div class="ifs-table-container">
                <table class="ifs-water-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Timestamp', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'pH Level', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Chlorine', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Clarity', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Inspector', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : ?>
                            <?php foreach ( $logs as $row ) : 
                                $ph = (float) $row->ph_level;
                                $cl = (float) $row->chlorine_ppm;
                                $row_safe = ( $ph >= 7.2 && $ph <= 7.8 ) && ( $cl >= 1.0 && $cl <= 3.0 );
                            ?>
                                <tr>
                                    <td class="ifs-mono" style="font-size: 11px; color: #64748b; white-space: nowrap;">
                                        <?php echo esc_html( $row->logged_at ); ?>
                                    </td>
                                    <td>
                                        <strong class="ifs-mono" style="color: <?php echo ( $ph >= 7.2 && $ph <= 7.8 ) ? '#10b981' : '#ef4444'; ?>;">
                                            <?php echo esc_html( number_format_i18n( $ph, 2 ) ); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <strong class="ifs-mono" style="color: <?php echo ( $cl >= 1.0 && $cl <= 3.0 ) ? '#10b981' : '#ef4444'; ?>;">
                                            <?php echo esc_html( number_format_i18n( $cl, 2 ) ); ?> ppm
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="ifs-pill-tag <?php echo $row_safe ? 'ifs-status-success' : 'ifs-status-warning'; ?>" style="font-size: 10.5px;">
                                            <?php echo esc_html( $row->clarity ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $row->logged_by ); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #64748b; padding: 36px 10px;">
                                    <?php esc_html_e( 'No water quality tests recorded yet.', 'swimming-pool-manager' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ( $total_pages > 1 ) : ?>
                <div style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 14px;">
                    <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>" class="ifs-btn <?php echo ( $paged === $i ) ? 'ifs-btn-primary' : 'ifs-btn-secondary'; ?>" style="height: 32px; padding: 0 10px; font-size: 11.5px;">
                            <?php echo esc_html( $i ); ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>