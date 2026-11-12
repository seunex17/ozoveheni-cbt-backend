<script lang="ts">
    import { Link, router } from "@inertiajs/svelte";
    import { Search, RotateCcw, ArrowLeft, ArrowRight, Users, ShieldCheck } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { exams, selectedExam, filters, students, totalAttempts } = $props();
    let exam = $state("");
    let search = $state("");
    let searching = $state(false);
    let clearing = $state(false);
    let target = $state<any>(null);
    let error = $state("");
    let modal: HTMLDialogElement;
    const name = (student: any) => [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ");
    $effect(() => { exam = filters.exam; search = filters.search; });

    function applyFilters(event: SubmitEvent) {
        event.preventDefault();
        searching = true;
        router.get("/dashboard/attempts", { ...(exam ? { exam } : {}), ...(search.trim() ? { search: search.trim() } : {}) }, {
            preserveState: true, preserveScroll: true,
            onFinish: () => searching = false,
        });
    }
    function confirmClear(student: any = null) {
        if (!selectedExam || clearing) return;
        target = student;
        error = "";
        modal.showModal();
    }
    function clearAttempts() {
        if (!selectedExam || clearing) return;
        clearing = true;
        const url = target
            ? `/dashboard/attempts/${selectedExam.uuid}/students/${target.uuid}/clear`
            : `/dashboard/attempts/${selectedExam.uuid}/clear`;
        router.post(url, { confirmed: true }, {
            preserveScroll: true,
            onSuccess: () => modal.close(),
            onError: (errors) => error = Object.values(errors).join(" "),
            onFinish: () => clearing = false,
        });
    }
</script>

<svelte:head><title>Manage attempts · Ozoveheni</title></svelte:head>
<DashboardLayout title="Manage attempts" description="Find students’ exam attempts and clear them when a fresh start is needed.">
    <div class="flex flex-col gap-6">
        <section class="card card-border bg-base-100" aria-labelledby="find-heading">
            <form onsubmit={applyFilters} class="card-body gap-5 p-5 sm:p-6" aria-busy={searching}>
                <h2 id="find-heading" class="text-sm font-semibold">Choose an exam and find a student</h2>
                <div class="grid items-end gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_auto]">
                    <div class="min-w-0"><label for="attempt-exam" class="mb-2 block text-xs font-medium text-base-content/70">Exam</label><select id="attempt-exam" class="select w-full rounded-lg border-base-300" bind:value={exam}><option value="">Select an exam</option>{#each exams as item (item.uuid)}<option value={item.uuid}>{item.title} · {item.department?.name ?? ""} · {item.set} · {item.level.toUpperCase()}</option>{/each}</select></div>
                    <div><label for="attempt-search" class="mb-2 block text-xs font-medium text-base-content/70">Student name or registration number</label><label class="input flex w-full items-center gap-2 rounded-lg border-base-300"><Search size={17} class="shrink-0 text-base-content/50" /><input id="attempt-search" type="search" bind:value={search} maxlength={100} placeholder="e.g. STD/25/003" /></label></div>
                    <button class="btn gap-2 rounded-lg" type="submit" disabled={searching}><Search size={16} />{searching ? "Searching…" : "Search"}</button>
                </div>
                <p class="border-t border-base-300 pt-4 text-xs text-base-content/60">Press Search or Enter to apply. Attempts include all course papers within the selected exam.</p>
            </form>
        </section>
        {#if selectedExam}
            <section class="card card-border bg-base-100" aria-labelledby="exam-heading">
                <div class="card-body flex-row flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                    <div class="min-w-0"><p class="text-xs text-base-content/60">Selected exam</p><h2 id="exam-heading" class="mt-1 text-lg font-semibold">{selectedExam.title}</h2><p class="mt-1 text-sm text-base-content/60">{selectedExam.set} · {selectedExam.level.toUpperCase()} · {totalAttempts} {totalAttempts === 1 ? "attempt" : "attempts"} in total</p></div>
                    <button class="btn btn-outline gap-2 rounded-lg text-error" disabled={!totalAttempts || searching} onclick={() => confirmClear()}><RotateCcw size={16} /> Clear all exam attempts</button>
                </div>
            </section>
        {/if}
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="students-heading" aria-busy={searching}>
            <div class="border-b border-base-300 px-5 py-5 sm:px-6"><h2 id="students-heading" class="text-base font-semibold">Student attempts</h2><p class="mt-1 text-xs text-base-content/60" role="status">{selectedExam ? `${students.total} students${filters.search ? ` matching “${filters.search}”` : ""}` : "Select an exam to view its attempts."}</p></div>
            {#if students.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table needs keyboard focus for horizontal navigation.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Student attempts, scroll horizontally if needed">
                    <table class="table min-w-170"><caption class="sr-only">Students with attempts in the selected exam</caption>
                        <thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6">Student</th><th scope="col">Course attempts</th><th scope="col">Submitted</th><th scope="col" class="pr-6 text-right">Action</th></tr></thead>
                        <tbody>{#each students.data as student (student.id)}<tr class="border-base-300/70 hover:bg-base-200/40"><th scope="row" class="px-6 py-5 font-normal"><p class="text-sm font-medium">{name(student)}</p><p class="mt-1 text-xs text-base-content/60">{student.reg_no}</p></th><td class="tabular-nums">{student.attempts_count}</td><td><span class="badge badge-ghost badge-sm tabular-nums">{student.submitted_count} / {student.attempts_count}</span></td><td class="pr-6 text-right"><button class="btn btn-ghost btn-sm gap-2 text-error" aria-label={`Clear attempts for ${student.reg_no}`} onclick={() => confirmClear(student)}><RotateCcw size={14} /> Clear attempts</button></td></tr>{/each}</tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 sm:px-6"><span class="text-xs text-base-content/60">Showing {students.from}–{students.to} of {students.total} students</span><nav class="flex flex-wrap items-center gap-2" aria-label="Student attempt pages">{#if students.prev_page_url}<Link href={students.prev_page_url} class="btn btn-sm" preserveScroll><ArrowLeft size={14} /> Previous</Link>{:else}<button class="btn btn-sm" disabled>Previous</button>{/if}<span class="text-xs text-base-content/60">Page {students.current_page} of {students.last_page}</span>{#if students.next_page_url}<Link href={students.next_page_url} class="btn btn-sm" preserveScroll>Next <ArrowRight size={14} /></Link>{:else}<button class="btn btn-sm" disabled>Next</button>{/if}</nav></div>
            {:else}
                <div class="flex flex-col items-center px-6 py-14 text-center"><Users size={30} strokeWidth={1.4} class="mb-4 text-base-content/45" /><h3 class="text-sm font-semibold">{!selectedExam ? "Start with an exam" : filters.search ? "No matching students" : "No attempts for this exam"}</h3><p class="mt-2 max-w-md text-sm leading-6 text-base-content/60">{!selectedExam ? "Choose the semester exam above, then press Search." : filters.search ? "Try a different name or registration number, or remove your student search." : "Student attempts will appear here after they sign in to a course paper."}</p></div>
            {/if}
        </section>
        <div class="flex items-start gap-3 text-xs leading-5 text-base-content/60"><ShieldCheck size={17} class="mt-0.5 shrink-0" /><p>Clearing attempts removes saved answers and live hall entries. Stored result scores are kept. Students can start again only while the exam’s normal admission window is open.</p></div>
    </div>
</DashboardLayout>
<dialog bind:this={modal} class="modal" aria-labelledby="clear-heading" aria-describedby="clear-description" oncancel={(event) => { if (clearing) event.preventDefault(); }}>
    <div class="modal-box max-w-lg">
        <div class="flex size-11 items-center justify-center rounded-lg bg-error/10 text-error"><RotateCcw size={21} /></div>
        <h2 id="clear-heading" class="mt-5 text-lg font-semibold">{target ? "Clear this student’s attempts?" : "Clear all attempts for this exam?"}</h2>
        <div id="clear-description" class="mt-3 space-y-3 text-sm leading-6 text-base-content/65"><p><strong class="text-base-content">{selectedExam?.title}</strong> · {selectedExam?.set} · {selectedExam?.level?.toUpperCase()}</p>{#if target}<p>Remove all <strong>{target.attempts_count} course attempts</strong> for <strong class="text-base-content">{name(target)} ({target.reg_no})</strong> in this exam.</p>{:else}<p>Remove <strong>all {totalAttempts} attempts for every student</strong> in this exam, including students outside the current search.</p>{/if}<p>Saved answers and live hall entries will be permanently removed. Stored result scores are kept. This cannot be undone.</p></div>
        {#if error}<p class="mt-4 text-sm text-error" role="alert">{error}</p>{/if}
        <div class="modal-action"><form method="dialog"><button class="btn" disabled={clearing}>Cancel</button></form><button class="btn btn-error" onclick={clearAttempts} disabled={clearing}>{clearing ? "Clearing…" : target ? "Clear student attempts" : "Clear all exam attempts"}</button></div>
    </div>
</dialog>
