<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['first_name'], $_SESSION['last_name'], $_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$upcoming = [];

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['first_name'] . " " . $_SESSION['last_name'];

$stmt = $conn->prepare("SELECT profile_image, id_number, year_level, section FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profile_image = $user['profile_image'] ?? 'default.png';
$id_number = $user['id_number'] ?? '';
$student_year = $user['year_level'] ?? '';
$student_section = $user['section'] ?? '';
$_SESSION['profile_image'] = $profile_image;
$stmt->close();

// Normalize profile image path for display
$profileSrc = (strpos($profile_image, '/') !== false)
    ? "../" . ltrim($profile_image, '/')
    : "../uploads/profiles/" . $profile_image;

// Auto-mark overdue '-' statuses as Absent for this student
$autoS = $conn->prepare("UPDATE attendance_details ad
    JOIN attendance a ON a.id = ad.attendance_id
    SET ad.status = 'Absent'
    WHERE ad.status = '-' AND ad.student_id = ? AND (
      a.attendance_date < CURDATE() OR
      (a.attendance_date = CURDATE() AND a.end_time IS NOT NULL AND a.end_time <> '' AND a.end_time <= CURTIME())
    )");
if ($autoS) { $autoS->bind_param("s", $id_number); $autoS->execute(); $autoS->close(); }

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['profile_image'])) {
    if ($_FILES['profile_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_name = $_FILES['profile_image']['name'];
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed)) {
            $new_name = "student_" . $user_id . "_" . time() . "." . $file_ext;
            $upload_dir = "../uploads/profiles/";

            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $destination = $upload_dir . $new_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $stmt = $conn->prepare("UPDATE users SET profile_image=? WHERE id=?");
                $stmt->bind_param("si", $new_name, $user_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['profile_image'] = $new_name;
                header("Location: student.php?success=1");
                exit;
            }
        }
    }
}

