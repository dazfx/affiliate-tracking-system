# Installation Guide

This document provides step-by-step instructions for installing the Affiliate Tracking System.

## Prerequisites

Before installing the Affiliate Tracking System, ensure you have the following:

1. **Web Server**: Apache or Nginx
2. **PHP**: Version 8.0 or higher
3. **Database**: MySQL 5.7 or higher
4. **Composer**: For PHP dependency management
5. **Git**: For version control (optional but recommended)

### PHP Extensions Required
- PDO with MySQL support
- cURL
- JSON
- OpenSSL
- Mbstring
- Fileinfo

## Installation Steps

### Step 1: Download the Application

You can download the application in one of two ways:

#### Option A: Using Git (Recommended)
```bash
git clone https://github.com/dazfx/affiliate-tracking-system.git
cd affiliate-tracking-system
```

#### Option B: Manual Download
1. Download the latest release from the GitHub releases page
2. Extract the files to your desired directory

### Step 2: Set File Permissions

Set appropriate permissions for the web server to access the files:

```bash
# Set ownership to web server user (www-data for Apache/Nginx on Ubuntu/Debian)
sudo chown -R www-data:www-data /path/to/affiliate-tracking-system

# Set directory permissions
find /path/to/affiliate-tracking-system -type d -exec chmod 755 {} \;

# Set file permissions
find /path/to/affiliate-tracking-system -type f -exec chmod 644 {} \;

# Set executable permissions for specific files
chmod 755 /path/to/affiliate-tracking-system/track/postback.php
chmod 755 /path/to/affiliate-tracking-system/track/process_queue.php
```

### Step 3: Configure the Database

1. Create a new MySQL database:
```sql
CREATE DATABASE affiliate_tracking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Create a database user with appropriate permissions:
```sql
CREATE USER 'affiliate_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON affiliate_tracking.* TO 'affiliate_user'@'localhost';
FLUSH PRIVILEGES;
```

3. Import the database schema:
```bash
mysql -u affiliate_user -p affiliate_tracking < DATABASE_SCHEMA.sql
```

### Step 4: Configure the Application

1. Edit the database configuration file `track/admin/db.php`:
```php
// Database connection settings
define('DB_HOST', 'localhost');        // Your database host
define('DB_NAME', 'affiliate_tracking'); // Your database name
define('DB_USER', 'affiliate_user');     // Your database user
define('DB_PASS', 'secure_password');    // Your database password
define('DB_CHAR', 'utf8mb4');           // Character set
```

2. (Optional) Configure environment-specific settings in `track/admin/config.php` if it exists.

### Step 5: Configure Web Server

#### For Apache:
Ensure `mod_rewrite` is enabled and configure your virtual host:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/affiliate-tracking-system
    
    <Directory /path/to/affiliate-tracking-system>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/affiliate-tracking-error.log
    CustomLog ${APACHE_LOG_DIR}/affiliate-tracking-access.log combined
</VirtualHost>
```

#### For Nginx:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/affiliate-tracking-system;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

### Step 6: Set Up Cron Jobs

The system requires cron jobs to process the postback queue:

```bash
# Edit crontab
crontab -e

# Add the following line to run the queue processor every minute
* * * * * cd /path/to/affiliate-tracking-system && php track/process_queue.php

# Optional: Add a daily cleanup job
0 2 * * * cd /path/to/affiliate-tracking-system && php track/cleanup.php
```

### Step 7: Configure HTTPS (Recommended)

For production environments, configure SSL/TLS:

1. Obtain an SSL certificate (Let's Encrypt is a free option)
2. Configure your web server to use HTTPS
3. Set up HTTP to HTTPS redirection

### Step 8: Initial Setup

1. Access the admin panel in your browser:
   `http://yourdomain.com/track/admin/`

2. If an installation script exists, follow the on-screen instructions

3. Log in with the default credentials (if applicable) or create your first admin user

4. Configure global settings through the admin interface:
   - Telegram bot settings (if using)
   - Global cURL settings
   - Other system-wide preferences

### Step 9: Add Your First Partner

1. Navigate to the partners section in the admin panel
2. Click "Add Partner"
3. Fill in the partner details:
   - Unique ID
   - Partner name
   - Target domain for postbacks
4. Configure partner-specific settings:
   - ClickID keys
   - Sum keys and mapping
   - Access controls
   - Integrations (Telegram, Google Sheets, etc.)

## Testing the Installation

### Test Postback Processing
Send a test postback to verify the system is working:

```bash
curl -X POST "http://yourdomain.com/track/postback.php?pid=PARTNER_ID&clickid=TEST123&sum=10.50"
```

Check the admin panel to verify the postback was processed correctly.

### Test Admin Panel
1. Access the admin panel
2. Verify you can log in
3. Check that all menus and features are working
4. Verify database connections are functioning

## Troubleshooting

### Common Issues and Solutions

1. **500 Internal Server Error**
   - Check web server error logs
   - Verify file permissions
   - Ensure PHP modules are installed
   - Check PHP error logs

2. **Database Connection Failed**
   - Verify database credentials in `db.php`
   - Check database server status
   - Ensure database user has proper permissions
   - Verify the database exists and is accessible

3. **404 Not Found for Admin Panel**
   - Check web server configuration
   - Verify mod_rewrite is enabled (Apache)
   - Confirm file paths are correct

4. **Postbacks Not Processing**
   - Check cron job configuration
   - Verify the queue processor is running
   - Check partner configuration
   - Review application logs

5. **Permission Denied Errors**
   - Recheck file permissions
   - Verify web server user ownership
   - Check directory permissions

### Checking Logs

#### Web Server Logs
- Apache: `/var/log/apache2/error.log`
- Nginx: `/var/log/nginx/error.log`

#### Application Logs
- Check the `track/logs/` directory for application-specific logs
- Enable debug mode if needed for detailed error information

## Updating the Application

To update to a newer version:

1. Backup your database and files
2. Pull the latest changes from the repository:
   ```bash
   git pull origin main
   ```
3. Run any database migrations if provided
4. Clear any caches if applicable
5. Test the updated application

## Support

For additional help:

1. Check the documentation files (README.md, CONFIGURATION.md, etc.)
2. Review error logs for specific error messages
3. Search existing issues on GitHub
4. Open a new issue on GitHub if you've found a bug or need help

## Security Considerations

After installation:

1. Change default passwords
2. Remove any default accounts
3. Configure proper SSL/TLS
4. Set up a firewall
5. Regularly update the application and dependencies
6. Monitor logs for suspicious activity