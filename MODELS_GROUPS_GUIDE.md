# Models & Groups Implementation Guide

## Overview
The Models page now displays product items organized into **two fixed groups**:
- **Group A**: Single Gas Detectors
- **Group B**: Multi Gas Detectors

All data is pulled from the `delivery_records` table, automatically categorized based on item names.

---

## How Items Are Grouped

### Automatic Categorization (Default)
Items are auto-grouped based on their `item_name` field:

| Criteria | Group |
|----------|-------|
| Contains "single" (case-insensitive) | Group A |
| Contains "multi" or "multiple" | Group B |
| No match | Group A (default) |

### Manual Assignment (Override)
Set the `groupings` column to explicitly assign items:
- `groupings = 'A'` → Group A
- `groupings = 'B'` → Group B  
- `groupings = NULL` → Uses auto-detection

---

## Adding/Editing Items with Groupings

### Via Delivery Records UI
1. Navigate to **Delivery Records** page
2. Click **Add Record** or **Edit** an existing record
3. Set the **Groupings** field to 'A' or 'B'
4. Save the record

### Via CSV Import (Upload Data)
Include a `groupings` column with values 'A' or 'B':

```csv
invoice_no,item_code,item_name,groupings
INV001,MCX3-BC1,BW Gas Detector - Single,A
INV002,MCX3-MULTI,BW Gas Detector - Multi,B
```

### Via API
POST to `api/add-record.php` or `api/update-record.php` with:
```json
{
  "item_code": "MCX3-BC1",
  "item_name": "BW Gas Detector - Single",
  "groupings": "A"
}
```

---

## Models Page Features

### Group Cards
Each group displays:
- **Models count**: Number of unique item codes in group
- **Units**: Total quantity delivered
- **Orders**: Total delivery record count

### View Details
Click any group card to see:
- Searchable table of all items in that group
- Item code and name
- Units delivered per item
- Number of orders per item

### Search
Use the search modal to filter items by code or name within each group.

---

## Database Schema

### Required Column
```sql
ALTER TABLE delivery_records ADD COLUMN groupings VARCHAR(50) DEFAULT NULL;
```

The column stores 'A' or 'B' to override auto-detection.

---

## Example Items

### Group A (Single Gas)
- "BW Gas Detector - Single Channel"
- "Single Gas Monitor"
- "MCX3-BC1"

### Group B (Multi Gas)  
- "BW Gas Detector - Multi Channel"
- "Multi Gas Detector"
- "MCX3-MULTI"

---

## Troubleshooting

### Items Not Showing in Expected Group
1. Check if `item_name` contains "single" or "multi"
2. Verify `groupings` column value (should be NULL to auto-detect)
3. Edit the record and explicitly set groupings='A' or 'B'

### Missing Groupings Column
Run: `api/add-delivery-columns.php` to add missing columns

### No Delivery Records Showing
- Ensure records have `item_code` and `item_name` populated
- Check that records are in the database: `SELECT * FROM delivery_records`
