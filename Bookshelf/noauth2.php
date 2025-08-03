<?php
// --- CONFIGURATION ---

// -- Core Settings --
define('COOKIE_NAME', 'noauth');
define('COOKIE_VALUE', 'VerifiedHuman_SomeLongRandomString_98765');
define('COOKIE_EXPIRATION_DAYS', 30);
// Choose the verification mode:
//   'REACTION' (Recommended): Verifies the user based on the time between the button appearing and their click.
//   'IGNORE_CLICKS' (Experimental): Verifies the user only after they have clicked the button a certain number of times.
define('CAPTCHA_MODE', 'IGNORE_CLICKS');
// If using 'IGNORE_CLICKS' mode, this is the number of clicks to ignore. The user must click N+1 times.
define('CLICKS_TO_IGNORE', 2);

// -- Start session for CSRF, challenge tracking, and thresholds --
session_start();

$errorMessage = '';
$success = false;

// --- Generate Per-Session Randomized Thresholds ---
if (empty($_SESSION['thresholds'])) {
    // Randomize thresholds so bots can't hardcode them.
    $_SESSION['thresholds'] = [
        'min_time_to_submit_ms' => rand(2500, 5000),   // Total time on page. Increased to account for button delay.
        'min_mouse_travel_px'   => rand(50, 150),      // Minimum mouse travel distance.
        'button_appear_delay_ms'=> rand(2000, 4000),   // How long to wait before showing the real button.
        'min_reaction_time_ms'  => rand(150, 400),     // (REACTION mode) Minimum time between button fade-in and click.
    ];
}
$thresholds = $_SESSION['thresholds'];

// --- Generate a unique ID for the real button per session ---
if (empty($_SESSION['real_button_id'])) {
    $_SESSION['real_button_id'] = 'btn-real-' . bin2hex(random_bytes(6));
}
$realButtonId = $_SESSION['real_button_id'];

// --- SERVER-SIDE VERIFICATION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checksPassed = true;
    error_log('CAPTCHA verification started...');

    // 1. CSRF Token Check: Prevents cross-site request forgery.
    if (!isset($_POST['challenge_token']) || !hash_equals($_SESSION['challenge_token'], $_POST['challenge_token'])) {
        $checksPassed = false;
        $errorMessage = 'Session expired or invalid. Please reload the page.';
        error_log('CAPTCHA Failure: CSRF token mismatch.');
    }

    // 2. Honeypot Button Check: Checks if the invisible honeypot button was clicked.
    if ($checksPassed && isset($_POST['honeypot_submit'])) {
        $checksPassed = false;
        $errorMessage = 'Automatic verification failed (HP).';
        error_log('CAPTCHA Failure: Honeypot submit button was clicked.');
    }

    // 3. Time on Page Check: Ensures the user didn't submit the form inhumanly fast.
    $timeToSubmit = isset($_POST['time_to_submit']) ? (int)$_POST['time_to_submit'] : 0;
    if ($checksPassed && $timeToSubmit < $thresholds['min_time_to_submit_ms']) {
        $checksPassed = false;
        $errorMessage = 'Verification failed (TF1).';
        error_log("CAPTCHA Failure: Submission was too fast ({$timeToSubmit}ms, threshold {$thresholds['min_time_to_submit_ms']}ms).");
    }

    // 4. Mouse Movement Check: Ensures there was some mouse activity.
    $mouseTravelDistance = isset($_POST['mouse_travel']) ? (int)$_POST['mouse_travel'] : 0;
    if ($checksPassed && $mouseTravelDistance < $thresholds['min_mouse_travel_px']) {
        $checksPassed = false;
        $errorMessage = 'Verification failed (MT).';
        error_log("CAPTCHA Failure: Insufficient mouse travel ({$mouseTravelDistance}px, threshold {$thresholds['min_mouse_travel_px']}px).");
    }

    // 5. Behavioral Check (Mode-Dependent)
    if ($checksPassed) {
        if (CAPTCHA_MODE === 'REACTION') {
            $reactionTime = isset($_POST['reaction_time']) ? (int)$_POST['reaction_time'] : 0;
            if ($reactionTime < $thresholds['min_reaction_time_ms']) {
                $checksPassed = false;
                $errorMessage = 'Verification failed (TF2).';
                error_log("CAPTCHA Failure: Reaction time was too fast ({$reactionTime}ms, threshold {$thresholds['min_reaction_time_ms']}ms).");
            }
        } elseif (CAPTCHA_MODE === 'IGNORE_CLICKS') {
            $clickCount = isset($_POST['click_count']) ? (int)$_POST['click_count'] : 0;
            // The successful click is N+1.
            if ($clickCount <= CLICKS_TO_IGNORE) {
                $checksPassed = false;
                $errorMessage = 'Verification failed (NEC).';
                error_log("CAPTCHA Failure: Not enough clicks in IGNORE_CLICKS mode (got {$clickCount}, needed > " . CLICKS_TO_IGNORE . ").");
            }
        }
    }


    // --- FINAL DECISION ---
    if ($checksPassed) {
        $cookieExpiration = time() + (COOKIE_EXPIRATION_DAYS * 24 * 60 * 60);
        setcookie(COOKIE_NAME, COOKIE_VALUE, [
            'expires' => $cookieExpiration,
            'path' => '/',
            'samesite' => 'Strict',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true
        ]);
        // Clean up session data
        unset($_SESSION['challenge_token'], $_SESSION['thresholds'], $_SESSION['real_button_id']);

        $target = filter_input(INPUT_GET, 'target', FILTER_SANITIZE_URL);
        $destination = ($target && strpos($target, '/') === 0) ? $target : '/';
        header('Location: ' . $destination);
        exit;
    }
    // If checks fail, the page will re-render below, displaying the $errorMessage.
}

