<script lang="ts">
    import { InfiniteScroll, Link } from "@inertiajs/svelte";
    import DashboardLayout from "./../../../Components/Layouts/DashboardLayout.svelte";
    import { Pen, Plus, Trash, View } from "lucide-svelte";
    import Time from "svelte-time";

    let { exams } = $props();
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
