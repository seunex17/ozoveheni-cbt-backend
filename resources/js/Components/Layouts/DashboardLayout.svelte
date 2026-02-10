<script lang="ts">
    import { ChevronLeft } from "lucide-svelte";
    import MainHeader from "./Partials/MainHeader.svelte";
    import SideBar from "./Partials/SideBar.svelte";

    let {
        children,
        actions = undefined,
        goBack = false,
        title = "Dashboard",
    } = $props();
</script>

<main class="flex flex-col w-full h-screen bg-base-200">
    <MainHeader />
    <div class="flex w-full gap-6 grow overflow-hidden">
        <SideBar />
        <div class="flex flex-col w-full gap-2 mt-3 grow">
            <div
                class="flex items-center justify-between w-full mb-3.5 px-4 py-2"
            >
                <div class="flex items-center grow gap-2">
                    {#if goBack}
                        <button
                            onclick={() => window.history.back()}
                            class="btn btn-square btn-sm"
                        >
                            <ChevronLeft size="20" />
                        </button>
                    {/if}
                    <h4 class="text-xl font-semibold capitalize">{title}</h4>
                </div>
                <div class="">
                    {@render actions?.()}
                </div>
            </div>
            <div class="grow overflow-y-auto">
                {@render children()}
            </div>
        </div>
    </div>
</main>
