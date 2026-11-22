<?php
date_default_timezone_set('Asia/Manila');
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}
include 'config.php';
$message = '';
// Keep old orders undated; new orders record their creation time below.
$orderDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'created_at'");
if ($orderDateColumn && mysqli_num_rows($orderDateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN created_at DATETIME NULL DEFAULT NULL");
}
$orderPromoColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'service_label'");
if ($orderPromoColumn && mysqli_num_rows($orderPromoColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN service_label VARCHAR(100) NULL DEFAULT NULL");
}
function esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function validPhone($phone) {
    return preg_match('/^09[0-9]{9}$/', $phone) === 1;
}
if (isset($_POST['add_customer'])) {
    $name=trim($_POST['name']??'');
    $phone=trim($_POST['phone']??'');
    if ($name !== '' && validPhone($phone)) {
        $check=mysqli_prepare($conn,"SELECT id FROM customers WHERE name=? AND phone=? LIMIT 1");
        mysqli_stmt_bind_param($check,'ss',$name,$phone);
        mysqli_stmt_execute($check);
        if(mysqli_fetch_assoc(mysqli_stmt_get_result($check))){
            $message='May customer record na gamit ang pangalan at phone number na ito.';
        }
        else{
            $stmt=mysqli_prepare($conn,"INSERT INTO customers (name, phone) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt,'ss',$name,$phone);
            if(mysqli_stmt_execute($stmt)){
                $message='Nagdagdag ng customer. Customer number: #'.mysqli_insert_id($conn);
            }
            else{
                $message='Hindi maidagdag ang customer.';
            }
        }
    } else $message='Ilagay ang valid na 11-digit na phone number na nagsisimula sa 09.';
}
if (isset($_POST['update_customer_phone'])) {
    $customerId=(int)($_POST['customer_id']??0);
    $phone=trim($_POST['phone']??'');
    if($customerId>0 && validPhone($phone)){
        $stmt=mysqli_prepare($conn,"UPDATE customers SET phone=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,'si',$phone,$customerId);
        $message=mysqli_stmt_execute($stmt)?'Na-update ang customer phone number.':'Hindi na-update ang phone number.';
    } else $message='Ilagay ang valid na 11-digit na phone number na nagsisimula sa 09.';
}
if (isset($_POST['save_price'])) {
    $id=(int)($_POST['price_id']??0);
    $rawPrice=trim($_POST['price']??'');
    $price=is_numeric($rawPrice)?(float)$rawPrice:-1;
    if ($price >= 0 && $price <= 100000) {
        $stmt=mysqli_prepare($conn,"UPDATE service_prices SET price=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,'di',$price,$id);
        $message=mysqli_stmt_execute($stmt)?'Na-update ang presyo.':'Hindi na-update ang presyo.';
    }
    else $message='Hindi valid ang presyo.';
}
if (isset($_POST['save_promotion'])) {
    $id=(int)($_POST['promotion_id']??0);
    $name=trim($_POST['name']??'');
    $description=trim($_POST['description']??'');
    $rawPrice=trim($_POST['price']??'');
    $rawMaxWeight=trim($_POST['max_weight']??'');
    $price=is_numeric($rawPrice)?(float)$rawPrice:-1;
    $maxWeight=is_numeric($rawMaxWeight)?(float)$rawMaxWeight:0;
    $active=isset($_POST['active'])?1:0;
    if ($id>0 && $name!=='' && $description!=='' && $price>=0 && $price<=100000 && $maxWeight>0) {
        $stmt=mysqli_prepare($conn,"UPDATE promotions SET name=?, description=?, price=?, max_weight=?, active=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,'siddii',$name,$description,$price,$maxWeight,$active,$id);
        $message=mysqli_stmt_execute($stmt)?'Na-update ang promo.':'Hindi na-update ang promo.';
    } else $message='Hindi valid ang presyo o maximum na timbang ng promo.';
}
if (isset($_POST['add_promotion'])) {
    $name=trim($_POST['name']??'');
    $description=trim($_POST['description']??'');
    $rawPrice=trim($_POST['price']??'');
    $rawMaxWeight=trim($_POST['max_weight']??'');
    $price=is_numeric($rawPrice)?(float)$rawPrice:-1;
    $maxWeight=is_numeric($rawMaxWeight)?(float)$rawMaxWeight:0;
    if ($name!=='' && $description!=='' && $price>=0 && $price<=100000 && $maxWeight>0) {
        $stmt=mysqli_prepare($conn,"INSERT INTO promotions (name, description, price, max_weight, active) VALUES (?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt,'ssdd',$name,$description,$price,$maxWeight);
        $message=mysqli_stmt_execute($stmt)?'Nagdagdag ng promo.':'Hindi naidagdag ang promo. Baka ginagamit na ang pangalan.';
    } else $message='Hindi valid ang presyo o maximum na timbang ng promo.';
}
if (isset($_POST['add_order'])) {
    $cid=(int)($_POST['customer_id']??0);
    $rawWeight=trim($_POST['weight']??'');
    $weight=is_numeric($rawWeight)?(float)$rawWeight:0;
    $priceId=(int)($_POST['price_id']??0);
    $promoId=(int)($_POST['promo_id']??0);
    $serviceLabel='';
    $amount=-1;
    if ($priceId>0 && $promoId===0) {
        $stmt=mysqli_prepare($conn,"SELECT service_name, price FROM service_prices WHERE id=?");
        mysqli_stmt_bind_param($stmt,'i',$priceId);
        mysqli_stmt_execute($stmt);
        $service=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($service) { $amount=round($weight*(float)$service['price'],2); $serviceLabel=$service['service_name']; }
    } elseif ($promoId>0 && $priceId===0) {
        $stmt=mysqli_prepare($conn,"SELECT name, price, max_weight FROM promotions WHERE id=? AND active=1");
        mysqli_stmt_bind_param($stmt,'i',$promoId);
        mysqli_stmt_execute($stmt);
        $promo=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($promo && $weight <= (float)$promo['max_weight']) { $amount=(float)$promo['price']; $serviceLabel=$promo['name']; }
    }
    if ($cid>0 && $weight>0 && $amount>=0 && $serviceLabel!=='') {
        $orderCreatedAt=date('Y-m-d H:i:s');
        $stmt=mysqli_prepare($conn,"INSERT INTO orders (customer_id, weight, amount, created_at, service_label) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt,'iddss',$cid,$weight,$amount,$orderCreatedAt,$serviceLabel);
        $message=mysqli_stmt_execute($stmt)?'Nagawa ang laundry order.':'Hindi nagawa ang order.';
    }
    else $message='Pumili ng customer at isang serbisyo o promo. Siguraduhing pasok sa maximum na timbang ng promo.';
}
if (isset($_POST['update_order'])) {
    $id=(int)($_POST['order_id']??0);
    $status=$_POST['status']??'';
    $payment=$_POST['payment']??'';
    if (in_array($status,['Pending','Processing','Completed'],true) && in_array($payment,['Unpaid','Paid'],true)) {
        $stmt=mysqli_prepare($conn,"UPDATE orders SET status=?, payment=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,'ssi',$status,$payment,$id);
        $message=mysqli_stmt_execute($stmt)?'Na-update ang order.':'Hindi na-update ang order.';
    }
}
$prices=mysqli_query($conn,"SELECT * FROM service_prices ORDER BY id");
$promotions=mysqli_query($conn,"SELECT * FROM promotions ORDER BY id");
$activePromotions=mysqli_query($conn,"SELECT * FROM promotions WHERE active=1 ORDER BY id");
$customers=mysqli_query($conn,"SELECT * FROM customers ORDER BY name");
$customerList=mysqli_query($conn,"SELECT id, name, phone FROM customers ORDER BY name");
$orderListDate=$_GET['order_date']??date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$orderListDate) || !checkdate((int)substr($orderListDate,5,2),(int)substr($orderListDate,8,2),(int)substr($orderListDate,0,4))) {
    $orderListDate=date('Y-m-d');
}
$orderListStart=$orderListDate.' 00:00:00';
$orderListEnd=date('Y-m-d',strtotime($orderListDate.' +1 day')).' 00:00:00';
$orderStmt=mysqli_prepare($conn,"SELECT orders.*, customers.name FROM orders JOIN customers ON orders.customer_id=customers.id WHERE orders.created_at>=? AND orders.created_at<? ORDER BY orders.id DESC");
mysqli_stmt_bind_param($orderStmt,'ss',$orderListStart,$orderListEnd);
mysqli_stmt_execute($orderStmt);
$orders=mysqli_stmt_get_result($orderStmt);
$counts=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM customers"));
$totalOrders=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM orders"));
$completed=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM orders WHERE status='Completed'"));
$reportDate=$_GET['report_date']??date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportDate) || !checkdate((int)substr($reportDate,5,2),(int)substr($reportDate,8,2),(int)substr($reportDate,0,4))) {
    $reportDate=date('Y-m-d');
}
$reportStart=$reportDate.' 00:00:00';
$reportEnd=date('Y-m-d',strtotime($reportDate.' +1 day')).' 00:00:00';
$reportStmt=mysqli_prepare($conn,"SELECT COUNT(*) order_count, COALESCE(SUM(amount),0) order_total, COALESCE(SUM(CASE WHEN payment='Paid' THEN amount ELSE 0 END),0) paid_total, COALESCE(SUM(CASE WHEN payment<>'Paid' THEN amount ELSE 0 END),0) unpaid_total FROM orders WHERE created_at>=? AND created_at<?");
mysqli_stmt_bind_param($reportStmt,'ss',$reportStart,$reportEnd);
mysqli_stmt_execute($reportStmt);
$salesSummary=mysqli_fetch_assoc(mysqli_stmt_get_result($reportStmt));
$salesStmt=mysqli_prepare($conn,"SELECT orders.id, customers.name, orders.weight, orders.amount, orders.status, orders.payment, orders.service_label FROM orders JOIN customers ON orders.customer_id=customers.id WHERE orders.created_at>=? AND orders.created_at<? ORDER BY orders.id DESC");
mysqli_stmt_bind_param($salesStmt,'ss',$reportStart,$reportEnd);
mysqli_stmt_execute($salesStmt);
$salesOrders=mysqli_stmt_get_result($salesStmt);
?>
<!DOCTYPE html>
<html lang="tl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>
            Admin - Mak-A-Tipid
        </title>
        <style>
* {
    box-sizing:border-box
}

body {
    font:15px Arial,sans-serif;
    background:#f3f6fb;
    margin:0;
    color:#263445
}

.header {
    background:#174a7e;
    color:white;
    padding:22px max(20px,calc((100% - 1120px)/2));
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px
}

.header h1 {
    margin:0 0 6px
}

.header p {
    margin:0;
    color:#e0ecf8
}

.logout {
    color:#174a7e;
    background:white;
    padding:10px 14px;
    border-radius:6px;
    text-decoration:none;
    font-weight:bold
}

.container {
    max-width:1160px;
    margin:auto;
    padding:24px
}

.box {
    background:white;
    padding:22px;
    margin-bottom:20px;
    border-radius:12px;
    box-shadow:0 3px 12px #00000009
}

h2 {
    color:#174a7e;
    margin:0 0 16px;
    font-size:20px
}

.cards {
    display:flex;
    gap:14px;
    flex-wrap:wrap
}

.card {
    flex:1;
    min-width:150px;
    padding:18px;
    background:#eaf2fb;
    border-radius:9px
}

.card:nth-child(2) {
    background:#e4f6ed
}

.card:nth-child(3) {
    background:#fff1d6
}

.card strong {
    display:block;
    font-size:27px;
    margin-top:8px
}

input,select,button {
    padding:10px;
    margin:4px;
    border:1px solid #ccd6e0;
    border-radius:6px;
    font-size:14px
}

button {
    background:#174a7e;
    color:white;
    border:0;
    cursor:pointer;
    font-weight:bold
}

.table-wrap {
    overflow:auto
}

.customer-records-wrap {
    max-height:360px;
    overflow:auto;
    border:1px solid #e4eaf0;
    border-radius:7px
}

.customer-records-wrap th {
    position:sticky;
    top:0;
    z-index:1
}

.admin-nav {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin:0 0 18px
}

.admin-nav a {
    display:inline-block;
    padding:9px 13px;
    border-radius:7px;
    background:#eaf2fb;
    color:#174a7e;
    text-decoration:none;
    font-weight:bold
}

.admin-nav a:hover {
    background:#d8e8f8
}

.orders-wrap {
    max-height:420px;
    overflow:auto;
    border:1px solid #e4eaf0;
    border-radius:7px
}

.orders-wrap th {
    position:sticky;
    top:0;
    z-index:1
}

section.box {
    scroll-margin-top:16px
}

body.sales-view .header,
body.sales-view .msg {
    display:none
}

body.sales-view .container {
    padding-top:24px
}

.report-date-print {
    display:none
}

@media print {
    body * {
        visibility:hidden !important
    }
    #sales-report, #sales-report * {
        visibility:visible !important
    }
    #sales-report {
        position:absolute;
        inset:0;
        width:100%;
        margin:0;
        padding:0;
        box-shadow:none;
        border:0
    }
    #sales-report .report-controls,
    #sales-report .orders-wrap {
        overflow:visible;
        max-height:none;
        border:0
    }
    #sales-report .report-controls label,
    #sales-report .report-controls input,
    #sales-report .report-controls button {
        display:none
    }
    #sales-report .report-date-print {
        display:block;
        margin:0 0 16px
    }
    #sales-report table {
        border:1px solid #ccd6e0
    }
}

