<?php

class FrmShipstationShipmentHelper {

    protected $shipmentApi;
    protected $shipmentModel;

    public function __construct() {
        $this->shipmentApi = new FrmShipstationShipmentApi();
        $this->shipmentModel = new FrmShipstationShipmentModel();
    }

    public function updateShipmentsApi() {

        // Get orders from ShipStation API
        $shipmentsRes = $this->shipmentApi->getShipments(['pageSize' => 500, 'sortBy' => 'OrderDate', 'sortDir' => 'DESC']); 
        $shipments = $shipmentsRes['items'] ?? [];

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
        return $this->shipmentModel->multipleUpdateCreate( $shipmentsProcessed );

    }

}