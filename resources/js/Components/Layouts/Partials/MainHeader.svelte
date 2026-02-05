<script lang="ts">
    import { Link, router } from "@inertiajs/svelte";
    import { Power } from "lucide-svelte";
</script>

<div class="sticky top-0 shadow-sm navbar bg-base-100">
    <div class="flex-1">
        <Link href="/dashboard" class="text-xl btn btn-ghost">
            <img src="/images/logo.jpg" class="w-10" alt="" />
        </Link>
    </div>
    <div class="flex-none">
        <button
            onclick={() => router.post("/logout")}
            class="btn btn-sm btn-error btn-soft"
        >
            <Power size="18" /> Logout
        </button>
    </div>
</div>
