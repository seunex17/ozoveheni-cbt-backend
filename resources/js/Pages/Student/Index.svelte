<script lang="ts">
    import { router } from "@inertiajs/svelte";
    import DashboardLayout from "./../../Components/Layouts/DashboardLayout.svelte";

    let { departments } = $props();

    let uuid: string = $state("");
    let selectedSet: string = $state("");

    const startYear = 2024;
    const currentYear = new Date().getFullYear();
    const years = Array.from(
        { length: currentYear - startYear + 1 },
        (_, i) => startYear + i,
    ).reverse();

    const submit = () => {
        router.visit(`/dashboard/student/department/${uuid}/${selectedSet}`);
    };
</script>

<DashboardLayout title="Students">
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <div class="space-y-3">
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Department</legend>
                        <select class="select" bind:value={uuid}>
                            <option value="" disabled selected
                                >Pick a department</option
                            >
                            {#each departments as dept}
                                <option value={dept.uuid}>{dept.name}</option>
                            {/each}
                        </select>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Set</legend>
                        <select class="select" bind:value={selectedSet}>
                            <option value="" disabled selected
                                >Pick a set</option
                            >
                            {#each years as year}
                                <option value={year}>{year}</option>
                            {/each}
                        </select>
                    </fieldset>
                    <button
                        onclick={submit}
                        class="btn btn-block btn-primary"
                        disabled={selectedSet === "" || uuid === ""}
                    >
                        Submit</button
                    >
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>
