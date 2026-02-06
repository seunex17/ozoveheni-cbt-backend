<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Save } from "lucide-svelte";

    let { department } = $props();
</script>

<DashboardLayout title="Update Department" goBack={true}>
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/department/{department.uuid}/edit"
                    disableWhileProcessing
                >
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Department Name</legend>
                        <input
                            type="text"
                            class="input"
                            name="name"
                            value={department.name}
                            placeholder="Department Name"
                        />
                    </fieldset>
                    <button class="btn btn-block btn-primary">
                        <Save size="16" /> Save</button
                    >
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
