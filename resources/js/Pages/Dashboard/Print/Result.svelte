<script lang="ts">
    import { Link, useForm } from "@inertiajs/svelte";
    import { Printer, Search, FileText, LoaderCircle } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    let { exams } = $props();
    let search = $state("");
    const form = useForm({ exam_id: "", reg_no: "", pin: "" });
    const selectedExam = $derived(exams.find((exam) => String(exam.id) === $form.exam_id));
    const matches = $derived(exams.filter((exam) => [exam.title, exam.subtitle, exam.department?.name, exam.set, exam.level].filter(Boolean).join(" ").toLowerCase().includes(search.trim().toLowerCase())));
    const options = $derived(selectedExam && !matches.some((exam) => exam.id === selectedExam.id) ? [selectedExam, ...matches] : matches);
    function submit(event: SubmitEvent) {
        event.preventDefault();
        if ($form.processing) return;
        $form.post("/dashboard/print/result", {
            onSuccess: () => $form.reset("pin"),
            onError: () => document.querySelector<HTMLInputElement>('#result-form [aria-invalid="true"]')?.focus(),
        });
    }
</script>
<svelte:head><title>Print student result · Ozoveheni</title></svelte:head>
<DashboardLayout title="Print student result" description="Choose an examination and open a student's result slip.">
    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <section class="card card-border bg-base-100" aria-labelledby="result-heading"><div class="card-body gap-6 p-5 sm:p-7"><div class="flex items-center gap-3"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><Printer size={22} strokeWidth={1.6} /></div><div><h2 id="result-heading" class="text-base font-semibold">Student result lookup</h2><p class="mt-1 text-xs text-base-content/60">Examination, registration number and voucher PIN.</p></div></div>
            {#if exams.length}
                <form id="result-form" onsubmit={submit} class="flex flex-col gap-5" aria-busy={$form.processing}>
                    <div><label for="result-search" class="mb-2 block text-sm font-medium">Find an examination</label><label class="input flex w-full items-center gap-2"><Search size={17} class="shrink-0 text-base-content/50" /><input id="result-search" type="search" bind:value={search} placeholder="Search title, department, set or level…" disabled={$form.processing} /></label><p class="mt-2 text-xs text-base-content/60" role="status">{matches.length} matching {matches.length === 1 ? "examination" : "examinations"}</p></div>
                    <div><label for="result-exam" class="mb-2 block text-sm font-medium">Examination</label><select id="result-exam" class="select w-full" bind:value={$form.exam_id} required disabled={$form.processing} aria-invalid={Boolean($form.errors.exam_id)} aria-describedby={$form.errors.exam_id ? "result-exam-error" : undefined}><option value="">Choose examination</option>{#each options as exam}<option value={String(exam.id)}>{exam.title} · {exam.department?.name ?? ""} · {exam.set}{exam.level ? ` · ${exam.level.toUpperCase()}` : ""}</option>{/each}</select>{#if $form.errors.exam_id}<p id="result-exam-error" class="mt-2 text-sm text-error" role="alert">{$form.errors.exam_id}</p>{/if}{#if !matches.length}<p class="mt-2 text-xs text-base-content/65">No matches. Try another title, department or set.</p>{/if}</div>
                    <div><label for="result-registration" class="mb-2 block text-sm font-medium">Student registration number</label><input id="result-registration" class="input w-full" bind:value={$form.reg_no} required maxlength={255} placeholder="Enter the student's registration number" autocomplete="off" spellcheck={false} disabled={$form.processing} aria-invalid={Boolean($form.errors.reg_no)} aria-describedby={$form.errors.reg_no ? "result-registration-error" : undefined} />{#if $form.errors.reg_no}<p id="result-registration-error" class="mt-2 text-sm text-error" role="alert">{$form.errors.reg_no}</p>{/if}</div>
                    <div><label for="result-pin" class="mb-2 block text-sm font-medium">Voucher PIN</label><input id="result-pin" class="input w-full font-mono tracking-wider" bind:value={$form.pin} type="text" inputmode="numeric" pattern={"[0-9]{12}"} required minlength={12} maxlength={12} placeholder="12-digit PIN" autocomplete="off" spellcheck={false} disabled={$form.processing} aria-invalid={Boolean($form.errors.pin)} aria-describedby={$form.errors.pin ? "pin-help pin-error" : "pin-help"} /><p id="pin-help" class="mt-2 text-xs leading-5 text-base-content/65">Use an unused voucher issued for the selected examination.</p>{#if $form.errors.pin}<p id="pin-error" class="mt-2 text-sm text-error" role="alert">{$form.errors.pin}</p>{/if}</div>
                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-base-300 pt-5"><p class="max-w-xs text-xs leading-5 text-base-content/65">Opening the result uses this voucher. Check the examination and registration number first.</p><button type="submit" class="btn btn-primary gap-2" disabled={$form.processing || !selectedExam || !$form.reg_no.trim() || !$form.pin}>{#if $form.processing}<LoaderCircle size={16} class="animate-spin" /> Opening…{:else}<FileText size={16} /> Open result{/if}</button></div>
                </form>
            {:else}<div class="py-8 text-center"><h3 class="text-sm font-semibold">No examinations available</h3><p class="mt-2 text-sm text-base-content/65">Results can be opened after an examination has been set up.</p></div>{/if}
        </div></section>
        <aside class="card card-border bg-base-100"><div class="card-body gap-4 p-5 sm:p-6"><h2 class="text-sm font-semibold">Before opening the result</h2>{#if selectedExam}<div class="rounded-lg bg-base-200 p-4"><p class="break-words text-sm font-semibold">{selectedExam.title}</p><p class="mt-2 text-sm leading-6 text-base-content/65">{selectedExam.department?.name}<br />Set {selectedExam.set}{selectedExam.level ? ` · ${selectedExam.level.toUpperCase()}` : ""}</p>{#if selectedExam.subtitle}<p class="mt-1 text-xs text-base-content/60">Session: {selectedExam.subtitle}</p>{/if}</div>{/if}<ol class="list-decimal space-y-3 pl-5 text-sm leading-6 text-base-content/65"><li>Select the correct examination and student set.</li><li>Enter the registration number exactly as registered.</li><li>Enter the 12-digit voucher PIN, then open the result.</li></ol><p class="border-t border-base-300 pt-4 text-xs leading-5 text-base-content/65">The next page provides a PDF preview and a link to print or save the slip.</p></div></aside>
    </div>
</DashboardLayout>
