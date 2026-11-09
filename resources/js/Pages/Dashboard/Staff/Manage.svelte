<script lang="ts">
    import { Link, router, useForm } from "@inertiajs/svelte";
    import { Plus, Pen, KeyRound, Search, X, Users, ArrowLeft, ArrowRight, SlidersHorizontal } from "lucide-svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { users, filters, totalStaff } = $props();
    let search = $state("");
    let sort = $state("name");
    let searching = $state(false);
    let accountModal: HTMLDialogElement;
    let passwordModal: HTMLDialogElement;
    let editingStaff = $state<any>(null);
    let passwordStaff = $state<any>(null);
    let status = $state("");
    let role = $state("");
    const accountForm = useForm({ name: "", email: "", is_admin: "0", status: "active", password: "", password_confirmation: "" });
    const passwordForm = useForm({ password: "", password_confirmation: "" });
    function openAddStaff() {
        editingStaff = null;
        $accountForm.reset(); $accountForm.clearErrors();
        accountModal.showModal();
    }
    function openEditStaff(user: any) {
        editingStaff = user;
        $accountForm.reset(); $accountForm.clearErrors();
        $accountForm.name = user.name; $accountForm.email = user.email;
        $accountForm.is_admin = Number(user.is_admin) ? "1" : "0";
        $accountForm.status = user.status;
        accountModal.showModal();
    }
    function saveAccount(event: SubmitEvent) {
        event.preventDefault();
        if ($accountForm.processing) return;
        $accountForm.transform((data) => editingStaff ? { name: data.name, email: data.email, status: data.status, is_admin: data.is_admin } : data)
            .post(editingStaff ? `/dashboard/staff/${editingStaff.id}/edit` : "/dashboard/staff/add", {
                onSuccess: () => { accountModal.close(); $accountForm.reset(); },
                onError: () => { $accountForm.reset("password", "password_confirmation"); accountModal.querySelector<HTMLInputElement>('[aria-invalid="true"]')?.focus(); },
            });
    }
    function openPassword(user: any) {
        passwordStaff = user;
        $passwordForm.reset(); $passwordForm.clearErrors();
        passwordModal.showModal();
    }
    function savePassword(event: SubmitEvent) {
        event.preventDefault();
        if ($passwordForm.processing || !passwordStaff) return;
        $passwordForm.post(`/dashboard/staff/${passwordStaff.id}/update-password`, {
            onSuccess: () => { passwordModal.close(); $passwordForm.reset(); },
            onError: () => { $passwordForm.reset(); passwordModal.querySelector<HTMLInputElement>("input")?.focus(); },
        });
    }
    const number = new Intl.NumberFormat("en-GB");
    const date = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric" });
    const hasFilters = $derived(Boolean(filters.search || filters.status || filters.role || filters.sort !== "name"));

    $effect(() => {
        search = filters.search ?? "";
        sort = filters.sort ?? "name";
        status = filters.status ?? ""; role = filters.role ?? "";
    });
    function applyFilters(event?: SubmitEvent) {
        event?.preventDefault();
        searching = true;
        const query: Record<string, string> = {};
        if (search.trim()) query.search = search.trim();
        if (status) query.status = status;
        if (role) query.role = role;
        if (sort !== "name") query.sort = sort;
        router.get("/dashboard/staff", query, {
            preserveState: true, preserveScroll: true,
            onFinish: () => searching = false,
        });
    }
    function clearFilters() {
        search = ""; sort = "name"; status = ""; role = "";
        applyFilters();
    }
</script>

