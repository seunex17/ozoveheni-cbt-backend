<script lang="ts">
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { hallState, type HallParticipant } from "../../../States/exam-hall.svelte";
    import { Search, Users, Wifi, WifiOff, Clock3, BookOpen, GraduationCap, Radio } from "lucide-svelte";
    import { onMount } from "svelte";

    let search = $state("");
    let filter = $state("all");
    let now = $state(Date.now());
    let failedPhotos = $state<Record<number, boolean>>({});
    onMount(() => {
        const timer = setInterval(() => now = Date.now(), 5000);
        return () => clearInterval(timer);
    });

    function status(row: HallParticipant): string {
        if (!row.last_seen_at) return "untracked";
        return now + hallState.serverOffset - Date.parse(row.last_seen_at) <= hallState.staleAfterSeconds * 1000 ? "online" : "stale";
    }
    const online = $derived(hallState.participants.filter((row) => status(row) === "online").length);
    const stale = $derived(hallState.participants.filter((row) => status(row) === "stale").length);
    const untracked = $derived(hallState.participants.filter((row) => status(row) === "untracked").length);
    const visible = $derived(hallState.participants.filter((row) => {
        const text = `${row.student.name} ${row.student.reg_no} ${row.student.department} ${row.exam.course}`.toLowerCase();
        return text.includes(search.trim().toLowerCase()) && (filter === "all" || status(row) === filter);
    }));

    function lastSeen(value: string | null): string {
        if (!value) return "No heartbeat received";
        const seconds = Math.max(0, Math.floor((now + hallState.serverOffset - Date.parse(value)) / 1000));
        if (seconds < 10) return "Seen just now";
        if (seconds < 60) return `Seen ${seconds}s ago`;
        return `Seen ${Math.floor(seconds / 60)}m ago`;
    }
</script>

<svelte:head><title>Live exam hall · Ozoveheni</title></svelte:head>
<DashboardLayout title="Live exam hall" description="See students in the hall and their last reported question. Submitted attempts leave this list.">
    {#snippet actions()}
        <span class="badge badge-outline gap-2 px-3 py-4 text-xs">
            <Radio size={14} class={hallState.connection === "connected" ? "text-success" : "text-base-content/50"} />
            {hallState.connection === "connected" ? "Live updates connected" : "Reconnecting · 30s fallback refresh"}
        </span>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            {#each [{ title: "In the hall", value: hallState.participants.length, icon: Users }, { title: "Reporting now", value: online, icon: Wifi }, { title: "Connection stale", value: stale, icon: WifiOff }, { title: "Not reporting yet", value: untracked, icon: Clock3 }] as stat}
                <div class="card card-border bg-base-100"><div class="card-body gap-3 p-5"><div class="flex items-center justify-between gap-2 text-xs text-base-content/60"><span>{stat.title}</span><stat.icon size={17} /></div><div class="text-3xl font-semibold tabular-nums">{hallState.loaded ? stat.value : "—"}</div></div></div>
            {/each}
        </div>
        {#if hallState.snapshotError}
            <div role="status" class="rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm">The hall snapshot could not be refreshed. Showing the last received data; connection states may be out of date.</div>
        {/if}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="input w-full rounded-lg border-base-300 bg-base-100 sm:max-w-90"><Search size={17} class="text-base-content/45" /><input bind:value={search} type="search" aria-label="Search students" placeholder="Search name, registration or course…" /></label>
            <div class="flex flex-wrap gap-1" aria-label="Filter connection state">
                {#each [{ value: "all", label: "All students" }, { value: "online", label: "Reporting" }, { value: "stale", label: "Stale" }, { value: "untracked", label: "Not reporting" }] as option}
                    <button class="btn btn-sm {filter === option.value ? 'btn-active' : 'btn-ghost'}" aria-pressed={filter === option.value} onclick={() => filter = option.value}>{option.label}</button>
                {/each}
            </div>
        </div>
        <p class="text-xs leading-5 text-base-content/50">A heartbeat within {hallState.staleAfterSeconds} seconds means the client is reporting. A stale connection does not prove the student left. Question position is reported by the exam software, not inferred from saved answers.</p>
        {#if !hallState.loaded}
            <div class="card card-border bg-base-100 p-10 text-center text-sm text-base-content/60" role="status">Loading the exam hall…</div>
        {:else if visible.length === 0}
            <div class="card card-border items-center bg-base-100 px-6 py-16 text-center"><GraduationCap size={32} strokeWidth={1.4} class="mb-4 text-base-content/40" /><h2 class="font-semibold">{hallState.participants.length ? "No matching students" : "The hall is empty"}</h2><p class="mt-2 max-w-85 text-sm leading-6 text-base-content/55">{hallState.participants.length ? "Try another name, registration number or connection filter." : "Students will appear here after they log in to an examination."}</p></div>
        {:else}
            <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                {#each visible as row (row.attempt_id)}
                    {@const connectionStatus = status(row)}
                    <article class="card card-border bg-base-100">
                        <div class="card-body gap-4 p-5">
                            <div class="flex items-start gap-3">
                                <div class="avatar avatar-placeholder shrink-0"><div class="h-16 w-16 rounded-xl bg-base-200 text-base-content/50">
                                    {#if row.student.photo_url && !failedPhotos[row.attempt_id]}
                                        <img src={row.student.photo_url} alt={row.student.name} loading="lazy" class="object-cover" onerror={() => failedPhotos[row.attempt_id] = true} />
                                    {:else}<span class="text-xl font-medium" aria-label="No profile photo">{row.student.name.split(" ").filter(Boolean).slice(0, 2).map((name) => name[0]).join("")}</span>{/if}
                                </div></div>
                                <div class="min-w-0 grow"><h2 class="text-sm font-semibold leading-5">{row.student.name}</h2><p class="mt-1 break-all text-xs text-base-content/55">{row.student.reg_no}</p><p class="mt-1 text-xs leading-5 text-base-content/45">{row.student.department}</p></div>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-t border-base-300 pt-4">
                                <span class="badge badge-sm badge-soft {connectionStatus === 'online' ? 'badge-success' : connectionStatus === 'stale' ? 'badge-warning' : 'badge-ghost'}">{connectionStatus === "online" ? "Reporting" : connectionStatus === "stale" ? "Connection stale" : "Not reporting yet"}</span>
                                <span class="text-[11px] text-base-content/45">{lastSeen(row.last_seen_at)}</span>
                            </div>
                            <div class="rounded-lg bg-base-200/70 p-3.5">
                                <div class="flex items-center gap-2 text-xs text-base-content/55"><BookOpen size={14} /><span>{row.exam.code} · {row.exam.course}</span></div>
                                <div class="mt-3 flex items-baseline gap-1.5">
                                    {#if row.current_question_number !== null}<span class="text-sm text-base-content/60">Question</span><span class="text-2xl font-semibold tabular-nums">{row.current_question_number}</span><span class="text-xs text-base-content/45">of {row.total_questions}</span>
                                    {:else}<span class="text-sm text-base-content/55">Question position not reported</span>{/if}
                                </div>
                            </div>
                        </div>
                    </article>
                {/each}
            </div>
        {/if}
    </div>
</DashboardLayout>
