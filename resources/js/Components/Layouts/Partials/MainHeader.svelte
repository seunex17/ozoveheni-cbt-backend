<script lang="ts">
    import { appState } from "./../../../States/app-state.svelte";
    import { Link, router } from "@inertiajs/svelte";
    import { House, Power } from "lucide-svelte";
    import { onDestroy, onMount } from "svelte";
    import NumberFlow from "@number-flow/svelte";

    onMount(() => {
        const channel = window.Echo.channel("exam-hall").listen(
            "ExamHallEvent",
            (e) => {
                appState.totalHall = e.totalStudent;
            },
        );
    });

    onDestroy(() => {
        window.Echo.leaveChannel("exam-hall");
    });

    const sweepExamHall = () => {
        router.post(
            "/dashboard/sweep-exam-hall",
            {},
            {
                preserveScroll: true,
            },
        );
    };
</script>

<div class="sticky top-0 shadow-sm navbar bg-base-100">
    <div class="flex-1">
        <Link href="/dashboard" class="text-xl btn btn-ghost">
            <img src="/images/logo.jpg" class="w-10" alt="" />
        </Link>
    </div>
    <div class="flex gap-3">
        <button
            class="btn btn-primary btn-soft"
            popovertarget="exam-hall"
            style="anchor-name:--exam-hall"
        >
            <House size="18" /> Exam Hall
            <span class="badge badge-primary badge-xs">
                <NumberFlow value={appState.totalHall} />
            </span>
        </button>
        <ul
            class="dropdown menu w-52 rounded-box bg-base-100 shadow-sm"
            popover
            id="exam-hall"
            style="position-anchor:--exam-hall"
        >
            <li><button onclick={sweepExamHall}>Sweep Hall</button></li>
        </ul>
        <div class="flex-none">
            <button
                onclick={() => router.post("/logout")}
                class="btn btn-error btn-soft"
            >
                <Power size="18" /> Logout
            </button>
        </div>
    </div>
</div>