<svelte:head><title>Staff accounts · Ozoveheni</title></svelte:head>
<DashboardLayout title="Staff" description="Manage staff accounts, access roles and account status.">
    {#snippet actions()}
        <button onclick={openAddStaff} class="btn btn-primary gap-2 rounded-lg font-medium"><Plus size={17} /> Add staff</button>
    {/snippet}
    <div class="flex flex-col gap-6">
        <div class="card card-border bg-base-100">
            <div class="card-body flex-row flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                <div class="flex items-center gap-4"><div class="flex size-11 items-center justify-center rounded-lg bg-base-200 text-base-content/60"><Users size={21} strokeWidth={1.6} /></div><div><p class="text-xs text-base-content/60">Staff accounts</p><p class="mt-1 text-2xl font-semibold tabular-nums">{number.format(totalStaff)}</p></div></div>
                <p class="max-w-md text-sm leading-6 text-base-content/60">Control staff and administrator access to the examination portal.</p>
            </div>
        </div>
        <section class="card card-border bg-base-100" aria-labelledby="filter-heading">
            <form onsubmit={applyFilters} class="card-body gap-5 p-5 sm:p-6" aria-busy={searching}>
                <div class="flex items-center gap-2"><SlidersHorizontal size={16} class="text-base-content/60" /><h2 id="filter-heading" class="text-sm font-semibold">Find a staff account</h2></div>
                <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto]">
                    <div class="sm:col-span-2 xl:col-span-1"><label for="staff-search" class="mb-2 block text-xs font-medium text-base-content/70">Name or email</label><label class="input flex w-full items-center gap-2 rounded-lg border-base-300"><Search size={17} class="shrink-0 text-base-content/50" /><input id="staff-search" type="search" bind:value={search} maxlength={100} placeholder="Search by username or email…" /></label></div>
                    <div><label for="staff-status" class="mb-2 block text-xs font-medium text-base-content/70">Status</label><select id="staff-status" class="select w-full rounded-lg border-base-300" bind:value={status}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <div><label for="staff-role" class="mb-2 block text-xs font-medium text-base-content/70">Role</label><select id="staff-role" class="select w-full rounded-lg border-base-300" bind:value={role}><option value="">All roles</option><option value="staff">Staff</option><option value="admin">Administrator</option></select></div>
                    <div><label for="staff-sort" class="mb-2 block text-xs font-medium text-base-content/70">Sort by</label><select id="staff-sort" class="select w-full rounded-lg border-base-300" bind:value={sort}><option value="name">Name A–Z</option><option value="newest">Recently added</option></select></div>
                    <button type="submit" class="btn gap-2 rounded-lg sm:col-span-2 xl:col-span-1" disabled={searching}><Search size={16} />{searching ? "Searching…" : "Search"}</button>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4 text-xs text-base-content/60"><span>Choose filters, then press Search or Enter to apply.</span>{#if hasFilters}<button type="button" class="btn btn-ghost btn-xs gap-1.5" onclick={clearFilters} disabled={searching}><X size={13} /> Clear filters</button>{/if}</div>
            </form>
        </section>
        <section class="card card-border overflow-hidden bg-base-100" aria-labelledby="results-heading" aria-busy={searching}>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-5 sm:px-6"><div><h2 id="results-heading" class="text-base font-semibold">Staff directory</h2><p class="mt-1 text-xs text-base-content/60" role="status" aria-live="polite">{number.format(users.total)} {users.total === 1 ? "account" : "accounts"}{filters.search ? ` matching “${filters.search}”` : ""}</p></div><span class="text-xs text-base-content/60">20 records per page</span></div>
            {#if users.data.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable table must accept focus for horizontal keyboard navigation.) -->
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Staff records table, scroll horizontally if needed">
                    <table class="table min-w-200">
                        <caption class="sr-only">Staff accounts matching the selected search and filters</caption>
                        <thead class="bg-base-200/60 text-[11px] uppercase tracking-wider text-base-content/60"><tr><th scope="col" class="px-6 font-medium">Staff</th><th scope="col" class="font-medium">Role</th><th scope="col" class="font-medium">Status</th><th scope="col" class="font-medium">Date added</th><th scope="col" class="pr-6 text-right font-medium">Actions</th></tr></thead>
                        <tbody>
                            {#each users.data as user (user.id)}
                                <tr class="border-base-300/70 hover:bg-base-200/40">
                                    <th scope="row" class="px-6 py-5 font-normal"><div class="flex items-center gap-3"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/55"><Users size={18} strokeWidth={1.6} /></div><div><span class="block max-w-65 whitespace-normal text-sm font-medium leading-5">{user.name}</span><span class="mt-1 block max-w-65 break-all text-xs text-base-content/65">{user.email}</span></div></div></th>
                                    <td class="text-xs">{Number(user.is_admin) ? "Administrator" : "Staff"}</td><td><span class="badge badge-soft badge-sm" class:badge-success={user.status === "active"} class:badge-neutral={user.status !== "active"}>{user.status === "active" ? "Active" : "Inactive"}</span></td>
                                    <td class="text-xs text-base-content/65">{#if user.created_at}<time datetime={user.created_at}>{date.format(new Date(user.created_at))}</time>{:else}—{/if}</td>
                                    <td class="pr-6"><div class="flex justify-end gap-1"><button onclick={() => openEditStaff(user)} class="btn btn-ghost btn-sm gap-1.5" aria-label={`Edit ${user.name}`}><Pen size={14} /> Edit</button><button onclick={() => openPassword(user)} class="btn btn-ghost btn-sm gap-1.5" aria-label={`Change password for ${user.name}`}><KeyRound size={14} /> Password</button></div></td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 sm:px-6"><span class="text-xs text-base-content/60">Showing {users.from}–{users.to} of {number.format(users.total)}</span><nav class="flex items-center gap-2" aria-label="Staff pages">{#if users.prev_page_url}<Link href={users.prev_page_url} class="btn btn-sm gap-1.5" preserveScroll><ArrowLeft size={14} /> Previous</Link>{:else}<button class="btn btn-sm" disabled>Previous</button>{/if}<span class="px-2 text-xs text-base-content/65" aria-current="page">Page {users.current_page} of {users.last_page}</span>{#if users.next_page_url}<Link href={users.next_page_url} class="btn btn-sm gap-1.5" preserveScroll>Next <ArrowRight size={14} /></Link>{:else}<button class="btn btn-sm" disabled>Next</button>{/if}</nav></div>
            {:else}
                <div class="flex flex-col items-center px-6 py-14 text-center"><Users size={30} strokeWidth={1.4} class="mb-4 text-base-content/45" /><h3 class="text-sm font-semibold">{totalStaff ? "No staff accounts match your search" : "Add your first staff account"}</h3><p class="mt-2 max-w-90 text-sm leading-6 text-base-content/60">{totalStaff ? "Try a different name or email, or change the selected filters." : "Add a staff account to give a team member access to the portal."}</p>{#if totalStaff}<button class="btn btn-sm mt-5" onclick={clearFilters}>Clear filters</button>{:else}<button onclick={openAddStaff} class="btn btn-sm mt-5">Add staff</button>{/if}</div>
            {/if}
        </section>
    </div>
</DashboardLayout>

<dialog bind:this={accountModal} class="modal" aria-labelledby="account-heading" oncancel={(event) => { if ($accountForm.processing) event.preventDefault(); }} onclose={() => $accountForm.reset("password", "password_confirmation")}>
    <div class="modal-box max-w-xl">
        <div class="flex items-center justify-between gap-4"><h2 id="account-heading" class="text-lg font-semibold">{editingStaff ? "Edit staff account" : "Add staff account"}</h2><button type="button" class="btn btn-square btn-ghost btn-sm" aria-label="Close staff form" disabled={$accountForm.processing} onclick={() => accountModal.close()}><X size={18} /></button></div>
        <p class="mt-2 text-sm leading-6 text-base-content/65">{editingStaff ? "Update account details, role and access status." : "Create an account for a member of staff. Their account will appear first after saving."}</p>
        <form onsubmit={saveAccount} class="mt-6 flex flex-col gap-5" aria-busy={$accountForm.processing}>
            <div><label for="staff-name" class="mb-2 block text-sm font-medium">Username</label><input id="staff-name" class="input w-full" bind:value={$accountForm.name} required maxlength={255} autocomplete="off" disabled={$accountForm.processing} aria-invalid={Boolean($accountForm.errors.name)} />{#if $accountForm.errors.name}<p class="mt-2 text-sm text-error" role="alert">{$accountForm.errors.name}</p>{/if}</div>
            <div><label for="staff-email" class="mb-2 block text-sm font-medium">Email address</label><input id="staff-email" type="email" class="input w-full" bind:value={$accountForm.email} required maxlength={255} autocomplete="off" disabled={$accountForm.processing} aria-invalid={Boolean($accountForm.errors.email)} />{#if $accountForm.errors.email}<p class="mt-2 text-sm text-error" role="alert">{$accountForm.errors.email}</p>{/if}</div>
            <div class="grid gap-4 sm:grid-cols-2"><div><label for="account-role" class="mb-2 block text-sm font-medium">Role</label><select id="account-role" class="select w-full" bind:value={$accountForm.is_admin} disabled={$accountForm.processing} aria-invalid={Boolean($accountForm.errors.is_admin)}><option value="0">Staff</option><option value="1">Administrator</option></select>{#if $accountForm.errors.is_admin}<p class="mt-2 text-sm text-error" role="alert">{$accountForm.errors.is_admin}</p>{/if}</div><div><label for="account-status" class="mb-2 block text-sm font-medium">Status</label><select id="account-status" class="select w-full" bind:value={$accountForm.status} disabled={$accountForm.processing} aria-invalid={Boolean($accountForm.errors.status)}><option value="active">Active</option><option value="inactive">Inactive</option></select>{#if $accountForm.errors.status}<p class="mt-2 text-sm text-error" role="alert">{$accountForm.errors.status}</p>{/if}</div></div>
            <p class="text-xs leading-5 text-base-content/65">Administrators can manage staff accounts and academic records. Inactive accounts cannot access the portal.</p>
            {#if !editingStaff}<div><label for="account-password" class="mb-2 block text-sm font-medium">Password</label><input id="account-password" type="password" class="input w-full" bind:value={$accountForm.password} required minlength={8} autocomplete="new-password" disabled={$accountForm.processing} aria-invalid={Boolean($accountForm.errors.password)} /><p class="mt-2 text-xs text-base-content/60">Use at least 8 characters.</p>{#if $accountForm.errors.password}<p class="mt-2 text-sm text-error" role="alert">{$accountForm.errors.password}</p>{/if}</div><div><label for="account-confirmation" class="mb-2 block text-sm font-medium">Confirm password</label><input id="account-confirmation" type="password" class="input w-full" bind:value={$accountForm.password_confirmation} required autocomplete="new-password" disabled={$accountForm.processing} /></div>{/if}
            <div class="modal-action mt-1 border-t border-base-300 pt-5"><button type="button" class="btn" disabled={$accountForm.processing} onclick={() => accountModal.close()}>Cancel</button><button type="submit" class="btn" disabled={$accountForm.processing}>{$accountForm.processing ? "Saving…" : editingStaff ? "Save changes" : "Create account"}</button></div>
        </form>
    </div>
</dialog>
<dialog bind:this={passwordModal} class="modal" aria-labelledby="password-heading" oncancel={(event) => { if ($passwordForm.processing) event.preventDefault(); }} onclose={() => $passwordForm.reset()}>
    <div class="modal-box max-w-lg"><h2 id="password-heading" class="text-lg font-semibold">Change password</h2><p class="mt-2 break-words text-sm text-base-content/65">Set a new password for {passwordStaff?.name} ({passwordStaff?.email}).</p><form onsubmit={savePassword} class="mt-6 flex flex-col gap-5" aria-busy={$passwordForm.processing}>
        <div><label for="new-password" class="mb-2 block text-sm font-medium">New password</label><input id="new-password" type="password" class="input w-full" bind:value={$passwordForm.password} required minlength={8} autocomplete="new-password" disabled={$passwordForm.processing} /><p class="mt-2 text-xs text-base-content/60">Use at least 8 characters.</p>{#if $passwordForm.errors.password}<p class="mt-2 text-sm text-error" role="alert">{$passwordForm.errors.password}</p>{/if}</div><div><label for="new-password-confirmation" class="mb-2 block text-sm font-medium">Confirm password</label><input id="new-password-confirmation" type="password" class="input w-full" bind:value={$passwordForm.password_confirmation} required autocomplete="new-password" disabled={$passwordForm.processing} /></div><div class="modal-action mt-1 border-t border-base-300 pt-5"><button type="button" class="btn" disabled={$passwordForm.processing} onclick={() => passwordModal.close()}>Cancel</button><button type="submit" class="btn" disabled={$passwordForm.processing}>{$passwordForm.processing ? "Saving…" : "Update password"}</button></div>
    </form></div>
</dialog>
