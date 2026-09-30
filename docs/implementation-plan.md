# Contact Form implementation plan

Goal: complete the approved PHP contact form and truthful Spanish README.
Architecture: public PHP controller/template calls a pure validation and submission module. Configuration stays outside the web root; the mail transport is injectable for tests.
Tech stack: PHP 8.2+, HTML, CSS, Python standard library for HTTP tests.

- [x] Create tests/run.php covering required fields, email/header injection, non-scalar input, size limits, CSRF, honeypot, throttling, demo, mail failure and success. Run php tests/run.php and observe missing implementation failure.
- [x] Implement src/contact.php and config.example.php; run php tests/run.php until all cases pass.
- [x] Create tests/http_test.py covering public form, POST validation, escaping, CSRF, success redirect, replay/throttle and transport failure. Run against the PHP built-in server and observe failure before the controller exists.
- [x] Implement public/index.php and public/style.css; serve with php -S 127.0.0.1:8000 -t public and run HTTP tests.
- [x] Add PHP lint and tests to .github/workflows/ci.yml using PHP 8.2/8.3/8.4/8.5. Replace the old static entry point and placeholder screenshot with the actual interface.
- [x] Write README.md with demo and real configuration commands, deployment boundaries, security limitations and test instructions. Run lint, unit tests, HTTP tests and git diff --check.
- [x] Review the complete change, fix findings, publish the branch and update the existing pull request. Preserve default branch pending review.
