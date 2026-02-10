<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { departments } = $props();

    const startYear = 2024;
    const currentYear = new Date().getFullYear();
    const years = Array.from(
        { length: currentYear - startYear + 1 },
        (_, i) => startYear + i,
    ).reverse();
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
                        <legend class="fieldset-legend">Semester</legend>
                        <input
                            type="text"
                            class="input"
                            name="title"
                            placeholder="First semester exam"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Session</legend>
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
                        <select class="select" name="set">
                            <option value="" disabled selected
                                >Pick a set</option
                            >
                            {#each years as year}
                                <option value={`${year}/${year + 1}`}
                                    >{`${year}/${year + 1}`}</option
                                >
                            {/each}
                        </select>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Start Date</legend>
                        <input type="date" class="input" name="start_date" />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">End Date</legend>
                        <input type="date" class="input" name="end_date" />
                    </fieldset>
                    <fieldset class="fieldset col-span-2 w-full">
                        <legend class="fieldset-legend">Level</legend>
                        <select class="select w-full" name="level">
                            <option value="" disabled selected
                                >Pick a level</option
                            >
                            <option value="nd1">ND1</option>
                            <option value="nd2">ND2</option>
                            <option value="hnd1">HND1</option>
                            <option value="hnd2">HND2</option>
                        </select>
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
