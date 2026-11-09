<script lang="ts">
    import { Link, router, useForm } from "@inertiajs/svelte";
    import { Plus, Pen, Trash2, Search, X, FolderOpen, Users, ArrowLeft, ArrowRight, SlidersHorizontal } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { departments, filters, totalDepartments } = $props();
    let search = $state("");
    let sort = $state("name");
    let searching = $state(false);
    let deleting = $state(false);
    let selectedDepartment = $state<any>(null);
    let deleteModal: HTMLDialogElement;
    let addModal: HTMLDialogElement;
    const addForm = useForm({ name: "" });
    function openAddDepartment() {
        $addForm.reset();
        $addForm.clearErrors();
        addModal.showModal();
    }
    function createDepartment(event: SubmitEvent) {
        event.preventDefault();
        if ($addForm.processing) return;
        $addForm.post("/dashboard/department/add", {
            preserveScroll: false,
            onSuccess: () => {
                addModal.close();
                $addForm.reset();
            },
            onError: () => addModal.querySelector<HTMLInputElement>("input")?.focus(),
        });
    }
    const number = new Intl.NumberFormat("en-GB");
    const date = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric" });
    const hasFilters = $derived(Boolean(filters.search || filters.sort !== "name"));

    $effect(() => {
        search = filters.search ?? "";
        sort = filters.sort ?? "name";
    });
    function applyFilters(event?: SubmitEvent) {
        event?.preventDefault();
        searching = true;
        const query: Record<string, string> = {};
        if (search.trim()) query.search = search.trim();
        if (sort !== "name") query.sort = sort;
        router.get("/dashboard/department", query, {
            preserveState: true, preserveScroll: true,
            onFinish: () => searching = false,
        });
    }
    function clearFilters() {
        search = ""; sort = "name";
        applyFilters();
    }
    function confirmDelete(department: any) {
        selectedDepartment = department;
        deleteModal.showModal();
    }
    function deleteDepartment() {
        if (!selectedDepartment || deleting) return;
        deleting = true;
        router.post("/dashboard/department/delete", { id: selectedDepartment.id }, {
            preserveScroll: true,
            onSuccess: () => deleteModal.close(),
            onFinish: () => deleting = false,
        });
    }
</script>

