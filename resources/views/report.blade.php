@php use App\Services\ReportService; @endphp
    <!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Departmental Broadsheet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css"/>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: white;
            color: black;
        }

        /* Strict borders for printing */
        table {
            border-collapse: collapse;
            width: 100%;
            page-break-inside: auto;
        }

        thead {
            /* This is the magic line that repeats the header */
            display: table-header-group;
        }

        tr {
            /* Prevents a single row from being split across two pages */
            page-break-inside: avoid;
            page-break-after: auto;
        }

        /* Optional: Add a little padding at the top of new pages */
        thead tr th {
            position: sticky;
            top: 0;
        }

        th, td {
            border: 1px solid #000 !important;
            padding: 4px 2px !important;
            text-align: center;
            font-size: 9px;
        }

        .course-header {
            font-size: 8px;
            font-weight: bold;
            background: #f3f4f6;
        }

        .vertical-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            padding: 5px 2px;
        }
    </style>
</head>
<body class="p-2">

    <div class="flex items-center justify-between mb-4 border-b-2 border-black pb-2">
        <div class="flex items-center gap-3">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo.jpg'))) }}" class="h-12">
            <div>
                <h1 class="text-lg font-bold uppercase">Ozoveheni College of Health Technology</h1>
                <p class="text-xs italic">Department of {{ $exam->department->name }} — {{ $exam->title }} Broadsheet ({{ $exam->subtitle }})</p>
            </div>
        </div>
        <div class="text-right text-[10px]">
            <p><strong>GPA:</strong> Grade Point Average</p>
        </div>
    </div>

    <table class="mt-3">
        <thead>
        <tr class="bg-gray-100">
            <th class="w-8">S/N</th>
            <th class="w-48 text-left px-2">Reg No</th>
            @foreach($singleExams as $singleExam)
                <th class="w-16">{{ $singleExam->course->code }}</th>
            @endforeach
            <th class="w-16">GPA</th>
        </tr>
        </thead>
        <tbody>
        @foreach($students as $index => $student)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-left px-2 font-semibold">
                    <div class="text-[8px]">{{ $student->reg_no }}</div>
                </td>

                @foreach($singleExams as $result)
                    <td class="font-bold">
                        {{ ReportService::score($exam, $student, $result->course) }}
                    </td>
                @endforeach
                <td class="bg-blue-50 font-black text-blue-800">{{ number_format(0, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="mt-12 flex justify-between px-10">
        <div class="text-center">
            <div class="border-t border-black w-40 mb-1"></div>
            <p class="text-[9px] font-bold">EXAM OFFICER</p>
        </div>
        <div class="text-center">
            <div class="border-t border-black w-40 mb-1"></div>
            <p class="text-[9px] font-bold">H.O.D. ACCOUNTANCY</p>
        </div>
        <div class="text-center">
            <div class="border-t border-black w-40 mb-1"></div>
            <p class="text-[9px] font-bold">COLLEGE REGISTRAR</p>
        </div>
    </div>

</body>
</html>
