<?php
require_once 'includes/user_db.php';
require_once 'includes/user_header.php';

// Pagination setup (Months per page)
$monthsPerPage = 2;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $monthsPerPage;

// Filter Inputs
$filterType = $_GET['type'] ?? '';
$filterKeyword = $_GET['keyword'] ?? '';

// Handle "All Months" Default
if (isset($_GET['month'])) {
    $filterMonth = $_GET['month'];
} else {
    $filterMonth = date('F'); 
}

$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');   

// Build WHERE clause dynamically (using 'd.' prefix for document table)
$whereClause = "WHERE YEAR(d.date) >= 2026";
$params = [];
$types = "";

// Define the linked types for the user side (Robust Version)
$equivalent_groups = [
    ["ION", "अंत: कार्यालयीन सूचना"],
    ["DO", "दैनिक आदेश"],
    ["Circular", "परिपत्र"]
];

if (!empty($filterType)) {
    $selected_type = trim($filterType);
    $matched_group = null;
    
    foreach ($equivalent_groups as $group) {
        if (in_array($selected_type, $group)) {
            $matched_group = $group;
            break;
        }
    }

    if ($matched_group) {
        // Look in BOTH the type table and custom_type column
        $whereClause .= " AND (t.type_name IN (?, ?) OR d.custom_type IN (?, ?))";
        array_push($params, $matched_group[0], $matched_group[1], $matched_group[0], $matched_group[1]);
        $types .= "ssss";
    } else {
        $whereClause .= " AND (t.type_name = ? OR d.custom_type = ?)";
        array_push($params, $selected_type, $selected_type);
        $types .= "ss";
    }
}

if (!empty($filterKeyword)) {
    $whereClause .= " AND d.title LIKE ?";
    $params[] = "%" . $filterKeyword . "%";
    $types .= "s";
}
if (!empty($filterMonth)) {
    $whereClause .= " AND DATE_FORMAT(d.date, '%M') = ?";
    $params[] = $filterMonth;
    $types .= "s";
}
if (!empty($filterYear)) {
    $whereClause .= " AND YEAR(d.date) = ?";
    $params[] = $filterYear;
    $types .= "i";
}

// Fetch all unique notice types (both manual and custom) to populate the dropdown
$filter_types = [];
$typeQuery = $conn->query("SELECT DISTINCT t.type_name, d.custom_type FROM document d LEFT JOIN type t ON d.type_id = t.type_id WHERE YEAR(d.date) >= 2026");
while($row = $typeQuery->fetch_assoc()) {
    if(!empty($row['type_name']) && !in_array($row['type_name'], $filter_types)) $filter_types[] = $row['type_name'];
    if(!empty($row['custom_type']) && !in_array($row['custom_type'], $filter_types)) $filter_types[] = $row['custom_type'];
}
sort($filter_types); 
?>

