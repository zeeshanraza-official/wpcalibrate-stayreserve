# WPCalibrate StayReserve

> **Professional Direct Accommodation & Hotel Room Booking System for WordPress**

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/zeeshanraza-official/wpcalibrate-stayreserve)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777bb4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759b.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-HPOS%20Compatible-96588a.svg)](https://woocommerce.com)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)

---

## About

**WPCalibrate StayReserve** is an enterprise-grade accommodation reservation engine built for hotels, guest houses, B&Bs, boutique resorts, hostels, and holiday apartment rentals. 

The plugin is designed for complete operational autonomy: it owns its room inventory, date availability calculations, stay restrictions, and server-side pricing logic. **WooCommerce is strictly optional**—the core plugin functions completely for manual, offline, and direct bookings without WooCommerce, while offering a seamless checkout bridge when WooCommerce payment gateways (Stripe, PayPal, Mollie, etc.) are desired.

---

## Key Features

### 🏨 Inventory & Accommodation Management
- **Room Types vs. Physical Units**: Distinguish between sellable room categories (*Deluxe King, Ocean Suite*) and actual physical inventory units (*Room 101, Villa A*).
- **Amenities Taxonomy**: Categorize rooms with reusable amenities (*High-speed WiFi, Air Conditioning, Balcony, King Bed*).
- **Flexible Capacities**: Define adult capacity, child capacity, total occupancy, room size, and bed specifications.
- **Maintenance & Blocked Periods**: Block specific physical units or entire room types for maintenance or seasonal closures.

### 📅 Booking Engine & Availability Logic
- **Half-Open Stay Semantics `[check_in, check_out)`**: Checkout day is freed immediately for incoming check-ins, eliminating false turnover locks.
- **Double-Booking & Race Condition Protection**: Named MySQL critical-section locks prevent concurrent requests from reserving the same final unit.
- **15-Minute Temporary Holds**: Automatically reserves inventory while guests complete checkout, preventing cart-snipping and abandoned unit lockouts.
- **Advance Booking Controls**: Enforce minimum/maximum advance notice and minimum/maximum stay length rules.

### 💰 Server-Side Pricing Engine
- **Deterministic Rate Priority**:
  1. Explicit Date Overrides
  2. Seasonal Rate Ranges
  3. Day-of-week / Weekend Adjustments
  4. Base Nightly Room Rate
  5. Add-ons & Extras (per-stay, per-night, per-person)
  6. Promo Coupons (fixed discount or percentage)
  7. Configured Fees
  8. Taxes
- **Authoritative Integer Pricing**: Calculations are executed exclusively server-side in minor units (integer cents) to eliminate floating-point rounding errors.

### 👥 Guest Experience & Self-Management
- **7-Step Frontend Flow**: Search Form → Available Rooms → Room Configuration → Guest Details → Review → Payment/Submit → Confirmation.
- **Secure Customer Portal**: Logged-in guests view account bookings. Guest checkouts receive time-limited, cryptographically signed SHA-256 links to manage or cancel reservations without passwords.
- **Customizable Notifications**: Automated transactional emails for booking confirmations, customer cancellations, and admin alerts with safe token replacement.

### 🔌 Ecosystem & Integrations
- **Gutenberg Dynamic Blocks**: Server-rendered dynamic blocks for Stay Search, Room Results, Booking Flow, and Booking Management.
- **Elementor Widgets**: Native widgets for Stay Search, Stay Rooms, and Stay Booking (activates safely only when Elementor is active).
- **Optional WooCommerce Integration**: Single virtual accommodation bridge product, HPOS (`custom_order_tables`) compatibility declared, and idempotent order state synchronization.
- **REST API**: Namespaced endpoints under `/wp-json/wpcalibrate-stayreserve/v1` with input sanitization and explicit permission callbacks.
- **GDPR / Privacy Tools**: Integrated with WordPress core Personal Data Exporter and Personal Data Eraser / Anonymizer.

---

## Documentation & Setup Guide

### System Requirements
- **PHP**: 8.2 or 8.3 (PHP 8.4 ready)
- **WordPress**: 6.2 or higher (tested on WordPress 6.5+ / 7.1.3)
- **WooCommerce** *(Optional)*: 8.0 through 11.2+ (HPOS verified)
- **MySQL**: 5.7+ / MariaDB 10.3+

### Quick Start Workflow
1. **Define Room Types**: Go to `WPCalibrate → Room Types` and add your accommodation categories with photos, capacities, and base rates.
2. **Add Amenities**: Tag rooms with amenities under `WPCalibrate → Amenities`.
3. **Register Physical Units**: Go to `WPCalibrate → StayReserve → Physical Units` and add individual physical inventory units (e.g. Room 101, Room 102).
4. **Set Pricing Rules**: Under `Rates & Extras`, define seasonal date ranges, weekend surcharges, and guest add-ons.
5. **Configure Settings**: Under `Settings`, set your currency, check-in/out times, and cancellation policy.
6. **Publish Booking Pages**: Add Gutenberg blocks or shortcodes to your site pages.

### Shortcode Reference

| Shortcode | Description |
| :--- | :--- |
| `[wpcsr_search]` | Responsive date & guest availability search bar |
| `[wpcsr_rooms]` | Grid catalog of room types with live rates and amenities |
| `[wpcsr_booking]` | Complete 7-step booking wizard |
| `[wpcsr_manage_booking]` | Secure guest reservation self-management portal |

---

## Changelog

### [1.0.2] - 2026-10-13
- **Fixed**: Admin sidebar menu icon brightness. Switched to crisp solid `#f0f0f1` vector SVG at 100% opacity matching WordPress dashboard core icons.
- **Updated**: Package structure and GitHub automated release assets.

### [1.0.1] - 2026-10-13
- **Fixed**: Constrained oversized admin sidebar logo to strict 20px x 20px dimensions.
- **Fixed**: Resolved responsive layout break on the "Marketplace" contact card in Support tab.
- **Added**: Comprehensive "Overview" tab in WPCalibrate StayReserve admin screen detailing plugin purpose, methods, and setup workflow.

### [1.0.0] - 2026-10-13
- **Initial Release**: Complete accommodation booking system with CPT room types, physical units, availability engine, concurrency lock protection, server-side pricing, transactional emails, blocks, widgets, and optional WooCommerce integration.

---

## License

This project is licensed under the **GNU General Public License v2.0 or later** - see the [LICENSE](LICENSE) file for details.

---

## Support & Author

- **Author**: WPCalibrate
- **Website**: [https://wpcalibrate.com](https://wpcalibrate.com)
- **Marketplace**: [https://marketplace.wpcalibrate.com](https://marketplace.wpcalibrate.com)
- **Email**: [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **Phone / WhatsApp**: +447474795976
