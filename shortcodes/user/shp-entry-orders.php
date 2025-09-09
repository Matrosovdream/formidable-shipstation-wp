<?php

add_shortcode('shp-entry-orders', function ($atts) {
    $atts = shortcode_atts(['order' => ''], $atts, 'shp-orders');
    $order_number = trim((string)$atts['order']);
    if ($order_number === '') {
        return '<div class="shp-orders-error">Order param is required.</div>';
    }

    if (!class_exists('FrmShipstationOrderModel')) {
        return '<div class="shp-orders-error">Order model not found.</div>';
    }

    try {
        $orderModel = new FrmShipstationOrderModel();
        $orders     = $orderModel->getByOrdersNumber($order_number);
    } catch (Throwable $e) {
        return '<div class="shp-orders-error">Failed to load order data.</div>';
    }

    if (empty($orders) || !is_array($orders)) {
        return '<div class="shp-orders-empty">No orders found for #' . esc_html($order_number) . '.</div>';
    }

    // Enqueue assets for this render
    wp_enqueue_style('frm-shipstation');
    wp_enqueue_script('frm-shipstation-shortcodes');

    // Per-instance data (kept in DOM attributes so many shortcodes can coexist)
    $nonce   = wp_create_nonce('shp_void_shipment');
    $wrap_id = 'shp-orders-' . wp_generate_uuid4();

    ob_start();
    ?>
    <div id="<?php echo esc_attr($wrap_id); ?>" class="shp-orders-wrap" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
        <?php foreach ($orders as $o): ?>
            <div class="shp-order-card">
                <div class="shp-order-header">
                    <div class="shp-order-title">Order #<?php echo esc_html($o['shp_order_id']); ?></div>
                    <div class="shp-order-meta">
                        <span class="shp-badge"><?php echo esc_html($o['order_status']); ?></span>
                        <span class="shp-dot">•</span>
                        <span class="shp-date">Created at <?php echo shp_orders_format_date($o['created_at']); ?></span>
                    </div>
                </div>

                <div class="shp-shipments">
                    <div class="shp-shipments-title">Shipments</div>

                    <?php if (empty($o['shipments'])): ?>
                        <div class="shp-muted">No shipments yet.</div>
                    <?php else: ?>
                        <ul class="shp-shipments-list">
                            <?php foreach ($o['shipments'] as $s): ?>
                                <?php
                                    $shipment_id  = $s['shipment_id']  ?? '';
                                    $carrier_code = $s['carrier_code'] ?? '';
                                    $service_code = $s['service_code'] ?? '';
                                    $package_code = $s['package_code'] ?? '';
                                    $created_s    = $s['created_at']    ?? '';
                                    $shipped_s    = $s['shipped_at']    ?? '';
                                    $is_voided    = !empty($s['is_voided']);
                                    $voided_at    = $s['voided_at']     ?? '';
                                ?>
                                <li class="shp-shipment" data-shipment="<?php echo esc_attr($shipment_id); ?>">
                                    <div class="shp-shipment-line"><strong>carrier code:</strong> <?php echo esc_html($carrier_code); ?></div>
                                    <div class="shp-shipment-line"><strong>service_code:</strong> <?php echo esc_html($service_code); ?></div>
                                    <div class="shp-shipment-line"><strong>package_code:</strong> <?php echo esc_html($package_code); ?></div>
                                    <div class="shp-shipment-line"><strong>created_at:</strong> <?php echo shp_orders_format_date($created_s); ?></div>
                                    <div class="shp-shipment-line"><strong>shipped_at:</strong> <?php echo shp_orders_format_date($shipped_s); ?></div>
                                    <div class="shp-shipment-line">
                                        <strong>is voided:</strong>
                                        <span class="shp-void-state"><?php echo $is_voided ? 'yes' : 'no'; ?></span>
                                        <?php if ($is_voided): ?>
                                            <span class="shp-voided-at"> (voided_at: <?php echo shp_orders_format_date($voided_at); ?>)</span>
                                        <?php else: ?>
                                            <button type="button" 
                                                class="shp-void-btn" 
                                                data-shipment="<?php echo esc_attr($shipment_id); ?>">
                                                Void
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>

                        </ul>
                    <?php endif; ?>

                                                                                <!-- Add new label toggle + form (hidden by default; styles in CSS) -->
                                    <div class="shp-label-actions">
                                        <button
                                            type="button"
                                            class="shp-label-toggle"
                                            data-target="#label-form-<?php echo esc_attr($o['shp_order_id']); ?>"
                                            aria-expanded="false"
                                            aria-controls="label-form-<?php echo esc_attr($o['shp_order_id']); ?>"
                                        >
                                            + Add new label
                                        </button>
                                    </div>

                                    <form
                                        class="shp-label-form"
                                        id="label-form-<?php echo esc_attr($o['shp_order_id']); ?>"
                                        method="post"
                                        action=""
                                    >

                                        <input type="hidden" name="action" value="shp_create_label">
                                        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                                        <input type="hidden" name="order_number" value="<?php echo esc_attr($o['shp_order_id']); ?>">

                                        <div class="shp-form-grid">
                                            <div class="shp-form-field">
                                                <label for="carrier-<?php echo esc_attr($o['shp_order_id']); ?>">Carrier</label>
                                                <br/>
                                                <select id="carrier-<?php echo esc_attr($o['shp_order_id']); ?>" name="carrier_code" class="shp-select">
                                                    <option value="">Select carrier…</option>
                                                    <option value="fedex_walleted">FedEx (walleted)</option>
                                                    <option value="fedex">FedEx</option>
                                                    <option value="ups">UPS</option>
                                                    <option value="usps">USPS</option>
                                                </select>
                                            </div>

                                            <div class="shp-form-field">
                                                <label for="service-<?php echo esc_attr($o['shp_order_id']); ?>">Service</label>
                                                <br/>
                                                <select id="service-<?php echo esc_attr($o['shp_order_id']); ?>" name="service_code" class="shp-select">
                                                    <option value="">Select service…</option>
                                                    <option value="fedex_priority_overnight">Priority Overnight</option>
                                                    <option value="fedex_2day">2 Day</option>
                                                    <option value="ground">Ground</option>
                                                </select>
                                            </div>

                                            <div class="shp-form-field">
                                                <label for="package-<?php echo esc_attr($o['shp_order_id']); ?>">Package</label>
                                                <br/>
                                                <select id="package-<?php echo esc_attr($o['shp_order_id']); ?>" name="package_code" class="shp-select">
                                                    <option value="">Select package…</option>
                                                    <option value="fedex_envelope">FedEx Envelope</option>
                                                    <option value="package">Package</option>
                                                    <option value="pak">Pak</option>
                                                    <option value="tube">Tube</option>
                                                </select>
                                            </div>

                                            <div class="shp-form-field">
                                                <label for="shipdate-<?php echo esc_attr($o['shp_order_id']); ?>">Ship Date</label>
                                                <br/>
                                                <input
                                                    id="shipdate-<?php echo esc_attr($o['shp_order_id']); ?>"
                                                    type="date"
                                                    name="ship_date"
                                                    class="shp-input"
                                                    value=""
                                                />
                                            </div>
                                        </div>

                                        <div class="shp-form-actions">
                                            <button type="submit" class="shp-btn-primary">Create label</button>
                                            <button
                                                type="button"
                                                class="shp-btn-link shp-label-cancel"
                                                data-target="#label-form-<?php echo esc_attr($o['shp_order_id']); ?>"
                                            >Cancel</button>
                                        </div>
                                    </form>
                                    <!-- /Add new label -->

                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});