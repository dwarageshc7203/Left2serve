<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "saapaadu";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['id'];
$username = $_SESSION['username'];

// Get vendor profile ID
$stmt = $conn->prepare("SELECT id FROM vendor_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Vendor profile not found.");
}

$vendor_data = $result->fetch_assoc();
$vendor_id = $vendor_data['id'];

// Handle order deletion
if (isset($_POST['delete_booking_id'])) {
    $booking_id = intval($_POST['delete_booking_id']);
    
    // Verify the booking belongs to this vendor
    $verify_stmt = $conn->prepare("SELECT b.id FROM bookings b 
                                 JOIN food_items f ON b.food_id = f.id 
                                 WHERE b.id = ? AND f.vendor_id = ?");
    $verify_stmt->bind_param("ii", $booking_id, $vendor_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        // Delete the booking
        $delete_stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $delete_stmt->bind_param("i", $booking_id);
        
        if ($delete_stmt->execute()) {
            $delete_message = "Order deleted successfully.";
        } else {
            $delete_error = "Error deleting order: " . $conn->error;
        }
    } else {
        $delete_error = "Order not found or you don't have permission to delete it.";
    }
}

// Fetch all orders for this vendor
$query = "SELECT b.id as booking_id, b.quantity, b.total_price, b.booking_date,
                 fi.food_name, fi.food_type, fi.price as unit_price,
                 cp.full_name as customer_name, cp.contact_number, cp.email,
                 u.username as customer_username
          FROM bookings b
          JOIN food_items fi ON b.food_id = fi.id
          JOIN customer_profiles cp ON b.customer_id = cp.user_id
          JOIN users u ON b.customer_id = u.id
          WHERE fi.vendor_id = ?
          ORDER BY b.booking_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$bookings_result = $stmt->get_result();

$bookings = [];
$total_revenue = 0;
$total_orders = 0;

while ($row = $bookings_result->fetch_assoc()) {
    $bookings[] = $row;
    $total_revenue += $row['total_price'];
    $total_orders++;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saapaadu - Vendor Orders</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&family=Abril+Fatface&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #1a1a1a;
            color: white;
            display: flex;
            flex-direction: column;
        }

        /* Header styling */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: #a2e759;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            height: 60px;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #7c5295;
        }

        nav {
            display: flex;
            gap: 20px;
        }

        .nav-link {
            color: #0066cc;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: #4a6cd1;
            transform: translateY(-2px);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: #4a6cd1;
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Main content */
        main {
            flex: 1;
            padding: 80px 20px 20px;
            min-height: 100vh;
            margin-bottom: 100px;
        }

        .orders-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-banner {
            background-color: #7c5295;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            animation: fadeIn 0.8s ease;
        }

        /* Statistics cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #f2d32c;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            color: #1a1a1a;
            animation: slideIn 0.8s ease;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #7c5295;
        }

        .stat-label {
            font-size: 1rem;
            color: #0066cc;
            margin-top: 5px;
        }

        /* Orders section */
        .orders-section {
            background-color: #f2d32c;
            padding: 30px;
            border-radius: 10px;
            animation: slideIn 0.8s ease;
        }

        .section-title {
            color: #1a1a1a;
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }

        .message {
            text-align: center;
            margin: 15px 0;
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
        }

        .success {
            color: #4caf50;
            background-color: rgba(76, 175, 80, 0.15);
            border: 1px solid #4caf50;
        }

        .error {
            color: #ff3333;
            background-color: rgba(255, 51, 51, 0.15);
            border: 1px solid #ff3333;
        }

        /* Order cards */
        .order-card {
            background-color: white;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .order-id {
            font-size: 1.1rem;
            font-weight: bold;
            color: #7c5295;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .detail-group {
            color: #333;
        }

        .detail-label {
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1rem;
        }

        .food-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 8px;
        }

        .veg {
            background-color: #4caf50;
            color: white;
        }

        .non-veg {
            background-color: #f44336;
            color: white;
        }

        .order-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }

        .delete-btn {
            background-color: #ff3333;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background-color: #e53935;
            transform: scale(1.05);
        }

        .no-orders {
            text-align: center;
            color: #666;
            font-size: 1.2rem;
            padding: 40px;
        }

        /* Modal for delete confirmation */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #f2d32c;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            color: #1a1a1a;
        }

        .modal-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #7c5295;
        }

        .modal-text {
            font-size: 1.1rem;
            margin-bottom: 25px;
            color: #333;
        }

        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .confirm-btn {
            background-color: #ff3333;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .confirm-btn:hover {
            background-color: #e53935;
        }

        .modal-cancel-btn {
            background-color: #00bcd4;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-cancel-btn:hover {
            background-color: #0097a7;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .order-details {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .order-actions {
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Left2serve</div>
        <nav>
            <a href="vendor_dashboard.php" class="nav-link">Dashboard</a>
            <a href="vendor_orders.php" class="nav-link">Orders</a>
            <a href="vendor_profile.php" class="nav-link">Profile</a>
            <a href="logout.php" class="nav-link">Logout</a>
            <a href="#" class="nav-link" id="contacts">Contact</a>
        </nav>
    </header>

    <main>
        <div class="orders-container">
            <div class="welcome-banner">
                <h1>Order Management</h1>
                <p>Track and manage your food orders, <?php echo htmlspecialchars($username); ?>!</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number">₹<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="orders-section">
                <h2 class="section-title">All Orders</h2>

                <?php if(isset($delete_message)): ?>
                <div class="message success"><?php echo $delete_message; ?></div>
                <?php endif; ?>

                <?php if(isset($delete_error)): ?>
                <div class="message error"><?php echo $delete_error; ?></div>
                <?php endif; ?>

                <?php if(count($bookings) > 0): ?>
                    <?php foreach($bookings as $booking): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-id">Order #<?php echo $booking['booking_id']; ?></div>
                                <form method="POST" onsubmit="return confirmDelete(<?php echo $booking['booking_id']; ?>, '<?php echo addslashes($booking['food_name']); ?>')">
                                    <input type="hidden" name="delete_booking_id" value="<?php echo $booking['booking_id']; ?>">
                                    <button type="submit" class="delete-btn">Delete Order</button>
                                </form>
                            </div>

                            <div class="order-details">
                                <div class="detail-group">
                                    <div class="detail-label">Customer</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($booking['customer_name'] ?: $booking['customer_username']); ?><br>
                                        <small><?php echo htmlspecialchars($booking['contact_number'] ?: 'No contact'); ?></small><br>
                                        <small><?php echo htmlspecialchars($booking['email'] ?: 'No email'); ?></small>
                                    </div>
                                </div>

                                <div class="detail-group">
                                    <div class="detail-label">Food Item</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($booking['food_name']); ?>
                                        <span class="food-type-badge <?php echo $booking['food_type']; ?>">
                                            <?php echo $booking['food_type'] === 'veg' ? 'VEG' : 'NON-VEG'; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="detail-group">
                                    <div class="detail-label">Order Details</div>
                                    <div class="detail-value">
                                        Quantity: <?php echo $booking['quantity']; ?><br>
                                        Unit Price: ₹<?php echo $booking['unit_price']; ?><br>
                                        <strong>Total: ₹<?php echo $booking['total_price']; ?></strong>
                                    </div>
                                </div>

                                <div class="detail-group">
                                    <div class="detail-label">Booking Time</div>
                                    <div class="detail-value">
                                        <?php echo date('d M Y, H:i', strtotime($booking['booking_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-orders">
                        <p>No orders found.</p>
                        <p>Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function confirmDelete(bookingId, foodName) {
            return confirm(`Are you sure you want to delete this order?\n\nOrder #${bookingId}\nFood: ${foodName}\n\nThis action cannot be undone.`);
        }

        // Contact scroll functionality
        window.addEventListener("DOMContentLoaded", function () {
            const contacts = document.getElementById("contacts");
            const footer = document.querySelector("footer");
          
            contacts.addEventListener("click", function () {
                footer.scrollIntoView({ behavior: "smooth" });
            });
        });
    </script>
</body>
</html>