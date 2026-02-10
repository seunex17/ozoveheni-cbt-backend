<script lang="ts">
    import { InfiniteScroll, Link, page, router } from "@inertiajs/svelte";
    import DashboardLayout from "../../Components/Layouts/DashboardLayout.svelte";
    import { Pen, Plus, Trash } from "lucide-svelte";
    import Time from "svelte-time";

    let { students, department, level } = $props();

    let deleteModal: HTMLDialogElement;
    let deptId;

    const doDelete = (department: any) => {
        deptId = department.id;
        deleteModal.showModal();
    };

    const deleteDepartment = () => {
        router.post(
            `/dashboard/student/delete`,
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
    title="{department.name} Students ({`${level.toUpperCase()}`})"
    goBack={true}
>
    <div class="w-full p-4 card bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <InfiniteScroll data="students">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Reg. No</th>
                                <th>First name</th>
                                <th>Last name</th>
                                <th>Set</th>
                                <th>Gender</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each students.data as student (student.id)}
                                <tr>
                                    <td>
                                        <div class="avatar">
                                            <div
                                                class="mask mask-squircle size-10"
                                            >
                                                <img
                                                    src="/storage/{student.photo}"
                                                    alt=""
                                                />
                                            </div>
                                        </div>
                                    </td>
                                    <td>{student.reg_no}</td>
                                    <td>{student.first_name}</td>
                                    <td>{student.last_name}</td>
                                    <td>{student.set}</td>
                                    <td>
                                        {#if student.gender === "M"}
                                            Male
                                        {:else}
                                            Female
                                        {/if}
                                    </td>
                                    <td
                                        ><Time
                                            timestamp={student.created_at}
                                        /></td
                                    >
                                    <td>
                                        <div class="flex gap-2">
                                            <Link
                                                href="/dashboard/student/{student.uuid}/edit"
                                            >
                                                <button
                                                    class="btn btn-square btn-primary btn-soft btn-sm"
                                                >
                                                    <Pen size="16" />
                                                </button>
                                            </Link>
                                            <button
                                                onclick={() =>
                                                    doDelete(student)}
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
        <Link href="{$page.url}/add" class="btn btn-primary btn-sm btn-outline">
            <Plus size="16" /> Add New"></Link
        >
    {/snippet}
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal">
    <div class="max-w-sm modal-box">
        <h3 class="text-lg font-bold text-center">Are you sure?</h3>
        <p class="py-4 text-center">
            Please confirm you want to delete this student.
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
