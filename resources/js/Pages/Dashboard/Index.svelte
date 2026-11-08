<script lang="ts">
    import { Link } from "@inertiajs/svelte";
    import DashboardLayout from "../../Components/Layouts/DashboardLayout.svelte";
    import { Users, FileQuestion, Activity, CalendarDays, Plus, GraduationCap, ArrowUpRight, ArrowRight, BookOpen, Printer, ClipboardList, Clock3 } from "lucide-svelte";

    let { totalStudents, activeExams, questionsBank, departments, upcomingExams = [] } = $props();
    const number = new Intl.NumberFormat("en-GB");
    const date = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "Africa/Lagos" });
    const time = new Intl.DateTimeFormat("en-GB", { hour: "2-digit", minute: "2-digit", timeZone: "Africa/Lagos" });
    const scheduledExams = $derived([...upcomingExams].sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime()));
    const stats = $derived([
        { title: "Registered students", value: totalStudents, icon: Users, note: "Across all departments", href: "/dashboard/student" },
        { title: "Active examinations", value: activeExams, icon: Activity, note: "Currently marked active", href: "/dashboard/exam" },
        { title: "Question bank", value: questionsBank, icon: FileQuestion, note: "Questions across all papers", href: "/dashboard/exam" },
        { title: "Departments", value: departments, icon: GraduationCap, note: "Academic departments", href: "/dashboard/department" },
    ]);
    const shortcuts = [
        { title: "Student records", description: "Browse and manage registered students", href: "/dashboard/student", icon: Users },
        { title: "Manage examinations", description: "Set up papers and review exam details", href: "/dashboard/exam", icon: ClipboardList },
        { title: "Print results", description: "Prepare student result slips", href: "/dashboard/print/result", icon: Printer },
    ];
</script>

<svelte:head><title>Overview · Ozoveheni</title></svelte:head>
<DashboardLayout title="Overview" description="An overview of your academic records and examination schedule.">
    {#snippet actions()}
        <Link href="/dashboard/exam/add" class="btn btn-primary gap-2 rounded-lg text-sm font-medium"><Plus size={17} /> Create examination</Link>
    {/snippet}

    <div class="flex flex-col gap-7">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {#each stats as stat}
                <Link href={stat.href} class="card card-border group bg-base-100 transition-colors hover:border-base-content/25">
                    <div class="card-body gap-4 p-5 lg:p-6">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-medium text-base-content/60 sm:text-sm">{stat.title}</span>
                            <stat.icon size={18} strokeWidth={1.6} class="shrink-0 text-base-content/40" />
                        </div>
                        <div class="text-3xl font-semibold tracking-tight tabular-nums">{number.format(stat.value ?? 0)}</div>
                        <div class="flex items-center justify-between text-xs text-base-content/45">
                            <span>{stat.note}</span><ArrowUpRight size={14} class="transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                        </div>
                    </div>
                </Link>
            {/each}
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_19rem]">
            <section class="card card-border min-w-0 overflow-hidden bg-base-100" aria-labelledby="schedule-heading">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-base-300 px-5 py-5 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg border border-base-300 p-2.5 text-base-content/60"><CalendarDays size={19} strokeWidth={1.6} /></div>
                        <div><h2 id="schedule-heading" class="text-base font-semibold">Upcoming examinations</h2><p class="mt-1 text-xs text-base-content/50">Your schedule for the next 7 days</p></div>
                    </div>
                    <Link href="/dashboard/exam" class="btn btn-ghost btn-sm gap-1.5 font-medium">View all <ArrowRight size={14} /></Link>
                </div>
                {#if scheduledExams.length}
                    <div class="overflow-x-auto">
                        <table class="table min-w-145">
                            <thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/50">
                                <tr><th class="px-6 py-3 font-medium">Course / paper</th><th class="py-3 font-medium">Schedule</th><th class="py-3 font-medium">Department</th><th><span class="sr-only">View examination</span></th></tr>
                            </thead>
                            <tbody>
                                {#each scheduledExams.slice(0, 6) as exam (exam.id)}
                                    <tr class="border-base-300/70 hover:bg-base-200/40">
                                        <td class="px-6 py-5"><div class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-base-content/45">{exam.course.code}</div><div class="max-w-65 whitespace-normal text-sm font-medium leading-5">{exam.course.name}</div></td>
                                        <td class="py-5"><div class="whitespace-nowrap text-sm">{date.format(new Date(exam.start))}</div><div class="mt-1 flex items-center gap-1.5 text-xs text-base-content/50"><Clock3 size={12} />{time.format(new Date(exam.start))} WAT</div></td>
                                        <td class="py-5"><div class="max-w-45 whitespace-normal text-xs leading-5 text-base-content/65">{exam.exam.department.name}</div></td>
                                        <td class="pr-5"><Link href={`/dashboard/exam/${exam.exam.uuid}/view`} class="btn btn-square btn-ghost btn-sm text-base-content/50" aria-label={`View ${exam.course.name} examination`}><ArrowUpRight size={17} /></Link></td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 border-t border-base-300 px-6 py-3 text-xs text-base-content/45"><span>Showing {Math.min(scheduledExams.length, 6)} of {scheduledExams.length} scheduled papers</span><span>All times in WAT</span></div>
                {:else}
                    <div class="flex flex-col items-center px-6 py-16 text-center">
                        <div class="mb-4 rounded-2xl bg-base-200 p-4 text-base-content/40"><CalendarDays size={28} strokeWidth={1.4} /></div>
                        <h3 class="text-sm font-semibold">A clear schedule ahead</h3>
                        <p class="mt-2 max-w-70 text-sm leading-6 text-base-content/50">No examinations are scheduled for the next 7 days. New papers will appear here once scheduled.</p>
                        <Link href="/dashboard/exam" class="btn btn-sm mt-5 gap-2">Manage examinations <ArrowRight size={14} /></Link>
                    </div>
                {/if}
            </section>

            <div class="flex flex-col gap-5">
                <section class="card card-border bg-base-100" aria-labelledby="quick-actions-heading">
                    <div class="card-body gap-1 p-5">
                        <h2 id="quick-actions-heading" class="mb-3 text-sm font-semibold">Quick access</h2>
                        {#each shortcuts as shortcut}
                            <Link href={shortcut.href} class="group -mx-2 flex items-start gap-3 rounded-lg p-3 transition-colors hover:bg-base-200">
                                <div class="rounded-lg border border-base-300 p-2 text-base-content/55"><shortcut.icon size={17} strokeWidth={1.6} /></div>
                                <div class="grow"><p class="text-sm font-medium">{shortcut.title}</p><p class="mt-1 text-xs leading-5 text-base-content/50">{shortcut.description}</p></div>
                                <ArrowUpRight size={14} class="mt-1 shrink-0 text-base-content/35" />
                            </Link>
                        {/each}
                    </div>
                </section>
                <section class="rounded-xl border border-base-300 bg-base-200 p-5">
                    <BookOpen size={20} strokeWidth={1.5} class="mb-3 text-base-content/50" />
                    <h2 class="text-sm font-medium">Preparing the next paper?</h2>
                    <p class="mt-2 text-xs leading-6 text-base-content/55">Review the course, timetable and question allocation before students begin their examination.</p>
                    <Link href="/dashboard/exam" class="mt-4 inline-flex items-center gap-2 text-xs font-semibold hover:underline">Go to examinations <ArrowRight size={13} /></Link>
                </section>
            </div>
        </div>
    </div>
</DashboardLayout>
