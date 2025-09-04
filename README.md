# Professional Affiliate Tracking System

A comprehensive affiliate tracking solution with postback handling, real-time statistics, and partner management capabilities.

## Features

- **Real-time Tracking**: Monitor affiliate clicks, conversions, and postbacks in real-time
- **Partner Management**: Create and manage multiple affiliate partners with custom configurations
- **Advanced Filtering**: Filter statistics by date range, status, and custom parameters
- **Data Visualization**: Dashboard with key metrics and performance indicators
- **Postback Processing**: Automated postback handling with detailed logging
- **Telegram Integration**: Send notifications to Telegram channels for important events
- **Google Sheets Integration**: Export data directly to Google Sheets
- **Responsive Design**: Mobile-friendly interface that works on all devices
- **Dark/Light Theme**: Toggle between dark and light themes based on preference
- **Security Features**: IP whitelisting and access control for partners

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server
- Composer (for dependency management)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/dazfx/affiliate-tracking-system.git
   ```

2. Navigate to the project directory:
   ```bash
   cd affiliate-tracking-system
   ```

3. Set up the database:
   - Create a MySQL database
   - Import the SQL schema (database schema to be added)

4. Configure database connection:
   - Edit `admin/db.php` with your database credentials

5. Set up web server:
   - Configure your web server to point to the `track` directory
   - Ensure URL rewriting is enabled

6. Access the admin panel:
   - Navigate to your domain in a web browser
   - The system will guide you through initial setup

## Configuration

### Database Setup

Edit `admin/db.php` with your database credentials:

```php
define('DB_HOST', 'your_database_host');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

### Global Settings

Access the admin panel to configure:
- Telegram bot integration
- cURL settings
- Global partner configurations

## Usage

### Adding a Partner

1. Navigate to the admin panel
2. Click "Add Partner" button
3. Fill in partner details:
   - Unique Partner ID
   - Partner Name
   - Target Domain
   - Click ID parameters
   - Sum parameters
   - IP whitelisting (optional)
4. Configure integrations:
   - Telegram notifications
   - Google Sheets export
5. Save the partner configuration

### Postback URL

Partners should send postbacks to:
```
https://yourdomain.com/track/postback.php?pid=PARTNER_ID&clickid=CLICK_ID&sum=CONVERSION_VALUE
```

Replace `PARTNER_ID` with the actual partner ID, `CLICK_ID` with the click identifier, and `CONVERSION_VALUE` with the conversion value.

### Viewing Statistics

1. Select a partner from the navigation menu
2. Use filters to narrow down results:
   - Date range
   - Status codes
   - Smart search (search by click ID, URL, or parameters)
3. Enable auto-refresh for real-time updates
4. Customize visible columns using the "Columns" dropdown

## API Endpoints

### Postback Endpoint
```
POST /track/postback.php
```

Parameters:
- `pid` (required): Partner ID
- `clickid` (required): Click identifier
- `sum` (optional): Conversion value
- Any additional parameters will be logged

### Admin API
```
POST /track/admin/api.php
```

Actions:
- `save_partner`: Save partner configuration
- `delete_partner`: Delete a partner
- `get_partner_data`: Retrieve partner details
- `save_global_settings`: Save global system settings
- `clear_partner_stats`: Clear statistics for a partner
- `get_detailed_stats`: Retrieve detailed statistics

## Security

- All API endpoints validate input data
- Partners can be restricted by IP address
- HTTPS is recommended for production use
- Database queries use prepared statements to prevent SQL injection

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please open an issue on GitHub or contact the maintainers.

## Screenshots

### Dashboard
![Dashboard](screenshots/dashboard.png)

### Partner Statistics
![Partner Statistics](screenshots/partner-stats.png)

### Partner Configuration
![Partner Configuration](screenshots/partner-config.png)