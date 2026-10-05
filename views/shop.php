<?php
/**
 * View: Retail Shop POS, Swimwear & Concessions Hub
 *
 * @package SwimmingPoolManager
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 1. Context, Permissions & Tables
$can_sell = current_user_can( 'ifs_sell_tickets' ) || current_user_can( 'manage_options' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'ifs_manage_settings' );

if ( ! $can_sell ) {
    wp_die( esc_html__( 'Forbidden: Insufficient privileges to access retail POS.', 'swimming-pool-manager' ), 403 );
}

$t_items  = $wpdb->prefix . 'ifs_pms_shop_items';
$t_orders = $wpdb->prefix . 'ifs_pms_shop_orders';

$currency = esc_html( (string) get_option( 'ifs_pms_currency', 'BDT' ) );
$base_url = admin_url( 'admin.php?page=ifs-pms&view=shop' );

// Auto-create retail tables if not exist (Zero friction)
$charset_collate = $wpdb->get_charset_collate();
$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$t_items} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        item_name varchar(150) NOT NULL,
        category varchar(50) DEFAULT 'Gear' NOT NULL,
        price decimal(10,2) NOT NULL DEFAULT 0.00,
        stock_qty int(5) DEFAULT 50 NOT NULL,
        status varchar(20) DEFAULT 'Active' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_category (category)
    ) {$charset_collate};"
);

$wpdb->query(
    "CREATE TABLE IF NOT EXISTS {$t_orders} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_code varchar(50) NOT NULL,
        ticket_code varchar(50) DEFAULT NULL,
        items_summary text NOT NULL,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        payment_method varchar(30) DEFAULT 'Cash' NOT NULL,
        sold_by varchar(100) NOT NULL,
        sold_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY order_code (order_code),
        KEY idx_sold_at (sold_at)
    ) {$charset_collate};"
);

// Seed Initial Catalog if Empty
$items_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_items}" );
if ( 0 === $items_count ) {
    $default_items = array(
        array( 'Pro Swim Cap (Silicone)', 'Gear', 250.00, 40 ),
        array( 'Anti-Fog UV Goggles', 'Gear', 650.00, 30 ),
        array( 'Standard Swim Shorts', 'Apparel', 500.00, 50 ),
        array( 'Waterproof Phone Pouch', 'Gear', 200.00, 60 ),
        array( 'Electrolyte Hydration Drink', 'F&B', 120.00, 100 ),
        array( 'Chilled Mineral Water 500ml', 'F&B', 30.00, 150 ),
        array( 'Energy Protein Snack Bar', 'F&B', 150.00, 75 ),
        array( 'SPF 50+ Sunscreen Lotion', 'Accessories', 450.00, 25 ),
    );
    foreach ( $default_items as $di ) {
        $wpdb->insert(
            $t_items,
            array(
                'item_name' => $di[0],
                'category'  => $di[1],
                'price'     => $di[2],
                'stock_qty' => $di[3],
            ),
            array( '%s', '%s', '%f', '%d' )
        );
    }
}

// 2. Action Handlers (Checkout & Add Product)
$feedback = null;

// Checkout Processing
if ( isset( $_POST['ifs_pms_action'] ) && 'retail_checkout' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $cart_json = wp_unslash( $_POST['cart_data'] ?? '[]' );
    $cart_data = json_decode( $cart_json, true );
    $tender    = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? 'Cash' ) );
    $ticket_ref= sanitize_text_field( wp_unslash( $_POST['ticket_code'] ?? '' ) );
    $staff     = wp_get_current_user()->display_name;

    if ( empty( $cart_data ) || ! is_array( $cart_data ) ) {
        $feedback = array( 'success' => false, 'message' => __( 'Cart is empty! Please add items before checkout.', 'swimming-pool-manager' ) );
    } else {
        $total_amount = 0.00;
        $summary_parts = array();

        foreach ( $cart_data as $item ) {
            $item_id = absint( $item['id'] ?? 0 );
            $qty     = max( 1, absint( $item['qty'] ?? 1 ) );
            $price   = max( 0.00, floatval( $item['price'] ?? 0.00 ) );

            $subtotal = $qty * $price;
            $total_amount += $subtotal;
            $summary_parts[] = "{$item['name']} x{$qty} (" . number_format( $subtotal, 2 ) . ")";

            // Deduct stock
            $wpdb->query( $wpdb->prepare( "UPDATE {$t_items} SET stock_qty = GREATEST(0, stock_qty - %d) WHERE id = %d", $qty, $item_id ) );
        }

        $order_code = 'ORD-' . strtoupper( current_time( 'M' ) ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
        $items_str  = implode( ', ', $summary_parts );

        $inserted = $wpdb->insert(
            $t_orders,
            array(
                'order_code'     => $order_code,
                'ticket_code'    => $ticket_ref,
                'items_summary'  => $items_str,
                'total_amount'   => $total_amount,
                'payment_method' => $tender,
                'sold_by'        => $staff,
                'sold_at'        => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            if ( function_exists( 'ifs_pms_record_audit' ) ) {
                ifs_pms_record_audit( 'retail_sale', $order_code, "Amount: {$total_amount}, Tender: {$tender}, Items: {$items_str}" );
            }
            $feedback = array(
                'success' => true,
                'message' => sprintf( __( 'Order #%s processed successfully! Total: %s %s', 'swimming-pool-manager' ), $order_code, $currency, number_format( $total_amount, 2 ) ),
            );
        }
    }
}

// Add New Product to Catalog
if ( isset( $_POST['ifs_pms_action'] ) && 'add_shop_item' === $_POST['ifs_pms_action'] ) {
    check_admin_referer( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' );

    $name  = sanitize_text_field( wp_unslash( $_POST['item_name'] ?? '' ) );
    $cat   = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'Gear' ) );
    $price = max( 0.00, floatval( wp_unslash( $_POST['price'] ?? 0.00 ) ) );
    $stock = max( 0, absint( wp_unslash( $_POST['stock_qty'] ?? 10 ) ) );

    if ( ! empty( $name ) && $price > 0 ) {
        $wpdb->insert(
            $t_items,
            array( 'item_name' => $name, 'category' => $cat, 'price' => $price, 'stock_qty' => $stock ),
            array( '%s', '%s', '%f', '%d' )
        );
        $feedback = array( 'success' => true, 'message' => sprintf( __( 'Item "%s" added to retail catalog.', 'swimming-pool-manager' ), $name ) );
    }
}

// 3. Telemetry Calculations
$today_ymd    = current_time( 'Y-m-d' );
$today_orders = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT COUNT(id) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM {$t_orders} 
         WHERE DATE(sold_at) = %s",
        $today_ymd
    )
);
$today_retail_rev   = $today_orders ? (float) $today_orders->total : 0.00;
$today_retail_count = $today_orders ? (int) $today_orders->count : 0;

$catalog = $wpdb->get_results( "SELECT * FROM {$t_items} WHERE status = 'Active' ORDER BY category ASC, item_name ASC" );
$recent_orders = $wpdb->get_results( "SELECT * FROM {$t_orders} ORDER BY id DESC LIMIT 5" );
?>

<style>
.ifs-shop-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ifs-text-primary, #0f172a);
    box-sizing: border-box;
}
.ifs-shop-wrap * { box-sizing: border-box; }
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

/* POS Layout Split */
.ifs-pos-grid {
    display: grid;
    grid-template-columns: 1.25fr 0.75fr;
    gap: 18px;
}

