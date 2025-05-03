# Employee Leave Management System

A web-based system for managing employee leave requests and approvals.

## Features

- User Registration & Activation
- Leave Request Management
- Leave Balance Tracking
- Leave History Reports
- Admin Dashboard for Approvals

## Technology Stack

- PHP 7.4+
- MySQL 5.7+
- HTML5
- CSS3
- JavaScript
- Bootstrap 5

## Installation Instructions

1. **Prerequisites**
   - XAMPP or similar web server with PHP and MySQL
   - Web browser

2. **Database Setup**
   - Start your MySQL server
   - Import the database schema from `database/leave_management.sql`
   - Update database credentials in `config/database.php` if needed

3. **Application Setup**
   - Place all files in your web server's document root (e.g., htdocs)
   - Ensure the web server has write permissions for the application directory

4. **Default Admin Credentials**
   - Username: admin
   - Password: admin123

## Usage Instructions

1. **Employee Registration**
   - Access the application through your web browser
   - Click on "Register as Employee" link
   - Fill in the registration form
   - Wait for admin approval

2. **Employee Features**
   - View leave balances
   - Apply for leave
   - View leave history
   - Track application status

3. **Admin Features**
   - Approve/reject employee registrations
   - Approve/reject leave requests
   - View all employee leave records

## Security Features

- Password hashing
- Session management
- Input validation
- SQL injection prevention
- XSS protection

## File Structure

```
├── config/
│   └── database.php
├── database/
│   └── leave_management.sql
├── admin/
│   └── dashboard.php
├── employee/
│   └── dashboard.php
├── index.php
├── register.php
├── logout.php
└── README.md
```

## Support

For any issues or questions, please contact the system administrator. 
