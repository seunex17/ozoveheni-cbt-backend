<script lang="ts">
    import { InfiniteScroll, Link } from "@inertiajs/svelte";
    import DashboardLayout from "../../../../Components/Layouts/DashboardLayout.svelte";
    import { Plus, Trash, View } from "lucide-svelte";
    import Time from "svelte-time";

    let { singleExam, questions } = $props();
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
