<?php
    $this->includeAtTemplateBase('includes/header.php');
?>
<?php if($this->data['error']): ?>
    <p class='error'><?= $this->data['error'] ?></p>
<?php endif; ?>
    <div id="greeter">
      <p>
        <?php echo $this->data['appstore']?><br>
      <a style='border-bottom: none' href="https://apps.apple.com/nl/app/readid-ready/id1486164875" target='_blank'>
        <img style='display: inline;' src='./apple.png' height=30px>
      </a>
      <a style='border-bottom: none' href="https://play.google.com/store/apps/details?id=com.readid.ready" target='_blank'>
        <img style='display: inline;' src='./play.png' height=30px>
      </a>
      </p>
      <input type="button" name="ctu" value="<?php echo $this->data['continue'] ?>" onclick="Greeter();">
    </div>
    <div id="qr" style='display: none'>
<?php if ($this->data['qrimg']): ?>
    <p>
    <a id="qrcode" href="https://app.readid.com/ready/intermediate/ready/activate?jwt=<?=$this->data['token']?>&callback=<?=$this->data['callbackUrl']?>" onclick="App();">
      <img src="<?= $this->data['qrimg'] ?>">
    </a>
    <div style="margin-left: 27px; margin-top: -20px; margin-bottom: 20px;">
      <p><img id="countbar" src="counter.png" height=2></p>
    </div>
    </p>
<?php else: ?>
      <h2><?php echo $this->data['noqr'] ?></h2>
<?php endif; ?>
    <form method="post" id='form' action="?">
      <input type="hidden" name="ReturnTo" value="<?= htmlspecialchars($this->data['returnTo']) ?>">
      <p><input type="submit" name="action" value="<?= $this->data['stop'] ?>"></p>
    </form>
    </div>

<script type="text/javascript">
  var success = false;
  var timeout = <?=$this->data['timeout']?>;
  var fullwidth = 245;
  var countbar = document.getElementById("countbar");
  var qrcode = document.getElementById("qrcode");
  var greeter = document.getElementById("greeter");
  var qr = document.getElementById("qr");

  var timer = timeout;
  countbar.style.width = fullwidth + "px";

  var xhr = new XMLHttpRequest();


  xhr.onload = function (e) {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        success = true;
      } else if (xhr.status === 202) {
        //console.error('Waiting...');
      } else {
        alert('Unknown Error');
        console.error(xhr.statusText);
      }
    }
  };

  xhr.onerror = function (e) {
    alert('Unknown Error');
    console.error(xhr.statusText);
  };

  function App() {
    qrcode.innerHTML = "<?=$this->data['close']?>";
    countbar.style.display = 'none';
    timer = 0;
  }

  function getResult() {
    xhr.open("GET", "/simplesaml/module.php/readid/wait.php", true);
    xhr.send(null);
    timer = timer - 1;
    countbar.style.width = Math.round(fullwidth*(timer/timeout)) + "px";
    if (success) document.getElementById("form").submit();
    else if (timer >= 0) setTimeout(getResult, 1000);
    else qrcode.innerHTML = "<?=$this->data['timeout_msg']?>";
  }

  function Greeter() {
    greeter.style.display = "none";
    qr.style.display = "block";
    setTimeout(getResult, 1000);
  }
</script>
<?php
    $this->includeAtTemplateBase('includes/footer.php');
?>


