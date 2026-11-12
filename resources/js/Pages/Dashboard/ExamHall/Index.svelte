<script lang="ts">
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { hallState, refreshExamHall } from "../../../States/exam-hall.svelte";
    import { Search, Users, CheckCircle2, GraduationCap, Radio, RefreshCw, PenLine } from "lucide-svelte";

    let search = $state("");
    let filter = $state("all");
    let failedPhotos = $state<Record<number, string>>({});
    const ongoing = $derived(hallState.participants.filter((row) => row.status === "ongoing").length);
    const submitted = $derived(hallState.participants.filter((row) => row.status === "submitted").length);
    const visible = $derived(hallState.participants.filter((row) => {
        const text = `${row.student.name} ${row.student.reg_no}`.toLowerCase();
        return text.includes(search.trim().toLowerCase()) && (filter === "all" || row.status === filter);
    }));
</script>

<svelte:head><title>Live exam hall · Ozoveheni</title></svelte:head>
<DashboardLayout title="Live exam hall" description="Student examination status. Submitted students remain visible until the hall is swept.">
    {#snippet actions()}
        <div class="flex flex-wrap items-center gap-3">
            <span class="badge badge-outline gap-2 px-3 py-4 text-xs">
                <Radio size={14} class={hallState.connection === "connected" ? "text-success" : "text-base-content/50"} />
                {hallState.connection === "connected" ? "Live updates connected" : "Live updates disconnected"}
            </span>
            <button class="btn btn-sm gap-2" disabled={hallState.refreshing} onclick={() => refreshExamHall()}><RefreshCw size={14} />{hallState.refreshing ? "Refreshing…" : "Refresh"}</button>
        </div>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {#each [{ title: "All students", value: hallState.participants.length, icon: Users }, { title: "Ongoing", value: ongoing, icon: PenLine }, { title: "Submitted", value: submitted, icon: CheckCircle2 }] as stat}
                <div class="card card-border bg-base-100"><div class="card-body gap-3 p-5"><div class="flex items-center justify-between gap-2 text-xs text-base-content/60"><span>{stat.title}</span><stat.icon size={17} /></div><div class="text-3xl font-semibold tabular-nums">{hallState.loaded ? stat.value : "—"}</div></div></div>
            {/each}
        </div>
        {#if hallState.snapshotError}
            <div role="status" class="rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm">The hall could not be refreshed. Showing the last received data. Use Refresh to try again.</div>
        {/if}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="input w-full rounded-lg border-base-300 bg-base-100 sm:max-w-90"><Search size={17} class="text-base-content/45" /><input bind:value={search} type="search" aria-label="Search students" placeholder="Search name or registration number…" /></label>
            <div class="flex flex-wrap gap-1" aria-label="Filter examination status">
                {#each [{ value: "all", label: "All students" }, { value: "ongoing", label: "Ongoing" }, { value: "submitted", label: "Submitted" }] as option}
                    <button class="btn btn-sm {filter === option.value ? 'btn-active' : 'btn-ghost'}" aria-pressed={filter === option.value} onclick={() => filter = option.value}>{option.label}</button>
                {/each}
            </div>
        </div>
        <p class="text-xs leading-5 text-base-content/50">Ongoing means the attempt has not been submitted. It does not indicate whether the student's device is connected.</p>
        {#if !hallState.loaded}
            <div class="card card-border bg-base-100 p-10 text-center text-sm text-base-content/60" role="status">{hallState.snapshotError ? "Use Refresh to load the hall." : "Loading the exam hall…"}</div>
        {:else if visible.length === 0}
            <div class="card card-border items-center bg-base-100 px-6 py-16 text-center"><GraduationCap size={32} strokeWidth={1.4} class="mb-4 text-base-content/40" /><h2 class="font-semibold">{hallState.participants.length ? "No matching students" : "The hall is empty"}</h2><p class="mt-2 max-w-85 text-sm leading-6 text-base-content/55">{hallState.participants.length ? "Try another name, registration number or status filter." : "Students will appear here after they log in to an examination."}</p></div>
        {:else}
            <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                {#each visible as row (row.attempt_id)}
                    <article class="card card-border bg-base-100">
                        <div class="card-body gap-4 p-5">
                            <div class="flex items-center gap-4">
                                <div class="avatar avatar-placeholder shrink-0"><div class="h-20 w-20 rounded-xl bg-base-200 text-base-content/50">
                                    {#if row.student.photo_url && failedPhotos[row.attempt_id] !== row.student.photo_url}
                                        <img src={row.student.photo_url} alt={row.student.name} loading="lazy" class="object-cover" onerror={() => failedPhotos[row.attempt_id] = row.student.photo_url!} />
                                    {:else}<span class="text-xl font-medium" aria-label="No passport photo">{row.student.name.split(" ").filter(Boolean).slice(0, 2).map((name) => name[0]).join("")}</span>{/if}
                                </div></div>
                                <div class="min-w-0 grow"><h2 class="text-sm font-semibold leading-5">{row.student.name}</h2><p class="mt-1 break-all text-xs text-base-content/55">{row.student.reg_no}</p>
                                    <span class="badge badge-sm badge-soft mt-3 {row.status === 'submitted' ? 'badge-success' : 'badge-info'}">{row.status === "submitted" ? "Submitted" : "Ongoing"}</span>
                                </div>
                            </div>
                        </div>
                    </article>
                {/each}
            </div>
        {/if}
    </div>
</DashboardLayout>
