@php
    use App\Services\StudentLevelService;
    use App\Services\StudentResultService;
    use Illuminate\Support\Facades\Storage;

    $imageData = static function (?string $path): ?string {
        if (! $path || ! is_file($path)) {
            return null;
        }
        $mime = mime_content_type($path);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    };
    $logo = $imageData(public_path('images/logo.jpg'));
    $stamp = $imageData(public_path('images/stamp.png'));
    $passport = $imageData($student->photo ? Storage::disk('public')->path($student->photo) : null);
    $level = $exam->level ? strtoupper($exam->level) : StudentLevelService::getLevelLabel($student->set);
    $remark = StudentResultService::courseRemark($student, $exam);
@endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Result slip — {{ $student->reg_no }}</title><style>

@page { size:A4 portrait; margin:13mm 14mm 11mm; }
*{box-sizing:border-box} :root{color-scheme:light} body{margin:0;background:#fff;color:#213336;font-family:Arial,Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{width:100%;padding:0;position:relative;}
header{display:flex;align-items:center;gap:3mm;padding-bottom:3mm;border-bottom:.6mm solid #14594f}
.logo{width:15mm;height:15mm;object-fit:contain;flex-shrink:0}
.school{flex:1}.school h1{margin:0;color:#164f48;font-size:16pt;line-height:1.1;letter-spacing:.4px}.school h2{margin:1.3mm 0;font-size:9pt;letter-spacing:.4px;font-weight:700}.school p{margin:0;font-size:8pt;color:#657376;line-height:1.5}.office{text-align:right;font-size:7pt;line-height:1.6;color:#657376;max-width:30mm}
.title-row{display:flex;justify-content:space-between;align-items:flex-end;margin:3mm 0;gap:4mm}.eyebrow{margin:0 0 1.5mm;color:#647576;font-size:7pt;letter-spacing:1.7px;font-weight:700;text-transform:uppercase}.title-row h2{margin:0;font-size:17pt;font-weight:700;letter-spacing:-.6px;color:#203b37}.proposal{padding:2mm 2.5mm;border:.25mm solid #c9d4d2;border-radius:1mm;color:#566a66;font-size:6.5pt;font-weight:700;letter-spacing:.6px;white-space:nowrap}
.identity{display:flex;gap:4mm;padding:3mm;border:.25mm solid #d7e1df;border-radius:2mm; background:#f8faf9}.identity-body{flex:1;min-width:0}.label{display:block;color:#697a77;font-size:6.5pt;letter-spacing:.7px;text-transform:uppercase;margin-bottom:1mm}.name{font-size:13pt;letter-spacing:.1px;font-weight:700;margin-bottom:2.5mm}.details{display:grid;grid-template-columns:1.25fr 1fr;gap:2mm 4mm}.value{font-size:9pt;font-weight:600;line-height:1.4}.registration{font-family:'Courier New',monospace;font-size:10pt;letter-spacing:.1px}.passport{width:22mm;height:27mm;border:.25mm solid #d1dbd8;background:#edf2ef;display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;overflow:hidden;flex-shrink:0}.head{height:9mm;width:9mm;background:#b7c7bf;border-radius:50%;margin-top:1mm}.shoulders{height:11mm;width:19mm;background:#b7c7bf;border-radius:10mm 10mm 0 0;margin-top:2mm}.passport small{font-size:5.5pt;letter-spacing:.7px;color:#5e7369;position:absolute;bottom:1.5mm;background:#edf2ef;padding:1mm;width:100%;text-align:center}.passport-note{font-size:6pt;text-align:center;color:#7d8c86;margin-top:1.5mm}
.exam-band{display:flex;justify-content:space-between;gap:4mm;margin:3mm 0}.exam-band .value{font-size:10pt}.session{text-align:right}
.records{position:relative}.records::before{content:'';position:absolute;inset:14mm 35mm 4mm;background:url('{{ $logo ?? '' }}') center/contain no-repeat;opacity:.025;pointer-events:none;z-index:0}.records table{width:100%;border-collapse:collapse;table-layout:fixed;position:relative;font-size:8.3pt}.records th{text-align:left;padding:2mm 2.4mm;background:#eaf1ee;color:#28584f;font-size:6.5pt;letter-spacing:.5px;font-weight:700;border-top:.4mm solid #2b685a;border-bottom:.25mm solid #cbdad3}.records td{padding:1.6mm 2.4mm;border-bottom:.2mm solid #e0e7e3;line-height:1.25;vertical-align:middle}.records tr:nth-child(even) td{background:rgba(242,246,243,.65)}.records th.num,.records td.num{text-align:center;font-variant-numeric:tabular-nums}.records .code{font-family:'Courier New',monospace;font-size:8pt;color:#506560}.records .total{font-weight:700;color:#1f3f36}.grade{font-weight:700;color:#145b44}.records .title{font-size:8.2pt}
.summary{margin-top:3mm;display:grid;grid-template-columns:1fr 1.25fr 1.25fr;border:.25mm solid #d4dfd9;border-radius:2mm;overflow:hidden}.summary>div{padding:2.5mm 4mm;border-right:.25mm solid #d4dfd9}.summary>div:last-child{border-right:0}.summary .number{font-size:17pt;line-height:1.1;font-weight:700;color:#1f4d3f}.summary .number small{font-size:9pt;font-weight:400;color:#6a7d73}.summary .remark{font-size:15pt;line-height:1.3;font-weight:700;color:#1f4d3f}.summary .muted{font-size:7pt;color:#74847c;margin-top:1mm}.summary .highlight{background:#f1f6f2}
.grade-key{display:flex;gap:4mm;align-items:flex-start;margin-top:2mm;font-size:6.7pt;line-height:1.7;color:#61726a}.grade-key strong{font-size:6.7pt;flex-shrink:0;color:#3f584c}.grade-key span{word-spacing:1mm}
.authorisation{margin-top:3mm;display:flex;align-items:flex-end;justify-content:space-between;gap:12mm}.signature{width:56mm}.signature-line{height:7mm;border-bottom:.25mm solid #9eaea5}.signature p{margin:2mm 0 0;font-size:7pt;color:#61726a}.stamp-space{width:44mm;height:17mm;border:.25mm dashed #b9c8bf;display:flex;align-items:center;justify-content:center;text-align:center;font-size:6pt;line-height:1.6;color:#84938b;letter-spacing:.5px;background:#fbfcfb}
footer{margin-top:2mm;padding-top:2mm;border-top:.25mm solid #d7e1db;display:flex;align-items:flex-start;justify-content:space-between;gap:5mm;font-size:6pt;line-height:1.7;color:#77867e}.sample-note{font-weight:700;letter-spacing:.4px;color:#5e7266}.folio{text-align:right;white-space:nowrap}

header,.title-row,.identity,.exam-band,.summary,.grade-key,.authorisation,footer { break-inside:avoid; }
.records thead { display:table-header-group; } .records tr { break-inside:avoid; }
.records td { overflow-wrap:anywhere; } .records .code { word-break:break-word; }
.summary-group { break-inside:avoid; }
.name,.value { overflow-wrap:anywhere; }
.passport img { width:100%;height:100%;object-fit:cover; }
.stamp-space { position:relative; height:18mm; border:0; background:transparent; }
.stamp-space img { position:absolute; top:50%; left:50%; width:34mm; height:23mm; max-width:none; max-height:none; object-fit:cover; object-position:center 45%; transform:translate(-50%,-50%); }
.grade-f { color:#9a3b34; }
.compact header { padding-bottom:2mm; }
.compact .logo { width:12mm; height:12mm; }
.compact .school h1 { font-size:14pt; }
.compact .title-row { margin:2mm 0; }
.compact .title-row h2 { font-size:15pt; }
.compact .identity { padding:2.5mm; }
.compact .name { font-size:12pt; margin-bottom:2mm; }
.compact .details { gap:1.5mm 4mm; }
.compact .passport { width:20mm; height:24mm; }
.compact .exam-band { margin:2mm 0; }
.compact .records td { padding-top:.6mm; padding-bottom:.6mm; line-height:1.15; }
.compact .records table,.compact .records .title { font-size:7.6pt; }
.compact .records .code { font-size:7.5pt; }
.compact .records th { padding-top:1.5mm; padding-bottom:1.5mm; }
.compact .summary { margin-top:2mm; }
.compact .summary>div { padding:2mm 4mm; }
.compact .summary .number { font-size:15pt; }
.compact .summary .remark { font-size:14pt; }
.compact .authorisation { margin-top:3mm; }
.compact .stamp-space { height:18mm; }
</style></head><body><main class="page {{ $singleExams->count() > 12 ? 'compact' : '' }}">
<header>@if($logo)<img class="logo" src="{{ $logo }}" alt="College logo">@endif<div class="school"><h1>OZOVEHENI COLLEGE</h1><h2>OF HEALTH SCIENCE AND TECHNOLOGY</h2><p>Uhuodo · Okehi LGA, Kogi State</p></div><div class="office">OFFICE OF THE REGISTRAR<br>Academic Division</div></header>
<section class="title-row"><div><p class="eyebrow">Academic record</p><h2>Student result slip</h2></div></section>
<section class="identity"><div class="identity-body"><span class="label">Student name</span><div class="name">{{ collect([$student->last_name, $student->middle_name, $student->first_name])->filter()->implode(' ') }}</div><div class="details"><div><span class="label">Registration number</span><div class="value registration">{{ $student->reg_no }}</div></div><div><span class="label">Department</span><div class="value">{{ $exam->department?->name ?? '—' }}</div></div><div><span class="label">Student set</span><div class="value">{{ $student->set }}</div></div><div><span class="label">Level</span><div class="value">{{ $level }}</div></div></div></div><div><div class="passport">@if($passport)<img src="{{ $passport }}" alt="Student passport">@else<div class="head"></div><div class="shoulders"></div><small>NO PASSPORT</small>@endif</div></div></section>
<section class="exam-band"><div><span class="label">Examination</span><div class="value">{{ $exam->title }}</div></div>@if($exam->subtitle)<div class="session"><span class="label">Academic session</span><div class="value">{{ $exam->subtitle }}</div></div>@endif</section>
<section class="records"><table><colgroup><col style="width:13%"><col style="width:39%"><col style="width:9%"><col style="width:9%"><col style="width:10%"><col style="width:10%"><col style="width:10%"></colgroup><thead><tr><th>CODE</th><th>COURSE TITLE</th><th class="num">1ST CA</th><th class="num">2ND CA</th><th class="num">EXAM</th><th class="num">TOTAL</th><th class="num">GRADE</th></tr></thead><tbody>
@forelse($singleExams as $singleExam)
    @php $grade = StudentResultService::courseGrade($student, $singleExam->course, $exam); @endphp
    <tr><td class="code">{{ $singleExam->course->code }}</td><td class="title">{{ $singleExam->course->name }}</td><td class="num">{{ StudentResultService::courseScore($student, $singleExam->course, $exam, 'first_ca') }}</td><td class="num">{{ StudentResultService::courseScore($student, $singleExam->course, $exam, 'second_ca') }}</td><td class="num">{{ StudentResultService::courseScore($student, $singleExam->course, $exam, 'exam') }}</td><td class="num total">{{ StudentResultService::courseTotalScore($student, $singleExam->course, $exam) }}</td><td class="num grade {{ $grade === 'F' ? 'grade-f' : '' }}">{{ $grade }}</td></tr>
@empty
    <tr><td colspan="7">No course papers are available for this examination.</td></tr>
@endforelse
</tbody></table></section>
<div class="summary-group">
<section class="summary"><div><span class="label">Courses listed</span><div class="number">{{ $singleExams->count() }}</div></div><div class="highlight"><span class="label">CGPA</span><div class="number">{{ number_format(StudentResultService::calculateCGPA($student, $exam), 2) }} <small>/ 4.00</small></div></div><div><span class="label">Remark</span><div class="remark">{{ $remark ?: '—' }}</div></div></section>
<div class="grade-key"><strong>GRADE KEY</strong><span>A 70–100 &nbsp; B 65–69 &nbsp; C 60–64 &nbsp; D 55–59 &nbsp; E 50–54 &nbsp; F 0–49</span></div>
<section class="authorisation"><div class="signature"><div class="signature-line"></div><p>Authorised signature</p></div><div class="stamp-space">@if($stamp)<img src="{{ $stamp }}" alt="Official stamp">@else Validation / stamp @endif</div></section>
</div>
<footer><div>Ozoveheni College of Health Science and Technology<br>Computer-generated student result slip.</div><div class="folio">{{ $student->reg_no }}<br>Academic Division</div></footer>
</main></body></html>
