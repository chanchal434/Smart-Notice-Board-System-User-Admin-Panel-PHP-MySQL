<?php
require_once 'includes/user_db.php';
require_once 'includes/user_header.php';
?>

<div class="container">
    <h2>Recent Notices</h2>

    <?php
    // Step 1: Get the latest 2 distinct months using the new 'date' column
    $monthQuery = "SELECT DISTINCT YEAR(date) as year_value, DATE_FORMAT(date, '%M') as month_name, MONTH(date) as month_num 
                   FROM document 
                   ORDER BY year_value DESC, month_num DESC 
                   LIMIT 2";
    $monthResult = $conn->query($monthQuery);

    if ($monthResult->num_rows > 0) {
        while ($monthRow = $monthResult->fetch_assoc()) {
            $currentYear = $monthRow['year_value'];
            $currentMonth = $monthRow['month_name'];
            
            echo "<div class='month-group'>";
            echo "<h3 class='month-heading'>" . htmlspecialchars($currentMonth . " " . $currentYear) . "</h3>";
            echo "<ul class='notice-list'>";

            // Step 2: Fetch notices exactly for this month/year
            $noticeStmt = $conn->prepare("
                SELECT d.custom_type, t.type_name, d.division_description, d.title, d.file_address 
                FROM document d
                LEFT JOIN type t ON d.type_id = t.type_id
                WHERE YEAR(d.date) = ? AND DATE_FORMAT(d.date, '%M') = ? 
                ORDER BY d.date DESC, d.time DESC
            ");
            $noticeStmt->bind_param("is", $currentYear, $currentMonth);
            $noticeStmt->execute();
            $notices = $noticeStmt->get_result();

            while ($notice = $notices->fetch_assoc()) {
                // Determine which type to display: the predefined type_name or the custom_type
                $display_type = !empty($notice['type_name']) ? $notice['type_name'] : $notice['custom_type'];

                // Handle NULL values cleanly
                $metaParts = [];
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
        echo "<p>No notices found.</p>";
    }
    ?>

    <div class="action-cards">
        <a href="user_since_2026.php" class="card-btn">View Notices (Since 2026)</a>
        <a href="user_before_2026.php" class="card-btn">View Notices (Before 2026)</a>
    </div>
</div>

</body>
</html>