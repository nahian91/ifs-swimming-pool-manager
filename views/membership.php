<?php
/**
 * View: Member Directory & Pass Enrollment Terminal (Dashicons UI)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$t_members = $wpdb->prefix . 'ifs_pms_memberships';
$currency  = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$b_name    = esc_html( (string) get_option( 'ifs_pms_business_name', 'Ozone Restaurant & Skypool' ) );
$base_url  = admin_url( 'admin.php?page=ifs-pms' );
$today_dt  = current_time( 'Y-m-d' );
$is_admin  = current_user_can( 'manage_options' ) || current_user_can( 'ozone_manage_settings' );

// Tab Router
$active_tab = isset( $_GET['tab'] ) && 'list' === $_GET['tab'] ? 'list' : 'add';

// Query Members
$members = $wpdb->get_results(
    "SELECT * FROM {$t_members} ORDER BY id DESC LIMIT 200"
);
?>

<style>
/* Guaranteed Modal Display & Backdrop Styling */
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
    max-width: 520px;
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

<div class="ifs-pms-membership-wrapper">
    <!-- Sub Navigation Tab Bar -->
    <div class="ifs-pms-subnav-bar" role="tablist">
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>" id="ifsPmsMemberTabBtnAdd" onclick="ifsPmsSwitchMemberTab('add', this)">
            <span class="dashicons dashicons-id-alt"></span> <?php esc_html_e( 'Add Member', 'swimming-pool-manager' ); ?>
        </button>
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>" id="ifsPmsMemberTabBtnList" onclick="ifsPmsSwitchMemberTab('list', this)">
            <span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'All Members', 'swimming-pool-manager' ); ?>
        </button>
    </div>

    <!-- TAB 1: Add Member Terminal -->
    <div id="ifsPmsMemberPaneAdd" class="ifs-pms-tab-pane <?php echo ( 'add' === $active_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-membership-layout">
            <!-- Enrollment Form -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-id-alt"></span>
                        <?php esc_html_e( 'Enroll Aquatic Member', 'swimming-pool-manager' ); ?>
                    </h3>
                    <span class="ifs-pms-badge ifs-pms-badge-success">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Auto RFID Pass', 'swimming-pool-manager' ); ?>
                    </span>
                </div>

                <form method="POST" action="<?php echo esc_url( $base_url . '&view=membership' ); ?>" id="ifsPmsMemberForm">
                    <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                    <input type="hidden" name="ifs_pms_action" value="create_membership">

                    <div class="ifs-pms-form-stack">
                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsMemName"><?php esc_html_e( 'Subscriber Full Name', 'swimming-pool-manager' ); ?> *</label>
                            <input type="text" name="m_name" id="ifsMemName" required placeholder="<?php esc_attr_e( 'e.g. Farhan Chowdhury', 'swimming-pool-manager' ); ?>" autocomplete="off">
                        </div>

                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsMemPhone"><?php esc_html_e( 'Mobile Contact (Primary Key)', 'swimming-pool-manager' ); ?> *</label>
                            <input type="tel" name="m_phone" id="ifsMemPhone" required placeholder="017XXXXXXXX" pattern="[0-9+\s\-]{7,20}" autocomplete="off">
                        </div>

                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label"><?php esc_html_e( 'Patron Profile Image / Avatar', 'swimming-pool-manager' ); ?></label>
                            <div class="ifs-pms-avatar-upload-row">
                                <input type="text" name="profile_image" id="ifsMemAvatarInput" placeholder="https://... image URL" class="ifs-pms-flex-1" oninput="ifsPmsSyncCardDisplay()">
                                <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-media" onclick="ifsPmsOpenMemberMediaUploader()">
                                    <span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Browse', 'swimming-pool-manager' ); ?>
                                </button>
                            </div>
                        </div>

                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsMemPlan"><?php esc_html_e( 'Membership Tier Plan', 'swimming-pool-manager' ); ?></label>
                            <select name="plan_type" id="ifsMemPlan" onchange="ifsPmsHandlePlanSelect()">
                                <option value="Monthly Sky Pass" data-months="1" data-price="4500.00"><?php esc_html_e( 'Monthly Sky Pass (1 Month)', 'swimming-pool-manager' ); ?></option>
                                <option value="Quarterly Lounge Pass" data-months="3" data-price="12500.00"><?php esc_html_e( 'Quarterly Lounge Pass (3 Months)', 'swimming-pool-manager' ); ?></option>
                                <option value="Half-Yearly VIP Pass" data-months="6" data-price="22500.00"><?php esc_html_e( 'Half-Yearly VIP Cabana Pass (6 Months)', 'swimming-pool-manager' ); ?></option>
                                <option value="Annual Elite Corporate" data-months="12" data-price="42000.00"><?php esc_html_e( 'Annual Elite VIP Pass (12 Months)', 'swimming-pool-manager' ); ?></option>
                            </select>
                        </div>

                        <div class="ifs-pms-grid-2">
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label" for="ifsMemDuration"><?php esc_html_e( 'Duration (Months)', 'swimming-pool-manager' ); ?></label>
                                <input type="number" name="duration_months" id="ifsMemDuration" class="ifs-pms-mono" value="1" min="1" max="36" required oninput="ifsPmsSyncCardDisplay()">
                            </div>
                            <div class="ifs-pms-field-group">
                                <label class="ifs-pms-field-label" for="ifsMemAmount"><?php printf( esc_html__( 'Total Due (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?></label>
                                <input type="number" step="0.01" name="m_amount" id="ifsMemAmount" class="ifs-pms-mono" value="4500.00" required>
                            </div>
                        </div>

                        <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary ifs-pms-btn-lg ifs-pms-btn-submit-block">
                            <span class="dashicons dashicons-id-alt"></span> <?php esc_html_e( 'Enroll & Provision Member Pass', 'swimming-pool-manager' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Live Holographic Card Preview -->
            <div>
                <div class="ifs-pms-rfid-card-stage">
                    <div class="ifs-pms-virtual-rfid-card">
                        <span class="dashicons dashicons-building ifs-pms-virtual-card-watermark"></span>
                        
                        <div class="ifs-pms-card-head-row">
                            <div class="ifs-pms-card-chip"></div>
                            <span class="ifs-pms-badge ifs-pms-badge-success ifs-pms-card-tier-pill" id="ifsPmsCardTierPill">
                                <?php esc_html_e( 'Monthly Sky Pass', 'swimming-pool-manager' ); ?>
                            </span>
                        </div>

                        <div class="ifs-pms-card-profile-row">
                            <div id="ifsPmsCardAvatarBox" class="ifs-pms-card-avatar">FC</div>
                            <div>
                                <div class="ifs-pms-card-business-title">
                                    <?php echo $b_name; ?>
                                </div>
                                <div class="ifs-pms-card-holder-name" id="ifsPmsCardHolder">
                                    <?php esc_html_e( 'Farhan Chowdhury', 'swimming-pool-manager' ); ?>
                                </div>
                            </div>
                        </div>

                        <div class="ifs-pms-card-footer-row ifs-pms-mono">
                            <div>
                                <div class="ifs-pms-card-footer-label"><?php esc_html_e( 'MEMBER TOKEN', 'swimming-pool-manager' ); ?></div>
                                <strong class="ifs-pms-card-token">OZONE-MEM-PRO</strong>
                            </div>
                            <div class="ifs-pms-text-align-right">
                                <div class="ifs-pms-card-footer-label"><?php esc_html_e( 'VALID THROUGH', 'swimming-pool-manager' ); ?></div>
                                <span id="ifsPmsCardExpiryDate" class="ifs-pms-card-expiry">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: All Members Registry -->
    <div id="ifsPmsMemberPaneList" class="ifs-pms-tab-pane <?php echo ( 'list' === $active_tab ) ? 'active' : ''; ?>">
        <div class="ifs-pms-panel-card">
            <div class="ifs-pms-panel-head">
                <h3 class="ifs-pms-panel-title">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e( 'Subscriber Directory Ledger', 'swimming-pool-manager' ); ?>
                </h3>
                <span class="ifs-pms-badge ifs-pms-badge-neutral">
                    <?php echo count( $members ); ?> <?php esc_html_e( 'Registered Members', 'swimming-pool-manager' ); ?>
                </span>
            </div>

            <div class="ifs-pms-search-bar">
                <div class="ifs-pms-search-container">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="ifsPmsMemberFilterInput" placeholder="<?php esc_attr_e( 'Filter by subscriber name, mobile, or UID...', 'swimming-pool-manager' ); ?>" oninput="ifsPmsFilterDirectory()" autocomplete="off">
                    <button type="button" class="ifs-pms-search-clear" id="ifsPmsFilterClearBtn" onclick="ifsPmsClearFilter()">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>
                <div class="ifs-pms-record-counter-text" id="ifsPmsRecordCounter">
                    <?php printf( esc_html__( 'Total: %d members', 'swimming-pool-manager' ), count( $members ) ); ?>
                </div>
            </div>

            <div class="ifs-pms-table-wrap">
                <table class="ifs-pms-table" id="ifsPmsMemberDirectoryTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Subscriber UID', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Patron Profile', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Tier Plan', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Valid Through', 'swimming-pool-manager' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'swimming-pool-manager' ); ?></th>
                            <th class="ifs-pms-th-actions"><?php esc_html_e( 'Actions', 'swimming-pool-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $members ) ) : ?>
                            <?php foreach ( $members as $m ) :
                                $expiry_stamp = strtotime( $m->expiry_date );
                                $today_stamp  = strtotime( $today_dt );
                                $is_expired   = ( $m->expiry_date < $today_dt );
                                $diff_days    = (int) floor( ( $expiry_stamp - $today_stamp ) / DAY_IN_SECONDS );

                                if ( $is_expired ) {
                                    $badge_class = 'ifs-pms-badge-danger';
                                    $status_text = __( 'Expired', 'swimming-pool-manager' );
                                } elseif ( $diff_days <= 7 ) {
                                    $badge_class = 'ifs-pms-badge-warning';
                                    $status_text = sprintf( __( 'Expires in %d d', 'swimming-pool-manager' ), $diff_days );
                                } else {
                                    $badge_class = 'ifs-pms-badge-success';
                                    $status_text = ! empty( $m->status ) ? $m->status : __( 'Active', 'swimming-pool-manager' );
                                }

                                $member_view_data = array(
                                    'code'          => (string) $m->member_code,
                                    'name'          => (string) $m->name,
                                    'phone'         => (string) $m->phone,
                                    'plan'          => (string) $m->plan_type,
                                    'amount'        => number_format_i18n( (float) $m->amount, 2 ),
                                    'start'         => (string) $m->start_date,
                                    'expiry'        => (string) $m->expiry_date,
                                    'status'        => (string) $m->status,
                                    'profile_image' => ! empty( $m->profile_image ) ? (string) $m->profile_image : '',
                                );

                                $member_edit_data = array(
                                    'id'            => (int) $m->id,
                                    'code'          => (string) $m->member_code,
                                    'name'          => (string) $m->name,
                                    'phone'         => (string) $m->phone,
                                    'plan'          => (string) $m->plan_type,
                                    'amount'        => (float) $m->amount,
                                    'expiry'        => (string) $m->expiry_date,
                                    'status'        => (string) $m->status,
                                    'profile_image' => ! empty( $m->profile_image ) ? (string) $m->profile_image : '',
                                );
                            ?>
                                <tr class="ifs-pms-member-record-row">
                                    <td class="ifs-pms-mono ifs-pms-text-uid">
                                        <?php echo esc_html( $m->member_code ); ?>
                                    </td>
                                    <td>
                                        <div class="ifs-pms-table-profile-row">
                                            <?php if ( ! empty( $m->profile_image ) ) : ?>
                                                <img src="<?php echo esc_url( $m->profile_image ); ?>" alt="<?php esc_attr_e( 'Avatar', 'swimming-pool-manager' ); ?>" class="ifs-pms-table-avatar-img">
                                            <?php else : ?>
                                                <div class="ifs-pms-table-avatar-fallback">
                                                    <?php echo esc_html( mb_strtoupper( mb_substr( $m->name, 0, 1 ) ) ); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong class="ifs-pms-table-name"><?php echo esc_html( $m->name ); ?></strong><br>
                                                <span class="ifs-pms-table-phone"><?php echo esc_html( $m->phone ); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ifs-pms-table-plan-title"><?php echo esc_html( $m->plan_type ); ?></span>
                                        <div class="ifs-pms-mono ifs-pms-table-plan-amount">
                                            <?php echo esc_html( $currency . ' ' . number_format_i18n( (float) $m->amount, 2 ) ); ?>
                                        </div>
                                    </td>
                                    <td class="ifs-pms-mono ifs-pms-table-expiry">
                                        <?php echo esc_html( $m->expiry_date ); ?>
                                    </td>
                                    <td>
                                        <span class="ifs-pms-badge <?php echo esc_attr( $badge_class ); ?>">
                                            <?php echo esc_html( $status_text ); ?>
                                        </span>
                                    </td>
                                    <td class="ifs-pms-td-actions">
                                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-view" onclick="ifsPmsOpenViewMemberModal(<?php echo esc_attr( wp_json_encode( $member_view_data ) ); ?>)">
                                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'swimming-pool-manager' ); ?>
                                        </button>

                                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-edit" onclick="ifsPmsOpenEditMemberModal(<?php echo esc_attr( wp_json_encode( $member_edit_data ) ); ?>)">
                                            <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'swimming-pool-manager' ); ?>
                                        </button>

                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url . '&view=membership' ); ?>" class="ifs-pms-inline-form" onsubmit="return confirm(<?php echo wp_json_encode( __( 'Permanently revoke and delete this membership account?', 'swimming-pool-manager' ) ); ?>);">
                                                <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
                                                <input type="hidden" name="ifs_pms_action" value="delete_membership">
                                                <input type="hidden" name="member_id" value="<?php echo esc_attr( $m->id ); ?>">
                                                <button type="submit" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-delete" title="<?php esc_attr_e( 'Revoke Membership', 'swimming-pool-manager' ); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr id="ifsPmsEmptyTableNotice">
                                <td colspan="6" class="ifs-pms-empty-state">
                                    <div class="ifs-pms-empty-state-icon">
                                        <span class="dashicons dashicons-id-alt"></span>
                                    </div>
                                    <?php esc_html_e( 'No active subscriptions recorded in system.', 'swimming-pool-manager' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: View Member Digital Card -->
<div id="ifsPmsViewMemberModal" class="ifs-pms-modal-overlay">
    <div class="ifs-pms-modal-card ifs-pms-text-align-center">
        <div class="ifs-pms-modal-head-row">
            <h3 class="ifs-pms-modal-title">
                <span class="dashicons dashicons-id-alt"></span>
                <?php esc_html_e( 'Aquatic Pass Identity', 'swimming-pool-manager' ); ?>
            </h3>
            <button type="button" class="ifs-pms-modal-close-btn" onclick="ifsPmsCloseViewMemberModal()">&times;</button>
        </div>

        <div class="ifs-pms-virtual-rfid-card ifs-pms-modal-rfid-preview">
            <span class="dashicons dashicons-building ifs-pms-virtual-card-watermark"></span>
            
            <div class="ifs-pms-card-head-row">
                <div class="ifs-pms-card-chip"></div>
                <span class="ifs-pms-badge ifs-pms-badge-success ifs-pms-card-tier-pill-small" id="ifsPmsViewModalTierPill">--</span>
            </div>

            <div class="ifs-pms-card-profile-row">
                <div id="ifsPmsViewModalAvatarBox" class="ifs-pms-card-avatar">--</div>
                <div>
                    <div class="ifs-pms-card-business-title"><?php echo $b_name; ?></div>
                    <div class="ifs-pms-modal-holder-title" id="ifsPmsViewModalHolder">--</div>
                    <div class="ifs-pms-modal-holder-phone" id="ifsPmsViewModalPhone">--</div>
                </div>
            </div>

            <div class="ifs-pms-card-footer-row ifs-pms-mono">
                <div>
                    <div class="ifs-pms-card-footer-label"><?php esc_html_e( 'MEMBER TOKEN', 'swimming-pool-manager' ); ?></div>
                    <strong class="ifs-pms-card-token" id="ifsPmsViewModalCode">--</strong>
                </div>
                <div class="ifs-pms-text-align-right">
                    <div class="ifs-pms-card-footer-label"><?php esc_html_e( 'EXPIRES', 'swimming-pool-manager' ); ?></div>
                    <span id="ifsPmsViewModalExpiry" class="ifs-pms-card-expiry">--</span>
                </div>
            </div>
        </div>

        <div class="ifs-pms-modal-actions-split" style="margin-top: 20px; display: flex; gap: 10px;">
            <button type="button" class="ifs-pms-btn ifs-pms-btn-primary ifs-pms-modal-print-btn" style="flex: 1;" onclick="window.print()">
                <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Pass Card', 'swimming-pool-manager' ); ?>
            </button>
            <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-modal-close-action-btn" style="flex: 1;" onclick="ifsPmsCloseViewMemberModal()">
                <?php esc_html_e( 'Close', 'swimming-pool-manager' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Edit Member Record -->
<div id="ifsPmsEditMemberModal" class="ifs-pms-modal-overlay">
    <div class="ifs-pms-modal-card">
        <div class="ifs-pms-modal-head-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 class="ifs-pms-modal-title" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit Member Subscription', 'swimming-pool-manager' ); ?> (<span id="ifsPmsEditModalCode" class="ifs-pms-mono"></span>)
            </h3>
            <button type="button" class="ifs-pms-modal-close-btn" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;" onclick="ifsPmsCloseEditMemberModal()">&times;</button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url . '&view=membership' ); ?>" id="ifsPmsEditMemberForm">
            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
            <input type="hidden" name="ifs_pms_action" value="edit_membership">
            <input type="hidden" name="member_id" id="ifsPmsEditModalId" value="">

            <div class="ifs-pms-form-stack" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="ifs-pms-field-group">
                    <label class="ifs-pms-field-label"><?php esc_html_e( 'Subscriber Full Name', 'swimming-pool-manager' ); ?> *</label>
                    <input type="text" name="m_name" id="ifsPmsEditModalName" required>
                </div>

                <div class="ifs-pms-field-group">
                    <label class="ifs-pms-field-label"><?php esc_html_e( 'Mobile Contact', 'swimming-pool-manager' ); ?> *</label>
                    <input type="tel" name="m_phone" id="ifsPmsEditModalPhone" required>
                </div>

                <div class="ifs-pms-field-group">
                    <label class="ifs-pms-field-label"><?php esc_html_e( 'Patron Profile Image / Avatar', 'swimming-pool-manager' ); ?></label>
                    <div class="ifs-pms-avatar-upload-row" style="display: flex; gap: 8px;">
                        <input type="text" name="profile_image" id="ifsPmsEditModalAvatarInput" placeholder="https://... image URL" style="flex: 1;">
                        <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-media" onclick="if(window.ifsPmsOpenEditMemberMediaUploader){ ifsPmsOpenEditMemberMediaUploader(); }">
                            <span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Browse', 'swimming-pool-manager' ); ?>
                        </button>
                    </div>
                </div>

                <div class="ifs-pms-grid-plan-fee" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Tier Plan Type', 'swimming-pool-manager' ); ?> *</label>
                        <input type="text" name="plan_type" id="ifsPmsEditModalPlan" required>
                    </div>

                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php printf( esc_html__( 'Fee (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?> *</label>
                        <input type="number" step="0.01" name="m_amount" id="ifsPmsEditModalAmount" class="ifs-pms-mono" required>
                    </div>
                </div>

                <div class="ifs-pms-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Expiry Date', 'swimming-pool-manager' ); ?> *</label>
                        <input type="date" name="expiry_date" id="ifsPmsEditModalExpiry" class="ifs-pms-mono" required>
                    </div>

                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Status', 'swimming-pool-manager' ); ?> *</label>
                        <select name="status" id="ifsPmsEditModalStatus" style="height: 48px; border-radius: 12px; border: 1.5px solid #cbd5e1; padding: 8px 14px; font-weight: 700;">
                            <option value="Active"><?php esc_html_e( 'Active', 'swimming-pool-manager' ); ?></option>
                            <option value="Suspended"><?php esc_html_e( 'Suspended', 'swimming-pool-manager' ); ?></option>
                            <option value="Expired"><?php esc_html_e( 'Expired', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="ifs-pms-modal-actions" style="display: flex; gap: 12px; margin-top: 14px;">
                    <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary" style="flex: 2; height: 46px; border-radius: 12px;">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Save Changes', 'swimming-pool-manager' ); ?>
                    </button>
                    <button type="button" class="ifs-pms-btn ifs-pms-btn-sm ifs-pms-btn-cancel" style="flex: 1; height: 46px; border-radius: 12px;" onclick="ifsPmsCloseEditMemberModal()">
                        <?php esc_html_e( 'Cancel', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Member Directory Modal Handlers
 */
(function() {
    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    window.ifsPmsOpenViewMemberModal = function(data) {
        if (!data) return;

        var tierEl    = document.getElementById('ifsPmsViewModalTierPill');
        var holderEl  = document.getElementById('ifsPmsViewModalHolder');
        var phoneEl   = document.getElementById('ifsPmsViewModalPhone');
        var codeEl    = document.getElementById('ifsPmsViewModalCode');
        var expiryEl  = document.getElementById('ifsPmsViewModalExpiry');
        var avatarBox = document.getElementById('ifsPmsViewModalAvatarBox');
        var modal     = document.getElementById('ifsPmsViewMemberModal');

        if (tierEl) tierEl.textContent = data.plan || '--';
        if (holderEl) holderEl.textContent = data.name || '--';
        if (phoneEl) phoneEl.textContent = data.phone || '--';
        if (codeEl) codeEl.textContent = data.code || '--';
        if (expiryEl) expiryEl.textContent = data.expiry || '--';

        if (avatarBox) {
            if (data.profile_image && data.profile_image.trim() !== '') {
                avatarBox.innerHTML = '<img src="' + escapeHtml(data.profile_image) + '" alt="Avatar" style="width:100%;height:100%;border-radius:inherit;object-fit:cover;">';
            } else {
                avatarBox.textContent = data.name ? data.name.charAt(0).toUpperCase() : 'M';
            }
        }

        if (modal) {
            modal.style.setProperty('display', 'flex', 'important');
            modal.classList.add('is-visible');
        }
    };

    window.ifsPmsCloseViewMemberModal = function() {
        var modal = document.getElementById('ifsPmsViewMemberModal');
        if (modal) {
            modal.style.setProperty('display', 'none', 'important');
            modal.classList.remove('is-visible');
        }
    };

    window.ifsPmsOpenEditMemberModal = function(data) {
        if (!data) return;

        var setVal = function(id, val) {
            var el = document.getElementById(id);
            if (el) el.value = val !== undefined && val !== null ? val : '';
        };

        setVal('ifsPmsEditModalId', data.id);
        setVal('ifsPmsEditModalName', data.name);
        setVal('ifsPmsEditModalPhone', data.phone);
        setVal('ifsPmsEditModalPlan', data.plan);
        setVal('ifsPmsEditModalAmount', parseFloat(data.amount || 0).toFixed(2));
        setVal('ifsPmsEditModalExpiry', data.expiry);
        setVal('ifsPmsEditModalStatus', data.status || 'Active');
        setVal('ifsPmsEditModalAvatarInput', data.profile_image || '');

        var codeEl = document.getElementById('ifsPmsEditModalCode');
        if (codeEl) codeEl.textContent = data.code || '';

        var modal = document.getElementById('ifsPmsEditMemberModal');
        if (modal) {
            modal.style.setProperty('display', 'flex', 'important');
            modal.classList.add('is-visible');
        }
    };

    window.ifsPmsCloseEditMemberModal = function() {
        var modal = document.getElementById('ifsPmsEditMemberModal');
        if (modal) {
            modal.style.setProperty('display', 'none', 'important');
            modal.classList.remove('is-visible');
        }
    };

    // Close on background overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList && e.target.classList.contains('ifs-pms-modal-overlay')) {
            window.ifsPmsCloseViewMemberModal();
            window.ifsPmsCloseEditMemberModal();
        }
    });

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.ifsPmsCloseViewMemberModal();
            window.ifsPmsCloseEditMemberModal();
        }
    });
})();
</script>