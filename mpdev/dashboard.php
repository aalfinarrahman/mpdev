<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv']); // Only allow these roles to access dashboard
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get selected year from filter (default to current year)
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Basic stats
// Perbaikan query untuk card statistics (sekitar baris 11-14)
$mpAktif = $conn->query("SELECT COUNT(*) FROM employees WHERE status = 'Aktif'")->fetchColumn();
$mpEndContract = $conn->query("SELECT COUNT(*) FROM end_contracts WHERE MONTH(dateOut) = MONTH(CURDATE()) AND YEAR(dateOut) = YEAR(CURDATE())")->fetchColumn();
// Perbaikan: Menggunakan date_in untuk menampilkan MP yang replacement di bulan berjalan
$mpRecruit = $conn->query("SELECT COUNT(*) FROM recruitment WHERE MONTH(date_in) = MONTH(CURDATE()) AND YEAR(date_in) = YEAR(CURDATE()) AND date_in IS NOT NULL")->fetchColumn();
$mpEdukasi = $conn->query("SELECT COUNT(*) FROM education WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();

// Perbaikan query MP Aktif by Line (sekitar baris 16-22)
$mpAktifCompAssy = $conn->prepare("SELECT line, COUNT(*) as total FROM employees WHERE status = 'Aktif' AND section = 'Comp Assy' GROUP BY line");
$mpAktifCompAssy->execute();
$mpAktifCompAssyData = $mpAktifCompAssy->fetchAll(PDO::FETCH_ASSOC);

$mpAktifCompWClutch = $conn->prepare("SELECT line, COUNT(*) as total FROM employees WHERE status = 'Aktif' AND section = 'Comp WClutch' GROUP BY line");
$mpAktifCompWClutch->execute();
$mpAktifCompWClutchData = $mpAktifCompWClutch->fetchAll(PDO::FETCH_ASSOC);

// MP End Contract by month (April to March) for selected year with gender breakdown
// Perbaikan untuk query End Contract Comp Assy dengan breakdown gender
$endContractCompAssy = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(dateOut) >= 4 THEN CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
            ELSE CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
        END as month_year,
        gender,
        COUNT(*) as total,
        MIN(CASE 
            WHEN MONTH(dateOut) >= 4 THEN YEAR(dateOut) * 100 + MONTH(dateOut)
            ELSE (YEAR(dateOut) + 1) * 100 + MONTH(dateOut)
        END) as sort_order
    FROM end_contracts 
    WHERE section = 'Comp Assy' 
    AND ((MONTH(dateOut) >= 4 AND YEAR(dateOut) = :year) 
         OR (MONTH(dateOut) <= 3 AND YEAR(dateOut) = :year_plus_1))
    GROUP BY month_year, gender
    ORDER BY sort_order
");
$endContractCompAssy->execute([
    ':year' => $selectedYear,
    ':year_plus_1' => $selectedYear + 1
]);
$endContractCompAssyData = $endContractCompAssy->fetchAll(PDO::FETCH_ASSOC);

$endContractCompWClutch = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(dateOut) >= 4 THEN CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
            ELSE CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
        END as month_year,
        gender,
        COUNT(*) as total,
        MIN(CASE 
            WHEN MONTH(dateOut) >= 4 THEN YEAR(dateOut) * 100 + MONTH(dateOut)
            ELSE (YEAR(dateOut) + 1) * 100 + MONTH(dateOut)
        END) as sort_order
    FROM end_contracts 
    WHERE section = 'Comp WClutch' 
    AND ((YEAR(dateOut) = :year AND MONTH(dateOut) >= 4) OR (YEAR(dateOut) = :year_plus_1 AND MONTH(dateOut) <= 3))
    GROUP BY month_year, gender
    ORDER BY sort_order
");
$endContractCompWClutch->execute([':year' => $selectedYear, ':year_plus_1' => $selectedYear + 1]);
$endContractCompWClutchData = $endContractCompWClutch->fetchAll(PDO::FETCH_ASSOC);

// Tambahkan parameter category filter
// Tambahkan setelah $selectedYear
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';

// Education data from education_schedule
// Fix for Comp Assy yearly education data (around line 61-69)
$educationCompAssyYearly = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp Assy' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
");

// Fix for Comp WClutch yearly education data (around line 72-80)
$educationCompWClutchYearly = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp WClutch' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
");

// Execute queries with category parameter
$params = [':year' => $selectedYear];
if ($selectedCategory) {
    $params[':category'] = $selectedCategory;
}

$educationCompAssyYearly->execute($params);
$educationCompAssyYearlyData = $educationCompAssyYearly->fetch(PDO::FETCH_ASSOC);

$educationCompWClutchYearly->execute($params);
$educationCompWClutchYearlyData = $educationCompWClutchYearly->fetch(PDO::FETCH_ASSOC);

// Education data by month - tambahkan juga filter category
$educationCompAssyMonthly = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
            ELSE MONTH(e.dateEdukasi) + 9
        END as fiscal_month,
        MONTH(e.dateEdukasi) as actual_month,
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp Assy' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
    GROUP BY MONTH(e.dateEdukasi), 
             CASE 
                 WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
                 ELSE MONTH(e.dateEdukasi) + 9
             END
    ORDER BY fiscal_month
");
$educationCompAssyMonthly->execute($params);
$educationCompAssyMonthlyData = $educationCompAssyMonthly->fetchAll(PDO::FETCH_ASSOC);

$educationCompWClutchMonthly = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
            ELSE MONTH(e.dateEdukasi) + 9
        END as fiscal_month,
        MONTH(e.dateEdukasi) as actual_month,
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp WClutch' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
    GROUP BY MONTH(e.dateEdukasi), 
             CASE 
                 WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
                 ELSE MONTH(e.dateEdukasi) + 9
             END
    ORDER BY fiscal_month
");
$educationCompWClutchMonthly->execute($params);
$educationCompWClutchMonthlyData = $educationCompWClutchMonthly->fetchAll(PDO::FETCH_ASSOC);

// Get available years for filter - update to include education dates
$yearsQuery = $conn->query("
    SELECT DISTINCT 
        CASE 
            WHEN MONTH(dateOut) >= 4 THEN YEAR(dateOut)
            ELSE YEAR(dateOut) - 1
        END as fiscal_year 
    FROM end_contracts 
    UNION 
    SELECT DISTINCT 
        CASE 
            WHEN MONTH(dateEdukasi) >= 4 THEN YEAR(dateEdukasi)
            ELSE YEAR(dateEdukasi) - 1
        END as fiscal_year 
    FROM education 
    ORDER BY fiscal_year DESC
");
$availableYears = $yearsQuery->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/sidebar.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <style>
    .card-box {
      border-radius: 10px;
      padding: 25px;
      background: white;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    .dashboard-title {
      font-size: 18px;
      color: #64748b;
    }
    .dashboard-value {
      font-size: 32px;
      font-weight: bold;
      color: #0f172a;
    }
    .chart-container {
      position: relative;
      height: 300px;
      margin: 20px 0;
    }
    .border-purple {
      border-color: #8b5cf6 !important;
    }
  </style>
</head>
<body>
<div class="sidebar">
    <div>
        <h4 class="mb-4">🛠️ MPD MS</h4>
        <div class="nav-links">
            <a href="dashboard.php" class="active">🏠 Dashboard</a>
            <a href="employees.php">📋 Database MP</a>
            <a href="end_contracts.php">📅 End Contract</a>
            <a href="replacement.php">🔁 Replacement</a>
            <a href="education.php">🎓 Edukasi</a>
            <a href="education_schedule.php">📆 Jadwal Edukasi</a>
            <a href="mapping.php">🗺️ Mapping MP</a>
            <a href="sk_comp_assy.php">📊 SK_CompAssy</a>
            <a href="sk_wclutch.php">📊 SK_WClutch</a>
            <a href="overtime.php">⏰ Overtime</a>
        </div>
    </div>
    <div>
        <hr class="bg-white">
        <a href="settings.php" class="btn btn-light w-100">⚙️ Settings</a>
        <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
    </div>
</div>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📊 Dashboard</h3>
    <!-- Filter dihapus dari sini -->
  </div>
  
  <!-- Summary Cards -->
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="card-box text-center border-start border-4 border-primary">
        <div class="dashboard-title">TOTAL MAN POWER AKTIF</div>
        <div class="dashboard-value"><?= $mpAktif ?></div>
        <div class="text-muted">Karyawan Aktif</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card-box text-center border-start border-4 border-success">
        <div class="dashboard-title">MP END CONTRACT</div>
        <div class="dashboard-value"><?= $mpEndContract ?></div>
        <div class="text-muted">Bulan Ini</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card-box text-center border-start border-4 border-warning">
        <div class="dashboard-title">MP RECRUITMENT</div>
        <div class="dashboard-value"><?= $mpRecruit ?></div>
        <div class="text-muted">Bulan Ini</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card-box text-center border-start border-4 border-purple">
        <div class="dashboard-title">MP EDUKASI</div>
        <div class="dashboard-value"><?= $mpEdukasi ?></div>
        <div class="text-muted">Bulan Ini</div>
      </div>
    </div>
  </div>

  <!-- End Contract Charts dengan Filter Tahun -->
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📅 MP End Contract Charts</h4>
        <div>
          <label for="yearFilter" class="form-label me-2">Filter Tahun:</label>
          <select id="yearFilter" class="form-select" style="width: auto; display: inline-block;" onchange="filterByYear()">
            <?php foreach ($availableYears as $year): ?>
              <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card-box">
        <h5>MP End Contract Comp Assy (Apr <?= $selectedYear ?> - Mar <?= $selectedYear + 1 ?>)</h5>
        <div class="chart-container">
          <canvas id="endContractCompAssyChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card-box">
        <h5>MP End Contract Comp WClutch (Apr <?= $selectedYear ?> - Mar <?= $selectedYear + 1 ?>)</h5>
        <div class="chart-container">
          <canvas id="endContractCompWClutchChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Education Monthly Charts dengan Filter Category -->
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>🎓 Education Charts</h4>
        <div>
          <label for="categoryFilter" class="form-label me-2">Filter Category:</label>
          <select id="categoryFilter" class="form-select" style="width: auto; display: inline-block;" onchange="filterByCategory()">
            <option value="">Semua Category</option>
            <option value="New MP">New MP</option>
            <option value="Refresh MP">Refresh MP</option>
            <option value="Skill Up MP">Skill Up MP</option>
          </select>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card-box">
        <h5>Edukasi Comp Assy <?= $selectedYear ?> - Bulanan<?= isset($selectedCategory) && $selectedCategory ? ' (' . $selectedCategory . ')' : '' ?></h5>
        <div class="chart-container">
          <canvas id="educationCompAssyMonthlyChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card-box">
        <h5>Edukasi Comp WClutch <?= $selectedYear ?> - Bulanan<?= isset($selectedCategory) && $selectedCategory ? ' (' . $selectedCategory . ')' : '' ?></h5>
        <div class="chart-container">
          <canvas id="educationCompWClutchMonthlyChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Remove MP Aktif debug logs
echo "<script>console.log('End Contract Comp Assy:', " . json_encode($endContractCompAssyData) . ");</script>";
echo "<script>console.log('End Contract Comp WClutch:', " . json_encode($endContractCompWClutchData) . ");</script>";
echo "<script>console.log('Education Comp Assy Yearly:', " . json_encode($educationCompAssyYearlyData) . ");</script>";
echo "<script>console.log('Education Comp WClutch Yearly:', " . json_encode($educationCompWClutchYearlyData) . ");</script>";
echo "<script>console.log('Education Comp Assy Monthly:', " . json_encode($educationCompAssyMonthlyData) . ");</script>";
echo "<script>console.log('Education Comp WClutch Monthly:', " . json_encode($educationCompWClutchMonthlyData) . ");</script>";
?>

<script>
// Function to filter by year
function filterByYear() {
    const yearFilter = document.getElementById('yearFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const selectedYear = yearFilter.value;
    const selectedCategory = categoryFilter ? categoryFilter.value : '';
    
    const url = new URL(window.location.href);
    url.searchParams.set('year', selectedYear);
    if (selectedCategory) {
        url.searchParams.set('category', selectedCategory);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}

// Function to filter by category
function filterByCategory() {
    const categoryFilter = document.getElementById('categoryFilter');
    const yearFilter = document.getElementById('yearFilter');
    const selectedCategory = categoryFilter.value;
    const selectedYear = yearFilter ? yearFilter.value : <?= $selectedYear ?>;
    
    const url = new URL(window.location.href);
    url.searchParams.set('year', selectedYear);
    if (selectedCategory) {
        url.searchParams.set('category', selectedCategory);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}

// Set selected values on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const selectedCategory = urlParams.get('category');
    const selectedYear = urlParams.get('year');
    
    if (selectedCategory && document.getElementById('categoryFilter')) {
        document.getElementById('categoryFilter').value = selectedCategory;
    }
    if (selectedYear && document.getElementById('yearFilter')) {
        document.getElementById('yearFilter').value = selectedYear;
    }
});

// Definisikan selectedYear dari PHP ke JavaScript
const selectedYear = <?= $selectedYear ?>;

// Definisikan context untuk End Contract charts
const endContractCompAssyCtx = document.getElementById('endContractCompAssyChart').getContext('2d');
const endContractCompWClutchCtx = document.getElementById('endContractCompWClutchChart').getContext('2d');

// Process data untuk End Contract Comp Assy
const endContractCompAssyData = <?= json_encode($endContractCompAssyData) ?>;
const fiscalMonthNames = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];

// Process data untuk fiscal year (Apr-Mar) - Comp Assy
const processedCompAssyData = {};
fiscalMonthNames.forEach(month => {
    processedCompAssyData[month] = { Pria: 0, Wanita: 0 };
});

endContractCompAssyData.forEach(item => {
    const monthIndex = parseInt(item.month_year.split('-')[1]) - 1;
    let fiscalMonthIndex;
    
    if (monthIndex >= 3) { // Apr-Dec (months 3-11 in 0-based index)
        fiscalMonthIndex = monthIndex - 3;
    } else { // Jan-Mar (months 0-2 in 0-based index)
        fiscalMonthIndex = monthIndex + 9;
    }
    
    const monthName = fiscalMonthNames[fiscalMonthIndex];
    processedCompAssyData[monthName][item.gender] = item.total;
});

const compAssyLabels = fiscalMonthNames;
const compAssyPriaData = fiscalMonthNames.map(month => processedCompAssyData[month].Pria);
const compAssyWanitaData = fiscalMonthNames.map(month => processedCompAssyData[month].Wanita);

// End Contract Comp Assy Chart - dengan breakdown gender (STACKED)
new Chart(endContractCompAssyCtx, {
    type: 'bar',
    data: {
        labels: compAssyLabels,
        datasets: [
            {
                label: 'Pria',
                data: compAssyPriaData,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Wanita',
                data: compAssyWanitaData,
                backgroundColor: 'rgba(255, 99, 132, 0.8)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Total',
                data: compAssyLabels.map((_, index) => compAssyPriaData[index] + compAssyWanitaData[index]),
                type: 'line',
                borderColor: 'rgba(255, 206, 86, 1)',
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
                borderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 8,
                fill: false,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                stacked: true,
                position: 'left'
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false
                }
            },
            x: {
                stacked: true
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    if (context.dataset.type === 'line') {
                        return value > 0 ? value : '';
                    }
                    return value > 0 ? value : '';
                }
            }
        }
    }
});

// Process data untuk End Contract Comp WClutch
const endContractCompWClutchData = <?= json_encode($endContractCompWClutchData) ?>;

// Process data untuk fiscal year (Apr-Mar) - Comp WClutch
const processedCompWClutchData = {};
fiscalMonthNames.forEach(month => {
    processedCompWClutchData[month] = { Pria: 0, Wanita: 0 };
});

endContractCompWClutchData.forEach(item => {
    const monthIndex = parseInt(item.month_year.split('-')[1]) - 1;
    let fiscalMonthIndex;
    
    if (monthIndex >= 3) { // Apr-Dec (months 3-11 in 0-based index)
        fiscalMonthIndex = monthIndex - 3;
    } else { // Jan-Mar (months 0-2 in 0-based index)
        fiscalMonthIndex = monthIndex + 9;
    }
    
    const monthName = fiscalMonthNames[fiscalMonthIndex];
    processedCompWClutchData[monthName][item.gender] = item.total;
});

const compWClutchLabels = fiscalMonthNames;
const compWClutchPriaData = fiscalMonthNames.map(month => processedCompWClutchData[month].Pria);
const compWClutchWanitaData = fiscalMonthNames.map(month => processedCompWClutchData[month].Wanita);

// End Contract Comp WClutch Chart - dengan breakdown gender (STACKED)
new Chart(endContractCompWClutchCtx, {
    type: 'bar',
    data: {
        labels: compWClutchLabels,
        datasets: [
            {
                label: 'Pria',
                data: compWClutchPriaData,
                backgroundColor: 'rgba(153, 102, 255, 0.8)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Wanita',
                data: compWClutchWanitaData,
                backgroundColor: 'rgba(255, 159, 64, 0.8)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Total',
                data: compWClutchLabels.map((_, index) => compWClutchPriaData[index] + compWClutchWanitaData[index]),
                type: 'line',
                borderColor: 'rgba(255, 206, 86, 1)',
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
                borderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 8,
                fill: false,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                stacked: true,
                position: 'left'
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false
                }
            },
            x: {
                stacked: true
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    if (context.dataset.type === 'line') {
                        return value > 0 ? value : '';
                    }
                    return value > 0 ? value : '';
                }
            }
        }
    }
});

