<script lang="ts">
    import { InfiniteScroll, Link, router } from "@inertiajs/svelte";
    import { Plus, Trash2, Upload, Eye, Download, X } from "lucide-svelte";
    import DashboardLayout from "../../../../Components/Layouts/DashboardLayout.svelte";
    let { singleExam, questions } = $props();
    let deleteModal: HTMLDialogElement;
    let importModal: HTMLDialogElement;
    let selectedQuestion = $state<any>(null);
    let deleting = $state(false);
    let importing = $state(false);
    let selectedFile = $state<File | null>(null);
    let importError = $state("");
    let fileInput: HTMLInputElement;
    function confirmDelete(question: any) { selectedQuestion = question; deleteModal.showModal(); }
    function deleteQuestion() {
        if (!selectedQuestion || deleting) return;
        deleting = true;
        router.post("/dashboard/exam/delete-question", { id: selectedQuestion.id }, { preserveScroll: true, onSuccess: () => deleteModal.close(), onFinish: () => deleting = false });
    }
    function openImport() { selectedFile = null; importError = ""; if (fileInput) fileInput.value = ""; importModal.showModal(); }
    function importQuestions(event: SubmitEvent) {
        event.preventDefault();
        if (!selectedFile || importing) return;
        importing = true; importError = "";
        router.post("/dashboard/exam/import-questions", { exam_id: singleExam.id, file: selectedFile }, {
            forceFormData: true, preserveScroll: true,
            onSuccess: (page) => { const message = (page.props.flash as any)?.error; if (message) importError = message; else importModal.close(); },
            onError: (errors) => importError = Object.values(errors).join(" "),
            onFinish: () => importing = false,
        });
    }
</script>
<svelte:head><title>Questions · {singleExam.course.name}</title></svelte:head>
<DashboardLayout title={singleExam.course.name} description={`${singleExam.course.code} · ${singleExam.exam.title} · ${singleExam.duration} minutes per student`}>
    {#snippet actions()}<Link href={`/dashboard/exam/${singleExam.exam.uuid}/view`} class="btn btn-ghost btn-sm">Back to course papers</Link><button class="btn gap-2" onclick={openImport}><Upload size={16} /> Import questions</button><Link href={`/dashboard/exam/${singleExam.uuid}/add-question`} class="btn btn-primary gap-2"><Plus size={16} /> Add question</Link>{/snippet}
    <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="questions-heading"><div class="border-b border-base-300 px-5 py-5 sm:px-6"><h2 id="questions-heading" class="text-base font-semibold">Question bank</h2><p class="mt-1 text-xs text-base-content/60">{questions.total} {questions.total === 1 ? "question" : "questions"} · Open a question to review its options and correct answer.</p></div>
        {#if questions.data.length}
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table accepts focus for keyboard navigation.) -->
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Question bank, scroll horizontally if needed"><InfiniteScroll data="questions"><table class="table min-w-160"><caption class="sr-only">Questions in this course paper</caption><thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6">Question</th><th scope="col">Marks</th><th scope="col">Options</th><th scope="col" class="pr-6 text-right">Actions</th></tr></thead><tbody>
                {#each questions.data as question (question.id)}<tr class="hover:bg-base-200/40"><th scope="row" class="max-w-130 whitespace-pre-wrap break-words px-6 py-5 text-sm font-normal leading-6">{question.question_text}</th><td class="text-xs tabular-nums">{question.marks}</td><td class="text-xs tabular-nums">{question.options_count}</td><td class="pr-6"><div class="flex justify-end gap-1"><Link href={`/dashboard/exam/${question.id}/view-question`} class="btn btn-ghost btn-sm gap-2" aria-label={`Review question ${question.id}`}><Eye size={15} /> Review</Link><button class="btn btn-square btn-ghost btn-sm" aria-label={`Delete question ${question.id}`} onclick={() => confirmDelete(question)}><Trash2 size={15} /></button></div></td></tr>{/each}
            </tbody></table></InfiniteScroll></div>
        {:else}<div class="px-6 py-14 text-center"><h3 class="text-sm font-semibold">No questions yet</h3><p class="mt-2 text-sm text-base-content/60">Add questions individually or import them from the Excel template.</p><Link href={`/dashboard/exam/${singleExam.uuid}/add-question`} class="btn btn-sm mt-5">Add question</Link></div>{/if}
    </section>
</DashboardLayout>
<dialog bind:this={deleteModal} class="modal" aria-labelledby="delete-question-heading"><div class="modal-box max-w-lg"><h2 id="delete-question-heading" class="text-lg font-semibold">Delete question?</h2><p class="mt-3 max-h-40 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-base-200 p-4 text-sm leading-6">{selectedQuestion?.question_text}</p><p class="mt-3 text-sm text-base-content/65">This removes the question and its answer options. This cannot be undone.</p><div class="modal-action"><form method="dialog"><button class="btn" disabled={deleting}>Cancel</button></form><button class="btn btn-error" onclick={deleteQuestion} disabled={deleting}>{deleting ? "Deleting…" : "Delete question"}</button></div></div></dialog>
<dialog bind:this={importModal} class="modal" aria-labelledby="import-heading" oncancel={(event) => { if (importing) event.preventDefault(); }}><div class="modal-box max-w-lg"><div class="flex items-center justify-between gap-4"><h2 id="import-heading" class="text-lg font-semibold">Import questions</h2><button class="btn btn-square btn-ghost btn-sm" aria-label="Close import" disabled={importing} onclick={() => importModal.close()}><X size={18} /></button></div><p class="mt-2 text-sm leading-6 text-base-content/65">Use the Excel template to prepare questions for {singleExam.course.code}. Choose a file, then select Import.</p><a href="/dashboard/exam/download-template" class="btn btn-sm mt-4 gap-2"><Download size={15} /> Download template</a><form onsubmit={importQuestions} class="mt-6 flex flex-col gap-5" aria-busy={importing}><div><label for="question-file" class="mb-2 block text-sm font-medium">Excel file (.xlsx)</label><input bind:this={fileInput} id="question-file" class="file-input w-full" type="file" accept=".xlsx" disabled={importing} onchange={(event) => selectedFile = event.currentTarget.files?.[0] ?? null} /></div>{#if importError}<p class="text-sm text-error" role="alert">{importError}</p>{/if}<div class="modal-action mt-0"><button type="button" class="btn" disabled={importing} onclick={() => importModal.close()}>Cancel</button><button type="submit" class="btn gap-2" disabled={!selectedFile || importing}><Upload size={16} />{importing ? "Importing…" : "Import questions"}</button></div></form></div></dialog>
