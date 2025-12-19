<?php
http_response_code(400);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>400 Bad Request</title>
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

/* clean list background */
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
  color:rgba(0,0,0,0.45);
  pointer-events:none;
}

/* purple shadow layer */
.shadow{
  position:absolute;
  width:420px;
  height:420px;
  background:#6a5cff;
  transform:translate(18px,18px);
}

/* main window */
.window{
  position:relative;
  width:420px;
  height:420px;
  background:#fff;
  border-radius:6px;
  box-shadow:0 20px 40px rgba(0,0,0,0.25);
  overflow:hidden;
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

/* grid background */
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
  padding:20px;
}

.big400{
  font-size:120px;
  font-weight:900;
  line-height:1;
}

.sub{
  font-size:26px;
  margin-top:10px;
  letter-spacing:2px;
}

.msg{
  margin-top:18px;
  font-size:15px;
  line-height:1.6;
  color:#333;
  max-width:300px;
}
</style>
</head>

<body>

<div class="bg-text">
<?php
for($i=0;$i<22;$i++){
  echo "<div>400 Bad Request</div>";
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
    <div class="big400">400</div>
    <div class="sub">Bad Request</div>

    <div class="msg">
      The request sent by your browser<br>
      was invalid or malformed.<br><br>
      Please check the URL, headers,<br>
      or try again later.
    </div>
  </div>
</div>

</body>
</html>