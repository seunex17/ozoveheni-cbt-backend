<script lang="ts">
    import { InfiniteScroll, Link } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import Time from "svelte-time";
    import { Key, Pen, Plus, Trash } from "lucide-svelte";

    let { users } = $props();
</script>

<DashboardLayout title="Manage Staffs">
    <div class="w-full p-4 card bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <InfiniteScroll data="users">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each users.data as user (user.id)}
                                <tr>
                                    <td>#{user.id}</td>
                                    <td>{user.name}</td>
                                    <td>{user.email}</td>
                                    <td>
                                        <div
                                            class={{
                                                "capitalize badge badge-soft": true,
                                                "badge-success":
                                                    user.status === "active",
                                                "badge-error":
                                                    user.status === "inactive",
                                            }}
                                        >
                                            {user.status}
                                        </div>
                                    </td>
                                    <td><Time timestamp={user.created_at} /></td
                                    >
                                    <td>
                                        <div class="flex gap-2">
                                            <Link
                                                href="/dashboard/staff/{user.id}/edit"
                                            >
                                                <button
                                                    class="btn btn-square btn-primary btn-soft btn-sm"
                                                >
                                                    <Pen size="16" />
                                                </button>
                                            </Link>
                                            <Link
                                                href="/dashboard/staff/{user.id}/update-password"
                                            >
                                                <button
                                                    class="btn btn-square btn-warning btn-soft btn-sm"
                                                >
                                                    <Key size="16" />
                                                </button>
                                            </Link>
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
            href="/dashboard/staff/add"
            class="btn btn-primary btn-sm btn-outline"
        >
            <Plus size="16" /> Add New"></Link
        >
    {/snippet}
</DashboardLayout>
