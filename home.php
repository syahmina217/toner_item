<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TonerMS, Inventory and Department Records Management</title>
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', Inter, Arial, sans-serif;
      background-color: #eef2f8;
      color: #111827;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Hero Section ── */
    .hero {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 100px 24px 110px;
      background: linear-gradient(160deg, #e8eef8 0%, #f0f4fc 60%, #eef2f8 100%);
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,0.72);
      border: 1px solid #d6def0;
      border-radius: 999px;
      padding: 8px 18px;
      font-size: 0.875rem;
      color: #4b6aa0;
      font-weight: 500;
      margin-bottom: 32px;
      backdrop-filter: blur(4px);
    }

    .badge svg {
      width: 17px;
      height: 17px;
      color: #3b7bf5;
    }

    h1 {
      font-size: clamp(2.4rem, 5vw, 3.6rem);
      font-weight: 800;
      line-height: 1.15;
      color: #0f172a;
      letter-spacing: -1.5px;
      max-width: 660px;
      margin-bottom: 22px;
    }

    .subtitle {
      font-size: 1.05rem;
      color: #6b7280;
      max-width: 560px;
      line-height: 1.75;
      margin-bottom: 42px;
    }

    .btn-primary {
      display: inline-block;
      background: #3b7bf5;
      color: #fff;
      text-decoration: none;
      padding: 16px 40px;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      letter-spacing: 0.2px;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 4px 18px rgba(59, 123, 245, 0.35);
    }

    .btn-primary:hover {
      background: #2563eb;
      transform: translateY(-2px);
      box-shadow: 0 8px 26px rgba(59, 123, 245, 0.45);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    /* ── Key Features Section ── */
    .features {
      background: #ffffff;
      padding: 80px 40px 90px;
      text-align: center;
    }

    .features h2 {
      font-size: 2rem;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.5px;
      margin-bottom: 48px;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 24px;
      max-width: 900px;
      margin: 0 auto;
      text-align: left;
    }

    .feature-card {
      border: 1px solid #e5e9f0;
      border-radius: 14px;
      padding: 28px 28px 32px;
      display: flex;
      align-items: flex-start;
      gap: 18px;
      background: #fff;
      transition: box-shadow 0.2s;
    }

    .feature-card:hover {
      box-shadow: 0 6px 24px rgba(59, 123, 245, 0.09);
    }

    .feature-icon {
      flex-shrink: 0;
      width: 46px;
      height: 46px;
      background: #ebf2ff;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .feature-icon svg {
      width: 22px;
      height: 22px;
      color: #3b7bf5;
      stroke: #3b7bf5;
    }

    .feature-text h3 {
      font-size: 1rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 8px;
    }

    .feature-text p {
      font-size: 0.9rem;
      color: #6b7280;
      line-height: 1.65;
    }

    /* ── CTA Section ── */
    .cta {
      background: #f0f4fc;
      padding: 90px 24px;
      text-align: center;
    }

    .cta h2 {
      font-size: 1.85rem;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.4px;
      margin-bottom: 14px;
    }

    .cta p {
      font-size: 0.98rem;
      color: #6b7280;
      margin-bottom: 36px;
    }

    /* ── Responsive ── */
    @media (max-width: 700px) {
      .features-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 600px) {
      .features {
        padding: 60px 20px 70px;
      }

      .cta {
        padding: 70px 20px;
      }
    }
  </style>
</head>
<body>

  <!-- Hero -->
  <section class="hero">

    <div class="badge">
      <!-- small box icon -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2L22 7V17L12 22L2 17V7L12 2Z"/>
        <polyline points="12 2 12 22"/>
        <line x1="2" y1="7" x2="22" y2="7"/>
        <path d="M2 7L12 12L22 7"/>
      </svg>
      Toner Inventory
    </div>

    <h1>Manage toner inventory<br>with accuracy and control</h1>

    <p class="subtitle">
      A structured system for monitoring toner stock, recording inventory movement,
      and tracking toner issued to each department through monthly and yearly records.
    </p>

    <a href="index.php" class="btn-primary">Open Inventory Controls</a>

  </section>

  <!-- Key Features -->
  <section class="features">
    <h2>System Capabilities</h2>
    <div class="features-grid">

      <!-- Inventory Control -->
      <div class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
          </svg>
        </div>
        <div class="feature-text">
          <h3>Inventory control</h3>
          <p>Monitor available toner quantities and keep stock information updated as products are added, issued, or adjusted.</p>
        </div>
      </div>

      <!-- Product Management -->
      <div class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5v14M5 12h14"/>
          </svg>
        </div>
        <div class="feature-text">
          <h3>Product registration and updates</h3>
          <p>Register new toner products, update product details, manage pricing, and maintain accurate product descriptions.</p>
        </div>
      </div>

      <!-- Stock Alerts -->
      <div class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </div>
        <div class="feature-text">
          <h3>Restock monitoring</h3>
          <p>Identify toner items that require restocking and prevent department issues when available quantity is insufficient.</p>
        </div>
      </div>

      <!-- Department Records -->
      <div class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
          </svg>
        </div>
        <div class="feature-text">
          <h3>Department records</h3>
          <p>Record toner issued to each department and maintain clear usage totals by department, product, month, and year.</p>
        </div>
      </div>

      <!-- Usage Calendar -->
      <div class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>
        <div class="feature-text">
          <h3>Usage calendar</h3>
          <p>View toner usage by date through a calendar-based record that supports clearer monthly tracking and review.</p>
        </div>
      </div>

      <!-- Reporting -->
      <div class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"/>
            <line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6"  y1="20" x2="6"  y2="14"/>
          </svg>
        </div>
        <div class="feature-text">
          <h3>Monthly and yearly reporting</h3>
          <p>Review department usage summaries, product breakdowns, and annual totals to support better toner stock planning.</p>
        </div>
      </div>

    </div>
  </section>

  <!-- CTA Banner -->
  <section class="cta">
    <h2>Start managing toner records with confidence</h2>
    <p>Open the inventory module to register products, update stock, and record toner issued to departments.</p>
    <a href="index.php" class="btn-primary">Go to Inventory Controls</a>
  </section>

</body>
</html>