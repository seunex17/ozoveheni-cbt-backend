<script lang="ts">
    import { Link, router, useForm } from "@inertiajs/svelte";
    import { Plus, Pen, Eye, Trash2, Search, X, ClipboardList, ArrowLeft, ArrowRight, SlidersHorizontal } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { exams, filters, totalExams, departments, sets = [], levels = [] } = $props();
    let search = $state("");
    let sort = $state("newest");
    let department = $state("");
    let selectedSet = $state("");
    let level = $state("");
    let status = $state("");
    let searching = $state(false);
    let deleting = $state(false);
    let selectedExam = $state<any>(null);
    let deleteModal: HTMLDialogElement;
    let addModal: HTMLDialogElement;
    let editingExam = $state<any>(null);
    const addForm = useForm({ id: null as number | null, title: "", subtitle: "", department_id: "", set: "", level: "", start_date: "", end_date: "" });
    const formLevels = $derived([...new Set(["nd1", "nd2", "hnd1", "hnd2", ...levels])]);
    function openAddExam() {
        editingExam = null;
        $addForm.reset();
        $addForm.clearErrors();
        addModal.showModal();
    }
    function openEditExam(exam: any) {
        editingExam = exam;
        $addForm.clearErrors();
        $addForm.id = exam.id;
        $addForm.title = exam.title;
        $addForm.subtitle = exam.subtitle ?? "";
        $addForm.department_id = String(exam.department_id);
        $addForm.set = exam.set;
        $addForm.level = exam.level?.toLowerCase() ?? "";
        $addForm.start_date = exam.start_date?.slice(0, 10) ?? "";
        $addForm.end_date = exam.end_date?.slice(0, 10) ?? "";
        addModal.showModal();
    }
    function createExam(event: SubmitEvent) {
        event.preventDefault();
        if ($addForm.processing) return;
        $addForm.post(editingExam ? "/dashboard/exam/edit" : "/dashboard/exam/add", {
            preserveScroll: false,
            onSuccess: () => {
                addModal.close();
                $addForm.reset();
            },
            onError: () => addModal.querySelector<HTMLInputElement>('[aria-invalid="true"]')?.focus(),
        });
    }
    const number = new Intl.NumberFormat("en-GB");
    const date = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric" });
    const hasFilters = $derived(Boolean(filters.search || filters.department || filters.set || filters.level || filters.status || filters.sort !== "newest"));

    $effect(() => {
        search = filters.search ?? "";
        sort = filters.sort ?? "newest";
        department = filters.department ?? ""; selectedSet = filters.set ?? ""; level = filters.level ?? ""; status = filters.status ?? "";
    });
    function applyFilters(event?: SubmitEvent) {
        event?.preventDefault();
        searching = true;
        const query: Record<string, string> = {};
        if (search.trim()) query.search = search.trim();
        if (department) query.department = department;
        if (selectedSet) query.set = selectedSet;
        if (level) query.level = level;
        if (status) query.status = status;
        if (sort !== "newest") query.sort = sort;
        router.get("/dashboard/exam", query, {
            only: ["exams", "filters", "totalExams"],
            preserveState: true, preserveScroll: true,
            onFinish: () => searching = false,
        });
    }
    function clearFilters() {
        search = ""; sort = "newest"; department = ""; selectedSet = ""; level = ""; status = "";
        applyFilters();
    }
    function confirmDelete(exam: any) {
        selectedExam = exam;
        deleteModal.showModal();
    }
    function deleteExam() {
        if (!selectedExam || deleting) return;
        deleting = true;
        router.post("/dashboard/exam/delete-exam", { id: selectedExam.id }, {
            preserveScroll: true,
            onSuccess: () => deleteModal.close(),
            onFinish: () => deleting = false,
        });
    }
</script>