table {
    width:100%;
    border-collapse:collapse
}

th,td {
    text-align:left;
    padding:11px 9px;
    border-bottom:1px solid #e4eaf0
}

th {
    background:#eaf2fb;
    color:#174a7e
}

.msg {
    background:#e1f5e8;
    padding:12px;
    margin-bottom:16px;
    border-radius:7px
}

.price-row {
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
    border-bottom:1px solid #e4eaf0;
    padding:9px 0
}

.price-row input[type=number] {
    width:140px
}

@media(max-width:600px) {
    .container {
        padding:14px
    }
    .box {
        padding:15px
    }
    .header {
        padding:18px;
        align-items:flex-start
    }
    .header p {
        font-size:13px
    }
    input,select {
        max-width:100%
    }
}
</style>
    </head>
    <body>
        <header class="header">
            <div>
                <h1>
                    Mak-A-Tipid Laundromat
                </h1>
                <p>
                    Admin dashboard · Welcome,
                    <?php echo esc($_SESSION['username']); ?>
                </p>
            </div>
            <a class="logout" href="logout.php">
                Log Out
            </a>
        </header>
        <main class="container">
            <nav class="admin-nav" aria-label="Admin sections">
                <a href="#overview" data-view="overview">Overview</a>
                <a href="#orders" data-view="orders">Orders</a>
                <a href="#customers" data-view="customers">Customers</a>
                <a href="#prices" data-view="prices">Prices</a>
                <a href="#sales-report" data-view="sales">Sales Report</a>
            </nav>
            <?php
