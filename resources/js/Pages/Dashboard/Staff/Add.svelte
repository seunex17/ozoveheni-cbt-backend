<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";
    import { Save } from "lucide-svelte";
    let { errors = {} } = $props();
</script>

<DashboardLayout title="Add New Staff" goBack={true}>
    <div class="flex items-center justify-center w-full">
        <div class="shadow-sm card w-full max-w-xl bg-base-100">
            <div class="card-body">
                <Form
                    class="space-y-3"
                    method="POST"
                    action="/dashboard/staff/add"
                    disableWhileProcessing
                >
                    {#each Object.values(errors) as error}<p class="text-sm text-error" role="alert">{error}</p>{/each}
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Username</legend>
                        <input
                            type="text"
                            class="input w-full"
                            name="name"
                            placeholder="Username"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Email Address</legend>
                        <input
                            type="email"
                            class="input w-full"
                            name="email"
                            placeholder="Email Address"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Role</legend>
                        <select class="select w-full" name="is_admin">
                            <option value="0">Staff</option>
                            <option value="1">Admin</option>
                        </select>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Password</legend>
                        <input
                            type="password"
                            class="input w-full"
                            name="password"
                            placeholder="Password"
                        />
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Confirm Password</legend
                        >
                        <input
                            type="password"
                            class="input w-full"
                            name="password_confirmation"
                            placeholder="Confirm Password"
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
