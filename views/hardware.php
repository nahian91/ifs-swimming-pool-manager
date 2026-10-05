<?php
/**
 * View: Turnstiles, Hardware Relays & Thermal Printer Infrastructure Hub
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Administrative Gatekeeper
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ifs_manage_settings' ) ) {
    wp_die( esc_html__( 'Forbidden: Hardware Infrastructure access requires administrative privileges.', 'swimming-pool-manager' ), 403 );
}

$base_url = admin_url( 'admin.php?page=ifs-pms&view=hardware' );

// 2. Handle Hardware Settings Submission
$save_feedback = false;
$test_feedback = null;

if ( isset( $_POST['ifs_pms_action'] ) && 'save_hardware_settings' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    update_option( 'ifs_pms_relay_ip', sanitize_text_field( wp_unslash( $_POST['relay_ip'] ?? '' ) ) );
    update_option( 'ifs_pms_relay_port', sanitize_text_field( wp_unslash( $_POST['relay_port'] ?? '80' ) ) );
    update_option( 'ifs_pms_relay_channel', absint( wp_unslash( $_POST['relay_channel'] ?? 1 ) ) );
    update_option( 'ifs_pms_relay_trigger_sec', max( 1, absint( wp_unslash( $_POST['relay_duration'] ?? 3 ) ) ) );
    update_option( 'ifs_pms_relay_protocol', sanitize_key( wp_unslash( $_POST['relay_protocol'] ?? 'http_get' ) ) );

    update_option( 'ifs_pms_escpos_ip', sanitize_text_field( wp_unslash( $_POST['escpos_ip'] ?? '' ) ) );
    update_option( 'ifs_pms_escpos_port', sanitize_text_field( wp_unslash( $_POST['escpos_port'] ?? '9100' ) ) );
    update_option( 'ifs_pms_thermal_paper_width', sanitize_text_field( wp_unslash( $_POST['thermal_paper_width'] ?? '80mm' ) ) );
    update_option( 'ifs_pms_scanner_mode', sanitize_key( wp_unslash( $_POST['scanner_mode'] ?? 'camera' ) ) );

    if ( function_exists( 'ifs_pms_record_audit' ) ) {
        ifs_pms_record_audit( 'hardware_config_update', 'RelayHub', 'Updated IoT turnstile and thermal printer settings' );
    }

    $save_feedback = true;
}

// 3. Handle Live Manual Diagnostic Relay Pulse Test
if ( isset( $_POST['ifs_pms_action'] ) && 'test_relay_trigger' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $relay_ip       = sanitize_text_field( wp_unslash( $_POST['test_ip'] ?? '' ) );
    $relay_port     = sanitize_text_field( wp_unslash( $_POST['test_port'] ?? '80' ) );
    $relay_channel  = absint( wp_unslash( $_POST['test_channel'] ?? 1 ) );
    $relay_duration = max( 1, absint( wp_unslash( $_POST['test_duration'] ?? 3 ) ) );

    if ( empty( $relay_ip ) ) {
        $test_feedback = array(
            'success' => false,
            'message' => __( 'Missing Controller Target IP Address.', 'swimming-pool-manager' ),
        );
    } else {
        $endpoint = "http://{$relay_ip}:{$relay_port}/trigger?channel={$relay_channel}&duration={$relay_duration}";
        $response = wp_remote_get( $endpoint, array( 'timeout' => 2.5 ) );

        if ( is_wp_error( $response ) ) {
            $test_feedback = array(
                'success' => false,
                'message' => sprintf( __( 'Connection Failed: %s', 'swimming-pool-manager' ), $response->get_error_message() ),
            );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                $test_feedback = array(
                    'success' => true,
                    'message' => sprintf( __( 'Pulse Triggered! Controller responded with HTTP %d (Turnstile Unlocked for %ds).', 'swimming-pool-manager' ), $code, $relay_duration ),
                );
            } else {
                $test_feedback = array(
                    'success' => false,
                    'message' => sprintf( __( 'Controller reachable but returned HTTP Status %d.', 'swimming-pool-manager' ), $code ),
                );
            }
        }
    }
}

// 4. Current Configuration Variables
$relay_ip          = (string) get_option( 'ifs_pms_relay_ip', '' );
$relay_port        = (string) get_option( 'ifs_pms_relay_port', '80' );
$relay_channel     = (int) get_option( 'ifs_pms_relay_channel', 1 );
$relay_duration    = (int) get_option( 'ifs_pms_relay_trigger_sec', 3 );
$relay_protocol    = (string) get_option( 'ifs_pms_relay_protocol', 'http_get' );

$escpos_ip         = (string) get_option( 'ifs_pms_escpos_ip', '' );
$escpos_port       = (string) get_option( 'ifs_pms_escpos_port', '9100' );
$thermal_paper     = (string) get_option( 'ifs_pms_thermal_paper_width', '80mm' );
$scanner_mode      = (string) get_option( 'ifs_pms_scanner_mode', 'camera' );
?>

<style>
.ifs-hw-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}
.ifs-hw-wrap * { box-sizing: border-box; }
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

.ifs-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.ifs-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
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
.ifs-btn-warning { background: #f59e0b; color: #ffffff; }
.ifs-btn-warning:hover { background: #d97706; }

.ifs-status-pill {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ifs-status-online { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.ifs-status-offline { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

.ifs-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

.ifs-diag-box {
    background: var(--ifs-surface-hover, #f8fafc);
    border: 1px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
    padding: 16px;
    margin-top: 14px;
}

/* Dark Mode Variables */
[data-theme="dark"] .ifs-card, [data-theme="dark"] .ifs-diag-box {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-input, [data-theme="dark"] .ifs-select {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.15);
    color: #f8fafc;
}

