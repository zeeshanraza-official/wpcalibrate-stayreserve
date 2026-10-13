=== WPCalibrate StayReserve ===
Contributors: WPCalibrate
Tags: hotel booking, room reservation, accommodation, booking engine, inventory management
Requires at least: 6.2
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A professional accommodation and hotel booking system for WordPress with real-time inventory, date-based pricing rules, and optional WooCommerce checkout.

== Description ==

WPCalibrate StayReserve is an accommodation reservation system designed for hotels, guest houses, bed and breakfasts, hostels, vacation rentals, lodges, and serviced apartments.

The plugin provides independent booking, inventory, availability calculation, pricing, and reservation management. Direct offline/manual reservations are natively supported, and WooCommerce checkout integration is available as an optional payment extension.

= Core Features =

* **Accommodation Management**: Define room types (CPT) with guest capacities (adults, children, total), bed configurations, dimensions, and amenities.
* **Physical Room Units**: Manage real individual physical inventory units (e.g. Room 101, Villa A) per room type to prevent overbooking.
* **Date-Based Pricing Engine**: Configure base nightly rates, seasonal ranges, date-specific overrides, and weekend/day-of-week adjustments calculated authoritatively on the server.
* **Overbooking & Concurrency Protection**: Server-side availability validation with locking safeguards to prevent simultaneous duplicate bookings for the last remaining unit.
* **Temporary Checkout Holds**: Protect inventory while customers complete checkout with configurable automatic hold expiration.
* **Add-ons & Extras**: Configure optional amenities such as breakfast, airport transfers, or parking with customizable pricing rules (per stay, per night, per guest).
* **Coupon & Discount Engine**: Create percentage or fixed discount promotional codes with minimum stay, minimum amount, and date restrictions.
* **Dedicated Admin Interface**: Manage bookings, review guest details, adjust stay dates with live inventory revalidation, and view a visual monthly occupancy calendar.
* **Transactional Email System**: Automated confirmation and notification emails for guests and property managers.
* **Customer Management Portal**: Logged-in customer dashboard and secure, signed time-limited access links for guest checkout reservations.
* **Optional WooCommerce Integration**: Process reservations through WooCommerce payments and gateways when enabled, with full High-Performance Order Storage (HPOS) compatibility.
* **Page Builder Support**: Native dynamic Gutenberg blocks and Elementor widgets when active.
* **Privacy & GDPR Compliance**: Integrated with WordPress Personal Data Export and Anonymization tools.

== Installation ==

1. Upload the `wpcalibrate-stayreserve` folder to your `/wp-content/plugins/` directory, or install the ZIP package via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WPCalibrate > StayReserve** in the WordPress admin menu to configure your currency, stay rules, and settings.
4. Create accommodation categories under **WPCalibrate > Room Types** and add physical inventory under the **Physical Units** tab.
5. Embed booking features on any page using Gutenberg blocks or shortcodes:
   * `[wpcsr_search]` - Availability search form
   * `[wpcsr_rooms]` - Available rooms list
   * `[wpcsr_booking]` - Complete multi-step reservation wizard
   * `[wpcsr_manage_booking]` - Customer booking management portal

== Frequently Asked Questions ==

= Is WooCommerce required to use this plugin? =
No. WooCommerce is strictly optional. WPCalibrate StayReserve contains its own booking engine and supports direct offline reservations (e.g., pay on arrival or bank transfer). If WooCommerce is installed and enabled in settings, checkout and payments can be routed through WooCommerce gateways.

= How does the plugin handle the check-out date? =
The plugin follows hotel stay semantics using half-open date ranges `[check_in, check_out)`. Check-out day is departure day and is not counted as an occupied night, meaning another guest can check in on that date.

= Can physical room units be managed individually? =
Yes. Each room type can have multiple physical inventory units (e.g., Room 101, Room 102). Availability is tracked per unit to prevent double-booking.

= Does the plugin support WooCommerce High-Performance Order Storage (HPOS)? =
Yes. The WooCommerce integration uses WooCommerce CRUD APIs and officially declares compatibility with High-Performance Order Storage (HPOS) and Cart/Checkout Blocks.

= How do guest customers manage their reservations? =
Guest reservations receive a cryptographically secure, signed time-limited management link in their confirmation email. Access is verified using token hashes and does not rely solely on predictable identifiers.

== Screenshots ==

1. Admin Dashboard with booking overview and quick statistics.
2. Monthly visual occupancy calendar view.
3. Frontend multi-step reservation wizard.
4. Room type configuration and nightly pricing settings.

== Changelog ==

= 1.0.0 =
* Initial release of WPCalibrate StayReserve.
* Custom database tables for transactional performance.
* Server-side availability and deterministic pricing engine.
* Concurrency protection for final unit allocations.
* Optional WooCommerce integration with HPOS compatibility.
* Gutenberg dynamic blocks and Elementor widgets.
* WordPress Privacy tools integration.
