<?php
date_default_timezone_set('Asia/Manila');
session_start();
if (($_SESSION['role'] ?? '') !== 'customer' || empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}
include 'config.php';
function esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
$stmt=mysqli_prepare($conn,"SELECT id, name, phone FROM customers WHERE id=?");
mysqli_stmt_bind_param($stmt,'i',$_SESSION['customer_id']);
mysqli_stmt_execute($stmt);
$customer=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$customer) {
    $_SESSION=[];
    session_destroy();
    header('Location: login.php');
    exit;
}
$customerId=(int)$customer['id'];

// Keep the columns used by both the customer and admin order lists available.
foreach ([
    'created_at' => "ALTER TABLE orders ADD COLUMN created_at DATETIME NULL DEFAULT NULL",
    'service_label' => "ALTER TABLE orders ADD COLUMN service_label VARCHAR(100) NULL DEFAULT NULL",
] as $column=>$alterSql) {
    $columnResult=mysqli_query($conn,"SHOW COLUMNS FROM orders LIKE '$column'");
    if ($columnResult && mysqli_num_rows($columnResult)===0) mysqli_query($conn,$alterSql);
}

$orderMessage='';
$orderError=false;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['place_order'])) {
    $rawWeight=trim($_POST['weight']??'');
    $weight=is_numeric($rawWeight)?(float)$rawWeight:0;
    $priceId=(int)($_POST['price_id']??0);
    $promoId=(int)($_POST['promo_id']??0);
    $label='';
    $amount=-1;
    $oneChoice=($priceId>0 && $promoId===0) || ($promoId>0 && $priceId===0);
    if ($weight>0 && $weight<=1000 && $oneChoice) {
        if ($priceId>0) {
            $q=mysqli_prepare($conn,"SELECT service_name,price FROM service_prices WHERE id=?");
            if ($q) {
                mysqli_stmt_bind_param($q,'i',$priceId);
                mysqli_stmt_execute($q);
                $service=mysqli_fetch_assoc(mysqli_stmt_get_result($q));
                if ($service) { $label=$service['service_name']; $amount=round($weight*(float)$service['price'],2); }
            }
        } else {
            $q=mysqli_prepare($conn,"SELECT name,price,max_weight FROM promotions WHERE id=? AND active=1");
            if ($q) {
                mysqli_stmt_bind_param($q,'i',$promoId);
                mysqli_stmt_execute($q);
                $promo=mysqli_fetch_assoc(mysqli_stmt_get_result($q));
                if ($promo && $weight<=(float)$promo['max_weight']) { $label=$promo['name']; $amount=(float)$promo['price']; }
            }
        }
    }
    if ($amount>=0 && $label!=='') {
        $createdAt=date('Y-m-d H:i:s');
        $q=mysqli_prepare($conn,"INSERT INTO orders (customer_id,weight,amount,status,payment,created_at,service_label) VALUES (?,?,?,'Pending','Unpaid',?,?)");
        if ($q) {
            mysqli_stmt_bind_param($q,'iddss',$customerId,$weight,$amount,$createdAt,$label);
            if (mysqli_stmt_execute($q)) { header('Location: customer.php?order=placed'); exit; }
            $orderMessage='Hindi na-save ang order: '.mysqli_stmt_error($q);
        } else $orderMessage='Hindi naihanda ang order: '.mysqli_error($conn);
    } else $orderMessage='Pumili ng isang serbisyo o promo at timbang na pasok sa limit ng promo.';
    $orderError=true;
}
if (($_GET['order']??'')==='placed') $orderMessage='Na-save ang order. Makikita ito sa admin listahan.';

$prices=mysqli_query($conn,"SELECT id,service_name,price,unit FROM service_prices ORDER BY id");
$promotions=mysqli_query($conn,"SELECT id,name,description,price,max_weight FROM promotions WHERE active=1 ORDER BY id");
$todayStart=date('Y-m-d 00:00:00');
$tomorrowStart=date('Y-m-d 00:00:00',strtotime('tomorrow'));
$stmt=mysqli_prepare($conn,"SELECT id, weight, amount, status, payment, service_label FROM orders WHERE customer_id=? AND created_at>=? AND created_at<? ORDER BY id DESC");
mysqli_stmt_bind_param($stmt,'iss',$customerId,$todayStart,$tomorrowStart);
mysqli_stmt_execute($stmt);
$orders=mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="tl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>
            Customer - Mak-A-Tipid
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
    padding:22px max(20px,calc((100% - 1000px)/2));
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px
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
    max-width:1000px;
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
    margin:0 0 14px
}

.table-wrap {
    overflow:auto
}

table {
    width:100%;
    border-collapse:collapse
}

th,td {
    text-align:left;
    padding:12px 9px;
    border-bottom:1px solid #e4eaf0
}

th {
    background:#eaf2fb;
    color:#174a7e
}

