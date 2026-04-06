<?php
$rows = ['A', 'B', 'C', 'D', 'E', 'F'];
$total_seats = 8;
$booked_seats = ['A7', 'B7', 'C6', 'D8', 'E2'];

$stadium_bg = 'image/stadium.png';

function getSeatClass($seat_id, $booked_seats)
{
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
      border-radius:34px;
      min-height:760px;
      overflow:hidden;
      padding:24px 24px 28px;
      background:
        radial-gradient(circle at center, rgba(255,255,255,0.02), transparent 42%),
        linear-gradient(180deg, rgba(8,14,28,0.96), rgba(6,11,24,0.98));
    }

    .stadium-stage{
      position:relative;
      width:100%;
      min-height:760px;
    }

    .stadium-image-wrap{
      position:absolute;
      inset:0;
      display:flex;
      align-items:flex-start;
      justify-content:center;
      pointer-events:none;
      z-index:1;
    }

    .stadium-image{
      width:100%;
      max-width:980px;
      height:auto;
      object-fit:contain;
      opacity:0.95;
      filter: drop-shadow(0 30px 50px rgba(0,0,0,0.4));
      user-select:none;
      pointer-events:none;
      transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
      transform-origin: center center;
    }

    .ground-layer-wrap{
      position:absolute;
      left:50%;
      top:205px;
      transform:translateX(-50%);
      width:560px;
      height:280px;
      z-index:3;
      pointer-events:none;
      overflow:hidden;
      border-radius:50%;
    }

    .ground-layer{
      width:100%;
      height:100%;
      border-radius:50%;
      background:
        radial-gradient(circle at center, rgba(120,190,90,0.20), rgba(20,80,30,0.10) 55%, rgba(0,0,0,0) 70%),
        radial-gradient(circle at center, #4f9a42 0%, #2f6b2f 58%, #1d4422 100%);
      box-shadow:
        inset 0 0 40px rgba(255,255,255,0.06),
        inset 0 -30px 50px rgba(0,0,0,0.24);
      transition:
        transform 0.9s ease-in-out,
        background-position 0.9s ease-in-out;
      transform-origin:center center;
      position:relative;
    }

    .ground-layer::before{
      content:"";
      position:absolute;
      left:50%;
      top:50%;
      width:120px;
      height:44px;
      transform:translate(-50%, -50%);
      background: linear-gradient(180deg, #ddd8ac, #aaa369);
      border-radius:4px;
      opacity:0.92;
      box-shadow:0 0 10px rgba(255,255,255,0.08);
    }

    .ground-layer::after{
      content:"";
      position:absolute;
      inset:16px;
      border-radius:50%;
      border:1px solid rgba(255,255,255,0.16);
      opacity:0.45;
    }

    .overlay-label{
      position:absolute;
      z-index:4;
      font-weight:800;
      letter-spacing:2px;
      text-shadow:0 2px 12px rgba(0,0,0,0.55);
      color:rgba(255,255,255,0.92);
      pointer-events:none;
    }

    .gate1{ top:150px; left:28px; font-size:14px; }
    .gate2{ top:150px; right:28px; font-size:14px; }
    .gate3{ bottom:150px; left:20px; font-size:14px; }
    .gate4{ bottom:150px; right:20px; font-size:14px; }

    .premium-top{
      position:absolute;
      top:118px;
      left:50%;
      transform:translateX(-50%);
      width:210px;
      height:70px;
      z-index:5;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:22px;
      font-weight:900;
      color:#2b2100;
      background: linear-gradient(180deg, rgba(250,204,21,0.95), rgba(214,158,12,0.85));
      clip-path: polygon(8% 0%, 92% 0%, 100% 40%, 90% 100%, 10% 100%, 0% 40%);
      box-shadow: 0 10px 22px rgba(250,204,21,0.22);
      pointer-events:none;
      transition: background 0.4s ease, color 0.4s ease, transform 0.5s ease, top 0.5s ease;
    }

    .left-side-label{
      left:62px;
      top:355px;
      transform:rotate(-90deg);
      font-size:13px;
    }

    .right-side-label{
      right:52px;
      top:350px;
      transform:rotate(90deg);
      font-size:13px;
      text-align:center;
      line-height:1.1;
    }

    .bottom-side-label{
      left:50%;
      bottom:128px;
      transform:translateX(-50%);
      font-size:15px;
    }

    .field-text{
      position:absolute;
      top:190px;
      left:50%;
      transform:translateX(-50%);
      z-index:4;
      font-size:18px;
      font-weight:900;
      letter-spacing:2px;
      color:rgba(255,255,255,0.88);
      text-shadow:0 2px 12px rgba(0,0,0,0.55);
      pointer-events:none;
      transition: top 0.5s ease, left 0.5s ease, transform 0.5s ease;
    }

    /* seat box niche kar diya gaya hai */
    .seat-overlay{
      position:absolute;
      left:50%;
      top:285px;
      transform:translateX(-50%);
      width:570px;
      max-width:92%;
      z-index:6;
      background: linear-gradient(180deg, rgba(9,18,38,0.88), rgba(2,8,24,0.95));
      border:1px solid rgba(255,255,255,0.07);
      border-radius:18px 18px 20px 20px;
      padding:22px 20px 18px;
      box-shadow:
        0 18px 40px rgba(0,0,0,0.45),
        inset 0 1px 0 rgba(255,255,255,0.03);
      transition: top 0.6s ease, left 0.6s ease, transform 0.6s ease;
    }

    .seat-overlay::before{
      content:"";
      position:absolute;
      left:-18px;
      right:-18px;
      top:-22px;
      bottom:-16px;
      background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0));
      clip-path: polygon(6% 0%, 94% 0%, 100% 100%, 0% 100%);
      z-index:-1;
      border-radius:20px;
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
      width:28px;
      text-align:center;
      font-size:28px;
      font-weight:800;
      color:rgba(255,255,255,0.95);
      margin-right:8px;
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
      opacity:0.9;
      cursor:not-allowed;
    }

    .legend{
      display:flex;
      gap:28px;
      flex-wrap:wrap;
      align-items:center;
      margin-top:22px;
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
      height:170px;
      background:
        linear-gradient(rgba(4,10,20,0.16), rgba(4,10,20,0.40)),
        url('<?php echo $stadium_bg; ?>') center center / cover no-repeat;
      display:flex;
      align-items:center;
      justify-content:center;
      color:#cbd5e1;
      font-size:18px;
      font-weight:600;
      position:relative;
      text-shadow:0 2px 10px rgba(0,0,0,0.5);
    }

    .preview-top::after{
      content:"";
      position:absolute;
      inset:0;
      background: radial-gradient(circle at center, rgba(255,255,255,0.04), rgba(0,0,0,0.18));
      pointer-events:none;
    }

    .preview-top span{
      position:relative;
      z-index:2;
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
        padding:18px 14px 22px;
      }

      .stadium-stage{
        min-height:640px;
      }

      .ground-layer-wrap{
        width:500px;
        height:250px;
        top:192px;
      }

      .seat-overlay{
        width:520px;
        top:265px;
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
        top:102px;
        width:180px;
        height:62px;
        font-size:18px;
      }

      .field-text{
        top:172px;
        font-size:15px;
      }
    }

    @media (max-width: 600px){
      .category-card{
        min-width:100%;
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

      .stadium-stage{
        min-height:590px;
      }

      .ground-layer-wrap{
        width:360px;
        height:190px;
        top:170px;
      }

      .premium-top{
        top:92px;
        width:150px;
        height:54px;
        font-size:15px;
      }

      .field-text{
        top:152px;
        font-size:12px;
        letter-spacing:1px;
      }

      .left-side-label,
      .right-side-label,
      .bottom-side-label,
      .gate1,.gate2,.gate3,.gate4{
        font-size:11px;
      }

      .left-side-label{
        left:28px;
        top:300px;
      }

      .right-side-label{
        right:18px;
        top:294px;
      }

      .bottom-side-label{
        bottom:120px;
      }

      .seat-overlay{
        top:245px;
        width:92%;
        padding:16px 10px 14px;
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

      .total-line .total-value{
        font-size:34px;
      }

      .checkout-btn{
        font-size:18px;
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
          <div class="stadium-stage">
            <div class="stadium-image-wrap">
              <img src="<?php echo $stadium_bg; ?>" alt="Stadium" class="stadium-image">
            </div>

            <!-- <div class="ground-layer-wrap">
              <div class="ground-layer" id="groundLayer"></div>
            </div> -->

            <div class="overlay-label gate1">GATE 1</div>
            <div class="overlay-label gate2">GATE 2</div>
            <div class="overlay-label gate3">GATE 3</div>
            <div class="overlay-label gate4">GATE 4</div>

            <div class="premium-top" id="selectedCategoryLabel">PREMIUM</div>

            <div class="overlay-label left-side-label">GENERAL</div>
            <div class="overlay-label right-side-label">VIP | OVALSE</div>
            <div class="overlay-label bottom-side-label">CORPORATE BOX</div>
            <div class="field-text" id="fieldText">STAGE / PLAYING FIELD</div>

            <div class="seat-overlay" id="seatOverlay">
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
                  <?php
  endfor; ?>
                </div>
              <?php
endforeach; ?>
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
            <div class="preview-top"><span>Stadium Preview</span></div>
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
    const selectedCategoryLabel = document.getElementById("selectedCategoryLabel");
    const summaryCategoryTitle = document.getElementById("summaryCategoryTitle");
    const seatOverlay = document.getElementById("seatOverlay");
    const fieldText = document.getElementById("fieldText");
    const groundLayer = document.getElementById("groundLayer");
    const stadiumImage = document.querySelector(".stadium-image");

    const categoryColors = {
      "General": "#93c5fd",
      "Premium": "#fde68a",
      "VIP": "#d8b4fe",
      "Corporate": "#fca5a5"
    };

    const badgeStyles = {
      "General": {
        background: "linear-gradient(180deg, rgba(147,197,253,0.96), rgba(96,165,250,0.86))",
        color: "#0f172a"
      },
      "Premium": {
        background: "linear-gradient(180deg, rgba(250,204,21,0.95), rgba(214,158,12,0.85))",
        color: "#2b2100"
      },
      "VIP": {
        background: "linear-gradient(180deg, rgba(196,181,253,0.96), rgba(168,85,247,0.86))",
        color: "#1f1133"
      },
      "Corporate": {
        background: "linear-gradient(180deg, rgba(252,165,165,0.96), rgba(239,68,68,0.86))",
        color: "#2f0d0d"
      }
    };

    /* seat box overall niche kar diya gaya hai */
    const layoutPositions = {
      "Premium": {
        seatTop: "285px",
        seatLeft: "50%",
        seatTransform: "translateX(-50%)",
        fieldTop: "190px",
        fieldLeft: "50%",
        fieldTransform: "translateX(-50%)",
        badgeTop: "118px",
        stadiumTransform: "translateY(0px) scale(1.0)"
      },
      "General": {
        seatTop: "292px",
        seatLeft: "50%",
        seatTransform: "translateX(-50%)",
        fieldTop: "196px",
        fieldLeft: "50%",
        fieldTransform: "translateX(-50%)",
        badgeTop: "122px",
        stadiumTransform: "translateY(0px) translateX(-80px) scale(1.05)"
      },
      "VIP": {
        seatTop: "292px",
        seatLeft: "50%",
        seatTransform: "translateX(-50%)",
        fieldTop: "196px",
        fieldLeft: "50%",
        fieldTransform: "translateX(-50%)",
        badgeTop: "122px",
        stadiumTransform: "translateY(0px) translateX(80px) scale(1.05)"
      },
      "Corporate": {
        seatTop: "302px",
        seatLeft: "50%",
        seatTransform: "translateX(-50%)",
        fieldTop: "206px",
        fieldLeft: "50%",
        fieldTransform: "translateX(-50%)",
        badgeTop: "132px",
        stadiumTransform: "translateY(40px) scale(1.1)"
      }
    };

    const groundMoves = {
      "Premium": "translateX(0px) translateY(0px) rotate(0deg) scale(1.00)",
      "General": "translateX(-35px) translateY(8px) rotate(-12deg) scale(1.05)",
      "VIP": "translateX(35px) translateY(8px) rotate(12deg) scale(1.05)",
      "Corporate": "translateX(0px) translateY(20px) rotate(0deg) scale(1.08)"
    };

    function applyCategoryView(category) {
      const badgeStyle = badgeStyles[category];
      selectedCategoryLabel.style.background = badgeStyle.background;
      selectedCategoryLabel.style.color = badgeStyle.color;

      const layout = layoutPositions[category];
      seatOverlay.style.top = layout.seatTop;
      seatOverlay.style.left = layout.seatLeft;
      seatOverlay.style.transform = layout.seatTransform;

      fieldText.style.top = layout.fieldTop;
      fieldText.style.left = layout.fieldLeft;
      fieldText.style.transform = layout.fieldTransform;

      selectedCategoryLabel.style.top = layout.badgeTop;

      // Move the stadium image to show the selected category area
      if (stadiumImage) {
        stadiumImage.style.transform = layout.stadiumTransform;
      }

      if (groundLayer) {
        groundLayer.style.transform = groundMoves[category] || groundMoves["Premium"];
      }
    }

    categoryCards.forEach(card => {
      card.addEventListener("click", function () {
        categoryCards.forEach(c => c.classList.remove("active-tab"));
        this.classList.add("active-tab");

        selectedCategory = this.dataset.category;
        seatPrice = parseInt(this.dataset.price);

        selectedCategoryLabel.textContent = selectedCategory.toUpperCase();
        summaryCategoryTitle.textContent = selectedCategory;
        document.getElementById("summaryCategoryPrice").textContent = "₹" + seatPrice.toLocaleString("en-IN");
        document.getElementById("selectedCategoryInput").value = selectedCategory;
        document.getElementById("selectedSeatPriceInput").value = seatPrice;

        summaryCategoryTitle.style.color = categoryColors[selectedCategory] || "#ffffff";

        applyCategoryView(selectedCategory);
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

    applyCategoryView("Premium");
    updateSummary();
  </script>
</body>
</html>