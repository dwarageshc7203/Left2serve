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

// Default food preference
$food_preference = isset($_GET['preference']) ? $_GET['preference'] : 'Veg';

// Get all hotspots based on preference
$hotspots_query = "SELECT h.*, v.vendor_name FROM hotspots h JOIN vendor_profiles v ON h.vendor_id = v.id WHERE h.food_type = ?";
$stmt = $conn->prepare($hotspots_query);
$stmt->bind_param("s", $food_preference);

$stmt->execute();
$result = $stmt->get_result();

$hotspots = [];
while ($row = $result->fetch_assoc()) {
    $now = new DateTime();
$expires = new DateTime($row['available_until']);
$interval = $now->diff($expires);
$hoursLeft = $expires > $now ? $interval->h + ($interval->days * 24) : 0;
$row['hours_left'] = $hoursLeft;

    $hotspots[] = $row;
}
$stmt->close();
$conn->close();
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
            padding-top: 60px;
            min-height: calc(100vh - 60px);
        }

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
            border-bottom: 1px solid #444;
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

        /* Map container */
        .map-container {
            flex: 6;
            background-color: #f0e68c;
            height: calc(100vh - 100px);
            margin-top: 40px;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        /* Hotspots list */
        .hotspots-container {
            flex: 4;
            padding: 20px;
            margin-top: 40px;
            background-color: #b2c7e8;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }

        .hotspot-card {
            background-color: #d1e0f5;
            border-radius: 10px;
            margin-bottom: 15px;
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .hotspot-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .hotspot-title {
            color: #1a1a1a;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .hotspot-type {
            background-color: #7c5295;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .hotspot-info {
            color: #0066cc;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .hotspot-timer {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #00bcd4;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }

        .location-icon {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 5px;
        }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: #f2d32c;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
            animation: slideIn 0.4s ease;
        }

        .modal-title {
            color: #1a1a1a;
            font-size: 2rem;
            margin-bottom: 20px;
            text-align: center;
        }

        .modal-body {
            color: #333;
            font-size: 1.1rem;
        }

        .modal-info {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }

        .modal-info-label {
            font-weight: bold;
            color: #0066cc;
        }

        .modal-info-value {
            text-align: right;
        }

        .modal-close {
            color: #1a1a1a;
            font-size: 2rem;
            position: absolute;
            top: 15px;
            right: 25px;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #0066cc;
        }

        @keyframes fadeIn {
            from {opacity: 0}
            to {opacity: 1}
        }

        @keyframes slideIn {
            from {transform: translateY(-50px); opacity: 0;}
            to {transform: translateY(0); opacity: 1;}
        }

        @media (max-width: 768px) {
            main {
                flex-direction: column;
            }

            .map-container, .hotspots-container {
                flex: none;
                width: 100%;
                height: 50vh;
                max-height: 50vh;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Saapaadu</div>
        <nav>
            <a href="customer_dashboard.php" class="nav-link">Dashboard</a>
            <a href="customer_profile.php" class="nav-link">Profile</a>
            <a href="logout.php" class="nav-link">Logout</a>
        </nav>
    </header>

    <div class="filter-bar">
        <div class="preference-toggle">
            <span>Displaying contents for <b><?php echo $food_preference; ?></b></span>
            <button class="change-btn" id="togglePreference">(change?)</button>
        </div>
    </div>

    <main>
        <div class="map-container">
            <div id="map"></div>
        </div>
        <div class="hotspots-container">
            <?php foreach ($hotspots as $hotspot): ?>
            <div class="hotspot-card" data-id="<?php echo $hotspot['id']; ?>">
                <h2 class="hotspot-title"><?php echo $hotspot['shop_name']; ?></h2>
                <div class="hotspot-type"><?php echo $hotspot['food_type']; ?></div>
                <p class="hotspot-info">
                    <svg class="location-icon" fill="#0066cc" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <?php echo $hotspot['address']; ?>
                </p>
                <p class="hotspot-info">Food: <?php echo $hotspot['food_item']; ?></p>
                <div class="hotspot-timer">
    <?php
    $now = new DateTime();
    $expires = new DateTime($hotspot['available_until']);
    $interval = $now->diff($expires);
    $hoursLeft = $expires > $now ? $interval->h + ($interval->days * 24) : 0;
    echo $hoursLeft . " Hour(s) left";
    ?>
</div>

            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Modal for hotspot details -->
    <div id="hotspotModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 class="modal-title" id="modalTitle"></h2>
            <div class="modal-body">
                <div class="modal-info">
                    <span class="modal-info-label">Food Type:</span>
                    <span class="modal-info-value" id="modalFoodType"></span>
                </div>
                <div class="modal-info">
                    <span class="modal-info-label">Food Item:</span>
                    <span class="modal-info-value" id="modalFoodItem"></span>
                </div>
                <div class="modal-info">
                    <span class="modal-info-label">Address:</span>
                    <span class="modal-info-value" id="modalAddress"></span>
                </div>
                <div class="modal-info">
                    <span class="modal-info-label">Meal Count:</span>
                    <span class="modal-info-value" id="modalMealCount"></span>
                </div>
                <div class="modal-info">
                    <span class="modal-info-label">Time Left:</span>
                    <span class="modal-info-value" id="modalTimer"></span>
                </div>
                <div class="modal-info">
                    <span class="modal-info-label">Price:</span>
                    <span class="modal-info-value" id="modalPrice"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS for map -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            var map = L.map('map').setView([13.0827, 80.2707], 13); // Chennai coordinates

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add markers for each hotspot
            const hotspots = <?php echo json_encode($hotspots); ?>;
            const hotspotMarkers = {};

            hotspots.forEach(hotspot => {
                // Assuming coordinates are stored as latitude and longitude in the database
                // If not, you would need to geocode the addresses
                const coords = [parseFloat(hotspot.latitude) || 13.0827, parseFloat(hotspot.longitude) || 80.2707];
                
                // Create custom icon based on food type
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

                const marker = L.marker(coords, {icon: customIcon}).addTo(map);
                marker.bindPopup(`<b>${hotspot.shop_name}</b><br>${hotspot.food_item}<br>${hotspot.timer} Hour left`);
                
                // Store marker reference
                hotspotMarkers[hotspot.id] = marker;
                
                // Add click event
                marker.on('click', function() {
                    showHotspotDetails(hotspot);
                });
            });

            // Toggle preference button
            document.getElementById('togglePreference').addEventListener('click', function() {
                const currentPreference = '<?php echo $food_preference; ?>';
                const newPreference = currentPreference === 'Veg' ? 'Non-Veg' : 'Veg';
                window.location.href = `customer_dashboard.php?preference=${newPreference}`;
            });

            // Hotspot card click event
            const hotspotCards = document.querySelectorAll('.hotspot-card');
            hotspotCards.forEach(card => {
                card.addEventListener('click', function() {
                    const hotspotId = this.getAttribute('data-id');
                    const hotspot = hotspots.find(h => h.id == hotspotId);
                    
                    if (hotspot) {
                        // Center map on this hotspot
                        const marker = hotspotMarkers[hotspotId];
                        if (marker) {
                            map.setView(marker.getLatLng(), 16);
                            marker.openPopup();
                        }
                        
                        // Show details modal
                        showHotspotDetails(hotspot);
                    }
                });
            });

            // Modal functionality
            const modal = document.getElementById('hotspotModal');
            const closeBtn = document.querySelector('.modal-close');
            
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Function to show hotspot details in modal
            function showHotspotDetails(hotspot) {
                document.getElementById('modalTitle').textContent = hotspot.shop_name;
                document.getElementById('modalFoodType').textContent = hotspot.food_type;
                document.getElementById('modalFoodItem').textContent = hotspot.food_item;
                document.getElementById('modalAddress').textContent = hotspot.address;
                document.getElementById('modalMealCount').textContent = hotspot.meal_count + ' meals available';
                document.getElementById('modalTimer').textContent = hotspot.timer + ' Hour left';
                document.getElementById('modalPrice').textContent = '₹' + hotspot.price + ' per meal';
                
                modal.style.display = 'block';
            }
        });
    </script>
</body>
</html>