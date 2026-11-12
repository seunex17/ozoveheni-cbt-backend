<script lang="ts">
    import { Link, router } from "@inertiajs/svelte";
    import { Search, SlidersHorizontal, Plus, Users, GraduationCap, ArrowLeft, ArrowRight, Pen, Trash2, X, FolderOpen } from "lucide-svelte";
    import DashboardLayout from "../../Components/Layouts/DashboardLayout.svelte";

    let { students, departments, sets = [], levels = [], filters, totalStudents = 0 } = $props();
    let search = $state("");
    let department = $state("");
    let selectedSet = $state("");
    let level = $state("");
    let sort = $state("name");
    let searching = $state(false);
    let deleting = $state(false);
    let selectedStudent = $state<any>(null);
    let deleteModal: HTMLDialogElement;
    let registerModal: HTMLDialogElement;
    let registrationDepartment = $state("");
    let registrationSet = $state("");
    let registrationLevel = $state("");
    let failedPhotos = $state<Record<number, boolean>>({});

    $effect(() => {
        search = filters.search ?? "";
        department = filters.department ?? "";
        selectedSet = filters.set ?? "";
        level = filters.level ?? "";
        sort = filters.sort ?? "name";
    });
    const hasFilters = $derived(Boolean(filters.search || filters.department || filters.set || filters.level || filters.sort !== "name"));
    const selectedDepartment = $derived(departments.find((item) => item.uuid === filters.department));
    const registrationLevels = $derived(levels);
    const currentYear = new Date().getFullYear();
    const registrationSets = $derived([...new Set([...sets, ...Array.from({ length: Math.max(1, currentYear - 2024 + 2) }, (_, index) => `${2024 + index}/${2025 + index}`)])].sort().reverse());
    const number = new Intl.NumberFormat("en-GB");

    function applyFilters(event?: SubmitEvent) {
        event?.preventDefault();
        searching = true;
        const query: Record<string, string> = {};
        for (const [key, value] of Object.entries({ search: search.trim(), department, set: selectedSet, level, sort })) {
            if (value && (key !== "sort" || value !== "name")) query[key] = value;
        }
        router.get("/dashboard/student", query, { only: ["students", "filters", "totalStudents"], preserveState: true, preserveScroll: true, onFinish: () => searching = false });
    }
    function clearFilters() {
        search = ""; department = ""; selectedSet = ""; level = ""; sort = "name";
        applyFilters();
    }
    function openRegistration() {
        registrationDepartment = department;
        registrationSet = selectedSet;
        registrationLevel = level;
        registerModal.showModal();
    }
    function registerStudent(event: SubmitEvent) {
        event.preventDefault();
        if (!registrationDepartment || !registrationSet || !registrationLevel) return;
        registerModal.close();
        router.visit(`/dashboard/student/department/${registrationDepartment}/${encodeURIComponent(registrationSet.replaceAll("/", "-"))}/${encodeURIComponent(registrationLevel)}/add`);
    }
    function confirmDelete(student: any) {
        selectedStudent = student;
        deleteModal.showModal();
    }
    function deleteStudent() {
        if (!selectedStudent || deleting) return;
        deleting = true;
        router.post("/dashboard/student/delete", { id: selectedStudent.id }, {
            preserveScroll: true,
            onSuccess: () => deleteModal.close(),
            onFinish: () => deleting = false,
        });
    }
    const fullName = (student: any) => [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ");
</script>

<svelte:head><title>Student directory · Ozoveheni</title></svelte:head>
<DashboardLayout title="Student directory" description="Find student records across departments, sets and levels.">
    {#snippet actions()}
        <button class="btn btn-primary gap-2 rounded-lg font-medium" onclick={openRegistration}><Plus size={17} /> Register student</button>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="grid gap-3 sm:grid-cols-3">
            {#each [{ title: "Registered students", value: totalStudents, icon: Users }, { title: "Departments", value: departments.length, icon: FolderOpen }, { title: "Matching students", value: students.total, icon: GraduationCap }] as stat}
                <div class="card card-border bg-base-100"><div class="card-body gap-3 p-5"><div class="flex items-center justify-between text-xs text-base-content/60"><span>{stat.title}</span><stat.icon size={17} strokeWidth={1.6} /></div><p class="text-3xl font-semibold tracking-tight tabular-nums">{number.format(stat.value)}</p></div></div>
            {/each}
        </div>
        <section class="card card-border bg-base-100" aria-labelledby="filter-heading">
            <form onsubmit={applyFilters} class="card-body gap-5 p-5 sm:p-6" aria-busy={searching}>
                <div class="flex items-center gap-2"><SlidersHorizontal size={16} class="text-base-content/50" /><h2 id="filter-heading" class="text-sm font-semibold">Find a student</h2></div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="grow"><label for="student-search" class="sr-only">Search by name or registration number</label><label class="input flex w-full items-center gap-2 rounded-lg border-base-300"><Search size={18} class="text-base-content/40" /><input id="student-search" type="search" bind:value={search} maxlength={100} placeholder="Search by name or registration number…" /></label></div>
                    <button type="submit" class="btn gap-2 rounded-lg px-6" disabled={searching}><Search size={16} />{searching ? "Searching…" : "Search students"}</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="flex flex-col gap-2"><label for="student-department" class="text-xs font-medium text-base-content/65">Department</label><select id="student-department" class="select w-full rounded-lg border-base-300" bind:value={department}><option value="">All departments</option>{#each departments as item}<option value={item.uuid}>{item.name}</option>{/each}</select></div>
                    <div class="flex flex-col gap-2"><label for="student-set" class="text-xs font-medium text-base-content/65">Set / session</label><select id="student-set" class="select w-full rounded-lg border-base-300" bind:value={selectedSet}><option value="">All sets</option>{#each sets as item}<option value={item}>{item}</option>{/each}</select></div>
                    <div class="flex flex-col gap-2"><label for="student-level" class="text-xs font-medium text-base-content/65">Level</label><select id="student-level" class="select w-full rounded-lg border-base-300" bind:value={level}><option value="">All levels</option>{#each levels as item}<option value={item}>{item.toUpperCase()}</option>{/each}</select></div>
                    <div class="flex flex-col gap-2"><label for="student-sort" class="text-xs font-medium text-base-content/65">Sort by</label><select id="student-sort" class="select w-full rounded-lg border-base-300" bind:value={sort}><option value="name">Name A–Z</option><option value="registration">Registration number</option><option value="newest">Recently added</option></select></div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4 text-xs text-base-content/50"><span>Choose filters, then press Search or Enter to apply.</span>{#if hasFilters}<button type="button" class="btn btn-ghost btn-xs gap-1.5" onclick={clearFilters} disabled={searching}><X size={13} /> Clear filters</button>{/if}</div>
            </form>
        </section>
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="results-heading" aria-busy={searching}>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-5 sm:px-6"><div><h2 id="results-heading" class="text-base font-semibold">Student records</h2><p class="mt-1 text-xs text-base-content/50" role="status" aria-live="polite">{number.format(students.total)} {students.total === 1 ? "student" : "students"}{selectedDepartment ? ` in ${selectedDepartment.name}` : ""}{filters.set ? ` · ${filters.set}` : ""}{filters.level ? ` · ${filters.level.toUpperCase()}` : ""}</p></div><span class="text-xs text-base-content/45">20 records per page</span></div>
            {#if students.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table must accept focus for horizontal keyboard navigation.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Student records table, scroll horizontally if needed">
                    <table class="table min-w-175">
                        <caption class="sr-only">Students matching the selected search and filters</caption>
                        <thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/50"><tr><th scope="col" class="px-6 font-medium">Student</th><th scope="col" class="font-medium">Registration number</th><th scope="col" class="font-medium">Department</th><th scope="col" class="font-medium">Set / level</th><th scope="col" class="pr-6 text-right font-medium">Actions</th></tr></thead>
                        <tbody>
                            {#each students.data as student (student.id)}
                                <tr class="border-base-300/70 hover:bg-base-200/40">
                                    <th scope="row" class="px-6 py-4 font-normal"><div class="flex items-center gap-3"><div class="avatar avatar-placeholder shrink-0"><div class="h-11 w-11 rounded-lg bg-base-200 text-base-content/50">{#if student.photo && !failedPhotos[student.id]}<img src={`/storage/${student.photo}`} alt="" loading="lazy" class="object-cover" onerror={() => failedPhotos[student.id] = true} />{:else}<span class="text-sm font-medium">{[student.first_name?.[0], student.last_name?.[0]].filter(Boolean).join("")}</span>{/if}</div></div><span class="max-w-55 whitespace-normal text-sm font-medium leading-5">{fullName(student)}</span></div></th>
                                    <td class="text-xs tabular-nums text-base-content/65">{student.reg_no}</td>
                                    <td class="max-w-55 whitespace-normal text-xs leading-5 text-base-content/65">{student.department?.name ?? "—"}</td>
                                    <td><div class="text-xs">{student.set}</div><div class="mt-1 text-[11px] uppercase text-base-content/45">{student.level}</div></td>
                                    <td class="pr-6"><div class="flex justify-end gap-1"><Link href={`/dashboard/student/${student.uuid}/edit`} class="btn btn-ghost btn-sm gap-1.5" aria-label={`Edit ${fullName(student)}`}><Pen size={14} /><span>Edit</span></Link><button class="btn btn-square btn-ghost btn-sm text-base-content/45 hover:text-error" aria-label={`Delete ${fullName(student)}`} onclick={() => confirmDelete(student)}><Trash2 size={15} /></button></div></td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 sm:px-6"><span class="text-xs text-base-content/50">Showing {students.from}–{students.to} of {number.format(students.total)}</span><nav class="flex items-center gap-2" aria-label="Student pages">{#if students.prev_page_url}<Link href={students.prev_page_url} class="btn btn-sm gap-1.5" preserveScroll><ArrowLeft size={14} /> Previous</Link>{:else}<button class="btn btn-sm" disabled>Previous</button>{/if}<span class="px-2 text-xs text-base-content/55" aria-current="page">Page {students.current_page} of {students.last_page}</span>{#if students.next_page_url}<Link href={students.next_page_url} class="btn btn-sm gap-1.5" preserveScroll>Next <ArrowRight size={14} /></Link>{:else}<button class="btn btn-sm" disabled>Next</button>{/if}</nav></div>
            {:else}
                <div class="flex flex-col items-center px-6 py-14 text-center"><Users size={30} strokeWidth={1.4} class="mb-4 text-base-content/40" /><h3 class="text-sm font-semibold">{totalStudents ? "No students match your search" : "Your student directory starts here"}</h3><p class="mt-2 max-w-90 text-sm leading-6 text-base-content/50">{totalStudents ? "Try a different name, registration number or set of filters." : "Register the first student to begin managing your academic records."}</p><button class="btn btn-sm mt-5" onclick={totalStudents ? clearFilters : openRegistration}>{totalStudents ? "Clear filters" : "Register student"}</button></div>
            {/if}
        </section>
    </div>
</DashboardLayout>

<dialog bind:this={registerModal} class="modal" aria-labelledby="register-heading">
    <div class="modal-box max-w-md"><h2 id="register-heading" class="text-lg font-semibold">Register a student</h2><p class="mt-2 text-sm leading-6 text-base-content/55">Choose the student's group. You'll enter their details on the next page.</p><form onsubmit={registerStudent} class="mt-5 flex flex-col gap-4"><div><label for="register-department" class="mb-2 block text-xs font-medium">Department</label><select id="register-department" class="select w-full" bind:value={registrationDepartment} required><option value="">Choose department</option>{#each departments as item}<option value={item.uuid}>{item.name}</option>{/each}</select></div><div class="grid grid-cols-2 gap-3"><div><label for="register-set" class="mb-2 block text-xs font-medium">Set / session</label><select id="register-set" class="select w-full" bind:value={registrationSet} required><option value="">Choose set</option>{#each registrationSets as item}<option value={item}>{item}</option>{/each}</select></div><div><label for="register-level" class="mb-2 block text-xs font-medium">Level</label><select id="register-level" class="select w-full" bind:value={registrationLevel} required><option value="">Choose level</option>{#each registrationLevels as item}<option value={item}>{item.toUpperCase()}</option>{/each}</select></div></div><div class="modal-action"><button type="button" class="btn" onclick={() => registerModal.close()}>Cancel</button><button type="submit" class="btn btn-primary gap-2" disabled={!registrationDepartment || !registrationSet || !registrationLevel}>Continue <ArrowRight size={14} /></button></div></form></div>
</dialog>
<dialog bind:this={deleteModal} class="modal" aria-labelledby="delete-heading" aria-describedby="delete-description">
    <div class="modal-box max-w-md"><h2 id="delete-heading" class="text-lg font-semibold">Delete student record?</h2><p id="delete-description" class="mt-3 text-sm leading-6 text-base-content/60">Remove <strong class="font-semibold text-base-content">{selectedStudent ? fullName(selectedStudent) : "this student"}</strong>{selectedStudent ? ` (${selectedStudent.reg_no})` : ""}? Their record and associated exam data will be deleted. This cannot be undone.</p><div class="modal-action"><form method="dialog"><button class="btn" disabled={deleting}>Cancel</button></form><button class="btn btn-error" onclick={deleteStudent} disabled={deleting}>{deleting ? "Deleting…" : "Delete student"}</button></div></div>
</dialog>
