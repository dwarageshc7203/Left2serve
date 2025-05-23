<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "saapaadu";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// DEBUGGING: Add a flag to check if we're getting any rows at all before location filtering
$debug_mode = true; // Set to true to enable debugging
$debug_message = "";
$debug_counts = [
    "total_hotspots" => 0,
    "after_preference_filter" => 0,
    "after_location_filter" => 0,
    "after_expiry_filter" => 0
];

// Get customer location information (if available)
$customer_id = $_SESSION['id'] ?? 0;
$customer_location = ['area' => '', 'city' => ''];

if ($customer_id > 0) {
    $customer_query = "SELECT area, city FROM customer_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($customer_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $customer_result = $stmt->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer_location = $customer_result->fetch_assoc();
    } else {
        $debug_message .= "Warning: Customer profile not found for user_id: $customer_id<br>";
    }
    $stmt->close();
} else {
    $debug_message .= "Warning: No valid user_id found in session<br>";
}

// Default food preference based on customer's dietary preference (if available)
$default_preference = 'Veg'; // Default if no preference is found

if ($customer_id > 0) {
    $customer_pref_query = "SELECT dietary_preference FROM customer_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($customer_pref_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $pref_result = $stmt->get_result();
    
    if ($pref_result->num_rows > 0) {
        $customer_pref = $pref_result->fetch_assoc();
        if (!empty($customer_pref['dietary_preference'])) {
            $default_preference = $customer_pref['dietary_preference'];
        }
    }
    $stmt->close();
}

// Get food preference from URL parameter or use default
$food_preference = isset($_GET['preference']) ? $_GET['preference'] : $default_preference;

// EMERGENCY DEBUG MODE - Completely bypass all filters
$debug_mode = true;
$emergency_debug = true;
$debug_message = "";
$debug_counts = [
    "total_hotspots" => 0,
    "after_preference_filter" => 0,
    "after_location_filter" => 0,
    "after_expiry_filter" => 0
];

// Direct raw data dump from the database
$raw_data = [];
$raw_vendors = [];
$raw_food_items = [];
$raw_customer = [];

// Get raw customer data
if ($customer_id > 0) {
    $raw_customer_query = "SELECT * FROM customer_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($raw_customer_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $raw_customer_result = $stmt->get_result();
    if ($raw_customer_result->num_rows > 0) {
        $raw_customer = $raw_customer_result->fetch_assoc();
    }
    $stmt->close();
}

// Get raw vendor data
$vendor_query = "SELECT * FROM vendor_profiles";
$vendor_result = $conn->query($vendor_query);
while ($row = $vendor_result->fetch_assoc()) {
    $raw_vendors[] = $row;
}

// Get raw food_items data
$food_query = "SELECT * FROM food_items";
$food_result = $conn->query($food_query);
while ($row = $food_result->fetch_assoc()) {
    $raw_food_items[] = $row;
}

// Generate raw data structure
foreach ($raw_vendors as $vendor) {
    foreach ($raw_food_items as $food) {
        if ($food['vendor_id'] == $vendor['id']) {
            $raw_data[] = [
                'vendor' => $vendor,
                'food' => $food
            ];
        }
    }
}

// Get actual hotspots with simplified logic
if ($emergency_debug) {
    // Use a very simple query that should return all hotspots
    $preference = $food_preference; // Use the URL parameter preference instead
    $simple_query = "SELECT vp.*, fi.*, vp.id as vendor_profile_id, fi.id as food_item_id
            FROM vendor_profiles vp
            JOIN food_items fi ON vp.id = fi.vendor_id
            WHERE fi.food_type = ?";
    $stmt = $conn->prepare($simple_query);
    $stmt->bind_param("s", $preference);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hotspots = [];
    while ($row = $result->fetch_assoc()) {
        // Create a nicely formatted hotspot object
        $hotspot = [
    'food_item_id' => $row['food_item_id'],  // Food item ID
    'vendor_profile_id' => $row['vendor_profile_id'],  // Vendor profile ID
    'vendor_id' => $row['vendor_id'],    
    'user_id' => $row['user_id'],  // User ID
    'vendor_name' => $row['full_name'],
    'shop_name' => $row['shop_name'],
    'food_type' => $row['food_type'],
    'address' => $row['shop_no'] . ', ' . $row['street'] . ', ' . $row['area'] . ', ' . $row['city'],
    'area' => $row['area'],
    'city' => $row['city'],
    'latitude' => $row['latitude'],
    'longitude' => $row['longitude'],
    'food_id' => $row['food_item_id'],  // Use the aliased food_item_id
    'food_item' => $row['food_name'],
    'meal_count' => $row['meal_count'],
    'price' => $row['price'],
    'available_until' => $row['available_until'],
    'hours_left' => 1 // Default value
];
        
        // Try to calculate hours left
        try {
            $now = new DateTime();
            $expires = new DateTime($row['available_until']);
            if ($expires > $now) {
                $interval = $now->diff($expires);
                $hotspot['hours_left'] = $interval->h + ($interval->days * 24);
            }
        } catch (Exception $e) {
            // Ignore date parsing errors
        }
        
        $hotspots[] = $hotspot;
    }
} else {
    // Original logic from before
    // (This code won't execute with emergency_debug = true)
}

