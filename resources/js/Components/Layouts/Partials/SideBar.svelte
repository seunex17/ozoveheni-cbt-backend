<script lang="ts">
    import { Link, page } from "@inertiajs/svelte";
    import { Folder, GraduationCap, Home, Users } from "lucide-svelte";

    const segments = $derived($page.url.split("/").filter(Boolean));

    const links = $derived([
        {
            name: "Dashboard",
            route: "/dashboard",
            icon: Home,
            active: segments.length === 1 && segments[0] === "dashboard",
        },
        {
            name: "Staffs",
            route: "/dashboard/staff",
            icon: Users,
            active: $page.url.startsWith("/dashboard/staff"),
        },
        {
            name: "Departments",
            route: "/dashboard/department",
            icon: Folder,
            active: $page.url.startsWith("/dashboard/department"),
        },
        {
            name: "Students",
            route: "/dashboard/student",
            icon: GraduationCap,
            active: $page.url.startsWith("/dashboard/student"),
        },
    ]);
</script>

<div class="h-full p-3 overflow-auto w-60 bg-base-100">
    <ul class="space-y-3">
        {#each links as link}
            <li>
                <Link
                    href={link.route}
                    class={[
                        "flex items-center gap-3 text-sm px-6 py-2.5 transition-colors duration-200 rounded-lg",
                        link.active
                            ? "bg-primary text-primary-content"
                            : "hover:bg-primary/20",
                    ].join(" ")}
                >
                    <link.icon size="14" />
                    <span>{link.name}</span>
                </Link>
            </li>
        {/each}
    </ul>
</div>
