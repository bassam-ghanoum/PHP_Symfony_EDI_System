# EDI Invoice Management System

A modern, robust EDIFACT invoice processing and management system built with Symfony 8 and PHP 8.5.

## Features

### 📋 Invoice Processing
- **EDIFACT Invoice Parsing**: Automatically parse and validate EDIFACT D96A invoice messages
- **Batch File Processing**: Import multiple invoice files at once from the import directory
- **Secure File Handling**: Automatically organize processed files for audit trails

### 🏢 Supplier & Brand Management
- Manage supplier (vendor) information
- Track multiple buyer entities
- Organize invoices by brand

### 📊 Invoice Data Extraction
The system extracts and stores:
- Invoice numbers and types
- Order IDs and dispatch IDs
- Pricing information (amounts before/after tax)
- Tax details (type and amount)
- Line item details (item codes, quantities, unit prices)
- Invoice dates and references

### 🔄 Asynchronous Processing
- Message queue support for handling large invoice batches
- Background job processing for non-blocking operations

### 🗄️ Data Persistence
- MySQL database for secure invoice storage
- Structured entity models for maintainability
- Database migrations for version control

## Quick Start

### Prerequisites
- Docker and Docker Compose installed on your machine
- The root `.env` file configured with database credentials (see [../README.md](../README.md))

### Starting the Application

1. **Start the Docker environment** from the project root:
   ```bash
   cd ..
   docker compose up --build -d
   ```

2. **Install dependencies** (if first time):
   ```bash
   docker compose exec edi_system composer install
   ```

3. **Run database migrations**:
   ```bash
   docker compose exec edi_system php bin/console doctrine:migrations:migrate
   ```

4. **Access the application**:
   - Main application: http://localhost:8080
   - Database management: http://localhost:8081 (phpMyAdmin)

## Usage Guide

### Importing Invoice Files

1. **Place EDIFACT files** in the `ImportedFiles/input/` directory
2. **Run the import command**:
   ```bash
   docker compose exec edi_system php bin/console app:imported-files:move
   ```
3. The system will:
   - Parse each EDIFACT invoice file
   - Extract invoice data
   - Store in the database
   - Move processed files to `ImportedFiles/imported/` directory

### Supported File Format

The system accepts **EDIFACT INVOIC D96A** messages. Example structure:
```
UNA:+.? '
UNH+1+INVOIC:D:96A:UN'
BGM+380+INV123456+9'
RFF+ON:12345'
RFF+DQ:67890'
MOA+79:1000,00'
MOA+9:1210,00'
TAX+7+VAT'
LIN+1+ITEM001'
QTY+1:100'
PRI+AAB:10,00'
UNT+10+1'
```

### Web Interface

Access the home page at http://localhost:8080 to:
- View application status
- Monitor import activities
- Check invoice records

## Development Commands

### Run Tests
```bash
docker compose exec edi_system php bin/phpunit
```

### Run Code Quality Checks
```bash
docker compose exec edi_system php bin/phpstan
```

### Access Database

**Using phpMyAdmin**:
- Navigate to http://localhost:8081
- Login with credentials from your `.env` file

**Using command line**:
```bash
docker compose exec edi_mysql mysql -u root -p
```

### View Application Logs
```bash
docker compose logs -f edi_system
```

## Directory Structure

```
app/
├── src/
│   ├── Controller/        # Web controllers
│   ├── Entity/            # Database entities (Invoice, Brand, Buyer, etc.)
│   ├── Service/           # Business logic services
│   ├── Parser/            # EDIFACT invoice parser
│   ├── Command/           # CLI commands
│   └── Message/           # Message queue handlers
├── tests/                 # Unit and integration tests
├── migrations/            # Database migrations
├── public/                # Web root
├── templates/             # Twig HTML templates
├── config/                # Application configuration
└── ImportedFiles/         # Invoice import directories
    ├── input/             # Place invoice files here
    └── imported/          # Processed files
```

## Database Schema

Key entities:
- **Invoice**: Core invoice information with amounts and taxes
- **InvoiceDetail**: Line items within invoices
- **Brand**: Brand/company information
- **Buyer**: Purchasing entity information
- **IncomingFile**: Track imported EDI files

## Troubleshooting

### Files Not Importing
- Check that files are in `ImportedFiles/input/` directory
- Verify files are valid EDIFACT INVOIC D96A format
- Check application logs: `docker compose logs edi_system`

### Database Connection Issues
- Verify `.env` credentials match your configuration
- Ensure MySQL service is running: `docker compose ps`
- Check port 3307 is not in use on host machine

### Permission Issues
- Ensure proper file permissions on `ImportedFiles/` directory
- Run: `docker compose exec edi_system chown -R www-data:www-data ImportedFiles/`

## Configuration

Application configuration files are located in `config/`:
- `packages/`: Service configuration (database, cache, security, etc.)
- `services.yaml`: Service definitions
- `routes.yaml`: URL routing
- `security.yaml`: Authentication and authorization

Modify `.env` file in the project root to override settings:
```
DATABASE_URL=mysql://user:password@edi_mysql:3306/edi
```

## Performance Considerations

- Batch processing: Process multiple files in one command run
- Message queues: Enable async processing for large volumes
- Database indexing: Already configured on invoice numbers and dates
- File cleanup: Regularly archive files from `ImportedFiles/imported/`

## Support & Documentation

- **Symfony Documentation**: https://symfony.com/doc
- **EDIFACT Standards**: https://www.unece.org/cefact/edifact/welcome.html
- **Docker Documentation**: https://docs.docker.com/

## License

All rights reserved.
