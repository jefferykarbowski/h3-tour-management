#!/usr/bin/env php
<?php
/**
 * Test Webhook Endpoint
 *
 * Usage: php test-webhook.php <webhook-url>
 * Example: php test-webhook.php https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook
 */

if ($argc < 2) {
    echo "Usage: php test-webhook.php <webhook-url>\n";
    echo "Example: php test-webhook.php https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook\n";
    exit(1);
}

$webhookUrl = $argv[1];

// Test payload
$payload = [
    'success' => true,
    'tourName' => 'test_webhook_' . date('YmdHis'),
    'tourId' => 'test_' . time(),
    's3Key' => 'uploads/test/test.zip',
    's3FolderName' => 'test',
    's3Bucket' => 'test-bucket',
    'filesExtracted' => 10,
    'processingTime' => 5000,
    'totalSize' => 1000000,
    'message' => 'Test webhook from CLI',
    'timestamp' => date('c'),
    's3Url' => 'https://test-bucket.s3.us-east-1.amazonaws.com/tours/test/'
];

echo "🧪 Testing Webhook Endpoint\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📍 URL: $webhookUrl\n";
echo "📦 Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Initialize cURL
$ch = curl_init($webhookUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: H3-Lambda-Processor/1.0'
    ],
    CURLOPT_VERBOSE => true,
    CURLOPT_HEADER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
]);

echo "📤 Sending POST request...\n\n";

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);

curl_close($ch);

// Parse response
$headerSize = $curlInfo['header_size'];
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "📥 Response Received\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔢 HTTP Status: $httpCode\n";

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
}

echo "\n📋 Response Headers:\n";
echo $headers . "\n";

echo "📄 Response Body:\n";
if ($body) {
    // Try to pretty-print JSON
    $json = json_decode($body, true);
    if ($json) {
        echo json_encode($json, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo $body . "\n";
    }
} else {
    echo "(empty)\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Analyze result
echo "\n🔍 Analysis:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

switch ($httpCode) {
    case 200:
        echo "✅ SUCCESS - Webhook processed successfully\n";
        echo "   The endpoint is working and accepted the payload.\n";
        break;

    case 400:
        echo "❌ BAD REQUEST - Payload validation failed\n";
        echo "   WordPress received the request but rejected it.\n";
        echo "   Check response body for validation error details.\n";
        break;

    case 401:
        echo "❌ UNAUTHORIZED - Signature verification failed\n";
        echo "   Webhook secret is configured but signature is missing/invalid.\n";
        break;

    case 404:
        echo "❌ NOT FOUND - Endpoint does not exist\n";
        echo "   Possible causes:\n";
        echo "   - Plugin not activated\n";
        echo "   - Webhook handler not registered\n";
        echo "   - URL is incorrect\n";
        break;

    case 405:
        echo "❌ METHOD NOT ALLOWED - Wrong request method\n";
        echo "   Endpoint exists but only accepts POST requests.\n";
        break;

    case 500:
        echo "❌ INTERNAL SERVER ERROR - WordPress error\n";
        echo "   Check WordPress debug.log for PHP errors.\n";
        break;

    case 0:
        echo "❌ CONNECTION FAILED - Could not reach server\n";
        echo "   Possible causes:\n";
        echo "   - Network/firewall issue\n";
        echo "   - Invalid URL\n";
        echo "   - Server is down\n";
        break;

    default:
        echo "⚠️  UNEXPECTED STATUS CODE: $httpCode\n";
        echo "   Check response headers and body for details.\n";
        break;
}

echo "\n📊 Connection Info:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🌐 URL: {$curlInfo['url']}\n";
echo "⏱️  Total Time: " . round($curlInfo['total_time'], 3) . "s\n";
echo "📡 Name Lookup: " . round($curlInfo['namelookup_time'], 3) . "s\n";
echo "🔌 Connect: " . round($curlInfo['connect_time'], 3) . "s\n";
echo "📤 Pretransfer: " . round($curlInfo['pretransfer_time'], 3) . "s\n";
echo "📥 Start Transfer: " . round($curlInfo['starttransfer_time'], 3) . "s\n";
echo "📊 Size Download: {$curlInfo['size_download']} bytes\n";
echo "📊 Size Upload: {$curlInfo['size_upload']} bytes\n";

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Exit with appropriate code
exit($httpCode === 200 ? 0 : 1);
