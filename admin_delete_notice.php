<?php
require 'includes/admin_auth.php';
require 'includes/admin_db.php';

$msg = '';

if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    
    $file_address = '';
    $stmt_file = $conn->prepare("SELECT file_address FROM document WHERE id = ?");
    $stmt_file->bind_param("i", $del_id);
    $stmt_file->execute();
    $stmt_file->bind_result($file_address);
    $stmt_file->fetch();
    $stmt_file->close();

    $stmt = $conn->prepare("DELETE FROM document WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    
    if($stmt->execute()) {
        $display_del = sprintf("%08d", $del_id);
        $msg = "Notice #$display_del removed from database.";
        
        if (!empty($file_address)) {
            $file_deleted = false;
            
            $potential_paths = [
                $file_address,                                                
                __DIR__ . '/' . ltrim($file_address, '/'),                    
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($file_address, '/'),  
                __DIR__ . '/../' . ltrim($file_address, '/')                  
            ];

            foreach ($potential_paths as $path) {
                if (file_exists($path) && is_file($path)) {
                    if (unlink($path)) {
                        $msg .= " Physical file successfully deleted.";
                        $file_deleted = true;
                    } else {
                        $msg .= " (Warning: File found, but server permissions prevented deletion.)";
                        $file_deleted = true; 
                    }
                    break; 
                }
            }

            if (!$file_deleted) {
                $msg .= " (Warning: Could not find the physical file on the server. It may have already been moved or deleted.)";
            }
        }
        
    } else {
        $msg = "Error deleting record: " . $conn->error;
    }
}

$has_filter = !empty($_GET['filter_year']) || !empty($_GET['filter_type']) || !empty($_GET['search_title']);

$limit = $has_filter ? 200 : 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where = "";
$params = [];
$types = "";

if (!$has_filter) {
    $month_page = isset($_GET['month_page']) && is_numeric($_GET['month_page']) ? max(0, (int)$_GET['month_page']) : 0;

    $newer_month_start = (new DateTime())->modify('-' . ($month_page * 2) . ' months')->format('Y-m-01');
    $older_month_start = (new DateTime($newer_month_start))->modify('-1 month')->format('Y-m-01');
    $after_newer_month = (new DateTime($newer_month_start))->modify('+1 month')->format('Y-m-01');

    $where = "WHERE d.date >= ? AND d.date < ?";
    $params = [$older_month_start, $after_newer_month];
    $types = "ss";

    $display_range = date('F Y', strtotime($older_month_start)) . " &amp; " . date('F Y', strtotime($newer_month_start));
} else {
    $active_year = !empty($_GET['filter_year']) ? $_GET['filter_year'] : date("Y");
    $where = "WHERE YEAR(d.date) = ?";
    $params = [$active_year];
    $types = "i";

    // --- NEW FILTER FIX: Group English and Hindi equivalents (Robust Version) ---
    if (!empty($_GET['filter_type'])) {
        $selected_type = trim($_GET['filter_type']); // trim removes accidental spaces
        
        // Group the equivalents together
        $equivalent_groups = [
            ["ION", "अंत: कार्यालयीन सूचना"],
            ["DO", "दैनिक आदेश"],
            ["Circular", "परिपत्र"]
        ];

        $matched_group = null;
        foreach ($equivalent_groups as $group) {
            // If the selected dropdown value is in this group, grab the whole group
            if (in_array($selected_type, $group)) {
                $matched_group = $group;
                break;
            }
        }

        if ($matched_group) {
            $where .= " AND (t.type_name IN (?, ?) OR d.custom_type IN (?, ?))";
            array_push($params, $matched_group[0], $matched_group[1], $matched_group[0], $matched_group[1]);
            $types .= "ssss";
        } else {
            $where .= " AND (t.type_name = ? OR d.custom_type = ?)";
            array_push($params, $selected_type, $selected_type);
            $types .= "ss";
        }
    }
    // -------------------------------------------------------------------
    
    if (!empty($_GET['search_title'])) {
        $where .= " AND d.title LIKE ?";
        $search_term = '%' . $_GET['search_title'] . '%'; 
        $params[] = $search_term;
        $types .= "s";
    }
}

// --- NEW SORTING FIX: Properly sorting by actual date and time ---
$sql = "SELECT d.id, d.title, d.file_address, t.type_name, d.custom_type, d.division_description as division_name, d.date, d.time 
        FROM document d
        LEFT JOIN type t ON d.type_id = t.type_id
        $where ORDER BY d.date DESC, d.time DESC, d.id DESC LIMIT ? OFFSET ?";
// -----------------------------------------------------------------

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$documents = $stmt->get_result();
$rowCount = $documents->num_rows;

$query_string = "";
if ($has_filter) {
    if (!empty($_GET['filter_year'])) $query_string .= "&filter_year=" . urlencode($_GET['filter_year']);
    if (!empty($_GET['filter_type'])) $query_string .= "&filter_type=" . urlencode($_GET['filter_type']);
    if (!empty($_GET['search_title'])) $query_string .= "&search_title=" . urlencode($_GET['search_title']);
} else {
    $query_string .= "&month_page=" . $month_page;
}

