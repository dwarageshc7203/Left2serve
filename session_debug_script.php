<?php
// Create this as debug_session.php to check what's in your session
session_start();

echo "<h2>Session Debug Information</h2>";
echo "<h3>All Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Specific Session Values:</h3>";
echo "loggedin: " . (isset($_SESSION['loggedin']) ? $_SESSION['loggedin'] : 'NOT SET') . "<br>";
echo "role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET') . "<br>";
echo "user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
echo "id: " . (isset($_SESSION['id']) ? $_SESSION['id'] : 'NOT SET') . "<br>";
echo "username: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'NOT SET') . "<br>";

// Check if it might be stored under a different name
echo "<h3>Possible Alternative Session Keys:</h3>";
foreach ($_SESSION as $key => $value) {
    if (strpos(strtolower($key), 'id') !== false || strpos(strtolower($key), 'user') !== false) {
        echo "$key: $value<br>";
    }
}
?>