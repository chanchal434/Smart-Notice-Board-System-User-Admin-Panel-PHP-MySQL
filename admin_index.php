<?php
session_start();
require_once 'includes/admin_db.php'; // Add this to connect to your database!

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Grab the data the user typed into the form
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Check if the username exists in our 'users' database table
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // If we found exactly 1 matching user
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if the typed password matches the database password
        if ($password === $user['password']) {
            // Success! Create the session and send them to the dashboard
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            header("Location: admin_dashboard.php");
            exit;
        } else {
            // Fail! Wrong password
            $error = "Invalid password. Please try again.";
        }
    } else {
        // Fail! Username not found
        $error = "Invalid username or password. Please try again.";
    }
    
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Notice System</title>
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>
    <div class="container" style="max-width: 400px; margin-top: 100px;">
        <h2 style="text-align: center; color: #0056b3;">Admin Login</h2>
        
        <?php if($error): ?> 
            <div class="msg error" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center;">
                <?php echo $error; ?>
            </div> 
        <?php endif; ?>
        
       
            <input type="password" name="password" required placeholder="Enter password">
            
            <button type="submit" style="margin-top: 15px;">Login</button>
        </form>
    </div>
</body>
</html>
