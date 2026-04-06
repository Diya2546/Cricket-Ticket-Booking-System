<?php
$rows = ['A', 'B', 'C', 'D', 'E', 'F'];
$total_seats = 8;
$booked_seats = ['A7', 'B7', 'C6', 'D8', 'E2'];

$stadium_bg = 'image/stadium.png';

function getSeatClass($seat_id, $booked_seats) {
    if (in_array($seat_id, $booked_seats)) {
        return 'booked';
    }
    return 'available';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Now - Cricket Ticket Booking</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
    }

    body{
      font-family: Arial, sans-serif;
      color:#fff;
      min-height:100vh;
      background:
        radial-gradient(circle at top left, rgba(59,130,246,0.10), transparent 22%),
        radial-gradient(circle at top right, rgba(168,85,247,0.10), transparent 22%),
        radial-gradient(circle at bottom left, rgba(34,197,94,0.08), transparent 25%),
        linear-gradient(135deg, #040814 0%, #091120 45%, #050b18 100%);
    }

    .glass{
      background: rgba(255,255,255,0.04);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.08);
      box-shadow: 0 12px 40px rgba(0,0,0,0.35);
    }

    .top-border{
      border-bottom:1px solid rgba(255,255,255,0.08);
    }

    .main-wrap{
      max-width:1450px;
      margin:0 auto;
      padding:0 20px;
    }

    .match-header{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:20px;
      padding:28px 0 22px;
      flex-wrap:wrap;
    }

    .back-btn{
      color:#fff;
      text-decoration:none;
      font-size:18px;
      opacity:0.9;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }

    .back-btn:hover{
      opacity:1;
    }

    .match-info{
      display:flex;
      align-items:flex-start;
      gap:18px;
      flex-wrap:wrap;
    }

    .teams-wrap h1{
      font-size:52px;
      font-weight:800;
      letter-spacing:1px;
      display:flex;
      align-items:center;
      gap:18px;
      flex-wrap:wrap;
    }

    .vs-text{
      color:#cbd5e1;
      font-size:40px;
      font-weight:700;
    }

    .flag-box{
      width:78px;
      height:54px;
      border-radius:14px;
      overflow:hidden;
      background: rgba(255,255,255,0.08);
      border:1px solid rgba(255,255,255,0.12);
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:700;
      font-size:15px;
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
    }

    .match-subtext{
      color:#cbd5e1;
      margin-top:10px;
      font-size:18px;
    }

    .steps{
      display:flex;
      align-items:center;
      gap:14px;
      flex-wrap:wrap;
      margin-top:10px;
    }

    .step{
      display:flex;
      align-items:center;
      gap:10px;
      color:rgba(255,255,255,0.55);
      font-size:18px;
      font-weight:600;
    }

    .step-number{
      width:36px;
      height:36px;
      border-radius:50%;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:700;
      background:rgba(255,255,255,0.1);
      color:#fff;
    }

    .step.active{
      color:#fff;
    }

    .step.active .step-number{
      background:#f6c84c;
      color:#111827;
    }

    .arrow{
      color:rgba(255,255,255,0.3);
      font-size:22px;
    }

    .content-grid{
      display:grid;
      grid-template-columns: 2fr 1fr;
      gap:34px;
      padding:34px 0 40px;
      align-items:start;
    }

    .category-list{
      display:flex;
      gap:14px;
      flex-wrap:wrap;
      margin-bottom:26px;
    }

    .category-card{
      min-width:150px;
      border-radius:18px;
      border:1px solid rgba(255,255,255,0.08);
      padding:16px 24px;
      text-align:left;
      color:#fff;
      background: rgba(255,255,255,0.03);
      cursor:pointer;
      transition:0.3s ease;
      position:relative;
    }

    .category-card .cat-name{
      font-size:20px;
      font-weight:700;
    }

    .category-card .cat-price{
      font-size:18px;
      margin-top:4px;
      opacity:0.95;
    }

    .category-card.general.active-tab{
      border-color: rgba(96,165,250,0.6);
      box-shadow: 0 0 20px rgba(59,130,246,0.18);
      background: linear-gradient(180deg, rgba(59,130,246,0.14), rgba(255,255,255,0.03));
    }

    .category-card.premium.active-tab{
      border-color: rgba(250,204,21,0.75);
      box-shadow: 0 0 25px rgba(250,204,21,0.22);
      background: linear-gradient(180deg, rgba(250,204,21,0.18), rgba(255,255,255,0.03));
    }

    .category-card.vip.active-tab{
      border-color: rgba(168,85,247,0.7);
      box-shadow: 0 0 25px rgba(168,85,247,0.22);
      background: linear-gradient(180deg, rgba(168,85,247,0.12), rgba(255,255,255,0.03));
    }

    .category-card.corporate.active-tab{
      border-color: rgba(239,68,68,0.65);
      box-shadow: 0 0 25px rgba(239,68,68,0.18);
      background: linear-gradient(180deg, rgba(239,68,68,0.12), rgba(255,255,255,0.03));
    }

    .category-card.active-tab::after{
      content:"";
      position:absolute;
      left:50%;
      bottom:-10px;
      transform:translateX(-50%);
      width:0;
      height:0;
      border-left:10px solid transparent;
      border-right:10px solid transparent;
      border-top:10px solid #f6c84c;
    }

    .stadium-box{
      position:relative;
      padding:42px 30px 30px;
      border-radius:34px;
      min-height:760px;
      overflow:hidden;
      background:
        radial-gradient(circle at center, rgba(255,255,255,0.03), transparent 45%),
        linear-gradient(180deg, rgba(8,14,28,0.96), rgba(6,11,24,0.98));
    }

    .gate-label{
      position:absolute;
      color:rgba(255,255,255,0.78);
      font-size:14px;
      font-weight:700;
      letter-spacing:1px;
      z-index:10;
    }

    .gate1{ top:182px; left:35px; }
    .gate2{ top:182px; right:35px; }
    .gate3{ bottom:108px; left:26px; }
    .gate4{ bottom:108px; right:26px; }

    .stadium-rings{
      position:relative;
      width:100%;
      max-width:860px;
      margin:0 auto;
      height:600px;
    }

    .outer-ring{
      position:absolute;
      inset:0;
      border-radius:50%;
      background:
        linear-gradient(180deg, rgba(255,255,255,0.14), rgba(148,163,184,0.10));
      box-shadow:
        inset 0 0 0 14px rgba(148,163,184,0.35),
        inset 0 -30px 60px rgba(0,0,0,0.25);
      opacity:0.9;
    }

    .inner-ring{
      position:absolute;
      top:78px;
      left:90px;
      right:90px;
      bottom:86px;
      border-radius:50%;
      overflow:hidden;
      background:
        radial-gradient(ellipse at center, rgba(65,131,72,0.30) 0%, rgba(39,89,49,0.22) 28%, rgba(20,31,51,0.10) 40%, rgba(8,12,23,0.00) 54%);
      box-shadow:
        inset 0 0 0 10px rgba(255,255,255,0.03),
        inset 0 -25px 50px rgba(0,0,0,0.30);
    }

    .top-stand{
      position:absolute;
      left:50%;
      top:56px;
      transform:translateX(-50%);
      width:520px;
      height:138px;
      border-radius:0 0 260px 260px;
      overflow:hidden;
      z-index:2;
      box-shadow:0 10px 30px rgba(0,0,0,0.22);
    }

    .top-stand::before{
      content:"";
      position:absolute;
      inset:0;
      background:
        repeating-linear-gradient(
          90deg,
          rgba(255,255,255,0.34) 0px,
          rgba(255,255,255,0.34) 2px,
          transparent 2px,
          transparent 18px
        ),
        repeating-linear-gradient(
          180deg,
          rgba(0,0,0,0.14) 0px,
          rgba(0,0,0,0.14) 2px,
          transparent 2px,
          transparent 16px
        ),
        linear-gradient(180deg, #f3cf57, #c28f17);
      opacity:0.95;
    }

    .top-stand-left,
    .top-stand-right{
      position:absolute;
      top:110px;
      width:180px;
      height:120px;
      overflow:hidden;
      z-index:2;
      opacity:0.95;
    }

    .top-stand-left{
      left:96px;
      transform:skewY(26deg);
      border-radius:20px 0 0 90px;
      background:
        repeating-linear-gradient(
          90deg,
          rgba(255,255,255,0.20) 0px,
          rgba(255,255,255,0.20) 2px,
          transparent 2px,
          transparent 16px
        ),
        repeating-linear-gradient(
          180deg,
          rgba(0,0,0,0.10) 0px,
          rgba(0,0,0,0.10) 2px,
          transparent 2px,
          transparent 14px
        ),
        linear-gradient(180deg, #bfc6d0, #7b8798);
    }

    .top-stand-right{
      right:96px;
      transform:skewY(-26deg);
      border-radius:0 20px 90px 0;
      background:
        repeating-linear-gradient(
          90deg,
          rgba(255,255,255,0.16) 0px,
          rgba(255,255,255,0.16) 2px,
          transparent 2px,
          transparent 16px
        ),
        repeating-linear-gradient(
          180deg,
          rgba(0,0,0,0.10) 0px,
          rgba(0,0,0,0.10) 2px,
          transparent 2px,
          transparent 14px
        ),
        linear-gradient(180deg, #d6a0ab, #8a5964);
    }

    .left-stand,
    .right-stand{
      position:absolute;
      top:164px;
      width:120px;
      height:280px;
      z-index:2;
      overflow:hidden;
    }

    .left-stand{
      left:42px;
      border-radius:120px 0 0 120px;
      background:
        repeating-linear-gradient(
          180deg,
          rgba(255,255,255,0.15) 0px,
          rgba(255,255,255,0.15) 2px,
          transparent 2px,
          transparent 16px
        ),
        repeating-linear-gradient(
          90deg,
          rgba(0,0,0,0.12) 0px,
          rgba(0,0,0,0.12) 2px,
          transparent 2px,
          transparent 16px
        ),
        linear-gradient(90deg, #616b7c, #8a94a3);
      transform:skewY(12deg);
    }

    .right-stand{
      right:42px;
      border-radius:0 120px 120px 0;
      background:
        repeating-linear-gradient(
          180deg,
          rgba(255,255,255,0.15) 0px,
          rgba(255,255,255,0.15) 2px,
          transparent 2px,
          transparent 16px
        ),
        repeating-linear-gradient(
          90deg,
          rgba(0,0,0,0.12) 0px,
          rgba(0,0,0,0.12) 2px,
          transparent 2px,
          transparent 16px
        ),
        linear-gradient(90deg, #8a94a3, #616b7c);
      transform:skewY(-12deg);
    }

    .field-area{
      position:absolute;
      top:118px;
      left:180px;
      right:180px;
      bottom:120px;
      border-radius:50% 50% 44% 44%;
      overflow:hidden;
      z-index:1;
      background:
        radial-gradient(ellipse at center, rgba(68,135,76,0.60), rgba(38,90,46,0.85)),
        linear-gradient(180deg, rgba(255,255,255,0.05), rgba(0,0,0,0.16));
      box-shadow:
        inset 0 0 40px rgba(0,0,0,0.20),
        0 0 30px rgba(0,0,0,0.16);
    }

    .pitch{
      position:absolute;
      width:120px;
      height:44px;
      background: linear-gradient(180deg, #ddd8ac, #aaa369);
      top:38px;
      left:50%;
      transform:translateX(-50%);
      border-radius:4px;
      opacity:0.92;
    }

    .field-text{
      position:absolute;
      top:76px;
      left:50%;
      transform:translateX(-50%);
      width:100%;
      text-align:center;
      color:rgba(255,255,255,0.82);
      font-size:16px;
      font-weight:800;
      letter-spacing:2px;
      text-transform:uppercase;
      text-shadow:0 2px 10px rgba(0,0,0,0.40);
    }

    .premium-top{
      position:absolute;
      top:12px;
      left:50%;
      transform:translateX(-50%);
      width:320px;
      height:120px;
      border-radius:0 0 180px 180px;
      background: linear-gradient(180deg, rgba(250,204,21,0.90), rgba(192,136,8,0.78));
      clip-path: polygon(10% 0%, 90% 0%, 100% 38%, 92% 100%, 8% 100%, 0% 38%);
      display:flex;
      align-items:center;
      justify-content:center;
      color:#2b2100;
      font-size:24px;
      font-weight:900;
      letter-spacing:1px;
      box-shadow: 0 12px 30px rgba(250,204,21,0.22);
      z-index:4;
    }

    .left-side-label,
    .right-side-label,
    .bottom-side-label{
      position:absolute;
      color:rgba(255,255,255,0.86);
      font-size:14px;
      font-weight:800;
      letter-spacing:2px;
      z-index:4;
      text-shadow:0 2px 10px rgba(0,0,0,0.4);
    }

    .left-side-label{
      left:56px;
      top:50%;
      transform:translateY(-50%) rotate(-90deg);
    }

    .right-side-label{
      right:50px;
      top:50%;
      transform:translateY(-50%) rotate(90deg);
    }

    .bottom-side-label{
      left:50%;
      bottom:20px;
      transform:translateX(-50%);
      font-size:16px;
    }

    .seat-board{
      position:absolute;
      left:50%;
      bottom:34px;
      transform:translateX(-50%);
      width:640px;
      max-width:92%;
      background:
        linear-gradient(180deg, rgba(15,23,42,0.94), rgba(2,6,23,0.98));
      border:1px solid rgba(255,255,255,0.08);
      border-radius:20px;
      padding:24px 22px 18px;
      box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.04),
        0 18px 40px rgba(0,0,0,0.45);
      z-index:5;
    }

    .seat-row{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      margin-bottom:12px;
      flex-wrap:nowrap;
    }

    .row-label{
      width:30px;
      text-align:center;
      font-size:30px;
      font-weight:800;
      color:rgba(255,255,255,0.95);
      margin-right:10px;
    }

    .seat{
      width:54px;
      height:42px;
      border-radius:10px;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:14px;
      font-weight:700;
      cursor:pointer;
      transition:0.25s ease;
      border:1px solid rgba(255,255,255,0.08);
      user-select:none;
    }

    .seat.available{
      background: linear-gradient(180deg, #dfe4ea, #b6bec9);
      color:#1f2937;
    }

    .seat.available:hover{
      transform:translateY(-2px);
      box-shadow:0 8px 20px rgba(255,255,255,0.08);
    }

    .seat.selected{
      background: linear-gradient(180deg, #78e06d, #2fa14a);
      color:#fff;
      box-shadow:0 0 0 1px rgba(74,222,128,0.35), 0 0 20px rgba(74,222,128,0.20);
    }

    .seat.booked{
      background: linear-gradient(180deg, #4e5c72, #2d384a);
      color:rgba(255,255,255,0.78);
      opacity:0.85;
      cursor:not-allowed;
    }

    .legend{
      display:flex;
      gap:28px;
      flex-wrap:wrap;
      align-items:center;
      margin-top:26px;
      justify-content:center;
    }

    .legend-item{
      display:flex;
      align-items:center;
      gap:10px;
      color:#d1d5db;
      font-size:18px;
    }

    .legend-box{
      width:26px;
      height:22px;
      border-radius:6px;
      display:inline-block;
    }

    .summary-card{
      border-radius:28px;
      padding:24px;
      position:sticky;
      top:20px;
    }

    .summary-card h2{
      font-size:24px;
      margin-bottom:18px;
      font-weight:800;
    }

    .stadium-preview{
      border:1px solid rgba(255,255,255,0.08);
      border-radius:22px;
      overflow:hidden;
      background: rgba(255,255,255,0.03);
      margin-bottom:22px;
    }

    .preview-top{
      height:150px;
      background:
        radial-gradient(circle at center, rgba(255,255,255,0.16), transparent 35%),
        linear-gradient(180deg, #343c47, #131a25);
      display:flex;
      align-items:center;
      justify-content:center;
      color:#cbd5e1;
      font-size:18px;
      font-weight:600;
    }

    .preview-bottom{
      padding:20px;
    }

    .category-title{
      font-size:22px;
      font-weight:700;
      margin-bottom:8px;
    }

    .category-price{
      font-size:28px;
      font-weight:800;
      margin-bottom:4px;
    }

    .small-text{
      color:#cbd5e1;
      font-size:18px;
    }

    .selected-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:16px;
      gap:10px;
      flex-wrap:wrap;
    }

    .selected-header h3{
      font-size:22px;
      font-weight:700;
    }

    .clear-btn{
      background:none;
      border:none;
      color:#cbd5e1;
      cursor:pointer;
      font-size:16px;
    }

    .clear-btn:hover{
      color:#fff;
    }

    .summary-seat{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      background: rgba(74,222,128,0.08);
      border:1px solid rgba(74,222,128,0.18);
      padding:12px 14px;
      border-radius:14px;
      margin-bottom:12px;
    }

    .summary-seat-name{
      font-size:20px;
      font-weight:700;
    }

    .summary-seat-row{
      color:#d1d5db;
      font-size:15px;
      margin-top:2px;
    }

    .remove-seat{
      width:32px;
      height:32px;
      border-radius:50%;
      border:none;
      background:rgba(255,255,255,0.10);
      color:#fff;
      font-size:18px;
      cursor:pointer;
    }

    .price-area{
      border-top:1px solid rgba(255,255,255,0.08);
      margin-top:18px;
      padding-top:18px;
    }

    .price-line{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:14px;
      font-size:20px;
    }

    .price-line .label{
      color:#d1d5db;
    }

    .total-line{
      border-top:1px solid rgba(255,255,255,0.08);
      margin-top:10px;
      padding-top:18px;
      display:flex;
      justify-content:space-between;
      align-items:center;
    }

    .total-line .total-label{
      font-size:24px;
      font-weight:700;
    }

    .total-line .total-value{
      font-size:48px;
      font-weight:800;
    }

    .checkout-btn{
      width:100%;
      margin-top:22px;
      padding:18px 20px;
      border:none;
      border-radius:18px;
      cursor:pointer;
      font-size:22px;
      font-weight:800;
      color:#fff;
      background: linear-gradient(90deg, #2f8f54, #7ad46f);
      box-shadow: 0 12px 28px rgba(74,222,128,0.20);
      transition:0.3s ease;
    }

    .checkout-btn:hover{
      transform:translateY(-2px);
      box-shadow: 0 16px 34px rgba(74,222,128,0.28);
    }

    .empty-seat-text{
      color:#9ca3af;
      font-size:17px;
    }

    @media (max-width: 1200px){
      .content-grid{
        grid-template-columns: 1fr;
      }

      .summary-card{
        position:static;
      }
    }

    @media (max-width: 900px){
      .teams-wrap h1{
        font-size:36px;
      }

      .vs-text{
        font-size:28px;
      }

      .stadium-box{
        min-height:700px;
        padding:34px 18px 20px;
      }

      .stadium-rings{
        height:540px;
      }

      .seat{
        width:46px;
        height:38px;
        font-size:13px;
      }

      .row-label{
        font-size:22px;
        width:24px;
      }

      .premium-top{
        width:260px;
        font-size:18px;
      }

      .top-stand{
        width:420px;
      }

      .field-area{
        left:150px;
        right:150px;
      }
    }

    @media (max-width: 600px){
      .category-card{
        min-width:100%;
      }

      .seat{
        width:38px;
        height:34px;
        font-size:11px;
      }

      .seat-row{
        gap:6px;
      }

      .row-label{
        width:18px;
        font-size:16px;
        margin-right:4px;
      }

      .teams-wrap h1{
        font-size:28px;
        gap:10px;
      }

      .flag-box{
        width:58px;
        height:42px;
        font-size:12px;
      }

      .match-subtext{
        font-size:15px;
      }

      .total-line .total-value{
        font-size:34px;
      }

      .checkout-btn{
        font-size:18px;
      }

      .premium-top{
        width:210px;
        height:90px;
        font-size:16px;
      }

      .stadium-rings{
        height:500px;
      }

      .gate1, .gate2{
        top:150px;
      }
    }
  </style>
</head>
<body>

  <div class="top-border">
    <div class="main-wrap">
      <div class="match-header">
        <div class="match-info">
          <a href="match.php?id=1" class="back-btn">← Back</a>

          <div class="teams-wrap">
            <h1>
              <div class="flag-box">PAK</div>
              PAK <span class="vs-text">vs</span> IND
              <div class="flag-box">IND</div>
            </h1>
            <div class="match-subtext">T20 • 14 Mar 2026 • 11:27 AM • Dubai Stadium</div>
          </div>
        </div>

        <div class="steps">
          <div class="step active">
            <div class="step-number">1</div>
            <span>Select Seats</span>
          </div>
          <div class="arrow">→</div>
          <div class="step active">
            <div class="step-number">2</div>
            <span>Payment</span>
          </div>
          <div class="arrow">→</div>
          <div class="step">
            <div class="step-number">3</div>
            <span>Confirmation</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="main-wrap">
    <div class="content-grid">

      <div>
        <div class="category-list" id="categoryCards">
          <button class="category-card general" data-category="General" data-price="2000">
            <div class="cat-name">General</div>
            <div class="cat-price">₹2,000</div>
          </button>

          <button class="category-card premium active-tab" data-category="Premium" data-price="3000">
            <div class="cat-name">Premium</div>
            <div class="cat-price" style="color:#fde68a;">₹3,000</div>
          </button>

          <button class="category-card vip" data-category="VIP" data-price="5000">
            <div class="cat-name">VIP</div>
            <div class="cat-price" style="color:#d8b4fe;">₹5,000</div>
          </button>

          <button class="category-card corporate" data-category="Corporate" data-price="10000">
            <div class="cat-name">Corporate</div>
            <div class="cat-price" style="color:#fca5a5;">₹10,000</div>
          </button>
        </div>

        <div class="stadium-box glass">
          <div class="gate-label gate1">GATE 1</div>
          <div class="gate-label gate2">GATE 2</div>
          <div class="gate-label gate3">GATE 3</div>
          <div class="gate-label gate4">GATE 4</div>

          <div class="stadium-rings">
            <div class="outer-ring"></div>
            <div class="inner-ring"></div>

            <div class="top-stand"></div>
            <div class="top-stand-left"></div>
            <div class="top-stand-right"></div>
            <div class="left-stand"></div>
            <div class="right-stand"></div>

            <div class="field-area">
              <div class="pitch"></div>
              <div class="field-text">STAGE / PLAYING FIELD</div>
            </div>

            <div class="premium-top" id="selectedCategoryLabel">PREMIUM</div>
            <div class="left-side-label">GENERAL</div>
            <div class="right-side-label">VIP | OVALSE</div>
            <div class="bottom-side-label">CORPORATE BOX</div>

            <div class="seat-board">
              <?php foreach ($rows as $row): ?>
                <div class="seat-row">
                  <div class="row-label"><?php echo $row; ?></div>
                  <?php for ($i = 1; $i <= $total_seats; $i++): ?>
                    <?php
                      $seat_id = $row . $i;
                      $seat_class = getSeatClass($seat_id, $booked_seats);
                    ?>
                    <div
                      class="seat <?php echo $seat_class; ?>"
                      data-seat="<?php echo $seat_id; ?>"
                      data-row="<?php echo $row; ?>"
                      onclick="selectSeat(this, '<?php echo $seat_id; ?>')"
                    >
                      <?php echo $seat_id; ?>
                    </div>
                  <?php endfor; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="legend">
          <div class="legend-item">
            <span class="legend-box" style="background:#d7dce5;"></span>
            Available
          </div>
          <div class="legend-item">
            <span class="legend-box" style="background:#3fa34d;"></span>
            Selected
          </div>
          <div class="legend-item">
            <span class="legend-box" style="background:#475569;"></span>
            Booked
          </div>
        </div>
      </div>

      <div>
        <div class="summary-card glass">
          <h2>Booking Summary</h2>

          <div class="stadium-preview">
            <div class="preview-top">Stadium Preview</div>
            <div class="preview-bottom">
              <div class="category-title">
                <span id="summaryCategoryTitle" style="color:#fde68a;">Premium</span> Category
              </div>
              <div class="category-price" id="summaryCategoryPrice">₹3,000</div>
              <div class="small-text">per seat</div>
            </div>
          </div>

          <div class="selected-header">
            <h3>Selected Seats (<span id="selectedCount">0</span>)</h3>
            <button type="button" class="clear-btn" onclick="clearAllSeats()">Clear All</button>
          </div>

          <div id="selectedSeatsContainer">
            <div class="empty-seat-text">No seats selected</div>
          </div>

          <div class="price-area">
            <div class="price-line">
              <span class="label">Ticket Price</span>
              <span id="ticketPriceText">₹0</span>
            </div>
            <div class="price-line">
              <span class="label">Convenience Fee</span>
              <span id="convenienceFee">₹0</span>
            </div>
            <div class="price-line">
              <span class="label">GST</span>
              <span id="gstAmount">₹0</span>
            </div>
          </div>

          <div class="total-line">
            <div class="total-label">Total</div>
            <div class="total-value" id="grandTotal">₹0</div>
          </div>

          <form action="process_booking.php" method="POST" onsubmit="return validateBooking();">
            <input type="hidden" name="selected_seats" id="selectedSeatsInput">
            <input type="hidden" name="total_amount" id="totalAmountInput">
            <input type="hidden" name="category" id="selectedCategoryInput" value="Premium">
            <input type="hidden" name="seat_price" id="selectedSeatPriceInput" value="3000">

            <button type="submit" class="checkout-btn">
              Proceed to Checkout →
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>

  <script>
    let selectedSeats = [];
    let selectedCategory = "Premium";
    let seatPrice = 3000;

    const categoryCards = document.querySelectorAll("#categoryCards .category-card");

    categoryCards.forEach(card => {
      card.addEventListener("click", function () {
        categoryCards.forEach(c => c.classList.remove("active-tab"));
        this.classList.add("active-tab");

        selectedCategory = this.dataset.category;
        seatPrice = parseInt(this.dataset.price);

        document.getElementById("selectedCategoryLabel").textContent = selectedCategory.toUpperCase();
        document.getElementById("summaryCategoryTitle").textContent = selectedCategory;
        document.getElementById("summaryCategoryPrice").textContent = "₹" + seatPrice.toLocaleString("en-IN");
        document.getElementById("selectedCategoryInput").value = selectedCategory;
        document.getElementById("selectedSeatPriceInput").value = seatPrice;

        const summaryCategoryTitle = document.getElementById("summaryCategoryTitle");
        let color = "#ffffff";

        if (selectedCategory === "General") color = "#93c5fd";
        if (selectedCategory === "Premium") color = "#fde68a";
        if (selectedCategory === "VIP") color = "#d8b4fe";
        if (selectedCategory === "Corporate") color = "#fca5a5";

        summaryCategoryTitle.style.color = color;

        updateSummary();
      });
    });

    function selectSeat(element, seatId) {
      if (element.classList.contains("booked")) return;

      const exists = selectedSeats.findIndex(seat => seat.id === seatId);

      if (exists === -1) {
        selectedSeats.push({
          id: seatId,
          row: seatId.charAt(0),
          price: seatPrice
        });
        element.classList.remove("available");
        element.classList.add("selected");
      } else {
        selectedSeats.splice(exists, 1);
        element.classList.remove("selected");
        element.classList.add("available");
      }

      updateSummary();
    }

    function removeSeat(seatId) {
      selectedSeats = selectedSeats.filter(seat => seat.id !== seatId);

      const seatElement = document.querySelector(`[data-seat="${seatId}"]`);
      if (seatElement && !seatElement.classList.contains("booked")) {
        seatElement.classList.remove("selected");
        seatElement.classList.add("available");
      }

      updateSummary();
    }

    function clearAllSeats() {
      selectedSeats.forEach(seat => {
        const seatElement = document.querySelector(`[data-seat="${seat.id}"]`);
        if (seatElement && !seatElement.classList.contains("booked")) {
          seatElement.classList.remove("selected");
          seatElement.classList.add("available");
        }
      });

      selectedSeats = [];
      updateSummary();
    }

    function updateSummary() {
      const selectedSeatsContainer = document.getElementById("selectedSeatsContainer");
      const selectedCount = document.getElementById("selectedCount");
      const ticketPriceText = document.getElementById("ticketPriceText");
      const convenienceFee = document.getElementById("convenienceFee");
      const gstAmount = document.getElementById("gstAmount");
      const grandTotal = document.getElementById("grandTotal");
      const selectedSeatsInput = document.getElementById("selectedSeatsInput");
      const totalAmountInput = document.getElementById("totalAmountInput");

      selectedCount.textContent = selectedSeats.length;

      if (selectedSeats.length === 0) {
        selectedSeatsContainer.innerHTML = `<div class="empty-seat-text">No seats selected</div>`;
        ticketPriceText.textContent = "₹0";
        convenienceFee.textContent = "₹0";
        gstAmount.textContent = "₹0";
        grandTotal.textContent = "₹0";
        selectedSeatsInput.value = "";
        totalAmountInput.value = 0;
        return;
      }

      let html = "";
      let subtotal = 0;

      selectedSeats.forEach(seat => {
        seat.price = seatPrice;
        subtotal += seat.price;

        html += `
          <div class="summary-seat">
            <div>
              <div class="summary-seat-name">${seat.id}</div>
              <div class="summary-seat-row">Row ${seat.row}</div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
              <div style="font-size:18px; font-weight:700;">₹${seat.price.toLocaleString("en-IN")}</div>
              <button type="button" class="remove-seat" onclick="removeSeat('${seat.id}')">×</button>
            </div>
          </div>
        `;
      });

      selectedSeatsContainer.innerHTML = html;

      const fee = selectedSeats.length * 150;
      const gst = Math.round(subtotal * 0.19);
      const total = subtotal + fee + gst;

      ticketPriceText.textContent = `₹${subtotal.toLocaleString("en-IN")}`;
      convenienceFee.textContent = `₹${fee.toLocaleString("en-IN")}`;
      gstAmount.textContent = `₹${gst.toLocaleString("en-IN")}`;
      grandTotal.textContent = `₹${total.toLocaleString("en-IN")}`;

      selectedSeatsInput.value = JSON.stringify(selectedSeats);
      totalAmountInput.value = total;
    }

    function validateBooking() {
      if (selectedSeats.length === 0) {
        alert("Please select at least one seat.");
        return false;
      }
      return true;
    }

    updateSummary();
  </script>
</body>
</html>