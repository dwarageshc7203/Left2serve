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

// Check if user exists in 'users' table
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found in users table.");
}

// Check if vendor profile exists
$stmt = $conn->prepare("SELECT id, shop_name, address FROM vendor_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $vendor_data = $result->fetch_assoc();
    $vendor_id = $vendor_data['id'];
} else {
    // Create new vendor profile
    $stmt = $conn->prepare("INSERT INTO vendor_profiles (user_id, shop_name, address) VALUES (?, '', '')");
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        die("Error inserting vendor profile: " . $stmt->error);
    }
    $vendor_id = $stmt->insert_id;
    $vendor_data = ['shop_name' => '', 'address' => ''];
}

// Handle adding new food item
if (isset($_POST['add_food_item'])) {
    $food_item = $_POST['food_item'];
    $meal_count = (int) $_POST['meal_count'];
    $food_type = $_POST['food_type']; // This should be 'veg' or 'non-veg'
    $duration_hours = isset($_POST['duration']) ? intval($_POST['duration']) : 1;
    
    // Fix: Calculate available_until correctly with proper timezone handling
    $current_time = new DateTime();
    $current_time->add(new DateInterval('PT' . $duration_hours . 'H'));
    $available_until = $current_time->format('Y-m-d H:i:s');
    
    $price = (int) $_POST['price'];

    $stmt = $conn->prepare("INSERT INTO food_items (vendor_id, food_name, food_type, meal_count, available_until, price) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issisi", $vendor_id, $food_item, $food_type, $meal_count, $available_until, $price);

    if ($stmt->execute()) {
        $food_update_msg = "Food item added successfully! Available until: " . $available_until;
    } else {
        $food_update_msg = "Error adding food item: " . $stmt->error;
    }
}