// Fetch all unique notice types (both manual and custom) to populate the dropdown
$filter_types = [];
$typeQuery = $conn->query("SELECT DISTINCT t.type_name, d.custom_type FROM document d LEFT JOIN type t ON d.type_id = t.type_id");
while($row = $typeQuery->fetch_assoc()) {
    if(!empty($row['type_name']) && !in_array($row['type_name'], $filter_types)) $filter_types[] = $row['type_name'];
    if(!empty($row['custom_type']) && !in_array($row['custom_type'], $filter_types)) $filter_types[] = $row['custom_type'];
}
sort($filter_types); // Alphabetize the list
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Notices</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .filter-bar { display: flex; flex-direction: row; flex-wrap: wrap; gap: 12px; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd; align-items: flex-end; margin-bottom: 25px; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group input[type="text"] { padding: 6px; border: 1px solid #ccc; border-radius: 4px; width: 200px; }
        .time-text { color: #d9534f; font-weight: bold; font-size: 0.9em; }
        .update-btn { background-color: #17a2b8; color: white; padding: 7px 14px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; margin-right: 5px; }
        .update-btn:hover { background-color: #117a8b; }
        .action-cell { text-align: center; vertical-align: middle; white-space: nowrap; }
        .id-badge { background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: bold; color: #333333; }
        .range-header { font-size: 1.1em; color: #0056b3; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/admin_header.php'; ?>
        
        <h2 style="border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 20px;">Update &amp; Delete Notices</h2>

        <?php if($msg): ?> 
            <div class="msg" style="padding: 15px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 4px; margin-bottom: 20px;">
                <?php echo $msg; ?>
            </div> 
        <?php endif; ?>

        <?php if (!$has_filter): ?>
            <div class="range-header">
                Showing notices from <strong><?= $display_range ?></strong>
            </div>
        <?php endif; ?>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Year</label>
                <select name="filter_year">
                    <?php 
                    $years_array = [];
                    $yearRes = $conn->query("SELECT DISTINCT YEAR(date) as doc_year FROM document WHERE date IS NOT NULL");
                    while($yRow = $yearRes->fetch_assoc()) {
                        if($yRow['doc_year']) $years_array[] = $yRow['doc_year'];
                    }
                    $current_year = date("Y");
                    if(!in_array($current_year, $years_array)) $years_array[] = $current_year;
                    rsort($years_array);
                    foreach($years_array as $y) {
                        $sel = (isset($_GET['filter_year']) && $_GET['filter_year'] == $y) ? 'selected' : '';
                        echo "<option value='{$y}' $sel>{$y}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Notice Type</label>
                <select name="filter_type">
                    <option value="">All Types</option>
                    <?php 
                    // Use our new dynamic array instead of strictly the old type table
                    foreach($filter_types as $t) {
                        $sel = (isset($_GET['filter_type']) && $_GET['filter_type'] == $t) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($t) . "' $sel>" . htmlspecialchars($t) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Search Title</label>
                <input type="text" name="search_title" placeholder="Type a keyword..." value="<?= isset($_GET['search_title']) ? htmlspecialchars($_GET['search_title']) : '' ?>">
            </div>

            <button type="submit">Apply Filter</button>
            <a href="admin_delete_notice.php" class="reset-btn">Reset</a>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Time (IST)</th>
                    <th>Notice Title</th>
                    <th>Division</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($rowCount > 0): ?>
                    <?php while($row = $documents->fetch_assoc()): ?>
                    <tr>
                        <td><span class="id-badge"><?= sprintf("%08d", $row['id']) ?></span></td>
                        <td style="color: #666; font-size: 0.9em;"><?= $row['date'] ?></td>
                        <td class="time-text"><?= date("h:i A", strtotime($row['time'])) ?></td>
                        <td>
                            <?php $safe_url = str_replace(' ', '%20', $row['file_address']); ?>
                            <a href="<?= htmlspecialchars($safe_url) ?>" target="_blank" style="text-decoration: none; color: #0056b3; font-weight: bold;">
                                <?= htmlspecialchars($row['title']) ?>
                            </a>
                            <br><small style="color: #999;">
                                <?= htmlspecialchars(!empty($row['type_name']) ? $row['type_name'] : $row['custom_type']) ?>
                            </small>
                        </td>
                        <td><?= htmlspecialchars($row['division_name']) ?></td>
                        <td class="action-cell">
                            <a href="admin_update_notice.php?id=<?= $row['id'] ?>" class="update-btn">Edit</a>
                            <a href="admin_delete_notice.php?delete_id=<?= $row['id'] ?><?= $query_string ?>&page=<?= $page ?>" 
                               class="delete-btn" 
                               onclick="return confirm('Permanently delete Notice #<?= sprintf("%08d", $row['id']) ?> and its file?');">
                               Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: #999;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination-container">
            <?php if (!$has_filter): // Recent 2-month mode ?>
                <?php if ($month_page > 0): ?>
                    <a href="?month_page=<?= $month_page - 1 ?>" class="btn-page">Next 2 Month &laquo; </a>
                <?php else: ?>
                    <div class="spacer"></div>
                <?php endif; ?>

                <a href="?month_page=<?= $month_page + 1 ?>" class="btn-page"> Previous 2 Months &raquo;</a>
            <?php else: // Filtered mode with 200/page ?>
                <?php if($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $query_string ?>" class="btn-page">&laquo; Previous 200</a>
                <?php else: ?>
                    <div class="spacer"></div> 
                <?php endif; ?>

                <?php if($rowCount == $limit): ?>
                    <a href="?page=<?= $page + 1 ?><?= $query_string ?>" class="btn-page">Next 200 &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>