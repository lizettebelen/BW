<?php
/**
 * Warranty Row Detection API
 * 
 * Detects rows with RED text color (font color ARGB: FFFF0000)
 * Uses PhpSpreadsheet to read Excel formatting
 * 
 * POST Parameters:
 * - file input (multipart)
 * 
 * Response:
 * {
 *   "success": true,
 *   "warranty_rows": [0, 2, 5],        // Row indices with red text
 *   "total_rows": 10,
 *   "message": "Detection complete"
 * }
 */

// Load PhpSpreadsheet first
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

ob_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error'
    ]);
    exit;
}

// Validate file type
$file = $_FILES['file'];
$fileName = $file['name'];
$filePath = $file['tmp_name'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExt, ['xlsx', 'xls', 'csv'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only .xlsx, .xls, .csv are supported'
    ]);
    exit;
}

try {
    // CSV files don't have cell formatting, so skip detection
    if ($fileExt === 'csv') {
        ob_clean();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'warranty_rows' => [],
            'total_rows' => 0,
            'message' => 'CSV files do not support color detection. All rows treated as normal.'
        ]);
        exit;
    }

    // Read the Excel file
    $spreadsheet = IOFactory::load($filePath);

    $requestedSheet = isset($_POST['sheet_name']) ? trim((string) $_POST['sheet_name']) : '';
    $worksheet = $spreadsheet->getActiveSheet();
    if ($requestedSheet !== '') {
        $sheet = $spreadsheet->getSheetByName($requestedSheet);
        if ($sheet !== null) {
            $worksheet = $sheet;
        }
    }

    $warranty_rows = [];
    $total_rows = 0;

    // Define RED color (ARGB format: FFFF0000 = FF (alpha) + FF0000 (red))
    // We check for various shades of red
    $red_patterns = [
        'FFFF0000',  // Pure red
        'FFC00000',  // Dark red
        'FFE74C3C',  // Light red
        'FFD32F2F',  // Material red
        'FF960000',  // Maroon red
        'FF0000',    // Without alpha
        'C00000',    // Dark red without alpha
    ];

    $highest_row = $worksheet->getHighestRow();
    $highest_col = $worksheet->getHighestColumn();
    $highest_col_index = Coordinate::columnIndexFromString($highest_col);

    $normalizeColor = function (?string $color): string {
        $value = strtoupper(trim((string) $color));
        if ($value === '') return '';
        if ($value[0] === '#') $value = substr($value, 1);
        if (strlen($value) === 8 && strpos($value, 'FF') === 0) {
            $value = substr($value, 2);
        }
        return $value;
    };

    $isRedColor = function (?string $color) use ($normalizeColor, $red_patterns): bool {
        $normalized = $normalizeColor($color);
        if ($normalized === '') return false;

        foreach ($red_patterns as $pattern) {
            $target = $normalizeColor($pattern);
            if ($target !== '' && (strpos($normalized, $target) !== false || $normalized === $target)) {
                return true;
            }
        }
        return false;
    };

    // Keep row index aligned with parsed frontend data: only count non-empty data rows after header.
    $row_index = 0;
    for ($row_num = 2; $row_num <= $highest_row; $row_num++) {
        $has_any_value = false;
        $has_red_text = false;

        for ($col = 1; $col <= $highest_col_index; $col++) {
            $cellCoordinate = Coordinate::stringFromColumnIndex($col) . $row_num;
            $cell = $worksheet->getCell($cellCoordinate);
            if ($cell === null) {
                continue;
            }

            $rawValue = $cell->getValue();
            if ($rawValue instanceof RichText) {
                $text = trim($rawValue->getPlainText());
                if ($text !== '') {
                    $has_any_value = true;
                }

                // RichText can carry per-run font colors.
                foreach ($rawValue->getRichTextElements() as $element) {
                    $font = method_exists($element, 'getFont') ? $element->getFont() : null;
                    if ($font && $font->getColor() && $isRedColor($font->getColor()->getARGB())) {
                        $has_red_text = true;
                        break;
                    }
                }
            } else {
                $text = trim((string) $rawValue);
                if ($text !== '') {
                    $has_any_value = true;
                }
            }

            if (!$has_red_text) {
                try {
                    $font = $cell->getStyle()->getFont();
                    if ($font !== null && $font->getColor() !== null) {
                        $argb = $font->getColor()->getARGB();
                        if ($isRedColor($argb)) {
                            $has_red_text = true;
                        }
                    }
                } catch (Exception $e) {
                    // Ignore style parsing errors and continue scanning.
                }
            }

            if ($has_any_value && $has_red_text) {
                // No need to inspect remaining cells in this row.
                break;
            }
        }

        if (!$has_any_value) {
            continue;
        }

        if ($has_red_text) {
            $warranty_rows[] = $row_index;
        }
        $row_index++;
    }

    $total_rows = $row_index;

    ob_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'warranty_rows' => $warranty_rows,
        'total_rows' => $total_rows,
        'sheet_name' => $worksheet->getTitle(),
        'message' => 'Warranty row detection complete'
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error detecting warranty rows: ' . $e->getMessage()
    ]);
}

exit;
?>
