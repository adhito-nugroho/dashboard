# Laragon Setup Guide

## Quick Setup

1. **Laragon should automatically detect your project** in `D:\laragon\www\dashboard-anggaran`

2. **Access your application:**
   - Open Laragon
   - Right-click on the project folder in Laragon
   - Select "Open in Browser" or access via:
   - `http://dashboard-anggaran.test/` (if auto virtual host is enabled)
   - Or manually add virtual host pointing to `public` directory

## Virtual Host Configuration

### Option 1: Auto Virtual Host (Laragon 4.0+)
Laragon automatically creates virtual hosts for folders in `www/`. Just access:
- `http://dashboard-anggaran.test/`

### Option 2: Manual Virtual Host Setup

1. Open Laragon
2. Click "Menu" → "Tools" → "Quick add" → "Project"
3. Or manually edit Apache/Nginx config:

**Apache Virtual Host:**
```apache
<VirtualHost *:80>
    ServerName dashboard-anggaran.test
    DocumentRoot "D:/laragon/www/dashboard-anggaran/public"
    <Directory "D:/laragon/www/dashboard-anggaran/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx Virtual Host:**
```nginx
server {
    listen 80;
    server_name dashboard-anggaran.test;
    root D:/laragon/www/dashboard-anggaran/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

4. Add to hosts file (Laragon usually does this automatically):
```
127.0.0.1 dashboard-anggaran.test
```

5. Restart Laragon

## Important Notes

- **Document Root:** Must point to the `public` directory
- **.htaccess:** Already configured in `public/.htaccess`
- **Routing:** Works automatically with Laragon's Apache/Nginx setup

## Troubleshooting

### 404 Errors
- Ensure virtual host points to `public` directory, not project root
- Check that `.htaccess` file exists in `public/` folder
- Verify `mod_rewrite` is enabled in Apache

### Routing Not Working
- Check Apache `mod_rewrite` is enabled
- Verify `.htaccess` file is present
- Ensure `AllowOverride All` is set in Apache config

### Access via localhost:8000
If you want to use port 8000, you need to:
1. Stop Laragon's default server
2. Use the PHP built-in server scripts provided
3. Or configure Laragon to use port 8000

**Recommended:** Use Laragon's default setup with virtual hosts (port 80) for best compatibility.

