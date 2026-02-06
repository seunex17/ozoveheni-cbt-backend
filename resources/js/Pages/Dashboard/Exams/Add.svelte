<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { departments } = $props();
</script>

<DashboardLayout title="Add New Exam">
    <div class="w-full flex flex-col justify-center items-center">
        <div class="w-full max-w-xl card bg-base-100">
            <div class="card-body">
                <Form
                    class="w-full grid grid-cols-2 gap-4"
                    action="/dashboard/exam/add"
                    method="POST"
                >
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Title</legend>
                        <input
                            type="text"
                            class="input"
                            name="title"
                            placeholder="First semester exam"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Subtitle</legend>
                        <input
                            type="text"
                            class="input"
                            name="subtitle"
                            placeholder="2025/2026"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Department</legend>
                        <select class="select" name="department_id">
                            <option value="" disabled selected
                                >Pick a department</option
                            >
                            {#each departments as dept}
                                <option value={dept.id}>{dept.name}</option>
                            {/each}
                        </select>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Set</legend>
                        <input
                            type="text"
                            class="input"
                            name="set"
                            placeholder="2025"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Start Date</legend>
                        <input type="date" class="input" name="start_date" />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">End Date</legend>
                        <input type="date" class="input" name="end_date" />
                    </fieldset>
                    <div class="col-span-2 w-full flex justify-center">
                        <div class="max-w-sm w-full">
                            <button class="btn btn-block btn-primary"
                                >Add Exam</button
                            >
                        </div>
                    </div>
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
