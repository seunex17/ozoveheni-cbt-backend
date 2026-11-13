<script lang="ts">
    import { Link, router } from "@inertiajs/svelte";
    import { ClipboardCheck, CheckCircle2, AlertTriangle, CircleAlert, RefreshCw, ArrowLeft, ArrowUpRight, Users, BookOpen } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { exam, checks, summary, checkedAt } = $props();
    let refreshing = $state(false);
    let filter = $state("attention");
    const titles: Record<string, string> = { not_ready: "Fix these issues before exam day", review: "Ready for a final review", ready: "Exam setup is ready" };
    const descriptions: Record<string, string> = {
        not_ready: "Some setup checks need attention. Follow the links below, make your changes, then run the check again.",
        review: "The essential setup checks passed. Review the warnings below before students sign in.",
        ready: "All setup checks passed for the current exam and student records.",
    };
    const order: Record<string, number> = { blocker: 0, warning: 1, passed: 2 };
    const visible = $derived(checks.filter((check: any) => filter === "all" || check.status !== "passed").toSorted((a: any, b: any) => order[a.status] - order[b.status]));
    const checkedTime = $derived(new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short", timeZone: "Africa/Lagos" }).format(new Date(checkedAt)));
    function refresh() {
        if (refreshing) return;
        refreshing = true;
        router.reload({ only: ["exam", "checks", "summary", "checkedAt"], onFinish: () => refreshing = false });
    }
</script>

<svelte:head><title>Exam readiness · {exam.title}</title></svelte:head>
<DashboardLayout title="Exam readiness" description={`${exam.title} · ${exam.department} · ${exam.set} · ${exam.level_name}`}>
    {#snippet actions()}
        <Link href={`/dashboard/exam/${exam.uuid}/view`} class="btn btn-ghost gap-2"><ArrowLeft size={16} /> Course papers</Link>
        <button class="btn gap-2" onclick={refresh} disabled={refreshing}><RefreshCw size={16} />{refreshing ? "Checking…" : "Run check again"}</button>
    {/snippet}
    <div class="flex flex-col gap-6" aria-busy={refreshing}>
        <section class="card card-border bg-base-100" aria-labelledby="readiness-summary">
            <div class="card-body gap-5 p-5 sm:p-7">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl {summary.status === 'not_ready' ? 'bg-error/10 text-error' : summary.status === 'review' ? 'bg-warning/10 text-warning' : 'bg-success/10 text-success'}"><ClipboardCheck size={25} strokeWidth={1.6} /></div>
                    <div class="grow"><div class="flex flex-wrap items-center gap-3"><h2 id="readiness-summary" class="text-lg font-semibold sm:text-xl">{titles[summary.status]}</h2><span class="badge badge-soft badge-sm {summary.status === 'not_ready' ? 'badge-error' : summary.status === 'review' ? 'badge-warning' : 'badge-success'}">{summary.status === "not_ready" ? "Needs attention" : summary.status === "review" ? "Review warnings" : "Ready"}</span></div><p class="mt-2 max-w-2xl text-sm leading-6 text-base-content/60">{descriptions[summary.status]}</p></div>
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-base-300 pt-5 text-xs text-base-content/65"><span class="flex items-center gap-2"><BookOpen size={15} />{summary.papers} course papers</span><span class="flex items-center gap-2"><Users size={15} />{summary.students} eligible student records</span><span>Checked {checkedTime} · Lagos time</span></div>
            </div>
        </section>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {#each [{ label: "Checks needing fixes", value: summary.blockers, icon: CircleAlert, colour: "text-error" }, { label: "Warnings to review", value: summary.warnings, icon: AlertTriangle, colour: "text-warning" }, { label: "Checks passed", value: summary.passed, icon: CheckCircle2, colour: "text-success" }] as stat}
                <div class="card card-border bg-base-100"><div class="card-body gap-3 p-5"><div class="flex items-center justify-between gap-3 text-xs text-base-content/60"><span>{stat.label}</span><stat.icon size={17} class={stat.colour} /></div><p class="text-3xl font-semibold tabular-nums">{stat.value}</p></div></div>
            {/each}
        </div>
        <section aria-labelledby="checks-heading" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="checks-heading" class="text-base font-semibold">Preparation checklist</h2><div class="flex flex-wrap gap-1" aria-label="Checklist filters">{#each [{ value: "attention", label: "Needs attention" }, { value: "all", label: "All checks" }] as option}<button class="btn btn-sm {filter === option.value ? 'btn-active' : 'btn-ghost'}" aria-pressed={filter === option.value} onclick={() => filter = option.value}>{option.label}</button>{/each}</div></div>
            {#if visible.length === 0}
                <div class="card card-border items-center bg-base-100 px-6 py-12 text-center"><CheckCircle2 size={32} strokeWidth={1.5} class="mb-4 text-success" /><h3 class="text-sm font-semibold">Nothing needs attention</h3><p class="mt-2 text-sm text-base-content/60">Select All checks to see the checks that passed.</p></div>
            {/if}
            {#each visible as check (check.id)}
                <article class="card card-border bg-base-100"><div class="card-body gap-4 p-5 sm:p-6">
                    <div class="flex items-start gap-3">
                        {#if check.status === "blocker"}<CircleAlert size={20} class="mt-0.5 shrink-0 text-error" />{:else if check.status === "warning"}<AlertTriangle size={20} class="mt-0.5 shrink-0 text-warning" />{:else}<CheckCircle2 size={20} class="mt-0.5 shrink-0 text-success" />{/if}
                        <div class="min-w-0 grow"><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-semibold">{check.title}</h3><span class="badge badge-ghost badge-sm">{check.status === "passed" ? "Passed" : `${check.count} ${check.count === 1 ? "issue" : "issues"}`}</span></div><p class="mt-2 text-sm leading-6 text-base-content/60">{check.description}</p></div>
                    </div>
                    {#if check.items.length}
                        <ul class="divide-y divide-base-300 rounded-lg border border-base-300">
                            {#each check.items as item}<li><Link href={item.href} class="flex items-center justify-between gap-4 px-4 py-3 text-sm hover:bg-base-200/50"><span class="min-w-0 break-words leading-6">{item.label}</span><ArrowUpRight size={16} class="shrink-0 text-base-content/50" /><span class="sr-only">Open record</span></Link></li>{/each}
                        </ul>
                        {#if check.count > check.items.length}<p class="text-xs text-base-content/55">Showing the first {check.items.length} of {check.count} affected records. Resolve these and run the check again to see the remaining issues.</p>{/if}
                    {/if}
                    {#if check.status !== "passed" && check.href}<div><Link href={check.href} class="btn btn-sm gap-2">{check.id === "papers" ? "Add course paper" : check.id.startsWith("student") || check.id === "eligible_students" ? "Review students" : "Review setup"}<ArrowUpRight size={14} /></Link></div>{/if}
                </div></article>
            {/each}
        </section>
        <p class="text-xs leading-5 text-base-content/55">This checklist reviews saved setup and student records. It does not open or close exams, change scores, or test the network and student devices. Checks run only when this page is opened or refreshed.</p>
    </div>
</DashboardLayout>
