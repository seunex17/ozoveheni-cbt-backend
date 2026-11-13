<script lang="ts">
    import { Link, router } from "@inertiajs/svelte";
    import { History, Search, SlidersHorizontal, ArrowLeft, ArrowRight, X, Eye, ShieldCheck } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { logs, filters, types, errors = {} } = $props();
    let search = $state("");
    let action = $state("");
    let type = $state("");
    let from = $state("");
    let to = $state("");
    let searching = $state(false);
    let selected = $state<any>(null);
    let dialog: HTMLDialogElement;
    const actions: Record<string, string> = { created: "Created", updated: "Updated", deleted: "Deleted", attempts_cleared: "Attempts cleared" };
    const labels: Record<string, string> = { exam: "Exam", paper: "Course paper", question: "Question", option: "Answer option", result: "Result", student: "Student", department: "Department", course: "Course", staff: "Staff account", level: "Level" };
    const date = new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short", timeZone: "Africa/Lagos" });
    const fields = $derived(selected ? [...new Set([...Object.keys(selected.before_values ?? {}), ...Object.keys(selected.after_values ?? {})])] : []);
    const fieldNames: Record<string, string> = { first_ca: "First CA", second_ca: "Second CA", exam: "Exam score", total_ca: "Total CA", is_admin: "Administrator", is_correct: "Correct answer", question_text: "Question text", option_text: "Option text", reg_no: "Registration number", dob: "Date of birth" };
    const fieldLabel = (field: string) => fieldNames[field] ?? field.replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase());
    const value = (object: any, field: string) => !Object.hasOwn(object ?? {}, field) ? "—" : object[field] === null ? "Empty" : typeof object[field] === "boolean" ? object[field] ? "Yes" : "No" : String(object[field]);
    $effect(() => { search = filters.search; action = filters.action; type = filters.type; from = filters.from; to = filters.to; });
    function apply(event?: SubmitEvent) {
        event?.preventDefault();
        searching = true;
        const query: Record<string, string> = {};
        for (const [key, item] of Object.entries({ search: search.trim(), action, type, from, to })) if (item) query[key] = item;
        router.get("/dashboard/audit-history", query, { preserveState: true, preserveScroll: true, onFinish: () => searching = false });
    }
    function clear() { search = ""; action = ""; type = ""; from = ""; to = ""; apply(); }
    function details(log: any) { selected = log; dialog.showModal(); }
</script>

