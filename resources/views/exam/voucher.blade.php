<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Departmental Broadsheet</title>
    <script src="{{ public_path('tailwind.js') }}"></script>
    <link href="{{ public_path('daisy.css') }}" rel="stylesheet" type="text/css"/>
</head>
<body class="p-2">
    <div class="p-8 bg-white">
        <div class="grid grid-cols-3 gap-4">
            @foreach($vouchers as $voucher)
                <div class="border-2 border-dashed border-gray-400 p-4 rounded-lg relative bg-gray-50 break-inside-avoid shadow-sm">
                    <div class="flex justify-between items-center mb-3">
                        <span class="badge badge-outline badge-sm font-mono text-xs">{{ $voucher['serial'] }}</span>
                        <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                            <span class="text-[8px] font-bold text-primary">EDU</span>
                        </div>
                    </div>

                    <div class="text-center space-y-2">
                        <h3 class="text-[10px] uppercase font-bold text-gray-500 tracking-tighter">Result Checker PIN</h3>

                        <div class="bg-white border border-gray-200 py-2 rounded shadow-inner">
                        <span class="text-lg font-black tracking-widest text-primary font-mono">
                            {{ $voucher['pin'] }}
                        </span>
                        </div>

                        <p class="text-[9px] leading-tight text-gray-400">
                            Visit: <span class="font-semibold text-gray-600">School Portal</span><br>
                            Keep this PIN safe.
                        </p>
                    </div>

                    <div class="absolute -top-3 -right-3 text-gray-300">✂️</div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
