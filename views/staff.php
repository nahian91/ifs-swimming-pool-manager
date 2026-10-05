<?php
/**
 * View: Staff Management & Unified Operator Salary Provisioning (Dashicons UI)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$t_tick   = $wpdb->prefix . 'ifs_pms_tickets';
$t_salary = $wpdb->prefix . 'ifs_pms_salaries';
$currency = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$base_url = admin_url( 'admin.php?page=ifs-pms&view=staff' );
$is_admin = current_user_can( 'manage_options' );

// Active Tab Router ('add' or 'list')
$active_tab = isset( $_GET['tab'] ) && 'list' === $_GET['tab'] ? 'list' : 'add';

// Fetch WordPress Users with salaries joined
$staff_users = get_users( array(
    'role__in' => array( 'administrator', 'ozone_cashier', 'editor' ),
    'orderby'  => 'display_name',
) );

// Index salary records by user_id
$salaries_raw     = $wpdb->get_results( "SELECT * FROM {$t_salary}", OBJECT_K );
$salaries_by_user = array();
foreach ( $salaries_raw as $sal ) {
    $salaries_by_user[ $sal->user_id ] = $sal;
}
?>

<style>
/* Guaranteed Staff Modal Display & Backdrop Styling */
.ifs-pms-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}
.ifs-pms-modal-overlay.is-visible {
    display: flex !important;
}
.ifs-pms-modal-card {
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    width: 100%;
    max-width: 540px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px;
    box-sizing: border-box;
    position: relative;
    border: 1px solid #e2e8f0;
}
[data-theme="dark"] .ifs-pms-modal-card {
    background: #1e293b;
    border-color: #334155;
    color: #f8fafc;
}
</style>

