<?php
/**
 * View: Terminal Configuration & Master Settings (100% Dynamic Database Driven - Dashicons Version)
 *
 * @package Ozone_Skypool_OS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$currency      = esc_html( (string) get_option( 'ifs_pms_currency', '' ) );
$b_name        = esc_html( (string) get_option( 'ifs_pms_business_name', '' ) );
$phone         = esc_html( (string) get_option( 'ifs_pms_phone', '' ) );
$address       = esc_html( (string) get_option( 'ifs_pms_address', '' ) );
$capacity_raw  = get_option( 'ifs_pms_max_capacity', '' );
$capacity      = ( '' !== $capacity_raw && is_numeric( $capacity_raw ) ) ? (int) $capacity_raw : '';
$receipt_note  = esc_textarea( (string) get_option( 'ifs_pms_receipt_note', '' ) );
$logo_url      = esc_attr( (string) get_option( 'ifs_pms_logo_url', '' ) );
$base_url      = admin_url( 'admin.php?page=ifs-pms' );

// POS Operational States & Toggles - Strictly Pulled from Database
$active_tab          = sanitize_key( $_GET['tab'] ?? 'pos' );
$enable_strict_scan  = (string) get_option( 'ifs_pms_enable_strict_scan', '' );
$auto_expire_passes  = (string) get_option( 'ifs_pms_auto_expire_passes', '' );
$enable_audio_buzzer = (string) get_option( 'ifs_pms_enable_audio_buzzer', '' );
$enable_amenities    = (string) get_option( 'ifs_pms_enable_amenities', '' );
$pool_status         = (string) get_option( 'ifs_pms_pool_status', '' );

$pricing_tiers   = get_option( 'ifs_pms_pricing_tiers', array() );
$amenity_addons  = get_option( 'ifs_pms_amenity_addons', array() );
$enabled_tenders = get_option( 'ifs_pms_enabled_tenders', array() );
if ( ! is_array( $enabled_tenders ) ) {
    $enabled_tenders = array();
}

$quick_cash_str = esc_attr( (string) get_option( 'ifs_pms_quick_cash_presets', '' ) );
$paper_width    = (string) get_option( 'ifs_pms_thermal_paper_width', '' );

// Operating Days
$days_map = array(
    'monday'    => __( 'Monday', 'swimming-pool-manager' ),
    'tuesday'   => __( 'Tuesday', 'swimming-pool-manager' ),
    'wednesday' => __( 'Wednesday', 'swimming-pool-manager' ),
    'thursday'  => __( 'Thursday', 'swimming-pool-manager' ),
    'friday'    => __( 'Friday', 'swimming-pool-manager' ),
    'saturday'  => __( 'Saturday', 'swimming-pool-manager' ),
    'sunday'    => __( 'Sunday', 'swimming-pool-manager' ),
);

$weekly_schedule = get_option( 'ifs_pms_weekly_schedule', array() );
if ( ! is_array( $weekly_schedule ) ) {
    $weekly_schedule = array();
}
?>

<div class="ifs-pms-settings-wrapper">
    <!-- Navigation Tabs -->
    <div class="ifs-pms-subnav-bar" role="tablist">
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'pos' === $active_tab ) ? 'active' : ''; ?>" onclick="ifsPmsSwitchSettingsTab('pos', this)">
            <span class="dashicons dashicons-tickets-alt"></span> <?php esc_html_e( 'Tickets POS & Operations', 'swimming-pool-manager' ); ?>
        </button>
        <button type="button" class="ifs-pms-subnav-btn <?php echo ( 'general' === $active_tab ) ? 'active' : ''; ?>" onclick="ifsPmsSwitchSettingsTab('general', this)">
            <span class="dashicons dashicons-building"></span> <?php esc_html_e( 'Venue & Receipt Header', 'swimming-pool-manager' ); ?>
        </button>
    </div>

    <form method="POST" action="<?php echo esc_url( $base_url . '&view=settings' ); ?>" id="ifsPmsSettingsForm">
        <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>
        <input type="hidden" name="ifs_pms_action" value="save_settings">
        <input type="hidden" name="ifs_pms_active_tab" id="ifsPmsActiveTabInput" value="<?php echo esc_attr( $active_tab ); ?>">

        <!-- TAB 1: POS & Operational Settings -->
        <div id="ifsPmsSettingsPanePos" class="ifs-pms-tab-pane <?php echo ( 'pos' === $active_tab ) ? 'active' : ''; ?>">
            <!-- 1. Admission Package Tiers -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-tag"></span>
                        <?php esc_html_e( 'Admission Packages (Tier Names, Categories & Rates)', 'swimming-pool-manager' ); ?>
                    </h3>
                    <button type="button" class="ifs-pms-btn ifs-pms-btn-secondary" onclick="ifsPmsAddPricingTierRow()">
                        <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Package Tier', 'swimming-pool-manager' ); ?>
                    </button>
                </div>

                <div class="ifs-pms-form-stack">
                    <table class="ifs-pms-repeater-table" id="ifsPmsPricingTierTable">
                        <thead>
                            <tr>
                                <th class="ifs-pms-th-tier-name"><?php esc_html_e( 'Package Name', 'swimming-pool-manager' ); ?></th>
                                <th class="ifs-pms-th-tier-age"><?php esc_html_e( 'Attendee Category / Age Group', 'swimming-pool-manager' ); ?></th>
                                <th class="ifs-pms-th-tier-price"><?php printf( esc_html__( 'Rate / Hr (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?></th>
                                <th class="ifs-pms-th-tier-action"><?php esc_html_e( 'Action', 'swimming-pool-manager' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $pricing_tiers ) && is_array( $pricing_tiers ) ) : ?>
                                <?php foreach ( $pricing_tiers as $tier ) : ?>
                                    <tr>
                                        <td><input type="text" name="ifs_pricing_tier_name[]" value="<?php echo esc_attr( $tier['name'] ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'Enter package title', 'swimming-pool-manager' ); ?>"></td>
                                        <td><input type="text" name="ifs_pricing_tier_age[]" value="<?php echo esc_attr( $tier['age_group'] ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'Enter age/category group', 'swimming-pool-manager' ); ?>"></td>
                                        <td><input type="number" step="0.01" name="ifs_pricing_tier_price[]" value="<?php echo esc_attr( $tier['price'] ?? '' ); ?>" required class="ifs-pms-mono" placeholder="0.00"></td>
                                        <td class="ifs-pms-td-action">
                                            <button type="button" class="ifs-pms-btn ifs-pms-btn-danger ifs-pms-btn-sm" onclick="ifsPmsRemoveRow(this)">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr class="ifs-pms-empty-row">
                                    <td colspan="4" class="ifs-pms-empty-table-notice">
                                        <?php esc_html_e( 'No package tiers registered. Click "Add Package Tier" above to configure.', 'swimming-pool-manager' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. Swimming Pool Status -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e( 'Swimming Pool Operational Status', 'swimming-pool-manager' ); ?>
                    </h3>
                </div>
                <div class="ifs-pms-form-stack">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Current Pool State', 'swimming-pool-manager' ); ?></label>
                        <select name="ifs_pms_pool_status" id="ifsPmsPoolStatusSelect" class="ifs-pms-select-heavy">
                            <option value="open" <?php selected( $pool_status, 'open' ); ?>><?php esc_html_e( 'Open (Accepting Visitors & Ticket Sales)', 'swimming-pool-manager' ); ?></option>
                            <option value="closed" <?php selected( $pool_status, 'closed' ); ?>><?php esc_html_e( 'Closed (Sales Blocked)', 'swimming-pool-manager' ); ?></option>
                            <option value="maintenance" <?php selected( $pool_status, 'maintenance' ); ?>><?php esc_html_e( 'Maintenance Mode (Servicing Deck)', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Day-Wise Operating Hours -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-clock"></span>
                        <?php esc_html_e( 'Weekly Operating Hours', 'swimming-pool-manager' ); ?>
                    </h3>
                </div>
                <div class="ifs-pms-form-stack">
                    <div class="ifs-pms-schedule-stack">
                        <?php foreach ( $days_map as $day_key => $day_label ) : 
                            $day_data     = $weekly_schedule[ $day_key ] ?? array();
                            $saved_status = $day_data['status'] ?? '';
                            $saved_open   = $day_data['open'] ?? '';
                            $saved_close  = $day_data['close'] ?? '';
                        ?>
                            <div class="ifs-pms-schedule-row">
                                <strong class="ifs-pms-schedule-day-title"><?php echo esc_html( $day_label ); ?></strong>
                                
                                <select name="ifs_pms_schedule_<?php echo esc_attr( $day_key ); ?>_status" id="ifsPmsSchedStatus_<?php echo esc_attr( $day_key ); ?>" onchange="ifsPmsToggleDayHours('<?php echo esc_attr( $day_key ); ?>')">
                                    <option value="open" <?php selected( $saved_status, 'open' ); ?>><?php esc_html_e( 'Open', 'swimming-pool-manager' ); ?></option>
                                    <option value="closed" <?php selected( $saved_status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'swimming-pool-manager' ); ?></option>
                                </select>

                                <div class="ifs-pms-schedule-time-block">
                                    <span class="ifs-pms-schedule-time-label"><?php esc_html_e( 'Open:', 'swimming-pool-manager' ); ?></span>
                                    <input type="time" name="ifs_pms_schedule_<?php echo esc_attr( $day_key ); ?>_open" id="ifsPmsOpen_<?php echo esc_attr( $day_key ); ?>" value="<?php echo esc_attr( $saved_open ); ?>" class="ifs-pms-mono ifs-pms-time-input">
                                </div>

                                <div class="ifs-pms-schedule-time-block">
                                    <span class="ifs-pms-schedule-time-label"><?php esc_html_e( 'Close:', 'swimming-pool-manager' ); ?></span>
                                    <input type="time" name="ifs_pms_schedule_<?php echo esc_attr( $day_key ); ?>_close" id="ifsPmsClose_<?php echo esc_attr( $day_key ); ?>" value="<?php echo esc_attr( $saved_close ); ?>" class="ifs-pms-mono ifs-pms-time-input">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 4. Gear Rentals & Amenities -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e( 'Gear Rentals & Amenity Add-Ons', 'swimming-pool-manager' ); ?>
                    </h3>
                </div>
                <div class="ifs-pms-form-stack">
                    <div class="ifs-pms-switch-card">
                        <div class="ifs-pms-switch-meta">
                            <div class="ifs-pms-switch-title"><?php esc_html_e( 'Enable Gear Rentals on POS', 'swimming-pool-manager' ); ?></div>
                            <div class="ifs-pms-switch-desc"><?php esc_html_e( 'Toggle to show or completely hide rentable gear at checkout.', 'swimming-pool-manager' ); ?></div>
                        </div>
                        <label class="ifs-pms-switch">
                            <input type="checkbox" name="ifs_pms_enable_amenities" value="1" <?php checked( $enable_amenities, '1' ); ?>>
                            <span class="ifs-pms-slider"></span>
                        </label>
                    </div>

                    <div id="ifsPmsAmenitiesRepeaterBox" class="ifs-pms-amenities-repeater-wrapper <?php echo ( '1' === $enable_amenities ) ? 'is-visible' : ''; ?>">
                        <div class="ifs-pms-amenities-box-header">
                            <label class="ifs-pms-field-label ifs-pms-field-label-compact"><?php esc_html_e( 'Rentable Gear Inventory & Rates', 'swimming-pool-manager' ); ?></label>
                            <button type="button" class="ifs-pms-btn ifs-pms-btn-secondary" onclick="ifsPmsAddAddonRow()">
                                <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Amenity Item', 'swimming-pool-manager' ); ?>
                            </button>
                        </div>

                        <table class="ifs-pms-repeater-table" id="ifsPmsAmenityAddonTable">
                            <thead>
                                <tr>
                                    <th class="ifs-pms-th-addon-name"><?php esc_html_e( 'Amenity Item Name', 'swimming-pool-manager' ); ?></th>
                                    <th class="ifs-pms-th-addon-price"><?php printf( esc_html__( 'Rental Fee (%s)', 'swimming-pool-manager' ), esc_html( $currency ) ); ?></th>
                                    <th class="ifs-pms-th-addon-action"><?php esc_html_e( 'Action', 'swimming-pool-manager' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $amenity_addons ) && is_array( $amenity_addons ) ) : ?>
                                    <?php foreach ( $amenity_addons as $addon ) : ?>
                                        <tr>
                                            <td><input type="text" name="ifs_addon_name[]" value="<?php echo esc_attr( $addon['name'] ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'Item name', 'swimming-pool-manager' ); ?>"></td>
                                            <td><input type="number" step="0.01" name="ifs_addon_price[]" value="<?php echo esc_attr( $addon['price'] ?? '' ); ?>" required class="ifs-pms-mono" placeholder="0.00"></td>
                                            <td class="ifs-pms-td-action">
                                                <button type="button" class="ifs-pms-btn ifs-pms-btn-danger ifs-pms-btn-sm" onclick="ifsPmsRemoveRow(this)">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr class="ifs-pms-empty-addon-row">
                                        <td colspan="3" class="ifs-pms-empty-table-notice">
                                            <?php esc_html_e( 'No gear rental items currently configured.', 'swimming-pool-manager' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 5. Payment Methods & POS Defaults -->
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php esc_html_e( 'Payment Methods & Checkout Configuration', 'swimming-pool-manager' ); ?>
                    </h3>
                </div>
                <div class="ifs-pms-form-stack">
                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label"><?php esc_html_e( 'Active Payment Methods on POS', 'swimming-pool-manager' ); ?></label>
                        <div class="ifs-pms-check-grid">
                            <?php
                            $available_tenders = array(
                                'Cash'          => __( 'Cash Tender', 'swimming-pool-manager' ),
                                'Room Guest'    => __( 'Hotel Room Guest', 'swimming-pool-manager' ),
                                'Complementary' => __( 'Complementary Pass', 'swimming-pool-manager' ),
                                'bKash / Nagad' => __( 'Mobile Banking (MFS)', 'swimming-pool-manager' ),
                                'Card POS'      => __( 'Card Terminal (POS)', 'swimming-pool-manager' ),
                            );
                            foreach ( $available_tenders as $tender_key => $tender_label ) :
                                $checked = in_array( $tender_key, $enabled_tenders, true ) ? 'checked' : '';
                            ?>
                                <div class="ifs-pms-switch-card ifs-pms-switch-card-tight">
                                    <div class="ifs-pms-switch-title-sm"><?php echo esc_html( $tender_label ); ?></div>
                                    <label class="ifs-pms-switch">
                                        <input type="checkbox" name="ifs_pms_enabled_tenders[]" value="<?php echo esc_attr( $tender_key ); ?>" <?php echo esc_attr( $checked ); ?>>
                                        <span class="ifs-pms-slider"></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ifs-pms-grid-2-compact">
                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsPmsQuickCashPresets"><?php esc_html_e( 'Quick Cash Buttons (Comma Separated)', 'swimming-pool-manager' ); ?></label>
                            <input type="text" name="ifs_pms_quick_cash_presets" id="ifsPmsQuickCashPresets" value="<?php echo $quick_cash_str; ?>" placeholder="<?php esc_attr_e( 'e.g. 500, 1000, 2000', 'swimming-pool-manager' ); ?>" class="ifs-pms-mono">
                        </div>
                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsPmsThermalPaperWidth"><?php esc_html_e( 'Thermal Printer Paper Size', 'swimming-pool-manager' ); ?></label>
                            <select name="ifs_pms_thermal_paper_width" id="ifsPmsThermalPaperWidth">
                                <option value="" <?php selected( $paper_width, '' ); ?>><?php esc_html_e( '-- Select Format --', 'swimming-pool-manager' ); ?></option>
                                <option value="80mm" <?php selected( $paper_width, '80mm' ); ?>><?php esc_html_e( '80mm Standard POS Slip', 'swimming-pool-manager' ); ?></option>
                                <option value="58mm" <?php selected( $paper_width, '58mm' ); ?>><?php esc_html_e( '58mm Mobile Bluetooth Slip', 'swimming-pool-manager' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <hr class="ifs-pms-separator">

                    <div class="ifs-pms-switch-stack">
                        <div class="ifs-pms-switch-card">
                            <div class="ifs-pms-switch-meta">
                                <div class="ifs-pms-switch-title"><?php esc_html_e( 'Strict Pass Verification', 'swimming-pool-manager' ); ?></div>
                                <div class="ifs-pms-switch-desc"><?php esc_html_e( 'Prevents double-entry by marking tickets used immediately upon scanning.', 'swimming-pool-manager' ); ?></div>
                            </div>
                            <label class="ifs-pms-switch">
                                <input type="checkbox" name="ifs_pms_enable_strict_scan" value="1" <?php checked( $enable_strict_scan, '1' ); ?>>
                                <span class="ifs-pms-slider"></span>
                            </label>
                        </div>

                        <div class="ifs-pms-switch-card">
                            <div class="ifs-pms-switch-meta">
                                <div class="ifs-pms-switch-title"><?php esc_html_e( 'Auto-Expire Day Passes at Midnight', 'swimming-pool-manager' ); ?></div>
                                <div class="ifs-pms-switch-desc"><?php esc_html_e( 'Automatically expires single-entry day passes at midnight.', 'swimming-pool-manager' ); ?></div>
                            </div>
                            <label class="ifs-pms-switch">
                                <input type="checkbox" name="ifs_pms_auto_expire_passes" value="1" <?php checked( $auto_expire_passes, '1' ); ?>>
                                <span class="ifs-pms-slider"></span>
                            </label>
                        </div>

                        <div class="ifs-pms-switch-card">
                            <div class="ifs-pms-switch-meta">
                                <div class="ifs-pms-switch-title"><?php esc_html_e( 'Turnstile & Scanner Sound Alerts', 'swimming-pool-manager' ); ?></div>
                                <div class="ifs-pms-switch-desc"><?php esc_html_e( 'Plays synthesized success and access-denied chime tones on scan.', 'swimming-pool-manager' ); ?></div>
                            </div>
                            <label class="ifs-pms-switch">
                                <input type="checkbox" name="ifs_pms_enable_audio_buzzer" value="1" <?php checked( $enable_audio_buzzer, '1' ); ?>>
                                <span class="ifs-pms-slider"></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary ifs-pms-btn-lg ifs-pms-btn-submit-block">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Save Operational Settings', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 2: Venue & Thermal Receipt Header -->
        <div id="ifsPmsSettingsPaneGeneral" class="ifs-pms-tab-pane <?php echo ( 'general' === $active_tab ) ? 'active' : ''; ?>">
            <div class="ifs-pms-panel-card">
                <div class="ifs-pms-panel-head">
                    <h3 class="ifs-pms-panel-title">
                        <span class="dashicons dashicons-admin-home"></span>
                        <?php esc_html_e( 'Venue Identity, Logo & Receipt Slip Header', 'swimming-pool-manager' ); ?>
                    </h3>
                </div>

                <div class="ifs-pms-form-stack">
                    <div class="ifs-pms-grid-2">
                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsPmsBusinessName"><?php esc_html_e( 'Venue / Property Name', 'swimming-pool-manager' ); ?> <span class="ifs-pms-required-mark">*</span></label>
                            <input type="text" name="ifs_pms_business_name" id="ifsPmsBusinessName" value="<?php echo esc_attr( $b_name ); ?>" required placeholder="<?php esc_attr_e( 'e.g. Ozone Restaurant & Skypool', 'swimming-pool-manager' ); ?>">
                        </div>
                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsPmsCurrency"><?php esc_html_e( 'Currency Symbol / Code', 'swimming-pool-manager' ); ?> <span class="ifs-pms-required-mark">*</span></label>
                            <input type="text" name="ifs_pms_currency" id="ifsPmsCurrency" value="<?php echo esc_attr( $currency ); ?>" required class="ifs-pms-mono" placeholder="<?php esc_attr_e( 'e.g. BDT or $', 'swimming-pool-manager' ); ?>">
                        </div>
                    </div>

                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label" for="ifsPmsLogoUrl"><?php esc_html_e( 'Venue Logo URL', 'swimming-pool-manager' ); ?></label>
                        <div class="ifs-pms-logo-upload-group">
                            <input type="url" name="ifs_pms_logo_url" id="ifsPmsLogoUrl" value="<?php echo esc_url( get_option( 'ifs_pms_logo_url', '' ) ); ?>" placeholder="https://example.com/logo.png">
                            <button type="button" class="ifs-pms-btn ifs-pms-btn-secondary" onclick="ifsPmsOpenMediaUploader()"><?php esc_html_e( 'Upload', 'swimming-pool-manager' ); ?></button>
                        </div>
                        <?php if ( ! empty( $logo_url ) ) : ?>
                            <div class="ifs-pms-logo-preview-box">
                                <img src="<?php echo esc_url( get_option( 'ifs_pms_logo_url', '' ) ); ?>" alt="<?php esc_attr_e( 'Logo Preview', 'swimming-pool-manager' ); ?>" class="ifs-pms-logo-img">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ifs-pms-grid-2">
                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsPmsPhone"><?php esc_html_e( 'Front Desk Phone Number', 'swimming-pool-manager' ); ?></label>
                            <input type="text" name="ifs_pms_phone" id="ifsPmsPhone" value="<?php echo esc_attr( $phone ); ?>" placeholder="<?php esc_attr_e( 'e.g. +880 1700-000000', 'swimming-pool-manager' ); ?>">
                        </div>
                        <div class="ifs-pms-field-group">
                            <label class="ifs-pms-field-label" for="ifsPmsCapacity"><?php esc_html_e( 'Rooftop Guest Capacity Limit', 'swimming-pool-manager' ); ?></label>
                            <input type="number" name="ifs_pms_max_capacity" id="ifsPmsCapacity" value="<?php echo esc_attr( (string) $capacity ); ?>" placeholder="<?php esc_attr_e( 'Maximum head count', 'swimming-pool-manager' ); ?>">
                        </div>
                    </div>

                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label" for="ifsPmsAddress"><?php esc_html_e( 'Physical Address (Printed on Slip)', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="ifs_pms_address" id="ifsPmsAddress" value="<?php echo esc_attr( $address ); ?>" placeholder="<?php esc_attr_e( 'Full address line', 'swimming-pool-manager' ); ?>">
                    </div>

                    <div class="ifs-pms-field-group">
                        <label class="ifs-pms-field-label" for="ifsPmsReceiptNote"><?php esc_html_e( 'Thermal Receipt Footer Terms & Safety Policy', 'swimming-pool-manager' ); ?></label>
                        <textarea name="ifs_pms_receipt_note" id="ifsPmsReceiptNote" rows="3" placeholder="<?php esc_attr_e( 'Add thermal slip policy statements...', 'swimming-pool-manager' ); ?>"><?php echo esc_textarea( get_option( 'ifs_pms_receipt_note', '' ) ); ?></textarea>
                    </div>

                    <button type="submit" class="ifs-pms-btn ifs-pms-btn-primary ifs-pms-btn-lg ifs-pms-btn-submit-block">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Save Venue Configuration', 'swimming-pool-manager' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>