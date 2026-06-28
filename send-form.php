<?php
header('Content-Type: application/json');

$to = "info@cavespringlandscapingllc.com";

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

$maxFiles = 5;
$maxSizeBytes = 5 * 1024 * 1024;
$allowedTypes = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];

$attachments = [];
if (isset($_FILES['propertyPhotos'])) {
    $names = $_FILES['propertyPhotos']['name'];
    $count = count($names);

    if ($count > $maxFiles) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Please select up to $maxFiles photos only."]);
        exit;
    }

    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['propertyPhotos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($_FILES['propertyPhotos']['error'][$i] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'There was a problem uploading one of your photos.']);
            exit;
        }
        if ($_FILES['propertyPhotos']['size'][$i] > $maxSizeBytes) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'One of your photos is larger than 5MB.']);
            exit;
        }

        $tmpPath = $_FILES['propertyPhotos']['tmp_name'][$i];
        $mimeType = mime_content_type($tmpPath);
        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Photos must be JPG, PNG, or HEIC files.']);
            exit;
        }

        $attachments[] = [
            'name' => basename($_FILES['propertyPhotos']['name'][$i]),
            'type' => $mimeType,
            'data' => file_get_contents($tmpPath),
        ];
    }
}

$subject = "New Free Estimate Request - $firstName $lastName";

$bodyText = "You have a new request from the Cave Spring Landscaping website:\n\n";
$bodyText .= "Name: $firstName $lastName\n";
$bodyText .= "Phone: " . ($phone !== '' ? $phone : 'Not provided') . "\n";
$bodyText .= "Email: $email\n";
$bodyText .= "Service Needed: $service\n";
$bodyText .= "Property Details:\n$message\n";
if (!empty($attachments)) {
    $bodyText .= "\nProperty photos attached: " . count($attachments) . "\n";
}

$fromHeader = "Cave Spring Landscaping Website <info@cavespringlandscapingllc.com>";

if (empty($attachments)) {
    $headers = "From: $fromHeader\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = mail($to, $subject, $bodyText, $headers);
} else {
    $boundary = md5(uniqid((string) time()));

    $headers = "From: $fromHeader\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $bodyText . "\r\n";

    foreach ($attachments as $file) {
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: {$file['type']}; name=\"{$file['name']}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$file['name']}\"\r\n\r\n";
        $body .= chunk_split(base64_encode($file['data'])) . "\r\n";
    }

    $body .= "--$boundary--";

    $sent = mail($to, $subject, $body, $headers);
}

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Mail could not be sent']);
}
