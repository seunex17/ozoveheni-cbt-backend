<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Save } from "lucide-svelte";

    let { exam, courses } = $props();
</script>

<DashboardLayout title="Set Exam ({exam.title})" goBack={true}>
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/exam/set-new-exam"
                    disableWhileProcessing
                >
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Course</legend>
                        <select class="select" name="course_id">
                            <option value="" disabled selected
                                >Pick a course</option
                            >
                            {#each courses as course}
                                <option value={course.id}>{course.name}</option>
                            {/each}
                        </select>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Schedule Date</legend>
                        <input type="date" class="input" name="schedule_date" />
                    </fieldset>
                    <div class="grid grid-cols-2 gap-2">
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Start Time</legend>
                            <input
                                type="time"
                                class="input"
                                name="start_time"
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">End Time</legend>
                            <input type="time" class="input" name="end_time" />
                        </fieldset>
                    </div>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend"
                            >Duration (Minutes)</legend
                        >
                        <input type="number" class="input" name="duration" />
                    </fieldset>
                    <button class="btn btn-block btn-primary">
                        <Save size="16" /> Save</button
                    >
                    <input type="hidden" name="exam_id" value={exam.id} />
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
