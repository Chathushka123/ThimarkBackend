<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    {{-- <meta name="csrf-token" content="{{ csrf_token() }}"> --}}

    {{-- <title>{{ config('app.name', 'Laravel') }}</title> --}}

    <!-- Scripts -->
    {{-- <script src="{{ asset('js/app.js') }}" defer></script> --}}

    <!-- Fonts -->
    {{-- <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet"> --}}

    <!-- Styles -->
    {{-- <link href="{{ asset('css/app.css') }}" rel="stylesheet"> --}}
    <style>
      /* * {
        box-sizing: border-box;
      } */
      .red {
          /* font-family: Nunito; */
          color: red;
        }
        .bg-red {
          background-color: red;
        }
        .h-5 {
          height: 5.0cm;
          border: 1px solid black;
        }
        .w-120 {
          /* width:300px; */
        }
        .border-around {
          border: 1px solid gray;
        }

      
    </style>
</head>
<body style="max-width: {{$params['page']['width']/10}}cm; height: 100%; margin: auto;">
  <?php
    $mTop = $params['page']['margin']['top']/10;
    $mRight = $params['page']['margin']['right']/10;
    $mBottom = $params['page']['margin']['bottom']/10;
    $mLeft = $params['page']['margin']['left']/10;

    $drawingWidth = $params['page']['width']/10 - $mRight - $mLeft;
  ?>
  {{-- <div style="position: absolute; width: {{$drawingWidth}}cm; margin: {{$mTop}}cm {{$mRight}}cm {{$mBottom}}cm {{$mLeft}}cm;"> --}}
    <div style="max-width: {{$params['page']['width']/10}}cm; padding: {{$mTop}}cm {{$mRight}}cm {{$mBottom}}cm {{$mLeft}}cm; border: 1px solid black;">
      {{-- <div style="width: 100%; border: 1px solid black;"> --}}
    @for ($r = 1; $r <= $params['rows']; $r++)
      <div style="width:100%; display: flex;">
        <?php
          $colWidth = ($drawingWidth)/$params['cols'];
        ?>
        @for ($i = 1; $i <= $params['cols']; $i++)
          <div style="overflow:hidden; text-overflow:clip; width: {{$colWidth}}cm; background-color: {{((((float)($i / 2)) - (int)($i / 2))*2) == 0 ? ("red") : ("yellow")}};">test</div>
        @endfor
      </div>
    @endfor
  </div>
</body>
</html>