// Handle deleting food item
if (isset($_GET['delete_item']) && is_numeric($_GET['delete_item'])) {
    $item_id = $_GET['delete_item'];
    
    // Step 1: Delete all bookings for this food item
$stmt = $conn->prepare("DELETE FROM bookings WHERE food_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();

    // Make sure this item belongs to the current vendor
    $stmt = $conn->prepare("DELETE FROM food_items WHERE id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $item_id, $vendor_id);
    
    if ($stmt->execute()) {
        $food_delete_msg = "Food item deleted successfully!";
    } else {
        $food_delete_msg = "Error deleting food item.";
    }
}

// Handle updating meal count
if (isset($_POST['update_count'])) {
    $item_id = $_POST['item_id'];
    $new_count = $_POST['new_count'];
    
    if ($new_count >= 0) {
        $stmt = $conn->prepare("UPDATE food_items SET meal_count = ? WHERE id = ? AND vendor_id = ?");
        $stmt->bind_param("iii", $new_count, $item_id, $vendor_id);
        $stmt->execute();
    }
}

// Handle booking (new functionality)
if (isset($_POST['book_item'])) {
    $item_id = $_POST['book_item_id'];
    $booking_quantity = (int) $_POST['booking_quantity'];
    
    // First, check if there's enough quantity available
    $stmt = $conn->prepare("SELECT meal_count, food_name FROM food_items WHERE id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $item_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $item_data = $result->fetch_assoc();
        if ($item_data['meal_count'] >= $booking_quantity) {
            // Update the meal count
            $new_count = $item_data['meal_count'] - $booking_quantity;
            $stmt = $conn->prepare("UPDATE food_items SET meal_count = ? WHERE id = ? AND vendor_id = ?");
            $stmt->bind_param("iii", $new_count, $item_id, $vendor_id);
            
            if ($stmt->execute()) {
                $booking_msg = "Booking confirmed! " . $booking_quantity . " portion(s) of " . $item_data['food_name'] . " booked.";
            } else {
                $booking_msg = "Error processing booking.";
            }
        } else {
            $booking_msg = "Sorry, only " . $item_data['meal_count'] . " portion(s) available.";
        }
    }
}

// Get all food items for this vendor
$stmt = $conn->prepare("SELECT id, food_name, food_type, meal_count, available_until, price FROM food_items WHERE vendor_id = ? ORDER BY available_until DESC");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$food_items_result = $stmt->get_result();
$food_items = [];

while ($row = $food_items_result->fetch_assoc()) {
    // Fix: Calculate time remaining properly with better precision
    $now = new DateTime();
    $expires = new DateTime($row['available_until']);
    
    if ($expires > $now) {
        $interval = $now->diff($expires);
        $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        $hoursLeft = floor($totalMinutes / 60);
        $minutesLeft = $totalMinutes % 60;
        
        $row['timer'] = $hoursLeft;
        $row['minutes_left'] = $minutesLeft;
        $row['total_minutes_left'] = $totalMinutes;
        $row['is_expired'] = false;
    } else {
        $row['timer'] = 0;
        $row['minutes_left'] = 0;
        $row['total_minutes_left'] = 0;
        $row['is_expired'] = true;
    }
    
    // Debug info - you can remove this after testing
    $row['debug_now'] = $now->format('Y-m-d H:i:s');
    $row['debug_expires'] = $expires->format('Y-m-d H:i:s');
    
    $food_items[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saapaadu - Vendor Dashboard</title>
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
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 80px 20px 20px;
            min-height: 100vh;
            margin-bottom: 100px;
        }

        .welcome-banner {
            width: 100%;
            max-width: 1200px;
            background-color: #7c5295;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            animation: fadeIn 0.8s ease;
        }

        .dashboard-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            justify-content: center;
        }

        .dashboard-section {
            background-color: #f2d32c;
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 550px;
            animation: slideIn 0.8s ease;
        }
        
        .section-title {
            color: #1a1a1a;
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .form-label {
            color: #0066cc;
            font-size: 1.2rem;
            width: 150px;
            flex-shrink: 0;
        }

        .form-input {
            flex: 1;
            padding: 8px 15px;
            border-radius: 20px;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }

        .submit-btn {
            display: block;
            width: 150px;
            margin: 20px auto 0;
            padding: 10px;
            background-color: #00bcd4;
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background-color: #0097a7;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 188, 212, 0.4);
        }

        .message {
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .success {
            color: #4caf50;
            background-color: rgba(76, 175, 80, 0.1);
        }

        .error {
            color: #ff3333;
            background-color: rgba(255, 51, 51, 0.1);
        }

        /* Hotspot styles */
        .hotspot-container {
            margin-top: 20px;
            width: 100%;
        }

        .hotspot-item {
            background-color: white;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .hotspot-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .hotspot-item.expired {
            opacity: 0.6;
            background-color: #f0f0f0;
        }

        .hotspot-info {
            flex: 1;
        }

        .hotspot-name {
            color: #1a1a1a;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .hotspot-details {
            color: #666;
            font-size: 0.9rem;
        }

        .hotspot-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 10px;
        }

        .veg {
            background-color: #4caf50;
            color: white;
        }

        .non-veg {
            background-color: #f44336;
            color: white;
        }

        .hotspot-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .count-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .count-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #00bcd4;
            color: white;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }

        .count-btn:hover {
            background-color: #0097a7;
        }

        .count-display {
            width: 40px;
            text-align: center;
            font-weight: bold;
            color: #1a1a1a;
        }

        .delete-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ff3333;
            color: white;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background-color: #e53935;
        }

        .hotspot-price {
            font-weight: bold;
            color: #7c5295;
            font-size: 1.1rem;
            margin-left: 10px;
        }

        .timer-display {
            font-size: 0.9rem;
            color: #0066cc;
        }

        .expired-text {
            color: #ff3333;
            font-weight: bold;
        }

        /* Modal styles for booking popup */
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
        }

        .modal-title {
            color: #1a1a1a;
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .modal-info {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

        .booking-form {
            margin-top: 20px;
        }

        .booking-input {
            width: 80px;
            padding: 8px;
            border-radius: 10px;
            border: none;
            font-size: 1.1rem;
            text-align: center;
            margin: 0 10px;
        }

        .book-btn {
            background-color: #4caf50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 1.1rem;
            cursor: pointer;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .book-btn:hover {
            background-color: #45a049;
        }

        .cancel-btn {
            background-color: #ff3333;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 1.1rem;
            cursor: pointer;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background-color: #e53935;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
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
            .dashboard-section {
                max-width: 100%;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-label {
                margin-bottom: 8px;
                width: 100%;
            }

            .form-input {
                width: 100%;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }
        }

        /*footer*/
        footer{
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: rgb(46, 53, 62);
            color: white;
            border-radius: 15px;
            margin: 50px;
        }

        #made_by{
            display: flex;
            flex-direction: row;
            font-size: 40px;
            font-family: 'Pacifico', cursive;
        }

        .founders{
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-family: 'Lato',cursive;
        }

        .links{
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
        }

        ul li{
            list-style: none;
            padding: 10px;
        }

        .links img {
            width: 40px; 
            height: auto; 
        }
        /*footer*/
    </style>
</head>
<body>
    <header>
        <div class="logo">Saapaadu</div>
        <nav>
            <a href="vendor_dashboard.php" class="nav-link">Dashboard</a>
            <a href="vendor_profile.php" class="nav-link">Profile</a>
            <a href="vendor_orders.php" class="nav-link">Orders</a>
            <a href="logout.php" class="nav-link">Logout</a>
            <a href="#" class="nav-link" id="contacts">Contact</a>
        </nav>
    </header>

    <main>
        <div class="welcome-banner">
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>Manage your food items and track your hotspots</p>
        </div>

        <div class="dashboard-container">
            <div class="dashboard-section">
                <h2 class="section-title">Add Food Item</h2>
                <?php if(isset($food_update_msg)): ?>
                <div class="message success"><?php echo $food_update_msg; ?></div>
                <?php endif; ?>
                <?php if(isset($booking_msg)): ?>
                <div class="message success"><?php echo $booking_msg; ?></div>
                <?php endif; ?>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="food_type" class="form-label">Food Type</label>
                        <select id="food_type" name="food_type" class="form-input" required>
                            <option value="veg">Vegetarian</option>
                            <option value="non-veg">Non-Vegetarian</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="food_item" class="form-label">Food Item</label>
                        <input type="text" id="food_item" name="food_item" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="meal_count" class="form-label">Meal Count</label>
                        <input type="number" id="meal_count" name="meal_count" class="form-input" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="duration" class="form-label">Available for (hours)</label>
                        <input type="number" id="duration" name="duration" class="form-input" min="1" value="2" required>
                    </div>
                    <div class="form-group">
                        <label for="price" class="form-label">Price (₹)</label>
                        <input type="number" id="price" name="price" class="form-input" min="1" required>
                    </div>
                    <button type="submit" name="add_food_item" class="submit-btn">Add Hotspot</button>
                </form>
            </div>

            <div class="dashboard-section">
                <h2 class="section-title">Your Hotspots</h2>
                <?php if(isset($food_delete_msg)): ?>
                <div class="message success"><?php echo $food_delete_msg; ?></div>
                <?php endif; ?>
                <div class="hotspot-container">
                    <?php if(count($food_items) > 0): ?>
                        <?php foreach($food_items as $item): ?>
                            <div class="hotspot-item <?php echo $item['is_expired'] ? 'expired' : ''; ?>" onclick="openBookingModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['food_name']); ?>', <?php echo $item['price']; ?>, <?php echo $item['meal_count']; ?>, '<?php echo $item['food_type']; ?>')">
                                <div class="hotspot-info">
                                    <div class="hotspot-name"><?php echo htmlspecialchars($item['food_name']); ?></div>
                                    <div class="hotspot-details">
                                        <span class="hotspot-type <?php echo $item['food_type']; ?>">
                                            <?php echo $item['food_type'] === 'veg' ? 'VEG' : 'NON-VEG'; ?>
                                        </span>
                                        <span class="timer-display <?php echo $item['is_expired'] ? 'expired-text' : ''; ?>">
                                            ⏱️ <?php 
                                                if($item['is_expired']) {
                                                    echo "EXPIRED";
                                                } else {
                                                    if($item['timer'] > 0) {
                                                        echo $item['timer'] . " hours";
                                                        if($item['minutes_left'] > 0) {
                                                            echo " " . $item['minutes_left'] . " min";
                                                        }
                                                    } else {
                                                        echo $item['minutes_left'] . " minutes";
                                                    }
                                                    echo " left";
                                                }
                                            ?>
                                        </span>
                                        <?php if(isset($item['debug_now'])): ?>
                                        <div style="font-size: 0.7rem; color: #888;">
                                            Created: <?php echo date('H:i:s', strtotime($item['debug_expires']) - ($duration_hours ?? 1) * 3600); ?><br>
                                            Expires: <?php echo date('H:i:s', strtotime($item['debug_expires'])); ?><br>
                                            Now: <?php echo date('H:i:s', strtotime($item['debug_now'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="hotspot-controls" onclick="event.stopPropagation();">
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="count-controls">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="new_count" id="new_count_<?php echo $item['id']; ?>" value="<?php echo $item['meal_count']; ?>">
                                        <button type="button" class="count-btn" onclick="decrementCount(<?php echo $item['id']; ?>)">-</button>
                                        <span class="count-display" id="count_display_<?php echo $item['id']; ?>"><?php echo $item['meal_count']; ?></span>
                                        <button type="button" class="count-btn" onclick="incrementCount(<?php echo $item['id']; ?>)">+</button>
                                        <button type="submit" name="update_count" style="display: none;" id="update_btn_<?php echo $item['id']; ?>">Update</button>
                                    </form>
                                    <span class="hotspot-price">₹<?php echo $item['price']; ?></span>
                                    <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?delete_item=' . $item['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this item?');">×</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666;">No food items added yet. Add your first food item!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title" id="modalTitle">Book Food Item</h2>
            <div class="modal-info">
                <p><strong>Item:</strong> <span id="modalFoodName"></span></p>
                <p><strong>Type:</strong> <span id="modalFoodType"></span></p>
                <p><strong>Price:</strong> ₹<span id="modalPrice"></span> per portion</p>
                <p><strong>Available:</strong> <span id="modalAvailable"></span> portions</p>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="booking-form">
                <input type="hidden" name="book_item_id" id="bookItemId">
                <div>
                    <label for="booking_quantity">Quantity:</label>
                    <input type="number" name="booking_quantity" id="booking_quantity" class="booking-input" min="1" value="1" required>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="book-btn">Confirm Booking</button>
                    <button type="button" class="cancel-btn" onclick="closeBookingModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!--<footer>
        <div id="made_by">Crafted by</div>
        <div class="founders">DWARAGESH C
            <ul class="links">
                <li><a href="https://mail.google.com/mail/u/0/#inbox?compose=GTvVlcSDbhCLBLxqwZgxzLwDDrrTwMZdmjHKRJfxNFlBZMtrvRCFTMjvbqCQNbfvSRxKtbpSXvwxG">
                    <img src="https://freepngimg.com/download/gmail/66428-icons-computer-google-email-gmail-free-transparent-image-hq.png">
                    </a></li>
                <li><a href="https://github.com/dwarageshc7203">
                    <img src="https://pngimg.com/uploads/github/github_PNG80.png">
                    </a></li>
                <li><a href="https://www.linkedin.com/in/dwarageshc/">
                    <img src="https://itcnet.gr/wp-content/uploads/2020/09/Linkedin-logo-on-transparent-Background-PNG--1024x1024.png">
                    </a></li>
            </ul>
        </div>

        <div class="founders">SRIDEV B
            <ul class="links">
                <li><a href="https://mail.google.com/mail/u/0/#inbox?compose=new">
                    <img src="https://freepngimg.com/download/gmail/66428-icons-computer-google-email-gmail-free-transparent-image-hq.png">
                    </a></li>
                <li><a href="https://github.com/SRIDEV20">
                    <img src="https://pngimg.com/uploads/github/github_PNG80.png">
                    </a></li>
                <li><a href="https://www.linkedin.com/in/sri-dev-58aa4334a/">
                    <img src="https://itcnet.gr/wp-content/uploads/2020/09/Linkedin-logo-on-transparent-Background-PNG--1024x1024.png">
                    </a></li>
            </ul>
        </div>
    </footer>-->

    <script>
        function decrementCount(itemId) {
            const countInput = document.getElementById(`new_count_${itemId}`);
            const countDisplay = document.getElementById(`count_display_${itemId}`);
            let currentCount = parseInt(countInput.value);
            
            if (currentCount > 0) {
                currentCount--;
                countInput.value = currentCount;
                countDisplay.textContent = currentCount;
                document.getElementById(`update_btn_${itemId}`).click();
            }
        }

        function incrementCount(itemId) {
            const countInput = document.getElementById(`new_count_${itemId}`);
            const countDisplay = document.getElementById(`count_display_${itemId}`);
            let currentCount = parseInt(countInput.value);
            
            currentCount++;
            countInput.value = currentCount;
            countDisplay.textContent = currentCount;
            document.getElementById(`update_btn_${itemId}`).click();
        }

        // Booking Modal Functions
        function openBookingModal(itemId, foodName, price, available, foodType) {
            if (available <= 0) {
                alert('Sorry, this item is out of stock!');
                return;
            }
            
            document.getElementById('modalFoodName').textContent = foodName;
            document.getElementById('modalPrice').textContent = price;
            document.getElementById('modalAvailable').textContent = available;
            document.getElementById('modalFoodType').textContent = foodType === 'veg' ? 'Vegetarian' : 'Non-Vegetarian';
            document.getElementById('bookItemId').value = itemId;
            document.getElementById('booking_quantity').max = available;
            document.getElementById('booking_quantity').value = 1;
            
            document.getElementById('bookingModal').style.display = 'block';
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target == modal) {
                closeBookingModal();
            }
        }

        // Validate booking quantity
        document.getElementById('booking_quantity').addEventListener('input', function() {
            const max = parseInt(this.max);
            const value = parseInt(this.value);
            
            if (value > max) {
                this.value = max;
                alert(`Only ${max} portions available!`);
            }
            if (value < 1) {
                this.value = 1;
            }
        });

        // Contact scroll functionality
        window.addEventListener("DOMContentLoaded", function () {
            const contacts = document.getElementById("contacts");
            const footer = document.querySelector("footer");
          
            contacts.addEventListener("click", function () {
                footer.scrollIntoView({ behavior: "smooth" });
            });
        });

        // Auto-refresh timer every minute to update time remaining
        setInterval(function() {
            location.reload();
        }, 60000); // Refresh every minute