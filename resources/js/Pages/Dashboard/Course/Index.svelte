<script lang="ts">
    import { InfiniteScroll, Link, router } from "@inertiajs/svelte";
    import DashboardLayout from "./../../../Components/Layouts/DashboardLayout.svelte";
    import Time from "svelte-time";
    import { Pen, Plus, Trash } from "lucide-svelte";

    let { courses } = $props();

    let deleteModal: HTMLDialogElement;
    let deptId;

    const doDelete = (department: any) => {
        deptId = department.id;
        deleteModal.showModal();
    };

    const deleteDepartment = () => {
        router.post(
            `/dashboard/course/delete`,
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

<DashboardLayout title="Courses ({courses.total})">
    <div class="card w-full bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <InfiniteScroll data="courses">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Course Name</th>
                                <th>Course Code</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each courses.data as course (course.id)}
                                <tr>
                                    <td>#{course.id}</td>
                                    <td>{course.name}</td>
                                    <td>{course.code}</td>
                                    <td
                                        ><Time
                                            timestamp={course.created_at}
                                        /></td
                                    >
                                    <td>
                                        <div class="flex gap-2">
                                            <Link
                                                href="/dashboard/course/{course.uuid}/edit"
                                            >
                                                <button
                                                    class="btn btn-square btn-primary btn-soft btn-sm"
                                                >
                                                    <Pen size="16" />
                                                </button>
                                            </Link>
                                            <button
                                                onclick={() => doDelete(course)}
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
            href="/dashboard/course/add"
            class="btn btn-primary btn-sm btn-outline"
        >
            <Plus size="16" /> Add New</Link
        >
    {/snippet}
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal">
    <div class="max-w-xs modal-box">
        <h3 class="text-lg font-bold text-center">Are you sure?</h3>
        <p class="py-4 text-center">
            Please confirm you want to delete this course?
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