// Generate a new, unique token for this page load.
$_SESSION['challenge_token'] = bin2hex(random_bytes(32));

// Prepare a configuration object to pass to JavaScript securely.
$jsConfig = [
    'captchaMode' => CAPTCHA_MODE,
    'clicksToIgnore' => CLICKS_TO_IGNORE,
    'buttonAppearDelay' => $thresholds['button_appear_delay_ms'],
    'realButtonId' => $realButtonId,
    'initialButtonText' => 'Click to Continue',
    'verifyingButtonText' => 'Verifying...'
];

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
            min-height: 48px; /* Reserve space for message */
        }
        .honeypot-button {
            position: absolute;
            left: -9999px;
            top: -9999px;
        }
        .button-container {
            min-height: 50px; /* Prevent layout shift when button appears */
            margin-top: 20px;
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
            transition: background-color 0.2s, opacity 2.5s ease-in-out;
            opacity: 0; /* Start invisible */
        }
        .continue-button.visible {
            opacity: 1; /* Fade to visible */
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
            color: #dc3545;
            min-height: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Please Wait</h1>
        <p id="status-message">We are running a quick check to verify you're a human.</p>

        <form id="verification-form" method="POST" autocomplete="off">
            <!-- 1. Honeypot Button: For simple bots. It's a submit button hidden off-screen. -->
            <button type="submit" name="honeypot_submit" class="honeypot-button" tabindex="-1" aria-hidden="true">Submit</button>

            <!-- 2. Security Token -->
            <input type="hidden" name="challenge_token" value="<?php echo htmlspecialchars($_SESSION['challenge_token']); ?>">

            <!-- 3. Hidden fields for client metrics -->
            <input type="hidden" id="time_to_submit" name="time_to_submit">
            <input type="hidden" id="mouse_travel" name="mouse_travel">
            <input type="hidden" id="reaction_time" name="reaction_time">
            <input type="hidden" id="click_count" name="click_count">

            <!-- 4. Real button will be injected here by JS -->
            <div class="button-container" id="button-container"></div>
        </form>

        <div id="result-display" class="result">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    </div>

    <script>
    (function() {
        "use strict";
        // --- Configuration from PHP ---
        const config = <?php echo json_encode($jsConfig); ?>;

        // --- State variables ---
        const pageLoadTime = Date.now();
        let lastMousePos = { x: -1, y: -1 };
        let totalMouseTravel = 0;
        let buttonAppearTime = 0;
        let clickCounter = 0;

        // --- DOM Elements ---
        const form = document.getElementById('verification-form');
        const buttonContainer = document.getElementById('button-container');
        const statusMessage = document.getElementById('status-message');

        // --- Hidden Inputs ---
        const timeToSubmitInput = document.getElementById('time_to_submit');
        const mouseTravelInput = document.getElementById('mouse_travel');
        const reactionTimeInput = document.getElementById('reaction_time');
        const clickCountInput = document.getElementById('click_count');

        // --- 1. Track total mouse travel distance ---
        document.addEventListener('mousemove', function(e) {
            if (lastMousePos.x > -1) {
                const dx = e.clientX - lastMousePos.x;
                const dy = e.clientY - lastMousePos.y;
                totalMouseTravel += Math.sqrt(dx*dx + dy*dy);
            }
            lastMousePos = { x: e.clientX, y: e.clientY };
        }, { passive: true });

        // --- 2. Create and show the real button after a delay ---
        setTimeout(function() {
            const realButton = document.createElement('button');
            realButton.id = config.realButtonId;
            realButton.type = 'button'; // Use 'button' to prevent form submission until we are ready
            realButton.className = 'continue-button';
            realButton.textContent = config.initialButtonText;

            buttonContainer.appendChild(realButton);

            // Use a tiny delay before adding 'visible' class to ensure the CSS transition runs.
            setTimeout(() => {
                realButton.classList.add('visible');
                buttonAppearTime = Date.now();
            }, 30);

        }, config.buttonAppearDelay);


        // --- 3. Handle clicks on the dynamically created button ---
        buttonContainer.addEventListener('click', function(event) {
            const clickedElement = event.target;
            // Ensure the click is on our real button and not somewhere else in the container
            if (clickedElement.id !== config.realButtonId) {
                return;
            }

            clickCounter++;
            clickCountInput.value = clickCounter;

            // --- MODE 1: Ignore Clicks (Experimental) ---
            if (config.captchaMode === 'IGNORE_CLICKS') {
                const clicksRemaining = (config.clicksToIgnore + 1) - clickCounter;
                if (clicksRemaining > 0) {
                    statusMessage.textContent = `We are running a quick check to verify you're a human. ${clicksRemaining}`;
                    // Wiggle the button to give feedback
                    clickedElement.style.transform = 'translateX(-5px)';
                    setTimeout(() => { clickedElement.style.transform = ''; }, 100);
                    return; // Don't submit yet
                }
            }

            // --- MODE 2: Reaction Time (Recommended) ---
            // This code runs for REACTION mode, or on the final click of IGNORE_CLICKS mode.
            const reactionTime = Date.now() - buttonAppearTime;
            reactionTimeInput.value = reactionTime;

            // Disable button and show feedback
            clickedElement.disabled = true;
            clickedElement.textContent = config.verifyingButtonText;

            // Populate final metrics and submit the form
            submitTheForm();
        });

        function submitTheForm() {
            // a. Calculate time spent on page
            timeToSubmitInput.value = Date.now() - pageLoadTime;
            // b. Store total mouse travel
            mouseTravelInput.value = Math.round(totalMouseTravel);

            // c. Submit the form
            form.submit();
        }
    })();
  </script>
</body>
</html>
