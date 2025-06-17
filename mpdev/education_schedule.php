<?php
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_date']) && !empty($_GET['filter_date'])) {
    $whereClause .= " AND DATE(e.dateEdukasi) = :filter_date";
    $params[':filter_date'] = $_GET['filter_date'];
}

if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
    $whereClause .= " AND es.status = :filter_status";
    $params[':filter_status'] = $_GET['filter_status'];
}

// Handle POST request for updating status only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        // Check if schedule already exists
        $checkStmt = $conn->prepare("SELECT id FROM education_schedule WHERE education_id = ?");
        $checkStmt->execute([$_POST['education_id']]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing schedule
            $sql = "UPDATE education_schedule SET time = :time, status = :status, note = :note WHERE education_id = :education_id";
        } else {
            // Insert new schedule
            $sql = "INSERT INTO education_schedule (education_id, npk, name, section, line, leader, category, namaPos, date, time, status, note) 
                    SELECT id, npk, name, section, line, leader, category, namaPos, dateEdukasi, :time, :status, :note 
                    FROM education WHERE id = :education_id";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':education_id' => $_POST['education_id'],
            ':time' => $_POST['time'],
            ':status' => $_POST['status'],
            ':note' => $_POST['note']
        ]);
        
        header("Location: education_schedule.php?success=1");
        exit();
    }
}

// Get filtered education data with schedule info
$sql = "SELECT e.*, 
               es.time, es.status, es.note, es.id as schedule_id
        FROM education e 
        LEFT JOIN education_schedule es ON e.id = es.education_id 
        WHERE 1=1" . $whereClause . "
        ORDER BY e.dateEdukasi ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$educationData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Jadwal Edukasi - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="css/sidebar.css" rel="stylesheet">
</head>
<body>
<div class="sidebar">
  <div>
    <h4 class="mb-4">🛠️ MPD MS</h4>
    <div class="nav-links">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="employees.php">📋 Database MP</a>
      <a href="end_contracts.php">📅 End Contract</a>
      <a href="replacement.php">🔁 Replacement</a>
      <a href="education.php">🎓 Edukasi</a>
      <a href="education_schedule.php" class="active">📆 Jadwal Edukasi</a>
      <a href="mapping.php">🗺️ Mapping MP</a>
      <a href="sk_comp_assy.php">📊 SK_CompAssy</a>
      <a href="sk_wclutch.php">📊 SK_WClutch</a>
      <a href="overtime.php">⏰ Overtime</a>
    </div>
  </div>
  <div>
    <hr class="bg-white">
    <a href="#" class="btn btn-light w-100">⚙️ Settings</a>
    <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
  </div>
</div>

