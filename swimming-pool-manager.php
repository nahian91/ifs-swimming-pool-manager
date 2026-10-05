<?php 
/** 
 * Plugin Name:       IFS Swimming Pool Manager (IFS Aquatic OS)
 * Plugin URI:        https://ifstechnologies.com/swimming-pool-manager
 * Description:       Enterprise Aquatic POS, Turnstile QR Gate Control, RFID/Pass Ledger, Pool Health Telemetry, Hardware Relay & Multi-Desk Financial Operating System with RBAC and Native SVG UI.
 * Version:           7.5.0 
 * Author:            IFS Technologies 
 * Author URI:        https://ifstechnologies.com 
 * License:           GPL-2.0-or-later 
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html 
 * Text Domain:       swimming-pool-manager 
 * Domain Path:       /languages 
 * Requires at least: 6.0 
 * Requires PHP:      7.4 
 * 
 * @package SwimmingPoolManager 
 */ 

if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
} 

define( 'IFS_PMS_VERSION', '7.5.0' ); 
define( 'IFS_PMS_PATH', plugin_dir_path( __FILE__ ) ); 
define( 'IFS_PMS_URL', plugin_dir_url( __FILE__ ) ); 

/** 
 * Viewport enforcement for responsive admin shells.
 */
add_action( 'admin_head', 'ifs_pms_ensure_admin_viewport_meta' );
function ifs_pms_ensure_admin_viewport_meta() {
    $hook = $_GET['page'] ?? '';
    if ( false !== strpos( $hook, 'ifs-pms' ) ) {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">';
    }
}

/** 
 * Load plugin textdomain. 
 */ 
add_action( 'plugins_loaded', 'ifs_pms_load_textdomain' ); 
function ifs_pms_load_textdomain() { 
    load_plugin_textdomain( 'swimming-pool-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
} 

/** 
 * Native SVG Icon Helper Engine (Zero External Dependencies) 
 */ 
function ifs_pms_get_svg( $icon_name, $class = '', $size = 16 ) { 
    $icons = array( 
        'overview'   => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>', 
        'ticket'     => '<path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-3-3c-1.11 0-2.08.6-2.58 1.5C11.92 2.6 10.95 2 9.84 2a2.996 2.996 0 0 0-3 1c0 .35.07.69.18 1H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-1 12H5V8h14v10z"/>', 
        'qrcode'     => '<path d="M2 2h8v8H2V2zm2 4h4V4H4v2zm8-4h8v8h-8V2zm2 4h4V4h-4v2zM2 12h8v8H2v-8zm2 4h4v-4H4v4zm10-4h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 0h2v2h-2v-2z"/>', 
        'tower'      => '<path d="M12 2L2 7v2h20V7L12 2zm0 3.25L18.5 8H5.5L12 5.25zM6 10v10h2V10H6zm4 0v10h4V10h-4zm6 0v10h2V10h-2zm-8 12h8v2H8v-2z"/>', 
        'users'      => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>', 
        'card'       => '<path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>', 
        'shield'     => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>', 
        'receipt'    => '<path d="M18 17h-6v-2h6v2zm0-4h-6v-2h6v2zm0-4h-6V7h6v2zM16 2H6c-1.1 0-2 .9-2 2v16l3-1.5L10 20l3-1.5L16 20V4c0-1.1-.9-2-2-2zm0 15.5l-3-1.5-3 1.5V4h6v13.5z"/>', 
        'sliders'    => '<path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4h-8v2h8v-2zm0-6h-4V5h-2v6h6V7z"/>', 
        'clock'      => '<path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>', 
        'print'      => '<path d="M19 8h-1V3H6v5H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zM8 5h8v3H8V5zm8 14H8v-4h8v4zm4-4h-2v-2H6v2H4v-4c0-.55.45-1 1-1h14c.55 0 1 .45 1 1v4z"/>', 
        'check'      => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>', 
        'water'      => '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>', 
        'circuit'    => '<path d="M4 6h4v4H4zm12 0h4v4h-4zm-6 7h4v4h-4zm-6 2h4v4H4zm12 0h4v4h-4z"/>', 
        'audit'      => '<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>', 
    ); 

    $path       = $icons[ $icon_name ] ?? '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>'; 
    $class_attr = ! empty( $class ) ? ' class="' . esc_attr( $class ) . '"' : ''; 

    return sprintf( 
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="%d" height="%d" fill="currentColor"%s>%s</svg>', 
        intval( $size ), 
        intval( $size ), 
        $class_attr, 
        $path 
    ); 
} 

/** 
 * 1. Database Schema Installation & Dynamic Upgrades 
 */ 
register_activation_hook( __FILE__, 'ifs_pms_install' ); 

function ifs_pms_install() { 
    global $wpdb; 
    require_once ABSPATH . 'wp-admin/includes/upgrade.php'; 

    $charset_collate = $wpdb->get_charset_collate(); 

    $t_cust     = $wpdb->prefix . 'ifs_pms_customers'; 
    $t_tick     = $wpdb->prefix . 'ifs_pms_tickets'; 
    $t_members  = $wpdb->prefix . 'ifs_pms_memberships'; 
    $t_expense  = $wpdb->prefix . 'ifs_pms_expenses'; 
    $t_salary   = $wpdb->prefix . 'ifs_pms_salaries'; 
    $t_water    = $wpdb->prefix . 'ifs_pms_water_logs'; 
    $t_audit    = $wpdb->prefix . 'ifs_pms_audit_trail'; 

    $sql = "CREATE TABLE {$t_cust} ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        name varchar(100) NOT NULL, 
        phone varchar(20) NOT NULL, 
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 
        PRIMARY KEY  (id), 
        UNIQUE KEY phone (phone) 
    ) {$charset_collate}; 

    CREATE TABLE {$t_tick} ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        ticket_code varchar(50) NOT NULL, 
        customer_id mediumint(9) NOT NULL, 
        guest_type varchar(20) DEFAULT 'customer' NOT NULL, 
        package_details text DEFAULT NULL, 
        duration_hours int(3) DEFAULT 1 NOT NULL, 
        payment_method varchar(30) DEFAULT 'Cash' NOT NULL, 
        room_no varchar(20) DEFAULT NULL, 
        amount decimal(10,2) NOT NULL DEFAULT 0.00, 
        sold_by varchar(100) NOT NULL, 
        scanned_by varchar(100) DEFAULT NULL, 
        status varchar(20) DEFAULT 'Valid' NOT NULL, 
        sold_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 
        scanned_at datetime NULL, 
        PRIMARY KEY  (id), 
        UNIQUE KEY ticket_code (ticket_code), 
        KEY idx_customer (customer_id), 
        KEY idx_guest_type (guest_type), 
        KEY idx_payment (payment_method), 
        KEY idx_status (status), 
        KEY idx_sold_at (sold_at), 
        KEY idx_scanned_at (scanned_at) 
    ) {$charset_collate}; 

    CREATE TABLE {$t_members} ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        member_code varchar(50) NOT NULL, 
        name varchar(100) NOT NULL, 
        phone varchar(20) NOT NULL, 
        plan_type varchar(50) NOT NULL, 
        amount decimal(10,2) NOT NULL DEFAULT 0.00, 
        start_date date NOT NULL, 
        expiry_date date NOT NULL, 
        status varchar(20) DEFAULT 'Active' NOT NULL, 
        profile_image varchar(255) DEFAULT NULL, 
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 
        PRIMARY KEY  (id), 
        UNIQUE KEY member_code (member_code), 
        KEY idx_phone (phone), 
        KEY idx_expiry (expiry_date), 
        KEY idx_status (status) 
    ) {$charset_collate}; 

    CREATE TABLE {$t_expense} ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        title varchar(200) NOT NULL, 
        category varchar(100) NOT NULL, 
        amount decimal(10,2) NOT NULL DEFAULT 0.00, 
        added_by varchar(100) NOT NULL, 
        expense_date date NOT NULL, 
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 
        PRIMARY KEY  (id), 
        KEY idx_expense_date (expense_date), 
        KEY idx_category (category) 
    ) {$charset_collate}; 

    CREATE TABLE {$t_salary} ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        user_id bigint(20) NOT NULL, 
        base_salary decimal(10,2) NOT NULL DEFAULT 0.00, 
        allowance decimal(10,2) NOT NULL DEFAULT 0.00, 
        pay_frequency varchar(20) DEFAULT 'Monthly' NOT NULL, 
        effective_date date NOT NULL, 
        status varchar(20) DEFAULT 'Active' NOT NULL, 
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 
        PRIMARY KEY  (id), 
        KEY idx_user (user_id) 
    ) {$charset_collate};

    CREATE TABLE {$t_water} ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        ph_level decimal(3,2) NOT NULL, 
        chlorine_ppm decimal(4,2) NOT NULL, 
        water_temp decimal(4,1) DEFAULT NULL, 
        clarity varchar(50) DEFAULT 'Clear' NOT NULL, 
        logged_by varchar(100) NOT NULL, 
        logged_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 
        notes text DEFAULT NULL, 
        PRIMARY KEY  (id), 
        KEY idx_logged_at (logged_at) 
    ) {$charset_collate};

    CREATE TABLE {$t_audit} ( 
        id bigint(20) NOT NULL AUTO_INCREMENT, 
        user_id bigint(20) NOT NULL, 
        user_name varchar(100) NOT NULL, 
        action_type varchar(50) NOT NULL, 
        item_ref varchar(100) DEFAULT NULL, 
        details text DEFAULT NULL, 
        ip_address varchar(45) NOT NULL, 
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 
        PRIMARY KEY  (id), 
        KEY idx_action (action_type), 
        KEY idx_timestamp (timestamp) 
    ) {$charset_collate};"; 

    dbDelta( $sql ); 

    $default_options = array( 
        'ifs_pms_business_name'       => '', 
        'ifs_pms_ticket_price'        => '0.00', 
        'ifs_pms_currency'            => 'BDT', 
        'ifs_pms_phone'               => '', 
        'ifs_pms_address'             => '', 
        'ifs_pms_receipt_note'        => '', 
        'ifs_pms_max_capacity'        => '50', 
        'ifs_pms_logo_url'            => '', 
        'ifs_pms_pool_status'         => 'Open', 
        'ifs_pms_relay_ip'            => '', 
        'ifs_pms_relay_port'          => '80', 
        'ifs_pms_relay_trigger_sec'   => '3', 
        'ifs_pms_pricing_tiers'       => array(), 
        'ifs_pms_amenity_addons'      => array(), 
        'ifs_pms_weekly_schedule'     => array(), 
        'ifs_pms_enabled_tenders'     => array( 'Cash' ), 
        'ifs_pms_quick_cash_presets'  => '100, 200, 500, 1000', 
        'ifs_pms_thermal_paper_width' => '80mm', 
        'ifs_pms_enable_strict_scan'  => '1', 
        'ifs_pms_auto_expire_passes'  => '1', 
        'ifs_pms_enable_audio_buzzer' => '1', 
        'ifs_pms_version'             => IFS_PMS_VERSION, 
    ); 

    foreach ( $default_options as $key => $val ) { 
        if ( false === get_option( $key ) ) { 
            add_option( $key, $val ); 
        } 
    } 
} 

