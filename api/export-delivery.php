<?php
/**
 * Export Delivery Records to Excel with formatted headers, summary and charts
 * Uses XML Spreadsheet format for Excel compatibility with styling
 */

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once '../db_config.php';

// Get all delivery records
$delivery_records = [];
$result = $conn->query("SELECT * FROM delivery_records ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $delivery_records[] = $row;
    }
}

// Calculate summary statistics
$monthly_data = [];
$company_data = [];
$yearly_data = [];
$grouping_data = [];
$total_quantity = 0;

foreach ($delivery_records as $record) {
    // Monthly count
    $month = $record['delivery_month'] ?? 'Unknown';
    if (!empty($month)) {
        if (!isset($monthly_data[$month])) {
            $monthly_data[$month] = ['count' => 0, 'quantity' => 0];
        }
        $monthly_data[$month]['count']++;
        $monthly_data[$month]['quantity'] += intval($record['quantity'] ?? 0);
    }
    
    // Company count
    $company = $record['company_name'] ?? 'Unknown';
    if (!empty($company)) {
        if (!isset($company_data[$company])) {
            $company_data[$company] = ['count' => 0, 'quantity' => 0];
        }
        $company_data[$company]['count']++;
        $company_data[$company]['quantity'] += intval($record['quantity'] ?? 0);
    }
    
    // Yearly count
    $year = $record['delivery_year'] ?? 'Unknown';
    if (!empty($year)) {
        if (!isset($yearly_data[$year])) {
            $yearly_data[$year] = ['count' => 0, 'quantity' => 0];
        }
        $yearly_data[$year]['count']++;
        $yearly_data[$year]['quantity'] += intval($record['quantity'] ?? 0);
    }
    
    // Grouping count
    $grouping = $record['groupings'] ?? 'Ungrouped';
    if (empty($grouping)) $grouping = 'Ungrouped';
    if (!isset($grouping_data[$grouping])) {
        $grouping_data[$grouping] = ['count' => 0, 'quantity' => 0];
    }
    $grouping_data[$grouping]['count']++;
    $grouping_data[$grouping]['quantity'] += intval($record['quantity'] ?? 0);
    
    $total_quantity += intval($record['quantity'] ?? 0);
}

// Sort monthly data by proper month order
$month_order = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$sorted_monthly = [];
foreach ($month_order as $m) {
    if (isset($monthly_data[$m])) {
        $sorted_monthly[$m] = $monthly_data[$m];
    }
}

// Sort company data by count (descending)
arsort($company_data);
$top_companies = array_slice($company_data, 0, 10, true);

// Sort yearly data
ksort($yearly_data);

