# Configuration Guide

This document provides detailed instructions for configuring the Affiliate Tracking System.

## Table of Contents
- [Database Configuration](#database-configuration)
- [Global Settings](#global-settings)
- [Partner Configuration](#partner-configuration)
- [Telegram Integration](#telegram-integration)
- [Google Sheets Integration](#google-sheets-integration)
- [Security Settings](#security-settings)
- [Advanced Configuration](#advanced-configuration)

## Database Configuration

The database configuration is stored in `admin/db.php`. You need to update the following constants:

```php
// Database connection settings
define('DB_HOST', 'your_database_host');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHAR', 'utf8mb4');
```

Make sure your database user has the necessary permissions to create tables and perform CRUD operations.

## Global Settings

Global settings can be configured through the admin panel or directly in the database. The following settings are available:

### Telegram Settings
- `telegram_bot_token`: Your Telegram bot token
- `telegram_channel_id`: The ID of the channel where notifications will be sent
- `telegram_globally_enabled`: Enable/disable global Telegram notifications

### cURL Settings
- `curl_timeout`: General timeout in seconds (default: 10)
- `curl_connect_timeout`: Connection timeout in seconds (default: 5)
- `curl_returntransfer`: Enable RETURNTRANSFER option (default: true)
- `curl_followlocation`: Enable FOLLOWLOCATION option (default: true)
- `curl_ssl_verify`: Enable SSL verification (default: true)

## Partner Configuration

Each partner can be configured with the following settings:

### Basic Information
- **ID**: Unique identifier for the partner
- **Name**: Partner name for display purposes
- **Target Domain**: The domain where postbacks will be sent

### URL Parameters
- **ClickID Keys**: Keys used to identify click IDs in postbacks (e.g., clickid, cid)
- **Sum Keys**: Keys used to identify conversion values (e.g., sum, payout)
- **Sum Mapping**: Mapping of sum values for profit calculation

### Access Control
- **IP Whitelist**: Restrict postbacks to specific IP addresses
- **Logging**: Enable/disable detailed logging for this partner

### Integrations
- **Telegram Notifications**: Enable partner-specific Telegram notifications
- **Google Sheets**: Configure Google Sheets integration for data export

## Telegram Integration

### Global Telegram Setup
1. Create a Telegram bot using BotFather
2. Obtain the bot token
3. Create a channel or group for notifications
4. Obtain the channel ID
5. Add the bot as an administrator to the channel

### Partner-Specific Telegram Setup
Partners can have their own Telegram bots and channels:
1. Create a separate bot for the partner
2. Obtain the bot token
3. Create a channel for the partner
4. Obtain the channel ID
5. Enable partner Telegram in the partner settings

### Telegram Whitelist
You can filter notifications by keywords:
- Enable the whitelist feature
- Add keywords that should trigger notifications
- Only postbacks containing these keywords will be sent to Telegram

## Google Sheets Integration

### Setup
1. Create a Google Cloud Project
2. Enable the Google Sheets API
3. Create a Service Account
4. Download the JSON key file
5. Share your Google Sheet with the service account email

### Configuration
In the partner settings:
- **Spreadsheet ID**: Found in the Google Sheets URL
- **Sheet Name**: The name of the sheet tab
- **Service Account JSON**: Paste the contents of your JSON key file

## Security Settings

### IP Whitelisting
Enable IP whitelisting to restrict postbacks to trusted sources:
1. Enable the IP whitelist feature
2. Add allowed IP addresses
3. Only postbacks from these IPs will be processed

### HTTPS Enforcement
The system should be deployed with HTTPS to ensure secure data transmission.

### Rate Limiting
The API includes built-in rate limiting to prevent abuse:
- 60 requests per minute per IP address
- Exceeding this limit will result in a 429 response

## Advanced Configuration

### Custom Postback Parameters
You can configure custom parameter mappings for each partner:
- Define additional keys to capture
- Map values to specific fields
- Process complex data structures

### Logging
Detailed logging can be enabled for debugging:
- File-based logging
- Database logging
- Telegram logging (for critical events)

### Performance Tuning
- Adjust cURL timeouts based on your network conditions
- Optimize database indexes for better query performance
- Configure caching for frequently accessed data

## Environment Variables

For enhanced security, you can use environment variables for sensitive configuration:

```php
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'affiliate_tracking');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
```

## Troubleshooting

### Database Connection Issues
1. Verify database credentials
2. Check database server status
3. Ensure the database user has proper permissions
4. Verify firewall settings

### Telegram Notifications Not Working
1. Verify bot token is correct
2. Check that the bot is added to the channel
3. Ensure the channel ID is correct
4. Check Telegram API rate limits

### Google Sheets Integration Issues
1. Verify the service account JSON is correct
2. Check that the spreadsheet is shared with the service account
3. Ensure the API is enabled in the Google Cloud Console
4. Verify the spreadsheet ID and sheet name

### Performance Issues
1. Check server resources
2. Optimize database queries
3. Review cURL timeout settings
4. Consider implementing caching