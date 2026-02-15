<script lang="ts">
    import { Printer } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Form } from "@inertiajs/svelte";

    let { exams } = $props();
</script>

<DashboardLayout title="Print Result">
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/print/result"
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
                        <legend class="fieldset-legend">Registration No</legend>
                        <input
                            type="text"
                            class="input"
                            name="reg_no"
                            placeholder="Reg No"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Voucher Pin</legend>
                        <input
                            type="text"
                            class="input"
                            name="pin"
                            placeholder="Pin"
                        />
                    </fieldset>
                    <button class="btn btn-block btn-primary">
                        <Printer size="16" /> Print</button
                    >
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