.price {
    padding:13px 0;
    border-bottom:1px solid #e4eaf0;
    display:flex;
    justify-content:space-between
}

.price:last-child {
    border-bottom:0
}

.promo-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:14px
}

.promo-card {
    border:2px solid #174a7e;
    border-radius:12px;
    padding:18px;
    background:linear-gradient(145deg,#f2f7ff,#fff)
}

.promo-card h3 {
    color:#174a7e;
    margin:0 0 8px
}

.promo-price {
    display:block;
    color:#174a7e;
    font-size:30px;
    margin:10px 0
}

label { display:block; font-weight:bold; margin:14px 0 6px }
select,input { width:100%; max-width:520px; padding:12px; border:1px solid #cbd7e5; border-radius:6px; font:inherit }
button { margin-top:18px; padding:12px 18px; border:0; border-radius:6px; background:#20764e; color:white; font:inherit; font-weight:bold; cursor:pointer }
.message { padding:12px; margin-bottom:16px; border-radius:6px; background:#edf7f0; border:1px solid #b9dfc5 }
.message.error { background:#fff1f0; border-color:#efb7b2 }

@media(max-width:600px) {
    .header,.container {
        padding:16px
    }
    .box {
        padding:15px
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
                    Welcome,
                    <?php echo esc($customer['name']); ?>
                    ! · Phone:
                    <?php echo esc($customer['phone']); ?>
                </p>
            </div>
            <a class="logout" href="logout.php">
                Log Out
            </a>
        </header>
        <main class="container">
            <section class="box">
                <h2>
                    Service Prices
                </h2>
                <?php while($p=mysqli_fetch_assoc($prices)): ?>
                <div class="price">
                    <span>
                        <?php echo esc($p['service_name']); ?>
                        (per
                        <?php echo esc($p['unit']); ?>
                        )
                    </span>
                    <strong>
                        ₱
                        <?php echo number_format((float)$p['price'],2); ?>
                    </strong>
                </div>
                <?php endwhile; ?>
            </section>
            <section class="box">
                <h2>Place a Laundry Order</h2>
                <?php if($orderMessage!==''): ?><p class="message <?php echo $orderError?'error':''; ?>" role="status"><?php echo esc($orderMessage); ?></p><?php endif; ?>
                <form method="post" action="customer.php">
                    <label for="price_id">Service</label>
                    <select id="price_id" name="price_id">
                        <option value="0">Choose a service</option>
                        <?php if($prices){ mysqli_data_seek($prices,0); while($p=mysqli_fetch_assoc($prices)): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo esc($p['service_name']); ?> — ₱<?php echo number_format((float)$p['price'],2); ?> per <?php echo esc($p['unit']); ?></option>
                        <?php endwhile; } ?>
                    </select>
                    <label for="promo_id">Or choose a promo</label>
                    <select id="promo_id" name="promo_id">
                        <option value="0">Choose a promo</option>
                        <?php if($promotions){ mysqli_data_seek($promotions,0); while($promo=mysqli_fetch_assoc($promotions)): ?>
                        <option value="<?php echo (int)$promo['id']; ?>"><?php echo esc($promo['name']); ?> — ₱<?php echo number_format((float)$promo['price'],2); ?> (up to <?php echo esc($promo['max_weight']); ?> kg)</option>
                        <?php endwhile; } ?>
                    </select>
                    <label for="weight">Laundry weight (kg)</label>
                    <input id="weight" type="number" name="weight" min="0.1" max="1000" step="0.1" required>
                    <button type="submit" name="place_order" value="1">Submit Order</button>
                </form>
            </section>
            <section class="box">
                <h2>Current Promos</h2>
                <div class="promo-grid">
                    <?php while($promo=mysqli_fetch_assoc($promotions)): ?>
                    <article class="promo-card">
                        <h3><?php echo esc($promo['name']); ?></h3>
                        <p><?php echo nl2br(esc($promo['description'])); ?></p>
                        <strong class="promo-price">₱<?php echo number_format((float)$promo['price'],2); ?></strong>
                        <strong>Up to <?php echo esc($promo['max_weight']); ?> kg</strong>
                    </article>
                    <?php endwhile; ?>
                </div>
            </section>
            <section class="box">
                <h2>
                    Today's Laundry Orders
                </h2>
                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>
                                Order
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
                        </tr>
                        <?php if(mysqli_num_rows($orders)===0): ?>
                        <tr>
                                <td colspan="6">
                                Wala ka pang laundry order.
                            </td>
                        </tr>
                        <?php else: while($o=mysqli_fetch_assoc($orders)): ?>
                        <tr>
                            <td>
                                #
                                <?php echo (int)$o['id']; ?>
                            </td>
                            <td>
                                <?php echo esc($o['service_label']??''); ?>
                            </td>
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
                        </tr>
                        <?php endwhile; endif; ?>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>