<svelte:head><title>Audit history · Ozoveheni</title></svelte:head>
<DashboardLayout title="Audit history" description="See who changed a record, what changed and when.">
    <div class="flex flex-col gap-6">
        <div class="card card-border bg-base-100"><div class="card-body flex-row items-start gap-4 p-5 sm:p-6"><div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><ShieldCheck size={22} strokeWidth={1.6} /></div><div><h2 class="text-sm font-semibold">A record of staff actions</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-base-content/60">Exam and academic record changes, score edits and attempt resets are recorded from when audit history was enabled. Earlier activity is unavailable. Student API activity and scheduled system updates are excluded.</p></div></div></div>
        <section class="card card-border bg-base-100" aria-labelledby="audit-filter-heading">
            <form onsubmit={apply} class="card-body gap-5 p-5 sm:p-6" aria-busy={searching}>
                <div class="flex items-center gap-2"><SlidersHorizontal size={16} class="text-base-content/55" /><h2 id="audit-filter-heading" class="text-sm font-semibold">Find an activity</h2></div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div><label for="audit-search" class="mb-2 block text-xs font-medium text-base-content/70">Staff name or record</label><label class="input flex w-full items-center gap-2 rounded-lg border-base-300"><Search size={16} class="text-base-content/45" /><input id="audit-search" bind:value={search} type="search" maxlength={100} placeholder="Name, student registration or exam…" /></label></div>
                    <div><label for="audit-action" class="mb-2 block text-xs font-medium text-base-content/70">Action</label><select id="audit-action" bind:value={action} class="select w-full rounded-lg border-base-300"><option value="">All actions</option>{#each Object.entries(actions) as [key, label]}<option value={key}>{label}</option>{/each}</select></div>
                    <div><label for="audit-type" class="mb-2 block text-xs font-medium text-base-content/70">Record type</label><select id="audit-type" bind:value={type} class="select w-full rounded-lg border-base-300"><option value="">All record types</option>{#each types as item}<option value={item}>{labels[item] ?? item}</option>{/each}</select></div>
                    <div><label for="audit-from" class="mb-2 block text-xs font-medium text-base-content/70">From date</label><input id="audit-from" bind:value={from} type="date" class="input w-full rounded-lg border-base-300" /></div>
                    <div><label for="audit-to" class="mb-2 block text-xs font-medium text-base-content/70">To date</label><input id="audit-to" bind:value={to} type="date" min={from || undefined} class="input w-full rounded-lg border-base-300" /></div>
                    <div class="flex items-end gap-2"><button type="submit" class="btn grow gap-2 rounded-lg" disabled={searching}><Search size={16} />{searching ? "Searching…" : "Search"}</button><button type="button" class="btn btn-square" onclick={clear} disabled={searching} aria-label="Clear audit filters"><X size={16} /></button></div>
                </div>
                {#if Object.keys(errors).length}<p class="text-sm text-error" role="alert">{Object.values(errors).join(" ")}</p>{/if}
                <p class="border-t border-base-300 pt-4 text-xs text-base-content/55">Press Search or Enter to apply. Dates and times use Lagos time.</p>
            </form>
        </section>
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="audit-records-heading" aria-busy={searching}>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-5 sm:px-6"><div><h2 id="audit-records-heading" class="text-base font-semibold">Activity records</h2><p class="mt-1 text-xs text-base-content/60" role="status">{logs.total} matching records · newest first</p></div><span class="text-xs text-base-content/55">20 records per page</span></div>
            {#if logs.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The table accepts focus for horizontal keyboard scrolling.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Audit history, scroll horizontally if needed">
                    <table class="table min-w-200"><caption class="sr-only">Staff changes matching the selected filters</caption><thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6">When / who</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col" class="pr-6 text-right">Details</th></tr></thead><tbody>
                    {#each logs.data as log (log.id)}<tr class="hover:bg-base-200/40"><td class="px-6 py-5"><p class="text-xs text-base-content/65"><time datetime={log.created_at}>{date.format(new Date(log.created_at))}</time></p><p class="mt-1 text-sm font-medium">{log.actor_name}</p></td><td><span class="badge badge-soft badge-sm {log.action === 'deleted' || log.action === 'attempts_cleared' ? 'badge-warning' : 'badge-ghost'}">{actions[log.action] ?? log.action}</span></td><td><p class="text-xs text-base-content/55">{labels[log.subject_type] ?? log.subject_type} #{log.subject_id}</p><p class="mt-1 max-w-100 whitespace-normal break-words text-sm leading-6">{log.subject_label}</p></td><td class="pr-6 text-right"><button class="btn btn-ghost btn-sm gap-2" onclick={() => details(log)} aria-label={`View audit entry ${log.id}`}><Eye size={15} /> View</button></td></tr>{/each}
                    </tbody></table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 sm:px-6"><span class="text-xs text-base-content/60">Showing {logs.from}–{logs.to} of {logs.total}</span><nav class="flex flex-wrap items-center gap-2" aria-label="Audit history pages">{#if logs.prev_page_url}<Link href={logs.prev_page_url} class="btn btn-sm" preserveScroll><ArrowLeft size={14} /> Previous</Link>{:else}<button class="btn btn-sm" disabled>Previous</button>{/if}<span class="text-xs text-base-content/60">Page {logs.current_page} of {logs.last_page}</span>{#if logs.next_page_url}<Link href={logs.next_page_url} class="btn btn-sm" preserveScroll>Next <ArrowRight size={14} /></Link>{:else}<button class="btn btn-sm" disabled>Next</button>{/if}</nav></div>
            {:else}<div class="flex flex-col items-center px-6 py-14 text-center"><History size={30} strokeWidth={1.4} class="mb-4 text-base-content/45" /><h3 class="text-sm font-semibold">No activity matches these filters</h3><p class="mt-2 max-w-md text-sm leading-6 text-base-content/60">New staff changes will appear here. Try a wider date range or clear the filters.</p></div>{/if}
        </section>
    </div>
</DashboardLayout>

<dialog bind:this={dialog} class="modal" aria-labelledby="audit-detail-heading">
    <div class="modal-box max-w-4xl"><div class="flex items-start justify-between gap-4"><div><h2 id="audit-detail-heading" class="text-lg font-semibold">Activity details</h2><p class="mt-2 text-sm text-base-content/60">{selected?.actor_name} · {selected ? date.format(new Date(selected.created_at)) : ""} · Lagos time</p></div><button class="btn btn-square btn-ghost btn-sm" onclick={() => dialog.close()} aria-label="Close activity details"><X size={18} /></button></div>
        {#if selected}<div class="mt-5 rounded-lg border border-base-300 bg-base-200/40 p-4"><span class="text-xs text-base-content/60">{actions[selected.action]} · {labels[selected.subject_type]} #{selected.subject_id}</span><p class="mt-2 break-words text-sm font-medium">{selected.subject_label}</p></div>
            {#if fields.length}<div class="mt-5 overflow-x-auto"><table class="table min-w-140"><caption class="sr-only">Before and after values</caption><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>{#each fields as field}<tr><th class="align-top text-xs font-medium">{fieldLabel(field)}</th><td class="max-w-70 whitespace-pre-wrap break-words align-top text-xs leading-6">{value(selected.before_values, field)}</td><td class="max-w-70 whitespace-pre-wrap break-words align-top text-xs leading-6">{value(selected.after_values, field)}</td></tr>{/each}</tbody></table></div>{/if}
            {#if selected.metadata?.password_changed}<p class="mt-5 text-sm text-base-content/65">Password set or changed. Password values are never recorded.</p>{/if}
            {#if selected.metadata?.attempts_removed !== undefined}<div class="mt-5 space-y-2 text-sm leading-6 text-base-content/65"><p><strong>{selected.metadata.attempts_removed}</strong> attempts removed · {selected.metadata.scope === "student" ? "Selected student" : "All students in this exam"}</p>{#if selected.metadata.student}<p>{selected.metadata.student}</p>{/if}<p>Saved answers and hall entries were removed. Stored result scores were kept.</p></div>{/if}
            {#if selected.metadata?.note}<p class="mt-5 text-xs leading-6 text-base-content/60">{selected.metadata.note}</p>{/if}
            <p class="mt-5 text-xs text-base-content/50">History entry #{selected.id}. Actor and record names are preserved as recorded at the time.</p>
        {/if}
        <div class="modal-action"><form method="dialog"><button class="btn">Close</button></form></div>
    </div>
</dialog>