<div class="main-content">
  <h3 class="mb-4">📆 Jadwal Edukasi</h3>
  
  <?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Jadwal edukasi berhasil diupdate!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  
  <!-- Filter Section -->
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">🔍 Filter Jadwal</h5>
      <form method="GET" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Tanggal</label>
          <input type="date" name="filter_date" class="form-control" 
                 value="<?= isset($_GET['filter_date']) ? $_GET['filter_date'] : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="filter_status" class="form-select">
            <option value="">Semua Status</option>
            <option value="Scheduled" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
            <option value="Completed" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">Filter</button>
          <a href="education_schedule.php" class="btn btn-secondary">Reset</a>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="button" class="btn btn-info" onclick="setToday()">Hari Ini</button>
        </div>
      </form>
    </div>
  </div>
  
  <div class="alert alert-info">
    <strong>Info:</strong> Semua data edukasi otomatis ditampilkan sebagai jadwal. Klik "Set Jadwal" untuk mengatur waktu dan status.
  </div>
  
  <table id="scheduleTable" class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>No</th>
        <th>NPK</th>
        <th>Name</th>
        <th>Section</th>
        <th>Line</th>
        <th>Leader</th>
        <th>Category</th>
        <th>Nama Pos</th>
        <th>Tanggal Edukasi</th>
        <th>Time</th>
        <th>Status</th>
        <th>Note</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($educationData as $index => $row): ?>
      <tr>
        <td><?= $index + 1 ?></td>
        <td><?= htmlspecialchars($row['npk']) ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['section']) ?></td>
        <td><?= htmlspecialchars($row['line']) ?></td>
        <td><?= htmlspecialchars($row['leader']) ?></td>
        <td><?= htmlspecialchars($row['category']) ?></td>
        <td><?= htmlspecialchars($row['namaPos']) ?></td>
        <td><?= date('d/m/Y', strtotime($row['dateEdukasi'])) ?></td>
        <td>
          <?php if ($row['time']): ?>
            <span class="badge bg-primary"><?= htmlspecialchars($row['time']) ?></span>
          <?php else: ?>
            <span class="badge bg-secondary">Belum Dijadwalkan</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($row['status']): ?>
            <span class="badge bg-<?= $row['status'] === 'Hadir' ? 'success' : 'danger' ?>">
              <?= htmlspecialchars($row['status']) ?>
            </span>
          <?php else: ?>
            <span class="badge bg-secondary">Belum Absen</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($row['note'] ?: '-') ?></td>
        <td>
          <button class="btn btn-sm btn-primary" onclick="setSchedule(<?= $row['id'] ?>, '<?= htmlspecialchars($row['npk']) ?>', '<?= htmlspecialchars($row['name']) ?>', '<?= htmlspecialchars($row['time'] ?: '') ?>', '<?= htmlspecialchars($row['status'] ?: '') ?>', '<?= htmlspecialchars($row['note'] ?: '') ?>')">
            Set Jadwal
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Set Jadwal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Set Jadwal & Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="education_id" id="education_id">
          <div class="mb-3">
            <label class="form-label">NPK - Nama</label>
            <input type="text" id="employee_info" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Waktu Jadwal</label>
            <select name="time" id="schedule_time" class="form-select" required>
              <option value="">Pilih Waktu</option>
              <option value="08:00-11:45">08:00-11:45</option>
              <option value="13:00-16:30">13:00-16:30</option>
              <option value="16:40-20:00">16:40-20:00</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Status Kehadiran</label>
            <select name="status" id="attendance_status" class="form-select">
              <option value="Belum ditentukan">Belum Ditentukan</option>
              <option value="Hadir">Hadir</option>
              <option value="Tidak Hadir">Tidak Hadir</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Note</label>
            <textarea name="note" id="schedule_note" class="form-control" rows="3" placeholder="Catatan jadwal..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="update_status" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function() {
    $('#scheduleTable').DataTable({
      responsive: true,
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
      },
      order: [[8, 'asc']] // Sort by education date
    });
  });
  
  function setSchedule(educationId, npk, name, time, status, note) {
    document.getElementById('education_id').value = educationId;
    document.getElementById('employee_info').value = npk + ' - ' + name;
    document.getElementById('schedule_time').value = time;
    document.getElementById('attendance_status').value = status;
    document.getElementById('schedule_note').value = note;
    
    const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    scheduleModal.show();
  }
  
  function setToday() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="filter_date"]').value = today;
    document.querySelector('form').submit();
  }
</script>

  <!-- Chart Section -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5>📊 Status Kehadiran</h5>
        </div>
        <div class="card-body">
          <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5>📊 Edukasi by Category</h5>
        </div>
        <div class="card-body">
          <canvas id="categoryChart"></canvas>
        </div>
      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php
$statusData = $conn->query("SELECT COALESCE(status, 'Belum ditentukan') as status, COUNT(*) as count FROM education_schedule GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$categoryData = $conn->query("SELECT category, COUNT(*) as count FROM education GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
?>

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($statusData, 'status')) ?>,
        datasets: [{
            label: 'Jumlah',
            data: <?= json_encode(array_column($statusData, 'count')) ?>,
            backgroundColor: ['rgba(255, 206, 86, 0.8)', 'rgba(75, 192, 192, 0.8)', 'rgba(255, 99, 132, 0.8)'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($categoryData, 'category')) ?>,
        datasets: [{
            label: 'Jumlah Edukasi',
            data: <?= json_encode(array_column($categoryData, 'count')) ?>,
            backgroundColor: 'rgba(153, 102, 255, 0.8)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
</body>
</html>