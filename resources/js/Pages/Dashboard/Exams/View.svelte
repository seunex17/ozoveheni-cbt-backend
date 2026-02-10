<script lang="ts">
    import { InfiniteScroll, Link } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { ChessKingIcon, Plus, Sheet, Trash, View } from "lucide-svelte";
    import Time from "svelte-time";

    let { exam, singleExams } = $props();
</script>

<DashboardLayout title="{exam.title} ({singleExams.total})" goBack={true}>
    <div class="card w-full bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <InfiniteScroll data="singleExams">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Course Code</th>
                                <th>Attends</th>
                                <th>Question</th>
                                <th>Duration</th>
                                <th>Schedule</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each singleExams.data as exam (exam.id)}
                                <tr>
                                    <td>{exam.course.name}</td>
                                    <td>{exam.course.code}</td>
                                    <td>
                                        <div
                                            class="badge badge-success badge-soft"
                                        >
                                            0
                                        </div>
                                    </td>
                                    <td>
                                        <div
                                            class="badge badge-primary badge-soft"
                                        >
                                            0
                                        </div>
                                    </td>
                                    <td>{exam.duration}(Min)</td>
                                    <td>
                                        <Time timestamp={exam.start} relative />
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            <Link
                                                href="/dashboard/exam/{exam.uuid}/single-exam"
                                            >
                                                <button
                                                    class="btn btn-square btn-primary btn-soft btn-sm"
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
        <div class="flex gap-4">
            <Link
                href="/dashboard/exam/{exam.uuid}/reports"
                class="btn btn-secondary btn-sm btn-outline"
            >
                <ChessKingIcon size="16" /> Reports</Link
            >
            <Link
                href="/dashboard/exam/{exam.uuid}/set-new-exam"
                class="btn btn-primary btn-sm btn-outline"
            >
                <Plus size="16" /> Set New Exam</Link
            >
            <Link
                href="/dashboard/exam/{exam.uuid}/broadsheet"
                class="btn btn-info btn-sm btn-outline"
            >
                <Sheet size="16" /> Broadsheet</Link
            >
        </div>
    {/snippet}
</DashboardLayout>
