<script lang="ts">
    import DashboardLayout from "../../../../Components/Layouts/DashboardLayout.svelte";

    let { question } = $props();
</script>

<DashboardLayout title="Manage Question" goBack={true}>
    <div class="w-full flex justify-center items-center">
        <div class="w-full max-w-xl card bg-base-100">
            <div class="card-body">
                <div class="flex justify-between items-start">
                    <span class="text-sm opacity-50">Question Preview</span>
                    <span class="badge badge-outline"
                        >{question.marks} Marks</span
                    >
                </div>

                <h3 class="text-lg font-semibold mt-2">
                    {question.question_text || "Enter your question..."}
                </h3>

                <div class="mt-6 space-y-2">
                    {#each question.options as option}
                        <div
                            class="flex items-center gap-3 p-3 rounded-lg border border-base-300 {option.is_correct
                                ? 'bg-success/10 border-success/30'
                                : ''}"
                        >
                            <div
                                class="w-4 h-4 rounded-full border-2 {option.is_correct
                                    ? 'bg-success border-success'
                                    : 'border-base-300'}"
                            ></div>
                            <span class={option.is_correct ? "font-bold" : ""}>
                                {option.option_text || "..."}
                            </span>
                            {#if option.is_correct}
                                <span
                                    class="badge badge-success badge-sm ml-auto"
                                    >Correct</span
                                >
                            {/if}
                        </div>
                    {/each}
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>
