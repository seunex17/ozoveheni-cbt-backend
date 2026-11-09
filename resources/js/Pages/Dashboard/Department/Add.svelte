<script lang="ts">
    import { Form, Link } from "@inertiajs/svelte";
    import { FolderOpen, Save } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    let { errors = {} } = $props();
</script>

<svelte:head><title>Add department · Ozoveheni</title></svelte:head>
<DashboardLayout title="Add department" description="Create an academic department for student and examination records.">
    {#snippet actions()}<Link href="/dashboard/department" class="btn btn-ghost btn-sm">Back to departments</Link>{/snippet}
    <div class="card card-border max-w-2xl bg-base-100">
        <div class="card-body gap-6 p-5 sm:p-7">
            <div class="flex items-center gap-3"><div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><FolderOpen size={21} strokeWidth={1.6} /></div><div><h2 class="text-base font-semibold">Department details</h2><p class="mt-1 text-xs text-base-content/60">Use the full, official department name.</p></div></div>
            <Form action={"/dashboard/department/add"} method="post" disableWhileProcessing class="flex flex-col gap-6">
                <div><label for="department-name" class="mb-2 block text-sm font-medium">Department name <span class="text-xs font-normal text-base-content/60">(required)</span></label><input id="department-name" type="text" name="name"  required class="input w-full rounded-lg border-base-300" placeholder="e.g. Community Health" aria-describedby="name-help" /><p id="name-help" class="mt-2 text-xs leading-5 text-base-content/60">This name appears in student records, filters and exam settings.</p></div>
                {#if errors.name}<p class="text-sm text-error" role="alert">{errors.name}</p>{/if}
                <div class="flex flex-wrap justify-end gap-3 border-t border-base-300 pt-5"><Link href="/dashboard/department" class="btn">Cancel</Link><button type="submit" class="btn btn-primary gap-2"><Save size={16} /> Create department</button></div>
            </Form>
        </div>
    </div>
</DashboardLayout>