// Set headers for Excel download
$filename = 'Delivery_Records_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Excel XML header
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Title>Delivery Records</Title>
  <Author>BW System</Author>
  <Created><?php echo date('Y-m-d\TH:i:s\Z'); ?></Created>
 </DocumentProperties>
 <Styles>
  <!-- Header Style - Blue background, white text, bold -->
  <Style ss:ID="Header">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1a3a5c"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1a3a5c"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1a3a5c"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1a3a5c"/>
   </Borders>
   <Font ss:Bold="1" ss:Color="#FFFFFF" ss:FontName="Calibri" ss:Size="11"/>
   <Interior ss:Color="#2f5fa7" ss:Pattern="Solid"/>
  </Style>
  <!-- Data Style - Normal cells -->
  <Style ss:ID="Data">
   <Alignment ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10"/>
  </Style>
  <!-- Number Style -->
  <Style ss:ID="Number">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10"/>
   <NumberFormat ss:Format="General"/>
  </Style>
  <!-- Date Style -->
  <Style ss:ID="Date">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#d0d0d0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10"/>
  </Style>
  <!-- Summary Sheet Styles -->
  <Style ss:ID="Title">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:Bold="1" ss:Color="#1a3a5c" ss:FontName="Calibri" ss:Size="18"/>
  </Style>
  <Style ss:ID="Subtitle">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:Color="#666666" ss:FontName="Calibri" ss:Size="11"/>
  </Style>
  <Style ss:ID="SectionHeader">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:Bold="1" ss:Color="#2f5fa7" ss:FontName="Calibri" ss:Size="13"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#2f5fa7"/>
   </Borders>
  </Style>
  <Style ss:ID="StatLabel">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#444444"/>
   <Interior ss:Color="#f5f5f5" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
   </Borders>
  </Style>
  <Style ss:ID="StatValue">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:Bold="1" ss:FontName="Calibri" ss:Size="12" ss:Color="#2f5fa7"/>
   <Interior ss:Color="#e8f4fc" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#c5ddf0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#c5ddf0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#c5ddf0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#c5ddf0"/>
   </Borders>
  </Style>
  <Style ss:ID="TableHeader">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:Bold="1" ss:Color="#FFFFFF" ss:FontName="Calibri" ss:Size="10"/>
   <Interior ss:Color="#27ae60" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e8449"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e8449"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e8449"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e8449"/>
   </Borders>
  </Style>
  <Style ss:ID="TableData">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10"/>
   <Interior ss:Color="#f9f9f9" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
   </Borders>
  </Style>
  <Style ss:ID="TableNumber">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:Bold="1" ss:FontName="Calibri" ss:Size="10" ss:Color="#27ae60"/>
   <Interior ss:Color="#f9f9f9" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e0e0e0"/>
   </Borders>
  </Style>
  <Style ss:ID="Instruction">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#666666" ss:Italic="1"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Delivery Records">
  <Table ss:DefaultColumnWidth="100" ss:DefaultRowHeight="20">
   <!-- Column Widths -->
   <Column ss:Width="100"/> <!-- Invoice No. -->
   <Column ss:Width="85"/>  <!-- Date -->
   <Column ss:Width="160"/> <!-- Delivery Month to Andison -->
   <Column ss:Width="150"/> <!-- Delivery Day to Andison -->
   <Column ss:Width="55"/>  <!-- Year -->
   <Column ss:Width="120"/> <!-- Item -->
   <Column ss:Width="220"/> <!-- Description -->
   <Column ss:Width="45"/>  <!-- Qty. -->
   <Column ss:Width="55"/>  <!-- UOM -->
   <Column ss:Width="120"/> <!-- Serial No. -->
   <Column ss:Width="180"/> <!-- Sold To -->
   <Column ss:Width="100"/> <!-- Date Delivered -->
   <Column ss:Width="100"/> <!-- Sold To Month -->
   <Column ss:Width="90"/>  <!-- Sold To Day -->
   <Column ss:Width="150"/> <!-- Remarks -->
   <Column ss:Width="80"/>  <!-- Groupings -->
   
   <!-- Header Row -->
   <Row ss:Height="28">
    <Cell ss:StyleID="Header"><Data ss:Type="String">Invoice No.</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Date</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Delivery Month to Andison</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Delivery Day to Andison</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Year</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Item</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Description</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Qty.</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">UOM</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Serial No.</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Sold To</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Date Delivered</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Sold To Month</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Sold To Day</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Remarks</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Groupings</Data></Cell>
   </Row>
   
   <!-- Data Rows -->
   <?php foreach ($delivery_records as $record): 
       // Format date
       $date_col = '';
       if (!empty($record['delivery_date'])) {
           $date_col = date('m/d/Y', strtotime($record['delivery_date']));
       }
       
       // Format delivery date
       $delivery_date_formatted = '';
       if (!empty($record['delivery_date'])) {
           $delivery_date_formatted = date('M j, Y', strtotime($record['delivery_date']));
       }
       
       // Escape special characters for XML
       $escape = function($str) {
           return htmlspecialchars($str ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
       };
   ?>
   <Row>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['invoice_no']); ?></Data></Cell>
    <Cell ss:StyleID="Date"><Data ss:Type="String"><?php echo $escape($date_col); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['delivery_month']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['delivery_day']); ?></Data></Cell>
    <Cell ss:StyleID="Number"><Data ss:Type="String"><?php echo $escape($record['delivery_year']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['item_code']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['item_name']); ?></Data></Cell>
    <Cell ss:StyleID="Number"><Data ss:Type="<?php echo (!empty($record['quantity']) && $record['quantity'] > 0) ? 'Number' : 'String'; ?>"><?php echo (!empty($record['quantity']) && $record['quantity'] > 0) ? intval($record['quantity']) : ''; ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['uom']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['serial_no']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['company_name']); ?></Data></Cell>
    <Cell ss:StyleID="Date"><Data ss:Type="String"><?php echo $escape($delivery_date_formatted); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['sold_to_month']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['sold_to_day']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['notes']); ?></Data></Cell>
    <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo $escape($record['groupings']); ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Layout x:Orientation="Landscape"/>
   </PageSetup>
   <FitToPage/>
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>1</SplitHorizontal>
   <TopRowBottomPane>1</TopRowBottomPane>
   <ActivePane>2</ActivePane>
  </WorksheetOptions>
 </Worksheet>
 
 <!-- Summary & Charts Worksheet -->
 <Worksheet ss:Name="Summary &amp; Charts">
  <Table ss:DefaultColumnWidth="100" ss:DefaultRowHeight="18">
   <Column ss:Width="180"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   <Column ss:Width="30"/>
   <Column ss:Width="180"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   
   <!-- Title -->
   <Row ss:Height="35">
    <Cell ss:StyleID="Title" ss:MergeAcross="2"><Data ss:Type="String">📊 Delivery Records Summary</Data></Cell>
   </Row>
   <Row ss:Height="20">
    <Cell ss:StyleID="Subtitle"><Data ss:Type="String">Generated: <?php echo date('F j, Y'); ?></Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>
   
   <!-- Overview Stats -->
   <Row ss:Height="24">
    <Cell ss:StyleID="SectionHeader" ss:MergeAcross="2"><Data ss:Type="String">📈 Overview Statistics</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="StatLabel"><Data ss:Type="String">Total Records</Data></Cell>
    <Cell ss:StyleID="StatValue"><Data ss:Type="Number"><?php echo count($delivery_records); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="StatLabel"><Data ss:Type="String">Total Quantity</Data></Cell>
    <Cell ss:StyleID="StatValue"><Data ss:Type="Number"><?php echo $total_quantity; ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="StatLabel"><Data ss:Type="String">Unique Companies</Data></Cell>
    <Cell ss:StyleID="StatValue"><Data ss:Type="Number"><?php echo count($company_data); ?></Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>
   
   <!-- Monthly Deliveries Table -->
   <Row ss:Height="24">
    <Cell ss:StyleID="SectionHeader" ss:MergeAcross="2"><Data ss:Type="String">📅 Monthly Deliveries</Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="SectionHeader" ss:MergeAcross="2"><Data ss:Type="String">🏢 Top Companies</Data></Cell>
   </Row>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Month</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Records</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Quantity</Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Company</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Records</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Quantity</Data></Cell>
   </Row>
   <?php 
   $month_keys = array_keys($sorted_monthly);
   $company_keys = array_keys($top_companies);
   $max_rows = max(count($sorted_monthly), count($top_companies));
   
   for ($i = 0; $i < $max_rows; $i++): 
       $month = isset($month_keys[$i]) ? $month_keys[$i] : '';
       $month_count = isset($sorted_monthly[$month]) ? $sorted_monthly[$month]['count'] : '';
       $month_qty = isset($sorted_monthly[$month]) ? $sorted_monthly[$month]['quantity'] : '';
       
       $company = isset($company_keys[$i]) ? $company_keys[$i] : '';
       $company_count = isset($top_companies[$company]) ? $top_companies[$company]['count'] : '';
       $company_qty = isset($top_companies[$company]) ? $top_companies[$company]['quantity'] : '';
   ?>
   <Row>
    <Cell ss:StyleID="TableData"><Data ss:Type="String"><?php echo htmlspecialchars($month); ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $month_count !== '' ? 'Number' : 'String'; ?>"><?php echo $month_count; ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $month_qty !== '' ? 'Number' : 'String'; ?>"><?php echo $month_qty; ?></Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="TableData"><Data ss:Type="String"><?php echo htmlspecialchars($company); ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $company_count !== '' ? 'Number' : 'String'; ?>"><?php echo $company_count; ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $company_qty !== '' ? 'Number' : 'String'; ?>"><?php echo $company_qty; ?></Data></Cell>
   </Row>
   <?php endfor; ?>
   
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>
   
   <!-- Yearly Summary -->
   <Row ss:Height="24">
    <Cell ss:StyleID="SectionHeader" ss:MergeAcross="2"><Data ss:Type="String">📆 Yearly Summary</Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="SectionHeader" ss:MergeAcross="2"><Data ss:Type="String">🏷️ By Groupings</Data></Cell>
   </Row>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Year</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Records</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Quantity</Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Group</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Records</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Quantity</Data></Cell>
   </Row>
   <?php 
   $year_keys = array_keys($yearly_data);
   $group_keys = array_keys($grouping_data);
   $max_rows2 = max(count($yearly_data), count($grouping_data));
   
   for ($i = 0; $i < $max_rows2; $i++): 
       $year = isset($year_keys[$i]) ? $year_keys[$i] : '';
       $year_count = isset($yearly_data[$year]) ? $yearly_data[$year]['count'] : '';
       $year_qty = isset($yearly_data[$year]) ? $yearly_data[$year]['quantity'] : '';
       
       $group = isset($group_keys[$i]) ? $group_keys[$i] : '';
       $group_count = isset($grouping_data[$group]) ? $grouping_data[$group]['count'] : '';
       $group_qty = isset($grouping_data[$group]) ? $grouping_data[$group]['quantity'] : '';
   ?>
   <Row>
    <Cell ss:StyleID="TableData"><Data ss:Type="String"><?php echo htmlspecialchars($year); ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $year_count !== '' ? 'Number' : 'String'; ?>"><?php echo $year_count; ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $year_qty !== '' ? 'Number' : 'String'; ?>"><?php echo $year_qty; ?></Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="TableData"><Data ss:Type="String"><?php echo htmlspecialchars($group); ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $group_count !== '' ? 'Number' : 'String'; ?>"><?php echo $group_count; ?></Data></Cell>
    <Cell ss:StyleID="TableNumber"><Data ss:Type="<?php echo $group_qty !== '' ? 'Number' : 'String'; ?>"><?php echo $group_qty; ?></Data></Cell>
   </Row>
   <?php endfor; ?>
   
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>
   
   <!-- Chart Instructions -->
   <Row ss:Height="24">
    <Cell ss:StyleID="SectionHeader" ss:MergeAcross="6"><Data ss:Type="String">💡 How to Create Charts</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="Instruction" ss:MergeAcross="6"><Data ss:Type="String">1. Select any data table above (e.g., Monthly Deliveries data including headers)</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="Instruction" ss:MergeAcross="6"><Data ss:Type="String">2. Go to Insert → Chart (or press Alt+F1 for quick chart)</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="Instruction" ss:MergeAcross="6"><Data ss:Type="String">3. Choose your preferred chart type (Bar, Column, Pie, Line)</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="Instruction" ss:MergeAcross="6"><Data ss:Type="String">4. Customize colors and labels as needed</Data></Cell>
   </Row>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Layout x:Orientation="Portrait"/>
   </PageSetup>
   <Print>
    <FitHeight>0</FitHeight>
    <FitWidth>1</FitWidth>
   </Print>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
