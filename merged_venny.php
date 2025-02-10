<?php
// Merged PHP script with both image generation and AI question submission interfaces

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["generate_image"])) {
        // Handle image generation submission

        // API Endpoint
        $url = "https://api.venice.ai/api/v1/image/generate";
        
        // Get user input
        $prompt = htmlspecialchars($_POST["image_prompt"]);
        $model = $_POST["model"];
        $randomize = isset($_POST["randomize"]);
        
        // Generate a random seed if randomization is enabled
        $seed = $randomize ? rand(1, 999999) : 123;
        
        // API Payload
        $payload = [
            "model" => $model,
            "prompt" => $prompt,
            "width" => 800,
            "height" => 800,
            "steps" => 30,
            "hide_watermark" => false,
            "return_binary" => false,
            "seed" => $seed,
            "cfg_scale" => 15,
            "negative_prompt" => "",
            "safe_mode" => true
        ] + (!empty($_POST["style_preset"]) ? ["style_preset" => $_POST["style_preset"]] : []);
        
        // Headers
        $headers = [
            "Authorization: Bearer <<<YOUR API KEY HERE>>>",
            "Content-Type: application/json"
        ];
        
        // Initialize cURL
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
        ]);
        
        // Execute request
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        // Process response
        $base64Image = "";
        if (!$error && $httpCode == 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData["images"][0])) {
                $base64Image = $responseData["images"][0];
            } else {
                $error = "No image data found in API response.";
            }
        } else {
            $error = "Error: " . ($error ?: "HTTP $httpCode - $response");
        }
    } elseif (isset($_POST["ask_question"]) && !empty($_POST["user_prompt"])) {
        // Handle question submission

        header('Content-Type: text/html; charset=UTF-8');

        $userPrompt = $_POST["user_prompt"];
        $tmodel = $_POST["tmodel"];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.venice.ai/api/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "model" => $tmodel,
                "messages" => [
                    [
                        "content" => $userPrompt,
                        "role" => "system",
                        "name" => "burb"
                    ]
                ]
            ], JSON_PRETTY_PRINT),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer <<<YOUR API KEY HERE>>>",
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $output = "<p><strong>Error:</strong> cURL Error: " . htmlspecialchars($err) . "</p>";
        } else {
            $data = json_decode($response, true);

            // Extract main response content
            $responseText = $data["choices"][0]["message"]["content"] ?? "No response received.";

            // Extract "think" section if it exists
            preg_match('/<think>(.*?)<\/think>/s', $responseText, $thinkMatch);
            $thinkSection = isset($thinkMatch[1]) ? $thinkMatch[1] : "No detailed thinking process available.";

            // Remove the "think" section from the main response
            $cleanResponse = preg_replace('/<think>.*?<\/think>/s', '', $responseText);

            // Output the results
            $output = "<p><strong>AI Response:</strong></p>";
            $output .= "<p class='ai-response'>" . nl2br(htmlspecialchars(trim($cleanResponse))) . "</p>";
            
            // Expandable "think" section
            $output .= "<details><summary><strong>Show Thought Process</strong></summary><pre>" . htmlspecialchars($thinkSection) . "</pre></details>";

            // Expandable raw JSON output
            $output .= "<details><summary><strong>Show Raw JSON Response</strong></summary><pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</pre></details>";
        }
    } else {
        $output = "";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Generator & AI Question Interface</title>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 40px;
        background-color: #f0f8ff;
        text-align: center;
    }
    
    textarea {
        width: 60%;
        height: 50px;
        padding: 10px;
        font-size: 16px;
        border-radius: 10px;
        border: 2px solid #007acc;
        outline: none;
        box-shadow: inset 0px 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    button {
        padding: 10px 20px;
        font-size: 16px;
        margin-top: 10px;
        background-color: #007acc;
        color: white;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        transition: 0.3s;
    }
    
    button:hover {
        background-color: #005fa3;
    }
    
    details {
        margin-top: 10px;
        cursor: pointer;
        background: #ffffff;
        border-radius: 15px;
        padding: 10px;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    pre {
    background: #f4f4f4;
    padding: 10px;
    border-radius: 10px;
    overflow-x: auto;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    text-align: left;
    white-space: pre-wrap;
    word-wrap: break-word;
    }
    
    .ai-response {
    width: 60%;
    max-width: 800px; /* Prevents it from getting too wide */
    color: darkgreen;
    padding: 10px;
    border-radius: 10px;
    overflow-x: auto;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    margin: 0 auto;
    text-align: left;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.copy-button {
    border: none;
    background: none;
    cursor: pointer;
    font-size: 18px;
    margin-left: 5px;
}
.copy-button:hover {
    color: #007acc;
}

.copy-message {
    margin-left: 8px;
    color: green;
    font-weight: bold;
    font-size: 14px;
    opacity: 0; /* Initially hidden */
    transition: opacity 0.3s ease-in-out;
}
</style>

    <style>
        /* Full-screen overlay */
        .spinner-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Darkened background */
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        /* Spinner animation */
        .spinner {
            border: 6px solid #f3f3f3; /* Light grey */
            border-top: 6px solid #3498db; /* Blue */
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Spinner text */
        .spinner-text {
            color: white;
            font-size: 18px;
            margin-top: 10px;
        }
    </style>

    <script>
        function showSpinner() {
            document.getElementById("spinner-overlay").style.display = "flex";
            let counter = 0;
            let textElement = document.getElementById("spinner-text");

            // Update the countdown every second
            let interval = setInterval(() => {
                counter++;
                textElement.innerText = "Processing... " + counter + "s elapsed";
            }, 1000);

            // Stop spinner when the page reloads (submits)
            setTimeout(() => { clearInterval(interval); }, 30000);
        }
    </script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const promptElement = document.getElementById("promptText");

    function updateTitle() {
        if (!promptElement) return;

        let promptText = promptElement.dataset.prompt.trim().substring(0, 30).replace(/\s+/g, "-");
        let date = new Date();
        let timestamp = date.toISOString().split("T")[0] + "-" + date.toLocaleTimeString().replace(/[: ]/g, "-");

        document.title = `${promptText}-${timestamp}`;
    }

    // Update title before printing
    window.addEventListener("beforeprint", updateTitle);
});
function copyToClipboard() {
    const promptText = document.getElementById("hpromptText").innerText;
    const copyMessage = document.getElementById("copyMessage");

    navigator.clipboard.writeText(promptText).then(() => {
        copyMessage.innerText = "Copied!";
        copyMessage.style.opacity = "1";

        // Hide the message after 1.5 seconds
        setTimeout(() => {
            copyMessage.style.opacity = "0";
        }, 1500);
    }).catch(err => {
        console.error("Failed to copy: ", err);
    });
}
</script>
</head>
<body>

    <div id="spinner-overlay" class="spinner-overlay">
        <div class="spinner"></div>
        <p id="spinner-text" class="spinner-text">Processing...</p>
    </div>


    <h1>AI Interaction Page using the <a href="https://venice.ai/chat?ref=aHYnVr">venice.ai</a> API</h1>
<h2>If you go to the site, please click the link above because it is my referral link.</h2>
    <hr>
<h2>Generate an Image</h2>
<form method="post" onsubmit="showSpinner()">
    
    <label for="model">Select Model:</label>
    <select id="model" name="model">
        <option value="fluently-xl">Fluently XL (fast)</option>
        <option value="flux-dev">flux-dev (higher quality)</option>
        <option value="stable-diffusion-3.5">stable-diffusion-3.5 (artistic)</option>            
    </select><br><br>

    <label for="style_preset">Select Style (some may not work, leave as none if you do not want to risk an error):</label>
    <select id="style_preset" name="style_preset">
            <option value="" selected>None</option>
            <option value="3D Model">3D Model</option>
            <option value="Abstract">Abstract</option>
            <option value="Advertising">Advertising</option>
            <option value="Alien">Alien</option>
            <option value="Analog Film">Analog Film</option>
            <option value="Anime">Anime</option>
            <option value="Architectural">Architectural</option>
            <option value="Cinematic">Cinematic</option>
            <option value="Collage">Collage</option>
            <option value="Comic Book">Comic Book</option>
            <option value="Craft Clay">Craft Clay</option>
            <option value="Cubist">Cubist</option>
            <option value="Digital Art">Digital Art</option>
            <option value="Disco">Disco</option>
            <option value="Dreamscape">Dreamscape</option>
            <option value="Dystopian">Dystopian</option>
            <option value="Enhance">Enhance</option>
            <option value="Fairy Tale">Fairy Tale</option>
            <option value="Fantasy Art">Fantasy Art</option>
            <option value="Fighting Game">Fighting Game</option>
            <option value="Film Noir">Film Noir</option>
            <option value="Flat Papercut">Flat Papercut</option>
            <option value="Food Photography">Food Photography</option>
            <option value="GTA">GTA</option>
            <option value="Gothic">Gothic</option>
            <option value="Graffiti">Graffiti</option>
            <option value="Grunge">Grunge</option>
            <option value="HDR">HDR</option>
            <option value="Horror">Horror</option>
            <option value="Hyperrealism">Hyperrealism</option>
            <option value="Impressionist">Impressionist</option>
            <option value="Isometric Style">Isometric Style</option>
            <option value="Kirigami">Kirigami</option>
            <option value="Legend of Zelda">Legend of Zelda</option>
            <option value="Line Art">Line Art</option>
            <option value="Long Exposure">Long Exposure</option>
            <option value="Lowpoly">Lowpoly</option>
            <option value="Minecraft">Minecraft</option>
            <option value="Minimalist">Minimalist</option>
            <option value="Monochrome">Monochrome</option>
            <option value="Nautical">Nautical</option>
            <option value="Neon Noir">Neon Noir</option>
            <option value="Neon Punk">Neon Punk</option>
            <option value="Origami">Origami</option>
            <option value="Paper Mache">Paper Mache</option>
            <option value="Paper Quilling">Paper Quilling</option>
            <option value="Papercut Collage">Papercut Collage</option>
            <option value="Papercut Shadow Box">Papercut Shadow Box</option>
            <option value="Photographic">Photographic</option>
            <option value="Pixel Art">Pixel Art</option>
            <option value="Pointillism">Pointillism</option>
            <option value="Pokemon">Pokemon</option>
            <option value="Pop Art">Pop Art</option>
            <option value="Psychedelic">Psychedelic</option>
            <option value="RPG Fantasy Game">RPG Fantasy Game</option>
            <option value="Real Estate">Real Estate</option>
            <option value="Renaissance">Renaissance</option>
            <option value="Retro Arcade">Retro Arcade</option>
            <option value="Retro Game">Retro Game</option>
            <option value="Silhouette">Silhouette</option>
            <option value="Space">Space</option>
            <option value="Stacked Papercut">Stacked Papercut</option>
            <option value="Stained Glass">Stained Glass</option>
            <option value="Steampunk">Steampunk</option>
            <option value="Strategy Game">Strategy Game</option>
            <option value="Street Fighter">Street Fighter</option>
            <option value="Super Mario">Super Mario</option>
            <option value="Surrealist">Surrealist</option>
            <option value="Techwear Fashion">Techwear Fashion</option>
            <option value="Texture">Texture</option>
            <option value="Thick Layered Papercut">Thick Layered Papercut</option>
            <option value="Tilt-Shift">Tilt-Shift</option>
            <option value="Tribal">Tribal</option>
            <option value="Typography">Typography</option>
            <option value="Venetian">Venetian</option>
            <option value="Watercolor">Watercolor</option>
            <option value="Zentangle">Zentangle</option>
    </select><br><br>

    <input type="checkbox" id="randomize" name="randomize">
    <label for="randomize">Randomize Seed</label><br><br>
    <textarea placeholder="Enter description for image..."id="image_prompt" name="image_prompt" rows="4" cols="50" required></textarea><br>

    <button type="submit" name="generate_image">Generate Image</button> <a href="<?= $_SERVER['PHP_SELF']; ?>" class="refresh-button">Refresh Page</a>
</form>

<?php if (!empty($base64Image)): ?>
<h3>Generated Image:</h3>
<p><strong>Prompt:</strong> 
    <span id="hpromptText"><?= htmlspecialchars($prompt) ?></span>
    <button onclick="copyToClipboard()" class="copy-button" title="Copy to clipboard">
        📋
    </button>
<span id="copyMessage" class="copy-message"></span>
</p>

<p><strong>Model:</strong> <?= htmlspecialchars($model) ?></p>
<span id="promptText" data-prompt="<?= htmlspecialchars($prompt) ?>" style="display: none;"></span>
    <?php if (!empty($style_preset)): ?>
        <p><strong>Style:</strong> <?= htmlspecialchars($style_preset) ?></p>
    <?php endif; ?>
    <img src="data:image/png;base64,<?= $base64Image ?>" alt="Generated AI Image">
<?php elseif (!empty($error)): ?>
    <p style="color:red;">Error: <?= $error ?></p>
<?php endif; ?>

<h2>Ask a Question:</h2>
    <form method="POST" onsubmit="showSpinner()">
    <label for="tmodel">Select Model:</label>
    <select id="tmodel" name="tmodel">
        <option value="llama-3.2-3b">llama-3.2-3b (fastest)</option>
        <option value="llama-3.3-70b">llama-3.3-70b</option>
        <option value="dolphin-2.9.2-qwen2-72b">dolphin-2.9.2-qwen2-72b (most uncensored)</option>
        <option value="deepseek-r1-llama-70b">deepseek-r1-llama-70b (popular)</option>            
    </select><br><br>
        <textarea name="user_prompt" placeholder="Enter your question here..."></textarea><br>
        <button type="submit" name="ask_question">Submit Question</button> <a href="<?= $_SERVER['PHP_SELF']; ?>" class="refresh-button">Refresh Page</a>
    </form>

    <hr>
<?php if (!empty($userPrompt)): ?>
    Model: <?= htmlspecialchars($tmodel); ?><br>
<p><strong>Prompt:</strong> 
    <span id="hpromptText"><?= htmlspecialchars($userPrompt) ?></span>
    <button onclick="copyToClipboard()" class="copy-button" title="Copy to clipboard">
        📋
    </button>
<span id="copyMessage" class="copy-message"></span>
</p>
<span id="promptText" data-prompt="<?= htmlspecialchars($userPrompt) ?>" style="display: none;"></span>
<?php endif; ?>

    <?php echo $output; ?>

</body>
</html>
