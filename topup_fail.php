<?php
http_response_code(400);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Top-up Failed</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
*{
  box-sizing:border-box;
  font-family: Inter, Arial, sans-serif;
}

body{
  margin:0;
  height:100vh;
  background:#ff7a5c;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
}

/* white meshy background lines */
body::before{
  content:"";
  position:absolute;
  inset:0;
  background:
    repeating-linear-gradient(
      120deg,
      rgba(255,255,255,0.25) 0px,
      rgba(255,255,255,0.25) 1px,
      transparent 1px,
      transparent 70px
    ),
    repeating-linear-gradient(
      60deg,
      rgba(255,255,255,0.18) 0px,
      rgba(255,255,255,0.18) 1px,
      transparent 1px,
      transparent 90px
    );
  pointer-events:none;
}

/* background list text */
.bg-text{
  position:absolute;
  top:0;
  right:0;
  width:45%;
  height:100%;
  display:flex;
  flex-direction:column;
  justify-content:center;
  gap:18px;
  padding-right:30px;
  font-size:14px;
  color:rgba(0,0,0,0.35);
  pointer-events:none;
}

/* purple secondary card */
.shadow{
  position:absolute;
  width:420px;
  height:420px;
  background:#6a5cff;
  transform:translate(18px,18px);
}

/* main card */
.window{
  position:relative;
  width:420px;
  height:420px;
  background:#fff;
  border-radius:6px;
  box-shadow:0 20px 40px rgba(0,0,0,0.3);
  overflow:hidden;
  border:1px solid rgba(0,0,0,0.15);
}

/* top bar */
.titlebar{
  height:38px;
  background:#000;
  display:flex;
  align-items:center;
  padding:0 10px;
  gap:8px;
}

.dot{
  width:10px;
  height:10px;
  background:#fff;
  border-radius:50%;
  opacity:0.9;
}

/* grid */
.grid{
  position:absolute;
  inset:38px 0 0 0;
  background:
    linear-gradient(#00000033 1px, transparent 1px),
    linear-gradient(90deg,#00000033 1px, transparent 1px);
  background-size:40px 40px;
}

/* content */
.content{
  position:relative;
  height:100%;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  text-align:center;
  padding:24px;
}

.icon{
  font-size:68px;
  font-weight:900;
  color:#e53935;
  line-height:1;
}

.title{
  font-size:28px;
  font-weight:800;
  margin-top:10px;
}

.msg{
  margin-top:18px;
  font-size:15px;
  line-height:1.7;
  color:#333;
  max-width:300px;
}

/* button */
.btn{
  margin-top:26px;
  padding:14px 34px;
  background:#000;
  color:#fff;
  font-weight:800;
  text-decoration:none;
  border-radius:4px;
  border:1px solid #000;
  transition:transform .2s, box-shadow .2s;
}

.btn:hover{
  transform:translateY(-3px);
  box-shadow:0 10px 20px rgba(0,0,0,0.3);
}
</style>
</head>

<body>

<div class="bg-text">
<?php
for($i=0;$i<22;$i++){
  echo "<div>Top-up Failed</div>";
}
?>
</div>

<div class="shadow"></div>

<div class="window">
  <div class="titlebar">
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
  </div>

  <div class="grid"></div>

  <div class="content">
    <div class="icon">×</div>
    <div class="title">Failed to Top-up</div>

    <div class="msg">
      No worries — try one more time.<br>
      Maybe this time it works.<br><br>
      If the issue continues, please<br>
      check your payment details.
    </div>

    <a class="btn" href="https://MineMechanics.xo.je/topup.php">
      TRY AGAIN
    </a>
  </div>
</div>

</body>
</html>