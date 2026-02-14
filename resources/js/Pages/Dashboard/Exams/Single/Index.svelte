<script lang="ts">
    import { InfiniteScroll, Link, router } from "@inertiajs/svelte";
    import DashboardLayout from "../../../../Components/Layouts/DashboardLayout.svelte";
    import {
        Plus,
        Trash,
        UploadIcon,
        View,
        FileSpreadsheet,
        Download,
    } from "lucide-svelte";
    import Time from "svelte-time";

    let { singleExam, questions } = $props();

    let deleteModal: HTMLDialogElement;
    let importExcelModal: HTMLDialogElement;
    let deptId;

    const doDelete = (department: any) => {
        deptId = department.id;
        deleteModal.showModal();
    };

    const deleteDepartment = () => {
        router.post(
            `/dashboard/exam/delete-question`,
            {
                id: deptId,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onSuccess: () => {
                    deleteModal.close();
                },
            },
        );
    };

    const openImportExcelModal = () => {
        importExcelModal.showModal();
    };

    const uploadExcel = (e: Event) => {
        const target = e.target as HTMLInputElement;
        if (target.files && target.files.length > 0) {
            router.post(
                `/dashboard/exam/import-questions`,
                {
                    exam_id: singleExam.id,
                    file: target.files[0],
                },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        importExcelModal.close();
                    },
                },
            );
        }
    };

    const downloadTemplate = () => {
        window.open("/dashboard/exam/download-template", "_blank");
    };
</script>

<DashboardLayout
    title="{singleExam.course.name} ({singleExam.course.code})"
    goBack={true}
>
    <div class="card w-full bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <div class="overflow-x-auto">
                    <InfiniteScroll data="questions">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Exam</th>
                                    <th>Question</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each questions.data as question (question.id)}
                                    <tr>
                                        <td>{singleExam.exam.title}</td>
                                        <td>{question.question_text}</td>
                                        <td>
                                            <Time
                                                timestamp={question.created_at}
                                            />
                                        </td>
                                        <td>
                                            <div class="flex gap-2">
                                                <Link
                                                    href="/dashboard/exam/{question.id}/view-question"
                                                >
                                                    <button
                                                        class="btn btn-square btn-success btn-soft btn-sm"
                                                    >
                                                        <View size="16" />
                                                    </button>
                                                </Link>
                                                <button
                                                    onclick={() =>
                                                        doDelete(question)}
                                                    class="btn btn-square btn-error btn-soft btn-sm"
                                                >
                                                    <Trash size="16" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </InfiniteScroll>
                </div>
            </div>
        </div>
    </div>

    {#snippet actions()}
        <Link
            href="/dashboard/exam/{singleExam.uuid}/add-question"
            class="btn btn-primary btn-sm btn-outline"
        >
            <Plus size="16" /> Add Question</Link
        >
        <button
            onclick={openImportExcelModal}
            class="btn btn-secondary btn-sm btn-outline"
        >
            <UploadIcon size="16" /> Import Excel
        </button>
    {/snippet}
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal">
    <div class="max-w-sm modal-box">
        <h3 class="text-lg font-bold text-center">Are you sure?</h3>
        <p class="py-4 text-center">
            Please confirm you want to delete this exam question
        </p>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn">Close</button>
            </form>
            <button onclick={deleteDepartment} class="btn btn-error btn-soft"
                >Delete</button
            >
        </div>
    </div>
</dialog>

<dialog bind:this={importExcelModal} class="modal">
    <div class="max-w-md modal-box">
        <h3 class="text-lg font-bold text-center">Import Questions</h3>
        <div class="flex flex-col items-center justify-center w-full mt-4">
            <label
                for="dropzone-file"
                class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-lg cursor-pointer border-base-300 bg-base-100 hover:bg-base-200"
            >
                <div
                    class="flex flex-col items-center justify-center pt-5 pb-6"
                >
                    <FileSpreadsheet
                        class="w-10 h-10 mb-3 text-base-content/50"
                    />
                    <p class="mb-2 text-sm text-base-content/70">
                        <span class="font-semibold">Select Excel file</span>
                    </p>
                    <p class="text-xs text-base-content/50">XLSX files only</p>
                </div>
                <input
                    id="dropzone-file"
                    type="file"
                    accept=".xlsx"
                    class="hidden"
                    onchange={uploadExcel}
                />
            </label>
            <button
                onclick={downloadTemplate}
                class="mt-4 btn btn-ghost btn-sm text-primary"
            >
                <Download size="16" class="mr-2" /> Download Template
            </button>
        </div>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn">Close</button>
            </form>
        </div>
    </div>
</dialog>
