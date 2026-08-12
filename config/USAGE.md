# Database Connection Usage

## Setup

1. Copy `env.example.txt` to `.env` in the config directory
2. Update the `.env` file with your actual database credentials

## Usage Example

```php
<?php
// Load environment variables
require_once __DIR__ . '/config/load_env.php';

// Get database connection
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getConnection();
    
    // Use the connection
    $stmt = $db->prepare("SELECT * FROM seksi");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
```

## Environment Variables

- `DB_HOST` - Database host (default: localhost)
- `DB_PORT` - Database port (default: 3306)
- `DB_NAME` - Database name (default: db_anggaran)
- `DB_USER` - Database username (required)
- `DB_PASS` - Database password (required)

