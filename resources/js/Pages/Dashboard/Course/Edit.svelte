<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Save } from "lucide-svelte";

    let { course } = $props();
</script>

<DashboardLayout title="{course.name} ({course.code})" goBack={true}>
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/course/{course.uuid}/edit"
                    disableWhileProcessing
                >
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Course Name</legend>
                        <input
                            type="text"
                            class="input"
                            name="name"
                            value={course.name}
                            placeholder="Course Name"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Course Code</legend>
                        <input
                            type="text"
                            class="input"
                            name="code"
                            value={course.code}
                            placeholder="Course Code"
                        />
                    </fieldset>
                    <button class="btn btn-block btn-primary">
                        <Save size="16" /> Save Changes</button
                    >
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
