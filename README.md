# 📌 Smart Notice Board System (User + Admin Panel)

A **web-based Notice Board System** developed using **PHP, MySQL, HTML, CSS, and JavaScript** that enables administrators to manage notices efficiently while allowing users to view updates in real-time through a clean and user-friendly interface.

---

## 🚀 Features

### 👨‍💼 Admin Panel

* 🔐 Secure Login Authentication
* ➕ Add New Notices with File Upload
* ✏️ Update Existing Notices
* ❌ Delete Notices (with automatic file removal)
* 🏷️ Manage Notice Types & Divisions
* 📂 Custom File Storage Paths
* 🔍 Advanced Filtering (Year, Type, Title Search)
* 📅 Automatic ID Generation (YYMMXXXX format)
* 👁️ Preview Before Upload

### 👨‍🎓 User Panel

* 📢 View Latest Notices (Grouped by Month or Year)
* 🔎 Filter by:

  * Type
  * Month
  * Year
  * Keyword Search
* 📂 Access Uploaded Files Directly
* 🌐 Clean and Responsive UI

---

## 🏗️ Project Structure

```
Smart-Notice-Board-System/
│
├── admin/
│   ├── admin_index.php          # Admin Login and starting file
│   ├── admin_dashboard.php      # Redirect to upload page :contentReference[oaicite:0]{index=0}
│   ├── admin_file_upload.php    # Add Notice :contentReference[oaicite:1]{index=1}
│   ├── admin_update_notice.php  # Update Notice :contentReference[oaicite:2]{index=2}
│   ├── admin_delete_notice.php  # Delete Notice :contentReference[oaicite:3]{index=3}
│   ├── admin_field_manage.php   # Manage Types/Divisions :contentReference[oaicite:4]{index=4}
│   ├── admin_logout.php         # Logout :contentReference[oaicite:5]{index=5}
│   └── includes/
│       ├── admin_auth.php       # Session Security :contentReference[oaicite:6]{index=6}
│       ├── admin_db.php         # Database Connection :contentReference[oaicite:7]{index=7}
│       └── admin_header.php     # Navigation Bar :contentReference[oaicite:8]{index=8}
│
├── user/
│   ├── user_index.php           # Recent Notices :contentReference[oaicite:9]{index=9}
│   ├── user_since_2026.php      # Filtered Notices :contentReference[oaicite:10]{index=10}
│   └── includes/
│       ├── user_db.php          # Database Connection :contentReference[oaicite:11]{index=11}
│       └── user_header.php      # UI Header :contentReference[oaicite:12]{index=12}
│
├── css/
│   ├── admin_style.css          # Admin UI Styling :contentReference[oaicite:13]{index=13}
│   └── user_style.css           # User UI Styling :contentReference[oaicite:14]{index=14}
│
└── database/
    └── notice_db.sql            # Database Structure (you create/import)
```

---

## 🧠 How It Works

### 🔄 Notice Flow

1. Admin logs in
2. Uploads notice with file + metadata
3. System:

   * Generates unique ID (YYMMXXXX)
   * Stores file in server directory
   * Saves record in database
4. Users can view/filter/download notices instantly

---

## 🗄️ Database Structure

### Main Tables:

* **users** → Admin authentication
* **document** → Stores notices
* **type** → Notice categories
* **division** → Departments
* **settings** → Default configurations

---

## ⚙️ Installation Guide

### 🔧 Requirements

* XAMPP / WAMP / LAMP Server
* PHP ≥ 7.x
* MySQL
* HTML
* CSS
* JavaScript

---

### 📥 Steps to Run

1. Clone the repository:

```bash
git clone https://github.com/your-username/smart-notice-board.git
```

2. Move project to:

```
htdocs/ (for XAMPP)
```

3. Start:

* Apache
* MySQL

4. Create Database:

```sql
CREATE DATABASE notice_db;
```

5. Import your SQL file (or create tables manually)

6. Configure DB:
   Edit:

```php
admin/includes/admin_db.php
user/includes/user_db.php
```

Default:

```php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "notice_db";
```

---

## 🔐 Default Admin Login

> ⚠️ Stored in database (plaintext in your current system)

```
Username: admin
Password: admin
```

👉 You can change it directly in the `users` table.

---

## 📸 Key Functional Highlights

### 📌 Smart File Handling

* Automatically deletes file when notice is removed
* Generates structured filenames:

```
YYMMXXXX-title-date.ext
```

---

### 🔎 Advanced Filtering System

* Multi-criteria filtering
* Supports:

  * English + Hindi type mapping
  * Keyword search
  * Month & Year selection

---

### 🧩 Dynamic Fields

Admin can:

* Add new **Types**
* Add new **Divisions**
* Use **Custom Entries**

---

## ⚠️ Known Limitations

* Passwords are stored in plain text (⚠️ needs hashing)
* No role-based access (only admin)
* No API integration yet
* No email notifications

---

## 🔒 Future Improvements

* 🔐 Password Hashing (bcrypt)
* 📧 Email Notification System
* 🌐 REST API Integration
* 📊 Analytics Dashboard
* 📱 Mobile Responsive UI Upgrade
* ☁️ Cloud File Storage (AWS / Firebase)

---

## 🛠️ Technologies Used

* **Frontend:** HTML, CSS, JavaScript
* **Backend:** PHP
* **Database:** MySQL
* **Server:** XAMPP

---

## 🤝 Contribution

Contributions are welcome!
Feel free to fork, improve, and submit pull requests.

---

## 📄 License

This project is open-source and available under the **MIT License**.

---

## 👨‍💻 Author

**Chanchal Choudhary** and **Anannay varshney**
B.Tech Student | Web Developer

---

