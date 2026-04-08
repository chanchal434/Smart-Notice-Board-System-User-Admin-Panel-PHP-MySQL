<?php
require 'includes/admin_auth.php';
require 'includes/admin_db.php';

$msg = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Notice ID.");
}
$notice_id = $_GET['id'];
$padded_id_display = sprintf("%08d", $notice_id); // Used for display and filename consistency

$fetch_stmt = $conn->prepare("SELECT * FROM document WHERE id = ?");
$fetch_stmt->bind_param("i", $notice_id);
$fetch_stmt->execute();
$current_notice = $fetch_stmt->get_result()->fetch_assoc();

if (!$current_notice) die("Notice not found.");

// --- LOGIC TO EXTRACT ORIGINAL FOLDER PATH ---
// We derive the folder path from the record itself instead of the global settings
$current_full_address = $current_notice['file_address'];
$stored_folder_path = dirname($current_full_address); 

if ($stored_folder_path === '.' || $stored_folder_path === '\\') {
    $stored_folder_path = '/'; 
} else {
    // Ensure the path ends with a forward slash for consistency
    $stored_folder_path = rtrim(str_replace('\\', '/', $stored_folder_path), '/') . '/';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type_id = $_POST['type_id'];
    if ($type_id === '') $type_id = NULL; 

    $title = $_POST['title'];
    $date = $_POST['notice_date'] ?? $current_notice['date']; 
    
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

    $base_address = trim($_POST['file_address']); // This is the stored folder path from the hidden/readonly input
    $base_address = str_replace('\\', '/', $base_address);
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
        $file_name = $current_notice['file_name']; 
        $old_physical = __DIR__ . '/' . ltrim($current_notice['file_address'], '/');

        $uploaded_file = $_FILES['uploaded_file'] ?? null;
        $is_new_file = $uploaded_file && $uploaded_file['error'] === UPLOAD_ERR_OK;

        if ($is_new_file) {
            $original_name = $uploaded_file['name'];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $original_basename = pathinfo($original_name, PATHINFO_FILENAME);

            $allowed_exts = ['doc', 'docx', 'pdf', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'wps', 'ppt', 'pptx', 'xlsx', 'xml', 'xps'];
            if (!in_array($ext, $allowed_exts)) {
                $msg = "Error: File type '.$ext' is not allowed. Update cancelled.";
            }
        } else {
            // Extract the original filename base by removing the 8-digit ID and the date suffix
            $without_id = preg_replace('/^\d{8}-/', '', $current_notice['file_name']);
            $original_basename = preg_replace('/-\d{4}-\d{2}-\d{2}(\.[^.]+)?$/', '', $without_id);
            $ext = pathinfo($current_notice['file_name'], PATHINFO_EXTENSION);
        }

        if (empty($msg)) {
            // Re-generate filename using the FIXED original padded ID
            $new_generated_filename = $padded_id_display . '-' . $original_basename . '-' . $date;
            if (!empty($ext)) $new_generated_filename .= '.' . $ext;

            $relative_dir = ltrim($base_address, '/');
            $new_physical_dir = __DIR__ . '/' . $relative_dir;
            
            if (!is_dir($new_physical_dir)) {
                mkdir($new_physical_dir, 0755, true);
            }
            
            $new_physical = $new_physical_dir . $new_generated_filename;
            $full_file_address = $base_address . $new_generated_filename;
            $file_name = $new_generated_filename;

            if ($is_new_file) {
                if (move_uploaded_file($uploaded_file['tmp_name'], $new_physical)) {
                    if (file_exists($old_physical) && $old_physical !== $new_physical) {
                        unlink($old_physical);
                    }
                } else {
                    $msg = "Failed to save the new file on server.";
                }
            } else {
                if ($old_physical !== $new_physical && file_exists($old_physical)) {
                    if (!rename($old_physical, $new_physical)) {
                        $msg = "Failed to move file to the new exact folder path.";
                    }
                }
            }

            if (empty($msg)) {
                $stmt = $conn->prepare("UPDATE document SET title=?, file_name=?, file_address=?, date=?, type_id=?, custom_type=?, division_description=? WHERE id=?");
                $stmt->bind_param("ssssssss", $title, $file_name, $full_file_address, $date, $type_id, $custom_type, $division_description, $notice_id);
                if ($stmt->execute()) {
                    header("Location: admin_delete_notice.php");
                    exit; 
                } else {
                    $msg = "Database error: " . $conn->error;
                }
            }
        }
    }
}

