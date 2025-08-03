<?php
// --- CONFIGURATION ---
define('COOKIE_NAME', 'noauth');
define('COOKIE_VALUE', 'VerifiedHuman_SomeLongRandomString_98765');
define('COOKIE_EXPIRATION_DAYS', 30);

// Start session for CSRF, challenge tracking, and thresholds
session_start();

$errorMessage = '';
$success = false;

// --- Generate Per-Session Randomized Thresholds ---
if (empty($_SESSION['thresholds'])) {
    // Randomize thresholds so bots can't hardcode
    $_SESSION['thresholds'] = [
        'min_time_to_submit' => rand(450, 1200), // ms
        'min_mouse_travel' => rand(80, 200),     // px
        'min_keyboard_events' => rand(1, 4),     // keystrokes
        'pow_difficulty' => rand(2, 3),          // proof-of-work: leading zeros
    ];
}
$thresholds = $_SESSION['thresholds'];

// --- Generate a random honeypot field name per session ---
if (empty($_SESSION['honeypot_name'])) {
    $_SESSION['honeypot_name'] = 'trap_' . bin2hex(random_bytes(4));
}
$honeypotName = $_SESSION['honeypot_name'];

// --- Generate proof-of-work challenge per session (optional) ---
if (empty($_SESSION['pow_challenge'])) {
    $_SESSION['pow_challenge'] = bin2hex(random_bytes(6));
}
$powChallenge = $_SESSION['pow_challenge'];

// --- SERVER-SIDE VERIFICATION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checksPassed = true;
    error_log('CAPTCHA testing started...');

    // 1. CSRF token check
    if (!isset($_POST['challenge_token']) || !hash_equals($_SESSION['challenge_token'], $_POST['challenge_token'])) {
        $checksPassed = false;
        $errorMessage = 'Session expired or invalid. Please reload the page.';
    }

    // 2. Honeypot check
    if ($checksPassed && (!isset($_POST[$honeypotName]) || !empty($_POST[$honeypotName]))) {
        $checksPassed = false;
        $errorMessage = 'Automatic verification failed (honeypot).';
        error_log('CAPTCHA Failure: Honeypot field was filled.');
    }

    // 3. Time to submit
    $timeToSubmit = isset($_POST['time_to_submit']) ? (int)$_POST['time_to_submit'] : 0;
    if ($checksPassed && $timeToSubmit < $thresholds['min_time_to_submit']) {
        $checksPassed = false;
        $errorMessage = 'You submitted too quickly. (0xT)';
        error_log("CAPTCHA Failure: Submission was too fast ({$timeToSubmit}ms, threshold {$thresholds['min_time_to_submit']}ms).");
    }

    // 4. Mouse movement
    $mouseTravelDistance = isset($_POST['mouse_travel']) ? (int)$_POST['mouse_travel'] : 0;
    if ($checksPassed && $mouseTravelDistance < $thresholds['min_mouse_travel']) {
        $checksPassed = false;
        $errorMessage = 'Not enough mouse movement detected. (0xM)';
        error_log("CAPTCHA Failure: Insufficient mouse travel ({$mouseTravelDistance}px, threshold {$thresholds['min_mouse_travel']}px).");
    }

    /* 5. Keyboard activity
    $keyboardEvents = isset($_POST['keyboard_events']) ? (int)$_POST['keyboard_events'] : 0;
    if ($checksPassed && $keyboardEvents < $thresholds['min_keyboard_events']) {
        $checksPassed = false;
        $errorMessage = 'Not enough keyboard interaction. (0xK)';
        error_log("CAPTCHA Failure: Insufficient keyboard events ({$keyboardEvents}, threshold {$thresholds['min_keyboard_events']}).");
    } */

    // 6. Browser fingerprint
    $browserFp = $_POST['browser_fp'] ?? '';
    if ($checksPassed && empty($browserFp)) {
        $checksPassed = false;
        $errorMessage = 'Browser fingerprint was missing. (0xF)';
        error_log('CAPTCHA Failure: Browser fingerprint was missing.');
    }

    // 7. (Optional) Proof-of-work
    $powSolution = $_POST['pow_solution'] ?? '';
    if ($checksPassed) {
        $expectedPrefix = str_repeat('0', $thresholds['pow_difficulty']);
        if (empty($powSolution) || substr(hash('sha256', $powChallenge . $powSolution), 0, $thresholds['pow_difficulty']) !== $expectedPrefix) {
            //$checksPassed = false;
            $errorMessage = 'Proof-of-work challenge failed (0xP).';
            error_log("CAPTCHA Failure: PoW failed ({$powSolution}, difficulty {$thresholds['pow_difficulty']}).");
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
        // Clean up session for challenge
        unset($_SESSION['challenge_token'], $_SESSION['pow_challenge'], $_SESSION['honeypot_name'], $_SESSION['thresholds']);
        $target = filter_input(INPUT_GET, 'target', FILTER_SANITIZE_URL);
        $destination = ($target && strpos($target, '/') === 0) ? $target : '/';
        header('Location: ' . $destination);
        exit;
    }
    // If checks fail, the page will re-render below, displaying the $errorMessage.
}