$attendanceQuery = $conn->prepare("
    SELECT 
      a.id AS attendance_id,
      ad.id AS detail_id,
      a.course_code,
      a.year_level,
      a.section,
      a.attendance_date,
      ad.status,
      a.created_by,
      c.course_description
    FROM attendance_details ad
    JOIN attendance a ON a.id = ad.attendance_id
    LEFT JOIN courses c ON c.course_code = a.course_code AND c.teacher_id = a.created_by
    WHERE ad.student_id = ?
    ORDER BY a.attendance_date DESC
");
$attendanceQuery->bind_param("s", $id_number);
$attendanceQuery->execute();
$attendanceResult = $attendanceQuery->get_result();
$attendanceRows = [];
while ($r = $attendanceResult->fetch_assoc()) { $attendanceRows[] = $r; }

$courseGroups = [];
foreach ($attendanceRows as $r) {
    $code = $r['course_code'];
    if (!isset($courseGroups[$code])) {
        $courseGroups[$code] = [
            'year_level' => $r['year_level'],
            'section' => $r['section'],
            'records' => [],
            'present' => 0,
            'total' => 0,
            'description' => $r['course_description'] ?? ''
        ];
    }
    $courseGroups[$code]['records'][] = $r;
    $courseGroups[$code]['total']++;
    if ($r['status'] === 'Present') { $courseGroups[$code]['present']++; }
}

// Selected course from query param (kept raw for accurate matching; all DB use prepared statements, and output is escaped)
$selectedCourse = null;
if (isset($_GET['course'])) {
    $selectedCourse = substr((string)$_GET['course'], 0, 100);
}

$courseHistory = [];
if ($selectedCourse) {
    foreach ($attendanceRows as $r) {
        if ($r['course_code'] === $selectedCourse) {
            $courseHistory[] = $r;
        }
    }
}

// Upcoming schedules (after we know $id_number)
// Try with start_time/end_time; if schema doesn't have those columns, fall back without them
$upcoming = [];
$qWithTime = "SELECT a.course_code, a.year_level, a.section, a.attendance_date, a.start_time, a.end_time, ad.status\n  FROM attendance a\n  JOIN attendance_details ad ON ad.attendance_id = a.id\n  WHERE ad.student_id = ? AND a.attendance_date >= CURDATE()\n  ORDER BY a.attendance_date ASC, a.start_time ASC\n  LIMIT 5";
$notifStmt = $conn->prepare($qWithTime);
if ($notifStmt) {
  $notifStmt->bind_param("s", $id_number);
  $notifStmt->execute();
  $notifRes = $notifStmt->get_result();
  while ($n = $notifRes->fetch_assoc()) { $upcoming[] = $n; }
  $notifStmt->close();
} else {
  $qNoTime = "SELECT a.course_code, a.year_level, a.section, a.attendance_date, ad.status\n    FROM attendance a\n    JOIN attendance_details ad ON ad.attendance_id = a.id\n    WHERE ad.student_id = ? AND a.attendance_date >= CURDATE()\n    ORDER BY a.attendance_date ASC\n    LIMIT 5";
  $notifStmt2 = $conn->prepare($qNoTime);
  if ($notifStmt2) {
    $notifStmt2->bind_param("s", $id_number);
    $notifStmt2->execute();
    $notifRes2 = $notifStmt2->get_result();
    while ($n = $notifRes2->fetch_assoc()) {
      $n['start_time'] = $n['start_time'] ?? '';
      $n['end_time'] = $n['end_time'] ?? '';
      $upcoming[] = $n;
    }
    $notifStmt2->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8fafc; }
.navbar { background: linear-gradient(45deg, #3b82f6, #06b6d4); z-index: 1050; }
.profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; }
.profile-preview { width: 90px; height: 90px; object-fit: cover; border: 3px solid #0d6efd; }
.table-container { background: #fff; border-radius: 15px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

/* Course chips */
.course-chip { border:none; color:#fff; padding:8px 14px; border-radius:25px; font-weight:600; cursor:pointer; transition:.2s; box-shadow:0 6px 14px rgba(0,0,0,.12); }
.course-chip:hover { transform:translateY(-2px); opacity:.95; }
.chip-1{ background:linear-gradient(135deg,#8b5cf6,#6366f1);} /* purple */
.chip-2{ background:linear-gradient(135deg,#f59e0b,#ef4444);} /* orange-red */
.chip-3{ background:linear-gradient(135deg,#22c55e,#14b8a6);} /* green-teal */
.chip-4{ background:linear-gradient(135deg,#06b6d4,#3b82f6);} /* cyan-blue */

/* Course cards */
.course-card { border:none; border-radius:18px; overflow:hidden; box-shadow:0 14px 28px rgba(0,0,0,.18); }
.course-card .card-header { color:#fff; font-weight:700; border:none; }
.grad-1{ background:linear-gradient(135deg,#3b82f6 0%,#06b6d4 100%);} 
.grad-2{ background:linear-gradient(135deg,#ef4444 0%,#f59e0b 100%);} 
.grad-3{ background:linear-gradient(135deg,#10b981 0%,#22c55e 100%);} 
.grad-4{ background:linear-gradient(135deg,#8b5cf6 0%,#6366f1 100%);} 
.course-card .progress { height:18px; border-radius:18px; background:#eef2ff; }
.course-card .progress-bar { background: linear-gradient(90deg,#22c55e,#06b6d4); }
.kpi { font-size:12px; }

/* Left sidebar for enrolled courses */
.sidebar-courses { position:fixed; top:0; left:0; width:260px; height:100vh; background:#0f172a; color:#fff; overflow-y:auto; box-shadow: 2px 0 12px rgba(0,0,0,.15); transform: translateX(-100%); transition: transform .25s ease; z-index: 1040; }
.sidebar-courses .title { padding:14px 16px; font-weight:700; font-size:14px; letter-spacing:.5px; color:#93c5fd; border-bottom:1px solid rgba(255,255,255,.08); }
.sidebar-courses .course-item { display:block; padding:12px 16px; color:#e5e7eb; text-decoration:none; border-bottom:1px solid rgba(255,255,255,.06); transition:.15s; }
.sidebar-courses .course-item small { color:#9ca3af; display:block; }
.sidebar-courses .course-item:hover { background:rgba(255,255,255,.06); color:#fff; }
.sidebar-courses .profile { padding:16px; text-align:center; border-bottom:1px solid rgba(255,255,255,.08); }
.sidebar-courses.open { transform: translateX(0); }
.sidebar-avatar { width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #93c5fd; }
.content-shift { margin-left: 0 !important; transition: margin-left .2s; }
@media (min-width: 992px) { 
  body:not(.sidebar-collapsed) .content-shift { margin-left: 0 !important; }
  body.sidebar-collapsed .content-shift { margin-left: 0 !important; }
}
.sidebar-courses { transition: width .2s; }
.sidebar-courses.collapsed { width:68px; }
.sidebar-courses.collapsed:hover { width:260px; }
.sidebar-courses.collapsed .profile .name, 
.sidebar-courses.collapsed .profile .student-id, 
.sidebar-courses.collapsed .title, 
.sidebar-courses.collapsed .course-item small { display:none; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm px-3 sticky-top">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-light d-lg-inline d-inline" id="hamburgerBtn" aria-label="Toggle sidebar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <a class="navbar-brand fw-bold" href="#">Attendance Management System</a>
    </div>
    <div class="ms-auto d-flex align-items-center text-white">
      <div class="dropdown me-3">
        <a class="text-white position-relative d-flex align-items-center text-decoration-none" href="#" id="dropdownNotif" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="me-1">🔔</span>
          <?php if (!empty($upcoming)): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;">
              <?= count($upcoming) > 9 ? '9+' : count($upcoming) ?>
              <span class="visually-hidden">unread notifications</span>
            </span>
          <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="dropdownNotif" style="min-width:320px;">
          <li class="px-3 py-2 fw-semibold border-bottom">Upcoming schedules</li>
          <?php if (!empty($upcoming)): ?>
            <?php foreach ($upcoming as $u): ?>
              <li>
                <a class="dropdown-item d-flex justify-content-between align-items-start" href="student.php?course=<?= urlencode($u['course_code']) ?>">
                  <div class="me-2">
                    <div class="fw-semibold mb-1"><?= htmlspecialchars($u['course_code']) ?></div>
                    <div class="small text-muted">
                      <?= htmlspecialchars(date('D, M j, Y', strtotime($u['attendance_date']))) ?>
                      <?php if (!empty($u['start_time']) && !empty($u['end_time'])): ?>
                        • <?= htmlspecialchars(date('g:i A', strtotime($u['start_time']))) ?> - <?= htmlspecialchars(date('g:i A', strtotime($u['end_time']))) ?>
                      <?php endif; ?>
                    </div>
                    <div class="small text-muted">Year <?= htmlspecialchars($u['year_level']) ?> • Section <?= htmlspecialchars($u['section']) ?></div>
                  </div>
                  <div>
                    <?php if ($u['status'] === '-'): ?>
                      <span class="badge bg-warning text-dark">Pending</span>
                    <?php elseif ($u['status'] === 'Present'): ?>
                      <span class="badge bg-success">Present</span>
                    <?php elseif ($u['status'] === 'Late'): ?>
                      <span class="badge bg-warning text-dark">Late</span>
                    <?php elseif ($u['status'] === 'Absent'): ?>
                      <span class="badge bg-danger">Absent</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">-</span>
                    <?php endif; ?>
                  </div>
                </a>
              </li>
            <?php endforeach; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="student.php">View all</a></li>
          <?php else: ?>
            <li><div class="px-3 py-3 text-muted small">No upcoming schedules.</div></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="dropdown me-3">
        <a class="dropdown-toggle text-white d-flex align-items-center text-decoration-none" href="#" id="dropdownProfile" data-bs-toggle="dropdown">
          <img src="<?= htmlspecialchars($profileSrc) ?>" class="profile-img me-2" alt="Profile">
          <span><?= htmlspecialchars($fullname) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end p-3 text-center" style="width:250px;">
          <img src="<?= htmlspecialchars($profileSrc) ?>" class="rounded-circle mb-2 profile-preview" alt="Profile">
          <h6 class="fw-semibold mb-3"><?= htmlspecialchars($fullname) ?></h6>
          <hr class="my-2">
          <form method="POST" enctype="multipart/form-data" id="profileForm">
            <input type="file" name="profile_image" id="profileInput" accept="image/*" style="display:none" required>
            <button type="button" class="btn btn-primary btn-sm w-100" onclick="document.getElementById('profileInput').click();">Change Picture</button>
          </form>
          <form action="../controllers/logout.php" method="POST" class="mt-2">
            <button type="submit" class="btn btn-warning btn-sm w-100">Logout</button>
          </form>
        </ul>
      </div>
    </div>
  </div>
</nav>

<?php if (!empty($courseGroups)): ?>
<div class="sidebar-courses" id="sidebarCourses">
  <div class="profile">
    <img src="<?= htmlspecialchars($profileSrc) ?>" class="sidebar-avatar mb-2" alt="Profile">
    <div class="name fw-semibold"><?= htmlspecialchars($fullname) ?></div>
    <div class="student-id small">ID: <?= htmlspecialchars($id_number) ?></div>
  </div>
  <div class="title">ENROLLED COURSES</div>
  <?php foreach ($courseGroups as $code => $meta): ?>
    <?php $safeId = preg_replace('/[^a-zA-Z0-9_-]/','',$code); ?>
    <a href="javascript:void(0)" class="course-item" onclick="scrollToCourse('card-<?= htmlspecialchars($safeId) ?>')">
      <div class="fw-semibold mb-1"><?= htmlspecialchars($code) ?></div>
      <small>Year <?= htmlspecialchars($meta['year_level']) ?> • Section <?= htmlspecialchars($meta['section']) ?></small>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($selectedCourse): ?>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= htmlspecialchars($selectedCourse) ?> — Enrolled course</h4>
    <a href="student.php" class="btn btn-outline-secondary btn-sm">Back to courses</a>
  </div>
  <?php 
    $desc = '';
    if (isset($courseGroups[$selectedCourse])) { $desc = $courseGroups[$selectedCourse]['description'] ?? ''; }
  ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="mb-1 text-muted">Course description</div>
      <div><?= $desc ? htmlspecialchars($desc) : '<span class="text-muted">No description provided by the teacher.</span>' ?></div>
    </div>
  </div>
  <?php
    // Fetch detailed sessions for this course for the logged-in student
    $courseSessions = [];
    $range = $_GET['range'] ?? 'all'; // 'all' | 'past' | 'month'
    $monthParam = $_GET['month'] ?? date('Y-m'); // YYYY-MM
    $baseSql = "SELECT a.id, a.attendance_date, a.start_time, a.end_time, a.year_level, a.section, ad.status
                FROM attendance a
                JOIN attendance_details ad ON ad.attendance_id = a.id
                WHERE ad.student_id = ? AND a.course_code = ?";
    $order = " ORDER BY a.attendance_date DESC, a.start_time DESC";
    $params = [$id_number, $selectedCourse];
    $types = 'ss';
    if ($range === 'past') {
      $baseSql .= " AND a.attendance_date < CURDATE()";
    } elseif ($range === 'month' && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
      $baseSql .= " AND DATE_FORMAT(a.attendance_date,'%Y-%m') = ?";
      $params[] = $monthParam;
      $types .= 's';
    }
    $stmtS = $conn->prepare($baseSql . $order);
    if ($stmtS) {
      $stmtS->bind_param($types, ...$params);
      $stmtS->execute();
      $resS = $stmtS->get_result();
      while ($row = $resS->fetch_assoc()) { $courseSessions[] = $row; }
      $stmtS->close();
    } else {
      // Fallback: if DB lacks start_time/end_time or prepare failed, fetch minimal fields
      $baseSql2 = "SELECT a.id, a.attendance_date, a.year_level, a.section, ad.status
                   FROM attendance a
                   JOIN attendance_details ad ON ad.attendance_id = a.id
                   WHERE ad.student_id = ? AND a.course_code = ?";
      if ($range === 'past') {
        $baseSql2 .= " AND a.attendance_date < CURDATE()";
      } elseif ($range === 'month' && preg_match('/^\\d{4}-\\d{2}$/', $monthParam)) {
        $baseSql2 .= " AND DATE_FORMAT(a.attendance_date,'%Y-%m') = ?";
      }
      $order2 = " ORDER BY a.attendance_date DESC";
      $stmtF = $conn->prepare($baseSql2 . $order2);
      if ($stmtF) {
        $stmtF->bind_param($types, ...$params);
        $stmtF->execute();
        $resF = $stmtF->get_result();
        while ($row = $resF->fetch_assoc()) {
          // Normalize to include start_time/end_time keys for renderer
          $row['start_time'] = $row['start_time'] ?? null;
          $row['end_time'] = $row['end_time'] ?? null;
          $courseSessions[] = $row;
        }
        $stmtF->close();
      }
    }
    $currentMonth = date('Y-m');
  ?>
  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h6 class="mb-2 mb-sm-0">Attendance sessions</h6>
        <div class="d-flex gap-2">
          <a href="student.php?course=<?= urlencode($selectedCourse) ?>&range=all" class="btn btn-sm btn-outline-secondary<?= ($range==='all'?' active':'') ?>">All</a>
          <a href="student.php?course=<?= urlencode($selectedCourse) ?>&range=past" class="btn btn-sm btn-outline-secondary<?= ($range==='past'?' active':'') ?>">All past</a>
          <a href="student.php?course=<?= urlencode($selectedCourse) ?>&range=month&month=<?= htmlspecialchars($currentMonth) ?>" class="btn btn-sm btn-outline-secondary<?= ($range==='month'?' active':'') ?>">Month</a>
        </div>
      </div>
      <?php if ($range==='month'): ?>
        <form class="row g-2 mb-3" method="GET">
          <input type="hidden" name="course" value="<?= htmlspecialchars($selectedCourse) ?>">
          <input type="hidden" name="range" value="month">
          <div class="col-auto">
            <input type="month" class="form-control form-control-sm" name="month" value="<?= htmlspecialchars($monthParam) ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Go</button>
          </div>
        </form>
      <?php endif; ?>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:30%">Date</th>
              <th style="width:30%">Description</th>
              <th style="width:15%" class="text-center">Status</th>
              <th style="width:12%" class="text-center">Points</th>
              <th style="width:13%">Remarks</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($courseSessions)): ?>
            <?php foreach ($courseSessions as $sess): ?>
              <?php 
                $dstr = date('D d M Y', strtotime($sess['attendance_date']));
                $t1 = $sess['start_time'] ? date('g:i A', strtotime($sess['start_time'])) : '';
                $t2 = $sess['end_time'] ? date('g:i A', strtotime($sess['end_time'])) : '';
              ?>
              <tr>
                <td>
                  <div><?= htmlspecialchars($dstr) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars(trim($t1.' - '.$t2)) ?></div>
                </td>
                <td>Regular class session</td>
                <td class="text-center">
                  <?php if ($sess['status'] === 'Present'): ?>
                    <span class="badge bg-success">Present</span>
                  <?php elseif ($sess['status'] === 'Late'): ?>
                    <span class="badge bg-warning text-dark">Late</span>
                  <?php elseif ($sess['status'] === 'Absent'): ?>
                    <span class="badge bg-danger">Absent</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">?</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">? / 2</td>
                <td class="text-muted">?</td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-muted text-center">No sessions found for this selection.</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>


<?php if ( ( !$selectedCourse || empty($courseHistory) ) && !empty($courseGroups) ): ?>
<div class="container my-4">
  <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success text-center py-2 mb-3"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger text-center py-2 mb-3"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
  <?php endif; ?>
  <h4 class="mb-3">Enrolled courses</h4>
  <div class="row g-4 justify-content-start">
    <?php $idx=1; foreach ($courseGroups as $code => $meta): ?>
      <?php $grad = 'grad-'.(($idx-1)%4+1); $idx++; $safeId = preg_replace('/[^a-zA-Z0-9_-]/','',$code); ?>
      <?php $pct = $meta['total'] ? round(($meta['present']/$meta['total'])*100) : 0; ?>
      <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="card course-card h-100" id="card-<?= htmlspecialchars($safeId) ?>">
          <div class="card-header <?= $grad ?>">
            <div class="d-flex justify-content-between align-items-center">
              <div class="fs-5"><?= htmlspecialchars($code) ?></div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark">
                  Year <?= htmlspecialchars($meta['year_level']) ?> • Section <?= htmlspecialchars($meta['section']) ?>
                </span>
                <a href="student.php?course=<?= urlencode($code) ?>" class="btn btn-light btn-sm">View</a>
              </div>
            </div>
          </div>
          <div class="card-body">
            <p class="text-muted mb-2"><?= htmlspecialchars($meta['description'] ?: 'No description provided') ?></p>
            <div class="mb-2 kpi">Attendance: <b><?= $pct ?>%</b> (<?= (int)$meta['present'] ?>/<?= (int)$meta['total'] ?>)</div>
            <div class="progress mb-3" role="progressbar" aria-label="attendance" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar" style="width: <?= $pct ?>%"></div>
            </div>
            <?php 
              $today = date('Y-m-d');
              $todayPendings = []; $todayFinalStatus = null;
              foreach ($meta['records'] as $rec) {
                if ($rec['attendance_date'] === $today) {
                  if ($rec['status'] === '-') { $todayPendings[] = $rec; }
                  else { $todayFinalStatus = $rec['status']; }
                }
              }
            ?>
            <?php if (!empty($todayPendings)): ?>
              <?php foreach ($todayPendings as $p): ?>
                <form action="../controllers/submit_status.php" method="POST" class="d-flex gap-2 align-items-center justify-content-start mb-2">
                  <input type="hidden" name="attendance_id" value="<?= (int)$p['attendance_id'] ?>">
                  <input type="hidden" name="detail_id" value="<?= (int)$p['detail_id'] ?>">
                  <select name="status" class="form-select form-select-sm w-auto" required>
                    <option value="" disabled selected>Submit status</option>
                    <option value="Present">Present</option>
                    <option value="Late">Late</option>
                    <option value="Absent">Absent</option>
                  </select>
                  <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                </form>
              <?php endforeach; ?>
            <?php elseif ($todayFinalStatus): ?>
              <?php if ($todayFinalStatus == 'Present'): ?>
                <span class="badge bg-success">Today: Present</span>
              <?php elseif ($todayFinalStatus == 'Late'): ?>
                <span class="badge bg-warning text-dark">Today: Late</span>
              <?php elseif ($todayFinalStatus == 'Absent'): ?>
                <span class="badge bg-danger">Today: Absent</span>
              <?php else: ?>
                <span class="badge bg-secondary">Today: -</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle
const bodyEl = document.body;
const sidebar = document.getElementById('sidebarCourses');
const hamburger = document.getElementById('hamburgerBtn');
if(hamburger && sidebar){
  hamburger.addEventListener('click', function(){
    sidebar.classList.toggle('open');
  });
}
function scrollToCourse(id){
  const el = document.getElementById(id);
  if(el){ el.scrollIntoView({behavior:'smooth', block:'center'}); }
}
document.getElementById('profileInput').addEventListener('change', function(){
  if(this.files.length > 0){
    document.getElementById('profileForm').submit();
  }
});
</script>
</body>
</html>
