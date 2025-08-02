<?php
/**** .htaccess file -- NO CHANGES NEEDED FOR THIS PART ***********
# --- Human Verification Gate ---

# Set the secret cookie name and value.
# Any user who has this cookie is considered verified.
# Change "SomeLongRandomString..." to your own secret value.
RewriteCond %{HTTP_COOKIE} !noauth=VerifiedHuman_SomeLongRandomString_98765
RewriteCond %{REQUEST_URI} !^/(noauth2\.php|favicon\.ico|errordocuments/|fonts/|\.well-known/)
RewriteRule ^(.*)$ /noauth2.php?target=%{REQUEST_URI} [R,L]
*********************************/

// --- CONFIGURATION ---
// This MUST match the value in your .htaccess file!
define('COOKIE_NAME', 'noauth');
define('COOKIE_VALUE', 'VerifiedHuman_SomeLongRandomString_98765');
define('COOKIE_EXPIRATION_DAYS', 30);

// Start a session to store a challenge token. This helps prevent CSRF and replay attacks.
session_start();

$errorMessage = '';

// --- SERVER-SIDE VERIFICATION LOGIC ---
// This block runs only when the form is submitted (a POST request).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checksPassed = true;
		error_log('CAPTCHA testing started...');

    // 1. Verify the anti-forgery token.
    if (!isset($_POST['challenge_token']) || !hash_equals($_SESSION['challenge_token'], $_POST['challenge_token'])) {
        $checksPassed = false;
        $errorMessage = 'Invalid session token. Please try again.';
    }

    // 2. Check the honeypot field. It must be empty.
    if ($checksPassed && (!isset($_POST['user_email']) || !empty($_POST['user_email']))) {
        $checksPassed = false;
        // Generic error message to avoid revealing the technique.
        $errorMessage = 'Automatic verification failed (0xfeed1).';
        // Log the specific reason for internal review.
        error_log('CAPTCHA Failure: Honeypot field was filled.');
    }

    // 3. Check client-side metrics.
    $timeToSubmit = isset($_POST['time_to_submit']) ? (int)$_POST['time_to_submit'] : 0;
    $mouseTravelDistance = isset($_POST['mouse_travel']) ? (int)$_POST['mouse_travel'] : 0;

    // A human needs a moment to read and react. 500ms is a reasonable minimum.
    if ($checksPassed && $timeToSubmit < 500) {
        $checksPassed = false;
        $errorMessage = 'Automatic verification failed (0xfeed2).';
        error_log('CAPTCHA Failure: Submission was too fast (' . $timeToSubmit . 'ms).');
    }

    // A human will move their mouse. A bot might not, or might jump directly to the button.
    // A threshold of 100 pixels is a good starting point.
    if ($checksPassed && $mouseTravelDistance < 100) {
        $checksPassed = false;
        $errorMessage = 'Automatic verification failed (0xfeed3).';
        error_log('CAPTCHA Failure: Insufficient mouse travel (' . $mouseTravelDistance . 'px).');
    }
    
    // 4. (Optional but recommended) Check basic browser fingerprint.
    // Here, we just check if it was submitted. More complex logic could be added.
    if ($checksPassed && empty($_POST['browser_fp'])) {
        $checksPassed = false;
        $errorMessage = 'Automatic verification failed (0xfeed4).';
        error_log('CAPTCHA Failure: Browser fingerprint was missing.');
    }


    // --- FINAL DECISION ---
    if ($checksPassed) {
        // 1. Set the verification cookie on the server-side.
        $cookieExpiration = time() + (COOKIE_EXPIRATION_DAYS * 24 * 60 * 60);
        // Use HttpOnly and Secure flags for better security.
        setcookie(COOKIE_NAME, COOKIE_VALUE, [
            'expires' => $cookieExpiration,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => true, // Set to true if you are using HTTPS
            'httponly' => true // The cookie cannot be accessed by JavaScript
        ]);

        // 2. Get the destination URL.
        $target = filter_input(INPUT_GET, 'target', FILTER_SANITIZE_URL);
        // Ensure the target is a relative path starting with '/' for security.
        $destination = ($target && strpos($target, '/') === 0) ? $target : '/';

        // 3. Redirect the user.
        header('Location: ' . $destination);
        exit; // Important to stop script execution after a redirect.
    }
    // If checks fail, the page will re-render below, displaying the $errorMessage.
}

