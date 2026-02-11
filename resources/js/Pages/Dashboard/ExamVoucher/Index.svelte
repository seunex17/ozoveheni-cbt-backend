<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Printer, Save } from "lucide-svelte";

    let { exams } = $props();
</script>

<DashboardLayout title="Generate EXam Voucher">
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <form
                    class="space-y-3"
                    method="GET"
                    action="/dashboard/voucher/generate"
                >
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Exam</legend>
                        <select class="select" name="exam_id" required>
                            {#each exams as exam}
                                <option value={exam.id}
                                    >{exam.title} ({exam.set})</option
                                >
                            {/each}
                        </select>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Quantity</legend>
                        <input
                            type="number"
                            class="input"
                            name="qty"
                            placeholder="Quantity"
                        />
                    </fieldset>
                    <button class="btn btn-block btn-primary">
                        <Printer size="16" /> Generate</button
                    >
                </form>
            </div>
        </div>
    </div>
</DashboardLayout>
