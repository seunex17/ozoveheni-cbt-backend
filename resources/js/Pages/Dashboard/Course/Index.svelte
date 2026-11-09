<script lang="ts">
    import { Link, router, useForm } from "@inertiajs/svelte";
    import { Plus, Pen, Trash2, Search, X, BookOpen, ArrowLeft, ArrowRight, SlidersHorizontal } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { courses, filters, totalCourses } = $props();
    let search = $state("");
    let sort = $state("name");
    let searching = $state(false);
    let deleting = $state(false);
    let selectedCourse = $state<any>(null);
    let deleteModal: HTMLDialogElement;
    let addModal: HTMLDialogElement;
    let editingCourse = $state<any>(null);
    const addForm = useForm({ name: "", code: "", unit: "" as string | number });
    function openAddCourse() {
        editingCourse = null;
        $addForm.reset();
        $addForm.clearErrors();
        addModal.showModal();
    }
    function openEditCourse(course: any) {
        editingCourse = course;
        $addForm.clearErrors();
        $addForm.name = course.name;
        $addForm.code = course.code;
        $addForm.unit = course.unit ?? "";
        addModal.showModal();
    }
    function createCourse(event: SubmitEvent) {
        event.preventDefault();
        if ($addForm.processing) return;
        $addForm.post(editingCourse ? `/dashboard/course/${editingCourse.uuid}/edit` : "/dashboard/course/add", {
            preserveScroll: false,
            onSuccess: () => {
                addModal.close();
                $addForm.reset();
            },
            onError: () => addModal.querySelector<HTMLInputElement>('[aria-invalid="true"]')?.focus(),
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
        router.get("/dashboard/course", query, {
            preserveState: true, preserveScroll: true,
            onFinish: () => searching = false,
        });
    }
    function clearFilters() {
        search = ""; sort = "name";
        applyFilters();
    }
    function confirmDelete(course: any) {
        selectedCourse = course;
        deleteModal.showModal();
    }
    function deleteCourse() {
        if (!selectedCourse || deleting) return;
        deleting = true;
        router.post("/dashboard/course/delete", { id: selectedCourse.id }, {
            preserveScroll: true,
            onSuccess: () => deleteModal.close(),
            onFinish: () => deleting = false,
        });
    }
</script>

<svelte:head><title>Courses · Ozoveheni</title></svelte:head>
<DashboardLayout title="Courses" description="Find and manage course names, codes and credit units.">
    {#snippet actions()}
        <button onclick={openAddCourse} class="btn btn-primary gap-2 rounded-lg font-medium"><Plus size={17} /> Add course</button>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="card card-border bg-base-100">
            <div class="card-body flex-row flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                <div class="flex items-center gap-4"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><BookOpen size={21} strokeWidth={1.6} /></div><div><p class="text-xs text-base-content/60">Total courses</p><p class="mt-1 text-2xl font-semibold tabular-nums">{number.format(totalCourses)}</p></div></div>
                <p class="max-w-md text-sm leading-6 text-base-content/60">Keep course names, codes and credit units up to date for examination setup.</p>
            </div>
        </div>
        <section class="card card-border bg-base-100" aria-labelledby="filter-heading">
            <form onsubmit={applyFilters} class="card-body gap-5 p-5 sm:p-6" aria-busy={searching}>
                <div class="flex items-center gap-2"><SlidersHorizontal size={16} class="text-base-content/60" /><h2 id="filter-heading" class="text-sm font-semibold">Find a course</h2></div>
                <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                    <div class="sm:col-span-2 xl:col-span-1"><label for="course-search" class="mb-2 block text-xs font-medium text-base-content/70">Course name or code</label><label class="input flex w-full items-center gap-2 rounded-lg border-base-300"><Search size={17} class="shrink-0 text-base-content/50" /><input id="course-search" type="search" bind:value={search} maxlength={100} placeholder="Search by name or course code…" /></label></div>
                    <div><label for="course-sort" class="mb-2 block text-xs font-medium text-base-content/70">Sort by</label><select id="course-sort" class="select w-full rounded-lg border-base-300" bind:value={sort}><option value="name">Name A–Z</option><option value="code">Course code</option><option value="newest">Recently added</option></select></div>
                    <button type="submit" class="btn gap-2 rounded-lg sm:col-span-2 xl:col-span-1" disabled={searching}><Search size={16} />{searching ? "Searching…" : "Search"}</button>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4 text-xs text-base-content/60"><span>Choose filters, then press Search or Enter to apply.</span>{#if hasFilters}<button type="button" class="btn btn-ghost btn-xs gap-1.5" onclick={clearFilters} disabled={searching}><X size={13} /> Clear filters</button>{/if}</div>
            </form>
        </section>
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="results-heading" aria-busy={searching}>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-5 sm:px-6"><div><h2 id="results-heading" class="text-base font-semibold">Course directory</h2><p class="mt-1 text-xs text-base-content/60" role="status" aria-live="polite">{number.format(courses.total)} {courses.total === 1 ? "course" : "courses"}{filters.search ? ` matching “${filters.search}”` : ""}</p></div><span class="text-xs text-base-content/60">20 records per page</span></div>
            {#if courses.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table must accept focus for horizontal keyboard navigation.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Course records table, scroll horizontally if needed">
                    <table class="table min-w-190">
                        <caption class="sr-only">Courses matching the selected search and filters</caption>
                        <thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6 font-medium">Course</th><th scope="col" class="font-medium">Course code</th><th scope="col" class="font-medium">Units</th><th scope="col" class="font-medium">Date added</th><th scope="col" class="pr-6 text-right font-medium">Actions</th></tr></thead>
                        <tbody>
                            {#each courses.data as course (course.id)}
                                <tr class="border-base-300/70 hover:bg-base-200/40">
                                    <th scope="row" class="px-6 py-5 font-normal"><div class="flex items-center gap-3"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/55"><BookOpen size={18} strokeWidth={1.6} /></div><span class="max-w-70 whitespace-normal text-sm font-medium leading-5">{course.name}</span></div></th>
                                    <td class="text-xs font-medium tabular-nums">{course.code}</td><td class="text-xs tabular-nums text-base-content/65">{course.unit ?? "—"}</td>
                                    <td class="text-xs text-base-content/65">{#if course.created_at}<time datetime={course.created_at}>{date.format(new Date(course.created_at))}</time>{:else}—{/if}</td>
                                    <td class="pr-6"><div class="flex justify-end gap-1"><button onclick={() => openEditCourse(course)} class="btn btn-ghost btn-sm gap-1.5" aria-label={`Edit ${course.name}`}><Pen size={14} /> Edit</button><button class="btn btn-square btn-ghost btn-sm text-base-content/55 hover:text-error" aria-label={`Delete ${course.name}`} onclick={() => confirmDelete(course)}><Trash2 size={15} /></button></div></td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 sm:px-6"><span class="text-xs text-base-content/60">Showing {courses.from}–{courses.to} of {number.format(courses.total)}</span><nav class="flex items-center gap-2" aria-label="Course pages">{#if courses.prev_page_url}<Link href={courses.prev_page_url} class="btn btn-sm gap-1.5" preserveScroll><ArrowLeft size={14} /> Previous</Link>{:else}<button class="btn btn-sm" disabled>Previous</button>{/if}<span class="px-2 text-xs text-base-content/65" aria-current="page">Page {courses.current_page} of {courses.last_page}</span>{#if courses.next_page_url}<Link href={courses.next_page_url} class="btn btn-sm gap-1.5" preserveScroll>Next <ArrowRight size={14} /></Link>{:else}<button class="btn btn-sm" disabled>Next</button>{/if}</nav></div>
            {:else}
                <div class="flex flex-col items-center px-6 py-14 text-center"><BookOpen size={30} strokeWidth={1.4} class="mb-4 text-base-content/45" /><h3 class="text-sm font-semibold">{totalCourses ? "No courses match your search" : "Add your first course"}</h3><p class="mt-2 max-w-90 text-sm leading-6 text-base-content/60">{totalCourses ? "Try another course name or code, or clear your search." : "Add a course with its code and credit units to use it in examinations."}</p>{#if totalCourses}<button class="btn btn-sm mt-5" onclick={clearFilters}>Clear filters</button>{:else}<button onclick={openAddCourse} class="btn btn-sm mt-5">Add course</button>{/if}</div>
            {/if}
        </section>
    </div>
</DashboardLayout>

<dialog bind:this={deleteModal} class="modal" aria-labelledby="delete-heading" aria-describedby="delete-description">
    <div class="modal-box max-w-md"><h2 id="delete-heading" class="text-lg font-semibold">Delete course?</h2><p id="delete-description" class="mt-3 text-sm leading-6 text-base-content/65">Delete <strong class="font-semibold text-base-content">{selectedCourse?.name ?? "this course"}</strong>? This will also delete the associated exam papers, questions, attempts and result records. This cannot be undone.</p><div class="modal-action"><form method="dialog"><button class="btn" disabled={deleting}>Cancel</button></form><button class="btn btn-error" onclick={deleteCourse} disabled={deleting}>{deleting ? "Deleting…" : "Delete course"}</button></div></div>
</dialog>

<dialog bind:this={addModal} class="modal" aria-labelledby="add-heading" aria-describedby="add-description" oncancel={(event) => { if ($addForm.processing) event.preventDefault(); }}>
    <div class="modal-box max-w-lg">
        <div class="flex items-start justify-between gap-4"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><BookOpen size={22} strokeWidth={1.6} /></div><button type="button" class="btn btn-square btn-ghost btn-sm" aria-label="Close course form" disabled={$addForm.processing} onclick={() => addModal.close()}><X size={18} /></button></div>
        <h2 id="add-heading" class="mt-5 text-lg font-semibold">{editingCourse ? "Edit course" : "Add course"}</h2>
        <p id="add-description" class="mt-2 text-sm leading-6 text-base-content/65">{editingCourse ? "Update the course details. The directory will show the saved course after saving." : "Enter the course details. The new course will appear at the top of the directory after saving."}</p>
        <form onsubmit={createCourse} class="mt-6 flex flex-col gap-5" aria-busy={$addForm.processing}>
            <div><label for="new-course-name" class="mb-2 block text-sm font-medium">Course name</label><input id="new-course-name" class="input w-full rounded-lg border-base-300" class:input-error={Boolean($addForm.errors.name)} name="name" type="text" bind:value={$addForm.name} required maxlength={255} disabled={$addForm.processing} placeholder="e.g. Human Anatomy" aria-invalid={Boolean($addForm.errors.name)} aria-describedby={$addForm.errors.name ? "new-course-error" : undefined} />{#if $addForm.errors.name}<p id="new-course-error" class="mt-2 text-sm text-error" role="alert">{$addForm.errors.name}</p>{/if}</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="course-code" class="mb-2 block text-sm font-medium">Course code</label><input id="course-code" class="input w-full rounded-lg border-base-300" class:input-error={Boolean($addForm.errors.code)} name="code" bind:value={$addForm.code} required maxlength={10} disabled={$addForm.processing} placeholder="e.g. ANA101" aria-invalid={Boolean($addForm.errors.code)} aria-describedby="code-help" /><p id="code-help" class="mt-2 text-xs text-base-content/60">Unique code, up to 10 characters.</p>{#if $addForm.errors.code}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.code}</p>{/if}</div>
                <div><label for="course-unit" class="mb-2 block text-sm font-medium">Credit units <span class="text-xs font-normal text-base-content/60">(optional)</span></label><input id="course-unit" class="input w-full rounded-lg border-base-300" class:input-error={Boolean($addForm.errors.unit)} name="unit" type="number" min="0" max="2147483647" step="1" bind:value={$addForm.unit} disabled={$addForm.processing} placeholder="e.g. 3" aria-invalid={Boolean($addForm.errors.unit)} />{#if $addForm.errors.unit}<p class="mt-2 text-sm text-error" role="alert">{$addForm.errors.unit}</p>{/if}</div>
            </div>
            <div class="modal-action mt-1 border-t border-base-300 pt-5"><button type="button" class="btn" disabled={$addForm.processing} onclick={() => addModal.close()}>Cancel</button><button type="submit" class="btn gap-2" disabled={$addForm.processing || !$addForm.name.trim() || !$addForm.code.trim()}><Plus size={16} />{$addForm.processing ? "Saving…" : editingCourse ? "Save changes" : "Create course"}</button></div>
        </form>
    </div>
</dialog>
