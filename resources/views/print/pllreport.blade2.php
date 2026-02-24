
<style>
  .test{
    font-size : 14px;
  }
  th span {
  transform-origin: 30% 30%;
  transform: rotate(-90deg); 
   /* white-space: nowrap;  */
  display: block;
  left: 50%;
  bottom: 0;
  max-height:20px;
  position: relative;
}


</style>


<!-- <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>
  <link href='https://fonts.googleapis.com/css?family=Libre Barcode 39' rel='stylesheet'>
  <!-- <link href='../sass/IDAUTOMATIONHC39M.TTF' rel='stylesheet'> -->
  <style>
    p {
      font-family: 'Libre Barcode 39';
      margin:0;
      padding:0;
      font-size:30px;
    }
    </style>
</head>
<body style=""> -->

<script type="text/php">
    if ( isset($pdf) ) {
        $font = "";
        $pdf->page_text(400, 560, "Page: {PAGE_NUM} of {PAGE_COUNT}", $font, 12, array(0,0,0));
    }
</script>

<h1 style="font-size:20px">Hi</h1>