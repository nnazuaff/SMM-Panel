<?php
/**
 * Path Checker untuk debugging cron job di hosting
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Path Checker - AcisPedia</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .info { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .command { background: #2d3748; color: #68d391; padding: 15px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
    <h2>🔍 Path Checker - AcisPedia Cron Setup</h2>
    
    <div class="info">
        <h3>📂 Directory Information:</h3>
        <p><strong>Current Directory (__DIR__):</strong> <?= __DIR__ ?></p>
        <p><strong>Parent Directory:</strong> <?= dirname(__DIR__) ?></p>
        <p><strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'Not available' ?></p>
        <p><strong>Script Path:</strong> <?= $_SERVER['SCRIPT_FILENAME'] ?? 'Not available' ?></p>
    </div>
    
    <div class="info">
        <h3>🐘 PHP Information:</h3>
        <p><strong>PHP Binary:</strong> <?= PHP_BINARY ?></p>
        <p><strong>PHP Version:</strong> <?= PHP_VERSION ?></p>
        <p><strong>SAPI:</strong> <?= php_sapi_name() ?></p>
        <p><strong>Operating System:</strong> <?= PHP_OS ?></p>
    </div>
    
    <div class="info">
        <h3>📋 File Paths Check:</h3>
        <?php
        $files = [
            'update_status.php' => __DIR__ . '/update_status.php',
            'http_cron.php' => __DIR__ . '/http_cron.php', 
            'cron_wrapper.php' => __DIR__ . '/cron_wrapper.php',
            'database.php' => dirname(__DIR__) . '/config/database.php',
            'MedanpediaAPI.php' => dirname(__DIR__) . '/api/MedanpediaAPI.php'
        ];
        
        foreach ($files as $name => $path) {
            $exists = file_exists($path);
            $readable = $exists && is_readable($path);
            echo "<p><strong>$name:</strong> ";
            echo $exists ? "✅ EXISTS" : "❌ NOT FOUND";
            echo $readable ? " & READABLE" : ($exists ? " but NOT READABLE" : "");
            echo "<br><small>$path</small></p>";
        }
        ?>
    </div>
    
    <div class="info">
        <h3>🔧 Recommended Cron Commands:</h3>
        
        <h4>HTTP Cron (RECOMMENDED):</h4>
        <div class="command">
/usr/bin/curl -s "<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) ?>/http_cron.php?key=AcisPedia2024" > /dev/null 2>&1
        </div>
        
        <h4>CLI Cron Options:</h4>
        <div class="command">
# Option 1:
<?= PHP_BINARY ?> <?= __DIR__ ?>/update_status.php

# Option 2:
/usr/bin/php <?= __DIR__ ?>/update_status.php

# Option 3 (with wrapper):
<?= PHP_BINARY ?> <?= __DIR__ ?>/cron_wrapper.php
        </div>
    </div>
    
    <div class="info">
        <h3>🧪 Test Links:</h3>
        <p><a href="http_cron.php?key=AcisPedia2024" target="_blank">🚀 Test HTTP Cron</a></p>
        <p><a href="update_status.php?cron_key=AcisPedia2024" target="_blank">🔧 Test Original Cron</a></p>
    </div>
    
    <div class="info">
        <h3>📝 Server Variables:</h3>
        <details>
            <summary>Click to expand server info</summary>
            <pre><?= print_r($_SERVER, true) ?></pre>
        </details>
    </div>
    
    <p><small>Generated at: <?= date('Y-m-d H:i:s') ?></small></p>
</body>
</html>
