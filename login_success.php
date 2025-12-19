<?php
http_response_code(200);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login Successful</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
*{
  box-sizing:border-box;
  font-family: Inter, Arial, sans-serif;
}

body{
  margin:0;
  height:100vh;
  background:#000;
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
      rgba(255,255,255,0.06) 0px,
      rgba(255,255,255,0.06) 1px,
      transparent 1px,
      transparent 60px
    ),
    repeating-linear-gradient(
      60deg,
      rgba(255,255,255,0.05) 0px,
      rgba(255,255,255,0.05) 1px,
      transparent 1px,
      transparent 80px
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
  color:rgba(255,255,255,0.12);
  pointer-events:none;
}

/* purple secondary card */
.shadow{
  position:absolute;
  width:520px;
  height:520px;
  background:#6a5cff;
  transform:translate(18px,18px);
}

/* main card */
.window{
  position:relative;
  width:520px;
  height:520px;
  background:#000;
  border-radius:6px;
  box-shadow:0 20px 40px rgba(0,0,0,0.7);
  overflow:hidden;
  border:1px solid rgba(255,255,255,0.25);
}

/* top bar */
.titlebar{
  height:38px;
  background:#0a0a0a;
  display:flex;
  align-items:center;
  padding:0 10px;
  gap:8px;
  border-bottom:1px solid rgba(255,255,255,0.15);
}

.dot{
  width:10px;
  height:10px;
  background:#777;
  border-radius:50%;
}

/* grid */
.grid{
  position:absolute;
  inset:38px 0 0 0;
  background:
    linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
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
  padding:30px;
  color:#fff;
}

.check{
  font-size:72px;
  font-weight:900;
  color:#7dff7a;
  line-height:1;
}

.title{
  font-size:34px;
  font-weight:800;
  margin-top:12px;
  letter-spacing:1px;
}

.subtitle{
  font-size:18px;
  margin-top:8px;
  color:#bbb;
}

.msg{
  margin-top:22px;
  font-size:15px;
  line-height:1.7;
  color:#ccc;
  max-width:360px;
}

/* button */
.btn{
  margin-top:30px;
  padding:14px 40px;
  background:transparent;
  color:#fff;
  font-weight:800;
  text-decoration:none;
  border-radius:4px;
  border:1px solid rgba(255,255,255,0.85);
  transition:transform .2s, box-shadow .2s, background .2s;
}

.btn:hover{
  background:#fff;
  color:#000;
  transform:translateY(-3px);
  box-shadow:0 10px 20px rgba(0,0,0,0.6);
}
</style>
</head>

<body>

<div class="bg-text">
<?php
for($i=0;$i<18;$i++){
  echo "<div>Login Successful</div>";
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
    <div class="check">✓</div>
    <div class="title">Welcome Back</div>
    <div class="subtitle">Login Successful</div>

    <div class="msg">
      Thank you for logging in.<br>
      Your session has been securely verified<br>
      and full access has been granted.
    </div>

    <a class="btn" href="https://MineMechanics.xo.je/dashboard.php">
      CONTINUE
    </a>
  </div>
</div>

</body>
</html>