<svelte:head><title>Departments · Ozoveheni</title></svelte:head>
<DashboardLayout title="Departments" description="Organise academic departments and find their student records.">
    {#snippet actions()}
        <button onclick={openAddDepartment} class="btn btn-primary gap-2 rounded-lg font-medium"><Plus size={17} /> Add department</button>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="card card-border bg-base-100">
            <div class="card-body flex-row flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                <div class="flex items-center gap-4"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><FolderOpen size={21} strokeWidth={1.6} /></div><div><p class="text-xs text-base-content/60">Total departments</p><p class="mt-1 text-2xl font-semibold tabular-nums">{number.format(totalDepartments)}</p></div></div>
                <p class="max-w-md text-sm leading-6 text-base-content/60">Use a department's student link to view its registered students across all sets and levels.</p>
            </div>
        </div>
        <section class="card card-border bg-base-100" aria-labelledby="filter-heading">
            <form onsubmit={applyFilters} class="card-body gap-5 p-5 sm:p-6" aria-busy={searching}>
                <div class="flex items-center gap-2"><SlidersHorizontal size={16} class="text-base-content/60" /><h2 id="filter-heading" class="text-sm font-semibold">Find a department</h2></div>
                <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                    <div class="sm:col-span-2 xl:col-span-1"><label for="department-search" class="mb-2 block text-xs font-medium text-base-content/70">Department name</label><label class="input flex w-full items-center gap-2 rounded-lg border-base-300"><Search size={17} class="shrink-0 text-base-content/50" /><input id="department-search" type="search" bind:value={search} maxlength={100} placeholder="Search department names…" /></label></div>
                    <div><label for="department-sort" class="mb-2 block text-xs font-medium text-base-content/70">Sort by</label><select id="department-sort" class="select w-full rounded-lg border-base-300" bind:value={sort}><option value="name">Name A–Z</option><option value="newest">Recently added</option></select></div>
                    <button type="submit" class="btn gap-2 rounded-lg sm:col-span-2 xl:col-span-1" disabled={searching}><Search size={16} />{searching ? "Searching…" : "Search"}</button>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4 text-xs text-base-content/60"><span>Choose filters, then press Search or Enter to apply.</span>{#if hasFilters}<button type="button" class="btn btn-ghost btn-xs gap-1.5" onclick={clearFilters} disabled={searching}><X size={13} /> Clear filters</button>{/if}</div>
            </form>
        </section>
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="results-heading" aria-busy={searching}>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-5 sm:px-6"><div><h2 id="results-heading" class="text-base font-semibold">Department directory</h2><p class="mt-1 text-xs text-base-content/60" role="status" aria-live="polite">{number.format(departments.total)} {departments.total === 1 ? "department" : "departments"}{filters.search ? ` matching “${filters.search}”` : ""}</p></div><span class="text-xs text-base-content/60">20 records per page</span></div>
            {#if departments.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table must accept focus for horizontal keyboard navigation.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Department records table, scroll horizontally if needed">
                    <table class="table min-w-180">
                        <caption class="sr-only">Departments matching the selected search and filters</caption>
                        <thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6 font-medium">Department</th><th scope="col" class="font-medium">Student records</th><th scope="col" class="font-medium">Date added</th><th scope="col" class="pr-6 text-right font-medium">Actions</th></tr></thead>
                        <tbody>
                            {#each departments.data as department (department.id)}
                                <tr class="border-base-300/70 hover:bg-base-200/40">
                                    <th scope="row" class="px-6 py-5 font-normal"><div class="flex items-center gap-3"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/55"><FolderOpen size={18} strokeWidth={1.6} /></div><span class="max-w-70 whitespace-normal text-sm font-medium leading-5">{department.name}</span></div></th>
                                    <td><Link href={`/dashboard/student?department=${department.uuid}`} class="btn btn-ghost btn-sm gap-2 font-normal" aria-label={`View student records for ${department.name}`}><Users size={15} /><span>View students</span><ArrowRight size={13} /></Link></td>
                                    <td class="text-xs text-base-content/65">{#if department.created_at}<time datetime={department.created_at}>{date.format(new Date(department.created_at))}</time>{:else}—{/if}</td>
                                    <td class="pr-6"><div class="flex justify-end gap-1"><Link href={`/dashboard/department/${department.uuid}/edit`} class="btn btn-ghost btn-sm gap-1.5" aria-label={`Edit ${department.name}`}><Pen size={14} /> Edit</Link><button class="btn btn-square btn-ghost btn-sm text-base-content/55 hover:text-error" aria-label={`Delete ${department.name}`} onclick={() => confirmDelete(department)}><Trash2 size={15} /></button></div></td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 sm:px-6"><span class="text-xs text-base-content/60">Showing {departments.from}–{departments.to} of {number.format(departments.total)}</span><nav class="flex items-center gap-2" aria-label="Department pages">{#if departments.prev_page_url}<Link href={departments.prev_page_url} class="btn btn-sm gap-1.5" preserveScroll><ArrowLeft size={14} /> Previous</Link>{:else}<button class="btn btn-sm" disabled>Previous</button>{/if}<span class="px-2 text-xs text-base-content/65" aria-current="page">Page {departments.current_page} of {departments.last_page}</span>{#if departments.next_page_url}<Link href={departments.next_page_url} class="btn btn-sm gap-1.5" preserveScroll>Next <ArrowRight size={14} /></Link>{:else}<button class="btn btn-sm" disabled>Next</button>{/if}</nav></div>
            {:else}
                <div class="flex flex-col items-center px-6 py-14 text-center"><FolderOpen size={30} strokeWidth={1.4} class="mb-4 text-base-content/45" /><h3 class="text-sm font-semibold">{totalDepartments ? "No departments match your search" : "Add your first department"}</h3><p class="mt-2 max-w-90 text-sm leading-6 text-base-content/60">{totalDepartments ? "Try another department name or clear your search." : "Create a department to organise its students and examinations."}</p>{#if totalDepartments}<button class="btn btn-sm mt-5" onclick={clearFilters}>Clear filters</button>{:else}<button onclick={openAddDepartment} class="btn btn-sm mt-5">Add department</button>{/if}</div>
            {/if}
        </section>
    </div>
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal" aria-labelledby="delete-heading" aria-describedby="delete-description">
    <div class="modal-box max-w-md"><h2 id="delete-heading" class="text-lg font-semibold">Delete department?</h2><p id="delete-description" class="mt-3 text-sm leading-6 text-base-content/65">Delete <strong class="font-semibold text-base-content">{selectedDepartment?.name ?? "this department"}</strong>? This will also delete all student records, exams and associated examination records in this department. This cannot be undone.</p><div class="modal-action"><form method="dialog"><button class="btn" disabled={deleting}>Cancel</button></form><button class="btn btn-error" onclick={deleteDepartment} disabled={deleting}>{deleting ? "Deleting…" : "Delete department"}</button></div></div>
</dialog>

<dialog bind:this={addModal} class="modal" aria-labelledby="add-heading" aria-describedby="add-description" oncancel={(event) => { if ($addForm.processing) event.preventDefault(); }}>
    <div class="modal-box max-w-lg">
        <div class="flex items-start justify-between gap-4"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><FolderOpen size={22} strokeWidth={1.6} /></div><button type="button" class="btn btn-square btn-ghost btn-sm" aria-label="Close add department" disabled={$addForm.processing} onclick={() => addModal.close()}><X size={18} /></button></div>
        <h2 id="add-heading" class="mt-5 text-lg font-semibold">Add department</h2>
        <p id="add-description" class="mt-2 text-sm leading-6 text-base-content/65">Enter the official department name. The new department will appear at the top of the directory after saving.</p>
        <form onsubmit={createDepartment} class="mt-6 flex flex-col gap-5" aria-busy={$addForm.processing}>
            <div><label for="new-department-name" class="mb-2 block text-sm font-medium">Department name</label><input id="new-department-name" class="input w-full rounded-lg border-base-300" class:input-error={Boolean($addForm.errors.name)} name="name" type="text" bind:value={$addForm.name} required maxlength={255} disabled={$addForm.processing} placeholder="e.g. Community Health" aria-invalid={Boolean($addForm.errors.name)} aria-describedby={$addForm.errors.name ? "new-department-error" : undefined} />{#if $addForm.errors.name}<p id="new-department-error" class="mt-2 text-sm text-error" role="alert">{$addForm.errors.name}</p>{/if}</div>
            <div class="modal-action mt-1 border-t border-base-300 pt-5"><button type="button" class="btn" disabled={$addForm.processing} onclick={() => addModal.close()}>Cancel</button><button type="submit" class="btn gap-2" disabled={$addForm.processing || !$addForm.name.trim()}><Plus size={16} />{$addForm.processing ? "Creating…" : "Create department"}</button></div>
        </form>
    </div>
</dialog>
