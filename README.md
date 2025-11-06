# myCred Polar.sh Integration

![Version](https://img.shields.io/badge/version-1.0.9-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-brightgreen.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-orange.svg)

> Seamlessly integrate Polar.sh payment processing with myCred points system. Support one-time purchases and recurring subscriptions with PWYW (Pay What You Want) functionality.

## 📸 Screenshots

![myCred Polar Interface](https://i.imgur.com/jAp08Lxl.png)

## ✨ Features

- 🛒 **One-time Point Purchases** - Buy points instantly with Polar.sh checkout
- 🔁 **Recurring Subscriptions** - Set up automatic point delivery cycles
- 💰 **PWYW Support** - Pay What You Want with automatic point recalculation
- 🎯 **Idempotent Point Awarding** - Prevents duplicate point awards
- 🔐 **Robust Webhook Verification** - Svix/whsec_ signature validation
- 👥 **Customer Portal Integration** - Users can manage subscriptions
- 📊 **Admin Dashboard** - View MRR, ARR, and subscription KPIs
- 📥 **CSV Export** - Download subscription data
- 🔄 **Subscription Sync** - Manual sync with Polar.sh API
- 🎨 **Modern Dark UI** - Beautiful, responsive interface
- 🌐 **Sandbox & Live Modes** - Test before going live
- 📝 **Transaction Logs** - Complete audit trail

## 🚀 Installation

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- [myCred](https://wordpress.org/plugins/mycred/) plugin installed and activated
- Polar.sh account (Sandbox or Live)

### Steps

1. **Download the plugin**
   ```bash
   git clone https://github.com/yourusername/mycred-polar-sh.git
   cd mycred-polar-sh
   ```

2. **Upload to WordPress**
   - Upload the `mycred-polar-sh` folder to `/wp-content/plugins/`
   - Or upload the zip file via WordPress Admin → Plugins → Add New

3. **Activate**
   - Go to WordPress Admin → Plugins
   - Find "myCred Polar.sh Points & Subscriptions"
   - Click "Activate"

4. **Configure**
   - Go to **myCred Polar.sh → Settings**
   - Add your Polar.sh API credentials
   - Set exchange rate and product IDs
   - Configure webhook secret

## ⚙️ Configuration

### 1. Polar.sh Setup

#### Create Access Token
1. Go to [Polar.sh Settings → API](https://polar.sh/settings/api)
2. Create a new token with these scopes:
   - ✅ `products:read`
   - ✅ `checkouts:write`
   - ✅ `orders:read`
   - ✅ `subscriptions:read`
   - ✅ `customer_sessions:write`
   - ✅ `subscriptions:write`

#### Create Products
1. **One-time Product**: For single point purchases (can be PWYW)
2. **Recurring Products**: For subscription plans

#### Setup Webhook
1. Go to Polar.sh → Webhooks
2. Create webhook:
   - **Event**: `order.paid`
   - **Endpoint**: `https://yoursite.com/wp-json/mycred-polar/v1/webhook`
   - **Secret**: Generate and copy to plugin settings

### 2. Plugin Settings

Navigate to **myCred Polar.sh → Settings**:

| Setting | Description | Example |
|---------|-------------|---------|
| Payment Mode | Sandbox or Live | `Sandbox` |
| Access Token | Your Polar API token | `polar_at_...` |
| Product ID | One-time product ID | `prod_...` |
| Exchange Rate | $ per point | `0.10` (10¢/point) |
| Min Points | Minimum purchase | `50` |
| Default Points | Pre-filled amount | `100` |
| Point Type | myCred point type | `mycred_default` |
| Webhook Secret | From Polar webhook | `whsec_...` |
| Webhook Verify | Verification mode | `Strict` |

### 3. Subscription Plans

Add recurring plans in settings:

```
Name: Daily Plan
Product ID: prod_xyz123
Points per Cycle: 2000
Use Custom Amount: ✓ (for PWYW)
```

## 📖 Usage

### Display Purchase Form

Add shortcode to any page or post:

```
[mycred_polar_form]
```

This displays:
- One-time purchase card
- Subscription plans selector
- Subscription management panel

### Admin Features

#### View Transactions
**myCred Polar.sh → Transaction Logs**
- See all point purchases
- Filter by user, status, date
- View order details

#### Subscription Dashboard
**myCred Polar.sh → Subscribe**
- View MRR (Monthly Recurring Revenue)
- View ARR (Annual Recurring Revenue)
- Active subscriber count
- Recent cancellations
- Sync with Polar.sh
- Export to CSV

## 🔧 Developer Guide

### File Structure

```
mycred-polar-sh/
├── mycred-polar.php                    # Main plugin file
├── includes/
│   ├── helpers.php                     # Utility functions
│   ├── class-mycred-polar-core.php     # Core initialization
│   ├── class-mycred-polar-database.php # Database operations
│   ├── class-mycred-polar-webhook.php  # Webhook handler
│   ├── class-mycred-polar-ajax.php     # AJAX endpoints
│   └── class-mycred-polar-admin.php    # Admin interface
├── admin/
│   └── views/
│       ├── settings-page.php           # Settings UI
│       ├── logs-page.php               # Transaction logs UI
│       └── subscribe-page.php          # Dashboard UI
└── public/
    ├── class-mycred-polar-shortcode.php # Shortcode handler
    └── shortcode-form.php               # Frontend template
```

### Hooks & Filters

#### Actions

```php
// Before point award
do_action('mycred_polar_before_award', $user_id, $points, $order_id);

// After point award
do_action('mycred_polar_after_award', $user_id, $points, $order_id);

// Webhook received
do_action('mycred_polar_webhook_received', $event_type, $order_data);
```

#### Filters

```php
// Modify exchange rate
add_filter('mycred_polar_exchange_rate', function($rate) {
    return $rate * 0.9; // 10% discount
});

// Modify points calculation
add_filter('mycred_polar_calculate_points', function($points, $amount) {
    return $points + 10; // Bonus points
}, 10, 2);

// Customize log entry
add_filter('mycred_polar_log_entry', function($entry, $order_id) {
    return "Premium purchase - " . $entry;
}, 10, 2);
```

### Custom Templates

Override the frontend form:

1. Copy `public/shortcode-form.php`
2. Place in your theme: `your-theme/mycred-polar/shortcode-form.php`
3. Customize as needed

## 🔐 Security Features

- ✅ **Webhook Signature Verification** - Svix standard HMAC validation
- ✅ **Nonce Protection** - WordPress nonce on all AJAX calls
- ✅ **Capability Checks** - Admin functions require `manage_options`
- ✅ **SQL Injection Prevention** - Prepared statements with `$wpdb`
- ✅ **XSS Protection** - All outputs escaped with `esc_html()`, `esc_attr()`
- ✅ **CSRF Protection** - WordPress nonce validation
- ✅ **Idempotent Awards** - Prevents duplicate point awards
- ✅ **Rate Limiting** - Transient-based locking mechanism

## 🐛 Troubleshooting

### Points Not Awarded

1. **Check webhook logs** in Polar.sh dashboard
2. **Verify webhook secret** matches plugin settings
3. **Test webhook** in Polar.sh → Send test event
4. **Check WordPress debug log** for errors
5. **Verify webhook endpoint** is accessible (not blocked by firewall)

### Connection Issues

1. **Test Connection** button in settings
2. Verify API token has all required scopes
3. Check if Sandbox/Live mode matches token type
4. Ensure WordPress can make external HTTPS requests

### Subscription Not Loading

1. Click **Sync from Polar** in Subscribe dashboard
2. Verify customer email is not demo/test/example domain
3. Check if `external_customer_id` matches WordPress user ID
4. Review subscription metadata in Polar.sh

### PWYW Not Calculating

1. Ensure `is_pwyw` flag is set in product metadata
2. Check exchange rate is set correctly
3. Verify webhook is receiving `net_amount` or `amount`
4. Review transaction logs for calculation details

## 📊 Database Tables

### `wp_mycred_polar_logs`

Stores all transactions:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Auto increment |
| user_id | bigint | WordPress user ID |
| order_id | varchar | Polar order ID |
| points | int | Points awarded |
| amount | int | Amount in cents |
| status | varchar | success/failed |
| webhook_data | longtext | Raw webhook JSON |
| created_at | datetime | Timestamp |

### `wp_mycred_polar_subscriptions`

Caches subscription data:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Auto increment |
| user_id | bigint | WordPress user ID |
| subscription_id | varchar | Polar subscription ID |
| product_id | varchar | Polar product ID |
| plan_name | varchar | Plan display name |
| points_per_cycle | int | Points awarded per cycle |
| amount | int | Amount in cents |
| currency | varchar | Currency code |
| recurring_interval | varchar | month/year/week/day |
| recurring_interval_count | int | Interval multiplier |
| status | varchar | active/canceled/past_due |
| cancel_at_period_end | tinyint | 1 if canceling |
| current_period_start | datetime | Current period start |
| current_period_end | datetime | Current period end |
| started_at | datetime | Subscription start |
| canceled_at | datetime | Cancellation date |
| ends_at | datetime | Final billing date |
| ended_at | datetime | Termination date |
| customer_email | varchar | Customer email |
| customer_external_id | varchar | WordPress user ID |
| updated_at | datetime | Last update |
| created_at | datetime | Creation time |

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use meaningful variable names
- Comment complex logic
- Test thoroughly in Sandbox mode

## 📝 Changelog

### Version 1.0.9

- ✨ Added modern dark UI design
- 🎨 Improved subscription management interface
- 🔧 Fixed plan selection value updates
- 📊 Enhanced admin dashboard with KPIs
- 🐛 Various bug fixes and improvements

### Version 1.0.5

- ✨ Added Customer Portal integration
- 🔄 Implemented subscription cancellation
- 📥 Added CSV export functionality
- 🎯 PWYW recalculation support
- 🔐 Enhanced webhook security

### Version 1.0.2

- 🚀 Complete plugin restructure
- 📁 Multi-file architecture
- 🎨 Separated views from logic
- 🔧 Improved maintainability

## 📄 License

This plugin is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) license.

## 🙏 Credits

- **myCred** - Points management system
- **Polar.sh** - Payment processing platform
- **WordPress** - Content management system

## 📧 Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/mycred-polar-sh/issues)
- **Documentation**: [Wiki](https://github.com/yourusername/mycred-polar-sh/wiki)
- **Email**: support@yoursite.com

## 🌟 Show Your Support

Give a ⭐️ if this plugin helped you!

---

Made with ❤️ for the WordPress community
