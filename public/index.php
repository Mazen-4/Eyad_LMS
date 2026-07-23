<?php
require_once __DIR__ . '/../includes/auth.php';

$successMessage = '';
$errorMessage = '';
$web3formsAccessKey = '1a222743-ff83-4204-8c4d-5fa84eda88ad';

$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$publicDir = dirname($scriptPath);

if ($publicDir === '/' || $publicDir === '.' || $publicDir === '') {
    $publicDir = '/public';
}

$baseUrl = rtrim($publicDir, '/') . '/';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Eyad Mazhar provides structured online math preparation for American-system students, with lectures, resources, and quizzes for EST, DSAT, and ACT success.">
    <meta name="robots" content="index,follow">
    <meta name="keywords" content="Eyad Mazhar, Eng. Eyad Mazhar, math tutor, EST prep, DSAT prep, ACT prep, American-system math, online math lessons">
    <meta property="og:title" content="Eyad Mazhar - Online Math Preparation for EST, DSAT, and ACT">
    <meta property="og:description" content="Explore structured math lessons, exam resources, and quiz-based practice from Eyad Mazhar for American-system students.">
    <meta property="og:type" content="website">
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="Eyad Mazhar - Online Math Preparation for EST, DSAT, and ACT">
    <meta property="twitter:description" content="Explore structured math lessons, exam resources, and quiz-based practice from Eyad Mazhar for American-system students.">
    <title>Eyad LMS - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appAssetUrl('assets/css/theme.css?v=2'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_nav.php'; ?>
    <section id="home" class="hero-shell">
        <div class="container py-5">
            <div class="row align-items-center g-4 mb-4">
                <div class="col-lg-7">
                    <div class="hero-panel">
                        <div class="hero-eyebrow">American-System Math Preparation</div>
                        <h1 class="display-5 fw-bold page-title mb-3">Structured mathematics preparation for students who want to perform with confidence.</h1>
                        <p class="lead text-light mb-4">Access organized lectures, exam-focused resources, and practice quizzes through a secure learning portal built for EST, DSAT, and ACT readiness.</p>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <span class="hero-badge">EST</span>
                            <span class="hero-badge">DSAT</span>
                            <span class="hero-badge">ACT</span>
                            <span class="hero-badge">American Math</span>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>login.php" class="btn btn-primary btn-lg">Login to Portal</a>
                            <a href="#about" class="btn btn-outline-secondary btn-lg">Learn About the Platform</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card hero-visual-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="card-title page-title mb-0">What students receive</h5>
                                <span class="hero-visual-pill">Secure Portal</span>
                            </div>
                            <div class="hero-visual-list">
                                <div class="hero-visual-item">
                                    <span class="hero-visual-icon">01</span>
                                    <div>
                                        <strong>Clear lesson pathways</strong>
                                        <p class="mb-0 text-muted">A structured learning flow that helps students study with direction.</p>
                                    </div>
                                </div>
                                <div class="hero-visual-item">
                                    <span class="hero-visual-icon">02</span>
                                    <div>
                                        <strong>Exam-ready materials</strong>
                                        <p class="mb-0 text-muted">Resources organized by topic and preparation stage for efficient review.</p>
                                    </div>
                                </div>
                                <div class="hero-visual-item">
                                    <span class="hero-visual-icon">03</span>
                                    <div>
                                        <strong>Group-aligned assessment support</strong>
                                        <p class="mb-0 text-muted">Quizzes and practice access tailored to the student’s assigned course track.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">3</div>
                    <div class="metric-label">Exams covered</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">EST · DSAT · ACT</div>
                    <div class="metric-label">Standardized tests</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">24/7</div>
                    <div class="metric-label">Portal access</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">100%</div>
                    <div class="metric-label">Secure access</div>
                </div>
            </div>
        </section>

            </section>
        </div>
    </section>

    <div class="container py-5">
        <div class="section-separator"></div>

        <section id="features" class="row g-4 mt-4">
            <div class="col-md-4">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Private student access</h5>
                        <p class="card-text text-muted">Each learner enters a secure portal where only the content assigned to their group is visible.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Clean and focused study flow</h5>
                        <p class="card-text text-muted">Lectures, PDFs, and quiz resources are organized in a way that reduces clutter and supports steady progress.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Built for exam readiness</h5>
                        <p class="card-text text-muted">The experience is tailored for students preparing for EST, DSAT, and ACT through structured American-system math support.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="section-separator"></div>

        <section id="about" class="row g-4 mt-4 align-items-start">
            <div class="col-lg-6">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Focused on American-system math</h5>
                        <p class="card-text text-muted">This platform supports Eng. Eyad Mazhar’s teaching for American curriculum students in Egypt.</p>
                        <p class="card-text text-muted mb-0">It is especially suited for students preparing for EST, DSAT, and ACT exams.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">About the Platform</h5>
                        <p class="card-text text-muted">This LMS is designed to make Eng. Eyad Mazhar’s American-system mathematics teaching easy to access, easy to manage, and easy to follow.</p>
                        <p class="card-text text-muted mb-0">It provides a secure home for EST, DSAT, and ACT preparation, helping students stay organized and focused on the topics that matter most.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="section-separator"></div>

        <section class="row g-4 mt-4 align-items-start">
            <div class="col-lg-5">
                <div class="card section-card h-100">
                    <div class="card-body p-4">
                        <div class="hero-eyebrow">The Approach</div>
                        <h3 class="mb-3">Why it works</h3>
                        <p class="text-muted">Eng. Eyad Mazhar’s platform is built around one idea: students learn better when everything is organized, accessible, and purposeful.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card section-card h-100">
                    <div class="card-body p-4">
                        <ul class="text-muted mb-0">
                            <li>Secure group-based access for lectures, resources, and quizzes.</li>
                            <li>Simple structure so students can focus on learning instead of navigating.</li>
                            <li>Teacher-managed content that keeps the experience clear and purposeful.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <div class="section-separator"></div>

        <section id="contact" class="row g-4 mt-4 align-items-start">
            <div class="col-lg-5">
                <div class="card section-card h-100">
                    <div class="card-body p-4">
                        <div class="hero-eyebrow">Contact</div>
                        <h2 class="fw-bold mb-3">Get in touch for support or general inquiries.</h2>
                        <p class="text-muted">For questions about access, course support, or general platform information, use the form or contact directly through the phone number below.</p>
                        <p class="mb-2"><strong>Phone:</strong> 01068161808</p>
                        <p class="mb-0"><strong>Response:</strong> Usually handled as quickly as possible for student support needs.</p>
                    </div>
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
        </section>

        <div class="section-separator"></div>

        <section class="text-center mt-4">
            <h3 class="mb-2">Ready to start preparing?</h3>
            <p class="text-muted mx-auto mb-3" style="max-width: 640px;">Log in to access your personalized study materials, quizzes, and exam preparation resources.</p>
            <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>login.php" class="btn btn-primary btn-lg">Login to Portal</a>
        </section>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
