<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$env = file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach($env as $value)
{
  $value = explode('=', $value);
  define($value[0], $value[1]);
}

// Send an email using Brevo accepting the following post values:

// - to: the email address of the recipient
// - subject: the subject of the email
// - message: the body of the email
//
// All other post values will be added to the bottom of the message.
//
// Email will come from contqact

// No longer using Brevo PHP library; using curl instead

// Get POST values
$to = $_POST['to'] ?? null;
$subject = $_POST['subject'] ?? 'Testing';
$message = $_POST['message'] ?? 'This is a test message.';

// Add all other POST values to the bottom of the message
$extra = '';
foreach ($_POST as $key => $value) 
{
  if (!in_array($key, ['to', 'subject', 'message'])) 
  {
    $extra .= "<br><b>" . htmlspecialchars($key) . ":</b> " . htmlspecialchars($value);
  }
}

if ($extra) 
{
  $message .= "<br><br>" . $extra;
}

// Set sender (from) address
$fromEmail = defined('BREVO_FROM_EMAIL') ? BREVO_FROM_EMAIL : 'support@brickmmo.com';
$fromName = defined('BREVO_FROM_NAME') ? BREVO_FROM_NAME : 'BrickMMO Support';

// Set Brevo API key
$apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : null;

if (!$apiKey) 
{
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode([
    'success' => false,
    'message' => 'Missing required parameters.'
  ]);
  exit;
}

if (!$to) {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode([
    'success' => false,
    'message' => 'Missing required "to" email address.'
  ]);
  exit;
}

// Send email using Brevo API with curl
$url = 'https://api.brevo.com/v3/smtp/email';
$data = [
    'sender' => [
        'email' => $fromEmail,
        'name' => $fromName,
    ],
    'to' => [
        [ 'email' => $to ]
    ],
    'subject' => $subject,
    'htmlContent' => $message,
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'accept: application/json',
  'api-key: ' . $apiKey,
  'content-type: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
header('Content-Type: application/json');
if (curl_errno($ch)) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Curl error: ' . curl_error($ch)
  ]);
  curl_close($ch);
  exit;
}
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
  echo json_encode([
    'success' => true,
    'message' => 'Email sent successfully.'
  ]);
} else {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Failed to send email.',
    'brevo_response' => $response
  ]);
}

