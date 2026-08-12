# PHP 8 MVC Application - Folder Structure

```
dashboard/
├── app/
│   ├── Controllers/     # Controller classes handling HTTP requests and business logic
│   ├── Models/          # Model classes representing data and database interactions
├── config/              # Configuration files (database, helpers, env)
├── public/              # **Document root** – satu-satunya folder yang diakses web
│   ├── index.php        # Entry point aplikasi
│   ├── router.php      # Router untuk PHP built-in server (-t public)
│   ├── .htaccess       # Rewrite ke index.php (Apache/Laragon)
│   ├── css/            # Stylesheet (style.css)
│   └── js/             # JavaScript (app.js)
├── views/               # View templates (di luar public, di-include oleh app)
├── vendor/              # Composer dependencies
├── router.php          # Router alternatif (document root = project root)
├── start-server.bat     # Jalankan server dengan document root = public/
├── start-server.ps1
└── README.md
```

**Penting:** Agar CSS/JS ter-load dengan benar, jalankan server dengan **document root = public/**:
- `php -S localhost:8000 -t public public/router.php` (dari folder project)
- Atau gunakan `start-server.bat` / `start-server.ps1`.

## Folder Explanations

### `/app`
Main application code directory containing the MVC components.

**Controllers/** - Handles HTTP requests, processes input, and coordinates between Models and Views. Each controller typically represents a resource or feature area.

**Models/** - Contains business logic and data access layer. Models interact with the database and represent entities in your application.

**Views/** - Presentation layer templates. Contains PHP/HTML files that render the user interface. Views receive data from controllers and display it to users.

### `/config`
Configuration files for database connections, application settings, routing rules, and environment-specific variables.

### `/public`
**Harus dipakai sebagai document root** agar asset (CSS, JS) ter-load. Berisi:
- `index.php` – entry point aplikasi
- `router.php` – router untuk PHP built-in server (bila jalan dengan `-t public`)
- `css/`, `js/` – asset statis
- `.htaccess` – rewrite ke index.php (Apache/Laragon)

### `/tests`
Test files for unit testing, integration testing, and other automated tests.

### `/vendor`
Composer-managed dependencies. Auto-generated directory, should be excluded from version control.

