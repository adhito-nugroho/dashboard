# Program CRUD Module

Complete CRUD (Create, Read, Update, Delete) module for the `program` table.

## Files Structure

```
app/
├── Models/
│   └── Program.php          # Model with database operations
├── Controllers/
│   └── ProgramController.php # Controller with CRUD actions
views/
└── program/
    ├── index.php            # List all programs
    └── form.php             # Create/Edit form
```

## Features

- ✅ Full CRUD operations
- ✅ PDO database access
- ✅ Bootstrap 5 styling
- ✅ Form validation (server-side & client-side)
- ✅ Flash messages
- ✅ Responsive design
- ✅ MVC pattern
- ✅ Unique kode_program validation

## Database Fields

- `id` - Primary key (auto-increment)
- `kode_program` - Program code (required, unique, max 50 chars)
- `nama_program` - Program name (required, max 255 chars)
- `tahun` - Year (required, 2000-2100)

## Routes

- `GET /program` - List all programs
- `GET /program/create` - Show create form
- `POST /program/store` - Save new program
- `GET /program/edit/{id}` - Show edit form
- `POST /program/update/{id}` - Update program
- `GET /program/delete/{id}` - Delete program

## Usage

The module is ready to use. Access `/program` in your browser to see the list of programs.

## Validation Rules

1. **kode_program**: Required, unique, max 50 characters
2. **nama_program**: Required, max 255 characters
3. **tahun**: Required, must be between 2000 and 2100

