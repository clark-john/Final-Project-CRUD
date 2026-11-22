<?php
include "config.php";

$username = "admin";
$password = password_hash("admin123", PASSWORD_DEFAULT);

$check = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE username = ?"
);
mysqli_stmt_bind_param($check, "s", $username);
mysqli_stmt_execute($check);
$result = mysqli_stmt_get_result($check);

if (mysqli_num_rows($result) > 0) {
    echo "Admin account already exists.";
} else {
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO users (username, password) VALUES (?, ?)"
    );

    mysqli_stmt_bind_param($stmt, "ss", $username, $password);

    if (mysqli_stmt_execute($stmt)) {
        echo "Admin account created successfully!";
    } else {
        echo "Unable to create admin account.";
    }
}
?>
