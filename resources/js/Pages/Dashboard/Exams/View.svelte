<script lang="ts">
    import { InfiniteScroll, Link } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Plus, FileText, Sheet, ArrowRight, RotateCcw, ClipboardCheck } from "lucide-svelte";
    let { exam, singleExams } = $props();
    const dateTime = new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short" });
    const formatDate = (value: string) => value && !Number.isNaN(Date.parse(value)) ? dateTime.format(new Date(value)) : "Not scheduled";
</script>
<svelte:head><title>Course papers · {exam.title}</title></svelte:head>
<DashboardLayout title={exam.title} description={`Course papers · ${exam.set} · ${exam.level?.toUpperCase() ?? ""}`}>
    {#snippet actions()}
        <Link href="/dashboard/exam" class="btn btn-ghost btn-sm">All examinations</Link>
        <Link href={`/dashboard/exam/${exam.uuid}/readiness`} class="btn gap-2"><ClipboardCheck size={16} /> Readiness check</Link>
        <Link href={`/dashboard/attempts?exam=${exam.uuid}`} class="btn gap-2"><RotateCcw size={16} /> Attempts</Link>
        <Link href={`/dashboard/exam/${exam.uuid}/reports`} class="btn gap-2"><FileText size={16} /> Reports</Link>
        <Link href={`/dashboard/exam/${exam.uuid}/broadsheet`} class="btn gap-2"><Sheet size={16} /> Broadsheet</Link>
        <Link href={`/dashboard/exam/${exam.uuid}/set-new-exam`} class="btn btn-primary gap-2"><Plus size={16} /> Add course paper</Link>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="card card-border bg-base-100"><div class="card-body p-5 sm:p-6"><h2 class="text-sm font-semibold">Admission window and student timer</h2><p class="text-sm leading-6 text-base-content/65">Students may start within a paper's admission window. Each student receives the configured duration from their own start time, even if their timer runs past the admission closing time.</p></div></div>
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="papers-heading">
            <div class="border-b border-base-300 px-5 py-5 sm:px-6"><h2 id="papers-heading" class="text-base font-semibold">Course papers</h2><p class="mt-1 text-xs text-base-content/60">{singleExams.total} {singleExams.total === 1 ? "paper" : "papers"} in this examination</p></div>
            {#if singleExams.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table accepts focus for keyboard navigation.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Course papers, scroll horizontally if needed"><InfiniteScroll data="singleExams"><table class="table min-w-190"><caption class="sr-only">Course papers and their admission windows</caption><thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6">Course</th><th scope="col">Student duration</th><th scope="col">Admission opens</th><th scope="col">Admission closes</th><th scope="col" class="pr-6 text-right">Questions</th></tr></thead><tbody>
                    {#each singleExams.data as paper (paper.id)}<tr class="hover:bg-base-200/40"><th scope="row" class="px-6 py-5 font-normal"><p class="max-w-60 whitespace-normal text-sm font-medium">{paper.course.name}</p><p class="mt-1 text-xs text-base-content/60">{paper.course.code}</p></th><td class="text-sm tabular-nums">{paper.duration} min</td><td class="text-xs text-base-content/70">{formatDate(paper.start)}</td><td class="text-xs text-base-content/70">{formatDate(paper.end)}</td><td class="pr-6 text-right"><Link href={`/dashboard/exam/${paper.uuid}/single-exam`} class="btn btn-ghost btn-sm gap-2" aria-label={`Manage questions for ${paper.course.name}`}>Manage questions <ArrowRight size={14} /></Link></td></tr>{/each}
                </tbody></table></InfiniteScroll></div>
            {:else}<div class="px-6 py-14 text-center"><h3 class="text-sm font-semibold">No course papers yet</h3><p class="mt-2 text-sm text-base-content/60">Add a course, its admission window and the time each student receives.</p><Link href={`/dashboard/exam/${exam.uuid}/set-new-exam`} class="btn btn-sm mt-5">Add course paper</Link></div>{/if}
        </section>
    </div>
</DashboardLayout>