<div class="ifs-pms-staff-wrapper">
    <!-- Sub Navigation Tab Bar -->
    <div class="ifs-pms-subnav-bar" role="tablist">
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>" onclick="ifsPmsSwitchStaffTab('add', this)">
            <span class="dashicons dashicons-id"></span> <?php esc_html_e( 'Add Staff & Salary', 'swimming-pool-manager' ); ?>
        </button>
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>" onclick="ifsPmsSwitchStaffTab('list', this)">
            <span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'All Staff Directory', 'swimming-pool-manager' ); ?>
        </button>
    </div>

    <!-- TAB 1: Add Staff & Salary Terminal -->
    <div id="ifsPmsStaffPaneAdd" class="ifs-pms-tab-pane <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-form-box-centered">
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e( 'Provision Operator & Compensation', 'swimming-pool-manager' ); ?>
                    </h3>
                    <span class="ifs-pms-badge ifs-pms-badge-success">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'RBAC & Payroll', 'swimming-pool-manager' ); ?>
                    </span>
                </div>

                <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=staff' ) ); ?>">
                    <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                    <input type="hidden" name="ifs_pms_action" value="create_staff">

                    <div class="ifs-pms-form-stack">
                        <div class="ifs-pms-grid-2">
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php esc_html_e( 'Username', 'swimming-pool-manager' ); ?> *</label>
                                <input type="text" name="user_login" required placeholder="e.g. cashier_tanvir" autocomplete="off">
                            </div>
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php esc_html_e( 'Display / Full Name', 'swimming-pool-manager' ); ?> *</label>
                                <input type="text" name="display_name" required placeholder="e.g. Tanvir Ahmed" autocomplete="off">
                            </div>
                        </div>

                        <div class="ifs-pms-grid-2">
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php esc_html_e( 'Email Address', 'swimming-pool-manager' ); ?> *</label>
                                <input type="email" name="user_email" required placeholder="cashier@ozoneskypool.com" autocomplete="off">
                            </div>
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php esc_html_e( 'Secure Password', 'swimming-pool-manager' ); ?> *</label>
                                <input type="password" name="user_pass" required placeholder="••••••••••••" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label"><?php esc_html_e( 'Assigned Terminal Role', 'swimming-pool-manager' ); ?> *</label>
                            <select name="user_role">
                                <option value="ozone_cashier"><?php esc_html_e( 'Ozone Cashier (POS, Scanner & Members)', 'swimming-pool-manager' ); ?></option>
                                <option value="administrator"><?php esc_html_e( 'Administrator / Facility Manager (Full Access)', 'swimming-pool-manager' ); ?></option>
                            </select>
                        </div>

                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label"><?php esc_html_e( 'Custom Avatar Image URL (Optional)', 'swimming-pool-manager' ); ?></label>
                            <div class="ifs-pms-avatar-upload-group" style="display: flex; gap: 8px;">
                                <input type="text" name="user_avatar_url" id="ifsPmsCreateAvatarUrl" placeholder="https://... (Leave blank for Gravatar)" autocomplete="off" style="flex: 1;">
                                <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-media-action" onclick="if(window.ifsPmsOpenMediaUploaderForAdd){ ifsPmsOpenMediaUploaderForAdd(); }">
                                    <span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Media Library', 'swimming-pool-manager' ); ?>
                                </button>
                            </div>
                        </div>

                        <hr class="ifs-pms-separator">

                        <div class="ifs-pms-grid-2">
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php printf( esc_html__( 'Base Salary (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?></label>
                                <input type="number" step="0.01" name="base_salary" class="ifs-pms-mono" placeholder="25000.00" value="0.00">
                            </div>
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php printf( esc_html__( 'Allowance (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?></label>
                                <input type="number" step="0.01" name="allowance" class="ifs-pms-mono" placeholder="3000.00" value="0.00">
                            </div>
                        </div>

                        <div class="ifs-pms-grid-2">
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php esc_html_e( 'Pay Frequency', 'swimming-pool-manager' ); ?></label>
                                <select name="pay_frequency">
                                    <option value="Monthly"><?php esc_html_e( 'Monthly', 'swimming-pool-manager' ); ?></option>
                                    <option value="Weekly"><?php esc_html_e( 'Weekly', 'swimming-pool-manager' ); ?></option>
                                    <option value="Daily"><?php esc_html_e( 'Daily Shift', 'swimming-pool-manager' ); ?></option>
                                </select>
                            </div>
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label"><?php esc_html_e( 'Effective Date', 'swimming-pool-manager' ); ?></label>
                                <input type="date" name="effective_date" class="ifs-pms-mono" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                            </div>
                        </div>

                        <div class="ifs-pms-submit-wrap" style="margin-top: 14px;">
                            <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary ifs-pms-btn-lg ifs-pms-btn-submit-block" style="width: 100%;">
                                <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Create Operator & Compensation', 'swimming-pool-manager' ); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 2: All Staff Directory -->
    <div id="ifsPmsStaffPaneList" class="ifs-pms-tab-pane <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-panel-card">
            <div class="ifs-pms-panel-head">
                <h3 class="ifs-pms-panel-title">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e( 'Active Operators Directory & Payroll Ledger', 'swimming-pool-manager' ); ?>
                </h3>
                <span class="ifs-pms-badge ifs-pms-badge-neutral">
                    <?php echo count( $staff_users ); ?> <?php esc_html_e( 'Registered Operators', 'swimming-pool-manager' ); ?>
                </span>
            </div>

            <div class="ifs-pms-search-bar">
                <div class="ifs-pms-search-container">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="ifsPmsStaffSearchInput" placeholder="<?php esc_attr_e( 'Filter by name, username, or email...', 'swimming-pool-manager' ); ?>" oninput="ifsPmsFilterStaffDirectory()" autocomplete="off">
                </div>
                <div class="ifs-pms-directory-subtext">
                    <?php esc_html_e( 'RBAC & Compensation Management', 'swimming-pool-manager' ); ?>
                </div>
            </div>

            <div class="ifs-pms-table-wrap">
                <table class="ifs-pms-table" id="ifsPmsStaffDirectoryTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Operator Profile', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Username', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Role', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Salary & Payout', 'swimming-pool-manager' ); ?></th>
                            <th class="ifs-pms-th-actions"><?php esc_html_e( 'Actions', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $staff_users ) ) : ?>
                            <?php foreach ( $staff_users as $u ) : 
                                $custom_avatar = get_user_meta( $u->ID, 'ifs_pms_custom_avatar', true );
                                $avatar_url    = ! empty( $custom_avatar ) ? $custom_avatar : get_avatar_url( $u->ID, array( 'size' => 96 ) );
                                $initial       = mb_strtoupper( mb_substr( $u->display_name, 0, 1 ) );
                                $role_name     = in_array( 'administrator', $u->roles, true ) ? __( 'Facility Manager', 'swimming-pool-manager' ) : __( 'Cashier Operator', 'swimming-pool-manager' );
                                $sal           = isset( $salaries_by_user[ $u->ID ] ) ? $salaries_by_user[ $u->ID ] : null;
                                $base          = $sal ? (float) $sal->base_salary : 0.00;
                                $allow         = $sal ? (float) $sal->allowance : 0.00;
                                $payout        = $base + $allow;

                                $view_data = array(
                                    'name'   => (string) $u->display_name,
                                    'login'  => (string) $u->user_login,
                                    'email'  => (string) $u->user_email,
                                    'role'   => (string) $role_name,
                                    'avatar' => (string) $avatar_url,
                                    'base'   => (float) $base,
                                    'allow'  => (float) $allow,
                                    'freq'   => (string) ( $sal ? $sal->pay_frequency : 'Monthly' ),
                                );

                                $edit_data = array(
                                    'id'       => (int) $u->ID,
                                    'name'     => (string) $u->display_name,
                                    'email'    => (string) $u->user_email,
                                    'role'     => in_array( 'administrator', $u->roles, true ) ? 'administrator' : 'ozone_cashier',
                                    'avatar'   => (string) $custom_avatar,
                                    'base'     => (float) $base,
                                    'allow'    => (float) $allow,
                                    'freq'     => (string) ( $sal ? $sal->pay_frequency : 'Monthly' ),
                                    'eff_date' => (string) ( $sal ? $sal->effective_date : current_time( 'Y-m-d' ) ),
                                );
                            ?>
                                <tr class="ifs-pms-staff-record-row">
                                    <td>
                                        <div class="ifs-pms-flex-center" style="display: flex; align-items: center; gap: 10px;">
                                            <div class="ifs-pms-staff-avatar-box" style="width: 36px; height: 36px; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #e2e8f0; font-weight: 800;">
                                                <?php if ( ! empty( $avatar_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php esc_attr_e( 'Avatar', 'swimming-pool-manager' ); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else : ?>
                                                    <?php echo esc_html( $initial ); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong class="ifs-pms-text-user-name"><?php echo esc_html( $u->display_name ); ?></strong>
                                                <div class="ifs-pms-text-user-email" style="font-size: 11.5px; color: #64748b;"><?php echo esc_html( $u->user_email ); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="ifs-pms-mono ifs-pms-text-username">
                                        <?php echo esc_html( $u->user_login ); ?>
                                    </td>
                                    <td>
                                        <span class="ifs-pms-badge <?php echo in_array( 'administrator', $u->roles, true ) ? 'ifs-pms-badge-success' : 'ifs-pms-badge-warning'; ?>">
                                            <?php echo esc_html( $role_name ); ?>
                                        </span>
                                    </td>
                                    <td class="ifs-pms-mono">
                                        <?php if ( $sal ) : ?>
                                            <strong class="ifs-pms-text-payout" style="color: #10b981;"><?php echo esc_html( $currency . ' ' . number_format_i18n( $payout, 2 ) ); ?></strong>
                                            <div class="ifs-pms-text-frequency" style="font-size: 11px; color: #64748b;"><?php echo esc_html( $sal->pay_frequency ); ?></div>
                                        <?php else : ?>
                                            <span class="ifs-pms-text-unset" style="color: #94a3b8; font-size: 12px;"><?php esc_html_e( 'Not set', 'swimming-pool-manager' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ifs-pms-td-actions">
                                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-view" onclick="ifsPmsOpenViewStaffModal(<?php echo esc_attr( wp_json_encode( $view_data ) ); ?>)">
                                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'swimming-pool-manager' ); ?>
                                        </button>

                                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-edit" onclick="ifsPmsOpenEditStaffModal(<?php echo esc_attr( wp_json_encode( $edit_data ) ); ?>)">
                                            <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'swimming-pool-manager' ); ?>
                                        </button>

                                        <?php if ( $is_admin && $u->ID !== get_current_user_id() ) : ?>
                                            <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=staff' ) ); ?>" class="ifs-pms-inline-form" onsubmit="return confirm(<?php echo wp_json_encode( __( 'Permanently revoke and delete this operator account?', 'swimming-pool-manager' ) ); ?>);">
                                                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                                                <input type="hidden" name="ifs_pms_action" value="delete_staff">
                                                <input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>">
                                                <button type="submit" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-delete" title="<?php esc_attr_e( 'Revoke Account', 'swimming-pool-manager' ); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: View Staff Details -->
<div id="ifsPmsViewStaffModal" class="ifs-pms-modal-overlay">
    <div class="ifs-pms-modal-card">
        <div class="ifs-pms-modal-head-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 class="ifs-pms-modal-title" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-shield"></span>
                <?php esc_html_e( 'Operator Profile & Compensation', 'swimming-pool-manager' ); ?>
            </h3>
            <button type="button" class="ifs-pms-modal-close-btn" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;" onclick="ifsPmsCloseViewStaffModal()">&times;</button>
        </div>

        <div class="ifs-pms-profile-preview-box" style="display: flex; align-items: center; gap: 14px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 18px;">
            <div class="ifs-pms-staff-avatar-box ifs-pms-avatar-lg" id="ifsPmsViewAvatarBox" style="width: 48px; height: 48px; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #0284c7; color: #fff; font-weight: 800; font-size: 18px;">--</div>
            <div>
                <strong class="ifs-pms-profile-name" id="ifsPmsViewStaffName" style="font-size: 15px; color: #0f172a; display: block;">--</strong>
                <div class="ifs-pms-profile-email" id="ifsPmsViewStaffEmail" style="font-size: 12px; color: #64748b;">--</div>
            </div>
        </div>

        <div class="ifs-pms-modal-details-stack" style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
            <div class="ifs-pms-modal-row" style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0;">
                <span class="ifs-pms-modal-label" style="color: #64748b;"><?php esc_html_e( 'Username:', 'swimming-pool-manager' ); ?></span>
                <strong class="ifs-pms-mono ifs-pms-text-username" id="ifsPmsViewStaffLogin" style="color: #0f172a;">--</strong>
            </div>
            <div class="ifs-pms-modal-row" style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0;">
                <span class="ifs-pms-modal-label" style="color: #64748b;"><?php esc_html_e( 'Terminal Permission:', 'swimming-pool-manager' ); ?></span>
                <span id="ifsPmsViewStaffRoleBadge" class="ifs-pms-badge ifs-pms-badge-success">--</span>
            </div>
            <div class="ifs-pms-modal-row" style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0;">
                <span class="ifs-pms-modal-label" style="color: #64748b;"><?php esc_html_e( 'Base Salary:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-mono ifs-pms-text-weight-bold" id="ifsPmsViewStaffBase" style="font-weight: 700; color: #0f172a;">--</span>
            </div>
            <div class="ifs-pms-modal-row" style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0;">
                <span class="ifs-pms-modal-label" style="color: #64748b;"><?php esc_html_e( 'Allowance:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-mono ifs-pms-text-weight-bold" id="ifsPmsViewStaffAllow" style="font-weight: 700; color: #0f172a;">--</span>
            </div>
            <div class="ifs-pms-modal-row-last" style="display: flex; justify-content: space-between;">
                <span class="ifs-pms-modal-label" style="color: #64748b;"><?php esc_html_e( 'Pay Frequency:', 'swimming-pool-manager' ); ?></span>
                <span class="ifs-pms-badge" id="ifsPmsViewStaffFreq" style="background: #f1f5f9; color: #475569; font-weight: 700;">--</span>
            </div>
        </div>

        <div class="ifs-pms-modal-footer" style="margin-top: 20px;">
            <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-secondary ifs-pms-btn-block" style="width: 100%; height: 42px; border-radius: 10px;" onclick="ifsPmsCloseViewStaffModal()">
                <?php esc_html_e( 'Close Profile', 'swimming-pool-manager' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Edit Staff Account & Salary -->
<div id="ifsPmsEditStaffModal" class="ifs-pms-modal-overlay">
    <div class="ifs-pms-modal-card">
        <div class="ifs-pms-modal-head-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 class="ifs-pms-modal-title" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit Operator Profile & Compensation', 'swimming-pool-manager' ); ?>
            </h3>
            <button type="button" class="ifs-pms-modal-close-btn" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;" onclick="ifsPmsCloseEditStaffModal()">&times;</button>
        </div>

        <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=staff&tab=list' ) ); ?>">
            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
            <input type="hidden" name="ifs_pms_action" value="edit_staff">
            <input type="hidden" name="user_id" id="ifsPmsEditStaffId">

            <div class="ifs-pms-form-stack" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="ifs-pms-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Display Name', 'swimming-pool-manager' ); ?> *</label>
                        <input type="text" name="display_name" id="ifsPmsEditStaffName" required>
                    </div>
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Email Address', 'swimming-pool-manager' ); ?> *</label>
                        <input type="email" name="user_email" id="ifsPmsEditStaffEmail" required>
                    </div>
                </div>

                <div class="ifs-pms-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Terminal Role', 'swimming-pool-manager' ); ?> *</label>
                        <select name="user_role" id="ifsPmsEditStaffRole" style="height: 48px; border-radius: 10px; border: 1.5px solid #cbd5e1; padding: 6px 12px; font-weight: 600;">
                            <option value="ozone_cashier"><?php esc_html_e( 'Ozone Cashier', 'swimming-pool-manager' ); ?></option>
                            <option value="administrator"><?php esc_html_e( 'Facility Manager', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'New Password', 'swimming-pool-manager' ); ?></label>
                        <input type="password" name="user_pass" placeholder="Leave blank if unchanged">
                    </div>
                </div>

                <div class="ifs-pms-field-group">
                    <label class="ifs-pms-field-label"><?php esc_html_e( 'Custom Avatar Image URL', 'swimming-pool-manager' ); ?></label>
                    <div class="ifs-pms-avatar-upload-group" style="display: flex; gap: 8px;">
                        <input type="text" name="user_avatar_url" id="ifsPmsEditAvatarUrl" placeholder="https://..." autocomplete="off" style="flex: 1;">
                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-media-action" onclick="if(window.ifsPmsOpenMediaUploaderForEdit){ ifsPmsOpenMediaUploaderForEdit(); }">
                            <span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Media Library', 'swimming-pool-manager' ); ?>
                        </button>
                    </div>
                </div>

                <hr class="ifs-pms-separator-light" style="border: 0; border-top: 1px dashed #e2e8f0; margin: 4px 0;">

                <div class="ifs-pms-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Base Salary', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.01" name="base_salary" id="ifsPmsEditStaffBase" class="ifs-pms-mono" required>
                    </div>
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Allowance', 'swimming-pool-manager' ); ?></label>
                        <input type="number" step="0.01" name="allowance" id="ifsPmsEditStaffAllow" class="ifs-pms-mono" required>
                    </div>
                </div>

                <div class="ifs-pms-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Pay Frequency', 'swimming-pool-manager' ); ?></label>
                        <select name="pay_frequency" id="ifsPmsEditStaffFreq" style="height: 48px; border-radius: 10px; border: 1.5px solid #cbd5e1; padding: 6px 12px; font-weight: 600;">
                            <option value="Monthly"><?php esc_html_e( 'Monthly', 'swimming-pool-manager' ); ?></option>
                            <option value="Weekly"><?php esc_html_e( 'Weekly', 'swimming-pool-manager' ); ?></option>
                            <option value="Daily"><?php esc_html_e( 'Daily Shift', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Effective Date', 'swimming-pool-manager' ); ?></label>
                        <input type="date" name="effective_date" id="ifsPmsEditStaffDate" class="ifs-pms-mono" required>
                    </div>
                </div>

                <div class="ifs-pms-modal-actions" style="display: flex; gap: 12px; margin-top: 14px;">
                    <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary" style="flex: 2; height: 46px; border-radius: 12px;">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Save Changes', 'swimming-pool-manager' ); ?>
                    </button>
                    <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-secondary" style="flex: 1; height: 46px; border-radius: 12px;" onclick="ifsPmsCloseEditStaffModal()">
                        <?php esc_html_e( 'Cancel', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Staff Directory & Modal Controllers
 */
(function() {
    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    window.ifsPmsOpenViewStaffModal = function(data) {
        if (!data) return;

        var nameEl   = document.getElementById('ifsPmsViewStaffName');
        var loginEl  = document.getElementById('ifsPmsViewStaffLogin');
        var emailEl  = document.getElementById('ifsPmsViewStaffEmail');
        var roleEl   = document.getElementById('ifsPmsViewStaffRoleBadge');
        var baseEl   = document.getElementById('ifsPmsViewStaffBase');
        var allowEl  = document.getElementById('ifsPmsViewStaffAllow');
        var freqEl   = document.getElementById('ifsPmsViewStaffFreq');
        var avatarEl = document.getElementById('ifsPmsViewAvatarBox');
        var modal    = document.getElementById('ifsPmsViewStaffModal');

        if (nameEl)  nameEl.textContent  = data.name || '--';
        if (loginEl) loginEl.textContent = data.login || '--';
        if (emailEl) emailEl.textContent = data.email || '--';
        if (roleEl)  roleEl.textContent  = data.role || '--';

        var curr = '<?php echo esc_js( $currency ); ?> ';
        if (baseEl)  baseEl.textContent  = curr + parseFloat(data.base || 0).toFixed(2);
        if (allowEl) allowEl.textContent = curr + parseFloat(data.allow || 0).toFixed(2);
        if (freqEl)  freqEl.textContent  = data.freq || 'Monthly';

        if (avatarEl) {
            if (data.avatar && data.avatar.trim() !== '') {
                avatarEl.innerHTML = '<img src="' + escapeHtml(data.avatar) + '" alt="Avatar" style="width:100%;height:100%;border-radius:inherit;object-fit:cover;">';
            } else {
                avatarEl.textContent = data.name ? data.name.charAt(0).toUpperCase() : 'O';
            }
        }

        if (modal) {
            modal.style.setProperty('display', 'flex', 'important');
            modal.classList.add('is-visible');
        }
    };

    window.ifsPmsCloseViewStaffModal = function() {
        var modal = document.getElementById('ifsPmsViewStaffModal');
        if (modal) {
            modal.style.setProperty('display', 'none', 'important');
            modal.classList.remove('is-visible');
        }
    };

    window.ifsPmsOpenEditStaffModal = function(data) {
        if (!data) return;

        var setVal = function(id, val) {
            var el = document.getElementById(id);
            if (el) el.value = (val !== undefined && val !== null) ? val : '';
        };

        setVal('ifsPmsEditStaffId', data.id);
        setVal('ifsPmsEditStaffName', data.name);
        setVal('ifsPmsEditStaffEmail', data.email);
        setVal('ifsPmsEditStaffRole', data.role || 'ozone_cashier');
        setVal('ifsPmsEditAvatarUrl', data.avatar || '');
        setVal('ifsPmsEditStaffBase', parseFloat(data.base || 0).toFixed(2));
        setVal('ifsPmsEditStaffAllow', parseFloat(data.allow || 0).toFixed(2));
        setVal('ifsPmsEditStaffFreq', data.freq || 'Monthly');
        setVal('ifsPmsEditStaffDate', data.eff_date || '');

        var modal = document.getElementById('ifsPmsEditStaffModal');
        if (modal) {
            modal.style.setProperty('display', 'flex', 'important');
            modal.classList.add('is-visible');
        }
    };

    window.ifsPmsCloseEditStaffModal = function() {
        var modal = document.getElementById('ifsPmsEditStaffModal');
        if (modal) {
            modal.style.setProperty('display', 'none', 'important');
            modal.classList.remove('is-visible');
        }
    };

    window.ifsPmsFilterStaffDirectory = function() {
        var input = document.getElementById('ifsPmsStaffSearchInput');
        var query = (input ? input.value : '').toLowerCase().trim();
        var rows = document.querySelectorAll('#ifsPmsStaffDirectoryTable tbody .ifs-pms-staff-record-row');

        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            if (!query || text.indexOf(query) !== -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    };

    // Close on overlay backdrop click
    document.addEventListener('click', function(e) {
        if (e.target.classList && e.target.classList.contains('ifs-pms-modal-overlay')) {
            window.ifsPmsCloseViewStaffModal();
            window.ifsPmsCloseEditStaffModal();
        }
    });

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.ifsPmsCloseViewStaffModal();
            window.ifsPmsCloseEditStaffModal();
        }
    });
})();
</script>