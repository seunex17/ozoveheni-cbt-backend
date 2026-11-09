<script lang="ts">
    import { Link } from "@inertiajs/svelte";
    import { onDestroy } from "svelte";
    import axios from "axios";
    import { Ticket, Search, Printer, Download, ExternalLink, CheckCircle2, LoaderCircle } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    type ExamOption = { id: number; title: string; subtitle?: string; set: string; level?: string; department?: { name: string } };
    let { exams }: { exams: ExamOption[] } = $props();
    let search = $state("");
    let examId = $state("");
    let quantity = $state<number | undefined>(undefined);
    let generating = $state(false);
    let errors = $state<Record<string, string>>({});
    let failure = $state("");
    let pdfUrl = $state("");
    let batch = $state<{ title: string; quantity: number } | null>(null);
    const selectedExam = $derived(exams.find((exam) => String(exam.id) === examId));
    const filteredExams = $derived(exams.filter((exam) => [exam.title, exam.subtitle, exam.set, exam.level, exam.department?.name].filter(Boolean).join(" ").toLowerCase().includes(search.trim().toLowerCase())));
    // Keep the selected exam available when refining the search.
    const examOptions = $derived(selectedExam && !filteredExams.some((exam) => exam.id === selectedExam.id) ? [selectedExam, ...filteredExams] : filteredExams);
    const number = new Intl.NumberFormat("en-GB");
    function clearPreview() {
        if (pdfUrl) URL.revokeObjectURL(pdfUrl);
        pdfUrl = ""; batch = null;
    }
    async function generateVouchers(event: SubmitEvent) {
        event.preventDefault();
        if (generating || !selectedExam || !quantity) return;
        generating = true; errors = {}; failure = "";
        const requestedBatch = { title: `${selectedExam.title} · ${selectedExam.set}`, quantity };
        try {
            const response = await axios.get("/dashboard/voucher/generate", {
                params: { exam_id: selectedExam.id, qty: quantity },
                responseType: "blob", headers: { Accept: "application/json" },
            });
            if (!response.headers["content-type"]?.includes("application/pdf")) throw new Error("Unexpected response");
            clearPreview();
            pdfUrl = URL.createObjectURL(response.data);
            batch = requestedBatch;
        } catch (error: any) {
            if (error.response?.status === 422) {
                try {
                    const payload = JSON.parse(await error.response.data.text());
                    errors = Object.fromEntries(Object.entries(payload.errors ?? {}).map(([key, messages]) => [key, Array.isArray(messages) ? String(messages[0]) : String(messages)]));
                } catch { failure = "Please check the selected exam and quantity, then try again."; }
            } else {
                failure = "The voucher PDF could not be prepared. Check with your administrator before retrying; a batch may already have been created.";
            }
        } finally { generating = false; }
    }
    onDestroy(() => { if (pdfUrl) URL.revokeObjectURL(pdfUrl); });
</script>

