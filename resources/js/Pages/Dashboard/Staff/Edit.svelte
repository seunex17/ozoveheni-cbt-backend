<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Save } from "lucide-svelte";

    let { user } = $props();
</script>

<DashboardLayout title="Edit {user.name}" goBack={true}>
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/staff/{user.id}/edit"
                    disableWhileProcessing
                >
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Username</legend>
                        <input
                            type="text"
                            class="input"
                            name="name"
                            value={user.name}
                            placeholder="Username"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Email Address</legend>
                        <input
                            type="email"
                            class="input"
                            name="email"
                            value={user.email}
                            placeholder="Email Address"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Status</legend>
                        <select class="select" name="status">
                            <option
                                value="active"
                                selected={user.status === "active"}
                                >Active</option
                            >
                            <option
                                value="inactive"
                                selected={user.status === "inactive"}
                                >Inactive</option
                            >
                        </select>
                    </fieldset>
                    <button class="btn btn-block btn-primary">
                        <Save size="16" /> Save</button
                    >
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
