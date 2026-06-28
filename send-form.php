<?php
header('Content-Type: application/json');

$to = "info@cavespringlandscapellc.com";

function clean($value) {
    $value = trim($value ?? '');
    return str_replace(["\r", "\n"], ' ', $value);
}

$firstName = clean($_POST['firstName'] ?? '');
$lastName  = clean($_POST['lastName'] ?? '');
$phone     = clean($_POST['phone'] ?? '');
$email     = clean($_POST['email'] ?? '');
$service   = clean($_POST['service'] ?? '');
$message   = trim($_POST['message'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || $service === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

$subject = "New Free Estimate Request - $firstName $lastName";

$body = "You have a new request from the Cave Spring Landscaping website:\n\n";
$body .= "Name: $firstName $lastName\n";
$body .= "Phone: " . ($phone !== '' ? $phone : 'Not provided') . "\n";
$body .= "Email: $email\n";
$body .= "Service Needed: $service\n";
$body .= "Property Details:\n$message\n";

$headers = "From: Cave Spring Landscaping Website <noreply@cavespringlandscapellc.com>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Mail could not be sent']);
}
