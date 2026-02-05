<script lang="ts">
    import { Toaster, toast } from "svelte-sonner";
    import { page } from "@inertiajs/svelte";

    let { children } = $props();

    $effect(() => {
        if ($page.props.flash?.success) {
            toast.success($page.props.flash.success);
        }

        if ($page.props.flash?.error) {
            toast.error($page.props.flash.error);
        }
    });
</script>

{@render children()}

<Toaster richColors position="top-right" />
