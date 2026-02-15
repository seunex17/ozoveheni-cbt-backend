@php use App\Services\StudentResultService; @endphp
    <!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $student->first_name }} {{ $student->last_name }} </title>
    <script src="{{ public_path('tailwind.js') }}"></script>
    <link href="{{ public_path('daisy.css') }}" rel="stylesheet" type="text/css"/>
</head>
<body>

    <div class="bg-gray-200 p-4 flex justify-center">
        <div class="relative w-full bg-white p-4 overflow-hidden">

            <div class="absolute inset-0 flex items-center justify-center pointer-events-none select-none opacity-[0.1]">
                <div class="px-10 py-5 flex w-full h-full flex-col justify-center items-center">
                    <img class="w-96" src="{{ public_path('images/logo.jpg') }}" alt="">
                </div>
            </div>

            <div class="absolute inset-0 opacity-[0.02] pointer-events-none flex flex-wrap gap-20 p-10">
                @for ($i = 0; $i < 20; $i++)
                    <span class="text-xs font-bold uppercase rotate-12">Apex University 2026</span>
                @endfor
            </div>

            <div class="relative z-10">
                <div class="flex justify-between items-center border-b-4 border-double border-primary pb-6 mb-4">
                    <img src="{{ public_path('images/logo.jpg') }}" alt="School Logo" class="w-24 h-24 grayscale">

                    <div class="text-center flex-1 gap-1">
                        <h1 class="text-2xl font-black uppercase text-gray-800">Ozoveheni College Of Health Technology</h1>
                        <p class="text-xs font-serif uppercase tracking-widest text-gray-500">Office of the Registrar (Academic Division)</p>
                        <div class="mt-4 badge badge-primary badge-outline font-bold px-6 capitalize">{{ $exam->title }} ({{ $exam->subtitle }}) RESULT</div>
                    </div>

                    <div class="avatar">
                        <div class="w-24 h-28 rounded-sm ring-1 ring-gray-400">
                            <img src="{{ public_path("storage/$student->photo") }}" alt="Student Passport"/>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4 bg-gray-50 p-4 rounded-lg border border-gray-100">
                    <div class="space-y-1">
                        <p class="text-[10px] text-gray-500 uppercase font-bold">Student Name</p>
                        <p class="font-bold text-lg">{{ $student->last_name }} {{ $student->middle_name }} {{ $student->first_name }}</p>
                    </div>
                    <div class="text-right space-y-1">
                        <p class="text-[10px] text-gray-500 uppercase font-bold">Matriculation Number</p>
                        <p class="font-mono font-bold text-lg">{{ $student->reg_no }}</p>
                    </div>
                </div>

                <table class="table w-full border border-gray-300">
                    <thead class="bg-gray-100 text-gray-700">
                    <tr class="text-center border-b border-gray-300">
                        <th class="border-r border-gray-300 w-24">CODE</th>
                        <th class="text-left border-r border-gray-300">COURSE TITLE</th>
                        <th>1st CA</th>
                        <th>2nd CA</th>
                        <th>EXAM</th>
                        <th>TOTAL</th>
                        <th>GRADE</th>
                    </tr>
                    </thead>
                    <tbody class="text-xs font-semibold">
                    @foreach($singleExams as $singleExam)
                        <tr class="text-center border-b border-gray-200">
                            <td class="bg-gray-50">{{ $singleExam->course->code }}</td>
                            <td class="text-left">{{ $singleExam->course->name }}</td>
                            <td>{{ StudentResultService::courseScore($student, $singleExam->course, $exam, 'first_ca') }}</td>
                            <td>{{ StudentResultService::courseScore($student, $singleExam->course, $exam, 'second_ca') }}</td>
                            <td>{{ StudentResultService::courseScore($student, $singleExam->course, $exam, 'exam') }}</td>
                            <td class="font-bold">{{ StudentResultService::courseTotalScore($student, $singleExam->course, $exam) }}</td>
                            <td><span>{{ StudentResultService::courseGrade($student, $singleExam->course, $exam) }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="mt-8 w-1/2 ml-auto">
                    <table class="table table-compact w-full border border-gray-300">
                        <tr class="bg-gray-50">
                            <td class="font-bold">Current GPA</td>
                            <td class="text-right">4.42</td>
                        </tr>
                        <tr>
                            <td class="font-bold">Cumulative GPA</td>
                            <td class="text-right">4.15</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-24 flex justify-between items-end px-10">
                    <div class="text-center">
                        <div class="w-40 border-b border-gray-400 mb-2"></div>
                        <p class="text-[10px] font-bold uppercase">Departmental H.O.D</p>
                    </div>

                    <div class="text-center relative">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/3/3a/Jon_Kirsch_Signature.png" class="absolute -top-12 left-5 w-32 mix-blend-multiply opacity-80"/>
                        <div class="w-40 border-b border-gray-400 mb-2"></div>
                        <p class="text-[10px] font-bold uppercase">Registrar's Signature</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