// Education Comp Assy Monthly Chart - Planned as Line with Dots, Hadir/Tidak Hadir as Stacked
const educationCompAssyMonthlyCtx = document.getElementById('educationCompAssyMonthlyChart').getContext('2d');
const monthNames = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];
const compAssyMonthlyData = <?= json_encode($educationCompAssyMonthlyData) ?>;

// Create arrays for all 12 months (Apr-Mar)
const compAssyMonthlyLabels = monthNames;
const compAssyMonthlyPlanned = new Array(12).fill(0);
const compAssyMonthlyHadir = new Array(12).fill(0);
const compAssyMonthlyTidakHadir = new Array(12).fill(0);

// Fill data based on fiscal_month
compAssyMonthlyData.forEach(item => {
    const index = item.fiscal_month - 1; // fiscal_month is 1-12, array is 0-11
    if (index >= 0 && index < 12) {
        compAssyMonthlyPlanned[index] = item.planned;
        compAssyMonthlyHadir[index] = item.hadir;
        compAssyMonthlyTidakHadir[index] = item.tidak_hadir;
    }
});

new Chart(educationCompAssyMonthlyCtx, {
    type: 'bar',
    data: {
        labels: compAssyMonthlyLabels,
        datasets: [
            {
                label: 'Hadir',
                data: compAssyMonthlyHadir,
                backgroundColor: 'rgba(75, 192, 192, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Tidak Hadir',
                data: compAssyMonthlyTidakHadir,
                backgroundColor: 'rgba(255, 99, 132, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Planned',
                data: compAssyMonthlyPlanned,
                type: 'line',
                borderColor: 'rgba(201, 203, 207, 1)',
                backgroundColor: 'rgba(201, 203, 207, 0.2)',
                borderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    return value > 0 ? value : '';
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: true
            },
            x: {
                stacked: true
            }
        }
    }
});

