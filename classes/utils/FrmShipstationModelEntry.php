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
                'carrier_code'      => $order['carrierCode'] ?? '',
                'service_code'      => $order['serviceCode'] ?? '',
                'package_code'      => $order['packageCode'] ?? '',
                'created_at'        => $order['createDate'] ?? '',
                'updated_at'        => $order['modifyDate'] ?? '',
                'paid_at'           => $order['paymentDate'] ?? '',
                'ship_date'         => $order['shipDate'] ?? '',
            ];

        }

        // Update records
        return $orderModel->multipleUpdateCreate( $ordersProcessed );

    }

    // Update carriers
    public function updateCarriersApi() {

        $api = new FrmShipstationApi();
        $carrierModel = new FrmShipstationCarrierModel();
        $serviceModel = new FrmShipstationServiceModel();
        $packageModel = new FrmShipstationPackageModel();

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

        // Prepare carriers for update/create
        $carriersProcessed = [];
        $services = [];
        foreach( $carriers as $carrier ) {

            $carriersProcessed[] = [
                'name' => $carrier['name'] ?? '',
                'code' => $carrier['code'] ?? '',
                'account_number' => $carrier['accountNumber'] ?? '',
                'balance' => $carrier['balance'] ?? 0,
                'is_primary' => $carrier['primary'],
                'is_req_funded' => $carrier['requiresFundedAccount'],
            ];

            // Add services
            if( ! empty($carrier['services']) ) {
                $services = array_merge( $services, $carrier['services'] );
            }

            // Add packages
            if( ! empty($carrier['packages']) ) {
                $packages = array_merge( $packages, $carrier['packages'] ); 
            }

        }

        // Update records
        $res[] = $carrierModel->multipleUpdateCreate( $carriersProcessed  );


        // Prepare and update services
        $servicesProcessed = [];
        foreach( $services as $service ) {
            $servicesProcessed[] = [
                'code' => $service['code'] ?? '',
                'carrier_code' => $service['carrierCode'] ?? '',
                'name' => $service['name'] ?? '',
                'is_domestic' => $service['domestic'],
                'is_international' => $service['international'],
            ];
        }
        $res[] = $serviceModel->multipleUpdateCreate( $servicesProcessed );


        // Prepare and update packages
        $packagesProcessed = [];
        foreach( $packages as $package ) {
            $packagesProcessed[] = [
                'code' => $package['code'] ?? '',
                'carrier_code' => $package['carrierCode'] ?? '',
                'name' => $package['name'] ?? '',
                'is_domestic' => $package['domestic'],
                'is_international' => $package['international'],
            ];
        }
        $res[] = $packageModel->multipleUpdateCreate( $packagesProcessed );

        return $res;

    }

    public function updateShipmentsApi() {

        $api = new FrmShipstationApi();
        $shipmentModel = new FrmShipstationShipmentModel();

        // Get orders from ShipStation API
        $shipmentsRes = $api->listShipments(['pageSize' => 500, 'sortBy' => 'OrderDate', 'sortDir' => 'DESC']); 
        $shipments = $shipmentsRes['shipments'] ?? [];

        // Prepare orders for update/create
        $shipmentsProcessed = [];
        foreach( $shipments as $item ) {

            $shipmentsProcessed[] = [
                'shp_order_id'      => $item['orderId'] ?? 0,
                'shp_order_number'  => $item['orderNumber'] ?? '',
                'shipment_id'      => $item['shipmentId'] ?? 0,
                'entry_id'          => 0,
                'shipment_cost'    => $item['shipmentCost'] ?? 0,
                'insurance_cost'    => $item['insuranceCost'] ?? 0,
                'tracking_number'   => $item['trackingNumber'] ?? '',
                'carrier_code'      => $item['carrierCode'] ?? '',
                'service_code'      => $item['serviceCode'] ?? '',
                'package_code'      => $item['packageCode'] ?? '',
                'is_voided'         => $item['voided'] ?? false,
                'voided_at'        => $item['voidDate'] ?? '',
                'ship_to'           => !empty($item['shipTo']) ? json_encode($item['shipTo']) : '',
                'weight'           => !empty($item['weight']) ? json_encode($item['weight']) : '',
                'dimensions'       => !empty($item['dimensions']) ? json_encode($item['dimensions']) : '',
                'created_at'        => $item['createDate'] ?? '',
                'updated_at'        => $item['modifyDate'] ?? '',
                'shipped_at'         => $item['shipDate'] ?? '',
            ];

        }

        // Update records
        return $shipmentModel->multipleUpdateCreate( $shipmentsProcessed );

    }

}