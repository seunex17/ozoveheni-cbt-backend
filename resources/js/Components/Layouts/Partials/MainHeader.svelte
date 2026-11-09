<script lang="ts">
    import { hallState, startExamHallMonitor } from "./../../../States/exam-hall.svelte";
    import { Link, router } from "@inertiajs/svelte";
    import { ChevronDown, DoorOpen, LogOut, Menu, PanelLeft } from "lucide-svelte";
    import { onMount } from "svelte";
    import NumberFlow from "@number-flow/svelte";

    onMount(startExamHallMonitor);

    const sweepExamHall = () => router.post("/dashboard/sweep-exam-hall", {}, { preserveScroll: true });
    const closeExamHall = () => router.post("/dashboard/close-exam-hall", {}, { preserveScroll: true });
</script>

<header class="navbar min-h-18 shrink-0 gap-3 border-b border-base-300 bg-base-100 px-4 sm:px-7 lg:px-9">
    <label for="dashboard-navigation" class="btn btn-square btn-ghost btn-sm drawer-button lg:hidden" aria-label="Open navigation"><Menu size={20} /></label>
    <div class="flex grow items-center gap-3">
        <PanelLeft size={17} class="hidden text-base-content/40 lg:block" />
        <span class="hidden text-sm text-base-content/50 sm:inline">Administration</span>
        <span class="hidden text-base-content/25 sm:inline">/</span>
        <span class="text-sm font-medium">Staff portal</span>
    </div>
    <details class="dropdown dropdown-end">
        <summary class="btn btn-sm gap-2 border-base-300 bg-base-100 font-medium shadow-none">
            <DoorOpen size={16} /><span class="hidden sm:inline">Exam hall</span>
            <span class="badge badge-sm badge-ghost tabular-nums">{#if hallState.loaded}<NumberFlow value={hallState.participants.filter((row) => row.status === "ongoing").length} />{:else}—{/if}</span>
            <ChevronDown size={13} />
        </summary>
        <ul class="dropdown-content menu z-50 mt-3 w-56 rounded-xl border border-base-300 bg-base-100 p-2 shadow-lg">
            <li class="menu-title text-xs">Exam hall controls</li>
            <li><Link href="/dashboard/exam-hall">View live exam hall</Link></li>
            <li><button onclick={sweepExamHall}>Sweep hall</button></li>
            <li><button onclick={closeExamHall}>Close hall</button></li>
        </ul>
    </details>
    <div class="h-6 w-px bg-base-300"></div>
    <button onclick={() => router.post("/logout")} class="btn btn-ghost btn-sm gap-2 font-normal text-base-content/60" aria-label="Sign out">
        <LogOut size={16} /><span class="hidden sm:inline">Sign out</span>
    </button>
</header>
