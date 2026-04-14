<?php
/**
 * Extract per-cell style colors from an uploaded Excel sheet.
 *
 * POST (multipart):
 * - file: xlsx/xls
 * - sheet_name (optional): target worksheet name
 *
 * Response:
 * {
 *   success: true,
 *   sheet_name: "Sheet1",
 *   total_rows: 120,
 *   styled_rows: 18,
 *   row_cell_styles: { "0": { "Invoice No.": {"text":"#FF0000"} } },
 *   row_highlights: { "0": "#FFF2CC" }
 * }
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

ob_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error'
    ]);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$filePath = $file['tmp_name'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExt, ['xlsx', 'xls'])) {
    ob_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'sheet_name' => '',
        'total_rows' => 0,
        'styled_rows' => 0,
        'row_cell_styles' => new stdClass(),
        'row_highlights' => new stdClass(),
        'message' => 'Style extraction skipped for non-Excel file'
    ]);
    exit;
}

$normalizeColor = function (?string $color): string {
    $value = strtoupper(trim((string) $color));
    if ($value === '') return '';
    if ($value[0] === '#') $value = substr($value, 1);
    if (strlen($value) === 8 && strpos($value, 'FF') === 0) {
        $value = substr($value, 2);
    }
    if (!preg_match('/^[0-9A-F]{6}$/', $value)) {
        return '';
    }
    return '#' . $value;
};

$isNeutralColor = function (string $color): bool {
    $v = strtoupper(trim($color));
    return $v === '#000000' || $v === '#FFFFFF';
};

try {
    $spreadsheet = IOFactory::load($filePath);

    $requestedSheet = isset($_POST['sheet_name']) ? trim((string) $_POST['sheet_name']) : '';
    $worksheet = $spreadsheet->getActiveSheet();
    if ($requestedSheet !== '') {
        $sheet = $spreadsheet->getSheetByName($requestedSheet);
        if ($sheet !== null) {
            $worksheet = $sheet;
        }
    }

    $highestRow = (int) $worksheet->getHighestRow();
    $highestCol = $worksheet->getHighestColumn();
    $highestColIndex = Coordinate::columnIndexFromString($highestCol);

    $headers = [];
    for ($col = 1; $col <= $highestColIndex; $col++) {
        $coord = Coordinate::stringFromColumnIndex($col) . '1';
        $headerValue = trim((string) $worksheet->getCell($coord)->getValue());
        if ($headerValue !== '') {
            $headers[$col] = $headerValue;
        }
    }

    if (empty($headers)) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'sheet_name' => $worksheet->getTitle(),
            'total_rows' => 0,
            'styled_rows' => 0,
            'row_cell_styles' => new stdClass(),
            'row_highlights' => new stdClass(),
            'message' => 'No headers found'
        ]);
        exit;
    }

    $rowCellStyles = [];
    $rowHighlights = [];

    // Parsed row index aligned with frontend/import data rows (non-empty rows after header).
    $parsedRowIndex = 0;

    for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
        $hasAnyValue = false;
        $styleByHeader = [];
        $fillColorCounts = [];

        foreach ($headers as $col => $header) {
            $coord = Coordinate::stringFromColumnIndex($col) . $rowNum;
            $cell = $worksheet->getCell($coord);

            $value = trim((string) $cell->getValue());
            if ($value !== '') {
                $hasAnyValue = true;
            }

            $bgColor = '';
            $textColor = '';

            try {
                $style = $worksheet->getStyle($coord);
                $fill = $style->getFill();
                if ($fill) {
                    $fg = $fill->getStartColor() ? $fill->getStartColor()->getARGB() : '';
                    if ($fg === '') {
                        $fg = $fill->getEndColor() ? $fill->getEndColor()->getARGB() : '';
                    }
                    $bgColor = $normalizeColor($fg);
                }

                $font = $style->getFont();
                if ($font && $font->getColor()) {
                    $textColor = $normalizeColor($font->getColor()->getARGB());
                }
            } catch (Throwable $e) {
                // ignore per-cell style failures
            }

            $styleEntry = [];
            if ($bgColor !== '' && !$isNeutralColor($bgColor)) {
                $styleEntry['bg'] = $bgColor;
                $fillColorCounts[$bgColor] = ($fillColorCounts[$bgColor] ?? 0) + 1;
            }
            if ($textColor !== '' && !$isNeutralColor($textColor)) {
                $styleEntry['text'] = $textColor;
            }

            if (!empty($styleEntry)) {
                $styleByHeader[$header] = $styleEntry;
            }
        }

        if (!$hasAnyValue) {
            continue;
        }

        if (!empty($styleByHeader)) {
            $rowCellStyles[(string) $parsedRowIndex] = $styleByHeader;
        }

        if (!empty($fillColorCounts)) {
            arsort($fillColorCounts);
            $topColor = array_key_first($fillColorCounts);
            if (!empty($topColor)) {
                $rowHighlights[(string) $parsedRowIndex] = $topColor;
            }
        }

        $parsedRowIndex++;
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'sheet_name' => $worksheet->getTitle(),
        'total_rows' => $parsedRowIndex,
        'styled_rows' => count($rowCellStyles),
        'row_cell_styles' => empty($rowCellStyles) ? new stdClass() : $rowCellStyles,
        'row_highlights' => empty($rowHighlights) ? new stdClass() : $rowHighlights,
        'message' => 'Cell styles extracted'
    ]);
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error extracting cell styles: ' . $e->getMessage()
    ]);
}

exit;