if($message!=='') echo '<div class="msg">'.esc($message).'</div>';
?>
            <section class="box" id="overview" data-panel="overview">
                <h2>
                    Dashboard Overview
                </h2>
                <div class="cards">
                    <div class="card">
                        Total Customers
                        <strong>
                            <?php echo (int)$counts['total']; ?>
                        </strong>
                    </div>
                    <div class="card">
                        Total Orders
                        <strong>
                            <?php echo (int)$totalOrders['total']; ?>
                        </strong>
                    </div>
                    <div class="card">
                        Completed Orders
                        <strong>
                            <?php echo (int)$completed['total']; ?>
                        </strong>
                    </div>
                </div>
            </section>
            <section class="box" id="sales-report" data-panel="sales">
                <h2>Sales Report</h2>
                <p class="report-date-print">Report date: <?php echo date('F j, Y', strtotime($reportDate)); ?></p>
                <form method="get" class="price-row report-controls">
                    <label for="report-date">Set Date</label>
                    <input type="date" id="report-date" name="report_date" value="<?php echo esc($reportDate); ?>" required>
                    <button type="submit">Show Report</button>
                    <button type="button" onclick="window.print()">Print Report</button>
                </form>
                <div class="cards">
                    <div class="card">Orders<strong><?php echo (int)$salesSummary['order_count']; ?></strong></div>
                    <div class="card">Order Total<strong>₱<?php echo number_format((float)$salesSummary['order_total'],2); ?></strong></div>
                    <div class="card">Paid Sales<strong>₱<?php echo number_format((float)$salesSummary['paid_total'],2); ?></strong></div>
                    <div class="card">Unpaid<strong>₱<?php echo number_format((float)$salesSummary['unpaid_total'],2); ?></strong></div>
                </div>
                <div class="table-wrap orders-wrap">
                    <table>
                        <tr><th>Order</th><th>Customer</th><th>Service / Promo</th><th>Weight</th><th>Amount</th><th>Status</th><th>Payment</th></tr>
                        <?php while($sale=mysqli_fetch_assoc($salesOrders)): ?>
                        <tr>
                            <td><?php echo (int)$sale['id']; ?></td>
                            <td><?php echo esc($sale['name']); ?></td>
                            <td><?php echo esc($sale['service_label']??''); ?></td>
                            <td><?php echo esc($sale['weight']); ?> kg</td>
                            <td>₱<?php echo number_format((float)$sale['amount'],2); ?></td>
                            <td><?php echo esc($sale['status']); ?></td>
                            <td><?php echo esc($sale['payment']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </section>
            <section class="box" id="prices" data-panel="prices">
                <h2>
                    Customer Price List
                </h2>
                <p>
                    Makikita rin ng customers ang mga presyong ito. Kinukuwenta ang bagong order gamit ang napiling serbisyo.
                </p>
                <?php while($price=mysqli_fetch_assoc($prices)): ?>
                <form method="post" class="price-row">
                    <strong>
                        <?php echo esc($price['service_name']); ?>
                        (per
                        <?php echo esc($price['unit']); ?>
                        )
                    </strong>
                    <label>
                        ₱
                        <input type="number" name="price" min="0" max="100000" step="0.01" value="<?php echo esc($price['price']); ?>
                        " required>
                    </label>
                    <input type="hidden" name="price_id" value="<?php echo (int)$price['id']; ?>
                    ">
                    <button name="save_price">
                        Save Price
                    </button>
                </form>
                <?php endwhile; ?>
                <h3>Promotions</h3>
                <p>These promos appear on the customer page and can be selected when creating an order.</p>
                <?php while($promo=mysqli_fetch_assoc($promotions)): ?>
                <form method="post" class="price-row">
                    <input type="hidden" name="promotion_id" value="<?php echo (int)$promo['id']; ?>">
                    <input name="name" value="<?php echo esc($promo['name']); ?>" maxlength="100" placeholder="Promo name" required>
                    <input name="description" value="<?php echo esc($promo['description']); ?>" placeholder="Promo details" required>
                    <label>₱ <input type="number" name="price" min="0" max="100000" step="0.01" value="<?php echo esc($promo['price']); ?>" required></label>
                    <label>Up to <input type="number" name="max_weight" min="0.01" step="0.01" value="<?php echo esc($promo['max_weight']); ?>" required> kg</label>
                    <label><input type="checkbox" name="active" value="1" <?php echo $promo['active']?'checked':''; ?>> Active</label>
                    <button name="save_promotion">Save Promo</button>
                </form>
                <?php endwhile; ?>
                <h3>Add Promotion</h3>
                <form method="post" class="price-row">
                    <input name="name" maxlength="100" placeholder="Promo name" required>
                    <input name="description" placeholder="Promo details" required>
                    <label>₱ <input type="number" name="price" min="0" max="100000" step="0.01" placeholder="Price" required></label>
                    <label>Up to <input type="number" name="max_weight" min="0.01" step="0.01" placeholder="kg" required> kg</label>
                    <button name="add_promotion">Add Promo</button>
                </form>
            </section>
            <section class="box" id="customers" data-panel="customers">
                <h2>
                    Add Customer
                </h2>
                <form method="post">
                    <input name="name" placeholder="Customer name" maxlength="100" required>
                    <input name="phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" placeholder="09XXXXXXXXX" required>
                    <button name="add_customer">
                        Add Customer
                    </button>
                </form>
            </section>
            <section class="box" data-panel="customers">
                <h2>
                    Customer Records
                </h2>
                <p>
                    I-update rito ang phone number ng existing customers para makapag-login sila. Customer login uses name and phone number.
                </p>
                <div class="table-wrap customer-records-wrap">
                    <table>
                        <tr>
                            <th>
                                Customer No.
                            </th>
                            <th>
                                Name
                            </th>
                            <th>
                                Phone Number
                            </th>
                        </tr>
                        <?php while($c=mysqli_fetch_assoc($customerList)): ?>
                        <tr>
                            <td>
                                #
                                <?php echo (int)$c['id']; ?>
                            </td>
                            <td>
                                <?php echo esc($c['name']); ?>
                            </td>
                            <td>
                                <form method="post" class="customer-phone-form">
                                    <input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>">
                                    <input type="tel" name="phone" value="<?php echo esc($c['phone']); ?>" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" required readonly>
                                    <button type="button" class="edit-phone">Edit</button>
                                    <button type="submit" name="update_customer_phone" disabled>Save Phone</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </section>
            <script>
                document.querySelectorAll('.customer-phone-form').forEach(function (form) {
                    const phone = form.querySelector('input[name="phone"]');
                    const editButton = form.querySelector('.edit-phone');
                    const saveButton = form.querySelector('button[name="update_customer_phone"]');
                    editButton.addEventListener('click', function () {
                        phone.readOnly = false;
                        saveButton.disabled = false;
                        phone.focus();
                    });
                });
            </script>
            <section class="box" id="orders" data-panel="orders">
                <h2>
                    Create Laundry Order
                </h2>
                <form method="post" id="order-form">
                    <input type="search" id="customer-search" placeholder="Search customer by name or phone" autocomplete="off">
                    <select name="customer_id" id="order-customer" required>
                        <option value="">
                            Select customer
                        </option>
                        <?php while($c=mysqli_fetch_assoc($customers)): ?>
                        <option value="<?php echo (int)$c['id']; ?>">
                            <?php echo esc('#'.(int)$c['id'].' — '.$c['name'].' ('.$c['phone'].')'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <select name="price_id" id="order-service">
                        <option value="">
                            Select service
                        </option>
                        <?php
mysqli_data_seek($prices,0);
while($p=mysqli_fetch_assoc($prices)):
?>
                        <option value="<?php echo (int)$p['id']; ?>" data-unit-price="<?php echo esc($p['price']); ?>">
                            <?php echo esc($p['service_name']); ?>
                            — ₱
                            <?php echo number_format((float)$p['price'],2); ?>
                            /
                            <?php echo esc($p['unit']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <select name="promo_id" id="order-promo">
                        <option value="">Select promo (optional)</option>
                        <?php while($promo=mysqli_fetch_assoc($activePromotions)): ?>
                        <option value="<?php echo (int)$promo['id']; ?>" data-promo-price="<?php echo esc($promo['price']); ?>" data-max-weight="<?php echo esc($promo['max_weight']); ?>">
                            <?php echo esc($promo['name'].' — ₱'.number_format((float)$promo['price'],2).' up to '.$promo['max_weight'].' kg'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <input type="number" id="order-weight" name="weight" step="0.01" min="0.01" placeholder="Weight (kg)" required>
                    <p id="order-total" aria-live="polite" style="margin: 0; font-weight: 700; color: #194f82;">Pumili ng serbisyo o promo at ilagay ang timbang para makita ang total.</p>
                    <button type="submit" name="add_order" id="add-order-button">
                        Add Order
                    </button>
                </form>
            </section>
            <script>
                (function () {
                    const search = document.getElementById('customer-search');
                    const customerSelect = document.getElementById('order-customer');
                    search.addEventListener('input', function () {
                        const query = search.value.trim().toLocaleLowerCase();
                        Array.from(customerSelect.options).forEach(function (option, index) {
                            if (index === 0) return;
                            option.hidden = !option.textContent.toLocaleLowerCase().includes(query);
                        });
                        if (customerSelect.selectedOptions[0]?.hidden) customerSelect.value = '';
                    });
                })();
                (function () {
                    const service = document.getElementById('order-service');
                    const promo = document.getElementById('order-promo');
                    const weight = document.getElementById('order-weight');
                    const total = document.getElementById('order-total');
                    const addButton = document.getElementById('add-order-button');
                    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

                    function updateTotal() {
                        const kilos = Number(weight.value);
                        const hasWeight = Number.isFinite(kilos) && kilos > 0;
                        const serviceOption = service.selectedOptions[0];
                        const promoOption = promo.selectedOptions[0];

                        if (promo.value && promoOption) {
                            const maxWeight = Number(promoOption.dataset.maxWeight);
                            if (!hasWeight) {
                                total.textContent = 'Ilagay ang timbang para makita ang total.';
                                addButton.disabled = true;
                            } else if (kilos > maxWeight) {
                                total.textContent = 'Lampas sa maximum na ' + maxWeight + ' kg ang timbang para sa promo na ito.';
                                addButton.disabled = true;
                            } else {
                                total.textContent = 'Total na babayaran: ' + money.format(Number(promoOption.dataset.promoPrice));
                                addButton.disabled = false;
                            }
                        } else if (service.value && serviceOption) {
                            if (hasWeight) {
                                const amount = Math.round(kilos * Number(serviceOption.dataset.unitPrice) * 100) / 100;
                                total.textContent = 'Total na babayaran: ' + money.format(amount);
                                addButton.disabled = false;
                            } else {
                                total.textContent = 'Ilagay ang timbang para makita ang total.';
                                addButton.disabled = true;
                            }
                        } else {
                            total.textContent = 'Pumili ng serbisyo o promo at ilagay ang timbang para makita ang total.';
                            addButton.disabled = true;
                        }
                    }

                    service.addEventListener('change', function () {
                        if (service.value) promo.value = '';
                        updateTotal();
                    });
                    promo.addEventListener('change', function () {
                        if (promo.value) service.value = '';
                        updateTotal();
                    });
                    weight.addEventListener('input', updateTotal);
                    updateTotal();
                })();
            </script>
            <section class="box" data-panel="orders">
                <h2>
                    Laundry Orders and Transactions
                </h2>
                <form method="get" class="price-row">
                    <label for="orders-date">Set Date</label>
                    <input type="date" id="orders-date" name="order_date" value="<?php echo esc($orderListDate); ?>" required>
                    <button type="submit">Show Orders</button>
                </form>
                <div class="table-wrap orders-wrap">
                    <table>
                        <tr>
                            <th>
                                Order
                            </th>
                            <th>
                                Customer
                            </th>
                                <th>
                                    Service / Promo
                                </th>
                                <th>
                                    Weight
                            </th>
                            <th>
                                Amount
                            </th>
                            <th>
                                Status
                            </th>
                            <th>
                                Payment
                            </th>
                            <th>
                                Update
                            </th>
                        </tr>
                        <?php while($o=mysqli_fetch_assoc($orders)): ?>
                        <tr>
                            <td>
                                <?php echo (int)$o['id']; ?>
                            </td>
                            <td>
                                <?php echo esc($o['name']); ?>
                            </td>
                            <td><?php echo esc($o['service_label']??''); ?></td>
                            <td>
                                <?php echo esc($o['weight']); ?>
                                kg
                            </td>
                            <td>
                                ₱
                                <?php echo number_format((float)$o['amount'],2); ?>
                            </td>
                            <td>
                                <?php echo esc($o['status']); ?>
                            </td>
                            <td>
                                <?php echo esc($o['payment']); ?>
                            </td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>
                                    ">
                                    <select name="status">
                                        <?php
foreach(['Pending','Processing','Completed'] as $s):
?>
                                        <option <?php echo $o['status']===$s?'selected':''; ?>
                                            >
                                            <?php echo $s; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="payment">
                                        <?php
foreach(['Unpaid','Paid'] as $p):
?>
                                        <option <?php echo $o['payment']===$p?'selected':''; ?>
                                            >
                                            <?php echo $p; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button name="update_order">
                                        Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </section>
        </main>
        <script>
            (function () {
                const tabs = document.querySelectorAll('.admin-nav [data-view]');
                const panels = document.querySelectorAll('[data-panel]');
                function showView(view) {
                    document.body.classList.toggle('sales-view', view === 'sales');
                    panels.forEach(function (panel) {
                        panel.hidden = panel.dataset.panel !== view;
                    });
                    tabs.forEach(function (tab) {
                        if (tab.dataset.view === view) tab.setAttribute('aria-current', 'page');
                        else tab.removeAttribute('aria-current');
                    });
                }
                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function (event) {
                        event.preventDefault();
                        showView(tab.dataset.view);
                        history.replaceState(null, '', tab.getAttribute('href'));
                    });
                });
                const query = new URLSearchParams(location.search);
                const initialView = location.hash.slice(1) || (query.has('report_date') ? 'sales-report' : (query.has('order_date') ? 'orders' : ''));
                showView(['overview', 'orders', 'customers', 'prices', 'sales-report'].includes(initialView) ? (initialView === 'sales-report' ? 'sales' : initialView) : 'overview');
            })();
        </script>
    </body>
</html>