$types = $conn->query("SELECT * FROM type");
$divisions = $conn->query("SELECT * FROM division");
$is_custom_type = !empty($current_notice['custom_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Notice</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        #notice_form > label { margin-top: 22px; margin-bottom: 8px; display: block; font-size: 14.5px; }
        #notice_form > label:first-child { margin-top: 0; }
        #notice_form > select, #notice_form > input[type="text"], #notice_form > input[type="date"], #notice_form > input[type="file"] { width: 100%; margin-bottom: 5px; }
        #notice_form > input[type="date"] { padding: 0 12px; border: 1px solid #ccc; border-radius: 4px; height: 42px; font-size: 14px; box-sizing: border-box; }
    </style>
    <script>
    var currentPaddedId = "<?= $padded_id_display ?>";
    var currentExistingFilename = "<?= htmlspecialchars($current_notice['file_name']) ?>";

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
            otherInput.required = true; 
        } else {
            otherInputContainer.style.display = "none"; 
            otherInput.required = false; 
        }
    }
    
    function getGeneratedFilename(originalName, date, paddedId, isNewFile) {
        if (isNewFile) {
            if (!originalName) return "pending";
            const lastDot = originalName.lastIndexOf('.');
            const basename = lastDot > -1 ? originalName.substring(0, lastDot) : originalName;
            const ext = lastDot > -1 ? originalName.substring(lastDot) : '';
            return paddedId + '-' + basename + '-' + date + ext;
        } else {
            // Keep the same name structure but update the date part
            return originalName.replace(/-\d{4}-\d{2}-\d{2}(?=\.[^.]+$|$)/, '-' + date);
        }
    }

    function showPreviewModal(event) {
        event.preventDefault(); 
        
        var title = document.getElementsByName('title')[0].value;
        var date = document.getElementsByName('notice_date')[0].value;
        var baseAddress = document.getElementsByName('file_address')[0].value;
        
        var fileInput = document.getElementById('fake_file_picker');
        var isNewFileSelected = fileInput.files && fileInput.files.length > 0;
        
        var originalFileName = isNewFileSelected 
            ? fileInput.files[0].name 
            : currentExistingFilename;

        var generatedFileName = getGeneratedFilename(originalFileName, date, currentPaddedId, isNewFileSelected);
        var cleanBase = baseAddress.endsWith('/') ? baseAddress : baseAddress + '/';
        var fullAddress = cleanBase + generatedFileName;

        var divSelect = document.getElementById('division_selection');
        var divisionText = divSelect.value === 'other' 
            ? document.getElementById('new_division_name').value 
            : divSelect.options[divSelect.selectedIndex].text;
        
        var typeSelect = document.getElementById('type_id');
        var typeText = typeSelect.value === 'other' 
            ? document.getElementById('new_type_name').value 
            : typeSelect.options[typeSelect.selectedIndex].text;

        document.getElementById('sum_type').innerText = typeText === '-- No Type --' ? 'N/A' : typeText;
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

    window.onload = function() {
        toggleOtherType();
        toggleOtherDivision();
    };
    </script>
</head>
<body>
    <div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; width: 550px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); text-align: center;">
            <h2 style="color: #0056b3; margin-top: 0;">Confirm Update Details</h2>
            
            <div style="text-align: left; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; border: 1px solid #ced4da;">
                <h4 style="margin-top: 0; margin-bottom: 10px; color: #333;">Data Summary:</h4>
                <p style="margin: 5px 0;"><strong>Notice Type:</strong> <span id="sum_type" style="color:#0056b3;"></span></p>
                <p style="margin: 5px 0;"><strong>Division:</strong> <span id="sum_div" style="color:#0056b3;"></span></p>
                <p style="margin: 5px 0;"><strong>Notice Date:</strong> <span id="sum_date" style="color:#0056b3;"></span></p>
                <p style="margin: 5px 0;"><strong>Notice Title:</strong> <span id="sum_title" style="color:#0056b3;"></span></p>
                <hr style="border: 0; border-top: 1px solid #ccc; margin: 10px 0;">
                <p style="margin: 5px 0;"><strong>Final File Name:</strong> <span id="sum_file" style="color:#d9534f;"></span></p>
                <p style="margin: 5px 0;"><strong>Full Address (stored in DB):</strong> <span id="sum_full_address" style="color:#d9534f; word-break: break-all;"></span></p>
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
                <button type="button" onclick="submitForm()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 15px;">Confirm & Update</button>
            </div>
        </div>
    </div>

    <div class="container">
        <?php include 'includes/admin_header.php'; ?>
        <h2>Update Notice</h2>
        
        <?php if($msg): ?> 
            <div class="msg" style="background: #f8d7da; color: #721c24; border-color: #f5c6cb;">
                <?php echo $msg; ?>
            </div> 
        <?php endif; ?>
        
        <form method="POST" id="notice_form" enctype="multipart/form-data" onsubmit="showPreviewModal(event)">
            <label>Notice Date</label>
            <input type="date" name="notice_date" id="notice_date" value="<?= htmlspecialchars($current_notice['date']) ?>" required>

            <label>Notice Type (Optional)</label>
            <select name="type_id" id="type_id" onchange="toggleOtherType()">
                <option value="" <?= empty($current_notice['type_id']) && empty($current_notice['custom_type']) ? 'selected' : '' ?>>-- No Type --</option>
                <?php while($row = $types->fetch_assoc()): ?>
                    <?php $sel = ($row['type_id'] == $current_notice['type_id']) ? 'selected' : ''; ?>
                    <option value="<?= $row['type_id'] ?>" <?= $sel ?>><?= htmlspecialchars($row['type_name']) ?></option>
                <?php endwhile; ?>
                <option value="other" style="font-weight: bold; color: #0056b3;" <?= $is_custom_type ? 'selected' : '' ?>>Other</option>
            </select>

            <div id="other_type_container" style="display: none; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 4px;">
                <label style="color: #0056b3;">New Type</label>
                <input type="text" name="new_type_name" id="new_type_name" value="<?= htmlspecialchars($current_notice['custom_type'] ?? '') ?>" style="width: 100%; margin-top: 5px;">
            </div>

            <?php 
            $current_div = $current_notice['division_description'];
            $is_standard_div = false;
            $div_res = $conn->query("SELECT * FROM division"); 
            ?>
            <label>Division (Optional)</label>
            <select name="division_selection" id="division_selection" onchange="toggleOtherDivision()">
                <option value="" <?= empty($current_div) ? 'selected' : '' ?>>-- No Division --</option>
                <?php while($row = $div_res->fetch_assoc()): ?>
                    <?php 
                    $sel = ($row['division_name'] == $current_div) ? 'selected' : ''; 
                    if ($sel) $is_standard_div = true;
                    ?>
                    <option value="<?= htmlspecialchars($row['division_name']) ?>" <?= $sel ?>><?= htmlspecialchars($row['division_name']) ?></option>
                <?php endwhile; ?>
                <?php $other_sel = (!$is_standard_div && !empty($current_div)) ? 'selected' : ''; ?>
                <option value="other" style="font-weight: bold; color: #0056b3;" <?= $other_sel ?>>Other</option>
            </select>

            <div id="other_division_container" style="display: <?= $other_sel ? 'block' : 'none' ?>; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 4px;">
                <label style="color: #0056b3;">Specify Other Division</label>
                <input type="text" name="new_division_name" id="new_division_name" value="<?= $other_sel ? htmlspecialchars($current_div) : '' ?>" style="width: 100%; margin-top: 5px;">
            </div>

            <label>Notice Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($current_notice['title']) ?>" required>

            <label>Select New File <span style="font-weight:normal; color:#666;">(Leave blank to keep: <b><?= htmlspecialchars($current_notice['file_name']) ?></b>)</span></label>
            <input type="file" name="uploaded_file" id="fake_file_picker" accept=".doc,.docx,.pdf,.csv,.png,.jpg,.jpeg,.gif,.wps,.ppt,.pptx,.xlsx,.xml,.xps" style="padding: 10px; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; height: 45px;">
            
            <input type="hidden" name="existing_file_name" value="<?= htmlspecialchars($current_notice['file_name']) ?>">

            <label>File Address <span style="font-size:13px;color:#666;">(Folder path used during creation)</span></label>
            <input type="text" name="file_address" value="<?= htmlspecialchars($stored_folder_path) ?>" readonly required>

            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit">Submit Changes</button>
                <a href="admin_delete_notice.php" class="reset-btn" style="text-decoration: none; display: inline-flex; align-items: center;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>