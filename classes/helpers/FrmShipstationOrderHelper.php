<?php

class FrmShipstationOrderHelper {

    protected $api;
    protected $orderModel;

    public function __construct(
    ) {
        $this->api = new FrmShipstationOrderApi();
        $this->orderModel = new FrmShipstationOrderModel();
    }

    public function updateOrdersApi( array $params = [] ) {

        // Get orders from ShipStation API
        $ordersRes = $this->api->getOrders(
            array_merge(
                $params, 
                ['pageSize' => 500, 'sortBy' => 'OrderDate', 'sortDir' => 'DESC']
            )
        ); 
        $orders = $ordersRes['items'] ?? [];

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
        return $this->orderModel->multipleUpdateCreate( $ordersProcessed );

    }

}