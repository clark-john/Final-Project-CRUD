<?php
session_start();
include "config.php";
if (isset($_SESSION['role'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'index.php' : 'customer.php'));
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['role'] ?? '') === 'customer') {
        $name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $action = $_POST['customer_action'] ?? 'login';
        if ($name === '' || mb_strlen($name) > 100 || !preg_match('/^09[0-9]{9}$/', $phone)) {
            $error = 'Ilagay ang valid na pangalan at 11-digit na phone number na nagsisimula sa 09.';
        }
        elseif ($action === 'register') {
            $stmt = mysqli_prepare($conn, "SELECT id FROM customers WHERE name = ? AND phone = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ss', $name, $phone);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($exists) {
                $error = 'May account na gamit ang pangalan at phone number na ito. Piliin ang Customer Login.';
            }
            else {
                $stmt = mysqli_prepare($conn, "INSERT INTO customers (name, phone) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, 'ss', $name, $phone);
                if (mysqli_stmt_execute($stmt)) {
                    session_regenerate_id(true);
                    $_SESSION['role'] = 'customer';
                    $_SESSION['customer_id'] = (int)mysqli_insert_id($conn);
                    $_SESSION['username'] = $name;
                    header('Location: customer.php');
                    exit;
                }
                $error = 'Hindi nagtagumpay ang registration. Subukan ulit.';
            }
        }
        else {
            $stmt = mysqli_prepare($conn, "SELECT id, name FROM customers WHERE name = ? AND phone = ? ORDER BY id LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ss', $name, $phone);
            mysqli_stmt_execute($stmt);
            $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($customer) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'customer';
                $_SESSION['customer_id'] = (int)$customer['id'];
                $_SESSION['username'] = $customer['name'];
                header('Location: customer.php');
                exit;
            }
            $error = 'Hindi nagtugma ang pangalan at phone number. Kung bago ka, piliin ang Register.';
        }
    }
    else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = mysqli_prepare($conn, "SELECT id, username, password FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['role'] = 'admin';
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php');
            exit;
        }
        $error = 'Mali ang username o password.';
    }
}
?>
<!DOCTYPE html>
<html lang="tl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>
            Login - Mak-A-Tipid
        </title>
        <style>
* {
    box-sizing:border-box
}

body {
    font-family:Arial,sans-serif;
    background:#f3f6fb;
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#263445;
    padding:24px
}

.page {
    width:min(100%,920px)
}

.brand {
    text-align:center;
    margin-bottom:24px
}

.brand h1 {
    color:#174a7e;
    margin:0 0 8px
}

.brand p {
    color:#64748b;
    margin:0
}

.error {
    background:#fde8e8;
    color:#a12626;
    padding:12px;
    border-radius:7px;
    margin-bottom:16px
}

.panels {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px
}

.panel {
    background:#fff;
    padding:30px;
    border-radius:14px;
    box-shadow:0 4px 16px #0000000d
}

.panel.admin {
    border-top:5px solid #174a7e
}

.panel.customer {
    border-top:5px solid #2c8a61
}

.panel h2 {
    margin:0 0 8px;
    color:#174a7e
}

.panel.customer h2 {
    color:#24744f
}

.desc {
    margin:0 0 18px;
    color:#64748b;
    min-height:36px
}

label {
    display:block;
    margin:15px 0 6px;
    font-weight:bold
}

input {
    width:100%;
    padding:12px;
    border:1px solid #ccd6e0;
    border-radius:6px;
    font-size:15px
}

button {
    width:100%;
    padding:12px;
    margin-top:20px;
    background:#174a7e;
    color:#fff;
    border:0;
    border-radius:6px;
    font-weight:bold;
    font-size:15px;
    cursor:pointer
}

.customer button {
    background:#24744f
}

button:hover {
    filter:brightness(.92)
}

@media(max-width:650px) {
    body {
        padding:16px
    }
    .panels {
        grid-template-columns:1fr
    }
    .panel {
        padding:23px
    }
    .desc {
        min-height:0
    }
}
</style>
    </head>
    <body>
        <div class="page">
            <div class="brand">
                <h1>
                    Mak-A-Tipid
                </h1>
                <p>
                    Laundromat Management System
                </p>
            </div>
            <?php
if($error!=='') echo '<div class="error">'.htmlspecialchars($error, ENT_QUOTES, 'UTF-8').'</div>';
?>
            <div class="panels">
                <section class="panel admin">
                    <h2>
                        Admin Login
                    </h2>
                    <p class="desc">
                        Mag-login gamit ang admin username at password.
                    </p>
                    <form method="post">
                        <input type="hidden" name="role" value="admin">
                        <label for="username">
                            Username
                        </label>
                        <input id="username" name="username" autocomplete="username" required>
                        <label for="password">
                            Password
                        </label>
                        <input id="password" type="password" name="password" autocomplete="current-password" required>
                        <button type="submit">
                            Login as Admin
                        </button>
                    </form>
                </section>
                <section class="panel customer">
                    <h2>
                        Customer
                    </h2>
                    <p class="desc">
                        Pangalan at phone number ang gagamitin para mahanap ang tamang customer record.
                    </p>
                    <form method="post">
                        <input type="hidden" name="role" value="customer">
                        <input type="hidden" name="customer_action" value="login">
                        <label for="customer_name">
                            Pangalan
                        </label>
                        <input id="customer_name" name="customer_name" maxlength="100" placeholder="Buong pangalan mo" required>
                        <label for="customer_phone">
                            Phone Number
                        </label>
                        <input id="customer_phone" name="customer_phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" placeholder="09XXXXXXXXX" autocomplete="tel" required>
                        <button type="submit">
                            Customer Login
                        </button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="role" value="customer">
                        <input type="hidden" name="customer_action" value="register">
                        <label for="new_customer_name">
                            First time? Register
                        </label>
                        <input id="new_customer_name" name="customer_name" maxlength="100" placeholder="Buong pangalan mo" required>
                        <label for="new_customer_phone">
                            Phone Number
                        </label>
                        <input id="new_customer_phone" name="customer_phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" placeholder="09XXXXXXXXX" autocomplete="tel" required>
                        <button type="submit">
                            Register as Customer
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </body>
</html>