<div class="container">
    <a href="user_index.php" class="btn-secondary" style="margin-bottom: 20px; display: inline-block; background-color: #004080; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 4px;">&larr; Back to Home</a>
    
    <h2>Notices: Since 2026</h2>

    <form class="filter-box" method="GET" action="user_since_2026.php">
        <select name="type">
            <option value="">All Types</option>
            <?php
            foreach($filter_types as $t) {
                $sel = ($filterType == $t) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($t) . "' $sel>" . htmlspecialchars($t) . "</option>";
            }
            ?>
        </select>

        <select name="month">
            <option value="" <?= ($filterMonth === '') ? 'selected' : '' ?>>All Months</option>
            <?php
            // Pull all available months from the database
            $monthRes = $conn->query("SELECT DISTINCT DATE_FORMAT(date, '%M') as month_name, MONTH(date) as month_num FROM document WHERE YEAR(date) >= 2026 ORDER BY month_num");
            $dbMonths = [];
            while ($row = $monthRes->fetch_assoc()) {
                $dbMonths[] = $row['month_name'];
                $selected = ($filterMonth === $row['month_name']) ? "selected" : "";
                echo "<option value='" . htmlspecialchars($row['month_name']) . "' {$selected}>" . htmlspecialchars($row['month_name']) . "</option>";
            }
            
            if (!in_array(date('F'), $dbMonths) && !isset($_GET['month'])) {
                echo "<option value='" . date('F') . "' selected>" . date('F') . "</option>";
            }
            ?>
        </select>

        <select name="year">
            <?php
            // Pull all available years from the database
            $yearRes = $conn->query("SELECT DISTINCT YEAR(date) as year_value FROM document WHERE YEAR(date) >= 2026 ORDER BY year_value DESC");
            $dbYears = [];
            while ($row = $yearRes->fetch_assoc()) {
                $dbYears[] = $row['year_value'];
                $selected = ($filterYear == $row['year_value']) ? "selected" : "";
                echo "<option value='" . htmlspecialchars($row['year_value']) . "' {$selected}>" . htmlspecialchars($row['year_value']) . "</option>";
            }

            if (!in_array(date('Y'), $dbYears) && !isset($_GET['year'])) {
                echo "<option value='" . date('Y') . "' selected>" . date('Y') . "</option>";
            }
            ?>
        </select>
        
        <input type="text" name="keyword" placeholder="Search keyword..." value="<?= htmlspecialchars($filterKeyword) ?>">

        <button type="submit" class="btn-primary">Filter</button>
        <a href="user_since_2026.php" class="btn-secondary" style="background-color: #008020; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 4px;">Reset</a>
    </form>

    <?php
    // We added LEFT JOIN to the month query to support the WHERE filters properly
    $monthQuery = "SELECT DISTINCT YEAR(d.date) as year_value, DATE_FORMAT(d.date, '%M') as month_name, MONTH(d.date) as month_num 
                   FROM document d
                   LEFT JOIN type t ON d.type_id = t.type_id
                   $whereClause 
                   ORDER BY year_value DESC, month_num DESC 
                   LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($monthQuery);
    
    $bindParams = $params;
    $bindParams[] = $monthsPerPage;
    $bindParams[] = $offset;
    $bindTypes = $types . "ii";
    
    if (!empty($bindTypes)) {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
    
    $stmt->execute();
    $monthResult = $stmt->get_result();

    $monthsFound = 0;

    if ($monthResult->num_rows > 0) {
        while ($monthRow = $monthResult->fetch_assoc()) {
            $monthsFound++;
            $currentYear = $monthRow['year_value'];
            $currentMonth = $monthRow['month_name'];
            
            echo "<div class='month-group'>";
            echo "<h3 class='month-heading'>" . htmlspecialchars($currentMonth . " " . $currentYear) . "</h3>";
            echo "<ul class='notice-list'>";

            // UPDATE: Join the type table so we can fetch t.type_name
            $noticeSql = "SELECT t.type_name, d.custom_type, d.division_description, d.title, d.file_address 
                          FROM document d 
                          LEFT JOIN type t ON d.type_id = t.type_id 
                          WHERE YEAR(d.date) = ? AND DATE_FORMAT(d.date, '%M') = ?";
            
            $noticeParams = [$currentYear, $currentMonth];
            $noticeTypes = "is";

            if (!empty($filterType)) {
                $selected_type_loop = trim($filterType);
                $matched_group_loop = null;
                
                foreach ($equivalent_groups as $group) {
                    if (in_array($selected_type_loop, $group)) {
                        $matched_group_loop = $group;
                        break;
                    }
                }
                
                if ($matched_group_loop) {
                    $noticeSql .= " AND (t.type_name IN (?, ?) OR d.custom_type IN (?, ?))";
                    array_push($noticeParams, $matched_group_loop[0], $matched_group_loop[1], $matched_group_loop[0], $matched_group_loop[1]);
                    $noticeTypes .= "ssss";
                } else {
                    $noticeSql .= " AND (t.type_name = ? OR d.custom_type = ?)";
                    array_push($noticeParams, $selected_type_loop, $selected_type_loop);
                    $noticeTypes .= "ss";
                }
            }

            if (!empty($filterKeyword)) {
                $noticeSql .= " AND d.title LIKE ?";
                $noticeParams[] = "%" . $filterKeyword . "%";
                $noticeTypes .= "s";
            }

            $noticeSql .= " ORDER BY d.date DESC, d.time DESC";
            $noticeStmt = $conn->prepare($noticeSql);
            $noticeStmt->bind_param($noticeTypes, ...$noticeParams);
            $noticeStmt->execute();
            $notices = $noticeStmt->get_result();

            while ($notice = $notices->fetch_assoc()) {
                $metaParts = [];
                
                // UPDATE: Figure out which type to display (pre-defined vs custom)
                $display_type = !empty($notice['type_name']) ? $notice['type_name'] : $notice['custom_type'];
                
                if (!empty($display_type)) $metaParts[] = htmlspecialchars($display_type);
                if (!empty($notice['division_description'])) $metaParts[] = "(" . htmlspecialchars($notice['division_description']) . ")";
                
                $metaText = !empty($metaParts) ? "<span class='notice-meta'>" . implode(" | ", $metaParts) . " : </span>" : "";
                
                $title = htmlspecialchars($notice['title']);
                $link = htmlspecialchars($notice['file_address']);
                
                echo "<li class='notice-item'>";
                echo $metaText;
                echo "<a href='{$link}' target='_blank' class='notice-title'>{$title}</a>";
                echo "</li>";
            }
            echo "</ul></div>";
            $noticeStmt->close();
        }
    } else {
        echo "<div class='month-group' style='padding: 20px;'>";
        $displayMonth = ($filterMonth === "") ? "All Months in" : htmlspecialchars($filterMonth);
        echo "<strong>No notices found for " . $displayMonth . " " . htmlspecialchars($filterYear) . ".</strong>";
        echo "<p>Please click the dropdown toggles above to select a different filter.</p>";
        echo "</div>";
    }
    $stmt->close();
    ?>

    <div class="pagination">
        <?php
        $queryString = http_build_query(array_merge($_GET, ['page' => $page - 1]));
        if ($page > 1) {
            echo "<a href='user_since_2026.php?$queryString' class='btn-primary'>&laquo; Previous Data</a>";
        } else {
            echo "<div></div>"; 
        }

        $nextQueryString = http_build_query(array_merge($_GET, ['page' => $page + 1]));
        if ($monthsFound == $monthsPerPage) {
            echo "<a href='user_since_2026.php?$nextQueryString' class='btn-primary'>Next Data &raquo;</a>";
        }
        ?>
    </div>
</div>

</body>
</html>