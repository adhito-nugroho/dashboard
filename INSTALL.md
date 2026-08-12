# Installation Guide

## Prerequisites

- PHP 8.0 or higher
- MySQL/MariaDB
- Composer (optional, for autoloading)

## Setup Steps

### 1. Install Dependencies (Optional)

If you want to use Composer autoloading, run:

```bash
composer install
```

**Note:** The application will work without Composer since all files are manually required. The autoloader is optional.

### 2. Configure Database

1. Copy `config/env.example.txt` to `config/.env`
2. Update the `.env` file with your database credentials:

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=db_anggaran
DB_USER=your_username
DB_PASS=your_password
```

### 3. Create Database

Create the MySQL database:

```sql
CREATE DATABASE db_anggaran CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Import Database Schema

Create the tables according to `DATABASE_SCHEMA.md`:

- seksi
- program
- kegiatan
- sub_kegiatan
- rekening
- pagu
- rak
- transaksi

### 5. Configure Web Server

#### Apache
Point your document root to the `/public` directory. The `.htaccess` file is already configured.

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 6. Access Application

Open your browser and navigate to:
- `http://localhost/dashboard-anggaran/` (or your configured domain)

## Troubleshooting

### Composer Not Found
If you see an error about `vendor/autoload.php`, you can either:
1. Run `composer install` to generate the vendor directory
2. The application will work without it since files are manually required

### Database Connection Error
- Check your `.env` file credentials
- Ensure MySQL is running
- Verify database `db_anggaran` exists

