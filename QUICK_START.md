# Quick Start Guide - Excel Upload Feature

## 🚀 Get Started in 5 Minutes

### Step 1: Set Up Database (1 minute)
1. Open phpMyAdmin or MySQL command line
2. Run the SQL commands from `DATABASE_SCHEMA.sql`:
   ```bash
   mysql -u root < DATABASE_SCHEMA.sql
   ```
3. Or create database manually:
   ```sql
   CREATE DATABASE bw_gas_detector DEFAULT CHARACTER SET utf8mb4;
   ```

### Step 2: Create the Table (1 minute)
Execute in MySQL:
```sql
USE bw_gas_detector;

CREATE TABLE delivery_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_month VARCHAR(20) NOT NULL,
  delivery_day INT NOT NULL,
  item_code VARCHAR(50) NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  company_name VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 0,
  status VARCHAR(50) DEFAULT 'Pending',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (delivery_month, delivery_day, item_code, company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Step 3: Configure API (1 minute)
Edit `api/import-data.php` lines 10-13:
```php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'your_password_here';  // Add your MySQL password
$db_name = 'bw_gas_detector';
```

### Step 4: Access Upload Page (1 minute)
In browser: `http://localhost/BW%20Gas%20Detector%20Project/`
- Click **"Upload Data"** in sidebar menu
- Or go directly to: `upload-data.php`

### Step 5: Upload Your First File (1 minute)
1. Click "Download Template" to get sample format
2. Add your data to the Excel file
3. Drag & drop file or click to browse
4. Click "Parse & Preview"
5. Review data in preview table
6. Click "Import Data"
✅ Done! Records imported successfully

---

## 📊 Excel File Format

**Minimum columns required:**
- Delivery_Month (text)
- Delivery_Day (1-31)
- Item_Code (text)
- Company_Name (text)
- Quantity (number)
- Status (text)

**Example row:**
```
| Delivery_Month | Delivery_Day | Item_Code | Item_Name | Company_Name | Quantity | Status | Notes |
|---|---|---|---|---|---|---|---|
| January | 1 | MCX3-BC1 | BW Gas Detector - Model 3 BC1 | Addison Industrial | 10 | Delivered | Sample |
```

---

## ✅ Features

✓ Drag & drop upload
✓ Excel (.xlsx, .xls) and CSV support
✓ Real-time data preview
✓ Automatic validation
✓ Template download
✓ Error reporting
✓ Duplicate detection & update
✓ Max file size: 20MB

---

## 🆘 Troubleshooting

| Problem | Solution |
|---------|----------|
| Can't find Upload Data menu | Refresh browser, or check sidebar permissions |
| Parser error | Check file isn't corrupted, use template format |
| Import fails | Check database credentials in api/import-data.php |
| Fewer records imported | Check error messages for validation issues |
| Can't download template | Check browser console for JS errors |

---

## 📁 Files Created

```
BW Gas Detector Project/
├── upload-data.php                    ← Main upload page
├── api/
│   └── import-data.php                ← Backend API
├── DATABASE_SCHEMA.sql                ← Database setup
├── UPLOAD_FEATURE_README.md           ← Full documentation
└── QUICK_START.md                     ← This file
```

---

## 🔒 Next Steps (Optional Security)

Add these for production:
1. **Authentication** - Restrict to admin users only
2. **CSRF Protection** - Add token validation
3. **Audit Logging** - Track all imports
4. **Error Logging** - Store errors for review
5. **Rate Limiting** - Prevent abuse

---

## 📞 Support

Check full documentation in: `UPLOAD_FEATURE_README.md`

For issues:
1. Check browser console (F12)
2. Check server error logs
3. Check MySQL error logs
4. Verify database connection in api/import-data.php

Enjoy! 🎉