<svelte:head><title>Exams · Ozoveheni</title></svelte:head>
<DashboardLayout title="Exams" description="Manage exam groups, academic periods and course papers.">
    {#snippet actions()}
        <button onclick={openAddExam} class="btn btn-primary gap-2 rounded-lg font-medium"><Plus size={17} /> Add exam</button>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="card card-border bg-base-100">
            <div class="card-body flex-row flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                <div class="flex items-center gap-4"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><ClipboardList size={21} strokeWidth={1.6} /></div><div><p class="text-xs text-base-content/60">Total exams</p><p class="mt-1 text-2xl font-semibold tabular-nums">{number.format(totalExams)}</p></div></div>
                <p class="max-w-md text-sm leading-6 text-base-content/60">Open an exam to manage its course papers, admission windows, questions and student duration.</p>
            </div>
        </div>
        <section class="card card-border bg-base-100" aria-labelledby="filter-heading">
            <form onsubmit={applyFilters} class="card-body gap-5 p-5 sm:p-6" aria-busy={searching}>
                <div class="flex items-center gap-2"><SlidersHorizontal size={16} class="text-base-content/60" /><h2 id="filter-heading" class="text-sm font-semibold">Find an exam</h2></div>
                <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                    <div class="sm:col-span-2 xl:col-span-1"><label for="exam-search" class="mb-2 block text-xs font-medium text-base-content/70">Title or session</label><label class="input flex w-full items-center gap-2 rounded-lg border-base-300"><Search size={17} class="shrink-0 text-base-content/50" /><input id="exam-search" type="search" bind:value={search} maxlength={100} placeholder="Search by exam title or session…" /></label></div>
                    <div><label for="exam-sort" class="mb-2 block text-xs font-medium text-base-content/70">Sort by</label><select id="exam-sort" class="select w-full rounded-lg border-base-300" bind:value={sort}><option value="newest">Recently added</option><option value="title">Title A–Z</option><option value="start_date">Latest start date</option></select></div>
                    <button type="submit" class="btn gap-2 rounded-lg sm:col-span-2 xl:col-span-1" disabled={searching}><Search size={16} />{searching ? "Searching…" : "Search"}</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div><label for="exam-department" class="mb-2 block text-xs font-medium text-base-content/70">Department</label><select id="exam-department" class="select w-full" bind:value={department}><option value="">All departments</option>{#each departments as item}<option value={item.uuid}>{item.name}</option>{/each}</select></div>
                    <div><label for="exam-set" class="mb-2 block text-xs font-medium text-base-content/70">Set</label><select id="exam-set" class="select w-full" bind:value={selectedSet}><option value="">All sets</option>{#each sets as item}<option value={item}>{item}</option>{/each}</select></div>
                    <div><label for="exam-level" class="mb-2 block text-xs font-medium text-base-content/70">Level</label><select id="exam-level" class="select w-full" bind:value={level}><option value="">All levels</option>{#each levels as item}<option value={item}>{item.toUpperCase()}</option>{/each}</select></div>
                    <div><label for="exam-status" class="mb-2 block text-xs font-medium text-base-content/70">Status</label><select id="exam-status" class="select w-full" bind:value={status}><option value="">All statuses</option><option value="pending">Pending</option><option value="active">Active</option><option value="completed">Completed</option></select></div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4 text-xs text-base-content/60"><span>Choose filters, then press Search or Enter to apply.</span>{#if hasFilters}<button type="button" class="btn btn-ghost btn-xs gap-1.5" onclick={clearFilters} disabled={searching}><X size={13} /> Clear filters</button>{/if}</div>
            </form>
        </section>
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="results-heading" aria-busy={searching}>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-5 sm:px-6"><div><h2 id="results-heading" class="text-base font-semibold">Exam directory</h2><p class="mt-1 text-xs text-base-content/60" role="status" aria-live="polite">{number.format(exams.total)} {exams.total === 1 ? "exam" : "exams"}{filters.search ? ` matching “${filters.search}”` : ""}</p></div><span class="text-xs text-base-content/60">20 records per page</span></div>
            {#if exams.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table must accept focus for horizontal keyboard navigation.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Exam records table, scroll horizontally if needed">
                    <table class="table min-w-240">
                        <caption class="sr-only">Exams matching the selected search and filters</caption>
                        <thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6 font-medium">Examination</th><th scope="col" class="font-medium">Department</th><th scope="col" class="font-medium">Set / level</th><th scope="col" class="font-medium">Exam period</th><th scope="col" class="font-medium">Status</th><th scope="col" class="pr-6 text-right font-medium">Actions</th></tr></thead>
                        <tbody>
                            {#each exams.data as exam (exam.id)}
                                <tr class="border-base-300/70 hover:bg-base-200/40">
                                    <th scope="row" class="px-6 py-5 font-normal"><div class="flex items-center gap-3"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/55"><ClipboardList size={18} strokeWidth={1.6} /></div><div class="max-w-60 whitespace-normal"><Link href={`/dashboard/exam/${exam.uuid}/view`} class="text-sm font-medium leading-5 hover:underline">{exam.title}</Link>{#if exam.subtitle}<p class="mt-1 text-xs text-base-content/60">{exam.subtitle}</p>{/if}</div></div></th>
                                    <td class="max-w-50 whitespace-normal text-xs leading-5 text-base-content/70">{exam.department?.name ?? "—"}</td>
                                    <td class="text-xs"><div>{exam.set}</div><div class="mt-1 uppercase text-base-content/60">{exam.level}</div></td>
                                    <td class="text-xs text-base-content/65"><div>{exam.start_date ? date.format(new Date(exam.start_date.slice(0, 10) + "T00:00:00")) : "—"}</div><div class="mt-1">to {exam.end_date ? date.format(new Date(exam.end_date.slice(0, 10) + "T00:00:00")) : "—"}</div></td>
                                    <td><span class="badge badge-soft badge-sm capitalize" class:badge-success={exam.status === "active"} class:badge-warning={exam.status === "pending"} class:badge-neutral={exam.status === "completed"}>{exam.status}</span></td>
                                    <td class="pr-6"><div class="flex justify-end gap-1"><Link href={`/dashboard/exam/${exam.uuid}/view`} class="btn btn-ghost btn-sm gap-1.5" aria-label={`Open papers for ${exam.title}`}><Eye size={15} /> Papers</Link><button onclick={() => openEditExam(exam)} class="btn btn-ghost btn-sm gap-1.5" aria-label={`Edit ${exam.title}`}><Pen size={14} /> Edit</button><button class="btn btn-square btn-ghost btn-sm text-base-content/55 hover:text-error" aria-label={`Delete ${exam.title}`} onclick={() => confirmDelete(exam)}><Trash2 size={15} /></button></div></td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 sm:px-6"><span class="text-xs text-base-content/60">Showing {exams.from}–{exams.to} of {number.format(exams.total)}</span><nav class="flex items-center gap-2" aria-label="Exam pages">{#if exams.prev_page_url}<Link href={exams.prev_page_url} class="btn btn-sm gap-1.5" preserveScroll><ArrowLeft size={14} /> Previous</Link>{:else}<button class="btn btn-sm" disabled>Previous</button>{/if}<span class="px-2 text-xs text-base-content/65" aria-current="page">Page {exams.current_page} of {exams.last_page}</span>{#if exams.next_page_url}<Link href={exams.next_page_url} class="btn btn-sm gap-1.5" preserveScroll>Next <ArrowRight size={14} /></Link>{:else}<button class="btn btn-sm" disabled>Next</button>{/if}</nav></div>
            {:else}
                <div class="flex flex-col items-center px-6 py-14 text-center"><ClipboardList size={30} strokeWidth={1.4} class="mb-4 text-base-content/45" /><h3 class="text-sm font-semibold">{totalExams ? "No exams match your search" : "Add your first exam"}</h3><p class="mt-2 max-w-90 text-sm leading-6 text-base-content/60">{totalExams ? "Try another title or session, or change the selected filters." : "Create an exam group, then add its course papers and questions."}</p>{#if totalExams}<button class="btn btn-sm mt-5" onclick={clearFilters}>Clear filters</button>{:else}<button onclick={openAddExam} class="btn btn-sm mt-5">Add exam</button>{/if}</div>
            {/if}
        </section>
    </div>
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal" aria-labelledby="delete-heading" aria-describedby="delete-description">
    <div class="modal-box max-w-md"><h2 id="delete-heading" class="text-lg font-semibold">Delete exam?</h2><p id="delete-description" class="mt-3 text-sm leading-6 text-base-content/65">Delete <strong class="font-semibold text-base-content">{selectedExam?.title ?? "this exam"}</strong>? This will also delete the associated exam papers, questions, attempts and result records. This cannot be undone.</p><div class="modal-action"><form method="dialog"><button class="btn" disabled={deleting}>Cancel</button></form><button class="btn btn-error" onclick={deleteExam} disabled={deleting}>{deleting ? "Deleting…" : "Delete exam"}</button></div></div>
</dialog>

<dialog bind:this={addModal} class="modal" aria-labelledby="add-heading" oncancel={(event) => { if ($addForm.processing) event.preventDefault(); }}>
    <div class="modal-box max-w-2xl"><div class="flex items-center justify-between gap-4"><h2 id="add-heading" class="text-lg font-semibold">{editingExam ? "Edit examination" : "Add examination"}</h2><button type="button" class="btn btn-square btn-ghost btn-sm" aria-label="Close exam form" disabled={$addForm.processing} onclick={() => addModal.close()}><X size={18} /></button></div>
        <p class="mt-2 text-sm leading-6 text-base-content/65">Set up the exam group and academic period. Configure each course paper's admission window and student duration inside Papers.</p>
        {#if !departments.length}<p class="mt-5 text-sm">Add a department before creating an exam. <Link href="/dashboard/department" class="underline">Manage departments</Link></p>{/if}
        <form onsubmit={createExam} class="mt-6 flex flex-col gap-5" aria-busy={$addForm.processing}>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label for="exam-title" class="mb-2 block text-sm font-medium">Exam title</label><input id="exam-title" class="input w-full" bind:value={$addForm.title} required maxlength={255} placeholder="e.g. First semester examination" disabled={$addForm.processing} aria-invalid={Boolean($addForm.errors.title)} />{#if $addForm.errors.title}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.title}</p>{/if}</div>
                <div><label for="exam-subtitle" class="mb-2 block text-sm font-medium">Session <span class="text-xs font-normal text-base-content/60">(optional)</span></label><input id="exam-subtitle" class="input w-full" bind:value={$addForm.subtitle} maxlength={255} placeholder="e.g. 2026/2027" disabled={$addForm.processing} aria-invalid={Boolean($addForm.errors.subtitle)} />{#if $addForm.errors.subtitle}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.subtitle}</p>{/if}</div>
                <div><label for="form-department" class="mb-2 block text-sm font-medium">Department</label><select id="form-department" class="select w-full" bind:value={$addForm.department_id} required disabled={$addForm.processing} aria-invalid={Boolean($addForm.errors.department_id)}><option value="">Choose department</option>{#each departments as item}<option value={String(item.id)}>{item.name}</option>{/each}</select>{#if $addForm.errors.department_id}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.department_id}</p>{/if}</div>
                <div><label for="form-set" class="mb-2 block text-sm font-medium">Student set</label><input id="form-set" class="input w-full" bind:value={$addForm.set} required maxlength={30} placeholder="e.g. 2025/2026" disabled={$addForm.processing} aria-invalid={Boolean($addForm.errors.set)} />{#if $addForm.errors.set}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.set}</p>{/if}</div>
                <div><label for="form-level" class="mb-2 block text-sm font-medium">Level</label><select id="form-level" class="select w-full" bind:value={$addForm.level} required disabled={$addForm.processing} aria-invalid={Boolean($addForm.errors.level)}><option value="">Choose level</option>{#each formLevels as item}<option value={item}>{item.toUpperCase()}</option>{/each}</select>{#if $addForm.errors.level}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.level}</p>{/if}</div>
                <div><label for="form-start" class="mb-2 block text-sm font-medium">Period start date</label><input id="form-start" class="input w-full" type="date" bind:value={$addForm.start_date} required disabled={$addForm.processing} aria-invalid={Boolean($addForm.errors.start_date)} />{#if $addForm.errors.start_date}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.start_date}</p>{/if}</div>
                <div><label for="form-end" class="mb-2 block text-sm font-medium">Period end date</label><input id="form-end" class="input w-full" type="date" bind:value={$addForm.end_date} min={$addForm.start_date || undefined} required disabled={$addForm.processing} aria-invalid={Boolean($addForm.errors.end_date)} />{#if $addForm.errors.end_date}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.end_date}</p>{/if}</div>
            </div>
            <div class="modal-action mt-1 border-t border-base-300 pt-5"><button type="button" class="btn" disabled={$addForm.processing} onclick={() => addModal.close()}>Cancel</button><button type="submit" class="btn" disabled={$addForm.processing || !departments.length}>{$addForm.processing ? "Saving…" : editingExam ? "Save changes" : "Create examination"}</button></div>
        </form>
    </div>
</dialog>