// Generate a new, unique token for this page load.
// This is stored in the session and placed in a hidden form field.
$_SESSION['challenge_token'] = bin2hex(random_bytes(32));

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifying you are human...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
            color: #333;
        }
        .container {
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 90%;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            color: #666;
        }
        .honeypot {
            position: absolute;
            left: -9999px;
            top: -9999px;
        }
        .continue-button {
            padding: 12px 25px;
            font-size: 18px;
            font-weight: bold;
            color: white;
            background-color: #007bff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s, opacity 0.2s;
            margin-top: 20px;
        }
        .continue-button:hover {
            background-color: #0056b3;
        }
        .continue-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .result {
            margin-top: 20px;
            font-weight: bold;
            font-size: 16px;
            color: #dc3545; /* Failure color */
            min-height: 24px; /* Reserve space to prevent layout shift */
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Please Wait</h1>
        <p>We are just checking a few things before we send you on your way.</p>
        
        <!-- The form now POSTs to the script itself for server-side validation -->
        <form id="verification-form" method="POST">
            <!-- 1. Honeypot: Still a valuable and simple check -->
            <div class="honeypot">
                <label for="user_email">Please leave this field empty</label>
                <input type="email" id="user_email" name="user_email" tabindex="-1" autocomplete="off">
            </div>

            <!-- 2. Security Token: Prevents CSRF -->
            <input type="hidden" name="challenge_token" value="<?php echo htmlspecialchars($_SESSION['challenge_token']); ?>">

            <!-- 3. Hidden fields for client-side metrics -->
            <input type="hidden" id="time_to_submit" name="time_to_submit">
            <input type="hidden" id="mouse_travel" name="mouse_travel">
            <input type="hidden" id="browser_fp" name="browser_fp">

            <button type="submit" id="continue-btn" class="continue-button">Click here to Continue</button>
        </form>

        <div id="result-display" class="result">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    </div>

    <script>
        (function() {
            "use strict";

            // --- State variables ---
            const pageLoadTime = Date.now();
            let lastMousePos = { x: -1, y: -1 };
            let totalMouseTravel = 0;

            // --- DOM Elements ---
            const form = document.getElementById('verification-form');
            const button = document.getElementById('continue-btn');
            const timeToSubmitInput = document.getElementById('time_to_submit');
            const mouseTravelInput = document.getElementById('mouse_travel');
            const browserFpInput = document.getElementById('browser_fp');

            // --- NEW: Enhanced Human Detection ---

            // 1. Track total mouse travel distance
            document.addEventListener('mousemove', function(e) {
                if (lastMousePos.x > -1) {
                    const dx = e.clientX - lastMousePos.x;
                    const dy = e.clientY - lastMousePos.y;
                    totalMouseTravel += Math.sqrt(dx*dx + dy*dy);
                }
                lastMousePos = { x: e.clientX, y: e.clientY };
            }, { passive: true });

            // 2. Gather a basic browser fingerprint
            function getBrowserFingerprint() {
                const fp = {
                    ua: navigator.userAgent,
                    lang: navigator.language,
                    res: `${screen.width}x${screen.height}`,
                    cd: screen.colorDepth,
                    tz: new Date().getTimezoneOffset()
                };
                // Convert to a string to be sent to the server
                return JSON.stringify(fp);
            }

            // --- Form Submission Logic ---
            form.addEventListener('submit', function(event) {
                // When the user clicks submit, populate the hidden fields with our collected data.
                
                // a. Calculate time spent on page
                timeToSubmitInput.value = Date.now() - pageLoadTime;

                // b. Store total mouse travel
                mouseTravelInput.value = Math.round(totalMouseTravel);
                
                // c. Store browser fingerprint
                browserFpInput.value = getBrowserFingerprint();

                // Briefly disable the button to prevent double submission
                button.disabled = true;
                button.textContent = "Verifying...";
                
                // The form will now submit normally to the server.
            });
        })();
    </script>

</body>
</html>