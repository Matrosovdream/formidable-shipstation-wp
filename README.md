<div align="center">

<img src="https://raw.githubusercontent.com/your-org/your-repo/main/.github/assets/shipstation-wp-banner.png" alt="WordPress ShipStation Plugin" width="820"/>

<h1>🚚 WordPress ShipStation API Plugin</h1>

<p>
<a href="https://wordpress.org/" target="_blank"><img src="https://img.shields.io/badge/WordPress-6.5%2B-21759B?logo=wordpress&logoColor=white" alt="WordPress 6.5+"/></a>
<a href="#"><img src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php&logoColor=white" alt="PHP 8.1+"/></a>
<a href="#"><img src="https://img.shields.io/badge/License-MIT-00b894" alt="MIT License"/></a>
<a href="#"><img src="https://img.shields.io/badge/Tests-GitHub%20Actions-informational?logo=github" alt="Tests"/></a>
<a href="#"><img src="https://img.shields.io/badge/Coverage-100%25-brightgreen" alt="Coverage"/></a>
</p>

<i>Create orders, print labels, and track shipments via the official ShipStation API—right from WordPress.</i>

</div>

---

## ✨ Features

* **Create ShipStation Orders** from WordPress (programmatic, REST, or WP‑CLI)
* **Print/Buy Labels** and download PDFs
* **Live Tracking** via shortcode and REST endpoint
* **Webhook Support** (order shipped, label created, delivery status)
* **Admin UI** for API credentials and defaults (service, confirmation, insurance)
* **Logs & Retry** with error surfacing in the dashboard
* **WooCommerce‑optional**: works standalone or with WooCommerce

> ✅ Designed for performance and large catalogs; works with caching and object cache.

---

## 🧩 Requirements

* WordPress **6.5+**
* PHP **8.1+** with `curl` and `json`
* HTTPS recommended
* (Optional) WooCommerce **8.0+**

---

## 🔧 Installation

### 1) From source (recommended during development)

```bash
cd wp-content/plugins
git clone https://github.com/your-org/shipstation-wp.git
wp plugin activate shipstation-wp
```

### 2) From ZIP (production)

1. Download `shipstation-wp.zip`
2. WordPress Admin → **Plugins → Add New → Upload Plugin**
3. Activate **ShipStation API** plugin

---

## ⚙️ Configuration

After activation, go to **Settings → ShipStation** and fill in:

* **API Key** & **API Secret**
* **Default Store ID** (if applicable)
* **Default Carrier/Service** (e.g., USPS Priority, UPS Ground)
* **Signature/Confirmation** & **Insurance** defaults
* **Webhook Callback URL** (auto‑generated; copy into ShipStation)

### Secure via `wp-config.php` (recommended)

```php
// wp-config.php
define( 'SHIPSTATION_API_KEY',    'your_key' );
define( 'SHIPSTATION_API_SECRET', 'your_secret' );
// Optional overrides
define( 'SHIPSTATION_API_BASE',   'https://ssapi.shipstation.com' );
```

When constants are defined, the settings screen hides those fields.

---

## 🏗️ Plugin Structure

```
shipstation-wp/
├─ shipstation-wp.php          # Plugin bootstrap
├─ src/
│  ├─ Admin/                   # Settings & UI
│  ├─ API/                     # REST routes (v1)
│  ├─ Services/ShipStation.php # HTTP client wrapper
│  ├─ Orders/                  # Order builders/adapters
│  ├─ Labels/                  # Label purchase/print
│  ├─ Tracking/                # Tracking + webhooks
│  └─ Helpers/                 # Utilities & logging
├─ includes/
│  └─ functions.php
├─ assets/
│  └─ admin.css/js
├─ languages/
├─ readme.txt                  # WP.org readme (optional)
└─ README.md                   # This file
```

---

## 🚀 Quick Start

### Create an order (PHP)

