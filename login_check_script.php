<?php
// Add this at the top of customer_dashboard.php after session_start() to debug login status
session_start();

echo "<!-- LOGIN DEBUG START -->";
echo "<!-- Session loggedin: " . (isset($_SESSION['loggedin']) ? $_SESSION['loggedin'] : 'NOT SET') . " -->";
echo "<!-- Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET') . " -->";
echo "<!-- Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . " -->";
echo "<!-- Session id: " . (isset($_SESSION['id']) ? $_SESSION['id'] : 'NOT SET') . " -->";
echo "<!-- LOGIN DEBUG END -->";

// If no valid user ID found, this might be the issue
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    echo "<!-- WARNING: No user ID found in session - user might not be properly logged in -->";
}
?>