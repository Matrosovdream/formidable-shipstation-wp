<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationModelEntry {

    public function __construct() {

    }

    public function updateOrdersApi() {

        $api = new FrmShipstationApi();
        $orderModel = new FrmShipstationOrderModel();

        // Get orders from ShipStation API
        $ordersRes = $api->listOrders(['pageSize' => 500, 'sortBy' => 'OrderDate', 'sortDir' => 'DESC']); 
        $orders = $ordersRes['orders'] ?? [];

        // Prepare orders for update/create
        $ordersProcessed = [];
        foreach( $orders as $order ) {

            $ordersProcessed[] = [
                'shp_order_id'      => $order['orderId'] ?? 0,
                'shp_order_number'  => $order['orderNumber'] ?? '',
                'entry_id'          => 0,
                'order_status'      => $order['orderStatus'] ?? '',
                'total'             => $order['orderTotal'] ?? 0,
                'shipping_total'    => $order['shippingAmount'] ?? 0,
                'carrier_code'      => '',
                'service_code'      => '',
                'created_at'        => $order['createDate'] ?? '',
                'updated_at'        => $order['modifyDate'] ?? '',
                'paid_at'           => $order['paymentDate'] ?? '',
            ];

        }

        // Update records
        return $orderModel->multipleUpdateCreate( $ordersProcessed  );

    }

    // Update carriers
    public function updateCarriersApi() {

        $api = new FrmShipstationApi();
        $carrierModel = new FrmShipstationCarrierModel();

        // Get carriers from ShipStation API
        $carriers = $api->listCarriers();

        foreach( $carriers as $key=>$carrier ) {

            // Get carrier services
            $services = $api->listCarrierServices(['carrierCode' => $carrier['code'] ?? '']);
            $carrier['services'] = $services;

            // Get carrier packages
            $packages = $api->listCarrierPackages(['carrierCode' => $carrier['code'] ?? '']);
            $carrier['packages'] = $packages;

            $carriers[$key] = $carrier;

        }

        echo '<pre>';
        print_r($carriers);
        echo '</pre>';
        die();

        // Prepare carriers for update/create
        $carriersProcessed = [];
        foreach( $carriers as $carrier ) {

            $carriersProcessed[] = [
                'name' => $carrier['name'] ?? '',
                'code' => $carrier['code'] ?? '',
                'account_number' => $carrier['accountNumber'] ?? '',
                'balance' => $carrier['balance'] ?? 0,
                'is_primary' => $carrier['primary'],
                'is_req_funded' => $carrier['requiresFundedAccount'],
            ];

        }

        // Update records
        return $carrierModel->multipleUpdateCreate( $carriersProcessed  );

    }

}