<script lang="ts">
    import { router, Link } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { RevoGrid, type ColumnRegular } from "@revolist/svelte-datagrid";
    import { HistoryIcon } from "lucide-svelte";


    let { exam, courseUUid, courses, course, reports } = $props();

    let source = $state([]);
    let selectedCourse = $state("");

    const columns: ColumnRegular[] = [
        {
            prop: "name",
            name: "Name",
            size: 250,
            sortable: true,
            filterable: true,
            readonly: true,
            pin: "colPinStart",
        },
        {
            prop: "reg_no",
            name: "Reg No",
            size: 200,
            sortable: true,
            filterable: true,
            readonly: true,
        },
        {
            prop: "first_ca",
            name: "First C.A",
            size: 150,
            sortable: true,
            filterable: true,
            cellProperties: ({ prop, model }) => {
                return {
                    class: "editable-cell",
                };
            },
        },
        {
            prop: "second_ca",
            name: "Second C.A",
            size: 150,
            sortable: true,
            filterable: true,
            cellProperties: ({ prop, model }) => {
                return {
                    class: "editable-cell",
                };
            },
        },
        {
            prop: "exam",
            name: "Exam",
            size: 150,
            sortable: true,
            filterable: true,
            readonly: true,
        },
        {
            prop: "total",
            name: "Total",
            size: 150,
            sortable: true,
            filterable: true,
            readonly: true,
        },
    ];

    function handleAfterEdit(event) {
        const data = event.detail.model;
        router.post("/dashboard/exam/update=report", data, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    const refreshReport = () => {
        router.post("/dashboard/exam/refresh-reports", {
            exam_id: exam.id,
            course_id: course.id,
        });
    };

    $effect(() => { source = reports ?? []; });
    $effect(() => { selectedCourse = courseUUid ?? ""; });
    function loadCourse(event: SubmitEvent) {
        event.preventDefault();
        if (selectedCourse) router.get(`/dashboard/exam/${exam.uuid}/reports`, { course_uuid: selectedCourse });
    }
</script>

<svelte:head><title>Reports · {exam.title}</title></svelte:head>
<DashboardLayout title="Student reports" description={exam.title}>
    {#snippet actions()}<Link href={`/dashboard/exam/${exam.uuid}/view`} class="btn btn-ghost btn-sm">Back to course papers</Link>{/snippet}
    <div class="flex flex-col gap-6">
        <section class="card card-border bg-base-100"><div class="card-body gap-4 p-5 sm:p-6"><h2 class="text-sm font-semibold">Choose a course</h2><form onsubmit={loadCourse} class="flex flex-col items-end gap-3 sm:flex-row"><div class="w-full sm:max-w-lg"><label for="report-course" class="sr-only">Course to view reports for</label><select id="report-course" class="select w-full" bind:value={selectedCourse}><option value="">Choose course</option>{#each courses as item}<option value={item.uuid}>{item.code} — {item.name}</option>{/each}</select></div><button type="submit" class="btn w-full sm:w-auto" disabled={!selectedCourse}>View reports</button></form></div></section>
        {#if course}
            <section class="card card-border overflow-hidden bg-base-100"><div class="flex flex-wrap items-center justify-between gap-4 border-b border-base-300 px-5 py-5"><div><h2 class="text-base font-semibold">{course.name} ({course.code})</h2><p class="mt-1 text-xs leading-5 text-base-content/65">{source.length} {source.length === 1 ? "student record" : "student records"} · Double-click a First C.A or Second C.A cell to edit. Changes save automatically.</p></div><button onclick={refreshReport} class="btn btn-sm gap-2"><HistoryIcon size={16} /> Refresh student records</button></div>{#if source.length}<div class="h-[65dvh] min-h-100 w-full"><RevoGrid {source} {columns} theme="material" resize={true} filter={true} range={true} readonly={false} canFocus={true} on:afteredit={handleAfterEdit} class="h-full w-full" /></div>{:else}<div class="px-6 py-12 text-center"><h3 class="text-sm font-semibold">No student reports yet</h3><p class="mt-2 text-sm text-base-content/65">Refresh student records to prepare the report for this course.</p></div>{/if}</section>
        {:else}<div class="card card-border bg-base-100"><div class="card-body items-center py-14 text-center"><h2 class="text-sm font-semibold">Select a course to view its reports</h2><p class="text-sm text-base-content/65">Review exam scores and enter continuous assessment marks in one place.</p></div></div>{/if}
    </div>
</DashboardLayout>
<style>
    :global(.editable-cell) { background-color: var(--color-base-200); cursor: text; }
    :global(.editable-cell:hover) { background-color: var(--color-base-300); }
</style>
