# Tour Sales Management System (TSMS) - Changelog & Setup Guide

## Project Information
- **Name**: Tour Sales Management System (TSMS)
- **GitHub Repository**: Tour-Sales-Management-System
- **Description**: A comprehensive system for managing tours, sales, expenses, and investments
- **Version**: 1.0.0
- **Author**: Toni Mladenic
- **Contact**: tonimladenic@gmail.com

## Version 1.0.0

### Features
- User Management
  - Admin and regular user roles
  - Secure login system
  - Password change functionality
  - User activity logging

- Tour Management
  - Create, edit, and archive tours
  - Tour statistics and overview
  - Tour-specific sales tracking
  - Waiting list management

- Sales Management
  - Track sales by product and salesperson
  - Promoter sales tracking
  - Sales statistics and reports
  - Export/import functionality

- Expense Management
  - Categorized expenses
  - Expense tracking per tour
  - Expense reports
  - Export/import functionality

- Investment Management
  - Investment calculations
  - Profit sharing
  - Investor management
  - Future investment planning

- Additional Features
  - Dark mode support
  - Mobile-responsive design
  - CSV export/import
  - PDF report generation
  - Activity logging
  - Notes system

### Setup Instructions

#### 1. Database Setup
1. Import the schema into MySQL:
   ```bash
   # Method 1: Using MySQL command line
   mysql -u your_username -p your_database_name < sql/schema.sql
   
   # Method 2: Using phpMyAdmin
   # 1. Log in to phpMyAdmin
   # 2. Select your database
   # 3. Click on "Import" tab
   # 4. Choose file: sql/schema.sql
   # 5. Click "Go" to import
   
   # Method 3: Using MySQL Workbench
   # 1. Open MySQL Workbench
   # 2. Connect to your server
   # 3. Open sql/schema.sql
   # 4. Click "Execute" to run the script
   ```

2. Verify the import:
   ```sql
   -- Check if tables were created
   SHOW TABLES;
   
   -- Check if default admin user exists
   SELECT * FROM users WHERE username = 'admin';
   ```

3. Default admin credentials:
   - Username: admin
   - Password: AdminAdmin
   - Role: admin

#### 2. Configuration
1. Database Configuration (`config/database.php`):
   ```php
   $host = 'localhost';      // Database host
   $dbname = 'your_db_name'; // Database name
   $username = 'your_user';  // Database username
   $password = 'your_pass';  // Database password
   ```

2. Basic Configuration (`includes/config.php`):
   ```php
   // Site URL (without trailing slash)
   define('SITE_URL', 'https://yourdomain.com');
   
   // Path to your installation (without trailing slash)
   define('BASE_PATH', '/path/to/your/installation');
   
   // Timezone
   date_default_timezone_set('Europe/Zagreb');
   ```

3. Backup Configuration (`backup_db.php`):
   ```php
   // Secret token for backup access
   $secret_token = "YOUR_SECRET_TOKEN";  // Change this to a secure random string
   
   // Backup directory
   $backup_dir = "./backup";  // Relative to script location
   ```

#### 3. Backup System Setup
1. Configure backup settings in `backup_db.php`:
   - Set your secret token (minimum 32 characters)
   - Update database credentials
   - Set backup directory path
   - Configure backup frequency (recommended: daily)

2. Secure backup directory:
   - Create `.htaccess` in backup folder:
   ```apache
   <Limit GET POST>
       Order Deny,Allow
       Deny from all
       Allow from YOUR_IP_ADDRESS
   </Limit>
   
   # Prevent directory listing
   Options -Indexes
   
   # Prevent access to .sql files
   <FilesMatch "\.sql$">
       Order Allow,Deny
       Deny from all
   </FilesMatch>
   ```

3. Running backups:
   - Manual backup: `https://yourdomain.com/backup_db.php?token=YOUR_SECRET_TOKEN`
   - Automated backup (cron job example):
   ```bash
   0 2 * * * wget -q -O /dev/null "https://yourdomain.com/backup_db.php?token=YOUR_SECRET_TOKEN"
   ```
   - Backup files are saved in: `backup/your_backup_YYYY-MM-DD_HH-MM-SS.sql`

#### 4. Customization
1. Site Name:
   - Change in `includes/header.php`:
     ```php
     <title>Your New Site Name</title>
     <a href="<?php echo $rootPath; ?>index.php" class="logo">Your New Site Name</a>
     ```
   - Change in `login.php`:
     ```php
     <title>Login - Your New Site Name</title>
     <h1>Your New Site Name</h1>
     ```
   - Change in `includes/footer.php`:
     ```php
     <p style="margin: 0;">&copy; <?php echo date('Y'); ?> Your New Site Name. All rights reserved.</p>
     ```

2. Branding:
   - Update logo in `assets/images/`
   - Modify colors in CSS files
   - Update favicon

#### 5. Security
1. File Permissions:
   - Directories: 755 (drwxr-xr-x)
   - Files: 644 (rw-r--r--)
   - Sensitive files (config, backup): 600 (rw-------)
   - Backup directory: 700 (drwx------)

