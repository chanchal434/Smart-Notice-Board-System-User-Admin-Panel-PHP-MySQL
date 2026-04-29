<?php
require 'includes/admin_auth.php';
require 'includes/admin_db.php';

$msg = '';


$dt_current = new DateTime('now', $tz);
$today_date = $dt_current->format('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type_id = $_POST['type_id'];
    if ($type_id === '') $type_id = NULL; // Allow empty type

    $title = $_POST['title'];
    $date = $_POST['notice_date'] ?? $today_date; 
    
    // --- UPDATED DIVISION LOGIC ---
    $division_selection = $_POST['division_selection'];
    if ($division_selection === 'other') {
        $division_description = trim($_POST['new_division_name']);
        if (empty($division_description)) {
            $msg = "Error: Please provide a name for the custom division.";
        }
    } else {
        $division_description = $division_selection;
    }
    if ($division_description === '') {
        $division_description = null;
    }

    $base_address = trim($_POST['file_address']);
    $base_address = str_replace('\\', '/', $base_address);
    if (stripos($base_address, 'C:/') === 0) {
        $base_address = str_ireplace('C:/', '/c_drive/', $base_address);
    }
    if (substr($base_address, -1) !== '/') $base_address .= '/';
    
    $custom_type = NULL; 

    if ($type_id === 'other') {
        $custom_type = trim($_POST['new_type_name']);
        $type_id = NULL; 
        if (empty($custom_type)) $msg = "Error: Please provide a name for the custom category.";
    }
    if ($custom_type === '') {
        $custom_type = null;
    }

    if (empty($msg)) {
        if (!isset($_FILES['uploaded_file']) || $_FILES['uploaded_file']['error'] !== UPLOAD_ERR_OK) {
            $msg = "Error: Please select a valid file to upload.";
        } else {
            $uploaded_file = $_FILES['uploaded_file'];
            $original_name = $uploaded_file['name'];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION)); // Lowercase for safe checking
            $original_basename = pathinfo($original_name, PATHINFO_FILENAME);
            
            // --- File Extension Restriction ---
            $allowed_exts = ['doc', 'docx', 'pdf', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'wps', 'ppt', 'pptx', 'xlsx', 'xml', 'xps'];
            
            if (!in_array($ext, $allowed_exts)) {
                $msg = "Error: File type '.$ext' is not allowed. Allowed types: pdf, docx, xlsx, png, jpg, etc.";
            } else {
                // 1. Setup Exact Directory based on User Input
                $relative_dir = ltrim($base_address, '/'); // Remove leading slash for safe physical path mapping
                $target_dir = __DIR__ . '/' . $relative_dir;
                
                // Create the exact user-defined folder if it doesn't exist
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true); 
                }

                // 2. GENERATE THE NEW YYMMXXXX ID based on chosen date
                $notice_date_obj = new DateTime($date);
                $yymm = $notice_date_obj->format('ym'); 
                
                $like_pattern = $yymm . '%';
                $stmt_id = $conn->prepare("SELECT MAX(id) as max_id FROM document WHERE id LIKE ?");
                $stmt_id->bind_param("s", $like_pattern);
                $stmt_id->execute();
                $res_id = $stmt_id->get_result();
                $row_id = $res_id->fetch_assoc();
                
                if ($row_id && $row_id['max_id']) {
                    $new_id = $row_id['max_id'] + 1; 
                } else {
                    $new_id = (int)($yymm . '0001'); 
                }
                $stmt_id->close();

                // 3. Generate filenames using the new ID
                $generated_filename = $new_id . '-' . $original_basename . '-' . $date;
                if (!empty($ext)) $generated_filename .= '.' . $ext;

                // Map target paths to EXACT user address
                $target_path = $target_dir . $generated_filename;
                $full_file_address = $base_address . $generated_filename;
                $time = $dt_current->format('H:i:s');

                // 4. Upload file and insert into database
                if (move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
                    $stmt = $conn->prepare("INSERT INTO document (id, title, file_name, file_address, date, time, type_id, custom_type, division_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssssss", $new_id, $title, $generated_filename, $full_file_address, $date, $time, $type_id, $custom_type, $division_description);
                    
                    if ($stmt->execute()) {
                        header("Location: admin_delete_notice.php");
                        exit;
                    } else {
                        unlink($target_path);
                        $msg = "Database error: " . $conn->error;
                    }
                } else {
                    $msg = "Failed to save uploaded file on server.";
                }
            }
        }
    }
}