/* Catalog Grid */
.ifs-catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
}

.ifs-item-card {
    background: var(--ifs-surface-hover, #f8fafc);
    border: 1.5px solid var(--ifs-border-subtle, #e2e8f0);
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.15s ease;
}

.ifs-item-card:hover {
    transform: translateY(-2px);
    border-color: #0284c7;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.1);
}

.ifs-item-cat {
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    color: #0284c7;
    letter-spacing: 0.5px;
}

.ifs-item-name {
    font-size: 13.5px;
    font-weight: 700;
    margin: 6px 0;
    line-height: 1.3;
}

.ifs-item-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--ifs-border-subtle, #e2e8f0);
}

.ifs-item-price {
    font-size: 14px;
    font-weight: 900;
    color: #10b981;
}

.ifs-item-stock {
    font-size: 10.5px;
    color: var(--ifs-text-tertiary, #64748b);
}

/* Cart Dock */
.ifs-cart-dock {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.ifs-cart-list {
    flex: 1;
    max-height: 340px;
    overflow-y: auto;
    border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);
    margin-bottom: 14px;
    padding-right: 4px;
}

.ifs-cart-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--ifs-border-subtle, #f8fafc);
}

.ifs-qty-ctrl {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ifs-qty-btn {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    border: 1px solid var(--ifs-border-subtle, #cbd5e1);
    background: #ffffff;
    font-size: 14px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.ifs-cart-total-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 10px 0 16px 0;
    font-size: 15px;
    font-weight: 800;
}

/* Forms & Inputs */
.ifs-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}
.ifs-form-group label {
    font-size: 12px;
    font-weight: 700;
    color: var(--ifs-text-secondary, #475569);
}
.ifs-input, .ifs-select {
    width: 100%;
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
    max-width: 480px;
    padding: 24px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

/* Dark Mode Variables */
[data-theme="dark"] .ifs-card, 
[data-theme="dark"] .ifs-modal-card {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-item-card {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .ifs-input, 
[data-theme="dark"] .ifs-select {
    background: #0d121f;
    border-color: rgba(255, 255, 255, 0.15);
    color: #f8fafc;
}
[data-theme="dark"] .ifs-qty-btn {
    background: #121829;
    border-color: rgba(255, 255, 255, 0.2);
    color: #f8fafc;
}

@media screen and (max-width: 1024px) {
    .ifs-pos-grid { grid-template-columns: 1fr; }
}
</style>

<div class="ifs-shop-wrap">
    <?php if ( is_array( $feedback ) ) : ?>
        <div style="background: <?php echo $feedback['success'] ? '#ecfdf5' : '#fef2f2'; ?>; border: 1px solid <?php echo $feedback['success'] ? '#a7f3d0' : '#fecaca'; ?>; color: <?php echo $feedback['success'] ? '#065f46' : '#991b1b'; ?>; padding: 12px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px;">
            <?php echo $feedback['success'] ? '✓' : '⚠️'; ?> <?php echo esc_html( $feedback['message'] ); ?>
        </div>
    <?php endif; ?>

    <!-- Main Touchscreen POS Grid -->
    <div class="ifs-pos-grid">
        <!-- 1. Catalog Products Deck -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <div>
                    <h2 class="ifs-card-title">
                        <span class="dashicons dashicons-store"></span>
                        <?php esc_html_e( 'Retail & Concessions POS', 'swimming-pool-manager' ); ?>
                    </h2>
                    <span style="font-size: 12px; color: var(--ifs-text-tertiary, #64748b);">
                        <?php esc_html_e( 'Click any item to immediately add to order cart', 'swimming-pool-manager' ); ?>
                    </span>
                </div>

                <div style="display: flex; gap: 8px;">
                    <?php if ( $is_admin ) : ?>
                        <button type="button" class="ifs-btn ifs-btn-secondary" style="height: 36px; font-size: 12px;" onclick="ifsOpenAddItemModal()">
                            <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Product', 'swimming-pool-manager' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="ifs-catalog-grid">
                <?php if ( ! empty( $catalog ) ) : ?>
                    <?php foreach ( $catalog as $item ) : ?>
                        <div class="ifs-item-card" onclick="ifsAddToCart(<?php echo (int) $item->id; ?>, '<?php echo esc_js( $item->item_name ); ?>', <?php echo (float) $item->price; ?>, <?php echo (int) $item->stock_qty; ?>)">
                            <div>
                                <span class="ifs-item-cat"><?php echo esc_html( $item->category ); ?></span>
                                <div class="ifs-item-name"><?php echo esc_html( $item->item_name ); ?></div>
                            </div>
                            <div class="ifs-item-bottom">
                                <span class="ifs-item-price ifs-mono"><?php echo esc_html( $currency . ' ' . number_format( (float) $item->price, 2 ) ); ?></span>
                                <span class="ifs-item-stock"><?php echo esc_html( (string) $item->stock_qty ); ?> in stock</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div style="grid-column: 1 / -1; text-align: center; color: #64748b; padding: 40px 10px;">
                        <?php esc_html_e( 'No retail items available in catalog.', 'swimming-pool-manager' ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Interactive Order Cart & Checkout -->
        <div class="ifs-card">
            <div class="ifs-card-header">
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-cart"></span>
                    <?php esc_html_e( 'Current Order Ticket', 'swimming-pool-manager' ); ?>
                </h3>
                <button type="button" onclick="ifsClearCart()" style="background: none; border: none; color: #ef4444; font-size: 11.5px; font-weight: 700; cursor: pointer;">
                    <?php esc_html_e( 'Clear Cart', 'swimming-pool-manager' ); ?>
                </button>
            </div>

            <div class="ifs-cart-dock">
                <!-- Cart Items Stream -->
                <div class="ifs-cart-list" id="ifsCartContainer">
                    <div id="ifsEmptyCartText" style="text-align: center; color: #64748b; padding: 40px 10px; font-size: 13px;">
                        <span class="dashicons dashicons-cart" style="font-size: 28px; width: 28px; height: 28px; color: #cbd5e1;"></span>
                        <p style="margin-top: 6px;"><?php esc_html_e( 'Cart is empty. Select products on the left.', 'swimming-pool-manager' ); ?></p>
                    </div>
                </div>

                <!-- Total Amount Display -->
                <div class="ifs-cart-total-box">
                    <span><?php esc_html_e( 'Grand Total:', 'swimming-pool-manager' ); ?></span>
                    <span class="ifs-mono" id="ifsCartTotalDisplay" style="color: #10b981; font-size: 20px;">
                        <?php echo esc_html( $currency . ' 0.00' ); ?>
                    </span>
                </div>

                <!-- Checkout Form -->
                <form method="POST" action="<?php echo esc_url( $base_url ); ?>" onsubmit="return ifsValidateCheckout()">
                    <input type="hidden" name="ifs_pms_action" value="retail_checkout">
                    <input type="hidden" name="cart_data" id="ifsCartDataInput" value="[]">
                    <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Payment Tender *', 'swimming-pool-manager' ); ?></label>
                        <select name="payment_method" class="ifs-select">
                            <option value="Cash"><?php esc_html_e( 'Cash Drawer', 'swimming-pool-manager' ); ?></option>
                            <option value="Card"><?php esc_html_e( 'POS Credit / Debit Card', 'swimming-pool-manager' ); ?></option>
                            <option value="bKash / Mobile"><?php esc_html_e( 'Mobile Banking / QR', 'swimming-pool-manager' ); ?></option>
                            <option value="Room Charge"><?php esc_html_e( 'Post to Room Guest Folio', 'swimming-pool-manager' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-form-group">
                        <label><?php esc_html_e( 'Link Pass / Ticket Code (Optional)', 'swimming-pool-manager' ); ?></label>
                        <input type="text" name="ticket_code" class="ifs-input ifs-mono" placeholder="e.g. IFS-OCT-05-0001">
                    </div>

                    <div style="margin-top: 14px;">
                        <button type="submit" class="ifs-btn ifs-btn-primary" style="width: 100%; height: 46px;">
                            <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Complete Sale & Print Slip', 'swimming-pool-manager' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. Today's Retail Sales Activity Summary -->
    <div class="ifs-card">
        <div class="ifs-card-header">
            <div>
                <h3 class="ifs-card-title" style="font-size: 15px;">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e( 'Today Retail Sales Stream (Recent 5)', 'swimming-pool-manager' ); ?>
                </h3>
            </div>
            <span class="ifs-pill-tag ifs-status-success">
                <?php echo esc_html( sprintf( __( 'Today Intake: %s %s (%d sales)', 'swimming-pool-manager' ), $currency, number_format_i18n( $today_retail_rev, 2 ), $today_retail_count ) ); ?>
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: var(--ifs-surface-hover, #f8fafc); border-bottom: 1.5px solid var(--ifs-border-subtle, #e2e8f0);">
                        <th style="padding: 10px 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; color: #64748b;"><?php esc_html_e( 'Order Ref', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; color: #64748b;"><?php esc_html_e( 'Items Purchased', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; color: #64748b;"><?php esc_html_e( 'Tender', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; color: #64748b;"><?php esc_html_e( 'Amount', 'swimming-pool-manager' ); ?></th>
                        <th style="padding: 10px 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; color: #64748b;"><?php esc_html_e( 'Sold At', 'swimming-pool-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $recent_orders ) ) : ?>
                        <?php foreach ( $recent_orders as $ord ) : ?>
                            <tr style="border-bottom: 1px solid var(--ifs-border-subtle, #f1f5f9);">
                                <td style="padding: 10px 12px;"><strong class="ifs-mono" style="color: #0284c7;"><?php echo esc_html( $ord->order_code ); ?></strong></td>
                                <td style="padding: 10px 12px; max-width: 300px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"><?php echo esc_html( $ord->items_summary ); ?></td>
                                <td style="padding: 10px 12px;"><span class="ifs-pill-tag ifs-status-success" style="background: #f1f5f9; color: #334155;"><?php echo esc_html( $ord->payment_method ); ?></span></td>
                                <td style="padding: 10px 12px;"><strong class="ifs-mono"><?php echo esc_html( $currency . ' ' . number_format_i18n( (float) $ord->total_amount, 2 ) ); ?></strong></td>
                                <td style="padding: 10px 12px; font-size: 11.5px; color: #64748b;" class="ifs-mono"><?php echo esc_html( $ord->sold_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 24px;"><?php esc_html_e( 'No retail transactions recorded yet today.', 'swimming-pool-manager' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add New Product -->
<div class="ifs-modal-backdrop" id="ifsAddProductModal">
    <div class="ifs-modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 class="ifs-card-title" style="font-size: 15px;">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e( 'Add Retail / F&B Product', 'swimming-pool-manager' ); ?>
            </h3>
            <button type="button" onclick="ifsCloseAddItemModal()" style="border: none; background: transparent; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <input type="hidden" name="ifs_pms_action" value="add_shop_item">
            <?php wp_nonce_field( 'ifs_pms_secure_action', 'ifs_pms_action_nonce' ); ?>

            <div class="ifs-form-group">
                <label><?php esc_html_e( 'Product Name *', 'swimming-pool-manager' ); ?></label>
                <input type="text" name="item_name" class="ifs-input" placeholder="e.g. Waterproof Ear Plugs" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="ifs-form-group">
                    <label><?php esc_html_e( 'Category', 'swimming-pool-manager' ); ?></label>
                    <select name="category" class="ifs-select">
                        <option value="Gear"><?php esc_html_e( 'Swim Gear', 'swimming-pool-manager' ); ?></option>
                        <option value="Apparel"><?php esc_html_e( 'Apparel / Costume', 'swimming-pool-manager' ); ?></option>
                        <option value="F&B"><?php esc_html_e( 'Food & Beverage', 'swimming-pool-manager' ); ?></option>
                        <option value="Accessories"><?php esc_html_e( 'Accessories', 'swimming-pool-manager' ); ?></option>
                    </select>
                </div>

                <div class="ifs-form-group">
                    <label><?php esc_html_e( 'Price (' . $currency . ') *', 'swimming-pool-manager' ); ?></label>
                    <input type="number" step="0.01" name="price" class="ifs-input ifs-mono" placeholder="250.00" required>
                </div>
            </div>

            <div class="ifs-form-group">
                <label><?php esc_html_e( 'Initial Stock Quantity', 'swimming-pool-manager' ); ?></label>
                <input type="number" name="stock_qty" class="ifs-input ifs-mono" value="50" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                <button type="button" class="ifs-btn ifs-btn-secondary" onclick="ifsCloseAddItemModal()"><?php esc_html_e( 'Cancel', 'swimming-pool-manager' ); ?></button>
                <button type="submit" class="ifs-btn ifs-btn-primary"><?php esc_html_e( 'Save to Catalog', 'swimming-pool-manager' ); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Fast Client-Side Cart Engine -->
<script>
var ifsCart = [];
var ifsCurrency = '<?php echo esc_js( $currency ); ?>';

function ifsAddToCart(id, name, price, stock) {
    if (stock <= 0) {
        alert('Item is currently out of stock!');
        return;
    }

    var existing = ifsCart.find(function(item) { return item.id === id; });
    if (existing) {
        if (existing.qty < stock) {
            existing.qty++;
        } else {
            alert('Cannot add more than available in stock (' + stock + ')');
        }
    } else {
        ifsCart.push({ id: id, name: name, price: price, qty: 1, maxStock: stock });
    }
    ifsRenderCart();
}

function ifsUpdateQty(id, delta) {
    var item = ifsCart.find(function(i) { return i.id === id; });
    if (!item) return;

    item.qty += delta;
    if (item.qty <= 0) {
        ifsCart = ifsCart.filter(function(i) { return i.id !== id; });
    } else if (item.qty > item.maxStock) {
        item.qty = item.maxStock;
        alert('Reached maximum available stock limit');
    }
    ifsRenderCart();
}

function ifsClearCart() {
    ifsCart = [];
    ifsRenderCart();
}

function ifsRenderCart() {
    var container = document.getElementById('ifsCartContainer');
    var totalEl   = document.getElementById('ifsCartTotalDisplay');
    var dataInput = document.getElementById('ifsCartDataInput');

    if (ifsCart.length === 0) {
        container.innerHTML = '<div id="ifsEmptyCartText" style="text-align: center; color: #64748b; padding: 40px 10px; font-size: 13px;"><span class="dashicons dashicons-cart" style="font-size: 28px; width: 28px; height: 28px; color: #cbd5e1;"></span><p style="margin-top: 6px;">Cart is empty. Select products on the left.</p></div>';
        totalEl.textContent = ifsCurrency + ' 0.00';
        dataInput.value = '[]';
        return;
    }

    var total = 0;
    var html = '';

    ifsCart.forEach(function(item) {
        var subtotal = item.price * item.qty;
        total += subtotal;

        html += '<div class="ifs-cart-row">' +
                    '<div>' +
                        '<div style="font-size: 13px; font-weight: 700;">' + item.name + '</div>' +
                        '<div class="ifs-mono" style="font-size: 11px; color: #64748b;">' + ifsCurrency + ' ' + item.price.toFixed(2) + ' each</div>' +
                    '</div>' +
                    '<div style="display: flex; align-items: center; gap: 14px;">' +
                        '<div class="ifs-qty-ctrl">' +
                            '<button type="button" class="ifs-qty-btn" onclick="ifsUpdateQty(' + item.id + ', -1)">-</button>' +
                            '<span class="ifs-mono" style="font-size: 13px; font-weight: 800; min-width: 18px; text-align: center;">' + item.qty + '</span>' +
                            '<button type="button" class="ifs-qty-btn" onclick="ifsUpdateQty(' + item.id + ', 1)">+</button>' +
                        '</div>' +
                        '<strong class="ifs-mono" style="font-size: 13.5px; min-width: 65px; text-align: right;">' + ifsCurrency + ' ' + subtotal.toFixed(2) + '</strong>' +
                    '</div>' +
                '</div>';
    });

    container.innerHTML = html;
    totalEl.textContent = ifsCurrency + ' ' + total.toFixed(2);
    dataInput.value = JSON.stringify(ifsCart);
}

function ifsValidateCheckout() {
    if (ifsCart.length === 0) {
        alert('Cart is empty! Please select at least one item.');
        return false;
    }
    return true;
}

function ifsOpenAddItemModal() {
    document.getElementById('ifsAddProductModal').classList.add('active');
}
function ifsCloseAddItemModal() {
    document.getElementById('ifsAddProductModal').classList.remove('active');
}
</script>