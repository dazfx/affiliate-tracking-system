# Deployment Guide

This document provides instructions for deploying the Affiliate Tracking System to a production environment.

## Table of Contents
- [System Requirements](#system-requirements)
- [Server Configuration](#server-configuration)
- [Database Setup](#database-setup)
- [Application Deployment](#application-deployment)
- [Post-Deployment Configuration](#post-deployment-configuration)
- [Security Considerations](#security-considerations)
- [Performance Optimization](#performance-optimization)
- [Monitoring and Maintenance](#monitoring-and-maintenance)

## System Requirements

### Server Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher (or compatible database)
- Apache or Nginx web server
- Composer (for dependency management)
- Git (for version control)

### PHP Extensions
- PDO with MySQL support
- cURL
- JSON
- OpenSSL
- Mbstring
- Fileinfo

### Recommended Server Specifications
- Minimum: 2 CPU cores, 4GB RAM, 20GB SSD
- Recommended: 4+ CPU cores, 8GB+ RAM, 50GB+ SSD

## Server Configuration

### Apache Configuration
Ensure your Apache server has the following modules enabled:
- mod_rewrite
- mod_headers
- mod_ssl (for HTTPS)

Sample virtual host configuration:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/affiliate-tracking-system
    
    <Directory /var/www/affiliate-tracking-system>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/affiliate-tracking-error.log
    CustomLog ${APACHE_LOG_DIR}/affiliate-tracking-access.log combined
</VirtualHost>
```

### Nginx Configuration
Sample Nginx configuration:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/affiliate-tracking-system;
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

## Database Setup

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

## Application Deployment

### Method 1: Git Clone (Recommended)
```bash
cd /var/www
git clone https://github.com/dazfx/affiliate-tracking-system.git
cd affiliate-tracking-system
```

### Method 2: Manual Upload
1. Download the latest release from GitHub
2. Extract the files to your web server directory
3. Set appropriate file permissions

### File Permissions
Set the following permissions:
```bash
# Set ownership to web server user (www-data for Apache/Nginx)
sudo chown -R www-data:www-data /var/www/affiliate-tracking-system

# Set directory permissions
find /var/www/affiliate-tracking-system -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/affiliate-tracking-system -type f -exec chmod 644 {} \;

# Set executable permissions for specific files
chmod 755 /var/www/affiliate-tracking-system/track/postback.php
chmod 755 /var/www/affiliate-tracking-system/track/process_queue.php
```

### Configuration Files
1. Update database configuration in `admin/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'affiliate_tracking');
define('DB_USER', 'affiliate_user');
define('DB_PASS', 'secure_password');
```

2. Update any other configuration as needed (see CONFIGURATION.md)

## Post-Deployment Configuration

### Initial Setup
1. Access the admin panel at `https://yourdomain.com/admin/`
2. Run the installation script if prompted
3. Configure global settings through the admin interface
4. Add your first partner

### Cron Jobs
Set up the following cron jobs for proper system operation:

```bash
# Process postback queue every minute
* * * * * cd /var/www/affiliate-tracking-system && php track/process_queue.php

# Daily cleanup (optional)
0 2 * * * cd /var/www/affiliate-tracking-system && php track/cleanup.php
```

### SSL Certificate
Install an SSL certificate for secure HTTPS connections:
- Use Let's Encrypt for free SSL certificates
- Configure your web server to redirect HTTP to HTTPS

## Security Considerations

### File Security
- Ensure sensitive files are not accessible directly
- Use .htaccess or server configuration to restrict access to config files
- Regularly update dependencies and the application

### Database Security
- Use strong database credentials
- Restrict database user permissions
- Regular backups of the database

### Application Security
- Keep PHP and server software updated
- Implement proper input validation
- Use prepared statements for database queries
- Enable CSRF protection

### Network Security
- Use a firewall to restrict unnecessary access
- Implement rate limiting
- Monitor for suspicious activity

## Performance Optimization

### PHP Optimization
- Enable OPcache for better performance
- Adjust PHP memory limits as needed
- Configure appropriate timeout values

### Database Optimization
- Add indexes to frequently queried columns
- Optimize queries with EXPLAIN
- Consider read replicas for high-traffic installations

### Caching
- Implement Redis or Memcached for session storage
- Use APCu for application-level caching
- Consider full-page caching for static content

### Web Server Optimization
- Enable Gzip compression
- Configure browser caching headers
- Use CDN for static assets

## Monitoring and Maintenance

### Log Monitoring
- Monitor web server logs for errors
- Check application logs regularly
- Set up alerts for critical errors

### Performance Monitoring
- Monitor server resource usage
- Track application response times
- Set up uptime monitoring

### Regular Maintenance
- Apply security updates regularly
- Perform database optimization
- Clean up old logs and temporary files
- Review and update configuration as needed

### Backup Strategy
- Implement regular database backups
- Backup application files
- Test restore procedures regularly

## Troubleshooting

### Common Issues
1. **500 Internal Server Error**
   - Check web server error logs
   - Verify file permissions
   - Check PHP error logs

2. **Database Connection Failed**
   - Verify database credentials
   - Check database server status
   - Ensure database user permissions

3. **Postbacks Not Processing**
   - Check cron job configuration
   - Verify postback queue processing
   - Review partner configuration

### Support
For additional help, please:
1. Check the documentation
2. Review error logs
3. Open an issue on GitHub if you've found a bug