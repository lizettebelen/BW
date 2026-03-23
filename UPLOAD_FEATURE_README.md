# Excel Data Upload Feature - Setup Guide

## Overview
The Excel Data Upload feature allows administrators to bulk import delivery records into the BW Gas Detector system using Excel (.xlsx, .xls) or CSV files.

## Features
✅ Drag-and-drop file upload interface
✅ Support for Excel (.xlsx, .xls) and CSV formats
✅ Real-time data preview before import
✅ Automatic data validation
✅ Error reporting for invalid records
✅ File size limit: 20MB
✅ Template download for correct format

## Access
Navigate to: **Upload Data** menu item in the sidebar
URL: `http://localhost/BW%20Gas%20Detector%20Project/upload-data.php`

## Database Setup

### Step 1: Create the Database
Execute the SQL commands in `DATABASE_SCHEMA.sql`:

```bash
mysql -u root -p < DATABASE_SCHEMA.sql
```

Or manually create the table:
```sql
CREATE DATABASE bw_gas_detector;
USE bw_gas_detector;

CREATE TABLE delivery_records (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  delivery_month VARCHAR(20) NOT NULL,
  delivery_day INT(2) NOT NULL,
  item_code VARCHAR(50) NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  company_name VARCHAR(255) NOT NULL,
  quantity INT(11) NOT NULL DEFAULT 0,
  status VARCHAR(50) NOT NULL DEFAULT 'Pending',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_delivery (delivery_month, delivery_day, item_code, company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Step 2: Update API Configuration
Edit `api/import-data.php` with your database credentials:

```php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';  // Your MySQL password
$db_name = 'bw_gas_detector';
```

## Excel File Format

### Required Columns
The Excel file must contain the following columns (in any order):

| Column Name | Type | Description | Example |
|------------|------|-------------|---------|
| Delivery_Month | Text | Month of delivery | January, February, etc. |
| Delivery_Day | Number | Day of delivery (1-31) | 1, 15, 28 |
| Item_Code | Text | Product code | MCX3-BC1, MCX3-FC1 |
| Item_Name | Text | Full product name | BW Gas Detector - Model 3 BC1 |
| Company_Name | Text | Client/Company name | Addison Industrial |
| Quantity | Number | Number of units | 10, 25, 50 |
| Status | Text | Delivery status | Delivered, Pending, In Transit |
| Notes | Text | Optional comments | Any notes about the delivery |

### Sample Excel Format
```
| Delivery_Month | Delivery_Day | Item_Code  | Item_Name                      | Company_Name         | Quantity | Status    | Notes                |
|----------------|--------------|------------|--------------------------------|----------------------|----------|-----------|----------------------|
| January        | 1            | MCX3-BC1   | BW Gas Detector - Model 3 BC1  | Addison Industrial   | 10       | Delivered | First shipment       |
| January        | 5            | MCX3-FC1   | BW Gas Detector - Model 3 FC1  | Tech Solutions Ltd   | 15       | Delivered | Rush order           |
| February       | 3            | MCX3-MPCB  | BW Gas Detector - Model 3 MPCB | Global Industries    | 8        | In Transit| Expected by Feb 10   |
```

### Download Template
Click "Download Template" button on the upload page to get a pre-formatted Excel template.

## How to Use

### Basic Workflow
1. **Navigate to Upload Data** → Click "Upload Data" in the sidebar menu
2. **Select File** → Drag & drop or click to browse for Excel/CSV file
3. **Parse & Preview** → Click "Parse & Preview" to see data preview
4. **Review Data** → Check the preview table for accuracy
5. **Import** → Click "Import Data" to save records to database
6. **Confirmation** → Success message shows number of imported records

### Validation Rules
- ✓ All required fields must be present
- ✓ Delivery_Day must be between 1-31
- ✓ Quantity must be non-negative
- ✓ File size must not exceed 20MB
- ✓ Supported formats: .xlsx, .xls, .csv
- ✓ Duplicate entries will be updated (unique key: month + day + item_code + company_name)

## Error Handling

### Common Errors
| Error | Cause | Solution |
|-------|-------|----------|
| "Invalid file format" | Wrong file type | Use Excel (.xlsx, .xls) or CSV |
| "File is too large" | Exceeds 20MB | Split file into smaller parts |
| "Missing required field" | Column not found | Download template and use correct column names |
| "Invalid delivery day" | Day < 1 or > 31 | Correct the day value |
| "Database connection failed" | API config incorrect | Update db credentials in api/import-data.php |

### Error Messages
- First 5 errors are displayed in the UI
- Additional errors are counted but not shown (to avoid clutter)
- Check server logs for detailed error information

## Files Overview

### Frontend
- **upload-data.php** - Main upload interface with file handling and preview
  - Drag & drop file upload
  - Client-side CSV parsing
  - Excel parsing using SheetJS library
  - Data preview table
  - Status messages and alerts

### Backend
- **api/import-data.php** - API endpoint for processing uploads
  - Validates data format
  - Connects to database
  - Inserts/updates records
  - Returns import summary

### Database
- **DATABASE_SCHEMA.sql** - SQL script for creating tables
  - Contains table definition
  - Sample data insertion
  - Index creation for performance

## Features Explained

### Drag & Drop Upload
- Click upload zone or drag file directly
- Visual feedback when hovering
- Automatic file validation

### Data Preview
- Shows first 10 rows of imported data
- Displays total rows, columns, and valid records
- Column headers extracted from file

### Duplicate Handling
- Uses unique key: (delivery_month, delivery_day, item_code, company_name)
- Duplicate records automatically update existing entries
- Prevents data duplication

### Template Download
- Pre-formatted Excel file with correct columns
- Sample data included
- Column widths optimized for readability

## Performance Considerations

- **Batch Processing** - Process data in batches for large files
- **Indexing** - Database has indexes on frequently queried columns
- **File Size Limit** - Limited to 20MB to prevent server overload
- **Preview Limit** - Shows only first 10 rows in preview

## Security Notes

⚠️ **Important Security Considerations:**
- Validate all user input (currently implemented)
- Use prepared statements for SQL (implemented with mysqli->prepare)
- Limit file upload size (20MB limit implemented)
- Restrict access to admin users only (todo: implement authentication)
- Add CSRF token validation (todo: implement)
- Sanitize file names (store in database, not file system)

### TODO: Enhanced Security
- [ ] Add user authentication check
- [ ] Add CSRF token validation
- [ ] Log all imports for audit trail
- [ ] Add admin approval workflow
- [ ] Rate limiting on API endpoint
- [ ] File scanning for malicious content

## Troubleshooting

### Issue: Upload button is disabled
**Solution:** Select a valid Excel or CSV file first

### Issue: "Parser error" or "Cannot read file"
**Solution:** Ensure file is not corrupted and uses correct format

### Issue: No data appears after import
**Solution:** 
- Check database credentials in api/import-data.php
- Ensure delivery_records table exists
- Check browser console for JavaScript errors

### Issue: Import shows fewer records than expected
**Solution:** Check error messages for validation failures and correct the data

## Browser Compatibility
- ✓ Chrome/Edge 80+
- ✓ Firefox 75+
- ✓ Safari 12+
- ✓ Mobile browsers (with drag-drop fallback)

## Dependencies
- **Frontend:** Chart.js (for library used in other pages)
- **Excel Parsing:** SheetJS (loaded via CDN)
- **Backend:** PHP 7.2+, MySQLi extension
- **Database:** MySQL 5.7+ or MariaDB

## Future Enhancements
- [ ] Scheduled/automated imports
- [ ] Multiple file import in batch
- [ ] Import history/logs
- [ ] Column mapping interface (if column names vary)
- [ ] Data transformation rules
- [ ] API documentation for programmatic import
- [ ] Webhook support for real-time sync
- [ ] Export to Excel (reverse function)
