<?php
session_start();

// Check if user is logged in and has correct role
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != "customer") {
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

$update_message = "";
$user_id = $_SESSION['id'];

// Get current user data
$stmt = $conn->prepare("SELECT username, phone_no FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Get customer profile data if exists
$customer_data = [];
$stmt = $conn->prepare("SELECT * FROM customer_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $customer_data = $result->fetch_assoc();
}
$stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $phone_no = $_POST['phone_no'];
    $email = $_POST['email'];
    $dietary_preference = $_POST['dietary_preference'];
    $house_no = $_POST['house_no'];
    $street = $_POST['street'];
    $area = $_POST['area'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    
    // Update phone number in users table
    $stmt = $conn->prepare("UPDATE users SET phone_no = ? WHERE id = ?");
    $stmt->bind_param("si", $phone_no, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Check if customer profile already exists
    $stmt = $conn->prepare("SELECT id FROM customer_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing profile
        $stmt = $conn->prepare("UPDATE customer_profiles SET full_name = ?, email = ?, dietary_preference = ?, house_no = ?, street = ?, area = ?, city = ?, state = ? WHERE user_id = ?");
        $stmt->bind_param("ssssssssi", $full_name, $email, $dietary_preference, $house_no, $street, $area, $city, $state, $user_id);
    } else {
        // Create new profile
        $stmt = $conn->prepare("INSERT INTO customer_profiles (user_id, full_name, email, dietary_preference, house_no, street, area, city, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssss", $user_id, $full_name, $email, $dietary_preference, $house_no, $street, $area, $city, $state);
    }
    
    if ($stmt->execute()) {
        $update_message = "Profile updated successfully!";
        
        // Update session data
        $_SESSION['profile_completed'] = true;
        
        // Redirect to dashboard after short delay
        header("refresh:2;url=customer_dashboard.php");
    } else {
        $update_message = "Error updating profile: " . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saapaadu - Customer Profile</title>
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
            justify-content: center;
            align-items: center;
            padding-top: 250px;
            margin-bottom: 200px;;
            min-height: 100vh;
        }

        .profile-container {
            background-color: #f2d32c;
            padding: 40px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            animation: fadeIn 0.8s ease;
            margin: 40px 0;
        }

        .profile-title {
            color: #1a1a1a;
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .form-label {
            color: #0066cc;
            font-size: 1.2rem;
            width: 180px;
            flex-shrink: 0;
        }

        .form-input {
            flex: 1;
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
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
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
        <div class="logo">Saapaadu</div>
        <nav>
            <a href="index.php" class="nav-link">Home</a>
            <a href="customer_dashboard.php" class="nav-link">Dashboard</a>
            <a href="customer_profile.php" class="nav-link">Profile</a>
            <a href="logout.php" class="nav-link">Logout</a>
            <a href="#" class="nav-link" id="contacts">Contact</a>
        </nav>
    </header>

    <main>
        <div class="profile-container">
            <h1 class="profile-title">Customer Profile</h1>
            <form id="profile-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-input" value="<?php echo isset($customer_data['full_name']) ? htmlspecialchars($customer_data['full_name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone_no" class="form-label">Phone Number</label>
                    <input type="text" id="phone_no" name="phone_no" class="form-input" value="<?php echo htmlspecialchars($user_data['phone_no']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input" value="<?php echo isset($customer_data['email']) ? htmlspecialchars($customer_data['email']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="dietary_preference" class="form-label">Dietary Preference</label>
                    <select id="dietary_preference" name="dietary_preference" class="form-input" required>
                        <option value="">-- Select --</option>
                        <option value="veg" <?php echo (isset($customer_data['dietary_preference']) && $customer_data['dietary_preference'] == 'veg') ? 'selected' : ''; ?>>Vegetarian</option>
                        <option value="non-veg" <?php echo (isset($customer_data['dietary_preference']) && $customer_data['dietary_preference'] == 'non-veg') ? 'selected' : ''; ?>>Non-Vegetarian</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="house_no" class="form-label">House Number</label>
                    <input type="text" id="house_no" name="house_no" class="form-input" value="<?php echo isset($customer_data['house_no']) ? htmlspecialchars($customer_data['house_no']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="street" class="form-label">Street</label>
                    <input type="text" id="street" name="street" class="form-input" value="<?php echo isset($customer_data['street']) ? htmlspecialchars($customer_data['street']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="area" class="form-label">Area</label>
                    <input type="text" id="area" name="area" class="form-input" value="<?php echo isset($customer_data['area']) ? htmlspecialchars($customer_data['area']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="city" class="form-label">City</label>
                    <input type="text" id="city" name="city" class="form-input" value="<?php echo isset($customer_data['city']) ? htmlspecialchars($customer_data['city']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="state" class="form-label">State</label>
                    <input type="text" id="state" name="state" class="form-input" value="<?php echo isset($customer_data['state']) ? htmlspecialchars($customer_data['state']) : ''; ?>" required>
                </div>
                
                <?php if($update_message): ?>
                <div class="message"><?php echo $update_message; ?></div>
                <?php endif; ?>
                
                <button type="submit" class="submit-btn">SAVE PROFILE</button>
            </form>
        </div>
    </main>
    <footer>
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
        </footer>

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