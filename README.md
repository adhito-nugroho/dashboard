# Dashboard Anggaran

PHP 8 MVC Application for Budget Management

## Quick Start with Laragon

1. **Place project in Laragon:**
   - Project should be in `D:\laragon\www\dashboard-anggaran`

2. **Access the application:**
   - Open Laragon
   - Start Apache/Nginx and MySQL
   - Access via: `http://dashboard-anggaran.test/`
   - Or: `http://localhost/dashboard-anggaran/`

3. **Configure database:**
   - Create `config/.env` file (copy from `config/env.example.txt`)
   - Update database credentials

## Installation

### For Laragon Users (Recommended)

1. Place this project in `D:\laragon\www\dashboard-anggaran`
2. Laragon will automatically detect it
3. Access via: `http://dashboard-anggaran.test/`
4. Configure database in `config/.env`

**Note:** The root `.htaccess` automatically redirects to the `public` directory, so Laragon's default setup works out of the box.

### For Other Servers

1. Clone or download this repository
2. Configure your database in `config/.env`
3. Set up your web server to point to the `public` directory
4. Access the application in your browser

## Project Structure

See `FOLDER_STRUCTURE.md` for detailed folder explanations.

## Documentation

- `LARAGON_SETUP.md` - Detailed Laragon setup instructions
- `SERVER_SETUP.md` - Server configuration guide
- `INSTALL.md` - Installation guide
- `DATABASE_SCHEMA.md` - Database schema
- `ROUTING.md` - Routing documentation