<svelte:head><title>Exam vouchers · Ozoveheni</title></svelte:head>
<DashboardLayout title="Exam vouchers" description="Generate and print examination voucher batches.">
    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <section class="card card-border bg-base-100" aria-labelledby="voucher-heading">
            <div class="card-body gap-6 p-5 sm:p-7">
                <div class="flex items-center gap-3"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><Ticket size={22} strokeWidth={1.6} /></div><div><h2 id="voucher-heading" class="text-base font-semibold">Generate vouchers</h2><p class="mt-1 text-xs text-base-content/60">Choose the examination and batch size.</p></div></div>
                {#if exams.length}
                    <form onsubmit={generateVouchers} class="flex flex-col gap-5" aria-busy={generating}>
                        <div><label for="voucher-search" class="mb-2 block text-sm font-medium">Find an examination</label><label class="input flex w-full items-center gap-2"><Search size={17} class="shrink-0 text-base-content/50" /><input id="voucher-search" type="search" bind:value={search} placeholder="Search title, department, set or level…" disabled={generating} /></label><p class="mt-2 text-xs text-base-content/60" role="status" aria-live="polite">{filteredExams.length} matching {filteredExams.length === 1 ? "examination" : "examinations"}</p></div>
                        <div><label for="voucher-exam" class="mb-2 block text-sm font-medium">Examination</label><select id="voucher-exam" class="select w-full" bind:value={examId} required disabled={generating} aria-invalid={Boolean(errors.exam_id)} aria-describedby={errors.exam_id ? "exam-error" : undefined}><option value="">Choose examination</option>{#each examOptions as exam}<option value={String(exam.id)}>{exam.title} · {exam.department?.name ?? ""} · {exam.set}{exam.level ? ` · ${exam.level.toUpperCase()}` : ""}{exam.subtitle ? ` · ${exam.subtitle}` : ""}</option>{/each}</select>{#if errors.exam_id}<p id="exam-error" class="mt-2 text-sm text-error" role="alert">{errors.exam_id}</p>{/if}{#if !filteredExams.length}<p class="mt-2 text-xs text-base-content/65">No matches. Try a different title, department or set.</p>{/if}</div>
                        <div><label for="voucher-quantity" class="mb-2 block text-sm font-medium">Number of vouchers</label><input id="voucher-quantity" type="number" class="input w-full" bind:value={quantity} required min="1" max="500" step="1" placeholder="e.g. 50" disabled={generating} aria-invalid={Boolean(errors.qty)} aria-describedby={errors.qty ? "quantity-help quantity-error" : "quantity-help"} /><p id="quantity-help" class="mt-2 text-xs leading-5 text-base-content/65">Generate 1–500 vouchers per batch to keep PDF generation manageable.</p>{#if errors.qty}<p id="quantity-error" class="mt-2 text-sm text-error" role="alert">{errors.qty}</p>{/if}</div>
                        {#if failure}<p class="rounded-lg bg-error/10 p-4 text-sm leading-6 text-error" role="alert">{failure}</p>{/if}
                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-base-300 pt-5"><p class="text-xs text-base-content/65" role="status">{generating ? "Preparing the PDF. Keep this page open…" : "Each generation creates a new batch."}</p><button type="submit" class="btn btn-primary gap-2" disabled={generating || !selectedExam || !quantity}>{#if generating}<LoaderCircle size={16} class="animate-spin" /> Preparing…{:else}<Printer size={16} /> Generate PDF{/if}</button></div>
                    </form>
                {:else}<div class="py-8 text-center"><h3 class="text-sm font-semibold">No examinations available</h3><p class="mt-2 text-sm leading-6 text-base-content/65">Create an examination before generating its vouchers.</p><Link href="/dashboard/exam" class="btn btn-sm mt-5">Manage examinations</Link></div>{/if}
            </div>
        </section>
        <aside class="flex flex-col gap-6">
            <section class="card card-border bg-base-100" aria-labelledby="batch-heading"><div class="card-body gap-4 p-5 sm:p-6"><h2 id="batch-heading" class="text-sm font-semibold">Batch summary</h2>{#if selectedExam}<div><p class="break-words text-base font-semibold">{selectedExam.title}</p><p class="mt-2 text-sm leading-6 text-base-content/65">{selectedExam.department?.name ?? ""}<br />Set {selectedExam.set}{selectedExam.level ? ` · ${selectedExam.level.toUpperCase()}` : ""}</p>{#if selectedExam.subtitle}<p class="mt-1 text-xs text-base-content/60">Session: {selectedExam.subtitle}</p>{/if}</div><div class="flex items-center justify-between border-t border-base-300 pt-4 text-sm"><span class="text-base-content/65">Vouchers to generate</span><strong class="text-xl font-semibold tabular-nums">{quantity ? number.format(quantity) : "—"}</strong></div>{:else}<p class="text-sm leading-6 text-base-content/65">Select an examination to review the department, set and batch quantity before generating.</p>{/if}</div></section>
            {#if pdfUrl && batch}<section class="card card-border bg-base-100" aria-labelledby="ready-heading"><div class="card-body gap-4 p-5 sm:p-6"><div class="flex items-center gap-2"><CheckCircle2 size={18} class="text-success" /><h2 id="ready-heading" class="text-sm font-semibold" role="status">Voucher PDF ready</h2></div><p class="break-words text-sm leading-6 text-base-content/65">{number.format(batch.quantity)} vouchers · {batch.title}</p><div class="flex flex-wrap gap-3"><a href={pdfUrl} target="_blank" rel="noopener noreferrer" class="btn btn-sm gap-2"><ExternalLink size={15} /> Open PDF</a><a href={pdfUrl} download="exam-vouchers.pdf" class="btn btn-sm gap-2"><Download size={15} /> Download</a></div><p class="text-xs leading-5 text-base-content/60">Use these buttons to print or download this batch again without generating more vouchers.</p></div></section>{/if}
        </aside>
    </div>
</DashboardLayout>
