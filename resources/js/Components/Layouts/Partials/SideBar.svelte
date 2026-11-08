<script lang="ts">
    import { Link, page } from "@inertiajs/svelte";
    import { BookA, Folder, GraduationCap, LayoutDashboard, Notebook, DoorOpen, Ticket, Printer, Users, ShieldCheck } from "lucide-svelte";

    let { onNavigate = () => {} } = $props();
    const currentPath = $derived($page.url.split("?")[0]);
    const groups = [
        { label: "Workspace", links: [{ name: "Overview", route: "/dashboard", icon: LayoutDashboard }] },
        { label: "Academic records", links: [
            { name: "Students", route: "/dashboard/student", icon: GraduationCap },
            { name: "Departments", route: "/dashboard/department", icon: Folder },
            { name: "Courses", route: "/dashboard/course", icon: BookA },
            { name: "Staff", route: "/dashboard/staff", icon: Users },
        ] },
        { label: "Examinations", links: [
            { name: "Live exam hall", route: "/dashboard/exam-hall", icon: DoorOpen },
            { name: "Manage exams", route: "/dashboard/exam", icon: Notebook },
            { name: "Exam vouchers", route: "/dashboard/voucher", icon: Ticket },
            { name: "Print results", route: "/dashboard/print/result", icon: Printer },
        ] },
    ];
</script>

<aside class="flex min-h-full w-66 flex-col bg-neutral text-neutral-content">
    <Link href="/dashboard" onclick={onNavigate} class="flex items-center gap-3 border-b border-neutral-content/10 px-6 py-6">
        <div class="avatar"><div class="w-10 rounded-lg bg-base-100 p-1"><img src="/images/logo.jpg" alt="Ozoveheni College crest" /></div></div>
        <div><div class="text-sm font-semibold tracking-widest">OZOVEHENI</div><div class="mt-1 text-xs text-neutral-content/50">Examination portal</div></div>
    </Link>
    <nav aria-label="Main navigation" class="grow px-4 py-6">
        {#each groups as group}
            <div class="mb-6">
                <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-neutral-content/40">{group.label}</p>
                <ul class="menu w-full gap-1 p-0 text-sm">
                    {#each group.links as link}
                        {@const active = link.route === "/dashboard" ? currentPath === link.route : currentPath === link.route || currentPath.startsWith(link.route + "/")}
                        <li>
                            <Link href={link.route} onclick={onNavigate} aria-current={active ? "page" : undefined}
                                class="flex min-h-11 gap-3 rounded-lg px-3 transition-colors {active ? 'bg-neutral-content/12 font-medium text-neutral-content' : 'text-neutral-content/60 hover:bg-neutral-content/5 hover:text-neutral-content'}">
                                <link.icon size={18} strokeWidth={1.7} />
                                <span class="grow">{link.name}</span>
                                {#if active}<span class="h-1.5 w-1.5 rounded-full bg-neutral-content/70"></span>{/if}
                            </Link>
                        </li>
                    {/each}
                </ul>
            </div>
        {/each}
    </nav>
    <div class="mx-5 mb-5 rounded-xl border border-neutral-content/10 p-4">
        <ShieldCheck size={20} class="mb-3 text-neutral-content/60" />
        <p class="text-xs font-medium">Staff workspace</p>
        <p class="mt-1 text-xs leading-5 text-neutral-content/45">Academic records, examinations and results in one place.</p>
    </div>
</aside>
