<script lang="ts">
    import { InfiniteScroll, Link, router } from "@inertiajs/svelte";
    import DashboardLayout from "../../../../Components/Layouts/DashboardLayout.svelte";
    import { Plus, Trash, View } from "lucide-svelte";
    import Time from "svelte-time";

    let { singleExam, questions } = $props();

    let deleteModal: HTMLDialogElement;
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
