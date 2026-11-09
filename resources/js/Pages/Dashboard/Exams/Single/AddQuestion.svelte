<script lang="ts">
    import { useForm, Link } from "@inertiajs/svelte";
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

    const submit = (event: SubmitEvent) => {
        event.preventDefault();
        if ($form.processing) return;
        $form.post("/dashboard/exam/add-question", {
            onSuccess: (page) => {
                if ((page.props.flash as any)?.error) return;
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
    title="Add question"
    description={`${singleExam.course.code} · ${singleExam.course.name}`}
>
    {#snippet actions()}<Link href={`/dashboard/exam/${singleExam.uuid}/single-exam`} class="btn btn-ghost btn-sm">Back to question bank</Link>{/snippet}
    <div class="grid items-start gap-6 xl:grid-cols-2">
        <section class="card card-border bg-base-100"><form onsubmit={submit} class="card-body gap-5 p-5 sm:p-6" aria-busy={$form.processing}>
                <h2 class="text-base font-semibold">Question details</h2><p class="text-sm leading-6 text-base-content/65">Write the question, add at least two options and select the correct answer.</p>
                {#each Object.values($form.errors) as error}<p class="text-sm text-error" role="alert">{error}</p>{/each}
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Question text</legend>
                    <textarea
                        class="textarea min-h-32 w-full" required disabled={$form.processing} aria-label="Question text"
                        bind:value={$form.question_text}
                        placeholder="e.g. What is the capital of Nigeria?"
                    ></textarea>
                </fieldset>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Marks</legend>
                    <input
                        type="number"
                        class="input w-full"
                        bind:value={$form.points} required min="1" step="1" disabled={$form.processing} aria-label="Marks for this question"
                    />
                </fieldset>

                <div class="divider">Options</div>

                <div class="space-y-3" use:autoAnimate>
                    {#each $form.options as option, i}
                        <div class="flex items-center gap-2">
                            <input
                                type="radio"
                                name="correct_answer"
                                class="radio" aria-label={`Mark option ${i + 1} as correct`} disabled={$form.processing} required
                                checked={option.is_correct}
                                onchange={() => setCorrect(i)}
                            />

                            <input
                                type="text"
                                bind:value={option.option_text}
                                class="input w-full" aria-label={`Option ${i + 1} text`} required disabled={$form.processing}
                                placeholder="Option {i + 1}"
                            />

                            <button
                                type="button"
                                class="btn btn-ghost btn-sm btn-square" aria-label={`Remove option ${i + 1}`} disabled={$form.processing || $form.options.length <= 2}
                                onclick={() => removeOption(i)}
                            >
                                <X size="14" />
                            </button>
                        </div>
                    {/each}
                </div>

                <div class="divider"></div>

                <div class="flex flex-wrap justify-between gap-3">
                    <button
                        type="button"
                        class="btn"
                        disabled={$form.processing}
                        onclick={addOption}
                    >
                        Add Option
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        disabled={$form.processing ||
                            $form.question_text === "" ||
                            $form.options.length < 2}
                    >
                        {$form.processing ? "Saving…" : "Save question"}
                    </button>
                </div>
            </form>
        </section>

        <div class="h-fit xl:sticky xl:top-6">
            <div class="card card-border bg-base-100">
                <div class="card-body">
                    <div class="flex justify-between items-start">
                        <span class="text-sm opacity-50">Question Preview</span>
                        <span class="badge badge-outline"
                            >{$form.points} Marks</span
                        >
                    </div>

                    <h3 class="mt-2 whitespace-pre-wrap break-words text-base font-semibold leading-7">
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
                                    class={`min-w-0 break-words ${option.is_correct ? "font-semibold" : ""}`}
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
