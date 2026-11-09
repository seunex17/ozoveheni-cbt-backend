<script lang="ts">
    import { ChevronLeft } from "lucide-svelte";
    import MainHeader from "./Partials/MainHeader.svelte";
    import SideBar from "./Partials/SideBar.svelte";

    let { children, actions = undefined, goBack = false, title = "Dashboard", description = "" } = $props();
    let navigationOpen = $state(false);
</script>

<div class="dashboard-shell drawer h-dvh bg-base-200 text-base-content lg:drawer-open" data-theme="light">
    <input id="dashboard-navigation" type="checkbox" class="drawer-toggle" bind:checked={navigationOpen} />
    <div class="drawer-content flex min-w-0 flex-col overflow-hidden">
        <MainHeader />
        <main id="dashboard-content" class="min-h-0 grow overflow-y-auto" scroll-region>
            <div class="mx-auto flex min-h-full w-full max-w-400 flex-col gap-7 px-4 py-6 sm:px-7 lg:px-9 lg:py-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        {#if goBack}
                            <button onclick={() => window.history.back()} class="btn btn-square btn-sm" aria-label="Go back">
                                <ChevronLeft size={18} />
                            </button>
                        {/if}
                        <div>
                            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h1>
                            {#if description}<p class="mt-2 text-sm leading-6 text-base-content/60">{description}</p>{/if}
                        </div>
                    </div>
                    {#if actions}<div class="flex flex-wrap items-center gap-2">{@render actions()}</div>{/if}
                </div>
                <div class="min-w-0 grow">{@render children()}</div>
                <footer class="flex shrink-0 flex-wrap justify-between gap-2 border-t border-base-300 pt-5 text-xs text-base-content/50">
                    <span>Ozoveheni College of Health Technology</span>
                    <span>Examination administration</span>
                </footer>
            </div>
        </main>
    </div>
    <div class="drawer-side z-40">
        <label for="dashboard-navigation" aria-label="Close navigation" class="drawer-overlay"></label>
        <SideBar onNavigate={() => navigationOpen = false} />
    </div>
</div>
