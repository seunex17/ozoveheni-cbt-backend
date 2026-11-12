<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { departments, exam, levels, errors = {} } = $props();
</script>

<DashboardLayout title="Edit: {exam.title}">
    <div class="w-full flex flex-col justify-center items-center">
        <div class="w-full max-w-xl card bg-base-100">
            <div class="card-body">
                <Form
                    class="w-full grid grid-cols-1 sm:grid-cols-2 gap-4"
                    action="/dashboard/exam/edit"
                    method="POST"
                    disableWhileProcessing
                >
                    {#each Object.values(errors) as error}<p class="text-sm text-error sm:col-span-2" role="alert">{error}</p>{/each}
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Title</legend>
                        <input
                            type="text"
                            class="input w-full"
                            name="title"
                            value={exam.title}
                            placeholder="First semester exam"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Subtitle</legend>
                        <input
                            type="text"
                            class="input w-full"
                            name="subtitle"
                            value={exam.subtitle}
                            placeholder="2025/2026"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Department</legend>
                        <select class="select w-full" name="department_id">
                            {#each departments as dept}
                                <option
                                    value={dept.id}
                                    selected={dept.id === exam.department_id}
                                    >{dept.name}</option
                                >
                            {/each}
                        </select>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Set</legend>
                        <input
                            type="text"
                            class="input w-full"
                            name="set"
                            value={exam.set}
                            placeholder="2025"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Start Date</legend>
                        <input
                            type="date"
                            class="input w-full"
                            name="start_date"
                            value={exam.start_date?.slice(0, 10)}
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">End Date</legend>
                        <input
                            type="date"
                            class="input w-full"
                            name="end_date"
                            value={exam.end_date?.slice(0, 10)}
                        />
                    </fieldset>
                    <div><label for="edit-exam-level" class="mb-2 block text-sm font-medium">Level</label><select id="edit-exam-level" name="level" class="select w-full" required>{#each levels as item}<option value={item} selected={item === exam.level?.toLowerCase()}>{item.toUpperCase()}</option>{/each}</select></div>
                    <div class="sm:col-span-2 w-full flex justify-center">
                        <div class="max-w-sm w-full">
                            <button class="btn btn-block btn-primary"
                                >Save Changes</button
                            >
                        </div>
                    </div>
                    <input type="hidden" name="id" value={exam.id} />
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