/** 
 * Dynamic Migration Runner 
 */ 
add_action( 'admin_init', 'ifs_pms_maybe_upgrade_db' ); 

function ifs_pms_maybe_upgrade_db() { 
    $installed_ver = get_option( 'ifs_pms_version', '0.0.0' ); 
    if ( version_compare( $installed_ver, IFS_PMS_VERSION, '<' ) ) { 
        ifs_pms_install(); 
        update_option( 'ifs_pms_version', IFS_PMS_VERSION ); 
    } 
} 

/** 
 * Security Audit Logger Function 
 */ 
function ifs_pms_record_audit( $action_type, $item_ref = '', $details = '' ) { 
    global $wpdb; 
    $user = wp_get_current_user(); 
    $wpdb->insert( 
        $wpdb->prefix . 'ifs_pms_audit_trail', 
        array( 
            'user_id'     => $user->ID ?? 0, 
            'user_name'   => $user->display_name ?? 'System Event', 
            'action_type' => sanitize_key( $action_type ), 
            'item_ref'    => sanitize_text_field( $item_ref ), 
            'details'     => sanitize_textarea_field( is_array( $details ) ? wp_json_encode( $details ) : $details ), 
            'ip_address'  => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ), 
            'timestamp'   => current_time( 'mysql' ), 
        ), 
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) 
    ); 
} 

/** 
 * 2. RBAC Registration & Capability Synchronization 
 */ 
add_action( 'admin_init', 'ifs_pms_ensure_capabilities' ); 

function ifs_pms_ensure_capabilities() { 
    $admin_role = get_role( 'administrator' ); 
    if ( $admin_role ) { 
        $caps = array( 
            'ifs_access_terminal', 
            'ifs_sell_tickets', 
            'ifs_scan_passes', 
            'ifs_manage_patrons', 
            'ifs_view_history', 
            'ifs_view_finances', 
            'ifs_manage_expenses', 
            'ifs_manage_settings', 
        ); 
        foreach ( $caps as $cap ) { 
            if ( ! $admin_role->has_cap( $cap ) ) { 
                $admin_role->add_cap( $cap ); 
            } 
        } 
    } 

    if ( ! get_role( 'ifs_cashier' ) ) { 
        add_role( 
            'ifs_cashier', 
            __( 'IFS Cashier', 'swimming-pool-manager' ), 
            array( 
                'read'                => true, 
                'ifs_access_terminal' => true, 
                'ifs_sell_tickets'    => true, 
                'ifs_scan_passes'     => true, 
                'ifs_manage_patrons'  => true, 
                'ifs_view_history'    => true, 
            ) 
        ); 
    } 
} 

/** 
 * 3. Menu Registration (Complete 16-Module Architecture)
 */ 
add_action( 'admin_menu', 'ifs_pms_register_menu' ); 

