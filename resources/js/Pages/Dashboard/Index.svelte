<script lang="ts">
    import { router } from "@inertiajs/svelte";
    import DashboardLayout from "../../Components/Layouts/DashboardLayout.svelte";
    import {
        Users,
        BookOpen,
        FileQuestion,
        Activity,
        Calendar,
        Plus,
        Settings,
        GraduationCap,
    } from "lucide-svelte";
    import Time from "svelte-time";

    let {
        totalStudents,
        activeExams,
        questionsBank,
        departments,
        upcomingExams,
    } = $props();

    // Mock data for the dashboard
    const stats = $derived([
        {
            title: "Total Students",
            value: totalStudents,
            icon: Users,
            color: "text-blue-600",
            bg: "bg-blue-100",
        },
        {
            title: "Active Exams",
            value: activeExams,
            icon: Activity,
            color: "text-green-600",
            bg: "bg-green-100",
        },
        {
            title: "Questions Bank",
            value: questionsBank,
            icon: FileQuestion,
            color: "text-purple-600",
            bg: "bg-purple-100",
        },
        {
            title: "Departments",
            value: departments,
            icon: GraduationCap,
            color: "text-orange-600",
            bg: "bg-orange-100",
        },
    ]);
</script>

<DashboardLayout title="Dashboard Overview">
    <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2 lg:grid-cols-4">
        {#each stats as stat}
            <div class="shadow-sm card bg-base-100">
                <div
                    class="flex flex-row items-center justify-between card-body"
                >
                    <div>
                        <h2 class="text-sm font-medium text-base-content/70">
                            {stat.title}
                        </h2>
                        <p class="mt-2 text-3xl font-bold">{stat.value}</p>
                    </div>
                    <div class="p-3 rounded-full {stat.bg}">
                        <stat.icon class="w-6 h-6 {stat.color}" />
                    </div>
                </div>
            </div>
        {/each}
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="col-span-1 lg:col-span-2">
            <div class="shadow-sm card bg-base-100">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">Upcoming Exams</h3>
                        <button
                            onclick={() => router.visit("/dashboard/exam")}
                            class="btn btn-ghost btn-xs">View All</button
                        >
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Date & Time</th>
                                    <th>Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each upcomingExams as exam}
                                    <tr class="hover">
                                        <td>
                                            <div class="font-bold">
                                                {exam.course.name}
                                            </div>
                                            <div class="text-xs opacity-50">
                                                {exam.course.code}
                                            </div>
                                        </td>
                                        <td>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Calendar size="14" />
                                                <span class="text-sm">
                                                    <Time
                                                        timestamp={exam.start}
                                                        relative
                                                    />
                                                </span>
                                            </div>
                                        </td>
                                        <td>{exam.exam.department.name}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-1">
            <div class="shadow-sm card bg-base-100">
                <div class="card-body">
                    <h3 class="mb-4 text-lg font-bold">Quick Actions</h3>
                    <div class="flex flex-col gap-3">
                        <button
                            onclick={() => router.visit("/dashboard/exam")}
                            class="justify-start btn btn-primary"
                        >
                            <Plus size="18" /> Create New Exam
                        </button>
                        <button
                            onclick={() => router.visit("/dashboard/student")}
                            class="justify-start btn btn-outline"
                        >
                            <Users size="18" /> Student Management
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>
