<?php
session_start();

// Check if user is logged in and has vendor role
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'vendor') {
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

$user_id = $_SESSION['id'];
$username = $_SESSION['username'];

// Get existing user data
$stmt = $conn->prepare("SELECT username, phone_no FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Check if vendor profile already exists
$stmt = $conn->prepare("SELECT * FROM vendor_profiles WHERE user_id = ?");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_exists = $result->num_rows > 0;
$profile_data = $profile_exists ? $result->fetch_assoc() : null;
$stmt->close();

$profile_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone_no = $_POST['phone_no'];
    $food_type = $_POST['food_type'];
    $shop_name = $_POST['shop_name'];
    $shop_no = $_POST['shop_no'];
    $street = $_POST['street'];
    $area = $_POST['area'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $latitude = floatval($_POST['latitude']);
$longitude = floatval($_POST['longitude']);


    
    // Handle file upload
    $shop_image = "";
    if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $target_file = $target_dir . basename($_FILES["shop_image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if it's an actual image
        $check = getimagesize($_FILES["shop_image"]["tmp_name"]);
        if ($check !== false) {
            // Create unique filename
            $shop_image = $target_dir . uniqid() . '.' . $imageFileType;
            if (move_uploaded_file($_FILES["shop_image"]["tmp_name"], $shop_image)) {
                // File uploaded successfully
            } else {
                $profile_message = "Sorry, there was an error uploading your file.";
            }
        } else {
            $profile_message = "File is not an image.";
        }
    } else if ($profile_exists && !empty($profile_data['shop_image'])) {
        // Keep existing image if available
        $shop_image = $profile_data['shop_image'];
    }
    
    if ($profile_exists) {
        // Update existing profile
        $stmt = $conn->prepare("UPDATE vendor_profiles SET full_name = ?, email = ?, food_type = ?, shop_name = ?, shop_no = ?, street = ?, area = ?, city = ?, state = ?, shop_image = ?, latitude = ?, longitude = ? WHERE user_id = ?");
$stmt->bind_param("ssssssssssddi", $full_name, $email, $food_type, $shop_name, $shop_no, $street, $area, $city, $state, $shop_image, $latitude, $longitude, $user_id);

    } else {
        // Insert new profile
        $stmt = $conn->prepare("INSERT INTO vendor_profiles (user_id, full_name, email, food_type, shop_name, shop_no, street, area, city, state, shop_image, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssssssssdd", $user_id, $full_name, $email, $food_type, $shop_name, $shop_no, $street, $area, $city, $state, $shop_image, $latitude, $longitude);

    }
    
    // Update phone number in users table
    $update_user = $conn->prepare("UPDATE users SET phone_no = ? WHERE id = ?");
    $update_user->bind_param("si", $phone_no, $user_id);
    $update_user->execute();
    $update_user->close();
    
    if ($stmt->execute()) {
        $profile_message = "Profile updated successfully!";
        // Refresh profile data
        $stmt = $conn->prepare("SELECT * FROM vendor_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile_data = $result->fetch_assoc();
        $profile_exists = true;
    } else {
        $profile_message = "Error updating profile: " . $stmt->error;
    }
    $stmt->close();
}

// Get latest user data
$stmt = $conn->prepare("SELECT username, phone_no FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saapaadu - Vendor Profile</title>
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
    padding-top: 100px; /* Pushes content below the fixed header */
    padding-bottom: 40px;
    min-height: 100vh;
    margin-bottom: 700px;
}



        .profile-container {
            background-color: #f2d32c;
            padding: 40px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            animation: fadeIn 0.8s ease;
        }

        .profile-title {
            color: #1a1a1a;
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
            flex: 1;
        }

        .form-label {
            color: #0066cc;
            font-size: 1.2rem;
            display: block;
            margin-bottom: 5px;
        }

        .form-input {
            width: 100%;
            padding: 10px 15px;
            border-radius: 20px;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }

        .submit-btn {
            display: block;
            width: 150px;
            margin: 30px auto 0;
            padding: 12px;
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
            color: #4caf50;
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        .dashboard-link {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 12px;
            background-color: #7c5295;
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dashboard-link:hover {
            background-color: #6a4580;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(124, 82, 149, 0.4);
        }

        @keyframes fadeIn {
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
            .profile-container {
                width: 95%;
                padding: 25px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
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
    bottom: 0;
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
        <div class="logo">Saapaadu</div>
        <nav>
            <a href="index.php" class="nav-link">Home</a>
            <a href="vendor_dashboard.php" class="nav-link">Dashboard</a>
            <a href="logout.php" class="nav-link">Logout</a>
            <a href="#" class="nav-link" id="contacts">Contact</a>
        </nav>
    </header>

    <main>
        <div class="profile-container">
            <h1 class="profile-title">Vendor Profile</h1>
            
            <?php if ($profile_message): ?>
            <div class="message"><?php echo $profile_message; ?></div>
            <?php endif; ?>
            
            <form id="profile-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" class="form-input" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['full_name']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone_no" class="form-label">Phone Number</label>
                        <input type="text" id="phone_no" name="phone_no" class="form-input" value="<?php echo htmlspecialchars($user_data['phone_no']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['email']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="food_type" class="form-label">Food Type</label>
                        <select id="food_type" name="food_type" class="form-input" required>
                            <option value="Veg" <?php echo ($profile_exists && $profile_data['food_type'] == 'Veg') ? 'selected' : ''; ?>>Veg</option>
                            <option value="Non-Veg" <?php echo ($profile_exists && $profile_data['food_type'] == 'Non-Veg') ? 'selected' : ''; ?>>Non-Veg</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="shop_name" class="form-label">Shop Name</label>
                        <input type="text" id="shop_name" name="shop_name" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['shop_name']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shop_no" class="form-label">Shop No.</label>
                        <input type="text" id="shop_no" name="shop_no" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['shop_no']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="street" class="form-label">Street</label>
                        <input type="text" id="street" name="street" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['street']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="area" class="form-label">Area</label>
                        <input type="text" id="area" name="area" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['area']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label">City</label>
                        <input type="text" id="city" name="city" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['city']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="state" class="form-label">State</label>
                    <input type="text" id="state" name="state" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['state']) : ''; ?>" required>
                </div>

                <div class="form-row">
    <div class="form-group">
        <label for="latitude" class="form-label">Latitude:</label>
        <input type="text" id="latitude" name="latitude" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['latitude']) : ''; ?>" required>
    </div>
    <div class="form-group">
        <label for="longitude" class="form-label">Longitude:</label>
        <input type="text" id="longitude" name="longitude" class="form-input" value="<?php echo $profile_exists ? htmlspecialchars($profile_data['longitude']) : ''; ?>" required>
    </div>
</div>

                
                <div class="form-group">
                    <label for="shop_image" class="form-label">Shop Image</label>
                    <input type="file" id="shop_image" name="shop_image" class="form-input">
                    <?php if ($profile_exists && !empty($profile_data['shop_image'])): ?>
                    <p style="margin-top: 5px; font-size: 0.9rem;">Current image: <?php echo basename($profile_data['shop_image']); ?></p>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="submit-btn">SAVE PROFILE</button>
            </form>
            
            <?php if ($profile_exists): ?>
            <a href="vendor_dashboard.php" class="dashboard-link">GO TO DASHBOARD</a>
            <?php endif; ?>
        </div>
    </main>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const profileForm = document.getElementById('profile-form');
            
            profileForm.addEventListener('submit', function(e) {
                // Form will be handled by PHP, no need to prevent default
                const submitBtn = document.querySelector('.submit-btn');
                submitBtn.textContent = 'Saving...';
                submitBtn.style.backgroundColor = '#0097a7';
            });
        });
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