function ifs_pms_register_menu() { 
    $cap_terminal = 'ifs_access_terminal'; 
    $cap_sales    = 'ifs_sell_tickets'; 
    $cap_patrons  = 'ifs_manage_patrons'; 
    $cap_finances = 'ifs_view_finances'; 
    $cap_expenses = 'ifs_manage_expenses'; 
    $cap_admin    = current_user_can( 'manage_options' ) ? 'manage_options' : 'ifs_manage_settings'; 

    add_menu_page( 
        __( 'IFS Pool Manager', 'swimming-pool-manager' ), 
        __( 'IFS Pool Manager', 'swimming-pool-manager' ), 
        $cap_terminal, 
        'ifs-pms', 
        'ifs_pms_render_application', 
        'dashicons-cloud', 
        2 
    ); 

    // Front Desk Operations
    add_submenu_page( 'ifs-pms', __( 'Point of Sale & Passes', 'swimming-pool-manager' ), __( 'Ticketing POS', 'swimming-pool-manager' ), $cap_sales, 'ifs-pms', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Turnstile QR Scanner', 'swimming-pool-manager' ), __( 'Access Gate', 'swimming-pool-manager' ), $cap_sales, 'ifs-pms-scanner', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Real-Time Pool Telemetry', 'swimming-pool-manager' ), __( 'Floor Monitor', 'swimming-pool-manager' ), $cap_terminal, 'ifs-pms-live-status', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Facility & Lane Reservations', 'swimming-pool-manager' ), __( 'Reservations', 'swimming-pool-manager' ), $cap_sales, 'ifs-pms-bookings', 'ifs_pms_render_application' ); 

    // Guest & Facility Services
    add_submenu_page( 'ifs-pms', __( 'Patron & Guest Profiles', 'swimming-pool-manager' ), __( 'Guest Profiles', 'swimming-pool-manager' ), $cap_patrons, 'ifs-pms-customers', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Membership Subscription Ledger', 'swimming-pool-manager' ), __( 'Memberships', 'swimming-pool-manager' ), $cap_patrons, 'ifs-pms-membership', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Swimwear & Concessions POS', 'swimming-pool-manager' ), __( 'Retail & Inventory', 'swimming-pool-manager' ), $cap_sales, 'ifs-pms-shop', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Key Band & Towel Deposit Tracking', 'swimming-pool-manager' ), __( 'Locker & Asset Hub', 'swimming-pool-manager' ), $cap_terminal, 'ifs-pms-lockers', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Water Quality & Chemical Logs', 'swimming-pool-manager' ), __( 'Water Quality', 'swimming-pool-manager' ), $cap_terminal, 'ifs-pms-water-log', 'ifs_pms_render_application' ); 

    // Finance & Governance
    add_submenu_page( 'ifs-pms', __( 'Shift Closing & Cash Drawer', 'swimming-pool-manager' ), __( 'Cash Reconciliation', 'swimming-pool-manager' ), $cap_sales, 'ifs-pms-dayclose', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Operational Cost Outflows', 'swimming-pool-manager' ), __( 'Operating Expenses', 'swimming-pool-manager' ), $cap_expenses, 'ifs-pms-expenses', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Operator Compensation & Roles', 'swimming-pool-manager' ), __( 'Staff & Payroll', 'swimming-pool-manager' ), $cap_admin, 'ifs-pms-staff', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Revenue & Attendance Analytics', 'swimming-pool-manager' ), __( 'Business Analytics', 'swimming-pool-manager' ), $cap_finances, 'ifs-pms-reports', 'ifs_pms_render_application' ); 

    // System Administration & Infrastructure
    add_submenu_page( 'ifs-pms', __( 'Turnstiles & IoT Gate Relays', 'swimming-pool-manager' ), __( 'Hardware Hub', 'swimming-pool-manager' ), $cap_admin, 'ifs-pms-hardware', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Security Audit & Activity Logs', 'swimming-pool-manager' ), __( 'Audit Trail', 'swimming-pool-manager' ), $cap_admin, 'ifs-pms-audit-logs', 'ifs_pms_render_application' ); 
    add_submenu_page( 'ifs-pms', __( 'Operating Rules & Tariffs', 'swimming-pool-manager' ), __( 'Facility Config', 'swimming-pool-manager' ), $cap_admin, 'ifs-pms-settings', 'ifs_pms_render_application' ); 
} 

/** 
 * 4. Admin Asset Queue & Script Localization 
 */ 
add_action( 'admin_enqueue_scripts', 'ifs_pms_enqueue_assets' ); 

function ifs_pms_enqueue_assets( $hook ) { 
    if ( false === strpos( $hook, 'ifs-pms' ) ) { 
        return; 
    } 

    wp_enqueue_media(); 

    wp_enqueue_style( 'ifs-pms-admin-css', IFS_PMS_URL . 'assets/css/style.css', array(), IFS_PMS_VERSION ); 
    wp_enqueue_script( 'ifs-pms-admin-js', IFS_PMS_URL . 'assets/js/main.js', array( 'jquery' ), IFS_PMS_VERSION, true ); 

    $transient_key = 'ifs_pms_last_ticket_' . get_current_user_id(); 
    $last_ticket   = get_transient( $transient_key ); 
    if ( $last_ticket ) { 
        delete_transient( $transient_key ); 
    } 

    $token = wp_create_nonce( 'ifs_pms_security_token' ); 

    wp_localize_script( 
        'ifs-pms-admin-js', 
        'ifsPmsConfig', 
        array( 
            'ajax_url'           => admin_url( 'admin-ajax.php' ), 
            'security_token'     => $token, 
            'nonce'              => $token, 
            'currency'           => esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) ), 
            'current_mon_prefix' => 'IFS-' . strtoupper( current_time( 'M' ) ) . '-' . current_time( 'd' ) . '-', 
            'current_operator'   => wp_get_current_user()->display_name, 
            'last_ticket'        => $last_ticket ? $last_ticket : null, 
        ) 
    ); 
} 

/** 
 * 5. PRG Form Post Handlers (Full Complete CRUD with Tickets, Staff, Expenses, Settings) 
 */ 
add_action( 'admin_init', 'ifs_pms_handle_form_submissions' ); 

