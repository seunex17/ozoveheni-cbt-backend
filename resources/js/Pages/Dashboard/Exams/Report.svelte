<script lang="ts">
    import { router } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { RevoGrid, type ColumnRegular } from "@revolist/svelte-datagrid";
    import { HistoryIcon } from "lucide-svelte";
    import { onMount } from "svelte";

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

    onMount(() => {
        source = reports ?? [];
    });

    $effect(() => {
        if (selectedCourse !== "") {
            router.visit(`?course_uuid=${selectedCourse}`);
        }
    });
</script>

<DashboardLayout title="{exam.title} ({reports.length})" goBack={true}>
    <div class="w-full h-[calc(100vh-12rem)] overflow-hidden">
        <RevoGrid
            {source}
            {columns}
            theme="material"
            resize={true}
            filter={true}
            range={true}
            readonly={false}
            canFocus={true}
            on:afteredit={handleAfterEdit}
            class="w-full h-full"
        />
    </div>

    {#snippet actions()}
        <div class="flex gap-2 items-center">
            <div class="w-xs">
                <fieldset class="fieldset">
                    <select
                        class="select"
                        name="department_id"
                        bind:value={selectedCourse}
                    >
                        <option value="" disabled selected>Pick a course</option
                        >
                        {#each courses as dept}
                            <option value={dept.uuid}>{dept.name}</option>
                        {/each}
                    </select>
                </fieldset>
            </div>
            {#if courseUUid}
                <button
                    onclick={refreshReport}
                    class="btn btn-circle btn-soft btn-info"
                >
                    <HistoryIcon size="18" />
                </button>
            {/if}
        </div>
    {/snippet}
</DashboardLayout>

<style>
    :global(.revogrid) {
        width: 100% !important;
        height: 100% !important;
    }

    :global(.editable-cell) {
        background-color: rgba(255, 255, 255, 0.05);
        cursor: text;
    }

    :global(.editable-cell:hover) {
        background-color: rgba(255, 255, 255, 0.1);
    }
</style>
