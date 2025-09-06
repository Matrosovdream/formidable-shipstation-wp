<?php
/*
Plugin Name: Formidable forms Extension - Shipstation API
Description: 
Version: 1.0
Plugin URI: 
Author URI: 
Author: Stanislav Matrosov
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables
define('FRM_SHP_BASE_URL', __DIR__);

// Initialize core
require_once 'classes/FrmShipstationInit.php';


add_action('init', 'FrmShipstationInit');
function FrmShipstationInit() {
    
    if( isset( $_GET['logg'] ) ) {

        //setTest(); die();

        $orderNumber = 100001;

        $api = new FrmShipstationApi(); // or frm_shipstation();

        $carriers = $api->listCarriers();

        $order = $api->getOrder(['number' => $orderNumber])[0] ?? [];

        //$shipments = $api->getShipmentsByOrder(['id' => $order['orderId'] ?? 0]);

        //$labels = $api->getLabelsByOrder(['number' => $order['orderId']], true);

        $label = $api->createLabelForOrder([
            'order_number' => $orderNumber,
            'carrier_code' => $carriers[0]['code'],
            'service_code' => 'usps_priority_mail',
            'package_code' => 'package',
            'weight_value' => 16,
            'weight_units' => 'ounces',
            'test_label'   => true
        ]);

        echo '<pre>';
        print_r($label);
        echo '</pre>';

        echo '<pre>';
        print_r($carriers);
        echo '</pre>';

        echo '<pre>';
        print_r($shipments);
        echo '</pre>';

        echo '<pre>';
        print_r($order);
        echo '</pre>';
        die();

    }

}

function setTest() {

    $apiKey = 'd882c6d094994ab4a6b56e9e7374f39c';
    $apiSecret = '1f581f70d62c4ec4902d7e62a42aa307';

    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => "https://ssapi.shipstation.com/orders/createlabelfororder",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS =>"{\n  \"orderId\": 133050581,\n  \"carrierCode\": \"fedex\",\n  \"serviceCode\": \"fedex_2day\",\n  \"packageCode\": \"package\",\n  \"confirmation\": null,\n  \"shipDate\": \"2014-04-03\",\n  \"weight\": {\n    \"value\": 2,\n    \"units\": \"pounds\"\n  },\n  \"dimensions\": null,\n  \"insuranceOptions\": null,\n  \"internationalOptions\": null,\n  \"advancedOptions\": null,\n  \"testLabel\": false\n}",
    CURLOPT_HTTPHEADER => array(
        "Host: ssapi.shipstation.com",
        "Authorization" => "Basic " . base64_encode( $apiKey . ':' . $apiSecret ),
        "Content-Type: application/json"
    ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;


}