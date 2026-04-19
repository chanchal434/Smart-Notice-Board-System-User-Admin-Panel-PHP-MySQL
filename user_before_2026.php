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

// --- MODIFIED FIX: Handle "All Months" Default ---
if (isset($_GET['month'])) {
    $filterMonth = $_GET['month'];
} else {
    $filterMonth = date('F'); 
}

$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');   

// Build WHERE clause dynamically
$whereClause = "WHERE YEAR(date) >= 2026";
$params = [];
$types = "";





if (!empty($filterKeyword)) {
    $whereClause .= " AND title LIKE ?";
    $params[] = "%" . $filterKeyword . "%";
    $types .= "s";
}
// Apply the Month filter ONLY if it is not empty ("" means All Months)
if (!empty($filterMonth)) {
    $whereClause .= " AND DATE_FORMAT(date, '%M') = ?";
    $params[] = $filterMonth;
    $types .= "s";
}
// Apply the Year filter (Defaults to current year)
if (!empty($filterYear)) {
    $whereClause .= " AND YEAR(date) = ?";
    $params[] = $filterYear;
    $types .= "i";
}
?>

<div class="container">
    <a href="user_index.php" class="btn-secondary" style="margin-bottom: 20px; display: inline-block; background-color: #004080; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 4px;">&larr; Back to Home</a>
    
    <h2>Notices: Since 2026</h2>

    <form class="filter-box" method="GET" action="user_since_2026.php">
        <select name="type">
            <option value="">All Types</option>
            <?php
            $typeRes = $conn->query("SELECT DISTINCT custom_type FROM document WHERE YEAR(date) >= 2026 AND custom_type IS NOT NULL");
            while ($row = $typeRes->fetch_assoc()) {
                $selected = ($filterType == $row['custom_type']) ? "selected" : "";
                echo "<option value='" . htmlspecialchars($row['custom_type']) . "' {$selected}>" . htmlspecialchars($row['custom_type']) . "</option>";
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
            
            // Safety check: If the current month has NO notices yet, add it to the dropdown anyway so the box isn't blank
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

            // Safety check: If the current year has NO notices yet, add it to the dropdown anyway
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
    // Fetch distinct months based on filters + pagination
    $monthQuery = "SELECT DISTINCT YEAR(date) as year_value, DATE_FORMAT(date, '%M') as month_name, MONTH(date) as month_num 
                   FROM document 
                   $whereClause 
                   ORDER BY year_value DESC, month_num DESC 
                   LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($monthQuery);
    
    // Bind parameters dynamically
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

            // Fetch notices for this specific month
            $noticeSql = "SELECT custom_type as type, division_description, title, file_address FROM document WHERE YEAR(date) = ? AND DATE_FORMAT(date, '%M') = ?";
            
            $noticeParams = [$currentYear, $currentMonth];
            $noticeTypes = "is";

           
                
                if ($matched_group_loop) {
                    $noticeSql .= " AND custom_type IN (?, ?)";
                    array_push($noticeParams, $matched_group_loop[0], $matched_group_loop[1]);
                    $noticeTypes .= "ss";
                } else {
                    $noticeSql .= " AND custom_type = ?";
                    $noticeParams[] = $selected_type_loop;
                    $noticeTypes .= "s";
                }
            }

            if (!empty($filterKeyword)) {
                $noticeSql .= " AND title LIKE ?";
                $noticeParams[] = "%" . $filterKeyword . "%";
                $noticeTypes .= "s";
            }

            $noticeSql .= " ORDER BY date DESC, time DESC";
            $noticeStmt = $conn->prepare($noticeSql);
            $noticeStmt->bind_param($noticeTypes, ...$noticeParams);
            $noticeStmt->execute();
            $notices = $noticeStmt->get_result();

            while ($notice = $notices->fetch_assoc()) {
                $metaParts = [];
                if (!empty($notice['type'])) $metaParts[] = htmlspecialchars($notice['type']);
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
