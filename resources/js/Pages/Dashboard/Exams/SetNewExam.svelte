<script lang="ts">
    import { Form, Link } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Save } from "lucide-svelte";
    let { exam, courses, errors = {} } = $props();
</script>
<svelte:head><title>Add course paper · {exam.title}</title></svelte:head>
<DashboardLayout title="Add course paper" description={exam.title}>
    {#snippet actions()}<Link href={`/dashboard/exam/${exam.uuid}/view`} class="btn btn-ghost btn-sm">Back to course papers</Link>{/snippet}
    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <section class="card card-border bg-base-100"><div class="card-body gap-6 p-5 sm:p-7"><h2 class="text-base font-semibold">Paper schedule</h2>
            {#if !courses.length}<p class="text-sm text-base-content/65">Create a course before scheduling a paper. <Link href="/dashboard/course" class="underline">Manage courses</Link></p>{/if}
            <Form action="/dashboard/exam/set-new-exam" method="post" disableWhileProcessing class="flex flex-col gap-5">
                {#each Object.values(errors) as error}<p class="text-sm text-error" role="alert">{error}</p>{/each}
                <div><label for="paper-course" class="mb-2 block text-sm font-medium">Course</label><select id="paper-course" class="select w-full" name="course_id" required><option value="">Choose course</option>{#each courses as course}<option value={course.id}>{course.code} — {course.name}</option>{/each}</select></div>
                <div><label for="paper-date" class="mb-2 block text-sm font-medium">Exam date</label><input id="paper-date" type="date" class="input w-full" name="schedule_date" required /></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="paper-start" class="mb-2 block text-sm font-medium">Admission opens</label><input id="paper-start" type="time" class="input w-full" name="start_time" required /></div><div><label for="paper-end" class="mb-2 block text-sm font-medium">Admission closes</label><input id="paper-end" type="time" class="input w-full" name="end_time" required /></div></div>
                <div><label for="paper-duration" class="mb-2 block text-sm font-medium">Time per student (minutes)</label><input id="paper-duration" type="number" class="input w-full" name="duration" min="1" step="1" required placeholder="e.g. 40" aria-describedby="duration-help" /><p id="duration-help" class="mt-2 text-xs leading-5 text-base-content/65">Each student's timer starts when they begin the paper.</p></div>
                <input type="hidden" name="exam_id" value={exam.id} />
                <div class="flex flex-wrap justify-end gap-3 border-t border-base-300 pt-5"><Link href={`/dashboard/exam/${exam.uuid}/view`} class="btn">Cancel</Link><button type="submit" class="btn btn-primary gap-2" disabled={!courses.length}><Save size={16} /> Save course paper</button></div>
            </Form>
        </div></section>
        <aside class="card card-border bg-base-100"><div class="card-body p-5 sm:p-6"><h2 class="text-sm font-semibold">Example: 8 am to 12 pm, 40 minutes</h2><p class="text-sm leading-6 text-base-content/65">Students enter in sets during the admission window. A student who starts at 11:50 am gets their full 40 minutes, until 12:30 pm.</p><p class="text-sm leading-6 text-base-content/65">When their timer expires, answers freeze. The client has 30 seconds to deliver the final answers.</p></div></aside>
    </div>
</DashboardLayout>
