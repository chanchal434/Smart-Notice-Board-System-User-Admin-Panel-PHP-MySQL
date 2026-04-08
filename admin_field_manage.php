<?php
require 'includes/admin_auth.php';
require 'includes/admin_db.php';

$msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_type'])) {
        $stmt = $conn->prepare("INSERT INTO type (type_name) VALUES (?)");
        $stmt->bind_param("s", $_POST['type_name']);
        $stmt->execute();
        $msg = "Type added successfully!";
    } elseif (isset($_POST['add_division'])) {
        $stmt = $conn->prepare("INSERT INTO division (division_name) VALUES (?)");
        $stmt->bind_param("s", $_POST['division_name']);
        $stmt->execute();
        $msg = "Division added successfully!";
    } elseif (isset($_POST['update_address'])) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'default_address'");
        $stmt->bind_param("s", $_POST['default_address']);
        $stmt->execute();
        $msg = "Default address updated successfully!";
    }
}

$res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'default_address'");
$current_address = $res->fetch_assoc()['setting_value'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Field Manage</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        /* Wraps all forms and centers them */
        .settings-wrapper {
            max-width: 400px; /* Keeps them small */
            margin: 0 auto;   /* Centers them on the page */
        }
        
        /* A very simple, clean box for each field */
        .simple-box {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); /* Very subtle shadow */
        }

        /* Standard GREY labels, exactly like your other pages! */
        .simple-box label {
            display: block;
            color: #555; /* The standard grey color */
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 15px;
            text-align: left;
        }

        .simple-box input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* Green buttons to match Add Notice */
        .simple-box button {
            width: 100%;
            padding: 10px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }

        .simple-box button:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/admin_header.php'; ?>
        
        <h2 style="text-align: center; border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 25px;">Field Management</h2>
        
        <?php if($msg): ?> 
            <div class="msg" style="max-width: 400px; margin: 0 auto 20px auto; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; text-align: center;">
                <?php echo $msg; ?>
            </div> 
        <?php endif; ?>

        <div class="settings-wrapper">
            
            <div class="simple-box">
                <form method="POST">
                    <label>New Type</label>
                    <input type="text" name="type_name" placeholder="Type Name" required>
                    <button type="submit" name="add_type">Add Type</button>
                </form>
            </div>

            <div class="simple-box">
                <form method="POST">
                    <label>Division / Description</label>
                    <input type="text" name="division_name" placeholder="Division Name" required>
                    <button type="submit" name="add_division">Add Division</button>
                </form>
            </div>

            <div class="simple-box">
                <form method="POST">
                    <label>Default File Address</label>
                    <input type="text" name="default_address" value="<?= htmlspecialchars($current_address) ?>" placeholder="Base Folder Address" required>
                    <button type="submit" name="update_address">Update Address</button>
                </form>
            </div>

        </div>
    </div>
</body>
</html>