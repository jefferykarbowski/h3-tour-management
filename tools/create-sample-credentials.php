<?php
/**
 * Create Sample Credentials File
 * This creates a placeholder credentials file to help with setup
 */

$sample_credentials = array(
    "type" => "service_account",
    "project_id" => "your-project-id",
    "private_key_id" => "your-private-key-id",
    "private_key" => "-----BEGIN PRIVATE KEY-----\nYOUR-PRIVATE-KEY-HERE\n-----END PRIVATE KEY-----\n",
    "client_email" => "your-service-account@your-project-id.iam.gserviceaccount.com",
    "client_id" => "your-client-id",
    "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
    "token_uri" => "https://oauth2.googleapis.com/token",
    "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
    "client_x509_cert_url" => "https://www.googleapis.com/robot/v1/metadata/x509/your-service-account%40your-project-id.iam.gserviceaccount.com"
);

$root_dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
$file_path = $root_dir . '/service-account-credentials-sample.json';

if (file_put_contents($file_path, json_encode($sample_credentials, JSON_PRETTY_PRINT))) {
    echo "Sample credentials file created at:\n";
    echo $file_path . "\n\n";
    echo "To use this file:\n";
    echo "1. Get real credentials from Google Cloud Console\n";
    echo "2. Replace the placeholder values in this file\n";
    echo "3. Rename to: service-account-credentials.json\n";
} else {
    echo "Failed to create sample credentials file.\n";
}
?>