<script lang="ts">
    import { useForm } from "@inertiajs/svelte";
    import { ArrowRight, BookOpen, ClipboardList, Eye, EyeOff, FileCheck2, LockKeyhole, LoaderCircle, UserRound, AlertCircle } from "lucide-svelte";

    let { flash = {} } = $props();
    const form = useForm({ name: "", password: "" });
    let showPassword = $state(false);
    let signInError = $state("");
    $effect(() => { signInError = flash.error ?? ""; });

    function signIn(event: SubmitEvent) {
        event.preventDefault();
        if ($form.processing) return;
        signInError = "";
        $form.post("/login", {
            onSuccess: (page) => {
                signInError = (page.props.flash as any)?.error ?? "";
                $form.reset("password");
                showPassword = false;
            },
            onError: () => { $form.reset("password"); showPassword = false; },
        });
    }
</script>

<svelte:head>
    <title>Sign in · Ozoveheni examination portal</title>
    <meta name="description" content="Sign in to the Ozoveheni College examination administration portal." />
</svelte:head>

<main class="dashboard-shell min-h-dvh bg-base-100 text-base-content lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]" data-theme="light">
    <section class="relative hidden min-h-dvh flex-col overflow-hidden bg-neutral text-neutral-content lg:flex" aria-labelledby="portal-heading">
        <div class="pointer-events-none absolute -right-64 top-1/4 size-150 rounded-full border border-neutral-content/5" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-48 top-[30%] size-118 rounded-full border border-neutral-content/5" aria-hidden="true"></div>
        <div class="relative flex h-full grow flex-col px-10 py-10 xl:px-16 xl:py-12">
            <div class="flex items-center gap-3.5"><div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-base-100 p-1.5"><img src="/images/logo.jpg" alt="Ozoveheni College crest" class="size-full object-contain" /></div><div><p class="text-sm font-semibold tracking-[0.16em]">OZOVEHENI</p><p class="mt-1 text-xs text-neutral-content/60">College of Health Technology</p></div></div>
            <div class="relative my-14 flex grow flex-col justify-center xl:my-20">
                <div class="mb-7 flex items-center gap-3"><span class="h-px w-8 bg-neutral-content/35"></span><p class="text-[10px] font-medium uppercase tracking-[0.24em] text-neutral-content/65">Academic administration</p></div>
                <h1 id="portal-heading" class="max-w-lg text-4xl font-semibold leading-[1.16] tracking-tight xl:text-5xl">Examination<br />administration.</h1>
                <p class="mt-6 max-w-sm text-sm leading-7 text-neutral-content/65">Manage academic records, organise examinations and prepare student results from one workspace.</p>
                <div class="mt-11 flex max-w-sm flex-col gap-5 border-t border-neutral-content/15 pt-7">
                    {#each [{ icon: BookOpen, label: "Student & academic records", number: "01" }, { icon: ClipboardList, label: "Examination management", number: "02" }, { icon: FileCheck2, label: "Results & reporting", number: "03" }] as item}
                        <div class="flex items-center gap-3"><item.icon size={17} strokeWidth={1.5} class="shrink-0 text-neutral-content/55" /><span class="grow text-xs text-neutral-content/80">{item.label}</span><span class="text-[10px] tabular-nums text-neutral-content/35">{item.number}</span></div>
                    {/each}
                </div>
            </div>
            <p class="text-[11px] text-neutral-content/45">Ozoveheni College · Examination portal</p>
        </div>
    </section>

    <section class="flex min-h-dvh flex-col px-6 py-7 sm:px-10 sm:py-10 lg:px-12 xl:px-20" aria-labelledby="sign-in-heading">
        <div class="flex items-center gap-3 lg:hidden"><img src="/images/logo.jpg" alt="Ozoveheni College crest" class="size-11 object-contain" /><div><p class="text-xs font-semibold tracking-[0.14em]">OZOVEHENI</p><p class="mt-1 text-[11px] text-base-content/60">Examination portal</p></div></div>
        <div class="my-12 flex grow items-center justify-center lg:my-8">
            <div class="w-full max-w-96">
                <div class="mb-7 inline-flex size-11 items-center justify-center rounded-xl border border-base-300 bg-base-200/70 text-base-content/60"><LockKeyhole size={20} strokeWidth={1.6} /></div>
                <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-base-content/55">Staff & administrator access</p>
                <h2 id="sign-in-heading" class="text-3xl font-semibold tracking-tight sm:text-[34px]">Welcome back</h2>
                <p class="mt-3 text-sm leading-6 text-base-content/60">Sign in with your staff account to continue.</p>
                <form onsubmit={signIn} class="mt-8 flex flex-col gap-5" aria-busy={$form.processing}>
                    {#if signInError}<div id="sign-in-error" class="flex items-start gap-2.5 rounded-lg border border-error/20 bg-error/5 p-3.5 text-sm leading-5 text-error" role="alert"><AlertCircle size={17} class="mt-0.5 shrink-0" /><span>{signInError}</span></div>{/if}
                    <div><label for="login-username" class="mb-2 block text-xs font-medium">Username</label><div class="input flex h-12 w-full items-center gap-3 rounded-lg border-base-300"><UserRound size={17} class="shrink-0 text-base-content/45" /><input id="login-username" name="name" type="text" bind:value={$form.name} autocomplete="username" autocapitalize="none" spellcheck={false} required placeholder="Enter your username" disabled={$form.processing} aria-invalid={Boolean($form.errors.name)} aria-describedby={$form.errors.name ? "username-error" : signInError ? "sign-in-error" : undefined} /></div>{#if $form.errors.name}<p id="username-error" class="mt-2 text-xs text-error" role="alert">{$form.errors.name}</p>{/if}</div>
                    <div><label for="login-password" class="mb-2 block text-xs font-medium">Password</label><div class="input flex h-12 w-full items-center gap-3 rounded-lg border-base-300"><LockKeyhole size={17} class="shrink-0 text-base-content/45" /><input id="login-password" name="password" type={showPassword ? "text" : "password"} bind:value={$form.password} autocomplete="current-password" required placeholder="Enter your password" disabled={$form.processing} aria-invalid={Boolean($form.errors.password)} aria-describedby={$form.errors.password ? "password-error" : signInError ? "sign-in-error" : undefined} /><button type="button" class="btn btn-square btn-ghost btn-sm -mr-1 text-base-content/55" onclick={() => showPassword = !showPassword} disabled={$form.processing} aria-label={showPassword ? "Hide password" : "Show password"} aria-pressed={showPassword} aria-controls="login-password">{#if showPassword}<EyeOff size={17} />{:else}<Eye size={17} />{/if}</button></div>{#if $form.errors.password}<p id="password-error" class="mt-2 text-xs text-error" role="alert">{$form.errors.password}</p>{/if}</div>
                    <button type="submit" class="btn btn-primary mt-1 h-12 w-full justify-between rounded-lg px-4 font-medium" disabled={$form.processing}><span>{$form.processing ? "Signing in…" : "Sign in"}</span>{#if $form.processing}<LoaderCircle size={17} class="animate-spin" />{:else}<ArrowRight size={17} />{/if}</button>
                </form>
                <div class="mt-7 border-t border-base-300 pt-5"><p class="text-xs leading-6 text-base-content/60">Need access or help with your password?<br /><span class="text-base-content/80">Contact your school administrator.</span></p></div>
            </div>
        </div>
        <footer class="text-center text-[11px] leading-5 text-base-content/50">Ozoveheni College of Health Technology<span class="mx-2 hidden sm:inline">·</span><br class="sm:hidden" />Examination administration</footer>
    </section>
</main>
