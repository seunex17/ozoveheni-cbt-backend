<script lang="ts">
    import { Link } from "@inertiajs/svelte";
    import { ExternalLink, FileText } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    let { exam } = $props();
    const pdfUrl = $derived(`/reports/${exam.uuid}`);
</script>
<svelte:head><title>Broadsheet · {exam.title}</title></svelte:head>
<DashboardLayout title="Examination broadsheet" description={exam.title}>
    {#snippet actions()}<Link href={`/dashboard/exam/${exam.uuid}/view`} class="btn btn-ghost btn-sm">Back to course papers</Link><a href={pdfUrl} target="_blank" rel="noopener noreferrer" class="btn gap-2"><ExternalLink size={16} /> Open PDF</a>{/snippet}
    <section class="card card-border overflow-hidden bg-base-100"><div class="flex items-center gap-2 border-b border-base-300 px-5 py-5"><FileText size={18} class="text-base-content/60" /><h2 class="text-sm font-semibold">Broadsheet preview</h2></div><div class="p-3 sm:p-5"><object data={pdfUrl} type="application/pdf" class="h-[70dvh] min-h-100 w-full" aria-label={`PDF broadsheet for ${exam.title}`}><div class="px-6 py-12 text-center"><p class="text-sm text-base-content/65">Open the broadsheet in a PDF viewer to read, print or download it.</p><a href={pdfUrl} target="_blank" rel="noopener noreferrer" class="btn mt-5">Open broadsheet PDF</a></div></object></div></section>
</DashboardLayout>
