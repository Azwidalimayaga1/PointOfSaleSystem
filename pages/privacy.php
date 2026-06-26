<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>Privacy Policy - <?= e(STORE_NAME) ?></title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .privacy-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 24px 60px;
            line-height: 1.8;
        }
        .privacy-header { display: flex; justify-content: space-between; align-items: center; }
        .privacy-page h1 {
            font-size: 28px;
            margin-bottom: 8px;
            color: var(--text);
        }
        .privacy-page .meta {
            font-size: 14px;
            color: var(--gray-400);
            margin-bottom: 32px;
        }
        .privacy-page h2 {
            font-size: 18px;
            margin-top: 32px;
            margin-bottom: 8px;
            color: var(--text);
        }
        .privacy-page p {
            margin-bottom: 12px;
            color: var(--gray-500);
        }
        .privacy-page ul {
            margin: 8px 0 16px 20px;
            color: var(--gray-500);
        }
        .privacy-page ul li {
            margin-bottom: 4px;
        }
        .privacy-page .back-link {
            display: inline-block;
            margin-top: 32px;
            color: var(--primary);
            text-decoration: none;
        }
        .privacy-page .back-link:hover {
            text-decoration: underline;
        }
        .privacy-footer {
            text-align: center;
            padding: 20px 24px;
            font-size: 13px;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
            margin-top: 20px;
        }
        .privacy-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        .privacy-footer a:hover {
            text-decoration: underline;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
    </style>
</head>
<body>
    <main>
        <div class="privacy-page">
            <div class="privacy-header">
                <div>
                    <h1>Privacy Policy</h1>
                    <p class="meta"><strong>Effective Date:</strong> 26 May 2026 &mdash; <strong>Business Name:</strong> WAPANDA &mdash; <strong>POS System:</strong> WAPANDA POS</p>
                </div>
                <button class="btn-theme" onclick="toggleTheme()" title="Toggle theme" id="theme-toggle">
                    <i class="fas fa-moon"></i>
                </button>
            </div>

            <h2>1. Introduction</h2>
            <p>This Privacy Policy explains how we collect, use, store, and protect personal information when customers, staff, and business users use our POS system.</p>
            <p>We are committed to protecting personal information in line with the Protection of Personal Information Act, 2013, known as POPIA. POPIA requires businesses in South Africa to process personal information lawfully and responsibly.</p>

            <h2>2. Information We Collect</h2>
            <p>Our POS system may collect:</p>
            <ul>
                <li>Customer name</li>
                <li>Phone number</li>
                <li>Email address</li>
                <li>Purchase history</li>
                <li>Payment details</li>
                <li>Refund and return records</li>
                <li>Loyalty or discount information</li>
                <li>Staff login details</li>
                <li>Sales and transaction records</li>
                <li>Device and system activity logs</li>
            </ul>
            <p>We do not knowingly collect unnecessary personal information.</p>

            <h2>3. How We Use Information</h2>
            <p>We use personal information to:</p>
            <ul>
                <li>Process sales and payments</li>
                <li>Create receipts and invoices</li>
                <li>Manage refunds and returns</li>
                <li>Track stock and sales reports</li>
                <li>Improve customer service</li>
                <li>Manage staff access</li>
                <li>Prevent fraud and misuse</li>
                <li>Comply with legal and tax requirements</li>
            </ul>

            <h2>4. Payment Information</h2>
            <p>Payment information is used only to complete transactions. We do not store full bank card numbers unless this is handled securely by an authorised payment provider.</p>

            <h2>5. Sharing of Information</h2>
            <p>We may share limited information with:</p>
            <ul>
                <li>Payment service providers</li>
                <li>Accounting or tax service providers</li>
                <li>System hosting providers</li>
                <li>Law enforcement or regulators where legally required</li>
            </ul>
            <p>We do not sell customer personal information.</p>

            <h2>6. Data Security</h2>
            <p>We protect personal information by using reasonable security measures, including:</p>
            <ul>
                <li>Password-protected accounts</li>
                <li>Role-based staff access</li>
                <li>Secure databases</li>
                <li>Backups</li>
                <li>Activity logs</li>
                <li>Encryption where possible</li>
            </ul>
            <p>Users must keep their login details private.</p>

            <h2>7. Data Retention</h2>
            <p>We keep personal information only for as long as needed for business, legal, tax, and record-keeping purposes. When information is no longer needed, it will be deleted, anonymised, or securely archived.</p>

            <h2>8. Customer Rights</h2>
            <p>Customers may request to:</p>
            <ul>
                <li>Access their personal information</li>
                <li>Correct incorrect information</li>
                <li>Ask how their information is used</li>
                <li>Request deletion where legally allowed</li>
                <li>Object to certain processing</li>
            </ul>
            <p>Requests can be sent to:</p>
            <p>Email: <a href="mailto:azwidalimanyaga244@gmail.com">azwidalimanyaga244@gmail.com</a><br>
            Phone: 071 674 0883</p>

            <h2>9. Cookies and Online Use</h2>
            <p>If the POS system has an online dashboard, website, or customer portal, cookies or similar tools may be used to improve login, security, and system performance.</p>

            <h2>10. Children&rsquo;s Information</h2>
            <p>Our POS system is not designed to collect information from children unless a parent, guardian, school, or authorised adult provides it for a lawful reason.</p>

            <h2>11. Changes to This Policy</h2>
            <p>We may update this Privacy Policy when the system, business, or legal requirements change. The latest version will always apply.</p>

            <h2>12. Contact Us</h2>
            <p>For questions about this Privacy Policy, contact:</p>
            <p>
                <strong>Business Name:</strong> WAPANDA<br>
                <strong>Email:</strong> <a href="mailto:azwidalimanyaga244@gmail.com">azwidalimanyaga244@gmail.com</a><br>
                <strong>Phone:</strong> 071 674 0883<br>
                <strong>Address:</strong> 39 Grant Drive, HillsHaven, Westonaria
            </p>

            <a href="javascript:history.back()" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </main>
    <div class="privacy-footer">
        &copy; <?= date('Y') ?> <?= e(STORE_NAME) ?> &mdash; WAPANDA POS
    </div>
<script>
function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('pos-theme', 'light');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('pos-theme', 'dark');
    }
    updateThemeIcon();
}
function updateThemeIcon() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    btn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
}
updateThemeIcon();
</script>
</body>
</html>