// Count for debug display
$debug_counts["total_hotspots"] = count($raw_food_items);
$debug_counts["after_preference_filter"] = count($raw_data);
$debug_counts["after_location_filter"] = count($raw_data);
$debug_counts["after_expiry_filter"] = count($hotspots);
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saapaadu - Customer Dashboard</title>
    <!-- Leaflet CSS for map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
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
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #1a1a1a;
            color: white;
            display: flex;
            flex-direction: column;
            margin-bottom: 1300px;
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

        /* Filter bar */
        .filter-bar {
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            background-color: #2e2e2e;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 900;
        }

        .preference-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 1.2rem;
        }

        .change-btn {
            background-color: #00bcd4;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 15px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .change-btn:hover {
            background-color: #0097a7;
            transform: scale(1.05);
        }

        /* Main content layout */
        .dashboard-container {
            display: flex;
            height: calc(100vh - 100px);
            margin-top: 100px;
        }

        /* Map styling */
        .map-container {
            flex: 7;
            background-color: #f0e68c;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        /* Sidebar styling */
        .sidebar {
            flex: 3;
            height: 100%;
            overflow-y: auto;
            padding: 10px;
            background-color: #b2c7e8;
        }

        .hotspot-btn {
            display: block;
            width: 100%;
            margin: 10px 0;
            padding: 15px;
            background-color: #d1e0f5;
            border: none;
            border-radius: 10px;
            text-align: left;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .hotspot-btn:hover {
            background-color: #a2c4e8;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .hotspot-name {
            font-weight: bold;
            font-size: 1.2rem;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .hotspot-type-time {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #0066cc;
        }

        .hotspot-time {
            background-color: #7c5295;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .hotspot-address {
            color: #333;
            font-size: 0.9rem;
            margin-top: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .hotspot-food {
            color: #555;
            font-style: italic;
            font-size: 0.9rem;
            margin-top: 3px;
        }

        /* No hotspots message */
        .no-hotspots {
            text-align: center;
            padding: 20px;
            color: #333;
            font-size: 1.1rem;
        }

        /* Modal/Popup styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #f2d32c;
            width: 90%;
            max-width: 500px;
            padding: 25px;
            border-radius: 10px;
            position: relative;
            color: #1a1a1a;
            animation: fadeIn 0.3s ease;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: #0066cc;
        }

        .modal-title {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #7c5295;
        }

        .modal-info {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }

        .modal-label {
            font-weight: bold;
            color: #0066cc;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .map-container {
                flex: none;
                height: 60%;
            }

            .sidebar {
                flex: none;
                height: 40%;
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
    padding: 10px;}

.links img {
    width: 40px; 
    height: auto; 
}

/*footer*/
    </style>
</head>
<body>
    <header>
        <div class="logo">Left2serve</div>
        <nav>
            <a href="customer_dashboard.php" class="nav-link">Dashboard</a>
            <a href="customer_profile.php" class="nav-link">Profile</a>
            <a href="logout.php" class="nav-link">Logout</a>
            <a href="#" class="nav-link" id="contacts">Contact</a>
            
        </nav>
    </header>

    <div class="filter-bar">
        <div class="preference-toggle">
            <span>Displaying contents for <b><?php echo $food_preference; ?></b></span>
            <button class="change-btn" id="togglePreference">(change?)</button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="map-container">
            <div id="map"></div>
        </div>
        <div class="sidebar">
            <?php if($debug_mode): ?><!--
            <div class="debug-info" style="background-color: #ffe6e6; color: #800000; padding: 10px; margin-bottom: 10px; border-radius: 5px; overflow: auto; max-height: 400px;">
                <h3>Debug Information</h3>
                <p>Total hotspots in database: <?php echo $debug_counts["total_hotspots"]; ?></p>
                <p>After food preference filter: <?php echo $debug_counts["after_preference_filter"]; ?></p>
                <p>After location filter: <?php echo $debug_counts["after_location_filter"]; ?></p>
                <p>After expiry filter: <?php echo $debug_counts["after_expiry_filter"]; ?></p>
                <p>Your location: Area - <?php echo $customer_location['area'] ?? 'Not set'; ?>, City - <?php echo $customer_location['city'] ?? 'Not set'; ?></p>
                <p>Your food preference: <?php echo $food_preference; ?></p>
                
                <?php if($emergency_debug): ?>
                <hr>
                <h4>Customer Data:</h4>
                <pre><?php print_r($raw_customer); ?></pre>
                
                <hr>
                <h4>Vendors Data:</h4>
                <pre><?php foreach($raw_vendors as $index => $vendor) {
                    echo "Vendor " . ($index + 1) . ":\n";
                    print_r($vendor);
                    echo "\n";
                } ?></pre>
                
                <hr>
                <h4>Food Items Data:</h4>
                <pre><?php foreach($raw_food_items as $index => $item) {
                    echo "Food Item " . ($index + 1) . ":\n";
                    print_r($item);
                    echo "\n";
                } ?></pre>
                <?php endif; ?>
                
                <?php echo $debug_message; ?>
            </div>-->
            <?php endif; ?>
            
            <?php if(count($hotspots) > 0): ?>
                <?php foreach($hotspots as $hotspot): ?>
                    <button class="hotspot-btn" onclick="showHotspotDetails(<?php echo htmlspecialchars(json_encode($hotspot)); ?>)">
                        <div class="hotspot-name"><?php echo htmlspecialchars($hotspot['shop_name']); ?></div>
                        <div class="hotspot-type-time">
                            <span><?php echo htmlspecialchars($hotspot['food_type']); ?></span>
                            <span class="hotspot-time"><?php echo $hotspot['hours_left']; ?> Hour<?php echo $hotspot['hours_left'] != 1 ? 's' : ''; ?> left</span>
                        </div>
                        <div class="hotspot-food"><?php echo htmlspecialchars($hotspot['food_item']); ?></div>
                        <div class="hotspot-address"><?php echo htmlspecialchars($hotspot['address']); ?></div>
                    </button>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-hotspots">
                    <p>No available hotspots found in your area.</p>
                    <p>Try changing your food preference or check back later!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for detailed hotspot information -->
    <div class="modal-overlay" id="hotspotModal">
        <div class="modal-content">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <h2 class="modal-title" id="modalTitle"></h2>
    <div class="modal-info">
        <span class="modal-label">Food Type:</span>
        <span id="modalFoodType"></span>
    </div>
    <div class="modal-info">
        <span class="modal-label">Food Item:</span>
        <span id="modalFoodItem"></span>
    </div>
    <div class="modal-info">
        <span class="modal-label">Address:</span>
        <span id="modalAddress"></span>
    </div>
    <div class="modal-info">
        <span class="modal-label">Available Meals:</span>
        <span id="modalMealCount"></span>
    </div>
    <div class="modal-info">
        <span class="modal-label">Time Left:</span>
        <span id="modalTimer"></span>
    </div>
    <div class="modal-info">
        <span class="modal-label">Price:</span>
        <span id="modalPrice"></span>
    </div>
    
    <!-- New booking section -->
    <div class="booking-section" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #ddd;">
        <div class="booking-controls" style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 15px;">
            <button onclick="decrementQuantity()" style="background-color: #ff4444; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; font-size: 18px; cursor: pointer;">-</button>
            <span id="bookingQuantity" style="font-size: 18px; font-weight: bold;">1</span>
            <button onclick="incrementQuantity()" style="background-color: #00aa00; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; font-size: 18px; cursor: pointer;">+</button>
        </div>
        <button id="bookButton" onclick="bookMeal()" style="background-color: #7c5295; color: white; border: none; border-radius: 10px; padding: 12px 30px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%;">Book Now</button>
    </div>
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
                    <li><a href="https://www.linkedin.com/in/sri-dev-58aa4434a/">
                        <img src="https://itcnet.gr/wp-content/uploads/2020/09/Linkedin-logo-on-transparent-Background-PNG--1024x1024.png">
                        </a></li>
                </ul>
            </div>
        </footer>-->

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialize map with default view (center of India)
        const map = L.map('map').setView([20.5937, 78.9629], 5);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // Get hotspots data from PHP
        const hotspots = <?php echo json_encode($hotspots); ?>;
        const markers = {};

        // Add markers to map
        if (hotspots.length > 0) {
            // Calculate bounds to fit all markers
            const bounds = [];
            
            hotspots.forEach(hotspot => {
                console.log("Processing hotspot:", hotspot);
                
                // Only proceed if coordinates are valid
                if (hotspot.latitude && hotspot.longitude) {
                    const lat = parseFloat(hotspot.latitude);
                    const lng = parseFloat(hotspot.longitude);
                    
                    if (!isNaN(lat) && !isNaN(lng)) {
                        const position = [lat, lng];
                        bounds.push(position);
                        
                        // Create icon based on food type
                        const iconUrl = hotspot.food_type === 'Veg' ? 
                            'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png' : 
                            'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png';
                        
                        const customIcon = L.icon({
                            iconUrl: iconUrl,
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        });
                        
                        // Create a unique ID for the marker
                        const markerId = hotspot.food_id || hotspot.id;
                        
                        // Create marker with unique ID 
                        const marker = L.marker(position, {icon: customIcon}).addTo(map);
                        
                        // Add popup to marker
                        marker.bindPopup(`
                            <b>${hotspot.shop_name || "Unknown Shop"}</b><br>
                            ${hotspot.food_item || "Unknown Food"}<br>
                            ${hotspot.hours_left} Hour${hotspot.hours_left != 1 ? 's' : ''} left
                        `);
                        
                        // Store marker
                        markers[markerId] = marker;
                        
                        // Add click event to marker
                        marker.on('click', () => showHotspotDetails(hotspot));
                    } else {
                        console.log("Invalid coordinates:", hotspot);
                    }
                } else {
                    console.log("Missing coordinates:", hotspot);
                }
            });
            
            // If we have valid bounds, fit the map to them
            if (bounds.length > 0) {
                map.fitBounds(bounds);
            } else {
                // Default to Chennai if no valid coordinates
                map.setView([13.0827, 80.2707], 12);
            }
        } else {
            // Default to Chennai if no hotspots
            map.setView([13.0827, 80.2707], 12);
        }

        // Toggle preference button
        document.getElementById('togglePreference').addEventListener('click', function() {
            const currentPreference = '<?php echo $food_preference; ?>';
            const newPreference = currentPreference === 'Veg' ? 'Non-Veg' : 'Veg';
            window.location.href = `customer_dashboard.php?preference=${newPreference}`;
        });

        // Show hotspot details in modal
       function showHotspotDetails(hotspot) {
    console.log("=== HOTSPOT DETAILS DEBUG ===");
    console.log("Raw hotspot data received:", hotspot);
    console.log("Type of hotspot:", typeof hotspot);
    
    // If hotspot is passed as a string (from onclick attribute), parse it
    if (typeof hotspot === 'string') {
        try {
            hotspot = JSON.parse(hotspot);
            console.log("Parsed hotspot data:", hotspot);
        } catch (e) {
            console.error("Failed to parse hotspot data:", e);
            alert("Error loading hotspot details");
            return;
        }
    }
    
    // Log all available properties
    console.log("Available hotspot properties:", Object.keys(hotspot));
    console.log("food_item_id:", hotspot.food_item_id);
    console.log("food_id:", hotspot.food_id);
    console.log("vendor_id:", hotspot.vendor_id);
    console.log("vendor_profile_id:", hotspot.vendor_profile_id);
    
    currentHotspot = hotspot;
    maxAvailable = parseInt(hotspot.meal_count) || 0;
    bookingQuantity = 1;
    
    console.log("Set currentHotspot:", currentHotspot);
    console.log("Max available meals:", maxAvailable);
    
    // Center map on selected hotspot and open its popup
    const foodId = hotspot.food_item_id || hotspot.food_id || hotspot.id;
    console.log("Looking for marker with ID:", foodId);
    
    if (markers[foodId]) {
        map.setView(markers[foodId].getLatLng(), 16);
        markers[foodId].openPopup();
    } else {
        console.log("No marker found for ID:", foodId);
        console.log("Available marker IDs:", Object.keys(markers));
    }
    
    // Populate modal with hotspot details
    document.getElementById('modalTitle').textContent = hotspot.shop_name || 'Unknown Shop';
    document.getElementById('modalFoodType').textContent = hotspot.food_type || 'Unknown';
    document.getElementById('modalFoodItem').textContent = hotspot.food_item || 'Not specified';
    document.getElementById('modalAddress').textContent = hotspot.address || 'Address not available';
    document.getElementById('modalMealCount').textContent = hotspot.meal_count || '0';
    document.getElementById('modalTimer').textContent = (hotspot.hours_left || 0) + ' Hour' + (hotspot.hours_left != 1 ? 's' : '') + ' left';
    document.getElementById('modalPrice').textContent = hotspot.price ? '₹' + hotspot.price : 'Not specified';
    
    // Reset booking controls
    document.getElementById('bookingQuantity').textContent = '1';
    const bookButton = document.getElementById('bookButton');
    
    if (maxAvailable > 0) {
        bookButton.disabled = false;
        bookButton.textContent = 'Book Now';
        bookButton.style.backgroundColor = '#7c5295';
    } else {
        bookButton.disabled = true;
        bookButton.textContent = 'Sold Out';
        bookButton.style.backgroundColor = '#999999';
    }
    
    // Show modal
    document.getElementById('hotspotModal').style.display = 'flex';
    
    console.log("Modal populated and displayed");
    console.log("=== END HOTSPOT DETAILS DEBUG ===");
}

        // Close modal function
        function closeModal() {
            document.getElementById('hotspotModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('hotspotModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });
        window.addEventListener("DOMContentLoaded", function () {
    const contacts = document.getElementById("contacts");
    const footer = document.querySelector("footer");
  
    contacts.addEventListener("click", function () {
      footer.scrollIntoView({ behavior: "smooth" });
    });
  });
  let currentHotspot = null;
let bookingQuantity = 1;
let maxAvailable = 0;

function incrementQuantity() {
    if (bookingQuantity < maxAvailable) {
        bookingQuantity++;
        document.getElementById('bookingQuantity').textContent = bookingQuantity;
    }
}

function decrementQuantity() {
    if (bookingQuantity > 1) {
        bookingQuantity--;
        document.getElementById('bookingQuantity').textContent = bookingQuantity;
    }
}

function bookMeal() {
    if (!currentHotspot) {
        alert('No hotspot selected');
        return;
    }
    
    // Debug: Log the current hotspot data
    console.log("=== BOOKING DEBUG ===");
    console.log("Current hotspot data:", currentHotspot);
    console.log("Available properties:", Object.keys(currentHotspot));
    
    // Extract values with fallbacks for different possible property names
    const foodId = currentHotspot.food_item_id || currentHotspot.food_id || currentHotspot.id || 0;
    const vendorId = currentHotspot.vendor_id || currentHotspot.vendor_profile_id || 0;
    const quantity = bookingQuantity || 1;
    
    console.log("Extracted values:");
    console.log("- foodId:", foodId);
    console.log("- vendorId:", vendorId);
    console.log("- quantity:", quantity);
    console.log("=== END BOOKING DEBUG ===");
    
    // Validate values before sending
    if (foodId <= 0 || vendorId <= 0 || quantity <= 0) {
        alert('Error: Invalid booking data. Please try selecting the item again.');
        console.error("Invalid values detected:", {foodId, vendorId, quantity});
        return;
    }
    
    const formData = new FormData();
    formData.append('food_id', foodId);
    formData.append('quantity', quantity);
    formData.append('vendor_id', vendorId);
    
    // Disable the book button to prevent multiple clicks
    const bookButton = document.getElementById('bookButton');
    bookButton.disabled = true;
    bookButton.textContent = 'Booking...';
    
    fetch('book_meal.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("Server response:", data);
        
        if (data.success) {
            alert('Booking successful!');
            // Update the meal count in the current display
            currentHotspot.meal_count -= bookingQuantity;
            document.getElementById('modalMealCount').textContent = currentHotspot.meal_count;
            
            // Reset booking quantity
            bookingQuantity = 1;
            document.getElementById('bookingQuantity').textContent = '1';
            
            // If no meals left, disable booking
            if (currentHotspot.meal_count <= 0) {
                bookButton.disabled = true;
                bookButton.textContent = 'Sold Out';
            } else {
                bookButton.disabled = false;
                bookButton.textContent = 'Book Now';
            }
            
            closeModal();
            
            // Optionally reload the page to refresh all data
            // location.reload();
        } else {
            alert('Booking failed: ' + (data.message || 'Unknown error'));
            // Re-enable the button
            bookButton.disabled = false;
            bookButton.textContent = 'Book Now';
            
            // If debug data is available, show it
            if (data.debug) {
                console.log("Debug data from server:", data.debug);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Booking failed. Please try again.');
        // Re-enable the button
        bookButton.disabled = false;
        bookButton.textContent = 'Book Now';
    });
}
    </script>
</body>
</html>