// Generate a new, unique token for this page load.
$_SESSION['challenge_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
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
        p, .thresholds {
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
            color: #dc3545;
            min-height: 24px;
        }
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid #ccc;
            border-radius: 50%;
            border-top: 3px solid #007bff;
            animation: spin 1s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg);}
            100% { transform: rotate(360deg);}
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Please Wait</h1>
        <p>We are just checking a few things before we send you on your way.</p>
        <div class="thresholds" style="display:none;">
            <!-- These can be shown for troubleshooting -->
            Min time: <?php echo htmlspecialchars($thresholds['min_time_to_submit']); ?>ms,
            Min mouse: <?php echo htmlspecialchars($thresholds['min_mouse_travel']); ?>px,
            Min keyboard: <?php echo htmlspecialchars($thresholds['min_keyboard_events']); ?>,
            PoW difficulty: <?php echo htmlspecialchars($thresholds['pow_difficulty']); ?>
        </div>
        <form id="verification-form" method="POST" autocomplete="off" spellcheck="false">
            <!-- 1. Randomized honeypot field -->
            <div class="honeypot" aria-hidden="true">
                <label for="<?php echo htmlspecialchars($honeypotName); ?>">Leave blank</label>
                <input type="text" id="<?php echo htmlspecialchars($honeypotName); ?>" name="<?php echo htmlspecialchars($honeypotName); ?>" tabindex="-1" autocomplete="off">
            </div>
            <!-- 2. Security Token -->
            <input type="hidden" name="challenge_token" value="<?php echo htmlspecialchars($_SESSION['challenge_token']); ?>">
            <!-- 3. Hidden fields for client metrics -->
            <input type="hidden" id="time_to_submit" name="time_to_submit">
            <input type="hidden" id="mouse_travel" name="mouse_travel">
            <input type="hidden" id="keyboard_events" name="keyboard_events">
            <input type="hidden" id="browser_fp" name="browser_fp">
            <!-- Proof-of-work -->
            <input type="hidden" id="pow_solution" name="pow_solution">
            <button type="submit" id="continue-btn" class="continue-button">Click here to Continue</button>
            <span id="loading-spinner" class="spinner" style="display:none;"></span>
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
        let keyboardEvents = 0;

        // --- DOM Elements ---
        const form = document.getElementById('verification-form');
        const button = document.getElementById('continue-btn');
        const spinner = document.getElementById('loading-spinner');
        const timeToSubmitInput = document.getElementById('time_to_submit');
        const mouseTravelInput = document.getElementById('mouse_travel');
        const keyboardEventsInput = document.getElementById('keyboard_events');
        const browserFpInput = document.getElementById('browser_fp');
        const powSolutionInput = document.getElementById('pow_solution');

        // --- Thresholds from PHP (for client-side PoW) ---
        const powChallenge = "<?php echo $powChallenge; ?>";
        const powDifficulty = <?php echo (int)$thresholds['pow_difficulty']; ?>;

        // --- 1. Track total mouse travel distance ---
        document.addEventListener('mousemove', function(e) {
            if (lastMousePos.x > -1) {
                const dx = e.clientX - lastMousePos.x;
                const dy = e.clientY - lastMousePos.y;
                totalMouseTravel += Math.sqrt(dx*dx + dy*dy);
            }
            lastMousePos = { x: e.clientX, y: e.clientY };
        }, { passive: true });

        // --- 2. Track keyboard events (focus on this page only) ---
        form.addEventListener('keydown', function(e) {
            // Only count printable keys
            if (e.key.length === 1) keyboardEvents++;
        }, true);

        // --- 3. Gather a basic browser fingerprint ---
        function getBrowserFingerprint() {
            const fp = {
                ua: navigator.userAgent,
                lang: navigator.language,
                res: `${screen.width}x${screen.height}`,
                cd: screen.colorDepth,
                tz: new Date().getTimezoneOffset(),
                plat: navigator.platform,
                vendor: navigator.vendor
            };
            // FIX: Return the stringified object directly, do not hash it.
            // The server only checks if this field is empty, not its content.
            return JSON.stringify(fp);
        }

        // --- 4. Simple JS SHA-256 (for PoW and fingerprint) ---
        // Source: https://geraintluff.github.io/sha256/
        function sha256(ascii) {
            function rightRotate(v, amt) { return (v>>>amt) | (v<<(32-amt)); }
            var mathPow=Math.pow,max=Math.max,imul=Math.imul;
            var result=[],k=[],hash=[],W=new Array(64),i,j;
            var s1,s0,maj,ch,temp1,temp2;
            var H=[1779033703,3144134277,1013904242,2773480762,1359893119,2600822924,528734635,1541459225];
            for(var i=0;i<64;i++)k[i]=Math.floor(mathPow(2,32)*Math.abs(Math.sin(i+1)));
            ascii += '\x80'; var l=ascii.length/4+2; var N=Math.ceil(l/16); var M=new Array(N);
            for(i=0;i<N;i++){M[i]=new Array(16);for(j=0;j<16;j++)M[i][j]=0;}
            for(i=0;i<ascii.length;i++)M[i>>6][i%64>>2]|=ascii.charCodeAt(i)<<((3-i%4)*8);
            M[N-1][14]=((ascii.length-1)*8)/Math.pow(2,32)|0;M[N-1][15]=((ascii.length-1)*8)&0xffffffff;
            for(i=0;i<N;i++){
                for(j=0;j<16;j++)W[j]=M[i][j];
                for(j=16;j<64;j++) W[j]=(((W[j-2]>>>17|W[j-2]<<15)^(W[j-2]>>>19|W[j-2]<<13)^(W[j-2]>>>10))+W[j-7]|0)+
                    (((W[j-15]>>>7|W[j-15]<<25)^(W[j-15]>>>18|W[j-15]<<14)^(W[j-15]>>>3))+W[j-16]|0)|0;
                var a=H[0],b=H[1],c=H[2],d=H[3],e=H[4],f=H[5],g=H[6],h=H[7];
                for(j=0;j<64;j++){
                    s1=(e>>>6|e<<26)^(e>>>11|e<<21)^(e>>>25|e<<7);
                    ch=(e&f)^((~e)&g);temp1=(h+s1+ch+k[j]+W[j])|0;
                    s0=(a>>>2|a<<30)^(a>>>13|a<<19)^(a>>>22|a<<10);
                    maj=(a&b)^(a&c)^(b&c);temp2=(s0+maj)|0;
                    h=g;g=f;f=e;e=(d+temp1)|0;d=c;c=b;b=a;a=(temp1+temp2)|0;
                }
                H[0]=(H[0]+a)|0;H[1]=(H[1]+b)|0;H[2]=(H[2]+c)|0;H[3]=(H[3]+d)|0;
                H[4]=(H[4]+e)|0;H[5]=(H[5]+f)|0;H[6]=(H[6]+g)|0;H[7]=(H[7]+h)|0;
            }
            for(i=0;i<H.length;i++)for(j=3;j+1;j--)result.push(('00'+((H[i]>>(j*8))&255).toString(16)).slice(-2));
            return result.join('');
        }

        // --- 5. Proof-of-work (find a nonce so hash(challenge+nonce) has N leading zeros) ---
        async function doProofOfWork(challenge, difficulty) {
            return new Promise(function(resolve) {
                let nonce = 0;
                let prefix = '0'.repeat(difficulty);
                function tryNonce() {
                    for (let i = 0; i < 1000; i++) {
                        let test = sha256(challenge + nonce);
                        if (test.substring(0, difficulty) === prefix) {
                            resolve(nonce.toString());
                            return;
                        }
                        nonce++;
                    }
                    setTimeout(tryNonce, 0); // Yield to UI thread
                }
                tryNonce();
            });
        }

        // --- 6. Form Submission Logic ---
        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            // UI feedback
            button.disabled = true;
            spinner.style.display = "inline-block";
            button.textContent = "Verifying...";

            // a. Calculate time spent on page
            timeToSubmitInput.value = Date.now() - pageLoadTime;
            // b. Store total mouse travel
            mouseTravelInput.value = Math.round(totalMouseTravel);
            // c. Store total keyboard events
            keyboardEventsInput.value = keyboardEvents;
            // d. Store browser fingerprint
            browserFpInput.value = getBrowserFingerprint();

            // e. Do proof-of-work
            powSolutionInput.value = await doProofOfWork(powChallenge, powDifficulty);

            // Now submit
            form.submit();
        });
    })();
  </script>
</body>
</html>