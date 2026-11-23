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
- The root `.env` file configured with database credentials (see [https://github.com/bassam-ghanoum/PHP_MYSQL_ENV/blob/master/README.md](../README.md))

### Starting the Application

1. **Start the Docker environment** from the project root:
   ```bash
   cd ..
   docker compose up --build -d
   ```

2. **Install dependencies** (if first time):
   ```bash
   docker exec system_environment composer install
   ```

3. **Run database migrations**:
   ```bash
   docker exec system_environment php bin/console doctrine:migrations:migrate
   ```

4. **Access the application**:
   - Main application: http://localhost:8080
   - Database management: http://localhost:8081 (phpMyAdmin)

## Usage Guide

### Importing Invoice Files

To try the bundled invoice end to end, first apply the migration and load the fixture SQL into your development database. In phpMyAdmin (`http://localhost:8081`), select the application database, choose **Import**, select `app/fixtures/initial_data.sql`, and execute it. The inserts are safe to re-run and do not clear existing data.

From the repository root, copy the sample into the watched input directory:

```powershell
Copy-Item .\app\fixtures\sample_invoice.edi .\app\ImportedFiles\input\sample_invoice.edi
```

Then queue it for processing:

   ```bash
   docker exec system_environment php bin/console app:imported-files:move
   ```

That command moves the file and queues it; it does not parse it synchronously. Process the queued invoice with:

```bash
docker exec system_environment php bin/console messenger:consume async --limit=1 -vv
```

Check phpMyAdmin for invoice `FIXTURE-INV-001`.

The current importer does not yet associate parsed line item codes with `item` records, so the imported invoice detail's `item_id` remains null even though the fixture includes a matching product and item.

For normal imports, place `.edi` or `.txt` files in `ImportedFiles/input/`, run `app:imported-files:move`, then run a Messenger `async` consumer. Processed files are moved to `ImportedFiles/imported/`.

### Supported File Format

The system accepts **EDIFACT INVOIC D96A** messages.

### Web Interface

Access the home page at http://localhost:8080 to:
- View application status
- Monitor import activities
- Check invoice records

## Development Commands

### Run Tests
```bash
docker exec system_environment php bin/phpunit
```

### Access Database

**Using phpMyAdmin**:
- Navigate to http://localhost:8081
- Login with credentials from your `.env` file

**Using command line**:
```bash
docker exec mysql_environment mysql -u root -p
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
- Check application logs: `docker compose logs system_environment`

### Database Connection Issues
- Verify `.env` credentials match your configuration
- Ensure MySQL service is running: `docker compose ps`
- Check port 3307 is not in use on host machine

### Permission Issues
- Ensure proper file permissions on `ImportedFiles/` directory
- Run: `docker exec system_environment chown -R www-data:www-data ImportedFiles/`

## Configuration
Modify `.env` file in the project root to override settings:
```
DATABASE_URL=mysql://user:password@mysql_environment:3306/edi
```

## Performance Considerations

- Batch processing: Process multiple files in one command run
- Message queues: Enable async processing for large volumes
- Database indexing: Already configured on invoice numbers and dates
- File cleanup: Regularly archive files from `ImportedFiles/imported/`

## License

All rights reserved.
