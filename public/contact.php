<?php
require_once __DIR__ . '/../includes/auth.php';

$successMessage = '';
$errorMessage = '';
$web3formsAccessKey = '1a222743-ff83-4204-8c4d-5fa84eda88ad';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Contact Eyad Mazhar for questions, support, or general inquiries about online math preparation and exam-focused teaching resources.">
    <meta name="robots" content="index,follow">
    <meta property="og:title" content="Contact Eyad Mazhar">
    <meta property="og:description" content="Get in touch with Eyad Mazhar for support, questions, or general inquiries about online math preparation.">
    <meta property="og:type" content="website">
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="Contact Eyad Mazhar">
    <meta property="twitter:description" content="Get in touch with Eyad Mazhar for support, questions, or general inquiries about online math preparation.">
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
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($errorMessage !== ''): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form id="contactForm" action="https://api.web3forms.com/submit" method="POST">
                            <input type="hidden" id="web3formsAccessKey" value="<?php echo htmlspecialchars($web3formsAccessKey, ENT_QUOTES, 'UTF-8'); ?>">
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
                                    <button type="submit" class="btn btn-primary" id="contactSubmitBtn">Send Message</button>
                                </div>
                            </div>
                        </form>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const form = document.getElementById('contactForm');
                                const submitBtn = document.getElementById('contactSubmitBtn');

                                if (!form || !submitBtn) {
                                    return;
                                }

                                form.addEventListener('submit', async function (event) {
                                    event.preventDefault();

                                    const accessKeyInput = document.getElementById('web3formsAccessKey');
                                    const accessKey = accessKeyInput ? String(accessKeyInput.value).trim() : '';
                                    const formData = new FormData(form);
                                    if (accessKey !== '') {
                                        formData.set('access_key', accessKey);
                                    }

                                    const originalText = submitBtn.textContent;
                                    submitBtn.textContent = 'Sending...';
                                    submitBtn.disabled = true;

                                    try {
                                        const response = await fetch('https://api.web3forms.com/submit', {
                                            method: 'POST',
                                            body: formData
                                        });

                                        const data = await response.json();

                                        if (response.ok) {
                                            alert('Success! Your message has been sent.');
                                            form.reset();
                                        } else {
                                            alert('Error: ' + (data.message || data.error || 'Unable to send your message.'));
                                        }
                                    } catch (error) {
                                        alert('Something went wrong. Please try again. ' + (error?.message || ''));
                                    } finally {
                                        submitBtn.textContent = originalText;
                                        submitBtn.disabled = false;
                                    }
                                });
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
