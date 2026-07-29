<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<head>
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: sans-serif;
            margin: 6px;
        }

        .outer-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 4px;
        }

        .sticker-td {
            width: 33.33%;
            max-width: 33.33%;
            vertical-align: top;
        }

        .sticker {
            border: 2px solid #000;
            width: 100%;
            border-collapse: collapse;
        }

        .sticker-qr {
            vertical-align: middle;
            text-align: center;
            width: 40%;
            padding: 4px;
            border-right: 1px solid #555;
        }

        .sticker-info {
            vertical-align: middle;
            padding: 4px 6px;
            width: 60%;
        }

        .info-label {
            font-size: 8px;
            color: #555;
            font-weight: bold;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 3px;
            word-break: break-word;
        }
    </style>
</head>

<body>
    @php $colCount = 0; $stickerCount = 0; @endphp
    <table class="outer-table" cellspacing="4" cellpadding="0">
        @foreach($users as $user)
            @php $colCount++; $stickerCount++; @endphp
            @if($colCount == 1)
        <tr>
            @endif

            <td class="sticker-td">
                <table class="sticker" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="sticker-qr">
                            <img src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(55)->generate((string) $user->id)) !!} ">
                        </td>
                        <td class="sticker-info">
                            <div class="info-label">Name</div>
                            <div class="info-value">{{ $user->name }}</div>
                            <div class="info-label">Email</div>
                            <div class="info-value">{{ $user->email }}</div>
                        </td>
                    </tr>
                </table>
            </td>

            @if($colCount == 3)
        </tr>
        @php $colCount = 0; @endphp
            @endif

            @if($stickerCount == 18 && !$loop->last)
                @if($colCount > 0)
                    @for($i = $colCount; $i < 3; $i++)
        <td class="sticker-td"></td>
                    @endfor
        </tr>
        @php $colCount = 0; @endphp
                @endif
    </table>
    <pagebreak style="page-break-before: always;" pagebreak="true"></pagebreak>
    <table class="outer-table" cellspacing="4" cellpadding="0">
        @php $stickerCount = 0; @endphp
            @endif
        @endforeach

        @if($colCount > 0)
            @for($i = $colCount; $i < 3; $i++)
        <td class="sticker-td"></td>
            @endfor
        </tr>
        @endif
    </table>
</body>

</html>
