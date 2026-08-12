# Routing Configuration

## Current Setup

The application uses a simple routing system in `public/index.php`.

## Routes

### Program Routes

- `GET /program` - List all programs
- `GET /program/create` - Show create form
- `POST /program/store` - Store new program
- `GET /program/edit/{id}` - Show edit form
- `POST /program/update/{id}` - Update program
- `GET /program/delete/{id}` - Delete program

## Web Server Configuration

### Apache (.htaccess)

Create `public/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Using a Router Library

For production, consider using a routing library like:
- FastRoute
- Symfony Routing Component
- Laravel Router (standalone)

