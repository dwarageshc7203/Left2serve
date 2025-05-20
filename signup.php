<?php
session_start();
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

$signup_error = "";
$signup_success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $phone_no = $_POST['phone_no'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Check if username already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $signup_error = "Username already exists!";
    } else {
        // Hash password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $insert_stmt = $conn->prepare("INSERT INTO users (username, phone_no, password, role) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("ssss", $username, $phone_no, $hashed_password, $role);
        
        if ($insert_stmt->execute()) {
            $signup_success = "Registration successful! You can now login.";
            // Redirect to login page after a short delay
            header("refresh:2;url=login.php");
        } else {
            $signup_error = "Error: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saapaadu - Sign Up</title>
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
            padding-top: 60px;
            min-height: 100vh;
        }

        .signup-container {
            background-color: #f2d32c;
            padding: 40px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            animation: slideIn 0.8s ease;
        }

        .signup-title {
            color: #1a1a1a;
            font-size: 3.5rem;
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
            font-size: 1.5rem;
            width: 150px;
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
            width: 120px;
            margin: 30px auto 0;
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

        .error-message {
            color: #ff3333;
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }

        .success-message {
            color: #4caf50;
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (max-width: 768px) {
            .signup-container {
                width: 90%;
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
    </style>
</head>
<body>
    <header>
        <div class="logo">Saapaadu</div>
        <nav>
            <a href="homepage.html" class="nav-link">Home</a>
            <a href="login.php" class="nav-link">Login</a>
            <a href="signup.php" class="nav-link">Sign Up</a>
            <a href="#" class="nav-link">Contact</a>
        </nav>
    </header>

    <main>
        <div class="signup-container">
            <h1 class="signup-title">Sign Up</h1>
            <form id="signup-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="phone_no" class="form-label">Phone Number</label>
                    <input type="number" id="phone_no" name="phone_no" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-input" required>
                        <option value="">-- Choose Role --</option>
                        <option value="customer">Customer</option>
                        <option value="vendor">Vendor</option>
                    </select>
                </div>
                <?php if($signup_error): ?>
                <div class="error-message"><?php echo $signup_error; ?></div>
                <?php endif; ?>
                <?php if($signup_success): ?>
                <div class="success-message"><?php echo $signup_success; ?></div>
                <?php endif; ?>
                <button type="submit" class="submit-btn">SUBMIT</button>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const signupForm = document.getElementById('signup-form');

            signupForm.addEventListener('submit', function(e) {
                // Form will be handled by PHP, no need to prevent default
                const submitBtn = document.querySelector('.submit-btn');
                submitBtn.textContent = 'Processing...';
                submitBtn.style.backgroundColor = '#0097a7';
            });
        });
    </script>
</body>
</html>