<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { user } = $props();
</script>

<DashboardLayout title="Update {user.name} Password" goBack={true}>
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-96 bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/staff/{user.id}/update-password"
                    disableWhileProcessing
                >
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">New password</legend>
                        <input
                            type="password"
                            class="input"
                            name="password"
                            placeholder="Password"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Confirm password</legend
                        >
                        <input
                            type="password"
                            class="input"
                            name="password_confirmation"
                            placeholder="Confirm Password"
                        />
                    </fieldset>
                    <button class="btn btn-block btn-primary">Submit</button>
                </Form>
            </div>
        </div>
    </div>
</DashboardLayout>
