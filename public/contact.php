<?php
require_once __DIR__ . '/../includes/auth.php';

$successMessage = '';
$errorMessage = '';
$web3formsAccessKey = 'YOUR_WEB3FORMS_ACCESS_KEY';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $errorMessage = 'Please fill in your name, email, and message.';
    } else {
        $postData = [
            'access_key' => $web3formsAccessKey,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject !== '' ? $subject : 'New contact form submission',
            'message' => $message,
            'from_name' => 'Eyad LMS Website',
            'botcheck' => ''
        ];

        $ch = curl_init('https://api.web3forms.com/submit');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode === 200 && !empty($decoded['success'])) {
            $successMessage = 'Your message was sent successfully. Thank you for contacting us.';
        } else {
            $errorMessage = 'The message could not be sent right now. Please try again later or contact the instructor directly.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact - Eyad LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_nav.php'; ?>
    <div class="container py-5">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <h1 class="mb-3 page-title">Contact</h1>
                <p class="lead text-muted">For questions, support, or general inquiries, send a message through the form below.</p>
                <p class="mb-3"><strong>Phone:</strong> 01068161808</p>
                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary me-2">Back to Home</a>
                    <a href="about.php" class="btn btn-outline-secondary">About the Platform</a>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card shadow-sm section-card">
                    <div class="card-body p-4">
                        <?php if ($successMessage !== ''): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <?php if ($errorMessage !== ''): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="5" required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Send Message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
