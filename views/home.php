<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> — Campus food, made easy</title>
    <meta name="description" content="A smart college canteen with wallet-based ordering, budget recommendations, table reservations and live inventory.">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="landing-page">
<div class="landing-bg"></div><div class="landing-orb landing-orb-a"></div><div class="landing-orb landing-orb-b"></div>
<header class="landing-nav">
    <a href="<?= e(url('index.php')) ?>" class="landing-brand"><span class="landing-mark">CC</span><span><span class="landing-brand-kicker">Made for campus life</span><span class="landing-brand-name">Smart College Canteen</span></span></a>
    <div class="landing-nav-actions"><a class="btn btn-ghost btn-sm landing-nav-link" href="#features">Explore</a><a class="btn btn-secondary btn-sm" href="<?= e(url('index.php?action=login&role=admin')) ?>">Admin</a><a class="btn btn-primary btn-sm landing-login-btn" href="<?= e(url('index.php?action=login')) ?>">Sign in</a></div>
</header>
<main class="landing-shell">
    <section class="landing-hero">
        <div class="landing-copy">
            <span class="landing-eyebrow">Your campus canteen, in your pocket</span>
            <h1>Good food fits your day and your budget.</h1>
            <p class="landing-lead">Browse the menu, see what your wallet can cover, order ahead and save a table between classes. Administrators keep orders and food stock up to date from one dashboard.</p>
            <div class="landing-actions"><a class="btn btn-primary btn-lg" href="<?= e(url('index.php?action=register')) ?>">Create student account</a><a class="btn btn-secondary btn-lg" href="<?= e(url('index.php?action=login')) ?>">Sign in</a></div>
            <div class="landing-mini-cards"><div class="mini-card"><strong>Spend with a plan</strong><p>Recommendations update with your wallet balance and current menu stock.</p></div><div class="mini-card"><strong>Arrive to a seat</strong><p>Check the live table calendar and reserve an open time slot.</p></div></div>
            <div class="landing-stats"><div class="landing-stat"><strong>₹0</strong><span>Wallet to start</span></div><div class="landing-stat"><strong>Live</strong><span>Food stock</span></div><div class="landing-stat"><strong>Easy</strong><span>Table booking</span></div></div>
        </div>
        <div class="landing-visual"><div class="smeic-visual"><div class="smeic-visual-head"><div><span class="hero-pill">One smooth lunch break</span><h2>Pick. Pay. Pick up.</h2></div><span class="hero-chip">Campus ready</span></div>
            <div class="smeic-flow"><div class="smeic-step smeic-step-order"><span class="smeic-step-mark">1</span><strong>Find your meal</strong><p>See popular plates and combinations that fit your wallet.</p></div><div class="smeic-flow-line"><span></span></div><div class="smeic-step smeic-step-kitchen"><span class="smeic-step-mark">2</span><strong>Order ahead</strong><p>Pay from your personal demo wallet and follow progress.</p></div><div class="smeic-flow-line"><span></span></div><div class="smeic-step smeic-step-pickup"><span class="smeic-step-mark">3</span><strong>Take your seat</strong><p>Reserve a table for your group and chosen time.</p></div></div>
            <div class="smeic-footnote"><span>Demo wallet payments are simulated. No real money is transferred.</span></div></div></div>
    </section>
    <section id="features" class="landing-section"><div class="section-heading"><span class="section-kicker">Made for campus</span><h2>A smarter way to run the canteen.</h2></div><div class="feature-grid">
        <article class="feature-card"><div class="feature-icon">01</div><h3>Wallet-aware picks</h3><p>Affordable single items and meal combinations use your current balance and real stock.</p></article>
        <article class="feature-card"><div class="feature-icon">02</div><h3>Quick ordering</h3><p>Add food to your cart, check out from your wallet and track each order status.</p></article>
        <article class="feature-card"><div class="feature-icon">03</div><h3>Open table calendar</h3><p>Choose a date, time and group size to see which tables are free.</p></article>
        <article class="feature-card"><div class="feature-icon">04</div><h3>Clear stock control</h3><p>Orders reduce inventory automatically, with low-stock alerts for administrators.</p></article>
    </div></section>
    <section class="landing-section landing-cta"><div><span class="section-kicker">Join your campus</span><h2>Start with your college email.</h2><p>Your personal wallet is created with a zero balance. Add demo funds whenever you need them.</p></div><a class="btn btn-primary btn-lg" href="<?= e(url('index.php?action=register')) ?>">Register now</a></section>
</main>
</body></html>
