<?php

class FrmShipstationOrderApi extends FrmShipstationAbstractApi {


    /** 
     * Retrieve a list of orders.
    */
    public function getOrders( array $params = [], bool $autoPaginate = true ) {

        $allowedParams = [
            'orderNumber','customerName','orderStatus','paymentStatus','storeId','tagId',
            'orderDateStart','orderDateEnd','createDateStart','createDateEnd',
            'modifyDateStart','modifyDateEnd',
        ];

        return $this->getListing(
            $params,
            $allowedParams,
            '/orders',
            'orders',
            $autoPaginate
        );
    }

    /**
     * Get order information
    */
    public function getOrder( array $args ) {

        if ( ! empty( $args['id'] ) ) {
            return $this->request( 'GET', '/orders/' . (int) $args['id'] );
        }
        if ( ! empty( $args['number'] ) ) {
            $out = $this->request( 'GET', '/orders', [ 'orderNumber' => (string) $args['number'] ] );
            // /orders returns an envelope { orders: [], total: n }
            if ( is_array( $out ) && isset( $out['orders'] ) ) {
                return $out['orders'];
            }
            return $out;
        }
        return new WP_Error( 'shipstation_order_param', __( 'Provide order "id" or "number".', 'shipstation-wp' ), [ 'status' => 400 ] );

    }

    /**
     * Create label for order
    */
    public function createLabelForOrder( array $params ) {
        $order = null;
        $order_id = null;
        
        
        if ( ! empty( $params['order_id'] ) ) {
            $order_id = (int) $params['order_id'];
            $order = $this->getOrder( [ 'id' => $order_id ] );
        } elseif ( ! empty( $params['order_number'] ) ) {
            $orders = $this->getOrder( [ 'number' => (string) $params['order_number'] ] );
        if ( is_array( $orders ) && ! empty( $orders[0]['orderId'] ) ) {
            $order = $orders[0];
            $order_id = (int) $order['orderId'];
        }
        } else {
            return new WP_Error( 'shipstation_label_param', __( 'Provide order_id or order_number.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        
        
        if ( is_wp_error( $order ) || empty( $order ) || empty( $order_id ) ) {
            return new WP_Error( 'shipstation_order_not_found', __( 'Order not found.', 'shipstation-wp' ), [ 'status' => 404 ] );
        }
        
        
        $carrier = (string) ( $params['carrier_code'] ?? $this->defaultCarrierCode );
        $service = (string) ( $params['service_code'] ?? $this->defaultServiceCode );
        $package = (string) ( $params['package_code'] ?? 'package' );
        $confirm = (string) ( $params['confirmation'] ?? $this->defaultConfirmation );
        
        
        // Weight can be provided as ['value'=>..,'units'=>..] or separate value/units
        $weight = $params['weight'] ?? null;
        if ( ! is_array( $weight ) ) {
            $weight = [
                'value' => isset( $params['weight_value'] ) ? (float) $params['weight_value'] : 0.0,
                'units' => (string) ( $params['weight_units'] ?? 'ounces' ),
            ];
        }
        
        if ( $carrier === '' || $service === '' ) {
            return new WP_Error( 'shipstation_missing_defaults', __( 'Carrier/Service are required (set defaults or pass overrides).', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        
        
        $shipment = [
            'orderId' => $order_id,
            'carrierCode' => $carrier,
            'serviceCode' => $service,
            'packageCode' => $package,
            'confirmation'=> $confirm,
            'shipTo' => $order['shipTo'] ?? ( $order['advancedOptions']['shipTo'] ?? null ),
            'shipFrom' => $order['shipFrom'] ?? ( $order['advancedOptions']['shipFrom'] ?? null ),
            'weight' => $weight,
        ];
        
        
        if ( $this->defaultInsurance && $this->defaultInsuranceAmount > 0 ) {
            $shipment['insuranceOptions'] = [ 'insureShipment' => true, 'insuredValue' => $this->defaultInsuranceAmount ];
        }

        return $this->request( 'POST', '/orders/createlabelfororder', [], [ 'shipment' => $shipment ] );
        
    }

}