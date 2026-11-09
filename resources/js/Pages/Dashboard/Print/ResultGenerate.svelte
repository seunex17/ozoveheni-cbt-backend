<script lang="ts">
    import { Link } from "@inertiajs/svelte";
    import { ExternalLink, FileText } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    let { exam, pdfUrl, student = null } = $props();
    const studentName = $derived(student ? [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ") : "");
</script>
<svelte:head><title>Result slip · {exam.title}</title></svelte:head>
<DashboardLayout title="Student result slip" description={exam.title}>
    {#snippet actions()}<Link href="/dashboard/print/result" class="btn btn-ghost btn-sm">Look up another result</Link><a href={pdfUrl} target="_blank" rel="noopener noreferrer" class="btn btn-primary gap-2"><ExternalLink size={16} /> Open PDF to print / save</a>{/snippet}
    <div class="flex flex-col gap-6">
        <section class="card card-border bg-base-100"><div class="card-body flex-row flex-wrap items-start justify-between gap-5 p-5 sm:p-6"><div><h2 class="break-words text-base font-semibold">{studentName || "Result ready"}</h2>{#if student}<p class="mt-1 text-sm text-base-content/65">{student.reg_no}</p>{/if}<p class="mt-2 text-xs text-base-content/60">Set {exam.set} · {exam.level?.toUpperCase() ?? ""}</p></div><p class="max-w-md text-sm leading-6 text-base-content/65">Use the PDF viewer to print or save the slip. Reopen this PDF from this page without entering the voucher again.</p></div></section>
        <section class="card card-border overflow-hidden bg-base-100"><div class="flex items-center gap-2 border-b border-base-300 px-5 py-5"><FileText size={18} class="text-base-content/60" /><h2 class="text-sm font-semibold">Result slip preview</h2></div><div class="p-3 sm:p-5"><object data={pdfUrl} type="application/pdf" class="h-[70dvh] min-h-100 w-full" aria-label={`Result slip PDF for ${studentName || exam.title}`}><div class="px-6 py-12 text-center"><p class="text-sm leading-6 text-base-content/65">Your browser may open PDFs in a separate viewer.</p><a href={pdfUrl} target="_blank" rel="noopener noreferrer" class="btn mt-5">Open result PDF</a></div></object></div></section>
    </div>
</DashboardLayout>
