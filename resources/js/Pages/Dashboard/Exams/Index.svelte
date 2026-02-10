<script lang="ts">
    import { InfiniteScroll, Link, router } from "@inertiajs/svelte";
    import DashboardLayout from "./../../../Components/Layouts/DashboardLayout.svelte";
    import { Pen, Plus, Trash, View } from "lucide-svelte";
    import Time from "svelte-time";

    let { exams } = $props();

    let deleteModal: HTMLDialogElement;
    let deptId;

    const doDelete = (department: any) => {
        deptId = department.id;
        deleteModal.showModal();
    };

    const deleteDepartment = () => {
        router.post(
            `/dashboard/exam/delete-exam`,
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

<DashboardLayout title="Examinations">
    <div class="card w-full bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <InfiniteScroll data="exams">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Department</th>
                                <th>Set</th>
                                <th>Level</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each exams.data as exam (exam.id)}
                                <tr>
                                    <td>{exam.title}</td>
                                    <td>{exam.department.name}</td>
                                    <td>{exam.set}</td>
                                    <td>{exam.level}</td>
                                    <td>
                                        <Time timestamp={exam.start_date} />
                                    </td>
                                    <td>
                                        <Time timestamp={exam.end_date} />
                                    </td>
                                    <td>
                                        <div
                                            class={{
                                                "badge badge-soft capitalize": true,
                                                "badge-warning":
                                                    exam.status === "pending",
                                                "badge-success":
                                                    exam.status === "active",
                                                "badge-error":
                                                    exam.status === "completed",
                                            }}
                                        >
                                            {exam.status}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            <Link
                                                href="/dashboard/exam/{exam.uuid}/edit"
                                            >
                                                <button
                                                    class="btn btn-square btn-primary btn-soft btn-sm"
                                                >
                                                    <Pen size="16" />
                                                </button>
                                            </Link>
                                            <Link
                                                href="/dashboard/exam/{exam.uuid}/view"
                                            >
                                                <button
                                                    class="btn btn-square btn-secondary btn-soft btn-sm"
                                                >
                                                    <View size="16" />
                                                </button>
                                            </Link>
                                            <button
                                                onclick={() => doDelete(exam)}
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

    {#snippet actions()}
        <Link
            href="/dashboard/exam/add"
            class="btn btn-primary btn-sm btn-outline"
        >
            <Plus size="16" /> Add New</Link
        >
    {/snippet}
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal">
    <div class="max-w-sm modal-box">
        <h3 class="text-lg font-bold text-center">Are you sure?</h3>
        <p class="py-4 text-center">
            Please confirm you want to delete this exam?
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