// Education Comp WClutch Monthly Chart - Planned as Line with Dots, Hadir/Tidak Hadir as Stacked
const educationCompWClutchMonthlyCtx = document.getElementById('educationCompWClutchMonthlyChart').getContext('2d');
const compWClutchMonthlyData = <?= json_encode($educationCompWClutchMonthlyData) ?>;

// Create arrays for all 12 months (Apr-Mar)
const compWClutchMonthlyLabels = monthNames;
const compWClutchMonthlyPlanned = new Array(12).fill(0);
const compWClutchMonthlyHadir = new Array(12).fill(0);
const compWClutchMonthlyTidakHadir = new Array(12).fill(0);

// Fill data based on fiscal_month
compWClutchMonthlyData.forEach(item => {
    const index = item.fiscal_month - 1; // fiscal_month is 1-12, array is 0-11
    if (index >= 0 && index < 12) {
        compWClutchMonthlyPlanned[index] = item.planned;
        compWClutchMonthlyHadir[index] = item.hadir;
        compWClutchMonthlyTidakHadir[index] = item.tidak_hadir;
    }
});

new Chart(educationCompWClutchMonthlyCtx, {
    type: 'bar',
    data: {
        labels: compWClutchMonthlyLabels,
        datasets: [
            {
                label: 'Hadir',
                data: compWClutchMonthlyHadir,
                backgroundColor: 'rgba(75, 192, 192, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Tidak Hadir',
                data: compWClutchMonthlyTidakHadir,
                backgroundColor: 'rgba(255, 99, 132, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Planned',
                data: compWClutchMonthlyPlanned,
                type: 'line',
                borderColor: 'rgba(201, 203, 207, 1)',
                backgroundColor: 'rgba(201, 203, 207, 0.2)',
                borderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    return value > 0 ? value : '';
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: true
            },
            x: {
                stacked: true
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
