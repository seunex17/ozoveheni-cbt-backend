<script lang="ts">
    import { Download, X } from "lucide-svelte";
    import { installPwa, pwaState } from "../States/pwa.svelte";
    let dialog: HTMLDialogElement;
    async function install() {
        if (pwaState.installing) return;
        if (!await installPwa()) dialog.showModal();
    }
</script>

{#if !pwaState.installed}
    <button type="button" class="btn btn-ghost btn-sm w-full gap-2" onclick={install} disabled={pwaState.installing}><Download size={16} />{pwaState.installing ? "Opening installer…" : "Install app"}</button>
{/if}
<dialog bind:this={dialog} class="modal text-base-content" data-theme="light" aria-labelledby="pwa-install-title">
    <div class="modal-box max-w-md"><div class="flex items-start justify-between gap-4"><h2 id="pwa-install-title" class="text-lg font-semibold">Install Ozoveheni</h2><button class="btn btn-square btn-ghost btn-sm" onclick={() => dialog.close()} aria-label="Close installation help"><X size={18} /></button></div>
        {#if !pwaState.secure}<p class="mt-4 text-sm leading-6 text-base-content/65">This address cannot install the app. Open the school server’s trusted HTTPS address, or use localhost when working on the server PC itself.</p>
        {:else}<p class="mt-4 text-sm leading-6 text-base-content/65">In Chrome or Microsoft Edge, open the browser menu and choose <strong>Install app</strong> or <strong>Apps → Install this site as an app</strong>. You may also see an install icon beside the address bar.</p><p class="mt-3 text-sm leading-6 text-base-content/65">After installation, launch Ozoveheni from your desktop or application launcher. If you just opened the site, allow app setup to finish and try again.</p>{/if}
        {#if pwaState.error}<p class="mt-4 text-sm text-error" role="status">{pwaState.error}</p>{/if}
        <p class="mt-4 text-xs leading-5 text-base-content/55">The school server must remain available. PDF viewing, printing and exports still use the server.</p>
        <div class="modal-action"><form method="dialog"><button class="btn">Close</button></form></div>
    </div>
</dialog>
