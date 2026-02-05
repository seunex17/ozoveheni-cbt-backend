<script lang="ts">
    import { User } from "lucide-svelte";
    import DashboardLayout from "../../Components/Layouts/DashboardLayout.svelte";
    import { Form } from "@inertiajs/svelte";

    let { student, departments } = $props();

    let preview = $derived("/storage/" + student.photo);

    const handleFileChange = (e: Event) => {
        const input = e.target as HTMLInputElement;
        if (input.files && input.files[0]) {
            preview = URL.createObjectURL(input.files[0]);
        }
    };
</script>

<DashboardLayout title="Update {student.first_name} Info" goBack={true}>
    <Form
        class="w-full flex gap-3"
        method="POST"
        action="/dashboard/student/edit"
        enctype="multipart/form-data"
        disableWhileProcessing
        resetOnSuccess
    >
        <div class="w-3/12">
            <div class="card w-full bg-base-100">
                <div class="card-body items-center text-center">
                    <h2 class="card-title mb-2">Passport Photo</h2>

                    <div class="relative group">
                        <div
                            class="size-56 bg-base-300 rounded-md overflow-hidden border-2 border-dashed border-base-content/20 flex items-center justify-center"
                        >
                            <div
                                id="placeholder"
                                class="text-base-content/50"
                                class:hidden={!!preview}
                            >
                                <User size="56" />
                                <p
                                    class="text-xs mt-2 uppercase tracking-widest"
                                >
                                    No Image
                                </p>
                            </div>

                            <img
                                id="preview"
                                class="w-full h-full object-cover"
                                class:hidden={!preview}
                                src={preview}
                                alt="Passport Preview"
                            />
                        </div>
                    </div>

                    <p class="text-xs text-base-content/60 mt-4">
                        Requirements: 2x2 inch, white background, JPEG or PNG.
                    </p>

                    <div class="card-actions mt-6 w-full">
                        <label
                            for="photo-upload"
                            class="btn btn-primary btn-soft btn-block"
                        >
                            Upload Photo
                            <input
                                type="file"
                                id="photo-upload"
                                class="hidden"
                                accept="image/*"
                                name="photo"
                                onchange={handleFileChange}
                            />
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="w-9/12">
            <div class="card w-full bg-base-100">
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-3">
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">First Name</legend>
                            <input
                                type="text"
                                class="input"
                                name="first_name"
                                value={student.first_name}
                                placeholder="First name"
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Middle Name</legend>
                            <input
                                type="text"
                                class="input"
                                name="middle_name"
                                value={student.middle_name}
                                placeholder="Middle name"
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Last Name</legend>
                            <input
                                type="text"
                                class="input"
                                name="last_name"
                                value={student.last_name}
                                placeholder="Last name"
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Reg No</legend>
                            <input
                                type="text"
                                class="input"
                                name="reg_no"
                                value={student.reg_no}
                                readonly
                                placeholder="Registration No."
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">DOB</legend>
                            <input
                                type="date"
                                class="input"
                                name="dob"
                                value={student.dob}
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Set</legend>
                            <input
                                type="text"
                                class="input"
                                name="set"
                                value={student.set}
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Department</legend>
                            <select class="select" name="department_id">
                                {#each departments as dept}
                                    <option
                                        value={dept.id}
                                        selected={dept.id ===
                                            student.department_id}
                                        >{dept.name}</option
                                    >
                                {/each}
                            </select>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Gender</legend>
                            <select class="select" name="gender">
                                <option
                                    value="M"
                                    selected={student.gender === "M"}
                                    >Male</option
                                >
                                <option
                                    value="F"
                                    selected={student.gender === "F"}
                                    >Female</option
                                >
                            </select>
                        </fieldset>
                        <div class="col-span-2 flex justify-end">
                            <button type="submit" class="btn btn-primary"
                                >save Changes</button
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" name="uuid" value={student.uuid} />>
    </Form>
</DashboardLayout>