2. Regular Maintenance:
   - Daily: Check error logs
   - Weekly: Review user activity
   - Monthly: 
     - Rotate backup files (keep last 30 days)
     - Update passwords
     - Check for unauthorized access
     - Review security logs

3. Security Best Practices:
   - Use strong passwords (minimum 12 characters)
   - Enable HTTPS
   - Regular updates
   - Monitor failed login attempts
   - Regular backup testing
   - IP whitelisting for admin access

### Known Issues
- None reported in version 1.0.0

### Future Improvements
- Multi-language support
- Advanced reporting features
- API integration
- Mobile app support
- Enhanced security features

### Support
For support or questions, contact:
- Email: tonimladenic@gmail.com
- Author: Toni Mladenic

### Basic Functionality Guide
1. User Management:
   - Admin can create/edit/delete users
   - Users can change their password
   - Activity logging for all actions

2. Tour Management:
   - Create tours with start/end dates
   - Assign suppliers and products
   - Track sales and expenses
   - Archive completed tours

3. Sales Tracking:
   - Record sales by product
   - Track salesperson performance
   - Monitor promoter sales
   - Generate sales reports

4. Expense Management:
   - Categorize expenses
   - Track expenses per tour
   - Export expense reports
   - Monitor budget vs actual

5. Investment Features:
   - Calculate investments
   - Track profit sharing
   - Manage investors
   - Plan future investments

### User Guide

#### 1. Getting Started
1. Login:
   - Access the login page
   - Enter admin credentials (default: admin/AdminAdmin)
   - Select a tour to work with

2. Navigation:
   - Use the sidebar menu for quick access
   - Current tour is displayed in the header
   - Dark mode toggle available

#### 2. User Management
1. Adding Users:
   - Go to Admin > Users
   - Click "Add User"
   - Fill in username and password
   - Select role (admin/user)
   - Save changes

2. Managing Users:
   - Edit user details
   - Change passwords
   - Deactivate/activate users
   - View user activity logs

#### 3. Tour Management
1. Creating Tours:
   - Go to Admin > Tours
   - Click "New Tour"
   - Enter tour details:
     - Tour name
     - Supplier
     - Start/end dates
     - Products available
   - Save tour

2. Tour Operations:
   - Edit tour details
   - Archive completed tours
   - View tour statistics
   - Export tour data
   - Import tour data

3. Tour Products:
   - Add products to tour
   - Set product prices
   - Manage product inventory
   - Track product sales

#### 4. Sales Management
1. Recording Sales:
   - Go to Sales > New Sale
   - Select product
   - Enter quantity
   - Select salesperson
   - Add any discounts
   - Save sale

2. Promoter Sales:
   - Go to Sales > Promoter Sales
   - Select promoter
   - Enter sales details
   - Track promoter performance

3. Sales Reports:
   - View daily/weekly/monthly sales
   - Export sales data
   - Generate sales statistics
   - Track salesperson performance

#### 5. Expense Management
1. Adding Expenses:
   - Go to Expenses > New Expense
   - Select category
   - Enter amount
   - Add description
   - Attach receipts (if available)
   - Save expense

2. Expense Categories:
   - Create new categories
   - Edit existing categories
   - Track expenses by category
   - Generate expense reports

3. Expense Reports:
   - View expense summaries
   - Export expense data
   - Track budget vs actual
   - Generate expense statistics

#### 6. Investment Management
1. Investment Calculations:
   - Go to Investments > New Calculation
   - Enter investment details
   - Add investors
   - Calculate profit sharing
   - Save calculation

2. Investor Management:
   - Add new investors
   - Set investment percentages
   - Track investor payouts
   - Manage future investments

3. Profit Distribution:
   - Calculate profit shares
   - Generate payout reports
   - Track investor payments
   - Plan future distributions

#### 7. Waiting List
1. Managing Waiting List:
   - Add new entries
   - Update customer information
   - Track customer status
   - Export waiting list

2. Customer Notifications:
   - Send availability updates
   - Track customer responses
   - Manage customer preferences

#### 8. Notes System
1. Creating Notes:
   - Add new notes
   - Categorize notes
   - Set reminders
   - Share notes with team

2. Note Management:
   - Edit existing notes
   - Archive old notes
   - Search notes
   - Export notes

#### 9. Reports and Statistics
1. Generating Reports:
   - Sales reports
   - Expense reports
   - Investment reports
   - Tour summaries

2. Export Options:
   - CSV export
   - PDF reports
   - Excel spreadsheets
   - Custom formats

#### 10. Backup and Security
1. Regular Backups:
   - Automatic daily backups
   - Manual backup option
   - Backup verification
   - Restore procedures

2. Security Features:
   - User authentication
   - Role-based access
   - Activity logging
   - IP restrictions

#### 11. Tips and Best Practices
1. Daily Operations:
   - Start with tour selection
   - Record sales promptly
   - Track expenses daily
   - Update waiting list

2. Weekly Tasks:
   - Review sales reports
   - Check expense summaries
   - Update investment calculations
   - Clean up old data

3. Monthly Maintenance:
   - Archive completed tours
   - Generate monthly reports
   - Review user access
   - Update passwords