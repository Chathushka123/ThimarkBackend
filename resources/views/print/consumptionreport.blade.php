<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>

</head>
<body style="font-family: sans-serif;">
  <h2 style ="text-align: center;text-decoration: underline;">Consumption Report</h2>
  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;">
    <tr style = "font-size:12px;">
      <th style = "padding:5px;"> FPO No </th>
      <th style = "padding:5px;"> Fabric </th>
      <th style = "padding:5px;"> Cut No</th>
      <th style = "padding:5px;"> Consumption</th>
    </tr>
    @foreach ($fpo_cut_plans as $fpo_cut_plan)
    <tr>
      <td style = "padding:5px; font-size:12px;">{{$fpo_cut_plan->wfx_fpo_no}}</td>
      <td style = "padding:5px; font-size:12px;">{{$fpo_cut_plan->fabric}}</td>
      <td style = "padding:5px; font-size:12px;">{{$fpo_cut_plan->cut_no}}</td>
      <td style = "padding:5px; font-size:12px; text-align:right;">{{$fpo_cut_plan->consumption}}</td>
    </tr>
    @endforeach
  </table>  

</body>
</html>