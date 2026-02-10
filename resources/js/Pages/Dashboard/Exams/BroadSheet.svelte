<script lang="ts">
    import { onMount } from "svelte";
    import DashboardLayout from "../../../Components/Layouts/DashboardLayout.svelte";

    let { exam } = $props();
    let pdfUrl = $state("");

    onMount(() => {
        pdfUrl = `/reports/${exam.uuid}`;
    });
</script>

<DashboardLayout title="{exam.title} Broadsheet" goBack={true}>
    <div class="card w-full bg-base-100">
        <div class="card-body w-full overflow-auto">
            <object
                data={pdfUrl}
                type="application/pdf"
                width="100%"
                height="600px"
                aria-label="PDF Broadsheet for {exam.title}"
            >
                <p>
                    It appears your browser doesn't support embedded PDFs.
                    <a href={pdfUrl}>Click here to download the PDF file.</a>
                </p>
            </object>
        </div>
    </div>
</DashboardLayout>