```php
// Anywhere in your theme/plugin code
$order_id = apply_filters( 'shipstation_wp_create_order', 0, [
  'orderNumber'   => 'WP-10001',
  'orderDate'     => gmdate('c'),
  'orderStatus'   => 'awaiting_shipment',
  'customerEmail' => 'jane@example.com',
  'billTo'        => [ 'name' => 'Jane Doe' ],
  'shipTo'        => [
    'name'     => 'Jane Doe',
    'street1'  => '123 Main St',
    'city'     => 'Austin',
    'state'    => 'TX',
    'postalCode' => '78701',
    'country'  => 'US',
    'phone'    => '555-555-5555',
  ],
  'items'         => [
    [ 'sku' => 'SKU-ABC', 'name' => 'Widget', 'quantity' => 2, 'unitPrice' => 19.99 ],
  ],
]);
```

### Buy/print a label (PHP)

```php
$label = apply_filters( 'shipstation_wp_create_label', null, [
  'orderId'     => $order_id,
  'carrierCode' => 'stamps_com',
  'serviceCode' => 'usps_priority_mail',
  'packageCode' => 'package',
  'weight'      => [ 'value' => 16, 'units' => 'ounces' ],
]);

// Save label PDF
if ( $label && isset( $label['labelData'] ) ) {
  $pdf = base64_decode( $label['labelData'] );
  file_put_contents( WP_CONTENT_DIR . "/uploads/labels/{$label['shipmentId']}.pdf", $pdf );
}
```

### Track a shipment (PHP)

```php
$tracking = apply_filters( 'shipstation_wp_get_tracking', null, [
  'carrierCode'   => 'stamps_com',
  'trackingNumber'=> '9400111899223197428499',
]);
```

---

## 🧰 REST API

Base: `/wp-json/shipstation/v1`

| Method | Endpoint   | Body/Params                     | Description        |
| -----: | ---------- | ------------------------------- | ------------------ |
|   POST | `/order`   | ShipStation order payload       | Create order       |
|   POST | `/label`   | `{ orderId, carrierCode, ... }` | Buy & return label |
|    GET | `/track`   | `carrierCode, trackingNumber`   | Get tracking info  |
|   POST | `/webhook` | ShipStation webhook payload     | Webhook receiver   |

> **Auth**: Uses WordPress nonce/cookie for logged‑in users. For server‑to‑server, enable application passwords or token auth via filter (see below).

---

## ⌨️ WP‑CLI

```bash
# Create order from JSON file
wp shipstation order create ./order.json

# Buy label for an existing ShipStation orderId
wp shipstation label create 123456789 --carrier=stamps_com --service=usps_priority_mail

# Track a package
wp shipstation track 9400111899223197428499 --carrier=stamps_com
```

---

## 🧩 Shortcodes

```text
[shipstation_tracking number="9400111899223197428499" carrier="stamps_com"]
```

Renders a compact tracking widget (status, checkpoints, ETA).

---

## 🔌 Actions & Filters

**Filters**

* `shipstation_wp_request_args( array $args, string $endpoint )` — alter HTTP args before API call
* `shipstation_wp_build_order_payload( array $payload, array $context )` — modify outgoing order payload
* `shipstation_wp_auth_provider( $auth )` — swap auth mechanism (e.g., token/header)

**Actions**

* `shipstation_wp_before_create_order( array $payload )`
* `shipstation_wp_after_create_order( array $response, array $payload )`
* `shipstation_wp_label_generated( array $label, array $request )`
* `shipstation_wp_tracking_updated( array $tracking )`
* `shipstation_wp_webhook_received( string $topic, array $body )`

---

## 🪵 Logging

* Logs to **Tools → Site Health → Debug Log** (if `WP_DEBUG_LOG` enabled)
* Optional per‑plugin log at `wp-content/uploads/shipstation-wp/shipstation.log`

```php
define( 'SHIPSTATION_WP_LOG', true );
```

---

## 🔐 Security Notes

* Store credentials in `wp-config.php` when possible
* Restrict REST access (capability `manage_options` by default)
* Webhook endpoint validates ShipStation signature (HMAC)

---

## 🧪 Testing

* Unit tests via **PHPUnit** (`/tests`)
* Integration tests via **WP‑CLI + Mock HTTP**

```bash
composer install
composer test
```

---

## 🧭 Roadmap

* [ ] Bulk order sync from custom post types
* [ ] Carrier/service presets per country
* [ ] Admin label printing queue
* [ ] Gutenberg blocks for tracking widgets
* [ ] Background retries with exponential backoff

---

<div align="center">
  <sub>Made with ❤️ for WordPress & ShipStation</sub>
</div>
