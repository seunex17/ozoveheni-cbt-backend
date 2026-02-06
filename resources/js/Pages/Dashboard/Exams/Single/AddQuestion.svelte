<script lang="ts">
    import { useForm } from "@inertiajs/svelte";
    import DashboardLayout from "../../../../Components/Layouts/DashboardLayout.svelte";
    import { X } from "lucide-svelte";
    import autoAnimate from "@formkit/auto-animate";

    let { singleExam } = $props();

    const form = useForm({
        question_text: "",
        single_exam_id: "",
        points: "1",
        options: [
            { option_text: "", is_correct: false },
            { option_text: "", is_correct: false },
        ],
    });

    $effect(() => {
        $form.single_exam_id = singleExam?.id ?? "";
    });

    const addOption = () => {
        $form.options = [
            ...$form.options,
            { option_text: "", is_correct: false },
        ];
    };

    const removeOption = (index) => {
        if ($form.options.length > 2) {
            $form.options = $form.options.filter((_, i) => i !== index);
        }
    };

    const setCorrect = (index) => {
        $form.options = $form.options.map((opt, i) => ({
            ...opt,
            is_correct: i === index,
        }));
    };

    const submit = () => {
        $form.post("/dashboard/exam/add-question", {
            onSuccess: () => {
                $form.reset();
                $form.single_exam_id = singleExam?.id;
                $form.options = [
                    { option_text: "", is_correct: false },
                    { option_text: "", is_correct: false },
                ];
            },
        });
    };
</script>

<DashboardLayout
    title="{singleExam.course.name} ({singleExam.course.code})"
    goBack={true}
>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-6">
        <div class="card bg-base-100 p-6">
            <div class="space-y-4">
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Question Text</legend>
                    <textarea
                        class="textarea h-24 w-full"
                        bind:value={$form.question_text}
                        placeholder="e.g. What is the capital of Nigeria?"
                    ></textarea>
                </fieldset>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Mark</legend>
                    <input
                        type="number"
                        class="input w-full"
                        bind:value={$form.points}
                    />
                </fieldset>

                <div class="divider">Options</div>

                <div class="space-y-3" use:autoAnimate>
                    {#each $form.options as option, i}
                        <div class="flex items-center gap-2">
                            <input
                                type="radio"
                                name="correct_answer"
                                class="radio radio-primary"
                                checked={option.is_correct}
                                onchange={() => setCorrect(i)}
                            />

                            <input
                                type="text"
                                bind:value={option.option_text}
                                class="input input-primary w-full"
                                placeholder="Option {i + 1}"
                            />

                            <button
                                type="button"
                                class="btn btn-error btn-soft btn-sm btn-circle"
                                onclick={() => removeOption(i)}
                            >
                                <X size="14" />
                            </button>
                        </div>
                    {/each}
                </div>

                <div class="divider"></div>

                <div class="flex justify-between">
                    <button
                        type="button"
                        class="btn btn-outline"
                        onclick={addOption}
                    >
                        Add Option
                    </button>
                    <button
                        onclick={submit}
                        class="btn btn-primary"
                        disabled={$form.processing ||
                            $form.question_text === "" ||
                            $form.options.length < 2}
                    >
                        Save Question
                    </button>
                </div>
            </div>
        </div>

        <div class="sticky top-6 h-fit">
            <div class="card bg-base-100 border-t-4 border-primary">
                <div class="card-body">
                    <div class="flex justify-between items-start">
                        <span class="text-sm opacity-50">Question Preview</span>
                        <span class="badge badge-outline"
                            >{$form.points} Marks</span
                        >
                    </div>

                    <h3 class="text-lg font-semibold mt-2">
                        {$form.question_text || "Enter your question..."}
                    </h3>

                    <div class="mt-6 space-y-2" use:autoAnimate>
                        {#each $form.options as option}
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
                                <span
                                    class={option.is_correct ? "font-bold" : ""}
                                >
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
    </div>
</DashboardLayout>
