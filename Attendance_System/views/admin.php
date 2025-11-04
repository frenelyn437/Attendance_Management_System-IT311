<?php
session_start();
include "../config/db.php";

if (empty($_SESSION['first_name']) || empty($_SESSION['last_name'])) {
    header("Location: ../index.php");
    exit;
}

// Get counts
$students = $conn->query("SELECT id,id_number,first_name,last_name,year_level,section,status FROM users WHERE role='0'");
$teachers = $conn->query("SELECT id,id_number,first_name,last_name,status FROM users WHERE role='1'");

$student_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='0'")->fetch_assoc()['count'];
$teacher_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='1'")->fetch_assoc()['count'];
$course_count = $conn->query("SELECT COUNT(DISTINCT CONCAT(course_code,'|',year_level,'|',section)) as count FROM attendance")->fetch_assoc()['count'] ?? 0;

// Get courses added by teachers from attendance records, including description
$courses = $conn->query("
    SELECT DISTINCT 
           a.course_code, a.year_level, a.section, a.created_by,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           c.course_description
    FROM attendance a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN courses c ON c.course_code = a.course_code AND c.teacher_id = a.created_by
    ORDER BY a.course_code, a.year_level, a.section
");

// Get all users for manage users section
$all_users = $conn->query("
    SELECT id, id_number, first_name, last_name, role, status 
    FROM users 
    ORDER BY role, last_name, first_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard</title>
<style>
*{margin:0; padding:0; box-sizing:border-box; font-family:"Inter","Segoe UI",Tahoma,Geneva,Verdana,sans-serif;}
body{min-height:100vh; display:flex; background:#f5f7fa;}

/* Sidebar Styles */
.sidebar{
  width:240px;
  background:#1e293b;
  color:#fff;
  display:flex;
  flex-direction:column;
  padding:1.5rem 0;
  box-shadow:2px 0 20px rgba(0,0,0,0.15);
  position:fixed;
  top:0;
  left:0;
  height:100%;
  transform:translateX(-100%);
  transition:transform .3s ease;
  z-index:10;
}
.sidebar.active{transform:translateX(0);}
.sidebar .logo{font-size:1.3rem;font-weight:700;padding:0 1.5rem;margin-bottom:2rem;text-align:center;color:#fff;}
.sidebar a, .sidebar .submenu-toggle{
  color:#94a3b8;
  text-decoration:none;
  padding:.85rem 1.5rem;
  margin-bottom:0.3rem;
  display:flex;
  align-items:center;
  gap:12px;
  cursor:pointer;
  transition:.3s;
  font-size:0.95rem;
  border-left:3px solid transparent;
}
.sidebar a:hover, .sidebar .submenu-toggle:hover, .sidebar a.active{
  background:rgba(255,255,255,0.1);
  color:#fff;
  border-left-color:#3b82f6;
}
.sidebar-icon{font-size:1.2rem;}
.submenu{display:none;flex-direction:column;background:rgba(0,0,0,0.2);}
.submenu a{padding:.7rem 1.5rem .7rem 3rem;font-size:0.9rem;}

/* Hamburger Icon */
.hamburger{
  position:fixed;
  top:15px;
  left:15px;
  font-size:26px;
  background:#1e293b;
  color:#fff;
  width:45px;
  height:45px;
  border:none;
  border-radius:10px;
  display:flex;
  justify-content:center;
  align-items:center;
  cursor:pointer;
  box-shadow:0 4px 12px rgba(0,0,0,0.2);
  z-index:20;
  transition:background .3s;
}
.hamburger:hover{background:#334155;}

/* Main Content */
.main-content{flex:1;padding:2rem;width:100%;margin-left:0;transition:margin-left .3s;}
.sidebar.active ~ .main-content{margin-left:240px;}

.top-header{
  background:#1e293b;
  color:#fff;
  padding:1.5rem 2rem;
  border-radius:12px;
  margin-bottom:2rem;
  box-shadow:0 4px 12px rgba(0,0,0,0.1);
}
.top-header h1{font-size:1.8rem;font-weight:700;}

.container{display:flex;flex-wrap:wrap;gap:1.5rem;justify-content:flex-start;}
.dashboard-card{
  background:#fff;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
  width:calc(33.333% - 1rem);
  min-width:280px;
  overflow:hidden;
  transition:transform .2s,box-shadow .2s;
  cursor:pointer;
}
.dashboard-card:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.card-top{height:120px;width:100%;position:relative;overflow:hidden;}
.card-top.students{background:linear-gradient(135deg,#84cc16,#22c55e);}
.card-top.teachers{background:linear-gradient(135deg,#06b6d4,#3b82f6);}
.card-top.courses{background:linear-gradient(135deg,#10b981,#14b8a6);}
.card-top.users{background:linear-gradient(135deg,#fb923c,#f97316);}
.card-body{padding:1.5rem;}
.card-body h2{font-size:1.4rem;color:#1e293b;margin-bottom:0.5rem;}
.card-body p{color:#64748b;font-size:0.9rem;margin-bottom:1rem;}
.card-total{font-size:1.1rem;font-weight:700;color:#1e293b;}
.card-total span{font-size:1.5rem;}

.data-card{
  background:#fff;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
  width:100%;
  overflow:hidden;
  animation:fadeInUp .5s ease-in-out;
  margin-top:1rem;
}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
.card-header{
  color:#fff;
  padding:1.2rem 1.5rem;
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-weight:600;
  font-size:1.1rem;
}
.card-header.students-header{background:linear-gradient(90deg,#84cc16,#22c55e);}
.card-header.teachers-header{background:linear-gradient(90deg,#06b6d4,#3b82f6);}
.card-header.courses-header{background:linear-gradient(90deg,#10b981,#14b8a6);}
.card-header.users-header{background:linear-gradient(90deg,#fb923c,#f97316);}
.card-header-buttons{display:flex;gap:10px;}
.card-header a, .btn-back{
  background:#fff;
  color:#1e293b;
  padding:.5rem 1rem;
  border-radius:6px;
  text-decoration:none;
  font-weight:600;
  transition:.3s;
  font-size:0.9rem;
}
.card-header a:hover, .btn-back:hover{background:#f1f5f9;transform:scale(1.05);}
table{width:100%;border-collapse:collapse;}
th,td{padding:14px 16px;font-size:.95rem;text-align:left;}
thead th{background:#f8fafc;color:#1e293b;font-weight:600;border-bottom:2px solid #e2e8f0;}
tbody tr{border-bottom:1px solid #f1f5f9;}
tbody tr:hover{background:#f8fafc;}
.btn-delete{
  background:#ef4444;
  color:#fff;
  padding:6px 14px;
  border-radius:6px;
  font-weight:600;
  text-decoration:none;
  transition:.3s;
  font-size:0.85rem;
  display:inline-block;
}
.btn-delete:hover{background:#dc2626;}
.btn-edit{
  background:#10b981;
  color:#fff;
  padding:6px 14px;
  border-radius:6px;
  font-weight:600;
  text-decoration:none;
  transition:.3s;
  margin-right:5px;
  font-size:0.85rem;
  display:inline-block;
}
.btn-edit:hover{background:#059669;}
.btn-block{
  background:#f59e0b;
  color:#fff;
  padding:6px 14px;
  border-radius:6px;
  font-weight:600;
  text-decoration:none;
  transition:.3s;
  margin-right:5px;
  font-size:0.85rem;
  display:inline-block;
}
.btn-block:hover{background:#d97706;}
.btn-activate{
  background:#3b82f6;
  color:#fff;
  padding:6px 14px;
  border-radius:6px;
  font-weight:600;
  text-decoration:none;
  transition:.3s;
  margin-right:5px;
  font-size:0.85rem;
  display:inline-block;
}
.btn-activate:hover{background:#2563eb;}
.status-badge{
  padding:4px 10px;
  border-radius:12px;
  font-size:0.85rem;
  font-weight:600;
  display:inline-block;
}
.status-active{background:#dcfce7;color:#16a34a;}
.status-blocked{background:#fee2e2;color:#dc2626;}
.role-badge{
  padding:4px 10px;
  border-radius:12px;
  font-size:0.85rem;
  font-weight:600;
  display:inline-block;
}
.role-admin{background:#fef3c7;color:#d97706;}
.role-teacher{background:#dbeafe;color:#2563eb;}
.role-student{background:#e0e7ff;color:#4f46e5;}

@media(max-width:1024px){
  .dashboard-card{width:calc(50% - 1rem);}
}
@media(max-width:640px){
  .dashboard-card{width:100%;}
  .main-content{padding:1rem;}
}
</style>
</head>
<body>

<!-- Hamburger Icon -->
<button class="hamburger" onclick="toggleSidebar()">☰</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="logo">Admin Dashboard</div>
  <a href="#" onclick="showDashboard()" class="active">
    <span class="sidebar-icon">📊</span> Dashboard
  </a>
  <a href="#" onclick="showSection('students')">
    <span class="sidebar-icon">👨‍🎓</span> Students
  </a>
  <a href="#" onclick="showSection('teachers')">
    <span class="sidebar-icon">👨‍🏫</span> Teachers
  </a>
  <a href="#" onclick="showSection('courses')">
    <span class="sidebar-icon">📚</span> Courses
  </a>
  <a href="#" onclick="showSection('manage-users')">
    <span class="sidebar-icon">⚙️</span> Manage Users
  </a>
  <a href="../controllers/logout.php">
    <span class="sidebar-icon">🚪</span> Logout
  </a>
</div>

<!-- Main Content -->
<div class="main-content" id="main">
  <div class="top-header">
    <h1>Admin Dashboard</h1>
  </div>
  
  <!-- Dashboard Cards -->
  <div class="container" id="dashboard-cards">
    <div class="dashboard-card" onclick="showSection('students')">
      <div class="card-top students"></div>
      <div class="card-body">
        <h2>Students</h2>
        <p>Manage and view all student records.</p>
        <div class="card-total">Total: <span><?= $student_count ?></span></div>
      </div>
    </div>

    <div class="dashboard-card" onclick="showSection('teachers')">
      <div class="card-top teachers"></div>
      <div class="card-body">
        <h2>Teachers</h2>
        <p>Add or manage teacher records.</p>
        <div class="card-total">Total: <span><?= $teacher_count ?></span></div>
      </div>
    </div>

    <div class="dashboard-card" onclick="showSection('courses')">
      <div class="card-top courses"></div>
      <div class="card-body">
        <h2>Courses</h2>
        <p>View existing courses added by teachers.</p>
        <div class="card-total">Total: <span><?= $course_count ?></span></div>
      </div>
    </div>

    <div class="dashboard-card" onclick="showSection('manage-users')">
      <div class="card-top users"></div>
      <div class="card-body">
        <h2>Manage Users</h2>
        <p>Block or remove any user.</p>
      </div>
    </div>
  </div>

  <!-- Students Section -->
  <div class="data-card" id="students" style="display:none;">
    <div class="card-header students-header">
      <span>Students</span>
      <a class="btn-back" href="#" onclick="showDashboard()">← Back</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>ID Number</th>
          <th>Full Name</th>
          <th>Year & Section</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($students && $students->num_rows): ?>
        <?php while($s=$students->fetch_assoc()): ?>
        <tr>
          <td><?=htmlspecialchars($s['id_number'])?></td>
          <td><?=htmlspecialchars($s['first_name'].' '.$s['last_name'])?></td>
          <td><?=htmlspecialchars($s['year_level'].' '.$s['section'])?></td>
          <td>
            <span class="status-badge <?= $s['status'] == 'active' ? 'status-active' : 'status-blocked' ?>">
              <?=ucfirst(htmlspecialchars($s['status']))?>
            </span>
          </td>
          <td><a class="btn-delete" href="../controllers/delete_user.php?id=<?=$s['id']?>" onclick="return confirm('Delete this student?')">Delete</a></td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align:center;color:#64748b;padding:2rem;">No students found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Teachers Section -->
  <div class="data-card" id="teachers" style="display:none;">
    <div class="card-header teachers-header">
      <span>Teachers</span>
      <div class="card-header-buttons">
        <a href="../views/add_teacher.php">+ Add Teacher</a>
        <a class="btn-back" href="#" onclick="showDashboard()">← Back</a>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>ID Number</th>
          <th>Full Name</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($teachers && $teachers->num_rows): ?>
        <?php while($t=$teachers->fetch_assoc()): ?>
        <tr>
          <td><?=htmlspecialchars($t['id_number'])?></td>
          <td><?=htmlspecialchars($t['first_name'].' '.$t['last_name'])?></td>
          <td>
            <span class="status-badge <?= $t['status'] == 'active' ? 'status-active' : 'status-blocked' ?>">
              <?=ucfirst(htmlspecialchars($t['status']))?>
            </span>
          </td>
          <td>
            <a class="btn-edit" href="../views/edit_teacher.php?id=<?=$t['id']?>">Edit</a>
            <a class="btn-delete" href="../controllers/delete_user.php?id=<?=$t['id']?>" onclick="return confirm('Delete this teacher?')">Delete</a>
          </td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4" style="text-align:center;color:#64748b;padding:2rem;">No teachers found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Courses Section -->
  <div class="data-card" id="courses" style="display:none;">
    <div class="card-header courses-header">
      <span>Courses</span>
      <a class="btn-back" href="#" onclick="showDashboard()">← Back</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Year Level</th>
          <th>Section</th>
          <th>Course Description</th>
          <th>Teacher</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($courses && $courses->num_rows): ?>
        <?php while($c=$courses->fetch_assoc()): ?>
        <tr>
          <td><?=htmlspecialchars($c['course_code'])?></td>
          <td><?=htmlspecialchars($c['year_level'])?></td>
          <td><?=htmlspecialchars($c['section'])?></td>
          <td><?=htmlspecialchars($c['course_description'] ?? '-')?></td>
          <td><?=htmlspecialchars($c['teacher_name'] ?? 'Unknown')?></td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align:center;color:#64748b;padding:2rem;">No courses found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Manage Users Section -->
  <div class="data-card" id="manage-users" style="display:none;">
    <div class="card-header users-header">
      <span>Manage Users</span>
      <a class="btn-back" href="#" onclick="showDashboard()">← Back</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>ID Number</th>
          <th>Full Name</th>
          <th>Role</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($all_users && $all_users->num_rows): ?>
        <?php while($u=$all_users->fetch_assoc()): ?>
        <tr>
          <td><?=htmlspecialchars($u['id_number'])?></td>
          <td><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></td>
          <td>
            <?php
              $role_text = '';
              $role_class = '';
              switch($u['role']) {
                case '2':
                  $role_text = 'Admin';
                  $role_class = 'role-admin';
                  break;
                case '1':
                  $role_text = 'Teacher';
                  $role_class = 'role-teacher';
                  break;
                case '0':
                  $role_text = 'Student';
                  $role_class = 'role-student';
                  break;
                default:
                  $role_text = 'Unknown';
                  $role_class = 'role-student';
              }
            ?>
            <span class="role-badge <?=$role_class?>"><?=$role_text?></span>
          </td>
          <td>
            <span class="status-badge <?= $u['status'] == 'active' ? 'status-active' : 'status-blocked' ?>">
              <?=ucfirst(htmlspecialchars($u['status']))?>
            </span>
          </td>
          <td>
            <?php if ($u['status'] == 'active'): ?>
              <a class="btn-block" href="../controllers/block_user.php?id=<?=$u['id']?>" onclick="return confirm('Block this user?')">Block</a>
            <?php else: ?>
              <a class="btn-activate" href="../controllers/activate_user.php?id=<?=$u['id']?>" onclick="return confirm('Activate this user?')">Activate</a>
            <?php endif; ?>
            <a class="btn-delete" href="../controllers/delete_user.php?id=<?=$u['id']?>" onclick="return confirm('Delete this user permanently?')">Delete</a>
          </td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align:center;color:#64748b;padding:2rem;">No users found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('active');
}

function showDashboard(){
  document.getElementById('dashboard-cards').style.display = 'flex';
  document.getElementById('students').style.display = 'none';
  document.getElementById('teachers').style.display = 'none';
  document.getElementById('courses').style.display = 'none';
  document.getElementById('manage-users').style.display = 'none';
  updateActiveLink(null);
}

function showSection(section){
  document.getElementById('dashboard-cards').style.display = 'none';
  document.getElementById('students').style.display = 'none';
  document.getElementById('teachers').style.display = 'none';
  document.getElementById('courses').style.display = 'none';
  document.getElementById('manage-users').style.display = 'none';
  document.getElementById(section).style.display = 'block';
  updateActiveLink(section);
}

function updateActiveLink(section){
  const links = document.querySelectorAll('.sidebar a');
  links.forEach(link => link.classList.remove('active'));
  if(section){
    links.forEach(link => {
      if(link.textContent.toLowerCase().includes(section.replace('-', ' '))){
        link.classList.add('active');
      }
    });
  } else {
    links[0].classList.add('active');
  }
}
</script>

</body>
</html>