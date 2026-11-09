<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { user, errors = {} } = $props();
</script>

<DashboardLayout title="Update {user.name} Password" goBack={true}>
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-full max-w-xl bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/staff/{user.id}/update-password"
                    disableWhileProcessing
                >
                    {#each Object.values(errors) as error}<p class="text-sm text-error" role="alert">{error}</p>{/each}
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">New password</legend>
                        <input
                            type="password"
                            class="input w-full"
                            name="password"
                            placeholder="Password"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Confirm password</legend
                        >
                        <input
                            type="password"
                            class="input w-full"
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