$res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'default_address'");
$default_address = $res->fetch_assoc()['setting_value'] ?? '';

$types = $conn->query("SELECT * FROM type");
$divisions = $conn->query("SELECT * FROM division");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Notice</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .notice-list { list-style-type: disc; padding-left: 20px; margin-top: 20px; font-size: 16px; line-height: 1.6; }
        .notice-list li { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed #e0e0e0; color: #333; }
        .notice-list li:last-child { border-bottom: none; }
        .notice-category { font-weight: bold; color: #000; }
        .notice-division { color: #555; }
        .notice-title-link { color: #0056b3; font-weight: bold; text-decoration: none; }
        .notice-title-link:hover { text-decoration: underline; }

        #notice_form > label { margin-top: 22px; margin-bottom: 8px; display: block; font-size: 14.5px; }
        #notice_form > label:first-child { margin-top: 0; }
        #notice_form > select, #notice_form > input[type="text"], #notice_form > input[type="date"], #notice_form > input[type="file"] { width: 100%; margin-bottom: 5px; }
        #notice_form > input[type="date"] { padding: 0 12px; border: 1px solid #ccc; border-radius: 4px; height: 42px; font-size: 14px; box-sizing: border-box; }
    </style>
    <script>
    function toggleOtherType() {
        var selectBox = document.getElementById("type_id");
        var otherInputContainer = document.getElementById("other_type_container");
        var otherInput = document.getElementById("new_type_name");
        if (selectBox.value === "other") {
            otherInputContainer.style.display = "block"; 
            otherInput.required = true; 
        } else {
            otherInputContainer.style.display = "none"; 
            otherInput.required = false; 
        }
    }

    function toggleOtherDivision() {
        var selectBox = document.getElementById("division_selection");
        var otherInputContainer = document.getElementById("other_division_container");
        var otherInput = document.getElementById("new_division_name");
        if (selectBox.value === "other") {
            otherInputContainer.style.display = "block"; 
            otherInput.required = true; // --- UPDATED: Make input required ---
        } else {
            otherInputContainer.style.display = "none"; 
            otherInput.required = false; 
        }
    }

    function getGeneratedFilename(originalName, date) {
        if (!originalName) return "pending";
        const lastDot = originalName.lastIndexOf('.');
        const basename = lastDot > -1 ? originalName.substring(0, lastDot) : originalName;
        const ext = lastDot > -1 ? originalName.substring(lastDot) : '';
        return "00000000" + '-' + basename + '-' + date + ext;
    }

    function showPreviewModal(event) {
        event.preventDefault(); 
        
        var title = document.getElementsByName('title')[0].value;
        var date = document.getElementsByName('notice_date')[0].value;
        var baseAddress = document.getElementsByName('file_address')[0].value;
        var fileInput = document.getElementById('fake_file_picker');
        var originalFileName = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0].name : "No file selected";

        var generatedFileName = getGeneratedFilename(originalFileName, date);
        var fullAddress = baseAddress + (baseAddress.endsWith('/') ? '' : '/') + generatedFileName;

        var divSelect = document.getElementById('division_selection');
        var divisionText = divSelect.value === 'other' 
            ? document.getElementById('new_division_name').value 
            : divSelect.options[divSelect.selectedIndex].text;
        
        var typeSelect = document.getElementById('type_id');
        var typeText = typeSelect.value === 'other' 
            ? document.getElementById('new_type_name').value 
            : typeSelect.options[typeSelect.selectedIndex].text;

        document.getElementById('sum_type').innerText = typeText === '-- No Type --' ? 'N/A' : typeText;
        // --- UPDATED: Handle N/A for empty division ---
        document.getElementById('sum_div').innerText = divisionText === '-- No Division --' ? 'N/A' : divisionText;
        document.getElementById('sum_date').innerText = date;
        document.getElementById('sum_title').innerText = title;
        document.getElementById('sum_file').innerText = generatedFileName;
        document.getElementById('sum_full_address').innerText = fullAddress;

        document.getElementById('preview_title').innerText = title;
        document.getElementById('preview_division').innerText = divisionText === '-- No Division --' ? 'N/A' : divisionText;
        document.getElementById('preview_type').innerText = typeText === '-- No Type --' ? 'N/A' : typeText;
        
        document.getElementById('previewModal').style.display = 'flex';
    }

    function closePreviewModal() {
        document.getElementById('previewModal').style.display = 'none';
    }

    function submitForm() {
        document.getElementById('notice_form').submit();
    }
    </script>
</head>
<body>
    <div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; width: 550px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); text-align: center;">
            <h2 style="color: #0056b3; margin-top: 0;">Confirm Notice Details</h2>
            
            <div style="text-align: left; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; border: 1px solid #ced4da;">
                <h4 style="margin-top: 0; margin-bottom: 10px; color: #333;">Data Summary:</h4>
                <p style="margin: 5px 0;"><strong>Notice Type:</strong> <span id="sum_type" style="color:#0056b3;"></span></p>
                <p style="margin: 5px 0;"><strong>Division:</strong> <span id="sum_div" style="color:#0056b3;"></span></p>
                <p style="margin: 5px 0;"><strong>Notice Date:</strong> <span id="sum_date" style="color:#0056b3;"></span></p>
                <p style="margin: 5px 0;"><strong>Notice Title:</strong> <span id="sum_title" style="color:#0056b3;"></span></p>
                <hr style="border: 0; border-top: 1px solid #ccc; margin: 10px 0;">
                <p style="margin: 5px 0;"><strong>Final File Name:</strong> <span id="sum_file" style="color:#d9534f;"></span></p>
                <p style="margin: 5px 0;"><strong>Full Address (stored in DB):</strong> <span id="sum_full_address" style="color:#d9534f; word-break: break-all;"></span></p>
                <p style="margin: 5px 0; color:#d9534f; font-size:13px;"><strong>Note:</strong> The ID <b>00000000</b> will be replaced with the actual 8-digit ID after saving.</p>
            </div>

            <p style="color: #555; margin-bottom: 10px; text-align: left; font-weight: bold;">How it will look on the board:</p>
            
            <div style="background: #f8f9fa; border: 1px dashed #ccc; padding: 15px; border-radius: 5px; text-align: left; margin-bottom: 25px;">
                <ul style="list-style-type: disc; padding-left: 20px; margin: 0; font-size: 16px;">
                    <li>
                        <span id="preview_type" style="font-weight: bold; color: #000;"></span> | 
                        <span style="color: #555;">(<span id="preview_division"></span>)</span> : 
                        <span id="preview_title" style="color: #0056b3; font-weight: bold; text-decoration: underline;"></span>
                    </li>
                </ul>
            </div>
            
            <div style="display: flex; justify-content: center; gap: 15px;">
                <button type="button" onclick="closePreviewModal()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 15px;">Edit Details</button>
                <button type="button" onclick="submitForm()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 15px;">Confirm & Publish</button>
            </div>
        </div>
    </div>

    <div class="container">
        <?php include 'includes/admin_header.php'; ?>
        <h2>Add New Notice</h2>
        <?php if($msg): ?> 
            
        <?php endif; ?>
        
        <form method="POST" id="notice_form" enctype="multipart/form-data" onsubmit="showPreviewModal(event)">
            <label>Notice Date</label>
            <input type="date" name="notice_date" id="notice_date" value="<?= $today_date ?>" required>

            <label>Notice Type (Optional)</label>
            <select name="type_id" id="type_id" onchange="toggleOtherType()">
                <option value="">-- No Type --</option>
                <?php while($row = $types->fetch_assoc()): ?>
                    <option value="<?= $row['type_id'] ?>"><?= htmlspecialchars($row['type_name']) ?></option>
                <?php endwhile; ?>
                <option value="other" style="font-weight: bold; color: #0056b3;">Other</option>
            </select>

            <div id="other_type_container" style="display: none; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 4px;">
                <label style="color: #0056b3;">New Type</label>
                <input type="text" name="new_type_name" id="new_type_name" placeholder="" style="width: 100%; margin-top: 5px;">
            </div>

            <label>Division/Description</label>
            <select name="division_selection" id="division_selection" onchange="toggleOtherDivision()">
                <option value="">-- No Division / Description --</option>
                <?php while($row = $divisions->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['division_name']) ?>"><?= htmlspecialchars($row['division_name']) ?></option>
                <?php endwhile; ?>
                <option value="other" style="font-weight: bold; color: #0056b3;">Other</option>
            </select>

            <div id="other_division_container" style="display: none; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 4px;">
                <label style="color: #0056b3;">New Division</label>
                <input type="text" name="new_division_name" id="new_division_name" placeholder="Enter division name" style="width: 100%; margin-top: 5px;">
            </div>

            <label>Notice Title</label>
            <input type="text" name="title" placeholder="" required>

            <label>Select File</label>
            <input type="file" name="uploaded_file" id="fake_file_picker" accept=".doc,.docx,.pdf,.csv,.png,.jpg,.jpeg,.gif,.wps,.ppt,.pptx,.xlsx,.xml,.xps" required style="padding: 10px; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; height: 45px;">

            <label>File Address <span style="font-size:13px;color:#666;">(uneditable – change only in Manage Fields)</span></label>
            <input type="text" name="file_address" value="<?= htmlspecialchars($default_address) ?>" readonly required>

            <button type="submit" style="margin-top: 25px;">Submit Notice</button>
        </form>

        <hr style="margin: 40px 0; border: 0; border-top: 2px solid #0056b3;">
        <h3 style="color: #0056b3;">Recently Added (Latest 10)</h3>
        <?php
        $recent_sql = "SELECT d.id, d.title, d.file_address, t.type_name, d.custom_type, d.division_description as division_name 
                       FROM document d LEFT JOIN type t ON d.type_id = t.type_id 
                       ORDER BY d.date DESC, d.time DESC, d.id DESC LIMIT 10";
        $recent_res = $conn->query($recent_sql);
        ?>
        <?php if($recent_res->num_rows > 0): ?>
            <ul class="notice-list">
                <?php while($row = $recent_res->fetch_assoc()): ?>
                    <?php 
                        $display_type = !empty($row['type_name']) ? $row['type_name'] : $row['custom_type']; 
                        if (empty($display_type)) $display_type = "Notice"; // Default fallback if type is empty
                        $display_div = !empty($row['division_name']) ? $row['division_name'] : "N/A";
                        $safe_url = str_replace(' ', '%20', $row['file_address']);
                    ?>
                    <li>
                        <span class="notice-category"><?= htmlspecialchars($display_type) ?></span> | 
                        <span class="notice-division">(<?= htmlspecialchars($display_div) ?>)</span> : 
                        <a href="<?= htmlspecialchars($safe_url) ?>" target="_blank" class="notice-title-link">
                            <?= htmlspecialchars($row['title']) ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color: #777; font-style: italic;">No notices added yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
