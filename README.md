# Tour Sales Management System (TSMS)

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Maintenance](https://img.shields.io/badge/Maintained-yes-green.svg)](https://github.com/TMladenic/Tour-Sales-Management-System/graphs/commit-activity)

A comprehensive web-based system for managing tours, sales, expenses, and investments. Built with PHP and MySQL.

## 📚 Dependencies

- **TCPDF** - PHP library for PDF generation
  - Author: Nicola Asuni
  - License: GNU Lesser General Public License v3.0
  - Website: https://tcpdf.org/
  - Used for generating PDF reports and documents

## 🌟 Features

- **User Management**
  - Role-based access control (Admin/User)
  - Secure authentication
  - Activity logging
  - Password management

- **Tour Management**
  - Create and manage tours
  - Track tour dates and suppliers
  - Archive completed tours
  - Tour-specific statistics

- **Sales Tracking**
  - Record sales by product
  - Track salesperson performance
  - Monitor promoter sales
  - Generate sales reports

- **Expense Management**
  - Categorize expenses
  - Track tour expenses
  - Generate expense reports
  - Budget monitoring

- **Investment Features**
  - Investment calculations
  - Profit sharing
  - Investor management
  - Future investment planning

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependencies)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/TMladenic/Tour-Sales-Management-System.git
   cd Tour-Sales-Management-System
   ```

2. Import the database schema:
   ```bash
   mysql -u your_username -p your_database_name < sql/schema.sql
   ```

3. Configure the application:
   - Copy `includes/config.example.php` to `includes/config.php`
   - Update database credentials in `config/database.php`
   - Set your domain in `includes/config.php`

4. Set up the web server:
   - Point your web server to the project directory
   - Ensure proper file permissions
   - Configure .htaccess for security

5. Access the application:
   - Open your browser
   - Navigate to your domain
   - Login with default credentials:
     - Username: admin
     - Password: AdminAdmin

## 🔒 Security

- Secure password hashing
- SQL injection prevention
- XSS protection
- Regular backup system
- Activity logging

## 📊 Database Structure

The system uses the following main tables:
- users
- tours
- products
- sales
- expenses
- investment_calculations
- promoters
- salespeople

For detailed schema, see `sql/schema.sql`

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👤 Author

**Toni Mladenic**
- Email: tonimladenic@gmail.com
- GitHub: [@TMladenic](https://github.com/TMladenic)

## 🙏 Acknowledgments

- Thanks to all contributors
- Special thanks to the PHP and MySQL communities
- Inspired by real-world tour management needs

## 📚 Documentation

For detailed documentation, please see [CHANGELOG.md](CHANGELOG.md)

## ⚠️ Disclaimer

This software is provided "as is" without warranty of any kind. Use at your own risk. 