function ifs_pms_handle_form_submissions() { 
    if ( ! isset( $_POST['ifs_pms_action'] ) ) { 
        return; 
    } 

    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); 

    global $wpdb; 
    $t_cust    = $wpdb->prefix . 'ifs_pms_customers'; 
    $t_tick    = $wpdb->prefix . 'ifs_pms_tickets'; 
    $t_members = $wpdb->prefix . 'ifs_pms_memberships'; 
    $t_expense = $wpdb->prefix . 'ifs_pms_expenses'; 
    $t_salary  = $wpdb->prefix . 'ifs_pms_salaries'; 

    $action = isset( $_POST['ifs_pms_action'] ) ? sanitize_key( wp_unslash( $_POST['ifs_pms_action'] ) ) : ''; 
    $base   = admin_url( 'admin.php?page=ifs-pms' ); 

    // --- ISSUE TICKET --- 
    if ( 'issue_ticket' === $action ) { 
        if ( ! current_user_can( 'ifs_sell_tickets' ) && ! current_user_can( 'manage_options' ) ) { 
            wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'swimming-pool-manager' ), 403 ); 
        } 

        $name           = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; 
        $phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : ''; 
        $guest_type     = isset( $_POST['guest_type'] ) ? sanitize_key( wp_unslash( $_POST['guest_type'] ) ) : 'customer'; 
        $package_name   = isset( $_POST['package_name'] ) ? sanitize_text_field( wp_unslash( $_POST['package_name'] ) ) : ''; 
        $duration_hours = isset( $_POST['duration_hours'] ) ? max( 1, absint( wp_unslash( $_POST['duration_hours'] ) ) ) : 1; 
        $payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'Cash'; 
        $room_no        = isset( $_POST['room_no'] ) ? sanitize_text_field( wp_unslash( $_POST['room_no'] ) ) : ''; 

        $is_free = ( 'room_guest' === $guest_type || 'Room Guest' === $payment_method || 'Complementary' === $payment_method ); 
        $amount  = $is_free ? 0.00 : ( isset( $_POST['amount'] ) ? max( 0.00, floatval( wp_unslash( $_POST['amount'] ) ) ) : 0.00 ); 
        $staff   = wp_get_current_user()->display_name; 

        if ( ! empty( $name ) && ! empty( $phone ) ) { 
            $customer = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$t_cust} WHERE phone = %s", $phone ) ); 
            if ( ! $customer ) { 
                $wpdb->insert( $t_cust, array( 'name' => $name, 'phone' => $phone ), array( '%s', '%s' ) ); 
                $cust_id = $wpdb->insert_id; 
            } else { 
                $cust_id = $customer->id; 
                $wpdb->update( $t_cust, array( 'name' => $name ), array( 'id' => $cust_id ), array( '%s' ), array( '%d' ) ); 
            } 

            $today_start = current_time( 'Y-m-d 00:00:00' ); 
            $today_end   = current_time( 'Y-m-d 23:59:59' ); 
            $today_count = (int) $wpdb->get_var( 
                $wpdb->prepare( 
                    "SELECT COUNT(id) FROM {$t_tick} WHERE sold_at >= %s AND sold_at <= %s", 
                    $today_start, 
                    $today_end 
                ) 
            ); 

            $code = 'IFS-' . strtoupper( current_time( 'M' ) ) . '-' . current_time( 'd' ) . '-' . str_pad( (string) ( $today_count + 1 ), 4, '0', STR_PAD_LEFT ); 
            $now_mysql = current_time( 'mysql' ); 

            $inserted = $wpdb->insert( 
                $t_tick, 
                array( 
                    'ticket_code'     => $code, 
                    'customer_id'     => $cust_id, 
                    'guest_type'      => $guest_type, 
                    'package_details' => $package_name, 
                    'duration_hours'  => $duration_hours, 
                    'payment_method'  => $payment_method, 
                    'room_no'         => $room_no, 
                    'amount'          => $amount, 
                    'sold_by'         => $staff, 
                    'status'          => 'Valid', 
                    'sold_at'         => $now_mysql, 
                ), 
                array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s' ) 
            ); 

            if ( $inserted ) { 
                $check_out_time = gmdate( 'Y-m-d H:i:s', strtotime( "+{$duration_hours} hours", strtotime( $now_mysql ) ) ); 
                ifs_pms_record_audit( 'issue_ticket', $code, "Amount: {$amount}, Tender: {$payment_method}" ); 

                set_transient( 
                    'ifs_pms_last_ticket_' . get_current_user_id(), 
                    array( 
                        'code'           => $code, 
                        'name'           => $name, 
                        'phone'          => $phone, 
                        'guest_type'     => $guest_type, 
                        'package'        => $package_name, 
                        'duration_hours' => $duration_hours, 
                        'payment_method' => $payment_method, 
                        'room'           => $room_no, 
                        'amount'         => number_format( $amount, 2 ), 
                        'staff'          => $staff, 
                        'check_in'       => $now_mysql, 
                        'check_out'      => $check_out_time, 
                    ), 
                    120 
                ); 

                wp_safe_redirect( add_query_arg( array( 'view' => 'tickets', 'msg' => 'ticket_created', 'tab' => 'add' ), $base ) ); 
                exit; 
            } 
        } 
    } 

    // --- EDIT TICKET --- 
    if ( 'edit_ticket' === $action ) { 
        if ( ! current_user_can( 'ifs_sell_tickets' ) && ! current_user_can( 'manage_options' ) ) { 
            wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'swimming-pool-manager' ), 403 ); 
        } 

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( wp_unslash( $_POST['ticket_id'] ) ) : 0; 
        $name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; 
        $phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : ''; 
        $room_no   = isset( $_POST['room_no'] ) ? sanitize_text_field( wp_unslash( $_POST['room_no'] ) ) : ''; 
        $amount    = isset( $_POST['amount'] ) ? max( 0.00, floatval( wp_unslash( $_POST['amount'] ) ) ) : 0.00; 

        $requested_status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'Valid'; 
        $allowed_statuses = array( 'Valid', 'Used', 'Completed', 'Cancelled' ); 
        $status           = in_array( $requested_status, $allowed_statuses, true ) ? $requested_status : 'Valid'; 

        if ( $ticket_id > 0 && ! empty( $name ) && ! empty( $phone ) ) { 
            $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT customer_id, ticket_code FROM {$t_tick} WHERE id = %d", $ticket_id ) ); 
            if ( $ticket ) { 
                $wpdb->update( $t_cust, array( 'name' => $name, 'phone' => $phone ), array( 'id' => $ticket->customer_id ), array( '%s', '%s' ), array( '%d' ) ); 
                $wpdb->update( 
                    $t_tick, 
                    array( 
                        'room_no' => $room_no, 
                        'amount'  => $amount, 
                        'status'  => $status, 
                    ), 
                    array( 'id' => $ticket_id ), 
                    array( '%s', '%f', '%s' ), 
                    array( '%d' ) 
                ); 
                ifs_pms_record_audit( 'edit_ticket', $ticket->ticket_code, "Updated status: {$status}, Amount: {$amount}" ); 
            } 
            wp_safe_redirect( add_query_arg( array( 'view' => 'tickets', 'msg' => 'ticket_updated', 'tab' => 'list' ), $base ) ); 
            exit; 
        } 
    } 

    // --- DELETE TICKET --- 
    if ( 'delete_ticket' === $action ) { 
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ifs_manage_settings' ) ) { 
            wp_die( esc_html__( 'Forbidden: Only managers can delete ticket records.', 'swimming-pool-manager' ), 403 ); 
        } 

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( wp_unslash( $_POST['ticket_id'] ) ) : 0; 
        if ( $ticket_id > 0 ) { 
            $ticket_code = $wpdb->get_var( $wpdb->prepare( "SELECT ticket_code FROM {$t_tick} WHERE id = %d", $ticket_id ) ); 
            $wpdb->delete( $t_tick, array( 'id' => $ticket_id ), array( '%d' ) ); 
            ifs_pms_record_audit( 'delete_ticket', $ticket_code, "Deleted ticket permanently" ); 
            wp_safe_redirect( add_query_arg( array( 'view' => 'tickets', 'msg' => 'ticket_deleted', 'tab' => 'list' ), $base ) ); 
            exit; 
        } 
    } 

    // --- STAFF CRUD --- 
    if ( 'create_staff' === $action ) { 
        if ( ! current_user_can( 'manage_options' ) ) { 
            wp_die( esc_html__( 'Forbidden: Administrator privileges required.', 'swimming-pool-manager' ), 403 ); 
        } 

        $username = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : ''; 
        $email    = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : ''; 
        $password = isset( $_POST['user_pass'] ) ? trim( (string) wp_unslash( $_POST['user_pass'] ) ) : ''; 
        $fullname = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : ''; 

        $requested_role = isset( $_POST['user_role'] ) ? sanitize_key( wp_unslash( $_POST['user_role'] ) ) : 'ifs_cashier'; 
        $allowed_roles  = array( 'ifs_cashier', 'administrator' ); 
        $role           = in_array( $requested_role, $allowed_roles, true ) ? $requested_role : 'ifs_cashier'; 

        $base_sal = isset( $_POST['base_salary'] ) ? floatval( wp_unslash( $_POST['base_salary'] ) ) : 0.00; 
        $allow    = isset( $_POST['allowance'] ) ? floatval( wp_unslash( $_POST['allowance'] ) ) : 0.00; 
        $freq     = isset( $_POST['pay_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['pay_frequency'] ) ) : 'Monthly'; 
        $eff_date = isset( $_POST['effective_date'] ) ? sanitize_text_field( wp_unslash( $_POST['effective_date'] ) ) : current_time( 'Y-m-d' ); 

        if ( ! empty( $username ) && is_email( $email ) && ! empty( $password ) ) { 
            $user_id = wp_create_user( $username, $password, $email ); 
            if ( ! is_wp_error( $user_id ) ) { 
                wp_update_user( array( 
                    'ID'           => $user_id, 
                    'display_name' => $fullname ? $fullname : $username, 
                    'role'         => $role, 
                ) ); 

                $wpdb->insert( 
                    $t_salary, 
                    array( 
                        'user_id'        => $user_id, 
                        'base_salary'    => $base_sal, 
                        'allowance'      => $allow, 
                        'pay_frequency'  => $freq, 
                        'effective_date' => $eff_date, 
                        'status'         => 'Active', 
                    ), 
                    array( '%d', '%f', '%f', '%s', '%s', '%s' ) 
                ); 

                ifs_pms_record_audit( 'create_staff', $username, "Provisioned ID {$user_id} with role {$role}" ); 
                wp_safe_redirect( add_query_arg( array( 'view' => 'staff', 'msg' => 'staff_created', 'tab' => 'list' ), $base ) ); 
                exit; 
            } 
        } 
        wp_safe_redirect( add_query_arg( array( 'view' => 'staff', 'msg' => 'error', 'tab' => 'add' ), $base ) ); 
        exit; 
    } 

    // --- EXPENSES CRUD --- 
    if ( 'add_expense' === $action ) { 
        if ( ! current_user_can( 'ifs_manage_expenses' ) && ! current_user_can( 'manage_options' ) ) { 
            wp_die( esc_html__( 'Forbidden: Insufficient permissions.', 'swimming-pool-manager' ), 403 ); 
        } 

        $wpdb->insert( 
            $t_expense, 
            array( 
                'title'        => isset( $_POST['expense_title'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_title'] ) ) : '', 
                'category'     => isset( $_POST['expense_cat'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_cat'] ) ) : 'Operations', 
                'amount'       => isset( $_POST['expense_amount'] ) ? max( 0.00, floatval( wp_unslash( $_POST['expense_amount'] ) ) ) : 0.00, 
                'expense_date' => isset( $_POST['expense_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_date'] ) ) : current_time( 'Y-m-d' ), 
                'added_by'     => wp_get_current_user()->display_name, 
            ), 
            array( '%s', '%s', '%f', '%s', '%s' ) 
        ); 

        ifs_pms_record_audit( 'add_expense', $_POST['expense_title'] ?? '', "Outflow: " . ( $_POST['expense_amount'] ?? '0.00' ) ); 
        wp_safe_redirect( add_query_arg( array( 'view' => 'expenses', 'msg' => 'expense_logged', 'tab' => 'list' ), $base ) ); 
        exit; 
    } 

    // --- MASTER CONFIG CRUD --- 
    if ( 'save_settings' === $action ) { 
        if ( ! current_user_can( 'ifs_manage_settings' ) && ! current_user_can( 'manage_options' ) ) { 
            wp_die( esc_html__( 'Forbidden: Admin authorization required.', 'swimming-pool-manager' ), 403 ); 
        } 

        $active_tab = isset( $_POST['ifs_pms_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['ifs_pms_active_tab'] ) ) : 'pos'; 

        update_option( 'ifs_pms_business_name', sanitize_text_field( wp_unslash( $_POST['ifs_pms_business_name'] ?? '' ) ) ); 
        update_option( 'ifs_pms_currency', sanitize_text_field( wp_unslash( $_POST['ifs_pms_currency'] ?? 'BDT' ) ) ); 
        update_option( 'ifs_pms_phone', sanitize_text_field( wp_unslash( $_POST['ifs_pms_phone'] ?? '' ) ) ); 
        update_option( 'ifs_pms_address', sanitize_text_field( wp_unslash( $_POST['ifs_pms_address'] ?? '' ) ) ); 
        update_option( 'ifs_pms_relay_ip', sanitize_text_field( wp_unslash( $_POST['ifs_pms_relay_ip'] ?? '' ) ) ); 
        update_option( 'ifs_pms_relay_port', sanitize_text_field( wp_unslash( $_POST['ifs_pms_relay_port'] ?? '80' ) ) ); 
        update_option( 'ifs_pms_relay_trigger_sec', absint( wp_unslash( $_POST['ifs_pms_relay_trigger_sec'] ?? 3 ) ) ); 

        ifs_pms_record_audit( 'save_settings', 'MasterConfig', 'Updated global tariffs and relay parameters' ); 
        wp_safe_redirect( add_query_arg( array( 'view' => 'settings', 'msg' => 'settings_saved', 'tab' => $active_tab ), $base ) ); 
        exit; 
    } 
} 

/** 
 * Root Visitor Redirection 
 */ 
add_action( 'template_redirect', 'ifs_pms_home_redirect_to_admin' ); 
function ifs_pms_home_redirect_to_admin() { 
    if ( is_front_page() || is_home() ) { 
        wp_safe_redirect( admin_url( 'admin.php?page=ifs-pms' ) ); 
        exit; 
    } 
} 

/** 
 * 6. Main Terminal Canvas Shell 
 */ 
function ifs_pms_render_application() { 
    if ( ! current_user_can( 'ifs_access_terminal' ) && ! current_user_can( 'manage_options' ) ) { 
        wp_die( esc_html__( 'Unauthorized access.', 'swimming-pool-manager' ), 403 ); 
    } 

    $is_admin         = current_user_can( 'manage_options' ) || current_user_can( 'ifs_manage_settings' ); 
    $admin_only_views = array( 'staff', 'expenses', 'reports', 'settings', 'hardware', 'audit-logs' ); 

    $plugin_page = $_GET['page'] ?? 'ifs-pms'; 
    $view_map    = array(
        'ifs-pms'             => 'dashboard',
        'ifs-pms-scanner'     => 'scanner',
        'ifs-pms-live-status' => 'live-status',
        'ifs-pms-bookings'    => 'bookings',
        'ifs-pms-customers'   => 'customers',
        'ifs-pms-membership'  => 'membership',
        'ifs-pms-shop'        => 'shop',
        'ifs-pms-lockers'     => 'lockers',
        'ifs-pms-water-log'   => 'water-log',
        'ifs-pms-dayclose'    => 'dayclose',
        'ifs-pms-expenses'    => 'expenses',
        'ifs-pms-staff'       => 'staff',
        'ifs-pms-reports'     => 'reports',
        'ifs-pms-hardware'    => 'hardware',
        'ifs-pms-audit-logs'  => 'audit-logs',
        'ifs-pms-settings'    => 'settings',
    );

    if ( isset( $view_map[ $plugin_page ] ) && 'ifs-pms' !== $plugin_page ) {
        $current_view = $view_map[ $plugin_page ];
    } else {
        $current_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';
    }

    if ( ! $is_admin && in_array( $current_view, $admin_only_views, true ) ) { 
        $current_view = 'dashboard'; 
    } 

    $b_name       = (string) get_option( 'ifs_pms_business_name', '' ); 
    $address      = (string) get_option( 'ifs_pms_address', '' ); 
    $phone        = (string) get_option( 'ifs_pms_phone', '' ); 
    $receipt_note = (string) get_option( 'ifs_pms_receipt_note', '' ); 
    $logo_url     = (string) get_option( 'ifs_pms_logo_url', '' ); 

    // Flash Messages Map
    $msg_code  = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : ''; 
    $flash_msg = ''; 
    $flash_map = array( 
        'ticket_created'      => __( 'Single Admission Pass Created Successfully.', 'swimming-pool-manager' ), 
        'ticket_updated'      => __( 'Ticket Record Updated.', 'swimming-pool-manager' ), 
        'ticket_deleted'      => __( 'Ticket Removed Permanently.', 'swimming-pool-manager' ), 
        'customer_updated'    => __( 'Patron Profile Updated Successfully.', 'swimming-pool-manager' ), 
        'membership_enrolled' => __( 'Member Subscription Record Synchronized.', 'swimming-pool-manager' ), 
        'expense_logged'      => __( 'Operational Outflow Logged.', 'swimming-pool-manager' ), 
        'staff_created'       => __( 'Operator Account & Compensation Provisioned.', 'swimming-pool-manager' ), 
        'staff_updated'       => __( 'Operator Profile Updated.', 'swimming-pool-manager' ), 
        'settings_saved'      => __( 'Master Configuration Saved.', 'swimming-pool-manager' ), 
    ); 
    if ( isset( $flash_map[ $msg_code ] ) ) { 
        $flash_msg = $flash_map[ $msg_code ]; 
    } 
    ?> 

    <div class="ifs-pms-shell" id="ifsPmsAppShell"> 
        <style>
            .ifs-pms-nav-scroll-wrap { scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, 0.4) transparent; -webkit-overflow-scrolling: touch; }
            .ifs-pms-nav-scroll-wrap::-webkit-scrollbar { width: 4px; }
            .ifs-pms-nav-scroll-wrap::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.35); border-radius: 4px; }

            .ifs-pms-mobile-trigger {
                display: none; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 12px;
                background: #ffffff !important; border: 1.5px solid #cbd5e1 !important; color: #0f172a !important; cursor: pointer;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08); flex-shrink: 0; position: relative; margin-right: 12px; z-index: 99;
            }
            .ifs-pms-mobile-trigger span,
            .ifs-pms-mobile-trigger span::before,
            .ifs-pms-mobile-trigger span::after {
                content: ''; display: block; width: 20px; height: 2.5px; background-color: #0f172a; border-radius: 2px;
                position: absolute; left: 50%; transform: translateX(-50%); transition: all 0.2s ease;
            }
            .ifs-pms-mobile-trigger span { top: 50%; transform: translate(-50%, -50%); }
            .ifs-pms-mobile-trigger span::before { top: -6.5px; }
            .ifs-pms-mobile-trigger span::after { top: 6.5px; }

            @media screen and (max-width: 992px) {
                .ifs-pms-shell { position: relative !important; flex-direction: column !important; height: 100vh !important; height: 100dvh !important; }
                .ifs-pms-mobile-trigger { display: inline-flex !important; }
                .ifs-pms-aside {
                    position: fixed !important; top: 0 !important; bottom: 0 !important; left: 0 !important; width: 285px !important;
                    height: 100vh !important; height: 100dvh !important; background-color: #ffffff !important;
                    transform: translateX(-105%) !important; transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
                    z-index: 100000 !important;
                }
                .ifs-pms-aside.mobile-open { transform: translateX(0) !important; box-shadow: 12px 0 40px rgba(0, 0, 0, 0.45) !important; }
                .ifs-pms-sidebar-backdrop {
                    display: none; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
                    background: rgba(15, 23, 42, 0.65) !important; backdrop-filter: blur(4px) !important; z-index: 99990 !important;
                }
                .ifs-pms-sidebar-backdrop.active { display: block !important; }
                .ifs-pms-canvas { padding: 16px 14px !important; height: 100% !important; flex: 1 1 auto !important; }
            }

            [data-theme="dark"] .ifs-pms-mobile-trigger { background: #121829 !important; border-color: rgba(255, 255, 255, 0.15) !important; }
            [data-theme="dark"] .ifs-pms-mobile-trigger span,
            [data-theme="dark"] .ifs-pms-mobile-trigger span::before,
            [data-theme="dark"] .ifs-pms-mobile-trigger span::after { background-color: #f8fafc !important; }
            [data-theme="dark"] .ifs-pms-aside { background-color: #0d121f !important; border-color: rgba(255, 255, 255, 0.1) !important; }
        </style>

        <!-- 1. Off-Canvas Sidebar Drawer with Organized Functional Clusters -->
        <aside class="ifs-pms-aside" id="ifsPmsSidebarDrawer" style="width: 260px; min-width: 260px; height: 100vh; max-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; box-sizing: border-box; padding: 22px 14px; background: var(--ifs-sidebar); border-right: 1px solid var(--ifs-border-subtle);"> 
            <div class="ifs-pms-nav-scroll-wrap" style="flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; min-height: 0; padding-right: 4px; margin-bottom: 12px;"> 
                
                <div class="ifs-pms-brand" style="display: flex; align-items: center; justify-content: space-between; padding: 4px 8px 18px 8px; border-bottom: 1px solid var(--ifs-border-subtle); margin-bottom: 16px;"> 
                    <div style="overflow: hidden;"> 
                        <div style="font-size: 15px; font-weight: 800; color: var(--ifs-text-primary); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?php echo $b_name ? esc_html( $b_name ) : esc_html__( 'IFS Pool Manager', 'swimming-pool-manager' ); ?></div> 
                        <div style="font-size: 10px; color: var(--ifs-accent); font-weight: 700; letter-spacing: 0.8px;"> 
                            <?php echo $is_admin ? esc_html__( 'MANAGER TERMINAL', 'swimming-pool-manager' ) : esc_html__( 'CASHIER DESK', 'swimming-pool-manager' ); ?> 
                        </div> 
                    </div> 
                    <button type="button" onclick="ifsPmsToggleMobileMenu(false);" aria-label="<?php esc_attr_e( 'Close Navigation', 'swimming-pool-manager' ); ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: none; background: var(--ifs-surface-hover); color: var(--ifs-text-secondary); cursor: pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div> 

                <!-- Functional Cluster 1: Front Desk Operations -->
                <div class="ifs-pms-nav-group-title"><?php esc_html_e( 'Front Desk Operations', 'swimming-pool-manager' ); ?></div> 
                <ul class="ifs-pms-nav"> 
                    <li class="ifs-pms-nav-item <?php echo ( 'dashboard' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms' ) ); ?>"> 
                            <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Overview', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'tickets' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms&view=tickets' ) ); ?>"> 
                            <span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Ticketing POS', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'scanner' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-scanner' ) ); ?>"> 
                            <span class="dashicons dashicons-fullscreen-alt"></span> <?php esc_html_e( 'Access Gate', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'live-status' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-live-status' ) ); ?>"> 
                            <span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Floor Monitor', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'bookings' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-bookings' ) ); ?>"> 
                            <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Reservations', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                </ul> 

                <!-- Functional Cluster 2: Guest & Facility Services -->
                <div class="ifs-pms-nav-group-title"><?php esc_html_e( 'Guest & Facility Services', 'swimming-pool-manager' ); ?></div> 
                <ul class="ifs-pms-nav"> 
                    <li class="ifs-pms-nav-item <?php echo ( 'customers' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-customers' ) ); ?>"> 
                            <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Guest Profiles', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'membership' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-membership' ) ); ?>"> 
                            <span class="dashicons dashicons-id-alt"></span> <?php esc_html_e( 'Memberships', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'shop' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-shop' ) ); ?>"> 
                            <span class="dashicons dashicons-store"></span> <?php esc_html_e( 'Retail & Inventory', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'lockers' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-lockers' ) ); ?>"> 
                            <span class="dashicons dashicons-vault"></span> <?php esc_html_e( 'Locker & Asset Hub', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <li class="ifs-pms-nav-item <?php echo ( 'water-log' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-water-log' ) ); ?>"> 
                            <?php echo wp_kses( ifs_pms_get_svg( 'water', '', 14 ), array( 'svg' => array( 'xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'fill' => true ), 'path' => array( 'd' => true ) ) ); ?> 
                            <span style="margin-left: 6px;"><?php esc_html_e( 'Water Quality', 'swimming-pool-manager' ); ?></span> 
                        </a> 
                    </li> 
                </ul> 

                <!-- Functional Cluster 3: Finance & Administration -->
                <div class="ifs-pms-nav-group-title"><?php esc_html_e( 'Finance & Administration', 'swimming-pool-manager' ); ?></div> 
                <ul class="ifs-pms-nav"> 
                    <li class="ifs-pms-nav-item <?php echo ( 'dayclose' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-dayclose' ) ); ?>"> 
                            <span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Cash Reconciliation', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                    <?php if ( $is_admin ) : ?> 
                        <li class="ifs-pms-nav-item <?php echo ( 'expenses' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                            <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-expenses' ) ); ?>"> 
                                <span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'Operating Expenses', 'swimming-pool-manager' ); ?> 
                            </a> 
                        </li> 
                        <li class="ifs-pms-nav-item <?php echo ( 'staff' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                            <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-staff' ) ); ?>"> 
                                <span class="dashicons dashicons-businessperson"></span> <?php esc_html_e( 'Staff & Payroll', 'swimming-pool-manager' ); ?> 
                            </a> 
                        </li> 
                        <li class="ifs-pms-nav-item <?php echo ( 'reports' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                            <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-reports' ) ); ?>"> 
                                <span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Business Analytics', 'swimming-pool-manager' ); ?> 
                            </a> 
                        </li> 
                    <?php endif; ?> 
                </ul> 

                <!-- Functional Cluster 4: Infrastructure & Governance -->
                <?php if ( $is_admin ) : ?> 
                    <div class="ifs-pms-nav-group-title"><?php esc_html_e( 'Infrastructure & Audit', 'swimming-pool-manager' ); ?></div> 
                    <ul class="ifs-pms-nav"> 
                        <li class="ifs-pms-nav-item <?php echo ( 'hardware' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                            <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-hardware' ) ); ?>"> 
                                <?php echo wp_kses( ifs_pms_get_svg( 'circuit', '', 14 ), array( 'svg' => array( 'xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'fill' => true ), 'path' => array( 'd' => true ) ) ); ?> 
                                <span style="margin-left: 6px;"><?php esc_html_e( 'Hardware Hub', 'swimming-pool-manager' ); ?></span> 
                            </a> 
                        </li> 
                        <li class="ifs-pms-nav-item <?php echo ( 'audit-logs' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                            <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-audit-logs' ) ); ?>"> 
                                <?php echo wp_kses( ifs_pms_get_svg( 'audit', '', 14 ), array( 'svg' => array( 'xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'fill' => true ), 'path' => array( 'd' => true ) ) ); ?> 
                                <span style="margin-left: 6px;"><?php esc_html_e( 'Audit Trail', 'swimming-pool-manager' ); ?></span> 
                            </a> 
                        </li> 
                        <li class="ifs-pms-nav-item <?php echo ( 'settings' === $current_view ) ? 'ifs-pms-active' : ''; ?>"> 
                            <a class="ifs-pms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ifs-pms-settings' ) ); ?>"> 
                                <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Facility Config', 'swimming-pool-manager' ); ?> 
                            </a> 
                        </li> 
                    </ul> 
                <?php endif; ?> 

                <!-- Functional Cluster 5: Session -->
                <div class="ifs-pms-nav-group-title"><?php esc_html_e( 'Session', 'swimming-pool-manager' ); ?></div> 
                <ul class="ifs-pms-nav"> 
                    <li class="ifs-pms-nav-item"> 
                        <a class="ifs-pms-nav-link" href="<?php echo esc_url( wp_logout_url( admin_url( 'index.php' ) ) ); ?>"> 
                            <span class="dashicons dashicons-migrate"></span> <?php esc_html_e( 'Logout', 'swimming-pool-manager' ); ?> 
                        </a> 
                    </li> 
                </ul> 
            </div> 

            <!-- Docked Mode & Identity Controls -->
            <div style="flex-shrink: 0; border-top: 1px solid var(--ifs-border-subtle); padding-top: 12px;"> 
                <div class="ifs-pms-theme-toggle-wrap" style="display: flex; align-items: center; justify-content: space-between; background: var(--ifs-surface-hover); border: 1px solid var(--ifs-border-subtle); border-radius: 10px; padding: 8px 12px; margin-bottom: 12px;"> 
                    <span style="font-size: 11px; font-weight: 700; color: var(--ifs-text-tertiary);"><?php esc_html_e( 'Mode', 'swimming-pool-manager' ); ?></span> 
                    <div style="display: flex; gap: 4px;"> 
                        <button type="button" class="ifs-pms-theme-btn" id="ifsThemeLightBtn" onclick="if(window.ifsPms && window.ifsPms.applyTheme){ window.ifsPms.applyTheme('light'); }"> 
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><line x1="12" y1="2" x2="12" y2="4"></line><line x1="12" y1="20" x2="12" y2="22"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="2" y1="12" x2="4" y2="12"></line><line x1="20" y1="12" x2="22" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                            <span><?php esc_html_e( 'Light', 'swimming-pool-manager' ); ?></span>
                        </button> 
                        <button type="button" class="ifs-pms-theme-btn" id="ifsThemeDarkBtn" onclick="if(window.ifsPms && window.ifsPms.applyTheme){ window.ifsPms.applyTheme('dark'); }"> 
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                            <span><?php esc_html_e( 'Dark', 'swimming-pool-manager' ); ?></span>
                        </button> 
                    </div> 
                </div> 

                <div class="ifs-pms-operator-card" style="background: var(--ifs-surface-hover); border: 1px solid var(--ifs-border-subtle); border-radius: 10px; padding: 10px 12px; display: flex; align-items: center; gap: 10px;"> 
                    <div class="ifs-pms-operator-avatar" style="width: 32px; height: 32px; background: var(--ifs-surface); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; color: var(--ifs-accent); border: 1px solid var(--ifs-border-strong);"><?php echo esc_html( strtoupper( substr( wp_get_current_user()->display_name, 0, 1 ) ) ); ?></div> 
                    <div style="overflow: hidden;"> 
                        <div style="font-size: 13px; font-weight: 700; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; color: var(--ifs-text-primary);"> 
                            <?php echo esc_html( wp_get_current_user()->display_name ); ?> 
                        </div> 
                        <div style="font-size: 11px; color: var(--ifs-text-tertiary);"> 
                            <?php echo $is_admin ? esc_html__( 'Facility Manager', 'swimming-pool-manager' ) : esc_html__( 'Active Cashier', 'swimming-pool-manager' ); ?> 
                        </div> 
                    </div> 
                </div> 
            </div> 
        </aside>

        <!-- 2. Main Admin Canvas Body -->
        <main class="ifs-pms-canvas"> 
            <header class="ifs-pms-header"> 
                <div style="display: flex; align-items: center; width: 100%;"> 
                    <button type="button" class="ifs-pms-mobile-trigger" onclick="ifsPmsToggleMobileMenu(true);" aria-label="<?php esc_attr_e( 'Open Navigation Menu', 'swimming-pool-manager' ); ?>">
                        <span></span>
                    </button>

                    <?php if ( ! empty( $logo_url ) ) : ?> 
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" style="max-height: 44px; width: auto; border-radius: 10px; border: 1px solid var(--ifs-border-subtle); margin-right: 12px;"> 
                    <?php endif; ?> 
                    <div> 
                        <h1><?php echo $b_name ? esc_html( $b_name ) : esc_html__( 'IFS Swimming Pool Manager', 'swimming-pool-manager' ); ?></h1> 
                        <p><span class="ifs-pms-pulse-dot"></span> <?php esc_html_e( 'Terminal Online', 'swimming-pool-manager' ); ?> &bull; <?php echo esc_html( current_time( 'l, F j, Y' ) ); ?></p> 
                    </div> 
                </div> 
            </header> 

            <?php if ( ! empty( $flash_msg ) ) : ?> 
                <div style="background: var(--ifs-success-soft, #ecfdf5); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--ifs-success, #059669); padding: 12px 18px; border-radius: 8px; margin-bottom: 22px; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 10px;"> 
                    <?php echo wp_kses( ifs_pms_get_svg( 'check', '', 16 ), array( 'svg' => array( 'xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'fill' => true ), 'path' => array( 'd' => true ) ) ); ?> <?php echo esc_html( $flash_msg ); ?> 
                </div> 
            <?php endif; ?> 

            <?php 
            $view_to_include = ( 'ticket_detail' === $current_view ) ? 'tickets' : $current_view; 
            $view_path       = IFS_PMS_PATH . 'views/' . $view_to_include . '.php'; 

            if ( file_exists( $view_path ) ) { 
                include $view_path; 
            } else { 
                echo '<div class="ifs-pms-panel" style="padding: 40px; text-align: center;"><p style="font-size: 15px; font-weight: 700; color: #64748b;">' . esc_html__( 'Module View File:', 'swimming-pool-manager' ) . ' <code>views/' . esc_html( $view_to_include ) . '.php</code></p></div>'; 
            } 
            ?> 
        </main> 

        <div class="ifs-pms-sidebar-backdrop" id="ifsPmsSidebarBackdrop" onclick="ifsPmsToggleMobileMenu(false);"></div>
    </div> 

    <!-- Global Thermal Receipt Modal --> 
    <div id="ifs-pms-thermal-modal"> 
        <div class="ifs-pms-receipt-card"> 
            <?php if ( ! empty( $logo_url ) ) : ?> 
                <div style="display: flex; justify-content: center; margin-bottom: 8px;"> 
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" style="max-height: 36px; width: auto;"> 
                </div> 
            <?php endif; ?> 
            <h2 style="margin: 0; font-size: 16px; font-weight: 800; text-align: center;"><?php echo esc_html( $b_name ? $b_name : 'IFS Swimming Pool' ); ?></h2> 
            <p style="margin: 4px 0 0 0; font-size: 11px; color: #475569; text-align: center;"> 
                <?php echo esc_html( $address ); ?><br> 
                <?php if ( ! empty( $phone ) ) : ?> 
                    <?php echo esc_html__( 'Tel:', 'swimming-pool-manager' ) . ' ' . esc_html( $phone ); ?> 
                <?php endif; ?> 
            </p> 
            <div class="ifs-pms-dashed-sep"></div> 
            <div id="ifs-pms-thermal-qr" style="display: flex; justify-content: center; margin: 12px 0;"></div> 
            <div id="ifs-pms-slip-code" style="font-size: 16px; font-weight: 800; letter-spacing: 1.5px; text-align: center;"></div> 
            <div class="ifs-pms-dashed-sep"></div> 
            <div id="ifs-pms-slip-meta" style="font-size: 11.5px; line-height: 1.6; text-align: left;"></div> 
            <div class="ifs-pms-dashed-sep"></div> 
            <?php if ( ! empty( $receipt_note ) ) : ?> 
                <p style="font-size: 9.5px; color: #64748b; margin: 0; text-align: center;"><?php echo esc_html( $receipt_note ); ?></p> 
            <?php endif; ?> 
            <div class="no-print" style="margin-top: 16px; display: flex; gap: 8px;"> 
                <button onclick="window.print()" class="ifs-pms-btn ifs-pms-btn-primary" style="flex: 1;"><?php echo wp_kses( ifs_pms_get_svg( 'print', '', 14 ), array( 'svg' => array( 'xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'fill' => true ), 'path' => array( 'd' => true ) ) ); ?> <?php esc_html_e( 'Print', 'swimming-pool-manager' ); ?></button> 
                <button onclick="if(window.ifsPms && window.ifsPms.closeReceipt){ window.ifsPms.closeReceipt(); }" class="ifs-pms-btn ifs-pms-btn-secondary" style="flex: 1;"><?php esc_html_e( 'Close', 'swimming-pool-manager' ); ?></button> 
            </div> 
        </div> 
    </div> 

    <script>
        function ifsPmsToggleMobileMenu(open) {
            var drawer = document.getElementById('ifsPmsSidebarDrawer');
            var backdrop = document.getElementById('ifsPmsSidebarBackdrop');
            if (!drawer || !backdrop) return;
            if (open) {
                drawer.classList.add('mobile-open');
                backdrop.classList.add('active');
            } else {
                drawer.classList.remove('mobile-open');
                backdrop.classList.remove('active');
            }
        }
    </script>
    <?php 
} 

/** 
 * 7. Pass Verification AJAX with IoT Relay Output 
 */ 
add_action( 'wp_ajax_ifs_pms_verify_pass_action', 'ifs_pms_verify_pass_callback' ); 

function ifs_pms_verify_pass_callback() { 
    check_ajax_referer( 'ifs_pms_security_token', 'security' ); 

    if ( ! current_user_can( 'ifs_scan_passes' ) && ! current_user_can( 'manage_options' ) ) { 
        wp_send_json_error( array( 'message' => __( 'Forbidden: Insufficient privileges.', 'swimming-pool-manager' ) ), 403 ); 
    } 

    global $wpdb; 
    $t_tick = '`' . esc_sql( $wpdb->prefix . 'ifs_pms_tickets' ) . '`'; 
    $t_cust = '`' . esc_sql( $wpdb->prefix . 'ifs_pms_customers' ) . '`'; 
    $code   = isset( $_POST['ticket_code'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_code'] ) ) : ''; 

    if ( empty( $code ) ) { 
        wp_send_json_error( array( 'message' => __( 'Missing pass identification parameter.', 'swimming-pool-manager' ) ), 400 ); 
    } 

    $ticket = $wpdb->get_row($wpdb->prepare( "SELECT t.*, c.name as customer_name, c.phone as customer_phone FROM {$t_tick} t LEFT JOIN {$t_cust} c ON t.customer_id = c.id WHERE t.ticket_code = %s", $code ) ); 

    if ( ! $ticket ) { 
        wp_send_json_error( array( 'message' => __( 'Unrecognized Pass ID.', 'swimming-pool-manager' ) ), 404 ); 
    } 

    if ( 'Valid' === $ticket->status ) { 
        $wpdb->update($wpdb->prefix . 'ifs_pms_tickets', 
            array( 'status' => 'Used', 'scanned_at' => current_time( 'mysql' ), 'scanned_by' => wp_get_current_user()->display_name ), 
            array( 'id' => $ticket->id ) 
        ); 

        // IoT Relay Trigger Command
        $relay_ip = get_option( 'ifs_pms_relay_ip', '' ); 
        if ( ! empty( $relay_ip ) ) { 
            $relay_port = get_option( 'ifs_pms_relay_port', '80' );$sec        = get_option( 'ifs_pms_relay_trigger_sec', '3' ); 
            wp_remote_get( "http://{$relay_ip}:{$relay_port}/trigger?channel=1&duration={$sec}", array( 'timeout' => 1.5, 'blocking' => false ) ); 
        } 

        ifs_pms_record_audit( 'scan_success', $ticket->ticket_code, "Unlocked turnstile for {$ticket->customer_name}" ); 
        wp_send_json_success( array( 'message' => __( 'ACCESS GRANTED • TURNSTILE UNLOCKED', 'swimming-pool-manager' ), 'ticket_code' => $ticket->ticket_code ) ); 
    } else { 
        ifs_pms_record_audit( 'scan_rejected', $ticket->ticket_code, "Pass status was {$ticket->status}" ); 
        wp_send_json_error( array( 'message' => sprintf( __( 'Pass already redeemed or expired at %s', 'swimming-pool-manager' ), gmdate( 'h:i A', strtotime( $ticket->scanned_at ) ) ) ) ); 
    } 
} 

/** 
 * 8. Secure CSV Exporter Callback (Managers & Cashiers) 
 */ 
add_action( 'wp_ajax_ifs_pms_export_csv_action', 'ifs_pms_export_csv_action_callback' ); 

function ifs_pms_export_csv_action_callback() { 
    check_ajax_referer( 'ifs_pms_security_token', 'security' ); 

    if ( ! current_user_can( 'ifs_view_finances' ) && ! current_user_can( 'manage_options' ) ) { 
        wp_die( esc_html__( 'Unauthorized access.', 'swimming-pool-manager' ), 403 ); 
    } 

    global $wpdb; 
    $t_tick = '`' . esc_sql( $wpdb->prefix . 'ifs_pms_tickets' ) . '`'; 
    $t_cust = '`' . esc_sql( $wpdb->prefix . 'ifs_pms_customers' ) . '`'; 

    $tickets =$wpdb->get_results( 
        "SELECT t.ticket_code, c.name as customer_name, c.phone as customer_phone, t.guest_type, t.package_details, t.duration_hours, t.payment_method, t.room_no, t.amount, t.sold_by, t.status, t.sold_at, t.scanned_at, t.scanned_by 
        FROM {$t_tick} t 
        LEFT JOIN {$t_cust} c ON t.customer_id = c.id 
        ORDER BY t.id DESC", 
        ARRAY_A 
    ); 

    nocache_headers(); 
    header( 'Content-Type: text/csv; charset=utf-8' ); 
    header( 'Content-Disposition: attachment; filename=ifs_pool_ledger_' . gmdate( 'Y-m-d' ) . '.csv' ); 

    $output = fopen( 'php://output', 'w' ); 
    if ( false !== $output ) { 
        fputcsv( $output, array( 'Ticket Code', 'Patron Name', 'Phone', 'Guest Type', 'Packages Enrolled', 'Hours', 'Payment Method', 'Room Number', 'Amount', 'Sold By', 'Status', 'Sold At', 'Scanned At', 'Scanned By' ) ); 

        if ( ! empty( $tickets ) ) { 
            foreach ( $tickets as$row ) { 
                fputcsv( 
                    $output, 
                    array( 
                        $row['ticket_code'],$row['customer_name'], 
                        $row['customer_phone'],$row['guest_type'], 
                        $row['package_details'],$row['duration_hours'], 
                        $row['payment_method'],$row['room_no'], 
                        $row['amount'],$row['sold_by'], 
                        $row['status'],$row['sold_at'], 
                        $row['scanned_at'],$row['scanned_by'], 
                    ) 
                ); 
            } 
        } 
        fclose( $output ); 
    } 
    exit; 
} 

/** 
 * 9. Clean Login Flow 
 */ 
add_filter( 'login_redirect', 'ifs_pms_login_redirect_dashboard', 10, 3 ); 

function ifs_pms_login_redirect_dashboard( $redirect_to, $requested_redirect_to,$user ) { 
    if ( isset( $user->ID ) && ( user_can( $user, 'ifs_access_terminal' ) || user_can($user, 'manage_options' ) ) ) { 
        return admin_url( 'admin.php?page=ifs-pms' ); 
    } 
    return $redirect_to; 
} 

add_filter( 'login_footertext', 'ifs_pms_custom_login_footer' ); 

function ifs_pms_custom_login_footer() { 
    $b_name = get_option( 'ifs_pms_business_name', '' ); 
    return sprintf( 
        esc_html__( '&copy; %1$d \%2$s &bull; IFS Enterprise Operating System', 'swimming-pool-manager' ), 
        (int) gmdate( 'Y' ), 
        esc_html( $b_name ? $b_name : 'IFS Swimming Pool Manager' ) 
    ); 
}