<script lang="ts">
    import { InfiniteScroll, Link, router } from "@inertiajs/svelte";
    import { Plus, Pen, Key, Trash } from "lucide-svelte";
    import Time from "svelte-time";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { departments } = $props();

    let deleteModal: HTMLDialogElement;
    let deptId;

    const doDelete = (department: any) => {
        deptId = department.id;
        deleteModal.showModal();
    };

    const deleteDepartment = () => {
        router.post(
            `/dashboard/department/delete`,
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

<DashboardLayout title="Departments">
    <div class="w-full p-4 card bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <InfiniteScroll data="departments">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Name</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each departments.data as dept (dept.id)}
                                <tr>
                                    <td>#{dept.id}</td>
                                    <td>{dept.name}</td>
                                    <td><Time timestamp={dept.created_at} /></td
                                    >
                                    <td>
                                        <div class="flex gap-2">
                                            <Link
                                                href="/dashboard/department/{dept.uuid}/edit"
                                            >
                                                <button
                                                    class="btn btn-square btn-primary btn-soft btn-sm"
                                                >
                                                    <Pen size="16" />
                                                </button>
                                            </Link>
                                            <button
                                                onclick={() => doDelete(dept)}
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
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal">
    <div class="max-w-sm modal-box">
        <h3 class="text-lg font-bold text-center">Are you sure?</h3>
        <p class="py-4 text-center">
            Please confirm you want to delete this department all student under
            this department will be deleted!
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
