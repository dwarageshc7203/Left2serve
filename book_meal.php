<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "saapaadu";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$food_id = isset($_POST['food_id']) ? intval($_POST['food_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
$customer_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;

// Validate inputs
if ($food_id <= 0 || $quantity <= 0 || $vendor_id <= 0 || $customer_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid booking data',
        'debug' => [
            'food_id' => $food_id,
            'quantity' => $quantity,
            'vendor_id' => $vendor_id,
            'customer_id' => $customer_id
        ]
    ]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // First, let's get the correct vendor user_id from the vendor_profiles table
    // since the foreign key references users.id, not vendor_profiles.id
    $vendor_user_query = "SELECT user_id FROM vendor_profiles WHERE id = ?";
    $stmt = $conn->prepare($vendor_user_query);
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $vendor_result = $stmt->get_result();
    
    if ($vendor_result->num_rows === 0) {
        throw new Exception('Vendor not found in profiles table');
    }
    
    $vendor_data = $vendor_result->fetch_assoc();
    $vendor_user_id = $vendor_data['user_id']; // This is the actual user_id we need
    
    error_log("Vendor lookup: vendor_id=" . $vendor_id . " maps to user_id=" . $vendor_user_id);
    
    // Check if food item exists and has enough meals available
    $check_query = "SELECT meal_count FROM food_items WHERE id = ? AND vendor_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $food_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Food item not found');
    }
    
    $food_data = $result->fetch_assoc();
    $available_meals = intval($food_data['meal_count']);
    
    if ($available_meals < $quantity) {
        throw new Exception('Not enough meals available');
    }
    
    // Update meal count
    $update_query = "UPDATE food_items SET meal_count = meal_count - ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $quantity, $food_id);
    $stmt->execute();
    
    // Insert booking record using the vendor's user_id (not profile_id)
    $booking_query = "INSERT INTO bookings (customer_id, vendor_id, food_id, quantity, status) VALUES (?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($booking_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare booking query: ' . $conn->error);
    }
    $stmt->bind_param("iiii", $customer_id, $vendor_user_id, $food_id, $quantity); // Using vendor_user_id here
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute booking query: ' . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Booking successful!']);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>