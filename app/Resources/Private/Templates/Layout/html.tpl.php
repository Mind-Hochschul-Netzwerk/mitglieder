<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?=!empty($htmlTitle) ? ($htmlTitle.' – ') : ''?>Mein MHN</title>

    <!-- META -->

    <link rel="icon" href="/favicon.png">

    <!-- CSS -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/bootstrap-toggle.min.css" rel="stylesheet">
    <link href="/css/sidebar.css" rel="stylesheet">
    <link href="/css/MHN.css?<?=md5((string)filemtime('/var/www/html/css/MHN.css'))?>" rel="stylesheet">
  </head>
  <body>
    <script src="/js/jquery.min.js"></script>
    <script src="/js/bootstrap.min.js"></script>
    <script src="/js/sidebar.js"></script>
    <script src="/js/bootstrap-toggle.min.js"></script>
    <script src="/js/MHN.js"></script>
    <script src="/js/marked.min.js"></script>
    <script>csrfToken = "<?=$_csrfToken()?>";</script>

    <?=$_contents->raw?>
  </body>
</html>
