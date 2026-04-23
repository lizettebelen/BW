# Warranty Detection Feature - Implementation Guide

## Overview

The Warranty Detection Feature automatically identifies warranty-related rows in Excel uploads based on **RED text color** and creates duplicate records in a `warranty_replacements` table while maintaining the original record in `delivery_records`.

## Key Features

✅ **Automatic Detection**: Detects RED text color (ARGB: FFFF0000) at the row level
✅ **Non-Destructive**: Records are COPIED, not moved - original stays in delivery_records
✅ **Row-Level Detection**: If ANY cell in a row has red text, the entire row is flagged as warranty
✅ **Built-in Integration**: Works seamlessly with existing upload flow
✅ **CSV Compatible**: CSV files skip color detection (they don't support formatting)
✅ **Database Agnostic**: Works with both MySQL and SQLite

## How It Works

### 1. **File Upload and Detection** (Frontend)
When a user uploads an Excel file:
1. File is parsed as usual
2. File is sent to `api/detect-warranty-rows.php` for warranty detection
3. Backend analyzes each row for RED text color
4. Returns array of row indices that contain red text
5. Row indices are stored in memory for import processing

### 2. **Warranty Data Storage** (Backend)
During import in `api/import-data.php`:
1. All rows are inserted normally into `delivery_records` table
2. Rows flagged as warranty (from detection) are ALSO inserted into `warranty_replacements` table
3. `warranty_replacements` includes reference to the original `delivery_record_id`
4. Warranty metadata includes:
   - `warranty_flag` (1 = warranty item)
   - `red_text_detected` (1 = red text found during import)
   - `warranty_date` (date flagged)
   - `delivery_record_id` (link back to original record)

## Database Schema

### warranty_replacements Table
```sql
-- All delivery information is copied from delivery_records
- id: Auto-increment primary key
- delivery_record_id: Reference to original delivery_records.id (nullable, optional)
- delivery_month, delivery_day, delivery_year: Date info
- invoice_no, serial_no, item_code, item_name: Product details
- company_name, sold_to: Company information
- quantity, uom: Amount and unit
- status: Warranty-specific status (default: "Warranty Pending")
- warranty_flag: 1 = yes, 0 = no (default: 1)
- warranty_date: Date record was flagged
- red_text_detected: 1 = had red text during import
- dataset_name: Dataset this came from
- highlight_color, cell_styles: Excel formatting info
- created_at, updated_at: Timestamps
```

## API Endpoints

### 1. detect-warranty-rows.php
**Purpose**: Analyze Excel file for red text
**Method**: POST (multipart form data)
**Input**:
```
file: <Excel file (.xlsx, .xls, or .csv)>
```

**Response**:
```json
{
  "success": true,
  "warranty_rows": [0, 2, 5],      // 0-based row indices with red text
  "total_rows": 10,                 // Total data rows processed
  "message": "Warranty row detection complete"
}
```

**Notes**:
- CSV files return empty warranty_rows (no formatting support)
- Header row (row 1) is skipped
- Multiple red color shades supported (FF, dark red, light red, etc.)

### 2. create-warranty-table.php
**Purpose**: Initialize warranty_replacements table
**Method**: GET or POST
**Response**:
```json
{
  "success": true,
  "message": "warranty_replacements table created or already exists"
}
```

**Auto-Called**: On database initialization or first import

### 3. import-data.php (Modified)
**New Input Parameter**:
```json
{
  "data": [...],
  "dataset_name": "data1",
  "warranty_rows": [0, 2, 5],          // NEW: Array of warranty row indices
  "timestamp": "2026-04-14T..."
}
```

**Behavior**:
- Inserts all rows to `delivery_records`
- Inserts warranty rows to `warranty_replacements` (copies only)
- Maintains data integrity in both tables

## File Locations

| File | Purpose |
|------|---------|
| `api/detect-warranty-rows.php` | Detects red text in Excel files |
| `api/create-warranty-table.php` | Creates warranty_replacements table |
| `api/import-data.php` | (Modified) Handles warranty duplication |
| `upload-data.php` | (Modified) Integrates warranty detection |

## Usage Guide for End Users

### 1. **Prepare Excel File**
- Format warranty items with RED text color
- Red text can be applied to any cell in the row
- All cells in that row will be treated as warranty

### 2. **Upload File**
- Go to "Upload Data" page
- Drag & drop or select Excel file
- System automatically detects warranty rows (background process)

### 3. **Import Data**
- Click "Parse & Preview"
- Click "Import Data"
- System:
  - Saves all records to delivery_records
  - Saves red-text rows to warranty_replacements
  - Shows success message

### 4. **View Warranty Records** (Future Enhancement)
- Can create a "Warranty Dashboard" to view warranty_replacements table
- Can filter delivery_records by warranty status
- Can link records between tables

## Color Detection Details

### Supported Red Colors (ARGB)
- `FFFF0000` - Pure red
- `FFC00000` - Dark red  
- `FFE74C3C` - Light red
- `FFD32F2F` - Material red
- `FF960000` - Maroon red

### Indexed Colors
- Excel palette index 3, 5, 10, 52 (red colors)

## Example Workflow

```
Excel File Upload
    ↓
[detect-warranty-rows.php]
    ↓ Detects: [Row 2, Row 4] have red text
    ↓
[upload-data.php stores warranty_rows]
    ↓
[import-data.php receives warranty_rows]
    ↓
INSERT delivery_records (all rows)
    ↓
INSERT warranty_replacements (rows 2 & 4 only)
    ↓
✅ Import complete - data in both tables
```

## Technical Implementation Details

### Detection Algorithm
1. Load Excel file with PhpSpreadsheet
2. Iterate through rows (skip header)
3. For each row, check all cells:
   - Get cell style and font color
   - Extract ARGB color value
   - Compare against red_patterns array
   - If match found, mark row as warranty
4. Return array of warranty row indices

### Data Duplication Strategy
- **Not a Move**: Original row stays in delivery_records
- **Copy with Link**: warranty_replacements row has `delivery_record_id` foreign key
- **Preserves Integrity**: Both tables maintain all original data
- **Audit Trail**: Can trace warranty records back to source

## Limitations & Constraints

⚠️ **CSV Files**: No color detection (CSV doesn't support formatting)
⚠️ **Google Sheets**: May need to be downloaded as Excel first
⚠️ **Row-Level Only**: Software can't distinguish between red cells in a row (all or nothing)
⚠️ **Red Shades**: Only common red ARGB values supported (can be expanded)

## Future Enhancements

- [ ] Warranty Dashboard to view warranty_replacements records
- [ ] Warranty filtering in delivery records list
- [ ] Support for multiple colors (blue for warranty claims, green for completed, etc.)
- [ ] Warranty status management (pending → approved → replaced)
- [ ] Warranty report generation
- [ ] Email notifications for new warranty flagged items

## Troubleshooting

### Q: Red text not detected
**A**: Check that:
- Text color is pure RED (FFFF0000) or listed shade
- Not using border/fill color - must be font/text color
- File is .xlsx or .xls (CSV won't work)
- Row is not the header row (row 1)

### Q: Warranty table not created
**A**: 
- Manually call `api/create-warranty-table.php`
- Check database permissions
- Verify database connection in `db_config.php`

### Q: Records not showing in warranty_replacements
**A**:
- Check import response for `warranty_rows` count
- Verify red text was actually applied to cells
- Check `delivery_record_id` is not NULL (link to original)
-Click "Import Data" again to retry

### Q: Performance issues with large files
**A**:
- Detection works at row level (efficient)
- Duplication happens during insert (normal performance)
- File size limit: 20MB total
- Increase PHP execution time if needed

## Testing Checklist

- [ ] Create test Excel with red text rows
- [ ] Upload and see warranty_rows detected
- [ ] Verify records in both delivery_records and warranty_replacements
- [ ] Check foreign key linking works
- [ ] Test with CSV (should skip detection)
- [ ] Test with multiple files
- [ ] Verify cell_styles and highlight_color preserved
- [ ] Test with mixed red and normal rows

## Support & Debugging

Enable debugging by adding to detect-warranty-rows.php:
```php
error_log('Warranty detection: ' . json_encode($warranty_rows));
```

Check browser console (F12) for JavaScript logs:
- "✅ Warranty rows detected for..."
- "⚠️ Warranty detection failed for..."

## Related Documentation

- See `DATABASE_SCHEMA.sql` for complete schema
- See `UPLOAD_FEATURE_README.md` for upload system overview
- See `api/import-data.php` for import logic