@media screen and (max-width: 1024px) {
    .ifs-grid-2 { grid-template-columns: 1fr; }
}
@media screen and (max-width: 640px) {
    .ifs-grid-3 { grid-template-columns: 1fr; }
}
</style>

<div class="ifs-hw-wrap">
    <?php if ( $save_feedback ) : ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px;">
            ✓ <?php esc_html_e( 'Hardware peripherals & relay trigger settings saved successfully.', 'swimming-pool-manager' ); ?>
        </div>
    <?php endif; ?>

    <?php if ( is_array( $test_feedback ) ) : ?>
        <div style="background: <?php echo $test_feedback['success'] ? '#ecfdf5' : '#fef2f2'; ?>; border: 1px solid <?php echo $test_feedback['success'] ? '#a7f3d0' : '#fecaca'; ?>; color: <?php echo $test_feedback['success'] ? '#065f46' : '#991b1b'; ?>; padding: 12px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px;">
            <?php echo $test_feedback['success'] ? '✓' : '⚠️'; ?> <?php echo esc_html( $test_feedback['message'] ); ?>
        </div>
    <?php endif; ?>

    <!-- Main Configuration Form -->
    <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
        <input type="hidden" name="ifs_pms_action" value="save_hardware_settings">
        <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

        <div class="ifs-grid-2">
            <!-- 1. Turnstile & IoT Relay Settings -->
            <div class="ifs-card">
                <div class="ifs-card-header">
                    <div>
                        <h2 class="ifs-card-title">
                            <span class="dashicons dashicons-rest-api"></span>
                            <?php esc_html_e( 'IoT Turnstile Gate Relay', 'swimming-pool-manager' ); ?>
                        </h2>
                    </div>
                    <span class="ifs-status-pill <?php echo ! empty( $relay_ip ) ? 'ifs-status-online' : 'ifs-status-offline'; ?>">
                        <span class="ifs-live-dot"></span>
                        <?php echo ! empty( $relay_ip ) ? esc_html__( 'Network IP Configured', 'swimming-pool-manager' ) : esc_html__( 'Standby / Manual', 'swimming-pool-manager' ); ?>
                    </span>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Relay Controller IP Address *', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="relay_ip" class="ifs-input ifs-mono" placeholder="192.168.1.120" value="<?php echo esc_attr( $relay_ip ); ?>">
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Port', 'swimming-pool-manager' ); ?></label>
                        <input type="number" name="relay_port" class="ifs-input ifs-mono" placeholder="80" value="<?php echo esc_attr( $relay_port ); ?>">
                    </div>
                </div>

                <div class="ifs-grid-3">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Relay Channel', 'swimming-pool-manager' ); ?></label>
                        <select name="relay_channel" class="ifs-select">
                            <option value="1" <?php selected( $relay_channel, 1 ); ?>>CH 1 (Main Gate)</option>
                            <option value="2" <?php selected( $relay_channel, 2 ); ?>>CH 2 (Aux Gate)</option>
                            <option value="3" <?php selected( $relay_channel, 3 ); ?>>CH 3</option>
                            <option value="4" <?php selected( $relay_channel, 4 ); ?>>CH 4</option>
                        </select>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Unlock Duration (Sec)', 'swimming-pool-manager' ); ?></label>
                        <input type="number" name="relay_duration" min="1" max="15" class="ifs-input ifs-mono" value="<?php echo esc_attr( (string) $relay_duration ); ?>">
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Protocol Engine', 'swimming-pool-manager' ); ?></label>
                        <select name="relay_protocol" class="ifs-select">
                            <option value="http_get" <?php selected( $relay_protocol, 'http_get' ); ?>>HTTP GET Rest</option>
                            <option value="tcp_socket" <?php selected( $relay_protocol, 'tcp_socket' ); ?>>Raw TCP Socket</option>
                        </select>
                    </div>
                </div>

                <p style="font-size: 11.5px; color: var(--ifs-text-tertiary, #64748b); margin: 0; line-height: 1.5;">
                    <?php esc_html_e( 'When a valid QR code is scanned in the Access Gate terminal, the system automatically dispatches an unlock command to the configured controller.', 'swimming-pool-manager' ); ?>
                </p>
            </div>

            <!-- 2. Thermal Printer & Barcode Scanner Settings -->
            <div class="ifs-card">
                <div class="ifs-card-header">
                    <div>
                        <h2 class="ifs-card-title">
                            <span class="dashicons dashicons-printer"></span>
                            <?php esc_html_e( 'ESC/POS Printer & Scanner Desk', 'swimming-pool-manager' ); ?>
                        </h2>
                    </div>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Network ESC/POS Printer IP', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="escpos_ip" class="ifs-input ifs-mono" placeholder="192.168.1.180 (Optional)" value="<?php echo esc_attr( $escpos_ip ); ?>">
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Printer Port', 'swimming-pool-manager' ); ?></label>
                        <input type="number" name="escpos_port" class="ifs-input ifs-mono" placeholder="9100" value="<?php echo esc_attr( $escpos_port ); ?>">
                    </div>
                </div>

                <div class="ifs-grid-2">
                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Thermal Receipt Paper Width', 'swimming-pool-manager' ); ?></label>
                        <select name="thermal_paper_width" class="ifs-select">
                            <option value="80mm" <?php selected( $thermal_paper, '80mm' ); ?>>80mm Standard POS Slip</option>
                            <option value="58mm" <?php selected( $thermal_paper, '58mm' ); ?>>58mm Compact Mini Slip</option>
                        </select>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Gate Scanner Mode', 'swimming-pool-manager' ); ?></label>
                        <select name="scanner_mode" class="ifs-select">
                            <option value="camera" <?php selected( $scanner_mode, 'camera' ); ?>>Integrated Camera Optical</option>
                            <option value="usb_hid" <?php selected( $scanner_mode, 'usb_hid' ); ?>>USB Hardware Barcode Gun</option>
                        </select>
                    </div>
                </div>

                <p style="font-size: 11.5px; color: var(--ifs-text-tertiary, #64748b); margin: 0; line-height: 1.5;">
                    <?php esc_html_e( 'Leave network printer IP blank to rely on standard browser silent printing for USB receipt printers.', 'swimming-pool-manager' ); ?>
                </p>
            </div>
        </div>

        <div style="margin-top: 18px; display: flex; gap: 10px;">
            <button type="submit" class="ifs-btn ifs-btn-primary">
                <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Hardware Configuration', 'swimming-pool-manager' ); ?>
            </button>
        </div>
    </form>

    <!-- 3. Hardware Pulse Diagnostics Test Bench -->
    <div class="ifs-card">
        <div class="ifs-card-header">
            <h2 class="ifs-card-title">
                <span class="dashicons dashicons-hammer"></span>
                <?php esc_html_e( 'Manual Relay Pulse Diagnostic Bench', 'swimming-pool-manager' ); ?>
            </h2>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>" class="ifs-diag-box">
            <input type="hidden" name="ifs_pms_action" value="test_relay_trigger">
            <input type="hidden" name="test_ip" value="<?php echo esc_attr( $relay_ip ); ?>">
            <input type="hidden" name="test_port" value="<?php echo esc_attr( $relay_port ); ?>">
            <input type="hidden" name="test_channel" value="<?php echo esc_attr( (string) $relay_channel ); ?>">
            <input type="hidden" name="test_duration" value="<?php echo esc_attr( (string) $relay_duration ); ?>">
            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <strong style="font-size: 13.5px;"><?php esc_html_e( 'Test Trigger Turnstile Barrier Output', 'swimming-pool-manager' ); ?></strong>
                    <div style="font-size: 12px; color: var(--ifs-text-tertiary, #64748b); margin-top: 3px;">
                        <?php echo esc_html( sprintf( __( 'Target: %s:%s &bull; Channel %d &bull; %d Seconds Pulse', 'swimming-pool-manager' ), ( $relay_ip ?: '0.0.0.0' ), $relay_port, $relay_channel, $relay_duration ) ); ?>
                    </div>
                </div>

                <button type="submit" class="ifs-btn ifs-btn-warning" <?php disabled( empty( $relay_ip ) ); ?>>
                    <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Trigger Test Signal', 'swimming-pool-manager' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>