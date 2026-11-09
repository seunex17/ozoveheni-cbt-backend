<script lang="ts">
    import { Form, Link } from "@inertiajs/svelte";
    import { BookOpen, Save } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    let { course, errors = {} } = $props();
</script>
<svelte:head><title>Edit course · Ozoveheni</title></svelte:head>
<DashboardLayout title="Edit course" description="Manage the course details used in examination setup.">
    {#snippet actions()}<Link href="/dashboard/course" class="btn btn-ghost btn-sm">Back to courses</Link>{/snippet}
    <div class="card card-border max-w-2xl bg-base-100"><div class="card-body gap-6 p-5 sm:p-7">
        <div class="flex items-center gap-3"><BookOpen size={22} class="text-base-content/60" /><h2 class="text-base font-semibold">Course details</h2></div>
        <Form method="post" action={`/dashboard/course/${course.uuid}/edit`} disableWhileProcessing class="flex flex-col gap-5">
            <div><label for="course-name" class="mb-2 block text-sm font-medium">Course name</label><input id="course-name" name="name" type="text" required maxlength="255" placeholder="e.g. Human Anatomy" value={course.name ?? ""} class="input w-full rounded-lg border-base-300" aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? "name-error" : undefined} />{#if errors.name}<p id="name-error" class="mt-2 text-sm text-error" role="alert">{errors.name}</p>{/if}</div>
            <div><label for="course-code" class="mb-2 block text-sm font-medium">Course code</label><input id="course-code" name="code" type="text" required maxlength="10" placeholder="e.g. ANA101" value={course.code ?? ""} class="input w-full rounded-lg border-base-300" aria-invalid={Boolean(errors.code)} aria-describedby={errors.code ? "code-error" : undefined} />{#if errors.code}<p id="code-error" class="mt-2 text-sm text-error" role="alert">{errors.code}</p>{/if}</div>
            <div><label for="course-unit" class="mb-2 block text-sm font-medium">Credit units (optional)</label><input id="course-unit" name="unit" type="number" min="0" max="2147483647" step="1" placeholder="e.g. 3" value={course.unit ?? ""} class="input w-full rounded-lg border-base-300" aria-invalid={Boolean(errors.unit)} aria-describedby={errors.unit ? "unit-error" : undefined} />{#if errors.unit}<p id="unit-error" class="mt-2 text-sm text-error" role="alert">{errors.unit}</p>{/if}</div>
            <div class="flex flex-wrap justify-end gap-3 border-t border-base-300 pt-5"><Link href="/dashboard/course" class="btn">Cancel</Link><button type="submit" class="btn btn-primary gap-2"><Save size={16} /> Save changes</button></div>
        </Form>
    </div></div>
</DashboardLayout>
